<?php
/* ===================================================================
   ADMIN - ORDER DETAIL
   -------------------------------------------------------------------
   Read-only - see admin/orders.php's doc comment for why (status
   changes/shipping actions are deliberately left to a later,
   not-yet-scoped phase). Looked up by the numeric `orders.id` in the
   URL - unlike the customer-facing account/invoice.php (which
   deliberately avoids exposing that id to a logged-out visitor), this
   page is already behind require_admin_login(), so there's no
   equivalent concern here.

   Phase 3D: added a "View Invoice"/"Generate & View" link next to the
   invoice row, pointing at the new admin/invoice.php - a thin wrapper
   around the exact same get_or_create_invoice_number()/
   build_invoice_pdf() the customer-facing flows already use. Still
   read-only in the sense that matters here: nothing about the order
   itself is editable from this page.

   Phase 5G: added a "Download" link next to "View Invoice" for
   invoices that already exist, pointing at the same admin/invoice.php
   with ?mode=download (which switches the response to Content-
   Disposition: attachment - see that file). "Generate & View" is
   intentionally left alone so a never-invoiced order is still first
   shown, not silently downloaded.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payment-functions.php';
require_once __DIR__ . '/../includes/order-functions.php';
require_once __DIR__ . '/../includes/stock-functions.php';
require_once __DIR__ . '/../includes/order-emails.php';

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Order Detail';
$activePage = 'orders';

$orderId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders.php');
}

$stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
$stmt->execute([$order['id']]);
$orderItems = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM order_addresses WHERE order_id = ? LIMIT 1');
$stmt->execute([$order['id']]);
$orderAddress = $stmt->fetch();

// Same "COD never creates a payment_transactions row" note as
// order-success.php - this lookup only makes sense for online
// payments (and Manual UPI, which logs a 'submitted' row the same
// way an online gateway logs a 'created' one).
$paymentTransaction = null;

if ($order['payment_method'] !== 'cod') {
    $stmt = db()->prepare(
        "SELECT * FROM payment_transactions
         WHERE order_id = ?
         ORDER BY (status = 'paid') DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([$order['id']]);
    $paymentTransaction = $stmt->fetch() ?: null;
}


/* ==========================================
   MANUAL UPI - VERIFY / REJECT PAYMENT
   -------------------------------------------------
   Only ever available for a manual_upi order still sitting at
   payment_status = 'pending' - once it's 'paid' or 'failed' there's
   nothing left to decide, and this deliberately never touches any
   other payment method (Razorpay resolves itself via
   PaymentManager::verifyPayment()/handleWebhook() - see that file -
   an admin overriding that here would bypass the actual gateway's
   own verification, which is exactly what this must NOT do).
========================================== */

$canReviewManualUpi = $order['payment_method'] === 'manual_upi'
    && $order['payment_status'] === 'pending'
    && $paymentTransaction !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_upi_action'])) {

    csrf_verify();

    if (!$canReviewManualUpi) {
        flash_set('error', 'This order is not awaiting Manual UPI verification.');
        redirect('order-detail.php?id=' . $orderId);
    }

    $action = $_POST['manual_upi_action'];

    if ($action === 'verify') {

        update_payment_transaction($paymentTransaction['id'], ['status' => 'paid']);
        update_order_payment_status($order['id'], 'paid', 'processing', 'manual_upi');

        // Manual UPI stock deduction point (Phase 6): stock is reserved
        // only once an admin actually VERIFIES the payment - never at
        // creation and never on reject. Idempotent via the
        // stock_deducted_at marker (see includes/stock-functions.php).
        apply_order_stock_deduction($order['id']);

        flash_set('success', 'Payment verified - order marked as Paid and moved to Processing.');

    } elseif ($action === 'reject') {

        update_payment_transaction($paymentTransaction['id'], ['status' => 'failed']);
        // Order status is left as-is on purpose - a rejected payment
        // means the order isn't paid, not that it's cancelled; the
        // customer may still resolve it (resubmit a correct UTR, or
        // be contacted directly), same as a failed online-gateway
        // attempt doesn't cancel the order either.
        update_order_payment_status($order['id'], 'failed', $order['order_status'], 'manual_upi');

        flash_set('success', 'Payment rejected - order marked as payment Failed.');

    } else {
        flash_set('error', 'Unknown action.');
    }

    redirect('order-detail.php?id=' . $orderId);
}

/* ==========================================
   ORDER STATUS UPDATE (admin dropdown)
   -------------------------------------------------
   Uses update_order_status(), which only touches
   orders.order_status and logs the real transition to
   the timeline. Safe for every payment method.

   Phase C1: added an AJAX JSON branch alongside the
   original POST + redirect + flash behavior, which is
   left completely unchanged for non-JS / AJAX-failure
   fallback. Detected the same way admin-dashboard.js's
   existing polling request already does (X-Requested-
   With header) - no new detection mechanism introduced.
   csrf_verify()/require_admin_login() above run exactly
   as before for both branches.
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_status_update'])) {

    csrf_verify();

    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

    $newStatus = (string) ($_POST['order_status'] ?? '');

    try {
        update_order_status($order['id'], $newStatus);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'            => true,
                'message'            => 'Order status updated.',
                'order_status'       => $newStatus,
                'order_status_label' => ucfirst($newStatus),
            ]);
            exit;
        }

        flash_set('success', 'Order status updated.');
    } catch (InvalidArgumentException $e) {

        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid order status selected.',
            ]);
            exit;
        }

        flash_set('error', 'Invalid order status selected.');
    }

    redirect('order-detail.php?id=' . $orderId);
}

/* ==========================================
   PAYMENT STATUS UPDATE (admin dropdown)
   -------------------------------------------------
   Only offered for COD and Manual UPI. Razorpay
   orders keep the gateway verification as the
   single source of truth - overriding that here would
   bypass the gateway's own verification, which is
   deliberately not allowed (same rule as the Manual
   UPI verify comment above). Mapping paid -> order
   status 'processing' matches the existing convention
   used by the Manual UPI verify action.
========================================== */

$paymentStatusEditable = in_array($order['payment_method'], ['cod', 'manual_upi'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_status_update'])) {

    csrf_verify();

    if (!$paymentStatusEditable) {
        flash_set('error', 'Payment status for online gateway orders is managed by the gateway itself.');
    } else {

        $newPaymentStatus = (string) ($_POST['payment_status'] ?? '');

        if (in_array($newPaymentStatus, ['pending', 'paid', 'failed', 'refunded'], true)) {
            $newOrderStatus = $newPaymentStatus === 'paid' ? 'processing' : $order['order_status'];
            update_order_payment_status($order['id'], $newPaymentStatus, $newOrderStatus, $order['payment_method']);

            // If the admin is marking the order Paid via this dropdown,
            // that is the confirmation point for this COD/Manual UPI
            // order - reserve stock. Idempotent: a COD order already
            // deducted at checkout, or a Manual UPI order already
            // verified via the Verify button, is a no-op here.
            if ($newPaymentStatus === 'paid') {
                apply_order_stock_deduction($order['id']);
            }

            flash_set('success', 'Payment status updated.');
        } else {
            flash_set('error', 'Invalid payment status selected.');
        }
    }

    redirect('order-detail.php?id=' . $orderId);
}

/* ==========================================
   SHIPPING / TRACKING UPDATE
   -------------------------------------------------
   Courier partner, AWB number and tracking URL are
   free-form text saved straight onto the order row.
   Shown to the customer once the order is shipped.
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shipping_update'])) {

    csrf_verify();

    $courierPartner = trim((string) ($_POST['courier_partner'] ?? ''));
    $awbNumber      = trim((string) ($_POST['awb_number'] ?? ''));
    $trackingUrl    = trim((string) ($_POST['tracking_url'] ?? ''));

    if (mb_strlen($courierPartner) > 100 || mb_strlen($awbNumber) > 100 || mb_strlen($trackingUrl) > 255) {
        flash_set('error', 'One of the tracking fields is too long.');
    } elseif (!is_safe_http_url($trackingUrl)) {
        flash_set('error', 'Tracking URL must be a valid http:// or https:// link.');
    } else {
        $stmt = db()->prepare(
            'UPDATE orders SET courier_partner = ?, awb_number = ?, tracking_url = ? WHERE id = ?'
        );
        $stmt->execute([
            $courierPartner !== '' ? $courierPartner : null,
            $awbNumber !== '' ? $awbNumber : null,
            $trackingUrl !== '' ? $trackingUrl : null,
            $orderId,
        ]);
        flash_set('success', 'Shipping & tracking details saved.');
    }

    redirect('order-detail.php?id=' . $orderId);
}

$transactionId = $paymentTransaction['gateway_payment_id']
    ?? $paymentTransaction['gateway_order_id']
    ?? null;

$errorMessage   = flash_get('error');
$successMessage = flash_get('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Order <?= h($order['order_number']) ?> | MoonAura Admin</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= versioned_asset('admin/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-body">

    <div class="admin-wrapper">

        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

        <div class="admin-main">

            <?php include __DIR__ . '/includes/admin-header.php'; ?>

            <div class="admin-content">

                <div class="admin-toolbar admin-nav-toolbar">
                    <a href="orders.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Orders
                    </a>
                </div>

                <?php if ($errorMessage): ?>
                    <div class="admin-alert admin-alert-error"><?= h($errorMessage) ?></div>
                <?php endif; ?>

                <?php if ($successMessage): ?>
                    <div class="admin-alert admin-alert-success"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <div class="admin-detail-grid">

                <div class="admin-form-card">

                    <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                        Order <?= h($order['order_number']) ?>
                    </h3>

                    <div class="admin-detail-row">
                        <span>Order Date</span>
                        <span><?= h(date('d M Y, h:i A', strtotime($order['created_at']))) ?></span>
                    </div>

                    <div class="admin-detail-row">
                        <span>Order Status</span>
                        <span>
                            <span id="order-status-badge" class="admin-badge admin-badge-<?= h($order['order_status']) ?>">
                                <?= h(ucfirst($order['order_status'])) ?>
                            </span>
                        </span>
                    </div>

                    <div class="admin-detail-row">
                        <span>Payment Method</span>
                        <span><?= h(payment_method_label($order['payment_method'])) ?></span>
                    </div>

                    <div class="admin-detail-row">
                        <span>Payment Status</span>
                        <span>
                            <span class="admin-badge admin-badge-<?= h($order['payment_status']) ?>">
                                <?= h(ucfirst($order['payment_status'])) ?>
                            </span>
                        </span>
                    </div>

                    <?php if ($transactionId): ?>
                        <div class="admin-detail-row">
                            <span>Transaction ID</span>
                            <span><?= h($transactionId) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="admin-detail-row">
                        <span>Invoice</span>
                        <span>
                            <?php if ($order['invoice_number']): ?>
                                <?= h($order['invoice_number']) ?> (<?= h(date('d M Y', strtotime($order['invoice_generated_at']))) ?>)
                                &middot;
                                <a href="invoice.php?id=<?= (int) $order['id'] ?>" target="_blank" rel="noopener">View Invoice</a>
                                &middot;
                                <a href="invoice.php?id=<?= (int) $order['id'] ?>&amp;mode=download" rel="noopener">Download</a>
                            <?php else: ?>
                                Not yet generated
                                &middot;
                                <a href="invoice.php?id=<?= (int) $order['id'] ?>" target="_blank" rel="noopener">Generate &amp; View</a>
                            <?php endif; ?>
                        </span>
                    </div>

                </div>

                <div class="admin-form-card">

                    <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                        Customer &amp; Shipping
                    </h3>

                    <div class="admin-detail-row">
                        <span>Name</span>
                        <span><?= h($order['customer_name']) ?></span>
                    </div>

                    <div class="admin-detail-row">
                        <span>Email</span>
                        <span><?= h($order['customer_email']) ?></span>
                    </div>

                    <div class="admin-detail-row">
                        <span>Phone</span>
                        <span><?= h($order['customer_phone']) ?></span>
                    </div>

                    <?php if ($orderAddress): ?>
                        <div class="admin-detail-row">
                            <span>Shipping Address</span>
                            <span>
                                <?= h($orderAddress['address_line1']) ?><?php if (!empty($orderAddress['address_line2'])): ?>, <?= h($orderAddress['address_line2']) ?><?php endif; ?><?php if (!empty($orderAddress['landmark'])): ?>, <?= h($orderAddress['landmark']) ?><?php endif; ?><br>
                                <?= h($orderAddress['city']) ?>, <?= h($orderAddress['state']) ?> - <?= h($orderAddress['postal_code']) ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="admin-detail-row">
                            <span>Shipping Address</span>
                            <span>No shipping address on file</span>
                        </div>
                    <?php endif; ?>

                </div>

                </div>

                <div class="admin-detail-grid" style="margin-top: 24px;">

                    <div class="admin-form-card">

                        <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                            Update Order Status
                        </h3>

                        <form method="post" id="order-status-form" data-order-id="<?= (int) $order['id'] ?>" data-ajax>
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_status_update" value="1">
                            <select name="order_status" id="order-status-select">
                                <option value="pending"    <?= $order['order_status'] === 'pending'    ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="shipped"    <?= $order['order_status'] === 'shipped'    ? 'selected' : '' ?>>Shipped</option>
                                <option value="delivered"  <?= $order['order_status'] === 'delivered'  ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled"  <?= $order['order_status'] === 'cancelled'  ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <div class="admin-form-actions">
                                <button type="submit" class="admin-btn-primary" id="order-status-submit">
                                    <i class="fa-solid fa-rotate"></i>
                                    Update Status
                                </button>
                            </div>
                            <div id="order-status-feedback" style="margin-top: 10px;" role="status" aria-live="polite" hidden></div>
                        </form>

                    </div>

                    <div class="admin-form-card">

                        <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                            Update Payment Status
                        </h3>

                        <?php if ($paymentStatusEditable): ?>

                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="payment_status_update" value="1">
                                <select name="payment_status">
                                    <option value="pending"  <?= $order['payment_status'] === 'pending'  ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid"     <?= $order['payment_status'] === 'paid'     ? 'selected' : '' ?>>Paid</option>
                                    <option value="failed"   <?= $order['payment_status'] === 'failed'   ? 'selected' : '' ?>>Failed</option>
                                    <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                </select>
                                <div class="admin-form-actions">
                                    <button type="submit" class="admin-btn-primary">
                                        <i class="fa-solid fa-rotate"></i>
                                        Update Payment
                                    </button>
                                </div>
                            </form>

                        <?php else: ?>

                            <p style="font-size: 13px; color: var(--text-light); line-height: 1.6; margin-top: 4px;">
                                Payment status for online gateway orders
                                (<?= h(payment_method_label($order['payment_method'])) ?>) is
                                managed by the gateway's own verification - it can't be
                                overridden from here.
                            </p>

                        <?php endif; ?>

                    </div>

                </div>

                <div class="admin-form-card" style="margin-top: 24px;">

                    <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                        Shipping &amp; Tracking
                    </h3>

                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="shipping_update" value="1">

                        <label for="courier_partner">Courier Partner</label>
                        <input
                            type="text"
                            id="courier_partner"
                            name="courier_partner"
                            maxlength="100"
                            placeholder="e.g. Delhivery, Blue Dart"
                            value="<?= h($order['courier_partner'] ?? '') ?>"
                        >

                        <label for="awb_number">AWB Number</label>
                        <input
                            type="text"
                            id="awb_number"
                            name="awb_number"
                            maxlength="100"
                            placeholder="Courier tracking / AWB number"
                            value="<?= h($order['awb_number'] ?? '') ?>"
                        >

                        <label for="tracking_url">Tracking URL</label>
                        <input
                            type="url"
                            id="tracking_url"
                            name="tracking_url"
                            maxlength="255"
                            placeholder="https://www.courier.com/track?awb=..."
                            value="<?= h($order['tracking_url'] ?? '') ?>"
                        >

                        <div class="admin-form-actions">
                            <button type="submit" class="admin-btn-primary">
                                <i class="fa-solid fa-truck-fast"></i>
                                Save Tracking Details
                            </button>
                        </div>
                    </form>

                </div>

                <?php if ($order['payment_method'] === 'manual_upi' && $paymentTransaction): ?>

                    <div class="admin-form-card" style="margin-top: 24px;">

                        <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                            Manual UPI Payment
                        </h3>

                        <div class="admin-detail-row">
                            <span>UTR Number</span>
                            <span><?= h($paymentTransaction['gateway_payment_id'] ?? 'Not provided') ?></span>
                        </div>

                        <div class="admin-detail-row">
                            <span>Submitted On</span>
                            <span><?= h(date('d M Y, h:i A', strtotime($paymentTransaction['created_at']))) ?></span>
                        </div>

                        <div class="admin-detail-row">
                            <span>Screenshot</span>
                            <span>
                                <?php if (!empty($paymentTransaction['screenshot_path'])): ?>
                                    <a href="<?= h(asset_url($paymentTransaction['screenshot_path'])) ?>" target="_blank" rel="noopener">View Screenshot</a>
                                <?php else: ?>
                                    Not provided
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if ($canReviewManualUpi): ?>

                            <div class="admin-form-actions">

                                <form method="post" onsubmit="return confirm('Mark this payment as verified and move the order to Processing?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="manual_upi_action" value="verify">
                                    <button type="submit" class="admin-btn-primary">
                                        <i class="fa-solid fa-check"></i>
                                        Verify Payment
                                    </button>
                                </form>

                                <form method="post" onsubmit="return confirm('Reject this payment reference? The order will be marked as payment Failed.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="manual_upi_action" value="reject">
                                    <button type="submit" class="admin-btn-danger">
                                        <i class="fa-solid fa-xmark"></i>
                                        Reject Payment
                                    </button>
                                </form>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

                <div class="admin-table-card" style="margin-top: 24px;">

                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>GST Rate</th>
                                <th>Line Total</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td><?= h($item['product_name']) ?></td>
                                    <td><?= (int) $item['quantity'] ?></td>
                                    <td><?= h(format_price((float) $item['unit_price'])) ?></td>
                                    <td><?= h(rtrim(rtrim(number_format((float) $item['gst_rate'], 2), '0'), '.')) ?>%</td>
                                    <td><?= h(format_price((float) $item['line_total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="admin-form-card" style="margin-top: 24px; max-width: 400px; margin-left: auto;">

                    <div class="admin-detail-row">
                        <span>Subtotal</span>
                        <span><?= h(format_price((float) $order['subtotal'])) ?></span>
                    </div>

                    <?php if ((float) $order['discount'] > 0): ?>
                        <div class="admin-detail-row">
                            <span>Discount</span>
                            <span>- <?= h(format_price((float) $order['discount'])) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="admin-detail-row">
                        <span>Shipping</span>
                        <span><?= h(format_price((float) $order['shipping_charge'])) ?></span>
                    </div>

                    <div class="admin-detail-row">
                        <span>GST (Included)</span>
                        <span><?= h(format_price((float) $order['gst_amount'])) ?></span>
                    </div>

                    <div class="admin-detail-row" style="font-size: 16px;">
                        <span>Grand Total</span>
                        <span style="color: var(--primary);"><?= h(format_price((float) $order['grand_total'])) ?></span>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <script src="<?= versioned_asset('admin/assets/js/admin-phase-c1.js', 'assets/js/admin-phase-c1.js') ?>" defer></script>

</body>
</html>
