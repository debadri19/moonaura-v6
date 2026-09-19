<?php
/* ===================================================================
   CHECKOUT PAGE
   -------------------------------------------------------------------
   GET  - show the order summary (from the live session cart) and the
          customer/address form.
   POST - validate everything server-side, create the order inside a
          transaction, clear the cart, and redirect to the success
          page. On validation failure, redisplay this same form with
          the errors and whatever the customer already typed.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-functions.php';
require_once __DIR__ . '/includes/product-image-variants.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/order-functions.php';
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/customer-functions.php';
require_once __DIR__ . '/includes/settings-functions.php';
require_once __DIR__ . '/includes/payment-functions.php';
require_once __DIR__ . '/includes/stock-functions.php';
require_once __DIR__ . '/includes/order-emails.php';
require_once __DIR__ . '/includes/tax-functions.php';
require_once __DIR__ . '/includes/payments/PaymentManager.php';
require_once __DIR__ . '/includes/analytics-functions.php';
require_once __DIR__ . '/includes/meta-pixel-functions.php';


/* ==========================================
   LOAD THE ITEMS TO CHECK OUT - LIVE, EVERY TIME
   -------------------------------------------------------------------
   Buy Now (product page "Buy Now" button) SKIPS the cart entirely -
   if a buy-now session is active, checkout works from that single
   item instead of the cart, and the cart itself is never read or
   modified. Otherwise this is the normal cart-based checkout, exactly
   as before. Either way, only AVAILABLE items count towards
   checkout - if nothing is available to buy, there's nothing to
   check out.
========================================== */

$isBuyNow = buy_now_active();

if ($isBuyNow) {
    $cartItems = get_buy_now_items_with_details();
} else {
    $cartItems = get_cart_items_with_details();
}

$availableItems = array_values(array_filter($cartItems, fn ($item) => $item['is_available']));

if (empty($availableItems)) {
    if ($isBuyNow) {
        // The buy-now product went inactive/was removed between the
        // product page and here - clear the stale session and send
        // the customer to the shop, not to a real cart that was
        // never touched by this flow.
        buy_now_clear();
        redirect('shop.php');
    }

    redirect('cart.php');
}


/* ==========================================
   TOTALS (server-side only - recalculated
   fresh on every load AND again on submit,
   never trusted from the browser)
========================================== */

function calculate_checkout_totals(array $items, ?string $shippingState = null): array
{
    $subtotal = 0.0;
    $taxLines = [];

    // Same resolution create_order() uses: 'intra' | 'inter' | 'unknown'
    // (business_state not configured, or shipping state not known yet
    // on this page load - falls back to the same conservative 50/50
    // CGST+SGST split create_order() itself documents). GST is
    // INFORMATIONAL only either way - it never changes grand_total,
    // since prices are GST-inclusive (Phase 5F).
    $taxType = resolve_tax_type($shippingState);

    foreach ($items as $item) {
        $lineTotal = round((float) $item['line_total'], 2);
        $subtotal += $lineTotal;

        // The item's product row is already a fresh SELECT p.* from
        // get_cart_items_with_details() (or, for Buy Now,
        // get_buy_now_items_with_details()) on every single load of
        // this page - never a client-supplied value - so reading
        // gst_rate straight off it here is exactly as authoritative
        // as create_order()'s own re-fetch, just normalized the same
        // way for safety.
        $gstRate = normalize_gst_rate($item['product']['gst_rate'] ?? 0.0) ?? 0.0;
        $taxLines[] = compute_line_tax($lineTotal, $gstRate, $taxType);
    }

    $subtotal = round($subtotal, 2);

    // Shipping is still a flat 0 for this phase (no shipping-rules
    // table yet). Discount is likewise always 0 for now - no coupon/
    // promo-code system exists yet - but create_order() already
    // accepts a $discount parameter, so the summary surfaces the row
    // now rather than needing another pass later.
    $shippingCharge = 0.00;
    $discount       = 0.00;

    $orderTax  = aggregate_line_tax($taxLines);
    $gstAmount = $orderTax['gst_amount'];

    $grandTotal = round($subtotal - $discount + $shippingCharge, 2);

    return [
        'subtotal'        => $subtotal,
        'discount'        => $discount,
        'shipping_charge' => $shippingCharge,
        'gst_amount'      => $gstAmount,
        'grand_total'     => $grandTotal,
    ];
}

$totals = calculate_checkout_totals($availableItems);


/* ==========================================
   FORM STATE (defaults for a fresh GET;
   overwritten below if this is a POST)
========================================== */

$errors = [];

// If a customer is logged in, prefill their details - they can still
// edit them for this order (e.g. shipping to someone else's address).
$loggedInCustomer = current_customer();

// Saved Address selector (logged-in customers only - guests have no
// address book). Empty array for guests/no saved addresses, in which
// case the dropdown below simply doesn't render.
$savedAddresses = $loggedInCustomer ? get_customer_addresses((int) $loggedInCustomer['id']) : [];

$customer = [
    'full_name' => $loggedInCustomer['name'] ?? '',
    'email'     => $loggedInCustomer['email'] ?? '',
    'mobile'    => $loggedInCustomer['phone'] ?? '',
];

$address = [
    'address_line1' => '',
    'address_line2' => '',
    'landmark'      => '',
    'city'          => '',
    'state'         => '',
    'pincode'       => '',
];

// "Save this address for future orders" - checked by default on a
// fresh page load; overwritten below if this is a POST redisplay.
$saveAddress = true;

// Which payment methods can this customer choose from? Multi-gateway
// model (Phase 4C, extended Phase 4D): every ENABLED *and configured*
// online gateway is offered
// (PaymentManager::getEnabledAndConfiguredGatewayNames() - could be
// one, several, or none), plus COD and/or Manual UPI QR Payment if
// their own settings are on. An enabled gateway with missing API
// credentials is not shown. Manual UPI is managed the same way COD
// already was - directly here, not through PaymentManager, since
// (like COD) it has no external gateway API to call. checkout.php
// never hardcodes which online gateways exist or are enabled - it
// only asks PaymentManager.
$enabledGateways  = PaymentManager::getEnabledAndConfiguredGatewayNames();
$codEnabled       = get_setting('cod_enabled', '1') === '1';
$manualUpiEnabled = get_setting('manual_upi_enabled', '0') === '1';

// Safety net: this system should never be configured into a state
// where a customer literally cannot pay. Admin > Settings (see
// admin/settings.php) now blocks turning off the last remaining
// method through the UI, but this stays as defense-in-depth - e.g.
// a direct database edit could still create that state - so if
// every online gateway, COD, AND Manual UPI end up disabled at once,
// force COD available as a last resort rather than render a checkout
// page with no way to complete an order.
if (empty($enabledGateways) && !$codEnabled && !$manualUpiEnabled) {
    error_log('checkout.php: every payment method is disabled in settings - forcing COD available as a safety fallback.');
    $codEnabled = true;
}

$allowedMethods = $enabledGateways;
if ($manualUpiEnabled) {
    $allowedMethods[] = 'manual_upi';
}
if ($codEnabled) {
    $allowedMethods[] = 'cod';
}

// PaymentManager::getDefaultGatewayName() returns an enabled gateway
// (or '' if none are enabled). The extra in_array() check here also
// covers an enabled-but-unconfigured default, so checkout never
// pre-selects a method that is not in $allowedMethods.
$defaultGateway = PaymentManager::getDefaultGatewayName();
$paymentMethod  = in_array($defaultGateway, $allowedMethods, true)
    ? $defaultGateway
    : ($allowedMethods[0] ?? 'cod');


/* ==========================================
   HANDLE FORM SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    // Re-check the items haven't changed between page load and submit
    // (e.g. another tab, or a product going out of stock) - same
    // buy-now-vs-cart source as the GET-time load above, never
    // trusted from anything the browser submitted.
    if ($isBuyNow) {
        $cartItems = get_buy_now_items_with_details();
    } else {
        $cartItems = get_cart_items_with_details();
    }
    $availableItems = array_values(array_filter($cartItems, fn ($item) => $item['is_available']));

    if (empty($availableItems)) {
        if ($isBuyNow) {
            buy_now_clear();
            flash_set('error', 'That item is no longer available. Please try again.');
            redirect('shop.php');
        }
        flash_set('error', 'Your cart is empty or its items are no longer available.');
        redirect('cart.php');
    }

    // Totals are recalculated fresh here too - the form never submits
    // its own total, so there's nothing client-side to trust or not.
    // (Passed after the address fields are read below, so the GST
    // preview can use the now-known shipping state.)

    $customer['full_name'] = trim($_POST['full_name'] ?? '');
    $customer['email']     = trim($_POST['email'] ?? '');
    $customer['mobile']    = trim($_POST['mobile'] ?? '');

    $address['address_line1'] = trim($_POST['address_line1'] ?? '');
    $address['address_line2'] = trim($_POST['address_line2'] ?? '');
    $address['landmark']      = trim($_POST['landmark'] ?? '');
    $address['city']          = trim($_POST['city'] ?? '');
    $address['state']         = trim($_POST['state'] ?? '');
    $address['pincode']       = trim($_POST['pincode'] ?? '');

    // "Save this address for future orders" - enabled by default; an
    // unchecked checkbox simply isn't present in $_POST at all (same
    // as any other checkbox), so this only turns off when the
    // customer actually unchecks it.
    $saveAddress = isset($_POST['save_address']);

    $totals = calculate_checkout_totals($availableItems, $address['state']);

    $paymentMethod = $_POST['payment_method'] ?? $defaultGateway;

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $paymentMethod = in_array($defaultGateway, $allowedMethods, true)
            ? $defaultGateway
            : ($allowedMethods[0] ?? 'cod');
    }

    /* ---------- Validation ---------- */

    if ($customer['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!is_valid_mobile_number($customer['mobile'])) {
        $errors[] = 'Please enter a valid 10-digit mobile number.';
    }

    if ($address['address_line1'] === '') {
        $errors[] = 'Address Line 1 is required.';
    }

    if ($address['city'] === '') {
        $errors[] = 'City is required.';
    }

    if ($address['state'] === '') {
        $errors[] = 'State is required.';
    }

    if (!is_valid_pin_code($address['pincode'])) {
        $errors[] = 'Please enter a valid 6-digit PIN code.';
    }

    /* ---------- Stock pre-flight (Phase 6) ---------- */

    // Friendly per-product message when the cart wants more than is in
    // stock, so we don't reach create_order() at all. The authoritative
    // no-negative-stock guarantee still lives in
    // apply_order_stock_deduction()'s guarded UPDATE (see
    // includes/stock-functions.php) - this check only covers the common
    // case with a nicer error surface.
    if (empty($errors)) {
        foreach (validate_cart_stock($availableItems) as $shortage) {
            $errors[] = 'Only ' . $shortage['available'] . ' left in stock for "'
                . $shortage['name'] . '" (you asked for ' . $shortage['requested'] . ').';
        }
    }

    /* ---------- Create the order ---------- */

    if (empty($errors)) {

        try {

            $order = create_order($availableItems, $customer, $address, $loggedInCustomer['id'] ?? null, $paymentMethod);

            if ($paymentMethod === 'cod') {

                // Cash on Delivery - no gateway involved at all. The
                // order is accepted right away; payment happens on
                // delivery, tracked outside this system for now.
                update_order_payment_status($order['id'], 'pending', 'processing', 'cod');

                // COD stock deduction point (Phase 6): the order became
                // "confirmed" the moment it was accepted above, so this
                // is where its inventory is reserved. Idempotent - see
                // includes/stock-functions.php.
                apply_order_stock_deduction($order['id']);

                // Phase 5D Step 2: the COD order is confirmed at
                // placement - send the confirmation email. Idempotent
                // (dedup + confirmed-check inside) and fail-closed, so a
                // mail outage can never break the redirect below.
                try {
                    send_order_confirmation_email($order['id']);
                } catch (Throwable $e) {
                    error_log('checkout.php: COD confirmation email trigger failed for order #' . $order['id'] . ' - ' . $e->getMessage());
                }

                if ($isBuyNow) {
                    buy_now_clear();
                } else {
                    cart_clear();
                }

                // "Save this address for future orders" - only after
                // the order is truly placed, only for logged-in
                // customers (guests have no address book), only if
                // the checkbox was actually checked.
                if ($loggedInCustomer && $saveAddress) {
                    save_customer_address_if_new((int) $loggedInCustomer['id'], [
                        'full_name'     => $customer['full_name'],
                        'phone'         => $customer['mobile'],
                        'address_line1' => $address['address_line1'],
                        'address_line2' => $address['address_line2'],
                        'landmark'      => $address['landmark'],
                        'city'          => $address['city'],
                        'state'         => $address['state'],
                        'postal_code'   => $address['pincode'],
                    ]);
                }

                $_SESSION['last_order_number'] = $order['order_number'];

                redirect('order-success.php?order=' . urlencode($order['order_number']));

            } elseif ($paymentMethod === 'manual_upi') {

                // Manual UPI QR - also no gateway, but unlike COD this
                // isn't auto-accepted: it needs an admin to verify the
                // UTR the customer submits before it's truly confirmed
                // (see manual-upi-payment.php and admin/order-detail.php's
                // "Verify Payment" action). create_order() already
                // inserts payment_status='pending', order_status='pending'
                // by default, which is exactly the right starting state
                // here - no extra status-update call needed. The order
                // itself is already committed the moment it's created,
                // same as COD, so the cart clears immediately; what's
                // still pending is proof-of-payment, not the order.
                if ($isBuyNow) {
                    buy_now_clear();
                } else {
                    cart_clear();
                }

                if ($loggedInCustomer && $saveAddress) {
                    save_customer_address_if_new((int) $loggedInCustomer['id'], [
                        'full_name'     => $customer['full_name'],
                        'phone'         => $customer['mobile'],
                        'address_line1' => $address['address_line1'],
                        'address_line2' => $address['address_line2'],
                        'landmark'      => $address['landmark'],
                        'city'          => $address['city'],
                        'state'         => $address['state'],
                        'postal_code'   => $address['pincode'],
                    ]);
                }

                $_SESSION['pending_payment_order_number'] = $order['order_number'];

                redirect('manual-upi-payment.php?order=' . urlencode($order['order_number']));

            } else {

                // Online payment (Razorpay) - the order exists but
                // isn't paid yet. Do NOT clear the cart here - only
                // once payment actually succeeds (payment.php /
                // payment-verify.php). This session flag is what
                // authorizes this browser to view/complete/retry
                // payment for this specific order. The "save this
                // address" choice is deferred the same way - it only
                // takes effect once payment-verify.php confirms the
                // order actually went through.
                $_SESSION['pending_payment_order_number']  = $order['order_number'];
                $_SESSION['pending_payment_save_address']  = $loggedInCustomer && $saveAddress;

                redirect('payment.php?order=' . urlencode($order['order_number']));
            }

        } catch (Exception $e) {

            // create_order() already error_log()s the full exception
            // before rethrowing; this logs it again here for defense
            // in depth. The customer only ever sees the generic
            // message below - never raw exception detail.

            error_log('checkout.php: order placement failed - ' . $e->getMessage());

            // Rare edge: the order was created and accepted (COD) but
            // the stock deduction then failed (a concurrent order took
            // the last units between our pre-flight check and the
            // guarded UPDATE). Don't leave a confirmed-but-unfulfillable
            // order holding nothing in stock - cancel it so the admin
            // queue never sees a "processing" order that can't ship.
            if (isset($order['id']) && $paymentMethod === 'cod') {
                try {
                    update_order_status($order['id'], 'cancelled', 'Cancelled automatically - insufficient stock.');
                } catch (Exception $ignored) {
                    error_log('checkout.php: failed to auto-cancel order #' . $order['id'] . ' after stock deduction failure.');
                }
            }

            $errors[] = 'Something went wrong while placing your order. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Checkout | MoonAura Crystals</title>
    <meta name="robots" content="noindex, follow">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/checkout.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="checkout-page">

        <div class="container">

            <h1>Checkout</h1>

            <?php if (!empty($errors)): ?>
                <div class="checkout-alert checkout-alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="checkout-layout">

                <!-- ==========================================
                     CUSTOMER + ADDRESS FORM
                ========================================== -->

                <div class="checkout-form-card">

                    <h2>Delivery Details</h2>

                    <form method="post" action="checkout.php">

                        <?= csrf_field() ?>

                        <?php if (!empty($savedAddresses)): ?>

                            <label for="saved_address">Saved Address</label>
                            <select id="saved_address" name="saved_address_id">
                                <option value="" selected>+ Enter a new address</option>
                                <?php foreach ($savedAddresses as $savedAddress): ?>
                                    <option
                                        value="<?= (int) $savedAddress['id'] ?>"
                                        data-full-name="<?= h($savedAddress['full_name']) ?>"
                                        data-phone="<?= h($savedAddress['phone']) ?>"
                                        data-address-line1="<?= h($savedAddress['address_line1']) ?>"
                                        data-address-line2="<?= h($savedAddress['address_line2'] ?? '') ?>"
                                        data-landmark="<?= h($savedAddress['landmark'] ?? '') ?>"
                                        data-city="<?= h($savedAddress['city']) ?>"
                                        data-state="<?= h($savedAddress['state']) ?>"
                                        data-pincode="<?= h($savedAddress['postal_code']) ?>"
                                    >
                                        <?= $savedAddress['is_default'] ? 'Default - ' : '' ?><?= h($savedAddress['full_name']) ?> - <?= h($savedAddress['address_line1']) ?>, <?= h($savedAddress['city']) ?> <?= h($savedAddress['postal_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="checkout-field-hint">Choose a saved address to auto-fill the form below, or keep "+ Enter a new address" selected to type a different one.</p>

                        <?php endif; ?>

                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?= h($customer['full_name']) ?>" required>

                        <div class="checkout-form-row">

                            <div>
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?= h($customer['email']) ?>" required>
                            </div>

                            <div>
                                <label for="mobile">Mobile Number</label>
                                <input type="tel" id="mobile" name="mobile" value="<?= h($customer['mobile']) ?>" required>
                            </div>

                        </div>

                        <label for="address_line1">Address Line 1</label>
                        <input type="text" id="address_line1" name="address_line1" value="<?= h($address['address_line1']) ?>" required>

                        <label for="address_line2">Address Line 2 <span style="font-weight:400; color: var(--text-light);">(optional)</span></label>
                        <input type="text" id="address_line2" name="address_line2" value="<?= h($address['address_line2']) ?>">

                        <label for="landmark">Landmark <span style="font-weight:400; color: var(--text-light);">(optional)</span></label>
                        <input type="text" id="landmark" name="landmark" value="<?= h($address['landmark']) ?>">

                        <div class="checkout-form-row">

                            <div>
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" value="<?= h($address['city']) ?>" required>
                            </div>

                            <div>
                                <label for="state">State</label>
                                <input type="text" id="state" name="state" value="<?= h($address['state']) ?>" required>
                            </div>

                        </div>

                        <label for="pincode">PIN Code</label>
                        <input type="text" id="pincode" name="pincode" value="<?= h($address['pincode']) ?>" required>

                        <?php if ($loggedInCustomer): ?>
                            <label class="checkout-checkbox-label">
                                <input type="checkbox" name="save_address" <?= $saveAddress ? 'checked' : '' ?>>
                                Save this address for future orders
                            </label>
                        <?php endif; ?>

                        <h2 style="margin-top: 24px;">Payment Method</h2>

                        <?php foreach ($enabledGateways as $gatewayName): ?>
                            <label class="checkout-payment-option">
                                <input type="radio" name="payment_method" value="<?= h($gatewayName) ?>" <?= $paymentMethod === $gatewayName ? 'checked' : '' ?>>
                                <?= h(PaymentManager::getGatewayLabel($gatewayName)) ?>
                            </label>
                        <?php endforeach; ?>

                        <?php if ($manualUpiEnabled): ?>
                            <label class="checkout-payment-option">
                                <input type="radio" name="payment_method" value="manual_upi" <?= $paymentMethod === 'manual_upi' ? 'checked' : '' ?>>
                                Manual UPI QR Payment
                            </label>
                        <?php endif; ?>

                        <?php if ($codEnabled): ?>
                            <label class="checkout-payment-option">
                                <input type="radio" name="payment_method" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : '' ?>>
                                Cash on Delivery
                            </label>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary">Place Order</button>

                    </form>

                </div>

                <!-- ==========================================
                     ORDER SUMMARY
                ========================================== -->

                <div class="checkout-summary">

                    <h2>Order Summary</h2>

                    <?php foreach ($availableItems as $item): ?>

                        <?php
                            $product   = $item['product'];
                            $imagePath = get_product_primary_image((int) $product['id']);
                        ?>

                        <div class="checkout-summary-item">

                            <img src="<?= h(product_image_variant_url($imagePath, 'sm')) ?>" alt="<?= h($product['name']) ?>">

                            <div class="checkout-summary-item-name">
                                <?= h($product['name']) ?>
                                <div class="checkout-summary-item-qty">Qty: <?= (int) $item['quantity'] ?></div>
                            </div>

                            <div class="checkout-summary-item-total">
                                <?= h(format_price($item['line_total'])) ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                    <div class="checkout-summary-row">
                        <span>Subtotal</span>
                        <span><?= h(format_price($totals['subtotal'])) ?></span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>Discount</span>
                        <span><?= $totals['discount'] > 0 ? '- ' . h(format_price($totals['discount'])) : h(format_price(0)) ?></span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>Shipping</span>
                        <span><?= h(format_price($totals['shipping_charge'])) ?></span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>GST (Included)</span>
                        <span><?= h(format_price($totals['gst_amount'])) ?></span>
                    </div>

                    <div class="checkout-summary-row checkout-summary-total">
                        <span>Grand Total</span>
                        <span><?= h(format_price($totals['grand_total'])) ?></span>
                    </div>

                </div>

            </div>

        </div>

    </section>

    <?php
    $checkoutGaItems = ga4_items_from_cart_items($availableItems);
    ga4_queue_event('begin_checkout', [
        'currency' => ga4_currency(),
        'value'    => (float) ($totals['grand_total'] ?? ga4_items_value($checkoutGaItems)),
        'items'    => $checkoutGaItems,
    ]);

    // Meta Phase 2: InitiateCheckout fires when the customer actually
    // enters the checkout flow (this page render), using the existing
    // checkout items and grand total. Queued once per page load so
    // incidental AJAX/UI updates cannot duplicate it.
    meta_pixel_track_checkout($checkoutGaItems, $totals['grand_total'] ?? null);

    // Meta Phase 5: hashed Advanced Matching from details already on
    // this checkout (logged-in prefills or guest-entered fields).
    $checkoutMatching = [
        'email'       => (string) ($customer['email'] ?? ''),
        'phone'       => (string) ($customer['mobile'] ?? ''),
        'name'        => (string) ($customer['full_name'] ?? ''),
        'city'        => (string) ($address['city'] ?? ''),
        'state'       => (string) ($address['state'] ?? ''),
        'postal_code' => (string) ($address['pincode'] ?? ''),
    ];
    meta_pixel_set_matching_context($checkoutMatching);
    ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
    <?php if (!empty($savedAddresses)): ?>
        <script src="<?= versioned_asset('assets/js/checkout.js') ?>"></script>
    <?php endif; ?>

</body>
</html>
