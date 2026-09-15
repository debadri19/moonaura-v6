<?php
/* ===================================================================
   WISHLIST FUNCTIONS
   -------------------------------------------------------------------
   A simple SESSION-based wishlist, deliberately mirroring
   includes/cart-functions.php's own pattern (same "$_SESSION array of
   product IDs, joined against live product data for display" shape,
   same "no login required" behavior as the cart) rather than
   introducing a new architecture (a customer_wishlist DB table would
   need its own migration, a merge-on-login story for a guest who logs
   in mid-session, etc.) for a feature the project's own cart already
   solves the exact same problem for. Nothing here touches the
   database except to look up live product details - the wishlist
   itself only ever lives in $_SESSION, exactly like the cart.

   Wishlist shape: $_SESSION['wishlist'] = [ product_id, product_id, ... ]
   (a plain list, not product_id => quantity like the cart - a
   wishlist item has no quantity, just "is it saved or not").
=================================================================== */

require_once __DIR__ . '/db.php';


/* ==========================================
   GET THE RAW WISHLIST ARRAY
   Makes sure $_SESSION['wishlist'] always
   exists, so nothing else has to check for it.
========================================== */

function wishlist_get(): array
{
    if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }

    return $_SESSION['wishlist'];
}


/* ==========================================
   IS THIS PRODUCT IN THE WISHLIST?
========================================== */

function is_in_wishlist(int $productId): bool
{
    return in_array($productId, wishlist_get(), true);
}


/* ==========================================
   ADD A PRODUCT TO THE WISHLIST
   Silently does nothing if the product doesn't
   exist/isn't active, or is already saved -
   same "fail quietly, nothing left to validate
   by the caller" convention as cart_add().
========================================== */

function wishlist_add(int $productId): void
{
    if ($productId <= 0 || is_in_wishlist($productId)) {
        return;
    }

    $stmt = db()->prepare('SELECT id FROM products WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$productId]);

    if (!$stmt->fetch()) {
        return;
    }

    $wishlist   = wishlist_get();
    $wishlist[] = $productId;

    $_SESSION['wishlist'] = $wishlist;
}


/* ==========================================
   REMOVE A PRODUCT FROM THE WISHLIST
========================================== */

function wishlist_remove(int $productId): void
{
    $_SESSION['wishlist'] = array_values(
        array_diff(wishlist_get(), [$productId])
    );
}


/* ==========================================
   TOGGLE - the one both wishlist-toggle.php
   and every "heart" button on the site actually
   use, so there's only one place that decides
   which direction a click goes.
========================================== */

function wishlist_toggle(int $productId): void
{
    if (is_in_wishlist($productId)) {
        wishlist_remove($productId);
    } else {
        wishlist_add($productId);
    }
}


/* ==========================================
   HOW MANY ITEMS ARE SAVED
   (for the header icon's count badge, same
   role as cart_count()).
========================================== */

function wishlist_count(): int
{
    return count(wishlist_get());
}


/* ==========================================
   GET WISHLIST ITEMS WITH LIVE PRODUCT DETAILS
   -------------------------------------------------
   Same shape/intent as get_cart_items_with_details():
   joins the session's product IDs with a fresh
   database lookup so prices/names/images are
   always current. A product that's been deleted
   entirely is skipped (and quietly dropped from
   the session, since there's nothing left to show
   or remove); one that still exists but is no
   longer active/available is still returned
   (marked unavailable) so the wishlist page can
   show it rather than it just silently vanishing -
   same convention the cart already uses.
========================================== */

function get_wishlist_items_with_details(): array
{
    $wishlist = wishlist_get();

    if (empty($wishlist)) {
        return [];
    }

    $items      = [];
    $stillValid = [];

    foreach ($wishlist as $productId) {

        $stmt = db()->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?'
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            continue; // deleted entirely - drop it from the session below
        }

        $stillValid[] = $productId;

        $items[] = [
            'product'      => $product,
            'is_available' => $product['status'] === 'active',
        ];
    }

    $_SESSION['wishlist'] = $stillValid;

    return $items;
}
