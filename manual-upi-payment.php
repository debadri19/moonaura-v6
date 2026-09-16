<?php
/* ===================================================================
   MANUAL UPI QR PAYMENT PAGE
   -------------------------------------------------------------------
   Reached after checkout.php creates an order with payment_method =
   'manual_upi'. Shows the admin-configured QR code / UPI ID / account
   name and a form to submit the UTR (transaction reference number)
   plus an optional payment screenshot. Submitting does NOT mark the
   order paid - it logs a payment_transactions row with status
   'submitted' and leaves payment_status at 'pending'; an admin must
   review and verify it from dashboard/order-detail.php before the order
   is considered paid (see that file's "Verify Payment" action).

   Same authorization model as payment.php: customer_or_session_owns_order()
   - either this is the same browser session that just created the
   order, or a logged-in customer owns it.

   Deliberately its own page rather than reusing order-success.php -
   that page's own authorization/settlement logic
   ($paymentIsSettled = payment_status === 'paid' || payment_method === 'cod')
   is not designed for a "still pending, but the customer has done
   their part" state, and redirects anything else to payment.php
   (the online-gateway flow), which would be wrong here. Once an
   admin verifies, payment_status becomes 'paid' and order-success.php
   works for this order with no changes needed there.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/order-functions.php';
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/payment-functions.php';
require_once __DIR__ . '/includes/upload-functions.php';

$orderNumber = trim($_GET['order'] ?? '');
$order       = $orderNumber !== '' ? get_order_by_number($orderNumber) : null;

if (!$order || !customer_or_session_owns_order($order)) {
    redirect('cart.php');
}

// Already verified (e.g. the customer came back after an admin
// confirmed it, or used the back button) - nothing left to do here.
if ($order['payment_status'] === 'paid') {
    $_SESSION['last_order_number'] = $order['order_number'];
    redirect('order-success.php?order=' . urlencode($order['order_number']));
}

// This page is only for manual_upi orders - anything else (an online
// gateway still pending, or COD which never reaches here at all)
// doesn't belong on this page.
if ($order['payment_method'] !== 'manual_upi') {
    redirect('cart.php');
}

// Has a reference already been submitted for this order? (customer
// refreshing or returning to this page after already submitting once)
$stmt = db()->prepare(
    "SELECT * FROM payment_transactions
     WHERE order_id = ? AND gateway = 'manual_upi'
     ORDER BY id DESC LIMIT 1"
);
$stmt->execute([$order['id']]);
$existingSubmission = $stmt->fetch() ?: null;

$errors  = [];
$success = false;

/* ==========================================
   HANDLE UTR SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingSubmission) {

    csrf_verify();

    $utr = trim($_POST['utr_reference'] ?? '');

    if ($utr === '') {
        $errors[] = 'Please enter the UTR / transaction reference number from your UPI payment.';
    } elseif (mb_strlen($utr) > 100) {
        $errors[] = 'That reference number looks too long - please double-check it.';
    }

    $screenshotPath = null;

    // Optional - only validate/save if a file was actually chosen.
    if (empty($errors) && !empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] !== UPLOAD_ERR_NO_FILE) {

        $result = save_uploaded_image(
            $_FILES['screenshot'],
            __DIR__ . '/assets/uploads/payment-screenshots',
            'screenshot'
        );

        if ($result['error'] !== null) {
            $errors[] = $result['error'];
        } else {
            $screenshotPath = 'assets/uploads/payment-screenshots/' . $result['path'];
        }
    }

    if (empty($errors)) {

        log_payment_transaction($order['id'], 'manual_upi', [
            'gateway_payment_id' => $utr,
            'status'             => 'submitted',
            'screenshot_path'    => $screenshotPath,
        ]);

        $success = true;

        // Refresh so the page now shows the "already submitted" state
        // instead of the form again (also correct if the customer
        // reloads this page later).
        $stmt = db()->prepare(
            "SELECT * FROM payment_transactions
             WHERE order_id = ? AND gateway = 'manual_upi'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$order['id']]);
        $existingSubmission = $stmt->fetch() ?: null;
    }
}

$upiId          = get_setting('upi_id', '');
$upiAccountName = get_setting('upi_account_name', '');
$upiQrImagePath = get_setting('upi_qr_image_path', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Complete Your Payment | MoonAura Crystals</title>
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

            <?php if ($existingSubmission): ?>

                <div class="checkout-alert" style="background: #eaf7ee; color: #1e7b34; border: 1px solid #c3e8cd;">
                    Thanks! We've received your payment reference
                    (<strong><?= h($existingSubmission['gateway_payment_id']) ?></strong>) for order
                    <strong><?= h($order['order_number']) ?></strong> and it's now awaiting verification.
                    You'll see your order marked as Paid here once that's done -
                    <?php if (is_customer_logged_in()): ?>
                        you can also check its status any time from <a href="account/orders.php">your account</a>.
                    <?php else: ?>
                        please check back on this page later, or contact us with your order number if you need an update.
                    <?php endif; ?>
                </div>

                <div class="checkout-form-card">
                    <p>Order <strong><?= h($order['order_number']) ?></strong></p>
                    <p>Amount: <strong><?= h(format_price((float) $order['grand_total'])) ?></strong></p>
                    <p style="margin-bottom: 0;">Status: <strong>Payment Verification Pending</strong></p>
                </div>

            <?php else: ?>

                <?php if (!empty($errors)): ?>
                    <div class="checkout-alert checkout-alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="checkout-form-card">

                    <p>Order <strong><?= h($order['order_number']) ?></strong></p>
                    <p style="margin-bottom: 20px;">Amount: <strong><?= h(format_price((float) $order['grand_total'])) ?></strong></p>

                    <?php if ($upiQrImagePath !== ''): ?>
                        <div style="text-align: center; margin-bottom: 16px;">
                            <img src="<?= h(asset_url($upiQrImagePath)) ?>" alt="UPI QR Code" style="max-width: 220px; width: 100%; border: 1px solid var(--border); border-radius: 12px;">
                        </div>
                    <?php endif; ?>

                    <?php if ($upiId !== ''): ?>
                        <p>UPI ID: <strong><?= h($upiId) ?></strong></p>
                    <?php endif; ?>

                    <?php if ($upiAccountName !== ''): ?>
                        <p>Account Name: <strong><?= h($upiAccountName) ?></strong></p>
                    <?php endif; ?>

                    <p style="margin-bottom: 20px; color: var(--text-light); font-size: 14px;">
                        Scan the QR code or pay to the UPI ID above using any UPI app
                        (Google Pay, PhonePe, Paytm, etc.) for the exact amount shown,
                        then enter your transaction reference number below so we can
                        verify it.
                    </p>

                    <form method="post" enctype="multipart/form-data">

                        <?= csrf_field() ?>

                        <label for="utr_reference" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                            UTR / Transaction Reference Number
                        </label>
                        <input
                            type="text"
                            id="utr_reference"
                            name="utr_reference"
                            value="<?= h($_POST['utr_reference'] ?? '') ?>"
                            placeholder="e.g. 123456789012"
                            required
                            style="width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: inherit; margin-bottom: 16px;"
                        >

                        <label for="screenshot" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                            Payment Screenshot <span style="font-weight: 400; color: var(--text-light);">(optional)</span>
                        </label>
                        <input
                            type="file"
                            id="screenshot"
                            name="screenshot"
                            accept="image/jpeg,image/png,image/webp"
                            style="width: 100%; margin-bottom: 20px;"
                        >

                        <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Payment Reference</button>

                    </form>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
