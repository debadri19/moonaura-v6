<?php
/* ===================================================================
   PAYMENT VERIFY (client-callback path)
   -------------------------------------------------------------------
   Receives whatever payment.js submitted after the active gateway's
   widget finished, and asks PaymentManager to confirm it before
   trusting any of it. This file is gateway-agnostic on purpose - it
   forwards every POST field through as $callbackData and lets
   PaymentManager/the resolved gateway class pick out whatever fields
   IT needs (a signature for Razorpay).

   This is the FAST path (immediate feedback for the customer) - the
   webhook (webhook-<gateway>.php) is the authoritative path that
   still confirms the same thing server-to-server, in case this page
   is never reached (tab closed, network drop, etc.).
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/order-functions.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/customer-functions.php';
require_once __DIR__ . '/includes/payment-functions.php';
require_once __DIR__ . '/includes/payments/PaymentManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

csrf_verify();

$orderNumber = trim($_POST['order_number'] ?? '');
$order       = $orderNumber !== '' ? get_order_by_number($orderNumber) : null;

if (!$order || !customer_or_session_owns_order($order)) {
    redirect('cart.php');
}

$gatewayOrderId = trim($_POST['gateway_order_id'] ?? '');

// Forward every submitted field through generically - each gateway
// class only reads the keys it understands (see
// PaymentGatewayInterface::verifyPayment()'s docblock).
$callbackData = $_POST;
unset($callbackData['csrf_token'], $callbackData['order_number']);

$isValid = $gatewayOrderId !== '' && PaymentManager::verifyPayment($gatewayOrderId, $callbackData, (int) $order['id']);

if ($isValid) {

    if (buy_now_active()) {
        buy_now_clear();
    } else {
        cart_clear();
    }

    // "Save this address for future orders" - deferred from
    // checkout.php until payment is actually confirmed (same
    // "commit only once truly successful" rule cart-clearing above
    // already follows). Reads the authoritative address that was
    // saved with the order itself, not anything client-submitted here.
    if (!empty($_SESSION['pending_payment_save_address'])) {
        $loggedInCustomer = current_customer();
        if ($loggedInCustomer) {
            $addressStmt = db()->prepare('SELECT * FROM order_addresses WHERE order_id = ? LIMIT 1');
            $addressStmt->execute([$order['id']]);
            $orderAddress = $addressStmt->fetch();

            if ($orderAddress) {
                save_customer_address_if_new((int) $loggedInCustomer['id'], $orderAddress);
            }
        }
    }
    unset($_SESSION['pending_payment_save_address']);

    unset($_SESSION['pending_payment_order_number']);
    $_SESSION['last_order_number'] = $order['order_number'];

    redirect('order-success.php?order=' . urlencode($order['order_number']));

} else {

    redirect('payment-failure.php?order=' . urlencode($order['order_number']));
}
