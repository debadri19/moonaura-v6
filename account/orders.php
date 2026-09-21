<?php
/* ===================================================================
   ORDER HISTORY
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';

require_customer_login();

$customer = current_customer();
$activeAccountPage = 'orders';

$stmt = db()->prepare(
    'SELECT order_number, created_at, grand_total, order_status, payment_status
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC'
);
$stmt->execute([$customer['id']]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php theme_boot(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>My Orders | MoonAura Crystals</title>
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

            <?php include __DIR__ . '/includes/account-nav.php'; ?>

            <div class="account-card">

                <h2>Order History</h2>

                <?php if (empty($orders)): ?>

                    <div class="account-empty">
                        You haven't placed any orders yet.
                        <br><br>
                        <a href="../shop.php" class="btn btn-primary">Start Shopping</a>
                    </div>

                <?php else: ?>

                    <div class="account-table-wrap">

                    <table class="account-table">

                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Order Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($orders as $order): ?>

                                <tr>
                                    <td data-label="Order #">
                                        <a href="order-detail.php?order=<?= urlencode($order['order_number']) ?>" class="account-order-id-link">
                                            <?= h($order['order_number']) ?>
                                        </a>
                                    </td>
                                    <td data-label="Date"><?= h(date('d M Y', strtotime($order['created_at']))) ?></td>
                                    <td data-label="Total"><?= h(format_price((float) $order['grand_total'])) ?></td>
                                    <td data-label="Order Status">
                                        <span class="account-badge account-badge-<?= h($order['order_status']) ?>">
                                            <?= h(ucfirst($order['order_status'])) ?>
                                        </span>
                                    </td>
                                    <td data-label="Payment">
                                        <span class="account-badge account-badge-<?= h($order['payment_status']) ?>">
                                            <?= h(ucfirst($order['payment_status'])) ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
