<?php
/* ===================================================================
   SAVED ADDRESSES - LIST
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../includes/customer-functions.php';

require_customer_login();

$customer = current_customer();
$activeAccountPage = 'addresses';

$addresses = get_customer_addresses((int) $customer['id']);

$successMessage = flash_get('success');
$errorMessage   = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Saved Addresses | MoonAura Crystals</title>
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

            <?php if ($successMessage): ?>
                <div class="account-alert account-alert-success"><?= h($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="account-alert account-alert-error"><?= h($errorMessage) ?></div>
            <?php endif; ?>

            <div class="account-card">

                <h2>Saved Addresses</h2>

                <p style="margin-bottom: 16px;">
                    <a href="address-form.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> Add Address
                    </a>
                </p>

                <?php if (empty($addresses)): ?>

                    <div class="account-empty">No saved addresses yet.</div>

                <?php else: ?>

                    <div class="account-address-grid">

                        <?php foreach ($addresses as $address): ?>

                            <div class="account-address-card">

                                <?php if ($address['is_default']): ?>
                                    <span class="account-badge account-badge-active">Default</span>
                                <?php endif; ?>

                                <div class="account-address-body">
                                <strong><?= h($address['full_name']) ?></strong><br>
                                <?= h($address['address_line1']) ?><br>
                                <?php if (!empty($address['address_line2'])): ?>
                                    <?= h($address['address_line2']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($address['landmark'])): ?>
                                    Landmark: <?= h($address['landmark']) ?><br>
                                <?php endif; ?>
                                <?= h($address['city']) ?>, <?= h($address['state']) ?> - <?= h($address['postal_code']) ?><br>
                                Phone: <?= h($address['phone']) ?>
                                </div>

                                <div class="account-address-actions">

                                    <a href="address-form.php?id=<?= (int) $address['id'] ?>" class="account-address-action">Edit</a>

                                    <form
                                        method="post"
                                        action="address-delete.php"
                                        onsubmit="return confirm('Delete this address?');"
                                    >
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $address['id'] ?>">
                                        <button type="submit" class="account-address-action">Delete</button>
                                    </form>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
