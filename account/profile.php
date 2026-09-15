<?php
/* ===================================================================
   PROFILE UPDATE
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../includes/order-functions.php'; // is_valid_mobile_number()

require_customer_login();

$customer = current_customer();
$activeAccountPage = 'profile';

$errors = [];

$name  = $customer['name'];
$email = $customer['email'];
$phone = $customer['phone'];

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

    // Make sure this email/phone isn't already used by a DIFFERENT account.
    if (empty($errors)) {

        $stmt = db()->prepare(
            'SELECT id FROM customers WHERE (email = ? OR phone = ?) AND id != ? LIMIT 1'
        );
        $stmt->execute([$email, $phone, $customer['id']]);

        if ($stmt->fetch()) {
            $errors[] = 'That email or mobile number is already used by another account.';
        }
    }

    if (empty($errors)) {

        $stmt = db()->prepare('UPDATE customers SET name = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->execute([$name, $email, $phone, $customer['id']]);

        flash_set('success', 'Profile updated.');
        redirect('profile.php');
    }
}

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
    <title>Edit Profile | MoonAura Crystals</title>
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

                <h2>Edit Profile</h2>

                <?php if ($successMessage): ?>
                    <div class="account-alert account-alert-success"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="account-alert account-alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="profile.php">

                    <?= csrf_field() ?>

                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= h($name) ?>" required>

                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= h($email) ?>" required>

                    <label for="phone">Mobile Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= h($phone) ?>" required>

                    <button type="submit" class="btn btn-primary">Save Changes</button>

                </form>

            </div>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
