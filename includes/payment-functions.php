<?php
/* ===================================================================
   PAYMENT FUNCTIONS
   -------------------------------------------------------------------
   Database operations for payment_transactions and for updating an
   order's payment/order status. Deliberately gateway-agnostic - this
   file has no idea Razorpay exists. PaymentManager calls these for
   online payments; checkout.php calls update_order_payment_status()
   directly for Cash on Delivery, skipping the gateway layer entirely.
   That's what "COD can be added without architectural changes" means
   in practice - it already was, by designing this function first.
=================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/order-functions.php';
require_once __DIR__ . '/order-emails.php';


/* ==========================================
   THE ONLY FUNCTION THAT EVER WRITES
   orders.payment_status / order_status
   -------------------------------------------------
   Writes both statuses together (that's the point of
   the original design - COD needed a single call that
   records "not yet paid, order accepted"). Timeline
   logging:
     - payment_status reaching 'paid' logs the
       "Payment Confirmed" milestone;
     - a change in order_status logs that milestone.
   Both only fire on real transitions (compared against
   the pre-update row), so no duplicate/fake entries.
========================================== */

function update_order_payment_status(
    int $orderId,
    string $paymentStatus,
    string $orderStatus,
    ?string $paymentMethod = null
): void {
    $stmt = db()->prepare(
        'SELECT payment_status, order_status FROM orders WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$orderId]);
    $current = $stmt->fetch();

    if ($paymentMethod !== null) {
        $stmt = db()->prepare(
            'UPDATE orders SET payment_status = ?, order_status = ?, payment_method = ? WHERE id = ?'
        );
        $stmt->execute([$paymentStatus, $orderStatus, $paymentMethod, $orderId]);
    } else {
        $stmt = db()->prepare(
            'UPDATE orders SET payment_status = ?, order_status = ? WHERE id = ?'
        );
        $stmt->execute([$paymentStatus, $orderStatus, $orderId]);
    }

    if ($current) {
        if ($paymentStatus === 'paid' && $current['payment_status'] !== 'paid') {
            log_order_status_event($orderId, 'payment_confirmed');

            // Phase 5D Step 2: the order just became CONFIRMED (payment
            // verified for Razorpay / Manual UPI, or an admin
            // marking a COD order Paid). send_order_confirmation_email()
            // is idempotent (dedup + confirmed-check inside) and fully
            // fail-closed, so a dead SMTP relay can never break the
            // payment confirmation path this code lives on.
            try {
                send_order_confirmation_email($orderId);
            } catch (Throwable $e) {
                error_log('update_order_payment_status(): confirmation email trigger failed for order #' . $orderId . ' - ' . $e->getMessage());
            }
        }
        if ($orderStatus !== $current['order_status']) {
            log_order_status_event($orderId, $orderStatus);
        }
    }
}


/* ==========================================
   LOG A NEW PAYMENT ATTEMPT
   One row per attempt - a retried payment
   creates a NEW row rather than overwriting
   the failed one, so the full history survives.
========================================== */

function log_payment_transaction(int $orderId, string $gateway, array $fields): int
{
    $stmt = db()->prepare(
        'INSERT INTO payment_transactions (order_id, gateway, gateway_order_id, gateway_payment_id, status, raw_response, screenshot_path)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $orderId,
        $gateway,
        $fields['gateway_order_id'] ?? null,
        $fields['gateway_payment_id'] ?? null,
        $fields['status'] ?? 'created',
        isset($fields['raw_response']) ? json_encode($fields['raw_response']) : null,
        $fields['screenshot_path'] ?? null,
    ]);

    return (int) db()->lastInsertId();
}


/* ==========================================
   UPDATE AN EXISTING TRANSACTION
   Only updates the fields actually passed in,
   so a partial update (e.g. just "status")
   doesn't wipe out columns not mentioned.
========================================== */

function update_payment_transaction(int $transactionId, array $fields): void
{
    $allowedColumns = ['gateway_payment_id', 'gateway_signature', 'status', 'raw_response', 'screenshot_path'];

    $setParts = [];
    $params   = [];

    foreach ($allowedColumns as $column) {
        if (array_key_exists($column, $fields)) {
            $setParts[] = "{$column} = ?";
            $params[]   = ($column === 'raw_response' && is_array($fields[$column]))
                ? json_encode($fields[$column])
                : $fields[$column];
        }
    }

    if (empty($setParts)) {
        return; // nothing to update
    }

    $params[] = $transactionId;

    $stmt = db()->prepare(
        'UPDATE payment_transactions SET ' . implode(', ', $setParts) . ' WHERE id = ?'
    );
    $stmt->execute($params);
}


/* ==========================================
   FIND A TRANSACTION BY GATEWAY ORDER ID
   Used by PaymentManager to work out which
   order/gateway a callback or webhook belongs
   to.
========================================== */

function get_payment_transaction_by_gateway_order_id(string $gatewayOrderId): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM payment_transactions WHERE gateway_order_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$gatewayOrderId]);

    $transaction = $stmt->fetch();

    return $transaction ?: null;
}


/* ==========================================
   AUTHORIZATION: MAY THIS BROWSER ACT ON
   THIS ORDER'S PAYMENT? (view / retry)
   -------------------------------------------------
   True if EITHER:
   - this session is the one that just created this
     order (same trust model already used by
     order-success.php's $_SESSION['last_order_number']),
   - or a logged-in customer owns this order (lets a
     registered customer come back later, even after
     the session-based flag has aged out, and retry a
     failed payment from their account).

   Requires includes/customer-auth.php to already be
   loaded by the caller (payment.php, payment-verify.php,
   payment-retry.php, payment-failure.php all do this).
========================================== */

function customer_or_session_owns_order(array $order): bool
{
    if (!empty($_SESSION['pending_payment_order_number'])
        && hash_equals($_SESSION['pending_payment_order_number'], $order['order_number'])
    ) {
        return true;
    }

    if (function_exists('is_customer_logged_in') && is_customer_logged_in()) {
        $customer = current_customer();
        if ($customer && $order['user_id'] !== null && (int) $order['user_id'] === (int) $customer['id']) {
            return true;
        }
    }

    return false;
}
