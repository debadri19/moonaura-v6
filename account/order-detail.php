<?php
/* ===================================================================
   ORDER DETAIL
   -------------------------------------------------------------------
   Always queries WHERE user_id = <this customer> AND order_number = ?
   together - never looks up an order by number alone - so a customer
   can never view another customer's order by editing the URL.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order-functions.php'; // timeline_event_label(), update_order_status()
require_once __DIR__ . '/../includes/customer-auth.php';

require_customer_login();

$customer = current_customer();
$activeAccountPage = 'orders';

$orderNumber = trim($_GET['order'] ?? '');

$stmt = db()->prepare(
    'SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$orderNumber, $customer['id']]);
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

// Order timeline - real stored records only (see
// order_status_history / log_order_status_event). Pre-migration
// orders that have no history simply show the empty state below.
$stmt = db()->prepare(
    'SELECT order_status, note, created_at
     FROM order_status_history
     WHERE order_id = ?
     ORDER BY id ASC'
);
$stmt->execute([$order['id']]);
$timelineEvents = $stmt->fetchAll();

// Tracking details are only relevant once the order has actually
// shipped (or been delivered).
$showTracking = in_array($order['order_status'], ['shipped', 'delivered'], true);

$errorMessage = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Order <?= h($order['order_number']) ?> | MoonAura Crystals</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/account.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/checkout.css') ?>">
</head>
<body>

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <section class="account-page">

        <div class="container">

            <h1>My Account</h1>

            <div class="account-order-actions">
                <?php include __DIR__ . '/includes/account-nav.php'; ?>
                <a href="orders.php" class="account-back-to-orders">
                    <i class="fa-solid fa-arrow-left"></i> Back to Orders
                </a>
            </div>

            <?php if ($errorMessage): ?>
                <div class="account-alert account-alert-error"><?= h($errorMessage) ?></div>
            <?php endif; ?>

            <div class="checkout-layout">

                <div class="checkout-form-card">

                    <div class="account-order-heading">
                        <p class="account-order-heading-number">Order <?= h($order['order_number']) ?></p>
                        <p class="account-order-heading-placed">Placed <?= h(date('d M Y, h:i A', strtotime($order['created_at']))) ?></p>
                    </div>

                    <h2>Item Details</h2>

                    <?php foreach ($orderItems as $orderItem): ?>

                        <div class="checkout-summary-item">

                            <div class="checkout-summary-item-name">
                                <?= h($orderItem['product_name']) ?>
                                <div class="checkout-summary-item-qty">
                                    Qty: <?= (int) $orderItem['quantity'] ?> &times; <?= h(format_price((float) $orderItem['unit_price'])) ?>
                                </div>
                            </div>

                            <div class="checkout-summary-item-total">
                                <?= h(format_price((float) $orderItem['line_total'])) ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                    <?php if ($orderAddress): ?>

                        <h2 style="margin-top: 24px;">Delivery Address</h2>

                        <p style="font-size: 14px; color: var(--text-light); line-height: 1.7;">
                            <?= h($orderAddress['full_name']) ?><br>
                            <?= h($orderAddress['address_line1']) ?><br>
                            <?php if (!empty($orderAddress['address_line2'])): ?>
                                <?= h($orderAddress['address_line2']) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($orderAddress['landmark'])): ?>
                                Landmark: <?= h($orderAddress['landmark']) ?><br>
                            <?php endif; ?>
                            <?= h($orderAddress['city']) ?>, <?= h($orderAddress['state']) ?> - <?= h($orderAddress['postal_code']) ?><br>
                            Phone: <?= h($orderAddress['phone']) ?>
                        </p>

                    <?php endif; ?>

                    <h2 style="margin-top: 24px;">Invoice</h2>

                    <?php if ($order['invoice_number']): ?>
                        <p style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">
                            Invoice <?= h($order['invoice_number']) ?> &middot; generated
                            <?= h(date('d M Y', strtotime($order['invoice_generated_at']))) ?>
                        </p>
                    <?php endif; ?>

                    <div class="account-invoice-actions">
                        <a href="invoice.php?order=<?= urlencode($order['order_number']) ?>&mode=download" class="btn btn-primary">
                            Download Invoice
                        </a>
                        <a href="invoice.php?order=<?= urlencode($order['order_number']) ?>&mode=print" class="btn btn-outline" target="_blank" rel="noopener">
                            Print Invoice
                        </a>
                    </div>

                    <?php if (!$order['invoice_number']): ?>
                        <p style="font-size: 13px; color: var(--text-light); margin-top: 8px;">
                            Your invoice will be generated automatically when you download or print it for the first time.
                        </p>
                    <?php endif; ?>

                </div>

                <div class="checkout-summary">

                    <h2>Order Status</h2>

                    <div class="checkout-summary-row">
                        <span>Order Status</span>
                        <span class="account-badge account-badge-<?= h($order['order_status']) ?>">
                            <?= h(ucfirst($order['order_status'])) ?>
                        </span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>Payment Status</span>
                        <span class="account-badge account-badge-<?= h($order['payment_status']) ?>">
                            <?= h(ucfirst($order['payment_status'])) ?>
                        </span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>Payment Method</span>
                        <span><?= h(payment_method_label((string) $order['payment_method'])) ?></span>
                    </div>

                    <h2 style="margin-top: 20px;">Payment Details</h2>

                    <div class="checkout-summary-row">
                        <span>Subtotal</span>
                        <span><?= h(format_price((float) $order['subtotal'])) ?></span>
                    </div>

                    <?php if ((float) $order['discount'] > 0): ?>
                        <div class="checkout-summary-row">
                            <span>Discount</span>
                            <span>- <?= h(format_price((float) $order['discount'])) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="checkout-summary-row">
                        <span>Shipping</span>
                        <span><?= h(format_price((float) $order['shipping_charge'])) ?></span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>GST (Included)</span>
                        <span><?= h(format_price((float) $order['gst_amount'])) ?></span>
                    </div>

                    <div class="checkout-summary-row checkout-summary-total">
                        <span>Grand Total</span>
                        <span><?= h(format_price((float) $order['grand_total'])) ?></span>
                    </div>

                </div>

            </div>

            <div class="account-timeline">

                <h2>Order Timeline</h2>

                <?php if (!empty($timelineEvents)): ?>

                    <div class="timeline">

                        <?php foreach ($timelineEvents as $event): ?>

                            <div class="timeline-item">

                                <span class="timeline-dot" aria-hidden="true"></span>

                                <div class="timeline-content">

                                    <span class="timeline-title">
                                        <?= h(timeline_event_label($event['order_status'])) ?>
                                    </span>

                                    <?php if (!empty($event['note'])): ?>
                                        <span class="timeline-note">
                                            <?= h($event['note']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <span class="timeline-date">
                                        <?= h(date('d M Y, h:i A', strtotime($event['created_at']))) ?>
                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p style="font-size: 14px; color: var(--text-light);">
                        No status updates recorded for this order yet.
                    </p>

                <?php endif; ?>

            </div>

            <?php if ($showTracking): ?>

                <div class="account-tracking">

                    <h2>Shipping &amp; Tracking</h2>

                    <div class="checkout-summary-row">
                        <span>Courier Partner</span>
                        <span><?= !empty($order['courier_partner']) ? h($order['courier_partner']) : 'Not provided' ?></span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>AWB Number</span>
                        <span><?= !empty($order['awb_number']) ? h($order['awb_number']) : 'Not provided' ?></span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>Tracking URL</span>
                        <span>
                            <?php if (!empty($order['tracking_url'])): ?>
                                <a href="<?= h($order['tracking_url']) ?>" target="_blank" rel="noopener">
                                    Track your order <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </span>
                    </div>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
