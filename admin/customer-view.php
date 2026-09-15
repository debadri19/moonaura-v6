<?php
/* ===================================================================
   ADMIN - CUSTOMER DETAIL
   -------------------------------------------------------------------
   Profile, saved addresses, and order history for one customer
   account. Orders are the existing orders rows linked by user_id.
   Password reset reuses the storefront forgot-password helpers and
   never shows the token or reset URL in admin.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/customer-functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email-templates.php';

require_admin_login();

$admin      = current_admin();
$activePage = 'customers';

$customerId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT id, name, email, phone, status, created_at
     FROM customers
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect('customers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_password_reset') {

    csrf_verify();

    if (($customer['status'] ?? '') !== 'active') {
        flash_set('error', 'Password reset links can only be sent to active customers.');
        redirect('customer-view.php?id=' . (int) $customer['id']);
    }

    if (!mail_is_configured()) {
        flash_set('error', 'Could not send the password reset email. Please try again later.');
        redirect('customer-view.php?id=' . (int) $customer['id']);
    }

    $rawToken  = create_customer_reset_token((int) $customer['id']);
    $resetLink = site_url('account/reset-password.php?token=' . $rawToken);
    $message   = customer_password_reset_email($resetLink);
    $sent      = send_email(
        $customer['email'],
        $customer['name'],
        $message['subject'],
        $message['html'],
        $message['text']
    );

    if ($sent) {
        flash_set('success', 'Password reset link sent to the customer email.');
    } else {
        flash_set('error', 'Could not send the password reset email. Please try again later.');
    }

    redirect('customer-view.php?id=' . (int) $customer['id']);
}

$pageTitle = $customer['name'];

$successMessage = flash_get('success');
$errorMessage   = flash_get('error');

$stmt = db()->prepare(
    'SELECT
         COUNT(*) AS total_orders,
         COALESCE(SUM(grand_total), 0) AS lifetime_spend,
         MAX(created_at) AS last_order_at
     FROM orders
     WHERE user_id = ?'
);
$stmt->execute([$customer['id']]);
$summary = $stmt->fetch() ?: [
    'total_orders'    => 0,
    'lifetime_spend'  => 0,
    'last_order_at'   => null,
];

$addresses = get_customer_addresses((int) $customer['id']);

$stmt = db()->prepare(
    'SELECT id, order_number, created_at, grand_total, payment_status, order_status
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title><?= h($customer['name']) ?> | MoonAura Admin</title>

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

                <?php if ($successMessage): ?>
                    <div class="admin-alert admin-alert-success"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="admin-alert admin-alert-error"><?= h($errorMessage) ?></div>
                <?php endif; ?>

                <div class="admin-toolbar admin-toolbar-end admin-nav-toolbar">
                    <a href="customers.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Customers
                    </a>
                    <a href="orders.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-bag-shopping"></i>
                        Back to Orders
                    </a>
                    <form method="post" action="customer-view.php?id=<?= (int) $customer['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_password_reset">
                        <button
                            type="submit"
                            class="admin-btn-primary"
                            <?= ($customer['status'] ?? '') !== 'active' ? 'disabled title="Password reset links can only be sent to active customers."' : '' ?>
                        >
                            <i class="fa-solid fa-envelope"></i>
                            Send Password Reset Link
                        </button>
                    </form>
                </div>

                <div class="admin-detail-grid">

                    <div class="admin-form-card">

                        <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                            Profile
                        </h3>

                        <div class="admin-detail-row">
                            <span>Name</span>
                            <span><?= h($customer['name']) ?></span>
                        </div>

                        <div class="admin-detail-row">
                            <span>Email</span>
                            <span><?= h($customer['email']) ?></span>
                        </div>

                        <div class="admin-detail-row">
                            <span>Mobile</span>
                            <span><?= h($customer['phone']) ?></span>
                        </div>

                        <div class="admin-detail-row">
                            <span>Registration Date</span>
                            <span><?= h(date('d M Y, h:i A', strtotime($customer['created_at']))) ?></span>
                        </div>

                        <div class="admin-detail-row">
                            <span>Status</span>
                            <span>
                                <span class="admin-badge admin-badge-<?= h($customer['status']) ?>">
                                    <?= h(ucfirst($customer['status'])) ?>
                                </span>
                            </span>
                        </div>

                    </div>

                    <div class="admin-form-card">

                        <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                            Summary
                        </h3>

                        <div class="admin-detail-row">
                            <span>Total Orders</span>
                            <span><?= (int) $summary['total_orders'] ?></span>
                        </div>

                        <div class="admin-detail-row">
                            <span>Lifetime Spend</span>
                            <span><?= h(format_price((float) $summary['lifetime_spend'])) ?></span>
                        </div>

                        <div class="admin-detail-row">
                            <span>Last Order Date</span>
                            <span>
                                <?= !empty($summary['last_order_at'])
                                    ? h(date('d M Y, h:i A', strtotime($summary['last_order_at'])))
                                    : '—' ?>
                            </span>
                        </div>

                    </div>

                </div>

                <div class="admin-form-card" style="margin-top: 24px;">

                    <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">
                        Addresses
                    </h3>

                    <?php if (empty($addresses)): ?>

                        <p style="font-size: 14px; color: var(--text-light); margin: 0;">
                            No saved addresses.
                        </p>

                    <?php else: ?>

                        <?php foreach ($addresses as $address): ?>

                            <div class="admin-form-card" style="margin: 0 0 16px; box-shadow: none;">

                                <?php if (!empty($address['is_default'])): ?>
                                    <span class="admin-badge admin-badge-active" style="margin-bottom: 8px;">Default</span>
                                <?php endif; ?>

                                <div class="admin-detail-row">
                                    <span>Address Line 1</span>
                                    <span><?= h($address['address_line1']) ?></span>
                                </div>

                                <div class="admin-detail-row">
                                    <span>Address Line 2</span>
                                    <span><?= h($address['address_line2'] ?? '') !== '' ? h($address['address_line2']) : '—' ?></span>
                                </div>

                                <div class="admin-detail-row">
                                    <span>Landmark</span>
                                    <span><?= h($address['landmark'] ?? '') !== '' ? h($address['landmark']) : '—' ?></span>
                                </div>

                                <div class="admin-detail-row">
                                    <span>City</span>
                                    <span><?= h($address['city']) ?></span>
                                </div>

                                <div class="admin-detail-row">
                                    <span>State</span>
                                    <span><?= h($address['state']) ?></span>
                                </div>

                                <div class="admin-detail-row">
                                    <span>Pincode</span>
                                    <span><?= h($address['postal_code']) ?></span>
                                </div>

                                <div class="admin-detail-row">
                                    <span>Country</span>
                                    <span><?= h($address['country']) ?></span>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

                <div class="admin-table-card" style="margin-top: 24px;" id="customer-orders">

                    <h3 style="margin: 16px 16px 0; color: var(--primary);">
                        Order History
                    </h3>

                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Payment Status</th>
                                <th>Order Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($orders)): ?>

                                <tr>
                                    <td colspan="6" class="admin-table-empty">
                                        No orders linked to this customer.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($orders as $order): ?>

                                    <tr>
                                        <td>
                                            <a href="order-detail.php?id=<?= (int) $order['id'] ?>">
                                                <code><?= h($order['order_number']) ?></code>
                                            </a>
                                        </td>
                                        <td><?= h(date('d M Y, h:i A', strtotime($order['created_at']))) ?></td>
                                        <td><?= h(format_price((float) $order['grand_total'])) ?></td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= h($order['payment_status']) ?>">
                                                <?= h(ucfirst($order['payment_status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= h($order['order_status']) ?>">
                                                <?= h(ucfirst($order['order_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="admin-table-actions">
                                            <a href="order-detail.php?id=<?= (int) $order['id'] ?>" title="View order">
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
