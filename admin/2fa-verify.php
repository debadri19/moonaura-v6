<?php
/* ===================================================================
   ADMIN 2FA VERIFY (Phase 5E)
   -------------------------------------------------------------------
   Step 2 of the admin login flow when 2FA is enabled:
     admin/login.php has already validated email+password and parked
     the admin's id in $_SESSION['admin_2fa_pending']. This page asks
     for the 6-digit TOTP code, verifies it, and ONLY then calls
     complete_admin_login() (which regenerates the session and sets
     $_SESSION['admin_id'] - the key every protected route checks).

   FAIL-CLOSED / NO-BYPASS:
   - No pending admin id  -> straight back to login.php.
   - Already fully logged in -> straight to dashboard.php.
   - Pending admin no longer active, 2FA disabled, or secret not
     decryptable -> pending state cleared, back to login.php (an
     attacker can never skip the OTP step by tampering with 2FA state).
   - OTP codes are verified against the encrypted stored secret and
     are never themselves stored anywhere.

   BRUTE FORCE:
   - The shared login lockout (includes/login-security.php) stays
     active: every wrong code counts as a failed login for that email,
     and the 5th locks the email exactly like the password step.
   - Additionally, a session-level attempt counter invalidates the
     pending step after ADMIN_2FA_MAX_ATTEMPTS wrong codes, forcing a
     fresh password login.
   - The same lockout + attempt cap apply to the recovery-code path.

   RECOVERY CODES (Phase 5F.1):
   - A "Use a recovery code instead" option lets an admin who has lost
     their authenticator sign in with a one-time backup code.
   - Codes are validated against the admin's ACTIVE batch only and are
     consumed atomically (usable exactly once). See
     includes/recovery-codes.php.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/login-security.php';
require_once __DIR__ . '/../includes/two-factor.php';
require_once __DIR__ . '/../includes/recovery-codes.php';

// Fully logged in? Nothing to verify.
if (is_admin_logged_in()) {
    redirect('dashboard.php');
}

// Self-service "cancel" - drop the pending 2FA step and go back to
// the password form (this only ever discards the admin's own pending
// login, so there is nothing for an attacker to gain).
if (isset($_GET['cancel'])) {
    unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_2fa_email'], $_SESSION['admin_2fa_attempts']);
    redirect('login.php');
}

// No pending 2FA login -> nothing to do here.
if (empty($_SESSION['admin_2fa_pending'])) {
    redirect('login.php');
}

$pendingAdminId = (int) $_SESSION['admin_2fa_pending'];
$admin = get_admin_2fa($pendingAdminId);

// Fail closed: unknown / disabled account, 2FA off, or an
// undecryptable secret means we cannot prove a valid OTP exists.
$secret = $admin ? two_fa_decrypt_secret($admin['two_factor_secret']) : null;

if (!$admin
    || $admin['status'] !== 'active'
    || (int) $admin['two_factor_enabled'] !== 1
    || $secret === null
) {
    unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_2fa_email']);
    redirect('login.php');
}

$errors = [];

/* ==========================================
   HANDLE THE OTP SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $useRecovery = !empty($_POST['use_recovery']);
    $code        = trim($_POST['code'] ?? '');

    // Shared brute-force protection applies to BOTH the TOTP and the
    // recovery-code paths: the email lockout and the session attempt
    // cap below rate-limit every OTP/recovery entry on this step.
    $lock = login_attempt_status($admin['email']);

    if ($lock['locked']) {

        $errors[] = login_lock_message($lock);

    } else {

        $attempts = (int) ($_SESSION['admin_2fa_attempts'] ?? 0);

        if ($attempts >= ADMIN_2FA_MAX_ATTEMPTS) {
            // Too many tries on this pending step - force a full
            // re-login rather than letting someone grind codes.
            unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_2fa_email'], $_SESSION['admin_2fa_attempts']);
            redirect('login.php');
        }

        if ($useRecovery) {

            /* ---------------- RECOVERY-CODE PATH ---------------- */
            $normalized = normalize_recovery_code($code);

            if ($normalized === '') {
                $errors[] = 'Please enter a recovery code.';

            } else {

                $result = consume_recovery_code((int) $admin['id'], $code);

                if ($result['status'] === 'ok') {

                    // Valid unused code - grant the session. It is
                    // already marked consumed (see consume_recovery_code),
                    // so a second use can never succeed.
                    log_admin_security_event(
                        (int) $admin['id'],
                        'recovery_code_used',
                        'login via recovery code (batch ' . ($result['batch_id'] ?? '-') . ', code #' . $result['code_id'] . ')'
                    );
                    login_attempt_succeeded($admin['email']);
                    complete_admin_login((int) $admin['id']);
                    redirect('dashboard.php');
                }

                // Reused or invalid - count it in the session cap AND
                // the shared lockout, exactly like a wrong TOTP.
                $_SESSION['admin_2fa_attempts'] = $attempts + 1;
                $lock                           = login_attempt_failed($admin['email']);

                if ($result['status'] === 'reused') {
                    log_admin_security_event((int) $admin['id'], 'recovery_code_reuse_attempt', 'code #' . $result['code_id']);
                    $message = 'This recovery code has already been used.';
                } else {
                    log_admin_security_event((int) $admin['id'], 'recovery_code_invalid');
                    $message = 'That recovery code is not valid.';
                }

                $errors[] = $lock['locked'] ? login_lock_message($lock) : $message;
            }

        } elseif (preg_match('/^[0-9]{6}$/', $code)) {

            /* ------------------- TOTP PATH ------------------- */
            if (verify_totp($secret, $code)) {

                // Valid OTP - grant the session. complete_admin_login()
                // regenerates the session id (anti-fixation) and clears
                // the pending state.
                login_attempt_succeeded($admin['email']);
                complete_admin_login((int) $admin['id']);
                redirect('dashboard.php');
            }

            // Wrong code - count it here (session) and in the shared
            // lockout (email), same as a bad password would.
            $_SESSION['admin_2fa_attempts'] = $attempts + 1;

            $lock    = login_attempt_failed($admin['email']);
            $errors[] = $lock['locked']
                ? login_lock_message($lock)
                : 'That code was not recognised. Check the current code in your authenticator app and try again.';

        } else {

            $errors[] = $useRecovery
                ? 'Please enter a recovery code.'
                : 'Please enter the 6-digit code from your authenticator app.';
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
    <title>Two-Factor Verification | MoonAura Admin</title>
    <link rel="stylesheet" href="<?= versioned_asset('admin/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-auth-page">

    <div class="admin-auth-box">

        <h1>Two-Factor Verification</h1>
        <p class="admin-auth-subtitle">
            Enter the 6-digit code from your authenticator app<?= $admin ? ' for ' . h($admin['name']) : '' ?>.
        </p>

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

        <form method="post" action="2fa-verify.php">

            <?= csrf_field() ?>

            <label for="code" id="code-label">Authentication Code</label>
            <input
                type="text"
                id="code"
                name="code"
                autocomplete="one-time-code"
                placeholder="123456"
                inputmode="numeric"
                maxlength="64"
                required
                autofocus
            >

            <label class="admin-recovery-toggle">
                <input type="checkbox" name="use_recovery" value="1" id="use-recovery">
                I don't have my code - use a recovery code instead
            </label>

            <button type="submit" class="admin-btn-primary">Verify &amp; Log In</button>

        </form>

        <a href="2fa-verify.php?cancel=1" class="admin-auth-link">Cancel and go back to login</a>

    </div>

    <script>
        (function () {
            var toggle  = document.getElementById('use-recovery');
            var input   = document.getElementById('code');
            var label   = document.getElementById('code-label');

            if (toggle && input && label) {
                toggle.addEventListener('change', function () {
                    if (toggle.checked) {
                        label.textContent       = 'Recovery Code';
                        input.placeholder       = 'XXXX-XXXX-XXXX';
                        input.setAttribute('inputmode', 'text');
                    } else {
                        label.textContent       = 'Authentication Code';
                        input.placeholder       = '123456';
                        input.setAttribute('inputmode', 'numeric');
                    }
                });
            }
        })();
    </script>
    <?php include __DIR__ . '/includes/page-loader.php'; ?>

</body>
</html>
