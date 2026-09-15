<?php
/* ===================================================================
   ADMIN - FORGOT PASSWORD (Phase 5B + 5D)
   -------------------------------------------------------------------
   Generates a one-time reset token (60-minute expiry), stores only
   its SHA-256 hash in admin_password_resets, and emails the reset
   link through the shared mailer (Phase 5D).

   SECURITY:
   - CSRF-protected form.
   - The SAME message is shown whether or not the email exists, so
     this form can't be used to enumerate valid admin accounts.
   - SMTP failures never break the flow: send_email() logs and returns
      false instead of throwing, and the same generic message is shown
      either way - nothing reveals whether an account exists or whether
      a mail was sent.
    - Reset-request throttling is independent of login lockout: cooldown
      plus a short request window, keyed by email + IP. The same
      throttle applies whether or not the email exists.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
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

    if (empty($errors) && password_reset_is_throttled('admin', $email)) {
        $errors[] = password_reset_throttle_message();
    }

    if (empty($errors)) {

        $stmt = db()->prepare('SELECT id, name, email FROM admin_users WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        // Deliberately show the same message whether or not the email
        // exists, so this form can't be used to discover valid admin
        // emails. The reset link is only actually generated (and
        // emailed) if it does.
        password_reset_throttle_hit('admin', $email);

        if ($admin) {

            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

            $stmt = db()->prepare(
                'INSERT INTO admin_password_resets (admin_id, token_hash, expires_at)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$admin['id'], $tokenHash, $expiresAt]);

            // Send the reset email. send_email() never throws: on any
            // failure it logs and returns false, and the flow below
            // continues exactly as if nothing went wrong - the same
            // generic message is shown either way.
            $resetLink = admin_url('reset-password.php?token=' . $rawToken);
            $message   = admin_password_reset_email($resetLink);
            send_email($admin['email'], $admin['name'], $message['subject'], $message['html'], $message['text']);
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
    <title>Forgot Password | MoonAura Admin</title>
    <link rel="stylesheet" href="<?= versioned_asset('admin/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-auth-page">

    <div class="admin-auth-box">

        <h1>Forgot Password</h1>
        <p class="admin-auth-subtitle">Enter your admin email to generate a reset link.</p>

        <?php if (!empty($errors)): ?>
            <div class="admin-alert admin-alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)): ?>
            <div class="admin-alert admin-alert-success">
                If that email belongs to an admin account, a reset link has been sent to it.
            </div>
        <?php endif; ?>

        <form method="post" action="forgot-password.php">

            <?= csrf_field() ?>

            <label for="email">Admin Email</label>
            <input type="email" id="email" name="email" required autofocus>

            <button type="submit" class="admin-btn-primary">Generate Reset Link</button>

        </form>

        <p style="text-align: center; margin-top: 16px; font-size: 13px;">
            <a href="login.php" style="color: var(--primary);">Back to Login</a>
        </p>

    </div>

    <?php include __DIR__ . '/includes/page-loader.php'; ?>

</body>
</html>
