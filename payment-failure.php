<?php
/* ===================================================================
   PAYMENT FAILURE PAGE
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/order-functions.php';
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/payment-functions.php';

$orderNumber = trim($_GET['order'] ?? '');
$order       = $orderNumber !== '' ? get_order_by_number($orderNumber) : null;

if (!$order || !customer_or_session_owns_order($order)) {
    redirect('cart.php');
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
    <title>Payment Failed | MoonAura Crystals</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/cart.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/checkout.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="checkout-page">

        <div class="container">

            <div class="cart-empty">

                <i class="fa-solid fa-circle-exclamation" style="color: #b3261e;"></i>

                <h1 style="margin-bottom: 10px;">Payment Failed</h1>

                <p>
                    Your payment for order <strong><?= h($order['order_number']) ?></strong>
                    could not be completed. No amount has been charged.
                    Your order is still saved - you can try again below.
                </p>

                <form method="post" action="payment-retry.php" style="display: inline-block; margin-right: 10px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_number" value="<?= h($order['order_number']) ?>">
                    <button type="submit" class="btn btn-primary">Retry Payment</button>
                </form>

                <a href="cart.php" class="btn btn-outline">Back to Cart</a>

            </div>

        </div>

    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
