<?php
/* ===================================================================
   ADMIN AUTHENTICATION HELPERS (Phase 5E hardened)
   -------------------------------------------------------------------
   Session-based authentication for the Admin Panel, hardened as part
   of the Phase 5E security pass:

   - require_admin_login() is called at the TOP of every protected
     admin page. If nobody is fully logged in, it redirects to
     login.php (2FA-pending sessions are NOT logged in - see below).
   - Session fixation: the session ID is regenerated on EVERY
     successful login step (password, then again after 2FA) via
     complete_admin_login().
   - Session hijacking: each session is bound to the browser's
     User-Agent hash; a mismatch destroys the session. An idle
     timeout (ADMIN_SESSION_IDLE_TIMEOUT, default 30 min) also
     expires unused sessions.
   - Two-step login: attempt_admin_login() ONLY validates
     credentials and returns the admin row. If that admin has 2FA
     enabled, login.php parks their id in $_SESSION['admin_2fa_pending']
     and sends them to 2fa-verify.php; $_SESSION['admin_id'] (which is
     what every protected route checks) is only set by
     complete_admin_login() AFTER a valid TOTP code. There is
     therefore no route into the panel without passing 2FA.
   - Logout fully destroys the session (destroy_session()) rather
     than unsetting a key.
=================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


/* ==========================================
   SECURITY HEADERS
   Sent once per response (config.php's global
   ones are already out by the time a page runs,
   but these are a second layer for auth pages).
========================================== */

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}


/* ==========================================
   IS AN ADMIN CURRENTLY LOGGED IN?
   A session is only "logged in" once $_SESSION['admin_id'] is set
   (i.e. after 2FA, when applicable). This also enforces:
     - idle timeout  -> session destroyed if inactive too long
     - UA binding    -> session destroyed if a different browser
                        presents the same cookie (session hijacking)
========================================== */

function is_admin_logged_in(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }

    // Idle timeout.
    $idleSeconds = ADMIN_SESSION_IDLE_TIMEOUT * 60;
    if (!empty($_SESSION['admin_last_activity'])
        && (time() - (int) $_SESSION['admin_last_activity']) > $idleSeconds) {
        destroy_session();
        return false;
    }

    // User-Agent binding (defense in depth against cookie theft).
    $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!empty($_SESSION['admin_ua_hash'])
        && !hash_equals($_SESSION['admin_ua_hash'], $uaHash)) {
        destroy_session();
        return false;
    }

    // Refresh the idle marker.
    $_SESSION['admin_last_activity'] = time();

    return true;
}


/* ==========================================
   PROTECT A PAGE
   Call this at the very top of any admin page
   that should only be visible when logged in.
========================================== */

function require_admin_login(): void
{
    send_security_headers();

    if (!is_admin_logged_in()) {
        // A half-finished 2FA login (password OK, OTP still pending)
        // has NO admin_id yet, so it holds no privileges - send it to
        // the OTP step rather than making it re-enter the password.
        if (!empty($_SESSION['admin_2fa_pending'])) {
            redirect('2fa-verify.php');
        }

        redirect('login.php');
    }
}


/* ==========================================
   GET THE CURRENTLY LOGGED-IN ADMIN
   Returns the admin_users row, or null.
========================================== */

function current_admin(): ?array
{
    if (!is_admin_logged_in()) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, role FROM admin_users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['admin_id']]);

    $admin = $stmt->fetch();

    return $admin ?: null;
}


/* ==========================================
   VALIDATE CREDENTIALS (STEP 1 OF LOGIN)
   -------------------------------------------------
   Checks email + password against the database. Returns the admin
   row (id, name, email, two_factor_enabled) on success, or null on
   ANY failure (no account / disabled / wrong password) - the caller
   shows one generic message. Does NOT create a session here: the
   admin either goes straight to complete_admin_login() (no 2FA) or
   is parked as 2FA-pending and must pass step 2 first.
========================================== */

function attempt_admin_login(string $email, string $password): ?array
{
    $email = normalize_email($email);

    $stmt = db()->prepare(
        'SELECT id, name, email, status, two_factor_enabled FROM admin_users
         WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);

    $admin = $stmt->fetch();

    if (!$admin) {
        return null; // no account with that email
    }

    if ($admin['status'] !== 'active') {
        return null; // account has been disabled
    }

    $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$admin['id']]);
    $hashRow = $stmt->fetch();

    if (!$hashRow || !password_verify($password, $hashRow['password_hash'])) {
        return null; // wrong password
    }

    unset($admin['status']);

    return $admin;
}


/* ==========================================
   COMPLETE A SUCCESSFUL LOGIN (STEP 2)
   -------------------------------------------------
   Called after step 1 (password) for admins without 2FA, and after
   step 2 (valid TOTP) for everyone else. This is the ONLY place that
   grants $_SESSION['admin_id'] and it always:
     - regenerates the session ID (anti-fixation),
     - clears any leftover 2FA-pending state,
     - stamps auth/last-activity time + User-Agent fingerprint,
     - records last_login_at.
========================================== */

function complete_admin_login(int $adminId): void
{
    session_regenerate_id(true);

    unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_2fa_email'], $_SESSION['admin_2fa_attempts']);

    $_SESSION['admin_id']          = $adminId;
    $_SESSION['admin_auth_at']     = time();
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_ua_hash']     = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

    $update = db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?');
    $update->execute([$adminId]);
}


/* ==========================================
   LOGOUT
   Fully destroys the session (see destroy_session()
   in functions.php) rather than just unsetting the
   admin_id key - Phase 3 hardening.
========================================== */

function admin_logout(): void
{
    destroy_session();
}
