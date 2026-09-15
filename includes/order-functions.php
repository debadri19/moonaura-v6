<?php
/* ===================================================================
   ORDER FUNCTIONS
   -------------------------------------------------------------------
   Phase 2C: turning a session cart into a real order. This file
   holds the order number generator (now transaction-safe), the
   checkout field validators, and the order-creation transaction
   itself - kept together since they're all part of the same
   "commerce" concern, separate from cart-functions.php (session
   cart) and product-functions.php (catalog display).
=================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stock-functions.php';
require_once __DIR__ . '/tax-functions.php';


/* ==========================================
   ORDER / INVOICE NUMBER GENERATION (transaction-safe, daily reset)
   -------------------------------------------------
   Order format:   MOAOD<YYYYMMDD><4-digit sequence>   e.g. MOAOD202601290001
   Invoice format: MOAINV<YYYYMMDD><4-digit sequence>  e.g. MOAINV202601290001

   Both sequences reset to 1 at the start of each calendar day, and
   are tracked independently of each other (an order number and an
   invoice number generated on the same day do NOT share a counter).

   IMPORTANT: generate_order_number() must be called from INSIDE a
   database transaction that is still open when it runs (see
   create_order() below). It locks today's row for the given
   sequence type with SELECT ... FOR UPDATE, so if two things happen
   at the exact same instant, the second one simply waits for the
   first transaction to finish before it can read/increment the
   number - guaranteeing no two orders (or invoices) ever collide.

   generate_invoice_number() is NOT called anywhere yet - invoice
   generation itself is a future phase (see the "Invoice Strategy"
   note in create_order() below). It's ready here so that whenever
   that feature is built, it already has a correct, safe number
   generator to call - it should be called from inside its own
   transaction the same way generate_order_number() is used here.
========================================== */

function generate_order_number(): string
{
    return generate_daily_sequence_number('order', 'MOAOD');
}

function generate_invoice_number(): string
{
    return generate_daily_sequence_number('invoice', 'MOAINV');
}

function generate_daily_sequence_number(string $sequenceType, string $prefix): string
{
    $pdo         = db();
    $today       = date('Y-m-d');   // the sequence's lookup key (resets when this changes)
    $dateCompact = date('Ymd');     // the date digits embedded in the number itself

    // Make sure a row exists for this type+day. INSERT ... ON
    // DUPLICATE KEY is atomic, so this is safe even if two
    // transactions both hit the very first order of a new day at
    // the same time.
    $pdo->prepare(
        'INSERT INTO daily_sequences (sequence_type, sequence_date, next_number)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE sequence_type = sequence_type'
    )->execute([$sequenceType, $today]);

    // Lock this row until our transaction commits or rolls back - no
    // other transaction can read it until then.
    $stmt = $pdo->prepare(
        'SELECT next_number FROM daily_sequences WHERE sequence_type = ? AND sequence_date = ? FOR UPDATE'
    );
    $stmt->execute([$sequenceType, $today]);
    $nextNumber = (int) $stmt->fetchColumn();

    $pdo->prepare(
        'UPDATE daily_sequences SET next_number = next_number + 1 WHERE sequence_type = ? AND sequence_date = ?'
    )->execute([$sequenceType, $today]);

    return sprintf('%s%s%04d', $prefix, $dateCompact, $nextNumber);
}


/* ==========================================
   VALIDATION HELPERS
========================================== */

/* ------------------------------------------
   MOBILE NUMBER VALIDATION - INDIA-SPECIFIC
   -------------------------------------------------
   This checks the INDIAN mobile number format only
   (10 digits, starting 6-9, optional "+91"/"91"
   prefix). It is deliberately isolated in this one
   function so that supporting other countries later
   is a single swap: replace the body of
   is_valid_mobile_number() (or branch on a country
   parameter) without touching checkout.php,
   account/register.php, or anywhere else that calls
   it - they only care about the true/false result.
------------------------------------------ */

// Strips everything down to the bare 10 digits (removes spaces,
// dashes, and an optional "+91"/"91" country code). Used both by
// is_valid_mobile_number() below AND anywhere a phone number is
// SAVED to the database, so every phone number is stored in the
// same consistent format - this is what makes guest-order-to-account
// linking by phone actually work reliably (Phase 3 hardening; before
// this, two differently-formatted entries of the same real number
// wouldn't match each other in the database).
function normalize_mobile_number(string $mobile): string
{
    $digitsOnly = preg_replace('/\D/', '', $mobile);

    if (strlen($digitsOnly) === 12 && str_starts_with($digitsOnly, '91')) {
        $digitsOnly = substr($digitsOnly, 2);
    }

    return $digitsOnly;
}

function is_valid_mobile_number(string $mobile): bool
{
    return (bool) preg_match('/^[6-9]\d{9}$/', normalize_mobile_number($mobile));
}

// Indian PIN codes: exactly 6 digits, first digit can't be 0.
function is_valid_pin_code(string $pincode): bool
{
    return (bool) preg_match('/^[1-9][0-9]{5}$/', trim($pincode));
}


/* ==========================================
   GET AN ORDER BY ITS ORDER NUMBER
   Shared lookup used by the payment pages.
========================================== */

function get_order_by_number(string $orderNumber): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
    $stmt->execute([$orderNumber]);

    $order = $stmt->fetch();

    return $order ?: null;
}


/* ==========================================
   CREATE AN ORDER FROM THE CART
   -------------------------------------------------
   $cartItems  - the ALREADY-LIVE-VALIDATED, available-only items
                 from get_cart_items_with_details() (checkout.php is
                 responsible for filtering out unavailable ones and
                 confirming the list isn't empty before calling this).
   $customer   - ['full_name', 'email', 'mobile']
   $address    - ['address_line1', 'address_line2', 'landmark', 'city', 'state', 'pincode']
   $customerId - the logged-in customer's id (Phase 2D), or null for
                 a guest checkout. Passing it here means a logged-in
                 customer's order is linked from the moment it's
                 created, instead of relying on link_guest_orders_to_customer()
                 to connect it on their next login.
   $paymentMethod - the payment method the customer chose at
                 checkout: 'cod', or the specific online gateway name
                 they selected among whatever's currently enabled
                 (e.g. 'razorpay' - see
                 PaymentManager::getEnabledGatewayNames(), multiple
                 can be enabled at once) - this function stores
                 whatever string it's given, it has no gateway-specific
                 knowledge itself. Stored on the order immediately;
                 for an online gateway this is provisional until
                 payment actually succeeds (payment_status stays
                 'pending' until then), for 'cod' the order is
                 considered accepted right away (see checkout.php).
   $shippingCharge / $discount - optional explicit amounts (INR).
                 Both default to 0.00 and stay backward-compatible:
                 checkout.php calls without them and keeps today's
                 "no shipping-rules / no coupon table yet" behaviour.
                 admin/order-create.php (Phase 5C) passes real values
                 an admin entered; both are clamped to >= 0 here and
                 discount is clamped to never exceed the subtotal.

   GST & TAX (Phase 5F):
   - Product prices are GST-INCLUSIVE. Each line's tax is DERIVED
     server-side from the product's own gst_rate (snapshotted from the
     product row the caller passes in) using the reverse inclusive-tax
     formula in includes/tax-functions.php. GST is NEVER added on top
     of the inclusive prices, so:
         grand_total = subtotal - discount + shipping_charge
   - The per-line and order-level breakdown (taxable_value, gst_amount,
     cgst/sgst/igst) is snapshotted into order_items + orders at
     creation time, so historical orders never change if a product's
     GST rate is edited later.
   - Tax type (CGST+SGST vs IGST) is resolved from the order's
     shipping state vs the settings.business_state seller state; if it
     cannot be reliably determined, tax_type is stored NULL and the GST
     is split conservatively as CGST+SGST (see resolve_tax_type()).
   - Nothing client-supplied is trusted: rates come from the product
     row, amounts are recomputed here.

   Returns ['id' => int, 'order_number' => string] on success. Throws
   on any failure, after rolling back - checkout.php decides what to
   show the customer if that happens.
========================================== */

function create_order(array $cartItems, array $customer, array $address, ?int $customerId = null, string $paymentMethod = 'cod', float $shippingCharge = 0.0, float $discount = 0.0): array
{
    $pdo = db();

    // Normalize email/phone before they ever touch the database - see
    // normalize_email() / normalize_mobile_number() for why (Phase 3
    // hardening: this is what makes guest-order-to-account linking by
    // email/phone actually match reliably).
    $customerEmail = normalize_email($customer['email']);
    $customerPhone = normalize_mobile_number($customer['mobile']);

    /* ---------- Totals + tax (server-side only) ---------- */

    $subtotal = 0.0;
    $taxLines = [];
    $taxType  = resolve_tax_type((string) ($address['state'] ?? ''));

    /* Authoritative GST rates, re-read from the products table for the
       whole cart in one indexed query. A caller-supplied product row
       (whose rate is derived from a server-side SELECT today) can never
       smuggle a rate in - the products table is always the source of
       truth for the rate. Deleted products fall back to the rate the
       caller's row carried (normalized) so a soft-deleted item still
       snapshots sensibly. */
    $authoritativeRates = [];
    $productIds = array_values(array_unique(array_map(
        static fn (array $i): int => (int) ($i['product']['id'] ?? 0),
        $cartItems
    )));

    if (count($productIds) > 0) {
        $in = implode(',', array_fill(0, count($productIds), '?'));
        $rateStmt = $pdo->prepare("SELECT id, gst_rate FROM products WHERE id IN ($in)");
        $rateStmt->execute($productIds);

        foreach ($rateStmt->fetchAll() as $rateRow) {
            $authoritativeRates[(int) $rateRow['id']] = normalize_gst_rate($rateRow['gst_rate']) ?? 0.0;
        }
    }

    foreach ($cartItems as $item) {
        $lineTotal = round((float) $item['line_total'], 2);
        $subtotal += $lineTotal;

        $product = $item['product'];
        $productId = (int) ($product['id'] ?? 0);
        $gstRate = $authoritativeRates[$productId] ?? (normalize_gst_rate($product['gst_rate'] ?? 0.0) ?? 0.0);
        $taxLines[] = compute_line_tax($lineTotal, $gstRate, $taxType) + ['gst_rate' => $gstRate];
    }

    $subtotal     = round($subtotal, 2);
    $discount       = min(max(0.0, (float) $discount), $subtotal); // never below 0, never above subtotal
    $shippingCharge = max(0.0, (float) $shippingCharge);

    $orderTax = aggregate_line_tax($taxLines);
    $gstRate  = order_level_gst_rate($taxLines);
    $gstAmount = $orderTax['gst_amount'];
    $grandTotal = round($subtotal - $discount + $shippingCharge, 2);

    $pdo->beginTransaction();

    try {

        $orderNumber = generate_order_number();

        /* ---------- orders ---------- */

        $orderStmt = $pdo->prepare(
            'INSERT INTO orders (
                order_number, invoice_number, invoice_generated_at,
                customer_name, customer_email, customer_phone, user_id,
                currency, gst_rate, subtotal, discount, shipping_charge, gst_amount, grand_total,
                taxable_value, cgst_amount, sgst_amount, igst_amount, tax_type,
                payment_status, order_status, payment_method, notes
             ) VALUES (
                ?, NULL, NULL,
                ?, ?, ?, ?,
                "INR", ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                "pending", "pending", ?, NULL
             )'
        );
        $orderStmt->execute([
            $orderNumber,
            $customer['full_name'], $customerEmail, $customerPhone, $customerId,
            $gstRate, $subtotal, $discount, $shippingCharge, $gstAmount, $grandTotal,
            $orderTax['taxable_value'], $orderTax['cgst_amount'], $orderTax['sgst_amount'], $orderTax['igst_amount'],
            in_array($taxType, ['intra', 'inter'], true) ? $taxType : null,
            $paymentMethod,
        ]);

        $orderId = (int) $pdo->lastInsertId();

        // Timeline: every order starts life as "Order Placed"
        // (order_status = 'pending'). Logged inside the same
        // transaction so an order always has at least one event.
        log_order_status_event($orderId, 'pending');

        /* ---------- order_items (snapshot of each product) ---------- */

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (
                order_id, product_id, product_name, product_slug,
                unit_price, quantity, gst_rate, line_total,
                taxable_value, cgst_amount, sgst_amount, igst_amount
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($cartItems as $i => $item) {
            $product   = $item['product'];
            $lineTax   = $taxLines[$i] ?? compute_line_tax(0.0, 0.0, 'unknown');
            $itemStmt->execute([
                $orderId,
                $product['id'],
                $product['name'],
                $product['slug'],
                $product['sell_price'],
                $item['quantity'],
                $lineTax['gst_rate'],
                round((float) $item['line_total'], 2),
                $lineTax['taxable_value'],
                $lineTax['cgst_amount'],
                $lineTax['sgst_amount'],
                $lineTax['igst_amount'],
            ]);
        }

        /* ---------- order_addresses ---------- */

        $addressStmt = $pdo->prepare(
            'INSERT INTO order_addresses (
                order_id, full_name, phone,
                address_line1, address_line2, landmark,
                city, state, postal_code, country
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "India")'
        );
        $addressStmt->execute([
            $orderId,
            $customer['full_name'],
            $customerPhone,
            $address['address_line1'],
            $address['address_line2'] !== '' ? $address['address_line2'] : null,
            $address['landmark'] !== '' ? $address['landmark'] : null,
            $address['city'],
            $address['state'],
            $address['pincode'],
        ]);

        $pdo->commit();

        return ['id' => $orderId, 'order_number' => $orderNumber];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('create_order() failed and was rolled back: ' . $e->getMessage());
        throw $e;
    }
}

/* ==========================================
   ORDER STATUS TIMELINE
   -------------------------------------------------
   Appends one row to order_status_history for an
   order-status/payment milestone. The customer
   timeline (account/order-detail.php) and any future
   admin timeline are driven by these real records,
   never by synthesising entries.

   $status uses the orders.order_status vocabulary
   ('pending', 'processing', 'shipped', 'delivered',
   'cancelled') plus the synthetic 'payment_confirmed'
   marker for the "Payment Confirmed" milestone - it
   lives in the same column so one timeline query
   covers every event in order.
========================================== */

function log_order_status_event(int $orderId, string $status, ?string $note = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO order_status_history (order_id, order_status, note, created_at)
         VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$orderId, $status, $note]);
}

/* ==========================================
   TIMELINE EVENT DISPLAY LABEL
   -------------------------------------------------
   Human-friendly label for one order_status_history
   row (the display name, not the stored vocabulary).
========================================== */

function timeline_event_label(string $status): string
{
    return match ($status) {
        'pending'           => 'Order Placed',
        'payment_confirmed' => 'Payment Confirmed',
        'processing'        => 'Processing',
        'shipped'           => 'Shipped',
        'delivered'         => 'Delivered',
        'cancelled'         => 'Cancelled',
        default             => ucfirst($status),
    };
}

/* ==========================================
   UPDATE ORDER STATUS ONLY
   -------------------------------------------------
   Focused writer for orders.order_status that leaves
   payment_status and payment_method alone - used by
   the admin order-status dropdown. Logs the change to
   the timeline; a no-op (no log) when the status is
   unchanged, so the timeline never records a "change"
   that didn't happen.
========================================== */

function update_order_status(int $orderId, string $orderStatus, ?string $note = null): void
{
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    if (!in_array($orderStatus, $allowed, true)) {
        throw new InvalidArgumentException("Invalid order status: {$orderStatus}");
    }

    $stmt = db()->prepare('SELECT order_status, payment_status, payment_method FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();

    if (!$row || $row['order_status'] === $orderStatus) {
        return; // unknown order, or no real transition (nothing to log)
    }

    $stmt = db()->prepare('UPDATE orders SET order_status = ? WHERE id = ?');
    $stmt->execute([$orderStatus, $orderId]);
    log_order_status_event($orderId, $orderStatus, $note);

    // Phase 5D Step 2: a REAL transition to shipped/delivered (we only
    // get here when the status actually changed - see the early return
    // above) triggers the customer notification email. The senders live
    // in includes/order-emails.php, which requires THIS file, so they
    // are invoked lazily via function_exists() instead of a hard
    // require (which would create a circular include). Every page that
    // drives a shipped/delivered transition loads order-emails.php; the
    // senders themselves are fail-closed and idempotent (order_email_log
    // dedup), so a retried status form can never double-email and an
    // SMTP failure can never break the status update.
    if (in_array($orderStatus, ['shipped', 'delivered'], true)) {
        $stmt = db()->prepare(
            'SELECT created_at FROM order_status_history
             WHERE order_id = ? AND order_status = ?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$orderId, $orderStatus]);
        $eventTime = $stmt->fetchColumn() ?: date('Y-m-d H:i:s');

        if ($orderStatus === 'shipped' && function_exists('send_order_shipped_email')) {
            try {
                send_order_shipped_email($orderId, (string) $eventTime);
            } catch (Throwable $e) {
                error_log('update_order_status(): shipped email trigger failed for order #' . $orderId . ' - ' . $e->getMessage());
            }
        } elseif ($orderStatus === 'delivered' && function_exists('send_order_delivered_email')) {
            try {
                send_order_delivered_email($orderId, (string) $eventTime);
            } catch (Throwable $e) {
                error_log('update_order_status(): delivered email trigger failed for order #' . $orderId . ' - ' . $e->getMessage());
            }
        }
    }

    // Stock side-effects (Phase 6) - both helpers are idempotent via
    // orders.stock_deducted_at, see includes/stock-functions.php:
    //   - cancelling  -> restore whatever this order holds (an order
    //                    that was never deducted, e.g. an unpaid online
    //                    order, restores nothing);
    //   - reactivating a cancelled order back into the flow -> re-reserve
    //                    stock, but ONLY for orders that are genuinely
    //                    confirmed (paid, or COD which is accepted at
    //                    placement). An unpaid online order reopened
    //                    while still pending keeps its stock until
    //                    payment is confirmed again.
    if ($orderStatus === 'cancelled') {
        restore_order_stock($orderId);
    } elseif ($row['order_status'] === 'cancelled') {
        if ($row['payment_status'] === 'paid' || $row['payment_method'] === 'cod') {
            apply_order_stock_deduction($orderId);
        }
    }
}
