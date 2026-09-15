<?php
/* ===================================================================
   ACCOUNT DASHBOARD
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';

require_customer_login();

$customer = current_customer();
$activeAccountPage = 'dashboard';

$memberSince = '';
if (!empty($customer['id'])) {
    $stmt = db()->prepare('SELECT created_at FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $customer['id']]);
    $createdAt = $stmt->fetchColumn();
    if ($createdAt) {
        $memberSince = date('M Y', strtotime((string) $createdAt));
    }
}

$stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$stmt->execute([$customer['id']]);
$orderCount = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?');
$stmt->execute([$customer['id']]);
$addressCount = (int) $stmt->fetchColumn();

$stmt = db()->prepare(
    'SELECT order_number, created_at, grand_total, order_status
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 3'
);
$stmt->execute([$customer['id']]);
$recentOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>My Account | MoonAura Crystals</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/account.css') ?>">
</head>
<body>

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <section class="account-page">

        <div class="container">

            <h1>My Account</h1>

            <div class="account-welcome-card">
                <div class="account-welcome-copy">
                    <h2>Welcome, <?= h($customer['name']) ?></h2>
                    <?php if ($memberSince !== ''): ?>
                        <p class="account-welcome-meta">Member Since <?= h($memberSince) ?></p>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="account-welcome-logout" aria-label="Log out" title="Log out">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>

            <nav class="account-nav-dropdown">
                <details class="account-nav-menu">
                    <summary class="account-nav-toggle">
                        Dashboard
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </summary>
                    <div class="account-nav-panel">
                        <a href="dashboard.php" class="active">Dashboard</a>
                        <a href="orders.php">Orders</a>
                        <a href="addresses.php">Saved Addresses</a>
                        <a href="profile.php">Profile</a>
                        <a href="change-password.php">Change Password</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </details>
            </nav>

            <?php if (!empty($recentOrders)): ?>

                <div class="account-recent-orders">

                    <h2>Recent Orders</h2>

                    <div class="account-order-cards">

                        <?php foreach ($recentOrders as $recentOrder): ?>
                            <div class="account-order-card">
                                <a href="order-detail.php?order=<?= urlencode($recentOrder['order_number']) ?>" class="account-order-card-number">
                                    <?= h($recentOrder['order_number']) ?>
                                </a>
                                <p class="account-order-card-date">
                                    <?= h(date('d M Y, h:i A', strtotime($recentOrder['created_at']))) ?>
                                </p>
                                <div class="account-order-card-footer">
                                    <span class="account-order-card-total"><?= h(format_price((float) $recentOrder['grand_total'])) ?></span>
                                    <span class="account-badge account-badge-<?= h($recentOrder['order_status']) ?>">
                                        <?= h(ucfirst($recentOrder['order_status'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>

                    <?php if ($orderCount > 3): ?>
                        <p class="account-recent-orders-more">
                            <a href="orders.php" class="btn btn-text">View All Orders</a>
                        </p>
                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
