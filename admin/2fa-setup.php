<?php
/* ===================================================================
   ADMIN 2FA SETUP (Phase 5E)
   -------------------------------------------------------------------
   "Security Settings" page for Two-Factor Authentication:
     - Disabled admin: a fresh TOTP secret + QR code is shown (Google /
       Microsoft Authenticator, Authy all accept the otpauth:// URI).
       The admin scans it, enters the current 6-digit code, and 2FA is
       enabled ONLY after that first code verifies.
     - Enabled admin: status is shown and 2FA can be disabled, but
       ONLY by entering a valid current OTP (no bypass route).

   SECURITY:
   - Page is behind require_admin_login() (a 2FA-pending session
     cannot reach it).
   - Every mutation (generate secret / enable / disable / recovery
     codes) is a CSRF-protected POST.
   - The secret is stored encrypted (two_fa_encrypt_secret()); the raw
     Base32 secret exists only on this page for the QR/manual entry.
   - The shared login lockout stays active on every OTP check.
   - A session attempt counter caps wrong codes; exceeding it rotates
     the pending secret (setup) or blocks further tries (disable) so
     a secret can't be brute-forced.
   - Missing ADMIN_2FA_ENCRYPTION_KEY fails closed: the page explains
     the problem and refuses to operate rather than store a plain-text
     secret.

   RECOVERY CODES (Phase 5F.1, additive - the TOTP architecture is
   untouched):
   - Only shown once 2FA is enabled. Generate/Regenerate creates a new
     batch of 10 one-time codes (only SHA-256 hashes are stored); the
     plaintext is shown exactly once via the session and can be
     downloaded as a .txt from admin/recovery-download.php.
   - Regenerating automatically invalidates the previous batch.
   - Hashes are never displayed; codes are never emailed.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/login-security.php';
require_once __DIR__ . '/../includes/two-factor.php';
require_once __DIR__ . '/../includes/recovery-codes.php';

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Security Settings';
$activePage = 'settings';

$twofa      = get_admin_2fa((int) $admin['id']);
$errors     = [];
$successMessage = flash_get('success');

$configOk   = two_fa_secret_key() !== null;
$isEnabled  = $twofa && (int) $twofa['two_factor_enabled'] === 1;

$secret     = null;       // raw Base32 secret (display only)
$otpauthUri = '';

if (!$isEnabled && $configOk && $twofa) {

    // Make sure a pending secret exists so the QR is always available.
    if (empty($twofa['two_factor_secret'])) {
        $fresh = two_fa_encrypt_secret(generate_totp_secret());
        if ($fresh !== null) {
            $stmt = db()->prepare(
                'UPDATE admin_users SET two_factor_secret = ?, two_factor_enabled = 0, two_factor_enabled_at = NULL WHERE id = ?'
            );
            $stmt->execute([$fresh, $admin['id']]);
            $twofa['two_factor_secret'] = $fresh;
        }
    }

    $secret = two_fa_decrypt_secret($twofa['two_factor_secret']);
}

if ($secret !== null) {
    $otpauthUri = two_factor_otpauth_uri('MoonAura Admin', (string) $admin['email'], $secret);
}


/* ==========================================
   HANDLE FORM SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $twofa) {

    csrf_verify();

    $action  = $_POST['action'] ?? '';
    $code    = trim($_POST['code'] ?? '');

    // --- Recovery-code actions (Phase 5F.1) -----------------------
    // These are authenticated admin actions (the page is behind
    // require_admin_login()) protected by CSRF; they do not require an
    // OTP and are not gated by the login lockout. The plaintext codes
    // are held in the session ONLY for the one-time display/download
    // panel on the very next page load; only hashes reach the DB.
    if ($action === 'recovery_generate' && $isEnabled) {

        $codes   = generate_recovery_codes();
        $batchId = store_recovery_codes((int) $admin['id'], $codes);

        log_admin_security_event((int) $admin['id'], 'recovery_codes_generated', 'batch ' . $batchId . ' (' . count($codes) . ' codes)');

        $_SESSION['admin_recovery_codes_once'] = [
            'batch_id'     => $batchId,
            'codes'        => $codes,
            'generated_at' => time(),
        ];

        redirect('2fa-setup.php');

    } elseif ($action === 'recovery_regenerate' && $isEnabled) {

        $old     = get_active_recovery_batch((int) $admin['id']);
        $codes   = generate_recovery_codes();
        $batchId = store_recovery_codes((int) $admin['id'], $codes);

        log_admin_security_event(
            (int) $admin['id'],
            'recovery_codes_regenerated',
            'batch ' . $batchId . ' invalidates batch ' . ($old['batch_id'] ?? '-')
        );

        $_SESSION['admin_recovery_codes_once'] = [
            'batch_id'     => $batchId,
            'codes'        => $codes,
            'generated_at' => time(),
        ];

        redirect('2fa-setup.php');

    } elseif ($action === 'recovery_done') {

        unset($_SESSION['admin_recovery_codes_once']);
        flash_set('success', 'Recovery codes saved - keep them somewhere safe.');
        redirect('2fa-setup.php');
    }

    $attempts = (int) ($_SESSION['admin_2fa_setup_attempts'] ?? 0);

    // Shared lockout check, reused for both enable and disable.
    $lock = login_attempt_status($admin['email']);

    if ($lock['locked']) {
        $errors[] = login_lock_message($lock);

    } elseif ($action === 'generate' && !$isEnabled) {

        // Rotate the pending secret (scan a fresh QR).
        if (!$configOk) {
            $errors[] = 'ADMIN_2FA_ENCRYPTION_KEY is not configured.';
        } else {
            $fresh = two_fa_encrypt_secret(generate_totp_secret());
            if ($fresh === null) {
                $errors[] = 'Could not encrypt a new secret.';
            } else {
                $stmt = db()->prepare(
                    'UPDATE admin_users SET two_factor_secret = ?, two_factor_enabled = 0, two_factor_enabled_at = NULL WHERE id = ?'
                );
                $stmt->execute([$fresh, $admin['id']]);
                unset($_SESSION['admin_2fa_setup_attempts']);
                flash_set('success', 'A new secret has been generated - scan it again.');
                redirect('2fa-setup.php');
            }
        }

    } elseif ($action === 'enable' && !$isEnabled && $secret !== null) {

        // Verify the first OTP BEFORE enabling (fail closed: no valid
        // code, no enable).
        if ($attempts >= ADMIN_2FA_MAX_ATTEMPTS) {
            // Too many wrong codes against this secret - rotate it so
            // nothing can be brute-forced, then let them rescan.
            $fresh = two_fa_encrypt_secret(generate_totp_secret());
            if ($fresh !== null) {
                $stmt = db()->prepare(
                    'UPDATE admin_users SET two_factor_secret = ?, two_factor_enabled = 0, two_factor_enabled_at = NULL WHERE id = ?'
                );
                $stmt->execute([$fresh, $admin['id']]);
                $twofa['two_factor_secret'] = $fresh;
                $secret = two_fa_decrypt_secret($fresh);
                if ($secret !== null) {
                    $otpauthUri = two_factor_otpauth_uri('MoonAura Admin', (string) $admin['email'], $secret);
                }
            }
            unset($_SESSION['admin_2fa_setup_attempts']);
            $errors[] = 'Too many incorrect codes. A new secret was generated - please scan it again.';

        } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
            $errors[] = 'Please enter the 6-digit code from your authenticator app.';

        } elseif (verify_totp($secret, $code)) {

            $stmt = db()->prepare(
                'UPDATE admin_users SET two_factor_enabled = 1, two_factor_enabled_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$admin['id']]);
            login_attempt_succeeded($admin['email']);
            unset($_SESSION['admin_2fa_setup_attempts']);
            $isEnabled = true;
            flash_set('success', 'Two-factor authentication is now enabled for your account.');
            redirect('2fa-setup.php');

        } else {

            $_SESSION['admin_2fa_setup_attempts'] = $attempts + 1;
            $lock = login_attempt_failed($admin['email']);
            $errors[] = $lock['locked']
                ? login_lock_message($lock)
                : 'That code was not recognised. Check the current code in your authenticator app and try again.';
        }

    } elseif ($action === 'disable' && $isEnabled) {

        // Disabling requires a valid current OTP - no bypass.
        $currentSecret = two_fa_decrypt_secret($twofa['two_factor_secret']);

        if ($attempts >= ADMIN_2FA_MAX_ATTEMPTS) {
            $errors[] = 'Too many attempts. Please try again in a moment.';
        } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
            $errors[] = 'Please enter the 6-digit code from your authenticator app.';
        } elseif ($currentSecret === null) {
            $errors[] = 'Your 2FA secret could not be read, so it cannot be disabled here. Contact support.';
        } elseif (verify_totp($currentSecret, $code)) {

            $stmt = db()->prepare(
                'UPDATE admin_users SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_enabled_at = NULL WHERE id = ?'
            );
            $stmt->execute([$admin['id']]);
            login_attempt_succeeded($admin['email']);
            unset($_SESSION['admin_2fa_setup_attempts']);
            $isEnabled = false;
            flash_set('success', 'Two-factor authentication has been disabled.');
            redirect('2fa-setup.php');

        } else {

            $_SESSION['admin_2fa_setup_attempts'] = $attempts + 1;
            $lock = login_attempt_failed($admin['email']);
            $errors[] = $lock['locked']
                ? login_lock_message($lock)
                : 'That code was not recognised. Check the current code in your authenticator app and try again.';
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
    <title><?= h($pageTitle) ?> | MoonAura Admin</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= versioned_asset('admin/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-body">

    <div class="admin-wrapper">

        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

        <div class="admin-main">

            <?php include __DIR__ . '/includes/admin-header.php'; ?>

            <div class="admin-content">

                <div class="settings-subpage">

                <div class="admin-toolbar settings-back-row admin-toolbar-end admin-nav-toolbar">
                    <a href="settings.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Settings
                    </a>
                </div>

                <?php if (!$configOk): ?>

                    <div class="admin-alert admin-alert-error">
                        <strong>Two-factor authentication is unavailable:</strong>
                        the <code>ADMIN_2FA_ENCRYPTION_KEY</code> environment variable is not set.
                        2FA refuses to operate (and to store any secret) without it. Ask the server
                        administrator to add a 32-byte base64 key, e.g. via
                        <code>php -r "echo base64_encode(random_bytes(32));"</code>.
                    </div>

                <?php else: ?>

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

                    <?php if ($isEnabled): ?>

                        <div class="admin-form-card">
                            <h3>Two-Factor Authentication is On</h3>
                            <p style="color: var(--text-light);">
                                Your account is protected by a time-based one-time password.
                                Every admin login now requires a 6-digit code from your
                                authenticator app.
                                <?php if (!empty($twofa['two_factor_enabled_at'])): ?>
                                    Enabled since <?= h(date('d M Y, H:i', strtotime($twofa['two_factor_enabled_at']))) ?>.
                                <?php endif; ?>
                            </p>

                            <form method="post" action="2fa-setup.php" style="margin-top: 16px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="disable">

                                <label for="disable_code">Current Authentication Code</label>
                                <input type="text" id="disable_code" name="code" inputmode="numeric"
                                       pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                                       placeholder="123456" style="max-width: 220px;" required>

                                <div style="margin-top: 16px;">
                                    <button type="submit" class="admin-btn-danger">Disable Two-Factor Authentication</button>
                                </div>
                            </form>
                        </div>

                        <?php
                        // Phase 5F.1 - recovery codes panel. The plaintext
                        // set lives in the session only right after
                        // generation/regeneration (one-time display +
                        // download); the DB keeps only SHA-256 hashes.
                        $recoveryOnce    = $_SESSION['admin_recovery_codes_once'] ?? null;
                        $recoveryRemain  = active_recovery_codes_remaining((int) $admin['id']);
                        ?>

                        <div class="admin-form-card">
                            <h3>Recovery Codes</h3>
                            <p style="color: var(--text-light);">
                                One-time backup codes let you sign in when you
                                don't have your authenticator app. Each code can
                                be used exactly once - store them somewhere safe.
                            </p>

                            <?php if (!empty($recoveryOnce['codes'])): ?>

                                <div class="admin-alert admin-alert-warning">
                                    <strong>Save these codes now</strong> - they are shown
                                    only once and are never emailed. Generating a new set
                                    invalidates these.
                                </div>

                                <div class="recovery-codes-list">
                                    <?php foreach ($recoveryOnce['codes'] as $rc): ?>
                                        <code><?= h(recovery_code_display($rc)) ?></code>
                                    <?php endforeach; ?>
                                </div>

                                <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <form method="post" action="recovery-download.php">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="admin-btn-primary">
                                            <i class="fa-solid fa-download"></i>
                                            Download as .txt
                                        </button>
                                    </form>

                                    <form method="post" action="2fa-setup.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="recovery_done">
                                        <button type="submit" class="admin-btn-secondary">
                                            I've saved these codes
                                        </button>
                                    </form>
                                </div>

                            <?php else: ?>

                                <p style="color: var(--text-light);">
                                    <?php if ($recoveryRemain > 0): ?>
                                        You have <strong><?= (int) $recoveryRemain ?></strong> unused
                                        recovery code<?= $recoveryRemain === 1 ? '' : 's' ?> remaining.
                                        Regenerating invalidates the current set.
                                    <?php else: ?>
                                        You don't have any active recovery codes yet.
                                    <?php endif; ?>
                                </p>

                                <form method="post" action="2fa-setup.php" style="margin-top: 16px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action"
                                           value="<?= $recoveryRemain > 0 ? 'recovery_regenerate' : 'recovery_generate' ?>">
                                    <button type="submit" class="admin-btn-secondary">
                                        <i class="fa-solid fa-key"></i>
                                        <?= $recoveryRemain > 0 ? 'Regenerate Recovery Codes' : 'Generate Recovery Codes' ?>
                                    </button>
                                </form>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="admin-form-card">
                            <h3>Enable Two-Factor Authentication</h3>
                            <p style="color: var(--text-light);">
                                Scan the QR code with <strong>Google Authenticator</strong>,
                                <strong>Microsoft Authenticator</strong> or <strong>Authy</strong>,
                                then enter the 6-digit code to confirm. Codes refresh every 30 seconds.
                            </p>

                            <?php if ($secret !== null): ?>

                                <div style="text-align: center; margin: 20px 0;">
                                    <div id="two-fa-qrcode" style="display: inline-block; background: #fff; padding: 10px; border-radius: 10px;"></div>
                                    <p style="color: var(--text-light); font-size: 13px;">
                                        Or enter this key manually:
                                        <code style="display: inline-block; margin-top: 6px; padding: 6px 10px; background: var(--bg-alt); border-radius: 6px;">
                                            <?= h($secret) ?>
                                        </code>
                                    </p>
                                </div>

                                <form method="post" action="2fa-setup.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="enable">

                                    <label for="enable_code">6-Digit Code from Authenticator</label>
                                    <input type="text" id="enable_code" name="code" inputmode="numeric"
                                           pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                                           placeholder="123456" style="max-width: 220px;" required autofocus>

                                    <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                                        <button type="submit" class="admin-btn-primary">Verify &amp; Enable</button>
                                    </div>
                                </form>

                                <form method="post" action="2fa-setup.php" style="margin-top: 10px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="generate">
                                    <button type="submit" class="admin-btn-secondary">
                                        <i class="fa-solid fa-rotate"></i>
                                        Generate a New Secret
                                    </button>
                                </form>

                            <?php else: ?>
                                <p style="color: var(--danger, #b3261e);">
                                    Your 2FA secret could not be decrypted. Please try again or
                                    contact support.
                                </p>
                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>

                </div>

            </div>

            <?php include __DIR__ . '/includes/admin-footer.php'; ?>

        </div>

    </div>

    <script src="<?= versioned_asset('admin/assets/js/qrcode.js', 'assets/js/qrcode.js') ?>"></script>
    <script>
        (function () {
            var uri = <?= json_encode($otpauthUri, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var box = document.getElementById('two-fa-qrcode');
            if (box && uri && typeof qrcode === 'function') {
                var qr = qrcode(0, 'M');
                qr.addData(uri);
                qr.make();
                box.innerHTML = qr.createImgTag(4, 4);
            }
        })();
    </script>

</body>
</html>
