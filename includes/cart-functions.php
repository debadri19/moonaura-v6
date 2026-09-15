<?php
/* ===================================================================
   CART FUNCTIONS
   -------------------------------------------------------------------
   A simple SESSION-based shopping cart. Nothing here touches the
   database except to look up live product details for display -
   the cart itself only ever lives in $_SESSION.

   Cart shape: $_SESSION['cart'] = [ product_id => quantity, ... ]
=================================================================== */

require_once __DIR__ . '/db.php';


/* ==========================================
   QUANTITY LIMITS
========================================== */

const CART_MIN_QUANTITY = 1;
const CART_MAX_QUANTITY = 99;


/* ==========================================
   CLAMP A QUANTITY INTO THE ALLOWED RANGE
   Never trusts the caller's number as-is -
   anything below the minimum or above the
   maximum is pulled back into range instead
   of being rejected outright.
========================================== */

function clamp_cart_quantity(int $quantity): int
{
    return max(CART_MIN_QUANTITY, min($quantity, CART_MAX_QUANTITY));
}


/* ==========================================
   IS THIS PRODUCT ALLOWED TO BE ADDED TO A
   CART?
   True only if the product still exists AND
   is active (the same "status" the rest of
   the project already uses - see products.status
   in schema.sql: 'active' or 'draft').
========================================== */

function product_is_available_for_cart(int $productId): bool
{
    if ($productId <= 0) {
        return false;
    }

    $stmt = db()->prepare('SELECT id FROM products WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$productId]);

    return (bool) $stmt->fetch();
}


/* ==========================================
   GET THE RAW CART ARRAY
   Makes sure $_SESSION['cart'] always exists,
   so nothing else has to check for it.
========================================== */

function cart_get(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (!empty($_SESSION['customer_id'])) {
        require_once __DIR__ . '/customer-cart-functions.php';

        // #25C cross-device refresh: for a logged-in customer the
        // persisted customer_carts state is authoritative on a normal
        // page refresh (GET) - reload it into the session cart so a
        // cart change made on another device shows up without a
        // logout/login. POST handlers (cart-add / cart-update /
        // cart-remove / cart_clear, incl. AJAX) keep the old behaviour:
        // their session cart can be one step ahead of the DB mid-
        // request, so they never refresh from it.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            customer_cart_refresh_session_from_db();
        } elseif (empty($_SESSION['customer_cart_hydrated'])) {
            customer_cart_maybe_hydrate_session();
        }
    }

    return $_SESSION['cart'];
}


/* ==========================================
   ADD A PRODUCT TO THE CART
   -------------------------------------------------
   If it's already in the cart, increases the
   quantity instead of adding a duplicate line.

   Does nothing (leaves the cart unchanged) if the
   product doesn't exist or isn't active - the
   caller (cart-add.php) doesn't need to check this
   itself, it just redirects back either way.
========================================== */

function cart_add(int $productId, int $quantity = 1): void
{
    if (!product_is_available_for_cart($productId)) {
        return;
    }

    $quantity = clamp_cart_quantity($quantity);

    $cart = cart_get();

    $newQuantity       = ($cart[$productId] ?? 0) + $quantity;
    $cart[$productId]  = clamp_cart_quantity($newQuantity); // clamp the running total too, not just this one add

    $_SESSION['cart'] = $cart;
}


/* ==========================================
   SET A PRODUCT'S QUANTITY DIRECTLY
   -------------------------------------------------
   Used by the cart page's "Update" action. The
   quantity is always clamped into range (1-99) -
   removal is a separate, dedicated action
   (cart_remove(), used by the Remove button), not
   something triggered by typing 0 here.
========================================== */

function cart_update_quantity(int $productId, int $quantity): void
{
    if ($productId <= 0) {
        return;
    }

    $cart = cart_get();

    $cart[$productId] = clamp_cart_quantity($quantity);

    $_SESSION['cart'] = $cart;
}


/* ==========================================
   REMOVE A PRODUCT FROM THE CART
========================================== */

function cart_remove(int $productId): void
{
    $cart = cart_get();

    unset($cart[$productId]);

    $_SESSION['cart'] = $cart;
}


/* ==========================================
   CLEAR THE ENTIRE CART
   Not used yet in Phase 2B - included now
   since it's a natural, one-line part of cart
   management that a future Checkout phase will
   need right away.
========================================== */

function cart_clear(): void
{
    $_SESSION['cart'] = [];

    require_once __DIR__ . '/customer-cart-functions.php';
    customer_cart_sync_if_logged_in(function (int $customerId): void {
        customer_cart_clear($customerId);
    });
}


/* ==========================================
   TOTAL ITEM COUNT (for the header badge)
   Adds up quantities, so 2 different products
   x2 each shows "4", not "2".
========================================== */

function cart_count(): int
{
    $cart = cart_get();

    return array_sum($cart);
}


/* ==========================================
   GET CART ITEMS WITH LIVE PRODUCT DETAILS
   -------------------------------------------------
   Joins the session's [product_id => quantity]
   pairs with a fresh database lookup, so prices,
   names, and images are always current - never a
   stale snapshot. Products that are no longer
   active/available are still returned (marked
   "unavailable") so the cart page can show them
   and let the customer remove them, rather than
   just silently vanishing.
========================================== */

function get_cart_items_with_details(): array
{
    $cart = cart_get();

    if (empty($cart)) {
        return [];
    }

    $items = [];

    foreach ($cart as $productId => $quantity) {

        $stmt = db()->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?'
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            // The product was deleted entirely - skip it, but the
            // customer can still remove its leftover session entry
            // via cart-remove.php if they somehow linked to it.
            continue;
        }

        $isAvailable = $product['status'] === 'active';

        $items[] = [
            'product'      => $product,
            'quantity'     => $quantity,
            'is_available' => $isAvailable,
            'line_total'   => $isAvailable ? ((float) $product['sell_price'] * $quantity) : 0.0,
        ];
    }

    return $items;
}


/* ==========================================
   CART SUBTOTAL
   Sum of line totals for AVAILABLE items only
   (an unavailable/removed product contributes
   nothing to the total).
========================================== */

function get_cart_subtotal(): float
{
    $subtotal = 0.0;

    foreach (get_cart_items_with_details() as $item) {
        $subtotal += $item['line_total'];
    }

    return $subtotal;
}


/* ==========================================
   BUY NOW
   -------------------------------------------------------------------
   Deliberately SEPARATE from the cart above - "Buy Now" skips the
   cart entirely. Nothing in this section ever reads or writes
   $_SESSION['cart'], and cart_add()/cart_clear() are never called
   from here. Session shape: $_SESSION['buy_now'] =
   ['product_id' => int, 'quantity' => int].

   get_buy_now_items_with_details() returns the exact same item shape
   get_cart_items_with_details() does (['product' => ..., 'quantity'
   => ..., 'is_available' => ..., 'line_total' => ...]), so every
   downstream consumer (checkout.php's totals calculation,
   validate_cart_stock(), create_order()) works unchanged for a Buy
   Now checkout - it's simply handed a one-item list instead of the
   cart's list.
========================================== */

function buy_now_set(int $productId, int $quantity = 1): bool
{
    if (!product_is_available_for_cart($productId)) {
        return false;
    }

    $_SESSION['buy_now'] = [
        'product_id' => $productId,
        'quantity'   => clamp_cart_quantity($quantity),
    ];

    return true;
}

function buy_now_active(): bool
{
    return isset($_SESSION['buy_now']['product_id'], $_SESSION['buy_now']['quantity']);
}

function buy_now_clear(): void
{
    unset($_SESSION['buy_now']);
}

function get_buy_now_items_with_details(): array
{
    if (!buy_now_active()) {
        return [];
    }

    $productId = (int) $_SESSION['buy_now']['product_id'];
    $quantity  = clamp_cart_quantity((int) $_SESSION['buy_now']['quantity']);

    // Fresh SELECT p.* every single load - never trusted from the
    // session beyond the id/quantity themselves, same guarantee the
    // cart's own get_cart_items_with_details() makes.
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ?
         LIMIT 1'
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        return [];
    }

    $isAvailable = $product['status'] === 'active';

    return [[
        'product'      => $product,
        'quantity'     => $quantity,
        'is_available' => $isAvailable,
        'line_total'   => $isAvailable ? round((float) $product['sell_price'] * $quantity, 2) : 0.0,
    ]];
}
