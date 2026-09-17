<?php
/* ===================================================================
   CUSTOMER RESET PASSWORD (Phase 5B)
   -------------------------------------------------------------------
   Validates the token from the URL against the HASHED value stored
   in customer_password_resets - checks it exists, hasn't expired, and
   hasn't already been used - before allowing a new password to be
   set. Mirrors the admin flow (dashboard/reset-password.php).

   On success the token is marked used (single-use) and the customer
   is redirected to the login page with a success flash message - they
   are deliberately NOT auto-logged-in.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-functions.php';
require_once __DIR__ . '/../includes/login-security.php';

$rawToken  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$resetRecord = get_valid_customer_reset_token($rawToken);

$errors        = [];
$tokenIsValid  = $resetRecord !== null;

if (!$tokenIsValid) {
    $errors[] = 'This password reset link is invalid, expired, or has already been used. Please request a new one.';
}


/* ==========================================
   HANDLE THE NEW PASSWORD SUBMISSION
========================================== */

if ($tokenIsValid && $_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = db()->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
        $update->execute([$newHash, $resetRecord['customer_id']]);

        // Mark the token used - it can never be used again, even if
        // it hasn't expired yet.
        consume_customer_reset_token((int) $resetRecord['id']);

        // Clear any login lockout on this account - the reset proves
        // ownership of the email, so a fresh start for login attempts.
        $emailStmt = db()->prepare('SELECT email FROM customers WHERE id = ?');
        $emailStmt->execute([$resetRecord['customer_id']]);
        $customerEmail = $emailStmt->fetchColumn();

        if ($customerEmail) {
            login_attempt_succeeded($customerEmail);
        }

        flash_set('success', 'Password updated successfully. Please login.');
        redirect('login.php');
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
    <title>Reset Password | MoonAura Crystals</title>
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

            <h1>Reset Password</h1>

            <?php if (!empty($errors)): ?>
                <div class="account-alert account-alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($tokenIsValid): ?>

                <p class="account-auth-subtitle">Enter a new password for your account.</p>

                <form method="post" action="reset-password.php">

                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= h($rawToken) ?>">

                    <label for="new_password">New Password</label>
                    <div class="pw-field">
                        <input type="password" id="new_password" name="new_password" minlength="8" required autofocus>
                        <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false" aria-controls="new_password">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <label for="confirm_password">Confirm New Password</label>
                    <div class="pw-field">
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                        <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false" aria-controls="confirm_password">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Password</button>

                </form>

            <?php else: ?>

                <p class="account-auth-footer" style="margin-top: 0;">
                    <a href="forgot-password.php">Request a new reset link</a>
                </p>

            <?php endif; ?>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
    <script src="<?= versioned_asset('assets/js/password-toggle.js') ?>"></script>

</body>
</html>
