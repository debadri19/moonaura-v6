<?php
/* ===================================================================
   CART - UPDATE QUANTITY
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/tax-functions.php';
require_once __DIR__ . '/includes/customer-cart-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

csrf_verify();

// #21 Phase A: same reasoning as cart-remove.php - an AJAX call reads
// its feedback from the JSON response, so the redirect-only flash
// message is skipped for it.
$isAjax = is_ajax_request();

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity  = (int) ($_POST['quantity'] ?? 1);

// cart_update_quantity() clamps the quantity into range (1-99)
// itself, so there's no need to pre-clamp it here. This is still the
// ONLY place the quantity is ever written - the JSON branch below
// only reads back what was already clamped and saved.
if ($productId > 0) {
    cart_update_quantity($productId, $quantity);

    $cart = cart_get();

    if (isset($cart[$productId])) {
        $savedQuantity = (int) $cart[$productId];
        customer_cart_sync_if_logged_in(function (int $customerId) use ($productId, $savedQuantity): void {
            customer_cart_update_quantity($customerId, $productId, $savedQuantity);
        });
    } else {
        customer_cart_sync_if_logged_in(function (int $customerId) use ($productId): void {
            customer_cart_remove($customerId, $productId);
        });
    }

    if (!$isAjax) {
        flash_set('success', 'Cart updated.');
    }
}

if ($isAjax) {
    // Read back this item's authoritative (already-clamped) quantity
    // and current line total from the same function cart.php itself
    // trusts - never the client's submitted $quantity value.
    $updatedQuantity = 0;
    $lineTotal       = 0.0;

    foreach (get_cart_items_with_details() as $item) {
        if ((int) $item['product']['id'] === $productId) {
            $updatedQuantity = $item['quantity'];
            $lineTotal       = $item['line_total'];
            break;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success'    => true,
        'product_id' => $productId,
        'quantity'   => $updatedQuantity,
        'line_total' => format_price($lineTotal),
        'cart_count' => cart_count(),
        'subtotal'   => format_price(get_cart_subtotal()),
        'gst_amount' => format_price(get_cart_gst_amount()),
        'total'      => format_price(get_cart_subtotal()),
    ]);
    exit;
}

redirect('cart.php');
