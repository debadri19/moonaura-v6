<?php
/* ===================================================================
   ADMIN - INVOICE VIEW (read-only)
   -------------------------------------------------------------------
   Phase 3D: admins had no way to see a customer's invoice at all.
   This is a thin admin-authenticated wrapper - it does not duplicate
   invoice logic. Same get_or_create_invoice_number() +
   build_invoice_pdf() call sequence as account/invoice.php and
   guest-invoice.php; the only difference is the auth/lookup at the
   top (require_admin_login() + lookup by orders.id, since this page
   is already behind admin login the same way dashboard/order-detail.php
   is - no signed token needed here, unlike the guest flow).

   No "print" variant was added - a print-vs-download distinction
   matters for a customer's own workflow but wasn't asked for here.
   Read-only in every sense: this can generate an invoice NUMBER on
   first view (same lazy generation as the customer-facing flow - it's
   the one thing about the order this endpoint can cause to change),
   but never edits the order itself.

   Phase 5G: added optional ?mode=download (Content-Disposition:
   attachment) so dashboard/order-detail.php can offer both an inline
   "View Invoice" link (the default, unchanged) and an explicit
   "Download" link for the same file.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/invoice-functions.php';

require_admin_login();

$orderId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders.php');
}

try {
    $invoiceMeta = get_or_create_invoice_number((int) $order['id']);
} catch (Exception $e) {
    error_log('Admin invoice generation failed for order #' . $order['id'] . ': ' . $e->getMessage());
    flash_set('error', 'Could not generate the invoice right now. Please try again in a moment.');
    redirect('order-detail.php?id=' . (int) $order['id']);
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

// Phase 5G: ?mode=download forces a Content-Disposition of "attachment"
// (browser downloads the file instead of displaying it inline). Default
// remains "inline" so existing "View Invoice" links keep working
// exactly as before - dashboard/order-detail.php now offers both, one
// "View Invoice" link and one "Download" link next to it.
$disposition = (($_GET['mode'] ?? '') === 'download') ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $pdfBytes;
exit;
