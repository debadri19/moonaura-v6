<?php
/* ===================================================================
   PAYMENT PAGE
   -------------------------------------------------------------------
   Reached after checkout.php creates an order with an online payment
   method (not COD). Creates a fresh gateway order every time this
   page loads (simplest correct behavior - refreshing never reuses a
   stale/ambiguous gateway order) via PaymentManager, then renders
   whichever gateway is currently active's checkout widget - this
   page never hardcodes a gateway name beyond loading the matching
   checkout widget; it reacts to whatever PaymentManager::createPayment()
   returns.

   Authorization: customer_or_session_owns_order() - either this is
   the same browser session that just created the order, or a
   logged-in customer owns it (lets them come back later to finish
   paying without losing access).
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/order-functions.php';
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/payment-functions.php';
require_once __DIR__ . '/includes/payments/PaymentManager.php';

$orderNumber = trim($_GET['order'] ?? '');
$order       = $orderNumber !== '' ? get_order_by_number($orderNumber) : null;

if (!$order || !customer_or_session_owns_order($order)) {
    redirect('cart.php');
}

// If this order was already paid (e.g. the customer used the back
// button after completing payment), there's nothing to pay - send
// them to the confirmation instead of a payment widget.
if ($order['payment_status'] === 'paid') {
    $_SESSION['last_order_number'] = $order['order_number'];
    redirect('order-success.php?order=' . urlencode($order['order_number']));
}

// Cash on Delivery never reaches this page (checkout.php redirects
// straight to order-success.php for COD) - nothing for this page to
// do if it somehow does.
if ($order['payment_method'] === 'cod') {
    redirect('cart.php');
}

// Manual UPI is paid outside the online gateways - pending manual
// UPI orders are completed on manual-upi-payment.php. Sending one
// through PaymentManager::createPayment() would fail because there
// is no gateway for this method, so route it back to the manual UPI
// page instead (this is the realistic trigger for pending orders
// that arrive here from order-success.php / payment-retry.php).
if ($order['payment_method'] === 'manual_upi') {
    redirect('manual-upi-payment.php?order=' . urlencode($order['order_number']));
}

$errors = [];
$payment = null;

try {
    $payment = PaymentManager::createPayment($order);

} catch (PaymentConfigurationException $e) {

    // Missing credentials, not a real API/network failure - log it
    // (no secrets are ever in this message, just what's missing) and
    // show the developer-facing message as-is so local/staging setup
    // is obvious instead of looking like a generic outage.
    error_log('payment.php: payment gateway not configured - ' . $e->getMessage());
    $errors[] = $e->getMessage();

} catch (Exception $e) {

    // A real failure (network error, Razorpay rejected the request,
    // etc.) - log the actual exception for debugging, but never show
    // that detail to the customer.
    error_log('payment.php: could not create gateway order - ' . $e->getMessage());
    $errors[] = 'Could not start the payment process. Please try again.';
}

$isTestMode = $payment && (
    ($payment['gateway'] === 'razorpay' && RAZORPAY_MODE === 'test')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Payment | MoonAura Crystals</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/checkout.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="checkout-page">

        <div class="container" style="max-width: 480px;">

            <h1>Complete Your Payment</h1>

            <?php if ($isTestMode): ?>
                <div class="checkout-test-mode-banner">
                    Test Mode - no real payment will be charged.
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>

                <div class="checkout-alert checkout-alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <a href="checkout.php" class="btn btn-outline">Back to Checkout</a>

            <?php else: ?>

                <div class="checkout-form-card">

                    <p>Order <strong><?= h($order['order_number']) ?></strong></p>
                    <p style="margin-bottom: 20px;">Amount: <strong><?= h(format_price((float) $order['grand_total'])) ?></strong></p>

                    <button type="button" id="gatewayPayButton" class="btn btn-primary">Pay Now</button>

                </div>

                <!-- ==========================================
                     CONFIG FOR assets/js/payment.js
                     (mirrors the JSON-LD embedding pattern
                     already used on product.php)
                ========================================== -->

                <script type="application/json" id="gatewayConfig">
                    <?= json_encode([
                        'gateway'            => $payment['gateway'],
                        'gateway_order_id'   => $payment['gateway_order_id'],
                        'amount'             => $payment['amount'],
                        'currency'           => $payment['currency'],
                        'name'               => 'MoonAura Crystals',
                        'description'        => 'Order ' . $order['order_number'],
                        'prefill'            => [
                            'name'    => $order['customer_name'],
                            'email'   => $order['customer_email'],
                            'contact' => $order['customer_phone'],
                        ],
                        'order_number'       => $order['order_number'],
                        'csrf_token'         => csrf_token(),
                        'verify_url'         => 'payment-verify.php',
                        'failure_url'        => 'payment-failure.php?order=' . urlencode($order['order_number']),

                        'key'                => $payment['key_id'] ?? null,
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                </script>

            <?php endif; ?>

        </div>

    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <?php if ($payment && $payment['gateway'] === 'razorpay'): ?>
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php endif; ?>
    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
    <script src="<?= versioned_asset('assets/js/payment.js') ?>"></script>

</body>
</html>
