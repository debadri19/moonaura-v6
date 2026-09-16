<?php
/* ===================================================================
   ADMIN - CREATE CUSTOMER PROFILE
   -------------------------------------------------------------------
   Dedicated profile form. Creates a customers row with the same
   name / email / phone fields as storefront registration.
   Does not open Create Order or the offline-order flow.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/customer-functions.php';

require_admin_login();

$admin      = current_admin();
$activePage = 'customers';
$pageTitle  = 'Create Customer Profile';

$errors = [];

$name  = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $name  = trim($_POST['name'] ?? '');
    $email = normalize_email(trim($_POST['email'] ?? ''));
    $phone = normalize_mobile_number(trim($_POST['phone'] ?? ''));

    if ($name === '') {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!is_valid_mobile_number($phone)) {
        $errors[] = 'Please enter a valid 10-digit mobile number.';
    }

    if (empty($errors)) {

        $stmt = db()->prepare('SELECT id FROM customers WHERE email = ? OR phone = ? LIMIT 1');
        $stmt->execute([$email, $phone]);

        if ($stmt->fetch()) {
            $errors[] = 'An account with that email or mobile number already exists.';
        }
    }

    if (empty($errors)) {

        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $stmt = db()->prepare(
            'INSERT INTO customers (name, email, phone, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $phone, $passwordHash]);

        $customerId = (int) db()->lastInsertId();

        flash_set('success', 'Customer profile created. Send a password reset link from the profile when they need to sign in.');
        redirect('customer-view.php?id=' . $customerId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Create Customer Profile | MoonAura Admin</title>

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

                <?php if (!empty($errors)): ?>
                    <div class="admin-alert admin-alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="admin-toolbar admin-toolbar-end admin-product-form-toolbar">
                    <a href="customers.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Customers
                    </a>
                    <button type="submit" form="admin-customer-create-form" class="admin-btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Create Customer Profile
                    </button>
                </div>

                <div class="admin-form-card">

                    <form
                        method="post"
                        action="customer-create.php"
                        id="admin-customer-create-form"
                    >

                        <?= csrf_field() ?>

                        <label for="name">Full Name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= h($name) ?>"
                            required
                        >

                        <label for="email">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= h($email) ?>"
                            required
                        >

                        <label for="phone">Mobile Number</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="<?= h($phone) ?>"
                            required
                        >

                        <p class="admin-field-hint">
                            No password is set here. Use Send Password Reset Link on the customer profile so they can choose one.
                        </p>

                    </form>

                </div>

            </div>

        </div>

    </div>

</body>
</html>
