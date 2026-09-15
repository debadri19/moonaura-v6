<?php
/* ===================================================================
   ADMIN 2FA RECOVERY CODES (Phase 5F.1)
   -------------------------------------------------------------------
   Additive enhancement to the Phase 5E TOTP 2FA system. This file is
   deliberately separate from includes/two-factor.php - the TOTP
   architecture (secret generation, encryption, verification) is NOT
   touched. This only adds one-time recovery codes for when an admin
   cannot use their authenticator app.

   MODEL:
   - 10 codes per batch. Each code is 12 chars from a 32-char alphabet
     (no 0/1/O/I), i.e. ~60 bits of randomness from random_bytes().
     Displayed as XXXX-XXXX-XXXX.
   - ONLY the SHA-256 hash of each code is stored
     (admin_recovery_codes.code_hash). The plaintext exists ONLY in the
     admin's session for the one-time post-generation panel and is
     NEVER emailed and NEVER shown again afterwards.
   - Codes belong to batches (admin_recovery_codes.batch_id). The
     active batch is always the most recently generated one, so
     regenerating automatically invalidates every earlier batch without
     deleting any rows.
   - Consumption is atomic: UPDATE ... WHERE consumed_at IS NULL with
     an affected-rows check, so a code can never be used twice even
     under concurrent requests.
   - Every interesting event (generation, regeneration, download, use,
     reuse attempt, invalid attempt) is written to admin_security_log.
   =================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!defined('RECOVERY_CODE_COUNT')) {
    define('RECOVERY_CODE_COUNT', 10);
}

if (!defined('RECOVERY_CODE_LENGTH')) {
    define('RECOVERY_CODE_LENGTH', 12);
}

// 32 chars (5 bits each). 0/1/O/I removed so codes are easy to read
// out loud and never ambiguous.
if (!defined('RECOVERY_CODE_ALPHABET')) {
    define('RECOVERY_CODE_ALPHABET', 'ABCDEFGHJKMNPQRSTUVWXYZ23456789');
}


/* ==========================================
   HASH A RECOVERY CODE
   ------------------------------------------
   SHA-256. The codes carry ~60 bits of randomness, so an offline
   brute-force of the stored hashes is infeasible. Only the hash is
   ever persisted; the plaintext is never stored or emailed.
========================================== */

function recovery_code_hash(string $code): string
{
    return hash('sha256', $code);
}


/* ==========================================
   GENERATE A NEW SET OF RECOVERY CODES
   ------------------------------------------
   Returns $count canonical codes (no grouping; display adds the
   dashes). Each code is RECOVERY_CODE_LENGTH random chars drawn from
   RECOVERY_CODE_ALPHABET via random_bytes() - cryptographically
   secure, no modulo bias concerns for this threat model (32 evenly
   divides 256).
========================================== */

function generate_recovery_codes(int $count = RECOVERY_CODE_COUNT): array
{
    $alphabetLength = strlen(RECOVERY_CODE_ALPHABET);
    $codes          = [];

    for ($i = 0; $i < $count; $i++) {
        $bytes = random_bytes(RECOVERY_CODE_LENGTH);
        $code  = '';

        for ($j = 0; $j < RECOVERY_CODE_LENGTH; $j++) {
            $code .= RECOVERY_CODE_ALPHABET[ord($bytes[$j]) % $alphabetLength];
        }

        $codes[] = $code;
    }

    return $codes;
}


/* ==========================================
   PRESENTATION + NORMALISATION
========================================== */

function recovery_code_display(string $canonical): string
{
    return trim(chunk_split($canonical, 4, '-'), '-');
}

// Uppercases and strips spaces/dashes so pasted codes always match.
function normalize_recovery_code(string $input): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
}


/* ==========================================
   STORE A NEW BATCH
   ------------------------------------------
   Inserts the hashes under a fresh random batch_id and returns it.
   The new batch is automatically the "active" one (latest created_at),
   so this alone invalidates every previous batch.
========================================== */

function store_recovery_codes(int $adminId, array $codes): string
{
    $batchId = bin2hex(random_bytes(16));
    $stmt    = db()->prepare(
        'INSERT INTO admin_recovery_codes (admin_id, batch_id, code_hash)
         VALUES (?, ?, ?)'
    );

    foreach ($codes as $code) {
        $stmt->execute([$adminId, $batchId, recovery_code_hash($code)]);
    }

    return $batchId;
}


/* ==========================================
   ACTIVE BATCH INFO
   ------------------------------------------
   Returns the latest batch (id + total + used counts) or null when the
   admin has no recovery codes at all.
========================================== */

function get_active_recovery_batch(int $adminId): ?array
{
    $stmt = db()->prepare(
        'SELECT batch_id, COUNT(*) AS total, SUM(consumed_at IS NOT NULL) AS used
         FROM admin_recovery_codes
         WHERE admin_id = ?
         GROUP BY batch_id
         ORDER BY MAX(created_at) DESC
         LIMIT 1'
    );
    $stmt->execute([$adminId]);

    $row = $stmt->fetch();

    return $row ? [
        'batch_id' => $row['batch_id'],
        'total'    => (int) $row['total'],
        'used'     => (int) $row['used'],
    ] : null;
}

function active_recovery_codes_remaining(int $adminId): int
{
    $batch = get_active_recovery_batch($adminId);

    return $batch ? max(0, $batch['total'] - $batch['used']) : 0;
}


/* ==========================================
   CONSUME A RECOVERY CODE (ATOMIC)
   ------------------------------------------
   Validates a submitted code against the ADMIN'S ACTIVE batch only
   (old batches from a regeneration are automatically ignored). Returns:

     ['status' => 'ok',     'code_id' => int, 'batch_id' => string]
     ['status' => 'reused', 'code_id' => int]
     ['status' => 'invalid']

   Only codes that match the active batch and are not yet consumed are
   granted - the UPDATE ... WHERE consumed_at IS NULL affected-rows
   check makes reuse impossible, even for two simultaneous requests.
   No OTP / no recovery code is ever stored or returned.
========================================== */

function consume_recovery_code(int $adminId, string $code): array
{
    $code = normalize_recovery_code($code);

    // Reject impossible codes up front (cheap, avoids a pointless hash
    // + DB lookup and keeps the invalid path constant-time-ish).
    if ($code === '' || !preg_match('/^[' . RECOVERY_CODE_ALPHABET . ']{' . RECOVERY_CODE_LENGTH . '}$/', $code)) {
        return ['status' => 'invalid'];
    }

    $codeHash = recovery_code_hash($code);

    $stmt = db()->prepare(
        'SELECT rc.id, rc.batch_id, rc.consumed_at
         FROM admin_recovery_codes rc
         INNER JOIN (
             SELECT batch_id
             FROM admin_recovery_codes
             WHERE admin_id = ?
             GROUP BY batch_id
             ORDER BY MAX(created_at) DESC
             LIMIT 1
         ) active ON active.batch_id = rc.batch_id
         WHERE rc.admin_id = ? AND rc.code_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$adminId, $adminId, $codeHash]);

    $row = $stmt->fetch();

    if (!$row) {
        return ['status' => 'invalid'];
    }

    if ($row['consumed_at'] !== null) {
        return ['status' => 'reused', 'code_id' => (int) $row['id']];
    }

    $ip   = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $stmt = db()->prepare(
        'UPDATE admin_recovery_codes
         SET consumed_at = NOW(), consumed_by_ip = ?
         WHERE id = ? AND consumed_at IS NULL'
    );
    $stmt->execute([$ip, $row['id']]);

    if ($stmt->rowCount() === 1) {
        return ['status' => 'ok', 'code_id' => (int) $row['id'], 'batch_id' => $row['batch_id']];
    }

    return ['status' => 'reused', 'code_id' => (int) $row['id']];
}


/* ==========================================
   SECURITY LOGGING
   ------------------------------------------
   Append-only admin_security_log. Used to record recovery-code
   generation, regeneration, download, use, reuse attempts and invalid
   attempts. Never stores code values - only ids/batches.
========================================== */

function log_admin_security_event(?int $adminId, string $eventType, string $detail = ''): void
{
    $ip   = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $stmt = db()->prepare(
        'INSERT INTO admin_security_log (admin_id, event_type, detail, ip_address)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$adminId, $eventType, substr($detail, 0, 255), $ip]);
}


/* ==========================================
   TXT DOWNLOAD CONTENT
   ------------------------------------------
   Built from the session-held plaintext codes at download time (the
   DB only has hashes). Never stored, never emailed.
========================================== */

function recovery_codes_txt(array $codes, string $adminEmail, string $siteName = 'MoonAura Crystals'): string
{
    $lines   = [];
    $lines[] = $siteName . ' - Admin Recovery Codes';
    $lines[] = 'Account: ' . $adminEmail;
    $lines[] = 'Generated: ' . date('d M Y, H:i');
    $lines[] = '';
    $lines[] = 'Each code can be used exactly ONCE to sign in when you do not';
    $lines[] = 'have your authenticator app. Store this file somewhere safe.';
    $lines[] = '';
    $lines[] = 'Generating a new set invalidates all of these codes.';
    $lines[] = '';

    foreach ($codes as $code) {
        $lines[] = recovery_code_display($code);
    }

    return implode("\r\n", $lines) . "\r\n";
}
