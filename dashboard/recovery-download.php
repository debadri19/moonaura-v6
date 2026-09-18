<?php
/* ===================================================================
   ADMIN RECOVERY CODES - TXT DOWNLOAD (Phase 5F.1)
   -------------------------------------------------------------------
   Serves the one-time recovery codes as a .txt attachment. The codes
   themselves exist ONLY in the admin's session (set when they were
   generated on dashboard/2fa-setup.php) - the database stores only
   SHA-256 hashes, so there is nothing here to leak and no way to
   re-download a previous set.

   SECURITY:
   - POST + CSRF remains the browser initiating action (the form on
     2fa-setup.php). GET/HEAD are accepted only for an already
     authenticated admin who still has the one-time session payload,
     so download managers that probe or retry the URL as GET do not
     receive an HTML redirect.
   - Behind the same is_admin_logged_in() check as require_admin_login().
     Failures return 403 with an empty non-HTML body instead of a
     login/setup page, so clients never see a webpage for this URL.
   - The plaintext is cleared from the session after GET/POST, so it
     can be fetched exactly once. HEAD does not consume it (download
     managers often HEAD-probe first).
   - Response headers force a binary attachment so clients do not
     treat the body as a webpage.
   - The event is written to admin_security_log (codes never logged).
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/recovery-codes.php';

send_security_headers();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    header('Content-Type: application/octet-stream');
    header('Content-Length: 0');
    exit;
}

if (!is_admin_logged_in()) {
    http_response_code(403);
    header('Content-Type: application/octet-stream');
    header('Content-Length: 0');
    exit;
}

if ($method === 'POST') {
    $submitted = $_POST['csrf_token'] ?? '';

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        header('Content-Type: application/octet-stream');
        header('Content-Length: 0');
        exit;
    }
}

$once = $_SESSION['admin_recovery_codes_once'] ?? null;

if (empty($once['codes']) || !is_array($once['codes'])) {
    http_response_code(404);
    header('Content-Type: application/octet-stream');
    header('Content-Length: 0');
    exit;
}

$admin = current_admin();
$txt   = recovery_codes_txt($once['codes'], (string) ($admin['email'] ?? ''));
$filename = 'moonaura-admin-recovery-codes.txt';
$length = strlen($txt);

if (function_exists('ini_set')) {
    ini_set('zlib.output_compression', '0');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $length);
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($method === 'HEAD') {
    exit;
}

unset($_SESSION['admin_recovery_codes_once']);

log_admin_security_event((int) $admin['id'], 'recovery_codes_downloaded', 'batch ' . ($once['batch_id'] ?? '-'));

session_write_close();

echo $txt;
exit;
