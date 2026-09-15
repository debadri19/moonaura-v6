<?php
/* ===================================================================
   ADMIN LOGIN (Phase 5E - 2FA-aware)
   -------------------------------------------------------------------
   Step 1 of a two-step login when the admin has 2FA enabled:
     Email + Password -> attempt_admin_login() -> if 2FA enabled,
     the admin is parked in $_SESSION['admin_2fa_pending'] and sent to
     2fa-verify.php for a TOTP code. Only after that is
     complete_admin_login() called and $_SESSION['admin_id'] set.
   Admins without 2FA go straight through (existing behaviour).
   The Phase 6/5G brute-force lockout still runs on the password step.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/login-security.php';

// If already logged in, skip the login form.
if (is_admin_logged_in()) {
    redirect('dashboard.php');
}

// A half-finished 2FA login (password OK, OTP pending) should go back
// to the 2FA step rather than the password form.
if (!empty($_SESSION['admin_2fa_pending'])) {
    redirect('2fa-verify.php');
}

$errors = [];

/* ==========================================
   HANDLE LOGIN SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } else {

        // Phase 6 login security: check the brute-force lockout FIRST.
        // A locked email is rejected without ever attempting (or even
        // looking up) the password, and the message never reveals
        // whether the account exists.
        $lock = login_attempt_status($email);

        if ($lock['locked']) {

            $errors[] = login_lock_message($lock);

        } else {

            $admin = attempt_admin_login($email, $password);

            if ($admin !== null) {

                // Credentials are valid - the shared lockout has done
                // its job, so clear it for the password step. (2FA
                // attempts start their own counter, see 2fa-verify.)
                login_attempt_succeeded($email);

                if (!empty($admin['two_factor_enabled'])) {

                    // Step 2 required: park the admin for the OTP step.
                    // admin_id is deliberately NOT set yet.
                    $_SESSION['admin_2fa_pending'] = (int) $admin['id'];
                    redirect('2fa-verify.php');

                }

                // No 2FA - complete the login now.
                complete_admin_login((int) $admin['id']);
                redirect('dashboard.php');
            }

            // Record the failure; the 5th one locks the email (see
            // includes/login-security.php). If that just happened, show
            // the lock message instead of the generic one.
            $lock    = login_attempt_failed($email);
            $errors[] = $lock['locked'] ? login_lock_message($lock) : 'Incorrect email or password.';
        }
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
    <title>Admin Login | MoonAura Crystals</title>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= versioned_asset('admin/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-auth-page">

    <div class="admin-auth-box">

        <h1>MoonAura Admin</h1>
        <p class="admin-auth-subtitle">Log in to manage your store.</p>

        <?php if ($successMessage): ?>
            <div class="admin-alert admin-alert-success">
                <?= h($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="admin-alert admin-alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php">

            <?= csrf_field() ?>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>

            <label for="password">Password</label>
            <div class="pw-field">
                <input type="password" id="password" name="password" required>
                <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false" aria-controls="password">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                </button>
            </div>

            <button type="submit" class="admin-btn-primary">Log In</button>

        </form>

        <a href="forgot-password.php" class="admin-auth-link">Forgot Password?</a>

    </div>

    <!-- #33: shared toggle script - same file account/login.php loads,
         reached via a relative "../" since admin pages keep their own
         self-contained admin/assets/ tree (see admin.css's header
         comment / admin_url() in includes/functions.php: the admin
         panel is designed to be servable from a split host in
         production, so every other admin asset reference is already
         scoped to admin/assets/ only - this is the one deliberate
         exception, to satisfy "one shared file, not duplicated" for
         this feature. See the final report for the tradeoff this
         implies if admin ever is split to a separate host. -->
    <script src="<?= versioned_asset('admin/assets/js/password-toggle.js', 'assets/js/password-toggle.js') ?>"></script>
    <?php include __DIR__ . '/includes/page-loader.php'; ?>

</body>
</html>
