<?php
/* ===================================================================
   ADMIN - INVOICE DESIGNER - LIVE PREVIEW  (Phase 6)
   -------------------------------------------------------------------
   Renders a real invoice PDF from fixed sample order data (never a
   real customer/order) using whatever Invoice Designer settings are
   currently saved, and streams it inline for the <iframe> on
   admin/invoice-designer.php to display. Requires an active admin
   session, same as every other admin page - this is not a public URL.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/invoice-functions.php';

require_admin_login();

$previewOrder = [
    'id' => 0,
    'order_number'   => 'MOAOD20260101SAMPLE',
    'invoice_number' => 'MOAINV20260101SAMPLE',
    'invoice_generated_at' => date('Y-m-d H:i:s'),
    'created_at'     => date('Y-m-d H:i:s'),
    'customer_name'  => 'Aanya Sharma',
    'customer_email' => 'aanya.sharma@example.com',
    'customer_phone' => '9876543210',
    'subtotal'       => 1498.00,
    'discount'       => 100.00,
    'shipping_charge' => 0.00,
    'taxable_value'  => 1331.25,
    'cgst_amount'    => 33.28,
    'sgst_amount'    => 33.28,
    'igst_amount'    => 0.00,
    'gst_amount'     => 66.56,
    'gst_rate'       => 5.00,
    'tax_type'       => 'intra',
    'grand_total'    => 1398.00,
    'payment_method' => 'cod',
    'payment_status' => 'pending',
];
$previewItems = [
    ['product_name' => 'Rose Quartz Bracelet', 'quantity' => 1, 'unit_price' => 499.00, 'line_total' => 499.00, 'gst_rate' => 5.00],
    ['product_name' => 'Green Aventurine Bracelet (Career Success)', 'quantity' => 2, 'unit_price' => 499.50, 'line_total' => 999.00, 'gst_rate' => 5.00],
];
$previewAddress = [
    'full_name'     => 'Aanya Sharma',
    'address_line1' => '221B Baker Street',
    'address_line2' => 'Near Central Park',
    'landmark'      => 'Opposite City Mall',
    'city'          => 'Kolkata',
    'state'         => 'West Bengal',
    'postal_code'   => '700001',
];

try {
    $pdfBytes = build_invoice_pdf($previewOrder, $previewItems, $previewAddress);
} catch (Throwable $e) {
    error_log('Invoice Designer preview failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Preview could not be generated with the current settings.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="invoice-preview.pdf"');
header('Content-Length: ' . strlen($pdfBytes));
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $pdfBytes;
