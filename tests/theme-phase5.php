<?php
/* ===================================================================
   Dark Mode Phase 5 - PHP helper + DB persistence harness
   -------------------------------------------------------------------
   Run with:  php tests/theme-phase5.php
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

check(customer_theme_normalize('light') === 'light', 'normalize light');
check(customer_theme_normalize('dark') === 'dark', 'normalize dark');
check(customer_theme_normalize('system') === 'system', 'normalize system');
check(customer_theme_normalize('sepia') === null, 'normalize sepia');
check(customer_theme_normalize('') === null, 'normalize empty');
check(customer_theme_normalize('DARK') === null, 'normalize uppercase');
check(customer_theme_normalize(null) === null, 'normalize null');
check(customer_theme_save(0, 'dark') === false, 'save rejects missing customer id');
check(customer_theme_save(1, 'sepia') === false, 'save rejects invalid mode');

$email = 'theme-phase5-' . bin2hex(random_bytes(4)) . '@example.test';
$phone = '9' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$hash  = password_hash('Phase5Test!ok', PASSWORD_DEFAULT);

$stmt = db()->prepare(
    'INSERT INTO customers (name, email, phone, password_hash) VALUES (?, ?, ?, ?)'
);
$stmt->execute(['Phase 5 Theme', $email, $phone, $hash]);
$customerId = (int) db()->lastInsertId();
check($customerId > 0, 'inserted test customer');

try {
    $loaded = customer_theme_load($customerId);
    check($loaded === null, 'new customer preference is NULL');

    check(customer_theme_save($customerId, 'dark') === true, 'save dark');
    check(customer_theme_load($customerId) === 'dark', 'load dark');

    check(customer_theme_save($customerId, 'light') === true, 'save light');
    check(customer_theme_load($customerId) === 'light', 'load light');

    check(customer_theme_save($customerId, 'system') === true, 'save system');
    check(customer_theme_load($customerId) === 'system', 'load system');

    check(customer_theme_save($customerId, 'sepia') === false, 'invalid save rejected');
    check(customer_theme_load($customerId) === 'system', 'invalid save leaves previous value');

    $before = db()->prepare('SELECT theme_preference FROM customers WHERE id = ?');
    $before->execute([$customerId]);
    $stored = $before->fetchColumn();
    check($stored === 'system', 'column stores system');

    $_SESSION['customer_id'] = $customerId;
    unset($_SESSION['customer_theme_preference']);
    customer_theme_apply_login($customerId);
    check(($_SESSION['customer_theme_preference'] ?? null) === 'system', 'login cache is account preference');

    echo "OK persist\n";
    echo "OK invalid\n";
    echo "OK null\n";
    echo "OK identity\n";
} finally {
    $cleanup = db()->prepare('UPDATE customers SET email = ?, phone = ?, status = ? WHERE id = ?');
    $cleanup->execute([
        'used-theme-phase5-' . $customerId . '@example.test',
        '8' . str_pad((string) $customerId, 9, '0', STR_PAD_LEFT),
        'inactive',
        $customerId,
    ]);
}
