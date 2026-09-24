<?php
/* ===================================================================
   CUSTOMER LOGIN
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../includes/customer-functions.php';
require_once __DIR__ . '/../includes/login-security.php';

if (is_customer_logged_in()) {
    redirect('dashboard.php');
}

$errors     = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $errors[] = 'Please enter both Email or Mobile Number and password.';
    } elseif (!is_customer_login_identifier($identifier)) {
        $errors[] = 'Invalid Email or Mobile Number';
    } else {

        // Phase 6 login security: same per-identifier brute-force
        // lockout as the admin login. A locked identifier is rejected
        // before the password is ever checked, and the message never
        // reveals whether the account exists.
        $lockKey = customer_login_lock_key($identifier);
        $lock    = login_attempt_status($lockKey);

        if ($lock['locked']) {

            $errors[] = login_lock_message($lock);

        } else {

            $success = attempt_customer_login($identifier, $password);

            if ($success) {

                login_attempt_succeeded($lockKey);

                $customer = current_customer();

                // Attach any guest orders placed under this email/phone
                // since the last time they logged in.
                link_guest_orders_to_customer((int) $customer['id'], $customer['email'], $customer['phone']);

                redirect('dashboard.php');
            }

            // Record the failure; the 5th one locks the identifier (see
            // includes/login-security.php).
            $lock     = login_attempt_failed($lockKey);
            $errors[] = $lock['locked'] ? login_lock_message($lock) : 'Incorrect email or password.';
        }
    }
}

$successMessage = flash_get('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php theme_boot(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Log In | MoonAura Crystals</title>
    <meta name="robots" content="noindex, follow">

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

        <div class="account-auth-box account-login-box">

            <div class="theme-toggle" role="group" aria-label="Color theme">
                <button type="button" class="theme-toggle-btn" data-theme-mode="light" aria-pressed="false">Light</button>
                <button type="button" class="theme-toggle-btn" data-theme-mode="dark" aria-pressed="false">Dark</button>
                <button type="button" class="theme-toggle-btn" data-theme-mode="system" aria-pressed="false">System</button>
            </div>

            <h1>Welcome Back</h1>
            <p class="account-auth-subtitle">Log in to view your orders and account details.</p>

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

            <form method="post" action="login.php">

                <?= csrf_field() ?>

                <label for="identifier">Email or Mobile Number</label>
                <input type="text" id="identifier" name="identifier" value="<?= h($identifier) ?>" autocomplete="username" required autofocus>

                <label for="password">Password</label>
                <div class="pw-field">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false" aria-controls="password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary">Log In</button>

            </form>

            <p class="account-auth-footer">
                Forgot your password? <a href="forgot-password.php">Reset it here</a><br>
                New here? <a href="register.php">Create an account</a>
            </p>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
    <script src="<?= versioned_asset('assets/js/password-toggle.js') ?>"></script>

</body>
</html>
