<?php
/* ===================================================================
   ADMIN RECOVERY CODES - TXT DOWNLOAD (Phase 5F.1)
   -------------------------------------------------------------------
   Serves the one-time recovery codes as a .txt attachment. The codes
   themselves exist ONLY in the admin's session (set when they were
   generated on admin/2fa-setup.php) - the database stores only
   SHA-256 hashes, so there is nothing here to leak and no way to
   re-download a previous set.

   SECURITY:
   - POST + CSRF only (a download can never be triggered by an
     external link / cross-site request).
   - Behind require_admin_login().
   - The plaintext is cleared from the session after the download, so
     it can be fetched exactly once.
   - Response headers disable caching so the codes don't linger in a
     browser cache.
   - The event is written to admin_security_log (codes never logged).
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/recovery-codes.php';

require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('2fa-setup.php');
}

csrf_verify();

$once = $_SESSION['admin_recovery_codes_once'] ?? null;

if (empty($once['codes'])) {
    flash_set('success', 'There are no recovery codes to download right now.');
    redirect('2fa-setup.php');
}

$admin = current_admin();
$txt   = recovery_codes_txt($once['codes'], (string) ($admin['email'] ?? ''));

unset($_SESSION['admin_recovery_codes_once']);

log_admin_security_event((int) $admin['id'], 'recovery_codes_downloaded', 'batch ' . ($once['batch_id'] ?? '-'));

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="moonaura-admin-recovery-codes.txt"');
header('Content-Length: ' . strlen($txt));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo $txt;
exit;
