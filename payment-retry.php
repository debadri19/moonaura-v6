<?php
/* ===================================================================
   PAYMENT RETRY
   -------------------------------------------------------------------
   Does not create a new order - re-establishes the session
   authorization flag for the existing order and sends the customer
   back to payment.php, which creates a fresh gateway order (a new
   payment_transactions row) for a new attempt.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/order-functions.php';
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/payment-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

csrf_verify();

$orderNumber = trim($_POST['order_number'] ?? '');
$order       = $orderNumber !== '' ? get_order_by_number($orderNumber) : null;

if (!$order || !customer_or_session_owns_order($order) || $order['payment_status'] === 'paid') {
    redirect('cart.php');
}

$_SESSION['pending_payment_order_number'] = $order['order_number'];

// Manual UPI has no online gateway - route the retry to its
// dedicated page instead of payment.php (which would only bounce
// it right back here via the same manual_upi guard).
if ($order['payment_method'] === 'manual_upi') {
    redirect('manual-upi-payment.php?order=' . urlencode($order['order_number']));
}

redirect('payment.php?order=' . urlencode($order['order_number']));
