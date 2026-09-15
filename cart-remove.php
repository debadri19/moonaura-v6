<?php
/* ===================================================================
   CART - REMOVE ITEM
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/tax-functions.php';
require_once __DIR__ . '/includes/customer-cart-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

csrf_verify();

// #21 Phase A: an AJAX call gets its feedback from the JSON response
// below instead of a redirect - the flash message exists purely to
// survive a redirect, so it's skipped here rather than sitting
// unread in the session until some later full page load.
$isAjax = is_ajax_request();

$productId = (int) ($_POST['product_id'] ?? 0);
$removedQuantity = $productId > 0 ? (int) (cart_get()[$productId] ?? 0) : 0;
$removedItem = ($productId > 0 && $removedQuantity > 0)
    ? ga4_item_from_product_id($productId, $removedQuantity)
    : null;

if ($productId > 0) {
    cart_remove($productId);

    customer_cart_sync_if_logged_in(function (int $customerId) use ($productId): void {
        customer_cart_remove($customerId, $productId);
    });

    if (!$isAjax) {
        flash_set('success', 'Item removed from your cart.');
    }
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'    => true,
        'product_id' => $productId,
        'cart_count' => cart_count(),
        'subtotal'   => format_price(get_cart_subtotal()),
        'gst_amount' => format_price(get_cart_gst_amount()),
        'total'      => format_price(get_cart_subtotal()),
        'is_empty'   => empty(get_cart_items_with_details()),
        'item'       => $removedItem,
    ]);
    exit;
}

redirect('cart.php');
