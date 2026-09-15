<?php
/* ===================================================================
   INVOICE DOWNLOAD / PRINT
   -------------------------------------------------------------------
   Same ownership check as order-detail.php - always queries
   WHERE user_id = <this customer> AND order_number = ? together, so a
   customer can never fetch another customer's invoice by editing the
   URL. Scoped to logged-in customers only - guest orders use a
   separate, deliberately different file (guest-invoice.php, project
   root) with signed-token auth instead of a login check; see that
   file and generate_guest_invoice_token()/verify_guest_invoice_token()
   in includes/invoice-functions.php.

   ?mode=download (default) -> PDF attachment (download only, no print)
   ?mode=print               -> generate/load the PDF and open the
                                 browser print flow (not a download)

   The PDF is built fresh on every request - see the "why on-demand"
   note at the top of includes/invoice-functions.php. Nothing here
   touches Payment Flow / PaymentManager / Gateway classes
   / checkout.php / the database schema.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../includes/invoice-functions.php';

require_customer_login();

$customer = current_customer();

$orderNumber = trim($_GET['order'] ?? '');
$mode        = (($_GET['mode'] ?? 'download') === 'print') ? 'print' : 'download';

$stmt = db()->prepare(
    'SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$orderNumber, $customer['id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders.php');
}

try {
    $invoiceMeta = get_or_create_invoice_number((int) $order['id']);
} catch (Exception $e) {
    error_log('Invoice generation failed for order #' . $order['id'] . ': ' . $e->getMessage());
    flash_set('error', 'We could not generate your invoice right now. Please try again in a moment.');
    redirect('order-detail.php?order=' . urlencode($orderNumber));
}

$order['invoice_number']       = $invoiceMeta['invoice_number'];
$order['invoice_generated_at'] = $invoiceMeta['invoice_generated_at'];

$stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
$stmt->execute([$order['id']]);
$orderItems = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM order_addresses WHERE order_id = ? LIMIT 1');
$stmt->execute([$order['id']]);
$orderAddress = $stmt->fetch() ?: null;

$pdfBytes = build_invoice_pdf($order, $orderItems, $orderAddress);
$filename = 'Invoice-' . $order['invoice_number'] . '.pdf';

send_invoice_pdf_response($pdfBytes, $filename, $mode);
