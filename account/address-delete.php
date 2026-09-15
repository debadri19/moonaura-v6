<?php
/* ===================================================================
   SAVED ADDRESS - DELETE
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';

require_customer_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('addresses.php');
}

csrf_verify();

$customer  = current_customer();
$addressId = (int) ($_POST['id'] ?? 0);

if ($addressId > 0) {

    // The "AND customer_id = ?" is what stops a customer from
    // deleting someone else's saved address by editing the form.
    $stmt = db()->prepare('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?');
    $stmt->execute([$addressId, $customer['id']]);
}

flash_set('success', 'Address deleted.');
redirect('addresses.php');
