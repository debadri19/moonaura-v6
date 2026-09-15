<?php
/* ===================================================================
   GUEST INVOICE DOWNLOAD / PRINT
   -------------------------------------------------------------------
   The no-login counterpart to account/invoice.php - lets a GUEST
   customer download/print their invoice from order-success.php.
   account/invoice.php itself is untouched; this is a separate,
   self-contained endpoint so that file's existing, working,
   login-gated flow is never at risk of being changed by this one.

   AUTHORIZATION: order_number + a signed token (?exp=&sig=), verified
   by verify_guest_invoice_token() (includes/invoice-functions.php) -
   NOT a session, NOT a raw order_id, NOT "trust order_number because
   it looks unguessable". See that function's doc comment for the
   full reasoning. order_id is never exposed in the URL or read from
   user input anywhere here - the order is looked up by order_number
   only, exactly like every other customer-facing order lookup in
   this project (order-success.php, account/order-detail.php,
   account/invoice.php all follow this same rule).

   Same generation flow as account/invoice.php, reused as-is:
   get_or_create_invoice_number() + build_invoice_pdf() (both in
   includes/invoice-functions.php) - nothing about how an invoice
   number is generated or how the PDF is built is duplicated or
   changed here.

   ?mode=download (default) -> PDF attachment (download only, no print)
   ?mode=print               -> generate/load the PDF and open the
                                 browser print flow (not a download)
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice-functions.php';

$orderNumber = trim($_GET['order'] ?? '');
$expParam    = trim($_GET['exp'] ?? '');
$sigParam    = trim($_GET['sig'] ?? '');
$mode        = (($_GET['mode'] ?? 'download') === 'print') ? 'print' : 'download';

if ($orderNumber === '' || !verify_guest_invoice_token($orderNumber, $expParam, $sigParam)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "This invoice link is invalid or has expired.\nPlease return to your order confirmation page for a fresh link.";
    exit;
}

$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Order not found.';
    exit;
}

// Same rule as order-success.php: an invoice only makes sense once
// payment has actually settled (paid online, or accepted as COD).
$paymentIsSettled = $order['payment_status'] === 'paid' || $order['payment_method'] === 'cod';

if (!$paymentIsSettled) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'This order does not have a completed payment yet.';
    exit;
}

try {
    $invoiceMeta = get_or_create_invoice_number((int) $order['id']);
} catch (Exception $e) {
    error_log('Guest invoice generation failed for order #' . $order['id'] . ': ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'We could not generate your invoice right now. Please try again in a moment.';
    exit;
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

header('X-Robots-Tag: noindex, nofollow');
send_invoice_pdf_response($pdfBytes, $filename, $mode);
