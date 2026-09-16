<?php
/* ===================================================================
   LOGIN SECURITY - BRUTE-FORCE LOCKOUT
   -------------------------------------------------------------------
   Phase 6: rate-limits BOTH the admin login (dashboard/login.php via
   includes/auth.php) and the customer login (account/login.php via
   includes/customer-auth.php) with a simple per-email lockout:

     - 5 consecutive failed attempts (default) lock the email address
       for 15 minutes (default).
     - Failed attempts are counted in the login_attempts table, keyed
       by the NORMALIZED email address. The row exists even when the
       email belongs to no account, so the lockout never reveals which
       emails have accounts (no information leakage - the same vague
       "Incorrect email or password." message is shown either way).
     - A successful login clears the record (minimal data kept).
     - Locks expire automatically; the first attempt after expiry
       starts a fresh count.

   Design constraints this satisfies:
     - Works on shared hosting: only a database table + the existing
       session architecture, no files, no daemons, no third-party
       packages, no CAPTCHA, no external services.
     - Idempotent and cheap: one indexed lookup per login attempt.
     - User-friendly: the login page shows how long the lock lasts and
       lets the customer/admin try again after it expires.
=================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!defined('LOGIN_MAX_FAILED_ATTEMPTS')) {
    define('LOGIN_MAX_FAILED_ATTEMPTS', 5);
}
if (!defined('LOGIN_LOCK_MINUTES')) {
    define('LOGIN_LOCK_MINUTES', 15);
}


/* ==========================================
   CURRENT LOCKOUT STATUS FOR AN EMAIL
   ------------------------------------------
   Never throws. Returns:
     locked              - whether the email is locked right now
     locked_until        - the stored lock deadline (DB format) or null
     retry_after_minutes - whole minutes left in the lock (0 if not locked)
     remaining_attempts  - attempts left before locking (0 while locked)
   As a side effect, an EXPIRED lock is cleaned up here (deadline
   cleared, counter reset) so the next attempt starts fresh - that is
   what makes "lock expiration" work with no scheduled jobs.
========================================== */

function login_attempt_status(string $email): array
{
    $email = normalize_email($email);

    $stmt = db()->prepare('SELECT failed_attempts, locked_until FROM login_attempts WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    $now = new DateTimeImmutable();

    if ($row && !empty($row['locked_until'])) {
        $lockedUntil = new DateTimeImmutable($row['locked_until']);

        if ($lockedUntil > $now) {
            // Actively locked.
            return [
                'locked'              => true,
                'locked_until'        => $row['locked_until'],
                'retry_after_minutes' => max(1, (int) ceil(($lockedUntil->getTimestamp() - $now->getTimestamp()) / 60)),
                'remaining_attempts'  => 0,
            ];
        }

        // Lock expired - clear it and reset the counter (lazy cleanup).
        $stmt = db()->prepare('UPDATE login_attempts SET failed_attempts = 0, locked_until = NULL, last_failed_at = NULL WHERE email = ?');
        $stmt->execute([$email]);
    }

    $failed = $row ? (int) $row['failed_attempts'] : 0;

    return [
        'locked'              => false,
        'locked_until'        => null,
        'retry_after_minutes' => 0,
        'remaining_attempts'  => max(0, LOGIN_MAX_FAILED_ATTEMPTS - $failed),
    ];
}


/* ==========================================
   RECORD A FAILED ATTEMPT
   ------------------------------------------
   Increments the counter for this email and locks it when the counter
   reaches the threshold. Defense-in-depth: if the email is ALREADY
   locked, nothing is incremented (the login page checks first, but a
   caller that forgot to won't keep lengthening an attacker's own
   lockout window). Returns the fresh status via login_attempt_status().
========================================== */

function login_attempt_failed(string $email): array
{
    $email = normalize_email($email);

    $existing = login_attempt_status($email);

    if ($existing['locked']) {
        return $existing; // already locked - don't keep growing the counter
    }

    $stmt = db()->prepare(
        'INSERT INTO login_attempts (email, failed_attempts, last_failed_at, locked_until)
         VALUES (?, 1, NOW(), NULL)
         ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1, last_failed_at = NOW()'
    );
    $stmt->execute([$email]);

    $stmt = db()->prepare('SELECT failed_attempts FROM login_attempts WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $failed = (int) $stmt->fetchColumn();

    if ($failed >= LOGIN_MAX_FAILED_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + (LOGIN_LOCK_MINUTES * 60));
        $stmt = db()->prepare('UPDATE login_attempts SET locked_until = ? WHERE email = ?');
        $stmt->execute([$lockUntil, $email]);
    }

    return login_attempt_status($email);
}


/* ==========================================
   CLEAR THE RECORD ON A SUCCESSFUL LOGIN
   ------------------------------------------
   Removes the whole row - a successful login proves the email is
   legitimate, and "store minimal data" means not keeping stale
   failure counts around forever.
========================================== */

function login_attempt_succeeded(string $email): void
{
    $email = normalize_email($email);

    $stmt = db()->prepare('DELETE FROM login_attempts WHERE email = ?');
    $stmt->execute([$email]);
}


/* ==========================================
   LOCKED-OUT MESSAGE
   ------------------------------------------
   One generic, user-friendly message for both login pages. Mentions
   the lock length but never whether the email exists or anything else
   that could leak account information.
========================================== */

function login_lock_message(array $status): string
{
    $minutes = max(1, (int) ($status['retry_after_minutes'] ?? LOGIN_LOCK_MINUTES));

    return 'Too many failed login attempts. Please wait ' . $minutes
        . ' minute' . ($minutes === 1 ? '' : 's')
        . ' before trying again.';
}
