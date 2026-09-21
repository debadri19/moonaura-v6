<?php
/* ===================================================================
   SAVED ADDRESS - ADD / EDIT
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../includes/customer-functions.php';
require_once __DIR__ . '/../includes/order-functions.php'; // is_valid_mobile_number(), is_valid_pin_code()

require_customer_login();

$customer = current_customer();
$activeAccountPage = 'addresses';

$addressId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEditing = $addressId !== null;

$errors = [];

$address = [
    'full_name'     => $customer['name'],
    'phone'         => $customer['phone'],
    'address_line1' => '',
    'address_line2' => '',
    'landmark'      => '',
    'city'          => '',
    'state'         => '',
    'postal_code'   => '',
    'is_default'    => 0,
];

if ($isEditing) {

    $found = get_customer_address((int) $customer['id'], $addressId);

    if (!$found) {
        flash_set('error', 'Address not found.');
        redirect('addresses.php');
    }

    $address = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $address['full_name']     = trim($_POST['full_name'] ?? '');
    $address['phone']         = normalize_mobile_number(trim($_POST['phone'] ?? ''));
    $address['address_line1'] = trim($_POST['address_line1'] ?? '');
    $address['address_line2'] = trim($_POST['address_line2'] ?? '');
    $address['landmark']      = trim($_POST['landmark'] ?? '');
    $address['city']          = trim($_POST['city'] ?? '');
    $address['state']         = trim($_POST['state'] ?? '');
    $address['postal_code']   = trim($_POST['postal_code'] ?? '');
    $address['is_default']    = isset($_POST['is_default']) ? 1 : 0;

    if ($address['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }

    if (!is_valid_mobile_number($address['phone'])) {
        $errors[] = 'Please enter a valid 10-digit mobile number.';
    }

    if ($address['address_line1'] === '') {
        $errors[] = 'Address Line 1 is required.';
    }

    if ($address['city'] === '') {
        $errors[] = 'City is required.';
    }

    if ($address['state'] === '') {
        $errors[] = 'State is required.';
    }

    if (!is_valid_pin_code($address['postal_code'])) {
        $errors[] = 'Please enter a valid 6-digit PIN code.';
    }

    if (empty($errors)) {

        if ($address['is_default']) {
            clear_other_default_addresses((int) $customer['id'], $isEditing ? $addressId : null);
        }

        if ($isEditing) {

            $stmt = db()->prepare(
                'UPDATE customer_addresses SET
                    full_name = ?, phone = ?, address_line1 = ?, address_line2 = ?, landmark = ?,
                    city = ?, state = ?, postal_code = ?, is_default = ?
                 WHERE id = ? AND customer_id = ?'
            );
            $stmt->execute([
                $address['full_name'], $address['phone'], $address['address_line1'], $address['address_line2'], $address['landmark'],
                $address['city'], $address['state'], $address['postal_code'], $address['is_default'],
                $addressId, $customer['id'],
            ]);

            flash_set('success', 'Address updated.');

        } else {

            $stmt = db()->prepare(
                'INSERT INTO customer_addresses (
                    customer_id, full_name, phone, address_line1, address_line2, landmark,
                    city, state, postal_code, is_default
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $customer['id'], $address['full_name'], $address['phone'], $address['address_line1'], $address['address_line2'], $address['landmark'],
                $address['city'], $address['state'], $address['postal_code'], $address['is_default'],
            ]);

            flash_set('success', 'Address added.');
        }

        redirect('addresses.php');
    }
}
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
    <title><?= $isEditing ? 'Edit' : 'Add' ?> Address | MoonAura Crystals</title>
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

                <h2><?= $isEditing ? 'Edit Address' : 'Add Address' ?></h2>

                <?php if (!empty($errors)): ?>
                    <div class="account-alert account-alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="address-form.php<?= $isEditing ? '?id=' . $addressId : '' ?>">

                    <?= csrf_field() ?>

                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?= h($address['full_name']) ?>" required>

                    <label for="phone">Mobile Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= h($address['phone']) ?>" required>

                    <label for="address_line1">Address Line 1</label>
                    <input type="text" id="address_line1" name="address_line1" value="<?= h($address['address_line1']) ?>" required>

                    <label for="address_line2">Address Line 2 <span style="font-weight:400; color: var(--text-light);">(optional)</span></label>
                    <input type="text" id="address_line2" name="address_line2" value="<?= h($address['address_line2']) ?>">

                    <label for="landmark">Landmark <span style="font-weight:400; color: var(--text-light);">(optional)</span></label>
                    <input type="text" id="landmark" name="landmark" value="<?= h($address['landmark']) ?>">

                    <div class="account-form-row">

                        <div>
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="<?= h($address['city']) ?>" required>
                        </div>

                        <div>
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" value="<?= h($address['state']) ?>" required>
                        </div>

                    </div>

                    <label for="postal_code">PIN Code</label>
                    <input type="text" id="postal_code" name="postal_code" value="<?= h($address['postal_code']) ?>" required>

                    <label class="account-checkbox-label">
                        <input type="checkbox" name="is_default" <?= $address['is_default'] ? 'checked' : '' ?>>
                        Set as default address
                    </label>

                    <button type="submit" class="btn btn-primary"><?= $isEditing ? 'Update Address' : 'Save Address' ?></button>

                </form>

            </div>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
