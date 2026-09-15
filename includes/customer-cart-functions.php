<?php
/* ===================================================================
   CUSTOMER CART PERSISTENCE HELPERS  (Phase #25A)
   -------------------------------------------------------------------
   DB-backed cart rows for a future logged-in customer cart.

   #25B: customer_cart_apply_login_merge() runs only after a
   successful login (or register-as-login). Guest carts stay
   session-only until then. Logout does not delete DB rows.

   #25C: after session cart mutation, logged-in customers also
   update customer_carts. Guests stay session-only. DB write
   failures must not undo the session cart or break AJAX.

   #25C (read path): for a logged-in customer the persisted
   customer_carts state is authoritative on a normal page refresh -
   cart_get() calls customer_cart_refresh_session_from_db() to
   re-load it into the session cart, so a change made on another
   device shows up without logout/login. A DB read failure keeps
   the existing session cart untouched.

   Shape matches the session cart: [ product_id => quantity, ... ]
=================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cart-functions.php';


/* ==========================================
   CUSTOMER EXISTS AND IS A POSITIVE ID
========================================== */

function customer_cart_customer_exists(int $customerId): bool
{
    if ($customerId <= 0) {
        return false;
    }

    $stmt = db()->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([$customerId]);

    return (bool) $stmt->fetch();
}


/* ==========================================
   PRODUCT EXISTS (FK-SAFE, ANY STATUS)
   Distinct from product_is_available_for_cart()
   which also requires status = active.
========================================== */

function customer_cart_product_exists(int $productId): bool
{
    if ($productId <= 0) {
        return false;
    }

    $stmt = db()->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);

    return (bool) $stmt->fetch();
}


/* ==========================================
   LOAD A CUSTOMER'S PERSISTED CART
   Returns [product_id => quantity]. Empty
   array if the customer id is invalid or
   there are no rows.
========================================== */

function customer_cart_load(int $customerId): array
{
    if (!customer_cart_customer_exists($customerId)) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT product_id, quantity
         FROM customer_carts
         WHERE customer_id = ?
         ORDER BY id ASC'
    );
    $stmt->execute([$customerId]);

    $cart = [];

    foreach ($stmt->fetchAll() as $row) {
        $productId = (int) $row['product_id'];
        $quantity  = clamp_cart_quantity((int) $row['quantity']);

        if ($productId > 0) {
            $cart[$productId] = $quantity;
        }
    }

    return $cart;
}


/* ==========================================
   SAVE / UPSERT ONE PRODUCT QUANTITY
   Inserts or replaces the row for this
   (customer, product). Quantity is clamped
   to 1-99. Does nothing if the customer is
   invalid or the product is not available
   for cart (same rule as cart_add()).
========================================== */

function customer_cart_save_item(int $customerId, int $productId, int $quantity): void
{
    error_log('[25C-DIAG] save_item() ENTER customer=' . $customerId . ' product=' . $productId . ' qty=' . $quantity);

    $customerExists = customer_cart_customer_exists($customerId);
    $productAvailable = product_is_available_for_cart($productId);
    error_log('[25C-DIAG] save_item() checks customer_exists=' . ($customerExists ? '1' : '0')
        . ' product_available=' . ($productAvailable ? '1' : '0'));

    if (!$customerExists) {
        error_log('[25C-DIAG] save_item() BAIL: customer_exists=false for customer=' . $customerId);
        return;
    }

    if (!$productAvailable) {
        error_log('[25C-DIAG] save_item() BAIL: product_is_available_for_cart=false for product=' . $productId);
        return;
    }

    $quantity = clamp_cart_quantity($quantity);
    error_log('[25C-DIAG] save_item() clamped_qty=' . $quantity);

    $stmt = null;
    try {
        $stmt = db()->prepare(
            'INSERT INTO customer_carts (customer_id, product_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = ?'
        );
    } catch (Throwable $e) {
        error_log('[25C-DIAG] save_item() PREPARE_EXCEPTION: ' . $e->getMessage());
        throw $e;
    }

    error_log('[25C-DIAG] save_item() before execute()'
        . ' customer=' . $customerId . ' product=' . $productId . ' qty=' . $quantity);

    try {
        $stmt->execute([$customerId, $productId, $quantity, $quantity]);
    } catch (Throwable $e) {
        error_log('[25C-DIAG] save_item() EXECUTE_EXCEPTION: ' . $e->getMessage());
        throw $e;
    }

    $rowCount = $stmt->rowCount();
    error_log('[25C-DIAG] save_item() EXECUTE_OK row_count=' . var_export($rowCount, true)
        . ' db=' . var_export(db()->query('SELECT DATABASE()')->fetchColumn(), true));

    $check = db()->query(
        'SELECT id, customer_id, product_id, quantity FROM customer_carts WHERE customer_id = '
        . (int) $customerId . ' AND product_id = ' . (int) $productId
    )->fetch(PDO::FETCH_ASSOC);
    error_log('[25C-DIAG] save_item() ROW_AFTER=' . var_export($check, true));
}


/* ==========================================
   UPDATE ONE PRODUCT QUANTITY
   Same clamp as cart_update_quantity().
   Does nothing if the customer/product ids
   are invalid. Does not remove a row when
   given 0 (removal is customer_cart_remove).
========================================== */

function customer_cart_update_quantity(int $customerId, int $productId, int $quantity): void
{
    if (!customer_cart_customer_exists($customerId)) {
        return;
    }

    if (!customer_cart_product_exists($productId)) {
        return;
    }

    $quantity = clamp_cart_quantity($quantity);

    $stmt = db()->prepare(
        'INSERT INTO customer_carts (customer_id, product_id, quantity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = ?'
    );
    $stmt->execute([$customerId, $productId, $quantity, $quantity]);
}


/* ==========================================
   REMOVE ONE PRODUCT FROM THE PERSISTED CART
========================================== */

function customer_cart_remove(int $customerId, int $productId): void
{
    if ($customerId <= 0 || $productId <= 0) {
        return;
    }

    $stmt = db()->prepare(
        'DELETE FROM customer_carts
         WHERE customer_id = ? AND product_id = ?'
    );
    $stmt->execute([$customerId, $productId]);
}


/* ==========================================
   CLEAR A CUSTOMER'S PERSISTED CART
========================================== */

function customer_cart_clear(int $customerId): void
{
    if ($customerId <= 0) {
        return;
    }

    $stmt = db()->prepare('DELETE FROM customer_carts WHERE customer_id = ?');
    $stmt->execute([$customerId]);
}


/* ==========================================
   #25B MERGE MAPS
   Union of session + DB. Same product: SUM
   then clamp 1-99. Invalid / unavailable
   product ids are dropped (same availability
   rule as cart_add()).
========================================== */

function customer_cart_merge_maps(array $sessionCart, array $dbCart): array
{
    $merged = [];
    $productIds = [];

    foreach (array_keys($sessionCart) as $id) {
        $productIds[(int) $id] = true;
    }

    foreach (array_keys($dbCart) as $id) {
        $productIds[(int) $id] = true;
    }

    foreach (array_keys($productIds) as $productId) {
        $productId = (int) $productId;

        if (!product_is_available_for_cart($productId)) {
            continue;
        }

        $sessionQty = 0;
        if (isset($sessionCart[$productId])) {
            $sessionQty = (int) $sessionCart[$productId];
        } elseif (isset($sessionCart[(string) $productId])) {
            $sessionQty = (int) $sessionCart[(string) $productId];
        }

        $dbQty = isset($dbCart[$productId]) ? (int) $dbCart[$productId] : 0;
        $total = $sessionQty + $dbQty;

        if ($total <= 0) {
            continue;
        }

        $merged[$productId] = clamp_cart_quantity($total);
    }

    return $merged;
}


/* ==========================================
   REPLACE THE PERSISTED CART ATOMICALLY
   Clear + insert in one transaction. Returns
   false on failure (caller must keep the
   original session cart).
========================================== */

function customer_cart_replace_all(int $customerId, array $cart): bool
{
    if (!customer_cart_customer_exists($customerId)) {
        return false;
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $delete = $pdo->prepare('DELETE FROM customer_carts WHERE customer_id = ?');
        $delete->execute([$customerId]);

        $insert = $pdo->prepare(
            'INSERT INTO customer_carts (customer_id, product_id, quantity)
             VALUES (?, ?, ?)'
        );

        foreach ($cart as $productId => $quantity) {
            $productId = (int) $productId;

            if (!product_is_available_for_cart($productId)) {
                continue;
            }

            $insert->execute([
                $customerId,
                $productId,
                clamp_cart_quantity((int) $quantity),
            ]);
        }

        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }
}


/* ==========================================
   #25B LOGIN MERGE
   Sum session + DB carts, persist the result,
   then put the same map in $_SESSION['cart'].
   If DB save fails, session cart is left
   unchanged.
========================================== */

function customer_cart_apply_login_merge(int $customerId, ?array $sessionCart = null): void
{
    if ($customerId <= 0) {
        return;
    }

    if ($sessionCart === null) {
        $sessionCart = (isset($_SESSION['cart']) && is_array($_SESSION['cart']))
            ? $_SESSION['cart']
            : [];
    }

    try {
        $dbCart = customer_cart_load($customerId);
        $merged = customer_cart_merge_maps($sessionCart, $dbCart);

        if (!customer_cart_replace_all($customerId, $merged)) {
            if ($merged !== [] && (!is_array($sessionCart) || $sessionCart === [])) {
                $_SESSION['cart'] = $merged;
            }
            return;
        }

        $_SESSION['cart'] = $merged;
        $_SESSION['customer_cart_hydrated'] = true;
    } catch (Throwable $e) {
        error_log('customer_cart login merge failed: ' . $e->getMessage());
    }
}


/* ==========================================
   #25C LOGGED-IN PERSISTENCE WRAPPER
   Guest (no customer_id): no-op.
   Logged-in: run $operation($customerId).
   Persistence exceptions are logged and
   swallowed so the session cart / AJAX
   response stay intact.
========================================== */

function customer_cart_sync_if_logged_in(callable $operation): void
{
    if (!function_exists('is_customer_logged_in')) {
        require_once __DIR__ . '/customer-auth.php';
    }

    $loggedIn = is_customer_logged_in();
    $sessionCustomerId = $_SESSION['customer_id'] ?? null;

    error_log('[25C-DIAG] sync_if_logged_in() logged_in=' . ($loggedIn ? '1' : '0')
        . ' session_customer_id=' . var_export($sessionCustomerId, true));

    if (!$loggedIn) {
        error_log('[25C-DIAG] sync_if_logged_in() BAIL: not logged in');
        return;
    }

    $customerId = (int) ($_SESSION['customer_id'] ?? 0);

    if ($customerId <= 0) {
        error_log('[25C-DIAG] sync_if_logged_in() BAIL: customer_id<=0');
        return;
    }

    try {
        error_log('[25C-DIAG] sync_if_logged_in() running $operation for customer=' . $customerId);
        $operation($customerId);
        error_log('[25C-DIAG] sync_if_logged_in() $operation completed for customer=' . $customerId);
    } catch (Throwable $e) {
        $dbName = null;
        try {
            $dbName = db()->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable $ignored) {
        }
        error_log('customer_cart persistence failed: ' . $e->getMessage()
            . ' [db=' . var_export($dbName, true) . ']');
    }
}


/* ==========================================
   #25C SESSION HYDRATE
   Once per login session, if the session cart
   is still empty, load customer_carts into
   $_SESSION['cart']. Covers a new device after
   login and a login request that authenticated
   but never finished merge. Never re-sums.
========================================== */

function customer_cart_maybe_hydrate_session(): void
{
    if (empty($_SESSION['customer_id'])) {
        return;
    }

    if (!empty($_SESSION['customer_cart_hydrated'])) {
        return;
    }

    $sessionCart = (isset($_SESSION['cart']) && is_array($_SESSION['cart']))
        ? $_SESSION['cart']
        : [];

    if ($sessionCart !== []) {
        $_SESSION['customer_cart_hydrated'] = true;
        return;
    }

    try {
        $dbCart = customer_cart_load((int) $_SESSION['customer_id']);

        if ($dbCart !== []) {
            $_SESSION['cart'] = $dbCart;
        }

        $_SESSION['customer_cart_hydrated'] = true;
    } catch (Throwable $e) {
        // Do NOT mark hydrated on failure - leave the flag unset so the
        // very next request retries the load. Otherwise a single read
        // error during the login POST would keep this session's cart
        // empty forever even after the DB becomes reachable again.
        error_log('customer_cart hydrate failed: ' . $e->getMessage());
    }
}


/* ==========================================
   #25C LOGGED-IN READ REFRESH
   For a logged-in customer the persisted
   customer_carts state is authoritative on a
   normal page refresh: re-load it into
   $_SESSION['cart'] so a change made on another
   device (add / update / remove) shows up
   without a logout/login.

   Runs at most once per request because a single
   GET page render can reach cart_get() several
   times (header badge cart_count(), the item
   listing, subtotal, ...).

   Guests (no $_SESSION['customer_id']) stay
   session-only. A DB / read failure is logged
   and the existing session cart is left exactly
   as it was - the session cart is only ever
   replaced AFTER a full successful read, never
   wiped because a read hiccuped.
========================================== */

function customer_cart_refresh_session_from_db(): void
{
    if (empty($_SESSION['customer_id'])) {
        return;
    }

    static $refreshedThisRequest = false;

    if ($refreshedThisRequest) {
        return;
    }
    $refreshedThisRequest = true;

    $customerId = (int) $_SESSION['customer_id'];

    if ($customerId <= 0) {
        return;
    }

    try {
        $dbCart = customer_cart_load($customerId);

        $_SESSION['cart']                   = $dbCart;
        $_SESSION['customer_cart_hydrated'] = true;
    } catch (Throwable $e) {
        // DB read failed - preserve the session cart untouched.
        error_log('customer_cart refresh-from-DB failed - session cart preserved: ' . $e->getMessage());
    }
}
