<?php
/* ===================================================================
   CART - ADD ITEM
   -------------------------------------------------------------------
   Handles the "Add to Cart" forms on the homepage, shop page, and
   product page. Redirects back to wherever the form was submitted
   from, so adding an item doesn't yank the customer away from what
   they were browsing.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/customer-cart-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('shop.php');
}

csrf_verify();

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity  = (int) ($_POST['quantity'] ?? 1);

// cart_add() itself checks the product exists/is active and clamps
// the quantity into range - it silently does nothing if either check
// fails, so there's nothing extra to validate here.
$canAdd = product_is_available_for_cart($productId);
cart_add($productId, $quantity);

if (!function_exists('is_customer_logged_in')) {
    require_once __DIR__ . '/includes/customer-auth.php';
}

$loggedInAtAdd = is_customer_logged_in();
$sessionCustomerIdAtAdd = $_SESSION['customer_id'] ?? null;
$dbNameAtAdd = null;
try {
    $dbNameAtAdd = db()->query('SELECT DATABASE()')->fetchColumn();
} catch (Throwable $ignored) {
}

error_log('[25C-DIAG] cart-add.php logged_in=' . ($loggedInAtAdd ? '1' : '0')
    . ' session_customer_id=' . var_export($sessionCustomerIdAtAdd, true)
    . ' product=' . $productId
    . ' qty_requested=' . $quantity
    . ' canAdd=' . ($canAdd ? '1' : '0')
    . ' db=' . var_export($dbNameAtAdd, true));

if ($canAdd) {
    $savedQuantity = (int) (cart_get()[$productId] ?? 0);

    error_log('[25C-DIAG] cart-add.php savedQuantity=' . $savedQuantity
        . ' entering sync block (' . ($savedQuantity > 0 ? 'will call sync_if_logged_in' : 'skipping: savedQuantity<=0')
        . ')');

    if ($savedQuantity > 0) {
        customer_cart_sync_if_logged_in(function (int $customerId) use ($productId, $savedQuantity): void {
            customer_cart_save_item($customerId, $productId, $savedQuantity);
        });
        error_log('[25C-DIAG] cart-add.php customer_cart_sync_if_logged_in() returned');
    }
}

// No flash message here on purpose - this redirects back to the
// homepage/shop/product pages, which don't display flash banners
// (kept exactly as designed, per "no redesign"). The header cart
// count badge updating is the feedback the customer sees instead.

/* ==========================================
   #21A - AJAX JSON RESPONSE
   Same is_ajax_request() convention as wishlist /
   cart-remove / cart-update. cart_add() already ran
   with the existing CSRF + availability + quantity
   clamp. This only decides how the result is
   reported. Non-JS POST still redirects below.
========================================== */

if (is_ajax_request()) {
    $addedItem = $canAdd ? ga4_item_from_product_id($productId, max(1, $quantity)) : null;

    header('Content-Type: application/json');
    echo json_encode([
        'success'    => $canAdd,
        'product_id' => $productId,
        'cart_count' => cart_count(),
        'item'       => $addedItem,
        'message'    => $canAdd
            ? 'Added to your cart.'
            : 'This item could not be added to your cart.',
    ]);
    exit;
}

/* ==========================================
   REDIRECT BACK TO THE REFERRING PAGE
   Only allow a LOCAL relative path (starts with
   a letter/number, never "http" or "//") so this
   can't be used to redirect someone off-site.
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
