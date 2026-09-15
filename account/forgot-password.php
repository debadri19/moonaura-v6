<?php
/* ===================================================================
   CUSTOMER FORGOT PASSWORD (Phase 5B + 5D)
   -------------------------------------------------------------------
   Generates a one-time reset token (60-minute expiry), stores only
   its SHA-256 hash in customer_password_resets, and emails the reset
   link to the customer through the shared mailer (Phase 5D). Mirrors
   the admin flow (admin/forgot-password.php).

   SECURITY:
   - CSRF-protected form.
   - The SAME message is shown whether or not the email exists, so the
     form can't be used to enumerate valid customer accounts.
   - Tokens are only generated for active customers, but the response
     never reveals whether that happened.
   - SMTP failures never break the flow: if the email cannot be sent
     (or SMTP is not configured) the page still shows the same generic
     message and the failure is logged by send_email(). Nothing here
     reveals whether an account exists OR whether a mail was sent.
   - Reset-request throttling is independent of the login lockout.
      Repeated reset requests never increment login_attempts and
      cannot lock customer login.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-functions.php';
require_once __DIR__ . '/../includes/password-reset-throttle.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email-templates.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $email = normalize_email(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors) && password_reset_is_throttled('customer', $email)) {
        $errors[] = password_reset_throttle_message();
    }

    if (empty($errors)) {

        $stmt = db()->prepare('SELECT id, name, email FROM customers WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        // Deliberately show the same message whether or not the
        // email exists, so this form can't be used to discover
        // valid customer emails. The reset link is only actually
        // generated (and emailed) if it does.
        password_reset_throttle_hit('customer', $email);

        if ($customer) {

            $rawToken  = create_customer_reset_token((int) $customer['id']);
            $resetLink = site_url('account/reset-password.php?token=' . $rawToken);

            // Send the reset email. send_email() never throws: on
            // any failure (SMTP down, misconfigured, invalid) it
            // logs and returns false, and the flow below continues
            // exactly as if nothing went wrong - the generic
            // "If that email belongs to an account..." message is
            // shown either way, so no enumeration is possible and
            // the primary flow never breaks because of email.
            $message = customer_password_reset_email($resetLink);
            send_email($customer['email'], $customer['name'], $message['subject'], $message['html'], $message['text']);
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
    <title>Forgot Password | MoonAura Crystals</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/account.css') ?>">
</head>
<body>

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <section class="account-auth-page">

        <div class="account-auth-box">

            <h1>Forgot Password</h1>
            <p class="account-auth-subtitle">Enter your account email to generate a reset link.</p>

            <?php if (!empty($errors)): ?>
                <div class="account-alert account-alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)): ?>
                <div class="account-alert account-alert-success">
                    If that email belongs to an account, a reset link has been sent to it.
                </div>
            <?php endif; ?>

            <form method="post" action="forgot-password.php">

                <?= csrf_field() ?>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus>

                <button type="submit" class="btn btn-primary">Generate Reset Link</button>

            </form>

            <p class="account-auth-footer">
                Remembered it? <a href="login.php">Back to Login</a>
            </p>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
