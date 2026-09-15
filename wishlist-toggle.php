<?php
/* ===================================================================
   WISHLIST - TOGGLE ITEM
   -------------------------------------------------------------------
   Handles every heart button on the site (homepage, shop, product
   page, and the wishlist page itself - where a click there means
   "remove"). Same shape as cart-add.php: redirects back to wherever
   the form was submitted from, so toggling a heart doesn't yank the
   customer away from what they were browsing.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/wishlist-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('shop.php');
}

csrf_verify();

$productId = (int) ($_POST['product_id'] ?? 0);

// wishlist_toggle() itself checks the product exists/is active before
// adding - it silently does nothing if that check fails, same
// "nothing extra to validate here" convention as cart-add.php.
wishlist_toggle($productId);

// No flash message here on purpose - same reasoning as cart-add.php:
// this redirects back to pages that don't display flash banners, and
// the header wishlist-count badge updating (plus the heart itself
// switching between outline/filled after the redirect) is the
// feedback the customer sees instead.

/* ==========================================
   #21 PHASE A - AJAX JSON RESPONSE
   -------------------------------------------------
   Same "X-Requested-With" convention #38's dashboard
   endpoint already uses to tell an AJAX call apart
   from a normal form submission. Nothing above this
   point changed - wishlist_toggle() already ran with
   the exact same validation/CSRF it always had. This
   only decides how the result is reported back.

   No login/auth check exists for the wishlist today
   (see includes/wishlist-functions.php - it's a
   session wishlist, same as the cart, with no login
   requirement to preserve), so none is introduced
   here either.
========================================== */

if (is_ajax_request()) {
    $inWishlist = is_in_wishlist($productId);
    $wishlistItem = $inWishlist ? ga4_item_from_product_id($productId, 1) : null;

    header('Content-Type: application/json');
    echo json_encode([
        'success'        => true,
        'product_id'     => $productId,
        'in_wishlist'    => $inWishlist,
        'wishlist_count' => wishlist_count(),
        'item'           => $wishlistItem,
    ]);
    exit;
}

/* ==========================================
   REDIRECT BACK TO THE REFERRING PAGE
   Identical safe-local-path check to
   cart-add.php - only ever redirects somewhere
   on this site, never off it.
========================================== */

$redirectTo = $_POST['redirect_to'] ?? 'shop.php';

$isSafeLocalPath =
    $redirectTo !== ''
    && !str_starts_with($redirectTo, '//')                 // blocks protocol-relative URLs, e.g. //evil.com
    && !preg_match('#^https?://#i', $redirectTo)            // blocks absolute URLs, e.g. http://evil.com
    && preg_match('/^\/?[a-zA-Z0-9][a-zA-Z0-9._\-\/?=&]*$/', $redirectTo);

if (!$isSafeLocalPath) {
    $redirectTo = 'shop.php';
}

redirect($redirectTo);
