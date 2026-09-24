<?php
/* ===================================================================
   CUSTOMER FUNCTIONS
   -------------------------------------------------------------------
   Everything specific to customer accounts that isn't pure auth
   (that's customer-auth.php) - guest order linking, saved
   address management, password-reset token helpers, and the
   authenticated Dark Mode theme preference.
=================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';       // normalize_email()
require_once __DIR__ . '/order-functions.php'; // normalize_mobile_number()


/* ==========================================
   LINK GUEST ORDERS TO A CUSTOMER ACCOUNT
   -------------------------------------------------
   Finds any past orders placed as a guest (user_id IS NULL) using
   the SAME email or mobile number as this customer account, and
   attaches them by setting user_id. Nothing else about those orders
   changes - no historical data is altered, only the link is added.

   Called after both registration and login, so orders placed under
   the same email/phone at any point get connected automatically.
========================================== */

function link_guest_orders_to_customer(int $customerId, string $email, string $phone): void
{
    // Defensive normalization - orders.customer_email/customer_phone
    // are stored normalized (see create_order()), but this makes the
    // match reliable even if a caller passes an un-normalized value.
    $normalizedEmail = normalize_email($email);
    $normalizedPhone = normalize_mobile_number($phone);

    $stmt = db()->prepare(
        'UPDATE orders
         SET user_id = ?
         WHERE user_id IS NULL
           AND (customer_email = ? OR customer_phone = ?)'
    );
    $stmt->execute([$customerId, $normalizedEmail, $normalizedPhone]);
}


/* ==========================================
   SAVED ADDRESSES
========================================== */

function get_customer_addresses(int $customerId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM customer_addresses
         WHERE customer_id = ?
         ORDER BY is_default DESC, created_at DESC'
    );
    $stmt->execute([$customerId]);

    return $stmt->fetchAll();
}

function get_customer_address(int $customerId, int $addressId): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ? LIMIT 1'
    );
    $stmt->execute([$addressId, $customerId]);

    $address = $stmt->fetch();

    return $address ?: null;
}

// If this address is being set as default, clear the flag on any
// other address this customer has first - only one default at a time.
function clear_other_default_addresses(int $customerId, ?int $exceptAddressId = null): void
{
    $sql    = 'UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?';
    $params = [$customerId];

    if ($exceptAddressId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $exceptAddressId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}


/* ==========================================
   AUTO-SAVE A CHECKOUT ADDRESS (Part 2 -
   "Save this address for future orders")
   -------------------------------------------------------------------
   Called only after a checkout order is successfully placed/paid, for
   logged-in customers only (guest checkout has no address book to
   save into) and only when the "Save this address" checkbox was
   checked. Reuses the exact same INSERT shape
   account/address-form.php's "add new address" path already uses -
   this isn't a new address architecture, just a second place that
   calls it.

   Dedup: if every core field (name, phone, address line 1/2, city,
   state, postal code) already matches one of this customer's saved
   addresses case-insensitively, nothing is inserted - re-ordering to
   an address already on file never creates a duplicate.

   The new address becomes the customer's default only if it's their
   first saved address ever; otherwise it's added alongside their
   existing ones without touching whichever they already chose as
   default (clear_other_default_addresses() is intentionally not
   called here).
========================================== */

function save_customer_address_if_new(int $customerId, array $address): void
{
    $fullName = trim((string) ($address['full_name'] ?? ''));
    $phone    = trim((string) ($address['phone'] ?? ''));
    $line1    = trim((string) ($address['address_line1'] ?? ''));
    $line2    = trim((string) ($address['address_line2'] ?? ''));
    $landmark = trim((string) ($address['landmark'] ?? ''));
    $city     = trim((string) ($address['city'] ?? ''));
    $state    = trim((string) ($address['state'] ?? ''));
    $postal   = trim((string) ($address['postal_code'] ?? ''));

    // Nothing safe to save if the core fields aren't all present -
    // mirrors the same required fields account/address-form.php
    // itself requires.
    if ($fullName === '' || $phone === '' || $line1 === '' || $city === '' || $state === '' || $postal === '') {
        return;
    }

    $existingAddresses = get_customer_addresses($customerId);

    foreach ($existingAddresses as $existing) {
        $sameCore =
            mb_strtolower(trim((string) $existing['full_name'])) === mb_strtolower($fullName)
            && trim((string) $existing['phone']) === $phone
            && mb_strtolower(trim((string) $existing['address_line1'])) === mb_strtolower($line1)
            && mb_strtolower(trim((string) ($existing['address_line2'] ?? ''))) === mb_strtolower($line2)
            && mb_strtolower(trim((string) $existing['city'])) === mb_strtolower($city)
            && mb_strtolower(trim((string) $existing['state'])) === mb_strtolower($state)
            && trim((string) $existing['postal_code']) === $postal;

        if ($sameCore) {
            return;
        }
    }

    $isFirstAddress = empty($existingAddresses);

    $stmt = db()->prepare(
        'INSERT INTO customer_addresses (
            customer_id, full_name, phone, address_line1, address_line2, landmark,
            city, state, postal_code, is_default
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $customerId, $fullName, $phone, $line1, $line2, $landmark,
        $city, $state, $postal, $isFirstAddress ? 1 : 0,
    ]);
}


/* ==========================================
   CUSTOMER PASSWORD RESETS (Phase 5B)
   ------------------------------------------
   Forgot/Reset Password flow, mirroring the admin implementation
   (dashboard/forgot-password.php + dashboard/reset-password.php) one-for-one:
     - 256-bit token from random_bytes(); only its SHA-256 hash is
       ever stored - the raw token exists only inside the reset link.
     - 60-minute expiry, single-use via used_at.
   These three helpers are the only DB touch-points, so a future
   Phase 5E (real email delivery) can send the token without touching
   any of this logic.
========================================== */

function create_customer_reset_token(int $customerId): string
{
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

    $stmt = db()->prepare(
        'INSERT INTO customer_password_resets (customer_id, token_hash, expires_at)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$customerId, $tokenHash, $expiresAt]);

    return $rawToken;
}

// Returns the reset row (id, customer_id) ONLY if the raw token hashes
// to a stored row that is unused AND unexpired - otherwise null. Never
// reveals which check failed; the caller shows one generic message for
// all cases (invalid / expired / already used / unknown token).
function get_valid_customer_reset_token(string $rawToken): ?array
{
    if ($rawToken === '') {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, customer_id, expires_at, used_at
         FROM customer_password_resets
         WHERE token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $rawToken)]);
    $row = $stmt->fetch();

    if (!$row
        || $row['used_at'] !== null
        || strtotime($row['expires_at']) <= time()
    ) {
        return null;
    }

    return $row;
}

// Marks a token as consumed so it can never be used again, even if it
// hasn't expired yet.
function consume_customer_reset_token(int $resetId): void
{
    $stmt = db()->prepare('UPDATE customer_password_resets SET used_at = NOW() WHERE id = ?');
    $stmt->execute([$resetId]);
}


/* ==========================================
   AUTHENTICATED THEME PREFERENCE (PHASE 5)
   -------------------------------------------------
   Account-level Light / Dark / System. Guests never
   write this column. Missing/NULL/invalid values are
   treated as "no account preference" so localStorage
   and the existing Light fallback keep working.
========================================== */

function customer_theme_normalize(?string $value): ?string
{
    if ($value === 'light' || $value === 'dark' || $value === 'system') {
        return $value;
    }

    return null;
}

function customer_theme_cached_mode(): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if (empty($_SESSION['customer_id']) || !array_key_exists('customer_theme_preference', $_SESSION)) {
        return null;
    }

    $cached = $_SESSION['customer_theme_preference'];

    return customer_theme_normalize(is_string($cached) ? $cached : null);
}

function customer_theme_load(int $customerId): ?string
{
    if ($customerId <= 0) {
        return null;
    }

    try {
        $stmt = db()->prepare(
            'SELECT theme_preference FROM customers WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $value = $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('customer theme load failed: ' . $e->getMessage());
        return null;
    }

    if ($value === false || $value === null) {
        return null;
    }

    return customer_theme_normalize(is_string($value) ? $value : null);
}

function customer_theme_save(int $customerId, string $mode): bool
{
    $mode = customer_theme_normalize($mode);

    if ($mode === null || $customerId <= 0) {
        return false;
    }

    try {
        $stmt = db()->prepare(
            'UPDATE customers SET theme_preference = ? WHERE id = ?'
        );
        $stmt->execute([$mode, $customerId]);
    } catch (Throwable $e) {
        error_log('customer theme save failed: ' . $e->getMessage());
        return false;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['customer_theme_preference'] = $mode;
    }

    return true;
}

function customer_theme_apply_login(int $customerId): void
{
    $mode = customer_theme_load($customerId);

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['customer_theme_preference'] = $mode;
    }
}

function customer_theme_hydrate(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['customer_id'])) {
        return;
    }

    if (array_key_exists('customer_theme_preference', $_SESSION)) {
        return;
    }

    try {
        $_SESSION['customer_theme_preference'] = customer_theme_load((int) $_SESSION['customer_id']);
    } catch (Throwable $e) {
        error_log('customer theme hydrate failed: ' . $e->getMessage());
    }
}
