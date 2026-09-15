<?php
/* ===================================================================
   ADMIN TWO-FACTOR AUTHENTICATION (Phase 5E)
   -------------------------------------------------------------------
   RFC 6238 TOTP implementation + the storage helpers that back the
   admin 2FA pages (admin/2fa-setup.php + admin/2fa-verify.php).

   SECURITY MODEL:
   - The secret is 160 random bits (RFC 6238 recommends >= 160) as a
     Base32 string - compatible with Google Authenticator, Microsoft
     Authenticator and Authy.
   - ONLY the ENCRYPTED secret is ever stored. AES-256-GCM with a
     96-bit random IV and 128-bit auth tag, keyed by
     ADMIN_2FA_ENCRYPTION_KEY (base64 32 bytes, from .env - never
     hardcoded). The stored blob is base64(iv || tag || ciphertext).
   - OTP values are never stored anywhere. Codes are computed on
     demand and discarded; there is no token to leak.
   - No recovery codes are stored (and none are ever written in plain
     text); disabling 2FA requires a valid OTP - there is deliberately
     no bypass route that an attacker holding the session could use to
     silently switch 2FA off.
   - Verification accepts the current 30-second window plus one step
     either side (standard practice) to absorb minor clock skew.
   - Every public entry point fails closed: missing encryption key,
     undecryptable secret or malformed input all return false/null
     rather than guessing.

   INTEROP: default parameters are SHA1 + 6 digits + 30s, which every
   TOTP app (including the three named above) implements by default.
================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';


/* ==========================================
   BASE32 ENCODING / DECODING (RFC 4648)
   Secret is stored/transmitted UPPERCASE,
   no padding.
========================================== */

const TWO_FACTOR_BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32_encode(string $data): string
{
    $alphabet = TWO_FACTOR_BASE32_ALPHABET;
    $bits     = '';
    $result   = '';

    foreach (str_split($data) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }

    foreach (str_split($bits, 5) as $chunk) {
        $result .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }

    return $result;
}

function base32_decode(string $base32): string
{
    $base32 = strtoupper(trim($base32));
    $base32 = str_replace('=', '', $base32);
    $bits   = '';

    foreach (str_split($base32) as $char) {
        $index = strpos(TWO_FACTOR_BASE32_ALPHABET, $char);
        if ($index === false) {
            return ''; // invalid character - fail closed
        }
        $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
    }

    $result = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $result .= chr(bindec($chunk));
        }
    }

    return $result;
}


/* ==========================================
   SECRET GENERATION
   20 random bytes (160 bits) -> Base32.
========================================== */

function generate_totp_secret(): string
{
    return base32_encode(random_bytes(20));
}


/* ==========================================
   TOTP CODE (RFC 6238 / RFC 4226 HOTP)
   Returns the 6-digit code for $secret at the
   given Unix time (default: now), zero-padded.
========================================== */

function totp_code(string $base32Secret, ?int $at = null): string
{
    $at     = $at ?? time();
    $binary = base32_decode($base32Secret);

    if ($binary === '') {
        return '';
    }

    $counter    = pack('N*', 0, intdiv($at, 30)); // 8-byte big-endian counter
    $hash       = hash_hmac('sha1', $counter, $binary, true);

    // Dynamic truncation (RFC 4226 §5.3): last nibble picks the offset.
    $offset     = ord($hash[strlen($hash) - 1]) & 0x0F;
    $binCode    = ((ord($hash[$offset]) & 0x7F) << 24)
                | ((ord($hash[$offset + 1]) & 0xFF) << 16)
                | ((ord($hash[$offset + 2]) & 0xFF) << 8)
                |  (ord($hash[$offset + 3]) & 0xFF);

    return str_pad((string) ($binCode % 1000000), 6, '0', STR_PAD_LEFT);
}


/* ==========================================
   VERIFY A SUBMITTED CODE
   Accepts the current 30s window plus $window
   steps either side (default 1) to tolerate
   clock drift. Fails closed on empty input.
========================================== */

function verify_totp(string $base32Secret, string $code, int $window = 1): bool
{
    $code = trim($code);

    if ($code === '' || !preg_match('/^[0-9]{6}$/', $code)) {
        return false;
    }

    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($base32Secret, time() + ($i * 30)), $code)) {
            return true;
        }
    }

    return false;
}


/* ==========================================
   SECRET ENCRYPTION (AES-256-GCM)
   -------------------------------------------------
   two_fa_secret_key()          -> raw 32-byte key or null if unset.
   two_fa_encrypt_secret()      -> base64(iv||tag||ciphertext) or null.
   two_fa_decrypt_secret()      -> plaintext Base32 secret or null on
                                   any failure (wrong key, tampered
                                   blob, missing key) - never throws,
                                   never reveals a partial secret.
========================================== */

function two_fa_secret_key(): ?string
{
    $key = base64_decode((string) ADMIN_2FA_ENCRYPTION_KEY, true);

    if ($key === false || strlen($key) !== 32) {
        return null; // not set, or not a 32-byte base64 key
    }

    return $key;
}

function two_fa_encrypt_secret(string $plainSecret): ?string
{
    $key = two_fa_secret_key();

    if ($key === null || $plainSecret === '') {
        return null;
    }

    $iv       = random_bytes(12);
    $tag      = '';
    $cipher   = openssl_encrypt($plainSecret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($cipher === false) {
        return null;
    }

    return base64_encode($iv . $tag . $cipher); // 12 + 16 + 40 bytes
}

function two_fa_decrypt_secret(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }

    $key = two_fa_secret_key();

    if ($key === null) {
        return null;
    }

    $blob = base64_decode($stored, true);

    if ($blob === false || strlen($blob) < 29) { // 12 IV + 16 tag + >=1 byte
        return null;
    }

    $iv       = substr($blob, 0, 12);
    $tag      = substr($blob, 12, 16);
    $cipher   = substr($blob, 28);

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    return $plain === false ? null : $plain;
}


/* ==========================================
   OTPAUTH URI (for QR codes / manual entry)
   Standard otpauth:// URI all authenticator
   apps understand. Period 30, digits 6, SHA1.
========================================== */

function two_factor_otpauth_uri(string $issuer, string $account, string $base32Secret): string
{
    $issuer  = rawurlencode($issuer);
    $account = rawurlencode($account);

    return 'otpauth://totp/' . $issuer . ':' . $account
        . '?secret=' . $base32Secret
        . '&issuer=' . $issuer
        . '&algorithm=SHA1&digits=6&period=30';
}


/* ==========================================
   ADMIN 2FA ROW HELPERS
   -------------------------------------------------
   get_admin_2fa(int $adminId) -> the admin_users row (incl. the 2FA
   columns) or null. Used by the setup + verify pages so the login
   flow and the security UI never duplicate SQL.
========================================== */

function get_admin_2fa(int $adminId): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, email, role, status,
                two_factor_enabled, two_factor_secret, two_factor_enabled_at
         FROM admin_users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$adminId]);

    $row = $stmt->fetch();

    return $row ?: null;
}
