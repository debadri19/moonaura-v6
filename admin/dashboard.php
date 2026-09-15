<?php
/* ===================================================================
   ADMIN DASHBOARD
   -------------------------------------------------------------------
   Phase 3D: Products/Orders cards now show live counts and link to
   their real pages - both modules have been functional for a while
   (Phase 1A and Phase 3C), this page just hadn't been updated to
   reflect that. Customers is a live count of the existing
   customers table (admin list/detail pages).
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/analytics-functions.php';

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// Live counts for the cards below - Products and Orders have both
// been functional modules for a while now (Phase 1A and Phase 3C
// respectively); this dashboard just never caught up to say so. Kept
// to simple COUNT()s, matching how account/dashboard.php already
// does the same thing for a customer's own stats - no new query
// pattern introduced.
$productCount  = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$orderCount    = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$customerCount = (int) db()->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$visitorStats  = ga4_get_dashboard_visitor_stats();

// #38 Dashboard Live Order Panel: same columns/shape as
// admin/orders.php's list query, just LIMITed - this is the page's
// initial (no-JS) render; admin/dashboard-recent-orders.php re-runs
// the same query for the polling refresh below.
$recentOrdersStmt = db()->query(
    'SELECT id, order_number, customer_name, grand_total, order_status, created_at
     FROM orders
     ORDER BY created_at DESC
     LIMIT 8'
);
$recentOrders = $recentOrdersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Dashboard | MoonAura Admin</title>

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

                <div class="admin-placeholder-grid admin-dashboard-stats">

                    <a href="products.php" style="text-decoration: none; color: inherit;">
                        <div class="admin-placeholder-card">
                            <i class="fa-solid fa-gem"></i>
                            <h3>Products</h3>
                            <p><?= (int) $productCount ?> Products</p>
                        </div>
                    </a>

                    <a href="orders.php" style="text-decoration: none; color: inherit;">
                        <div class="admin-placeholder-card">
                            <i class="fa-solid fa-bag-shopping"></i>
                            <h3>Orders</h3>
                            <p><?= (int) $orderCount ?> Orders</p>
                        </div>
                    </a>

                    <a href="customers.php" style="text-decoration: none; color: inherit;">
                        <div class="admin-placeholder-card">
                            <i class="fa-solid fa-users"></i>
                            <h3>Customers</h3>
                            <p><?= (int) $customerCount ?> Customers</p>
                        </div>
                    </a>

                    <div class="admin-placeholder-card admin-visitors-card">
                        <i class="fa-solid fa-chart-line"></i>
                        <h3>Visitors</h3>
                        <div class="admin-visitors-split">
                            <div class="admin-visitors-stat">
                                <span class="admin-visitors-label">Current:</span>
                                <span class="admin-visitors-value">
                                    <?php if ($visitorStats['current_status'] === 'ok'): ?>
                                        <?= (int) $visitorStats['current'] ?>
                                    <?php else: ?>
                                        <?= h($visitorStats['current_status']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="admin-visitors-stat">
                                <span class="admin-visitors-label">Total:</span>
                                <span class="admin-visitors-value">
                                    <?php if ($visitorStats['total_status'] === 'ok'): ?>
                                        <?= (int) $visitorStats['total'] ?>
                                    <?php else: ?>
                                        <?= h($visitorStats['total_status']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ==========================================================
                     #38 DASHBOARD LIVE ORDER PANEL
                     ----------------------------------------------------------
                     Server-rendered on load (works with JS disabled). The
                     tbody below then gets a lightweight polling refresh from
                     admin/dashboard-recent-orders.php (see admin-dashboard.js)
                     - same table markup/classes as admin/orders.php, so no
                     new table styling was introduced.
                =========================================================== -->
                <div class="admin-table-card admin-recent-orders-card">

                    <div class="admin-recent-orders-head">
                        <a href="orders.php" class="admin-btn-secondary">View All Orders</a>
                    </div>

                    <table class="admin-table" id="recent-orders-table">

                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <!-- data-notification-sound: resolved through asset_url()
                             so the sound follows the same SITE_URL/base-path
                             handling as every other asset - never hard-coded. -->
                        <tbody id="recent-orders-tbody"
                               data-notification-sound="<?= asset_url('admin/assets/sounds/order-notification.mp3') ?>">

                            <?php if (empty($recentOrders)): ?>

                                <tr>
                                    <td colspan="6" class="admin-table-empty">No orders yet.</td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($recentOrders as $order): ?>

                                    <tr data-order-id="<?= (int) $order['id'] ?>">
                                        <td><code><?= h($order['order_number']) ?></code></td>
                                        <td><?= h($order['customer_name']) ?></td>
                                        <td><?= h(format_price((float) $order['grand_total'])) ?></td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= h($order['order_status']) ?>">
                                                <?= h(ucfirst($order['order_status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= h(date('d M Y, h:i A', strtotime($order['created_at']))) ?></td>
                                        <td class="admin-table-actions">
                                            <a href="order-detail.php?id=<?= (int) $order['id'] ?>" title="View Details">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

    <script src="<?= versioned_asset('admin/assets/js/admin-dashboard.js', 'assets/js/admin-dashboard.js') ?>" defer></script>

</body>
</html>
