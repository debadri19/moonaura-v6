<?php
/* ===================================================================
   ADMIN - RESET PASSWORD
   -------------------------------------------------------------------
   Validates the token from the URL against the HASHED value stored
   in admin_password_resets - checks it exists, hasn't expired, and
   hasn't already been used - before allowing a new password to be set.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$rawToken  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = $rawToken !== '' ? hash('sha256', $rawToken) : '';

$errors = [];
$tokenIsValid = false;
$resetRecord  = null;

if ($tokenHash !== '') {

    $stmt = db()->prepare(
        'SELECT id, admin_id, expires_at, used_at
         FROM admin_password_resets
         WHERE token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $resetRecord = $stmt->fetch();

    if ($resetRecord
        && $resetRecord['used_at'] === null
        && strtotime($resetRecord['expires_at']) > time()
    ) {
        $tokenIsValid = true;
    }
}

if (!$tokenIsValid) {
    $errors[] = 'This password reset link is invalid, expired, or has already been used. Please request a new one.';
}


/* ==========================================
   HANDLE THE NEW PASSWORD SUBMISSION
========================================== */

$passwordWasReset = false;

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

        $update = db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        $update->execute([$newHash, $resetRecord['admin_id']]);

        // Mark the token used - it can never be used again, even if
        // it hasn't expired yet.
        $markUsed = db()->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE id = ?');
        $markUsed->execute([$resetRecord['id']]);

        $passwordWasReset = true;
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
    <title>Reset Password | MoonAura Admin</title>
    <link rel="stylesheet" href="<?= versioned_asset('admin/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-auth-page">

    <div class="admin-auth-box">

        <h1>Reset Password</h1>

        <?php if ($passwordWasReset): ?>

            <div class="admin-alert admin-alert-success">
                Your password has been updated. You can now log in with your new password.
            </div>

            <p style="text-align: center; margin-top: 12px;">
                <a href="login.php" class="admin-btn-primary" style="display:inline-block; text-decoration:none;">Go to Login</a>
            </p>

        <?php else: ?>

            <?php if (!empty($errors)): ?>
                <div class="admin-alert admin-alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($tokenIsValid): ?>

                <form method="post" action="reset-password.php">

                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= h($rawToken) ?>">

                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" minlength="8" required autofocus>

                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

                    <button type="submit" class="admin-btn-primary">Update Password</button>

                </form>

            <?php else: ?>

                <p style="text-align: center;">
                    <a href="forgot-password.php" style="color: var(--primary);">Request a new reset link</a>
                </p>

            <?php endif; ?>

        <?php endif; ?>

    </div>

    <?php include __DIR__ . '/includes/page-loader.php'; ?>

</body>
</html>
