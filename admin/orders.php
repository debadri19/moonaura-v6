<?php
/* ===================================================================
   ADMIN - ORDER LIST
   -------------------------------------------------------------------
   Phase 3C: the Orders sidebar link was disabled (see
   admin/includes/admin-sidebar.php) because this page simply didn't
   exist yet - nothing else was blocking it. This is a READ-ONLY view
   of orders already created by checkout.php - it does not add order
   editing, status changes, refunds, or shipping actions. Those are
   deliberately left to the "Order Timeline"/"Shipping Integration"
   phases already listed as not-yet-scoped in PROJECT_STATE.md - this
   page only makes the existing order data visible to admin, matching
   the task's "implement only the missing pieces required to make it
   functional" scope.

   Same list-page pattern as admin/products.php (search box, plain
   table, no pagination - products.php doesn't paginate either, so
   this doesn't introduce a new convention).

   Phase 3D: added an order-status filter dropdown alongside the
   existing search box (combined with AND when both are set), and a
   result-count line - still the same list-page pattern, no new
   architecture.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Orders';
$activePage = 'orders';

$search       = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Only allow a real order_status value through - anything else is
// treated as "no filter" rather than passed into the query.
$validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}


/* ==========================================
   FETCH ORDERS, optionally filtered by a
   search term on order number, customer name,
   customer email, or customer phone, and/or by
   order status.
========================================== */

$sql = 'SELECT id, order_number, customer_name, customer_email, customer_phone,
               grand_total, payment_method, payment_status, order_status, created_at
        FROM orders';

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $where[]  = 'order_status = ?';
    $params[] = $statusFilter;
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Orders | MoonAura Admin</title>

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

                <div class="admin-toolbar admin-orders-toolbar">

                    <form method="get" action="orders.php" class="admin-search-form">
                        <input
                            type="text"
                            name="search"
                            value="<?= h($search) ?>"
                            placeholder="Search by order #, customer name, email, or phone..."
                        >
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach ($validStatuses as $statusOption): ?>
                                <option value="<?= h($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>>
                                    <?= h(ucfirst($statusOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="admin-btn-secondary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <?php if ($search !== '' || $statusFilter !== ''): ?>
                            <a href="orders.php" class="admin-btn-secondary">Clear</a>
                        <?php endif; ?>
                    </form>

                    <div class="admin-orders-actions">
                        <span style="font-size: 14px; color: var(--text-light);">
                            <?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?>
                        </span>

                        <a href="order-create.php" class="admin-btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Create Order
                        </a>
                    </div>

                </div>

                <div class="admin-table-card">

                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Order Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($orders)): ?>

                                <tr>
                                    <td colspan="7" class="admin-table-empty">
                                        <?= ($search !== '' || $statusFilter !== '') ? 'No orders match your search/filter.' : 'No orders yet.' ?>
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($orders as $order): ?>

                                    <tr>
                                        <td><code><?= h($order['order_number']) ?></code></td>
                                        <td>
                                            <?= h($order['customer_name']) ?>
                                            <span class="admin-table-subtext">
                                                <?= h($order['customer_email']) ?>
                                            </span>
                                        </td>
                                        <td><?= h(date('d M Y, h:i A', strtotime($order['created_at']))) ?></td>
                                        <td><?= h(format_price((float) $order['grand_total'])) ?></td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= h($order['payment_status']) ?>">
                                                <?= h(ucfirst($order['payment_status'])) ?>
                                            </span>
                                            <span class="admin-table-subtext">
                                                <?= h(payment_method_label((string) $order['payment_method'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= h($order['order_status']) ?>">
                                                <?= h(ucfirst($order['order_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="admin-table-actions">
                                            <a href="order-detail.php?id=<?= (int) $order['id'] ?>" title="View">
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

</body>
</html>
