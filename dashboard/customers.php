<?php
/* ===================================================================
   ADMIN - CUSTOMER LIST
   -------------------------------------------------------------------
   List of existing customer accounts (customers table).
   Search, pagination, and a link through to customer-view.php.
   Create Customer Profile is a dedicated admin page - it does not
   open Create Order or the offline-order flow.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Customers';
$activePage = 'customers';

$successMessage = flash_get('success');
$errorMessage   = flash_get('error');

$search = trim($_GET['search'] ?? '');
$page   = (int) ($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}

$perPage = 20;

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

$countSql = 'SELECT COUNT(*) FROM customers c' . $whereSql;
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$totalCustomers = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalCustomers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = 'SELECT
            c.id,
            c.name,
            c.email,
            c.phone,
            c.status,
            c.created_at,
            COUNT(o.id) AS total_orders,
            COALESCE(SUM(o.grand_total), 0) AS lifetime_spend
        FROM customers c
        LEFT JOIN orders o ON o.user_id = c.id'
        . $whereSql . '
        GROUP BY c.id, c.name, c.email, c.phone, c.status, c.created_at
        ORDER BY c.created_at DESC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$listQuery = [];
if ($search !== '') {
    $listQuery['search'] = $search;
}

function customers_query_string(array $base, array $extra = []): string
{
    $merged = array_merge($base, $extra);
    return $merged === [] ? '' : ('?' . http_build_query($merged));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Customers | MoonAura Admin</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= versioned_asset('dashboard/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-body">

    <div class="admin-wrapper">

        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

        <div class="admin-main">

            <?php include __DIR__ . '/includes/admin-header.php'; ?>

            <div class="admin-content">

                <?php if ($successMessage): ?>
                    <div class="admin-alert admin-alert-success"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="admin-alert admin-alert-error"><?= h($errorMessage) ?></div>
                <?php endif; ?>

                <div class="admin-toolbar admin-orders-toolbar">

                    <form method="get" action="customers.php" class="admin-search-form">
                        <input
                            type="text"
                            name="search"
                            value="<?= h($search) ?>"
                            placeholder="Search by name, email, or mobile..."
                        >
                        <button type="submit" class="admin-btn-secondary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <?php if ($search !== ''): ?>
                            <a href="customers.php" class="admin-btn-secondary">Clear</a>
                        <?php endif; ?>
                    </form>

                    <div class="admin-orders-actions">
                        <span style="font-size: 14px; color: var(--text-light);">
                            <?= $totalCustomers ?> customer<?= $totalCustomers === 1 ? '' : 's' ?>
                        </span>

                        <a href="customer-create.php" class="admin-btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Create Customer Profile
                        </a>
                    </div>

                </div>

                <div class="admin-table-card">

                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Registered</th>
                                <th>Orders</th>
                                <th>Lifetime Spend</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($customers)): ?>

                                <tr>
                                    <td colspan="8" class="admin-table-empty">
                                        <?= $search !== '' ? 'No customers match your search.' : 'No customers yet.' ?>
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($customers as $customer): ?>

                                    <tr>
                                        <td><?= h($customer['name']) ?></td>
                                        <td><?= h($customer['email']) ?></td>
                                        <td><?= h($customer['phone']) ?></td>
                                        <td><?= h(date('d M Y', strtotime($customer['created_at']))) ?></td>
                                        <td><?= (int) $customer['total_orders'] ?></td>
                                        <td><?= h(format_price((float) $customer['lifetime_spend'])) ?></td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= h($customer['status']) ?>">
                                                <?= h(ucfirst($customer['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="admin-table-actions">
                                            <a href="customer-view.php?id=<?= (int) $customer['id'] ?>" title="View">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                    <?php if ($totalCustomers > $perPage): ?>
                        <div class="admin-pagination">
                            <span style="font-size: 13px; color: var(--text-light);">
                                Page <?= $page ?> of <?= $totalPages ?>
                            </span>
                            <div class="admin-pagination-links">
                                <?php if ($page > 1): ?>
                                    <a href="customers.php<?= h(customers_query_string($listQuery, ['page' => $page - 1])) ?>">Previous</a>
                                <?php endif; ?>
                                <?php for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++): ?>
                                    <?php if ($pageNum === $page): ?>
                                        <span class="active"><?= $pageNum ?></span>
                                    <?php else: ?>
                                        <a href="customers.php<?= h(customers_query_string($listQuery, ['page' => $pageNum])) ?>"><?= $pageNum ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="customers.php<?= h(customers_query_string($listQuery, ['page' => $page + 1])) ?>">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</body>
</html>
