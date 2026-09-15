<?php
/* ===================================================================
   BUY NOW
   -------------------------------------------------------------------
   Handles the "Buy Now" form on the product page. Deliberately does
   NOT touch the cart (cart_add()/$_SESSION['cart'] are never called
   here) - it stores a separate one-item "buy now" session and sends
   the customer straight to checkout.php, which reads that session
   instead of the cart for this request. Works for guests and logged-
   in customers exactly like the existing cart-based checkout does -
   no login is required here either.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('shop.php');
}

csrf_verify();

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity  = (int) ($_POST['quantity'] ?? 1);

// buy_now_set() checks the product exists/is active and clamps the
// quantity into range, exactly like cart_add() does. If it fails
// (bad/inactive product id), there's nothing to buy - send the
// customer to the shop instead of a checkout page with nothing in it.
if (!buy_now_set($productId, $quantity)) {
    redirect('shop.php');
}

redirect('checkout.php');
