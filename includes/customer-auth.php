<?php
/* ===================================================================
   CUSTOMER AUTHENTICATION HELPERS
   -------------------------------------------------------------------
   Mirrors includes/auth.php (the admin version) closely on purpose -
   same shape, same session-based approach - but uses its own session
   key ($_SESSION['customer_id']) so a customer being logged in on
   the storefront and an admin being logged into /admin/ at the same
   time (e.g. testing in two tabs) never interfere with each other.
=================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/customer-cart-functions.php';
require_once __DIR__ . '/order-functions.php';


/* ==========================================
   IS A CUSTOMER CURRENTLY LOGGED IN?
========================================== */

function is_customer_logged_in(): bool
{
    return !empty($_SESSION['customer_id']);
}


/* ==========================================
   PROTECT A PAGE
   Call this at the very top of any account
   page that should only be visible when
   logged in.
========================================== */

function require_customer_login(): void
{
    if (!is_customer_logged_in()) {
        redirect('login.php');
    }
}


/* ==========================================
   GET THE CURRENTLY LOGGED-IN CUSTOMER
   Returns the customers row, or null.
========================================== */

function current_customer(): ?array
{
    if (!is_customer_logged_in()) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, phone FROM customers WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['customer_id']]);

    $customer = $stmt->fetch();

    return $customer ?: null;
}


/* ==========================================
   LOGIN IDENTIFIER (email or mobile)
   Existing customers may log in with the
   registered email OR the stored 10-digit
   mobile. Format checks are application-level
   so an invalid identifier never implies
   whether an account exists.
========================================== */

function is_customer_login_identifier(string $identifier): bool
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return false;
    }

    if (filter_var(normalize_email($identifier), FILTER_VALIDATE_EMAIL)) {
        return true;
    }

    return is_valid_mobile_number($identifier);
}

function customer_login_lock_key(string $identifier): string
{
    $identifier = trim($identifier);
    $email = normalize_email($identifier);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }

    if (is_valid_mobile_number($identifier)) {
        return 'm:' . normalize_mobile_number($identifier);
    }

    return $email;
}

function find_customer_for_login(string $identifier): ?array
{
    $identifier = trim($identifier);
    $email = normalize_email($identifier);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare(
            'SELECT id, password_hash, status FROM customers WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    if (is_valid_mobile_number($identifier)) {
        $phone = normalize_mobile_number($identifier);
        $stmt = db()->prepare(
            'SELECT id, password_hash, status FROM customers WHERE phone = ? LIMIT 1'
        );
        $stmt->execute([$phone]);
        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    return null;
}


/* ==========================================
   ATTEMPT LOGIN
   Checks the email or mobile + password
   against the database. Returns true on success.
========================================== */

function attempt_customer_login(string $identifier, string $password): bool
{
    $customer = find_customer_for_login($identifier);

    if (!$customer) {
        return false; // no account with that email or mobile
    }

    if ($customer['status'] !== 'active') {
        return false; // account has been disabled
    }

    if (!password_verify($password, $customer['password_hash'])) {
        return false; // wrong password
    }

    // Snapshot the guest session cart before regenerate. PHP
    // copies session data onto the new id, but we pass the
    // snapshot explicitly so merge never depends on that.
    $sessionCart = (isset($_SESSION['cart']) && is_array($_SESSION['cart']))
        ? $_SESSION['cart']
        : [];

    // Success - regenerate the session ID to prevent session
    // fixation attacks, then store the customer's id.
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $customer['id'];

    // #25B: merge session + persisted carts only after auth.
    // Persistence must not turn a successful login into HTTP 500.
    try {
        customer_cart_apply_login_merge((int) $customer['id'], $sessionCart);
    } catch (Throwable $e) {
        error_log('customer_cart login merge failed: ' . $e->getMessage());
    }

    return true;
}


/* ==========================================
   LOGOUT
   Fully destroys the session (see destroy_session()
   in functions.php) rather than just unsetting the
   customer_id key - Phase 3 hardening. The session
   cart is cleared with the session; customer_carts
   rows are NOT deleted (Phase #25B).
========================================== */

function customer_logout(): void
{
    destroy_session();
}
