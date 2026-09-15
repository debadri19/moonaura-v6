<?php
/* ===================================================================
   CHANGE PASSWORD
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';

require_customer_login();

$customer = current_customer();
$activeAccountPage = 'change-password';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM customers WHERE id = ?');
    $stmt->execute([$customer['id']]);
    $passwordHash = $stmt->fetchColumn();

    if (!password_verify($currentPassword, $passwordHash)) {
        $errors[] = 'Your current password is incorrect.';
    }

    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = db()->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $customer['id']]);

        flash_set('success', 'Password updated successfully.');
        redirect('change-password.php');
    }
}

$successMessage = flash_get('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Change Password | MoonAura Crystals</title>
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

    <section class="account-page">

        <div class="container">

            <h1>My Account</h1>

            <?php include __DIR__ . '/includes/account-nav.php'; ?>

            <div class="account-card">

                <h2>Change Password</h2>

                <?php if ($successMessage): ?>
                    <div class="account-alert account-alert-success"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="account-alert account-alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="change-password.php">

                    <?= csrf_field() ?>

                    <label for="current_password">Current Password</label>
                    <div class="pw-field">
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false" aria-controls="current_password">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <label for="new_password">New Password</label>
                    <div class="pw-field">
                        <input type="password" id="new_password" name="new_password" minlength="8" required>
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

            </div>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
    <script src="<?= versioned_asset('assets/js/password-toggle.js') ?>"></script>

</body>
</html>
