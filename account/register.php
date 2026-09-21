<?php
/* ===================================================================
   CUSTOMER REGISTRATION
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../includes/customer-functions.php';
require_once __DIR__ . '/../includes/order-functions.php'; // for is_valid_mobile_number()

if (is_customer_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];

$name  = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $name            = trim($_POST['name'] ?? '');
    $email           = normalize_email(trim($_POST['email'] ?? ''));
    $phone           = normalize_mobile_number(trim($_POST['phone'] ?? ''));
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!is_valid_mobile_number($phone)) {
        $errors[] = 'Please enter a valid 10-digit mobile number.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    // Check email/phone aren't already registered.
    if (empty($errors)) {

        $stmt = db()->prepare('SELECT id FROM customers WHERE email = ? OR phone = ? LIMIT 1');
        $stmt->execute([$email, $phone]);

        if ($stmt->fetch()) {
            $errors[] = 'An account with that email or mobile number already exists.';
        }
    }

    if (empty($errors)) {

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = db()->prepare(
            'INSERT INTO customers (name, email, phone, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $phone, $passwordHash]);

        $customerId = (int) db()->lastInsertId();

        // Attach any past guest orders placed under this email/phone.
        link_guest_orders_to_customer($customerId, $email, $phone);

        $sessionCart = (isset($_SESSION['cart']) && is_array($_SESSION['cart']))
            ? $_SESSION['cart']
            : [];

        // Log the new customer straight in.
        session_regenerate_id(true);
        $_SESSION['customer_id'] = $customerId;

        // #25B: persist the guest session cart onto the new account.
        try {
            customer_cart_apply_login_merge($customerId, $sessionCart);
        } catch (Throwable $e) {
            error_log('customer_cart register merge failed: ' . $e->getMessage());
        }

        redirect('dashboard.php');
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
    <title>Create an Account | MoonAura Crystals</title>
    <meta name="robots" content="noindex, follow">

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

    <section class="account-auth-page">

        <div class="account-auth-box">

            <h1>Create an Account</h1>
            <p class="account-auth-subtitle">Track your orders and check out faster next time.</p>

            <?php if (!empty($errors)): ?>
                <div class="account-alert account-alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">

                <?= csrf_field() ?>

                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?= h($name) ?>" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= h($email) ?>" required>

                <label for="phone">Mobile Number</label>
                <input type="tel" id="phone" name="phone" value="<?= h($phone) ?>" required>

                <label for="password">Password</label>
                <div class="pw-field">
                    <input type="password" id="password" name="password" minlength="8" required>
                    <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false" aria-controls="password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <label for="confirm_password">Confirm Password</label>
                <div class="pw-field">
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                    <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false" aria-controls="confirm_password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary">Create Account</button>

            </form>

            <p class="account-auth-footer">
                Already have an account? <a href="login.php">Log in</a>
            </p>

        </div>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
    <script src="<?= versioned_asset('assets/js/password-toggle.js') ?>"></script>

</body>
</html>
