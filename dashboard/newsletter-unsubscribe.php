<?php
/* ===================================================================
   ADMIN - UNSUBSCRIBE NEWSLETTER SUBSCRIBER
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/newsletter-admin-functions.php';

require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('newsletter.php');
}

csrf_verify();

$email = normalize_email((string) ($_POST['email'] ?? ''));
$result = newsletter_admin_unsubscribe($email);

if ($result['ok']) {
    flash_set('success', 'Subscriber unsubscribed. Customer accounts were not changed.');
} else {
    flash_set('error', $result['error'] !== '' ? $result['error'] : 'Could not unsubscribe this address.');
}

$returnTo = 'newsletter.php';
if (!empty($_POST['return']) && $_POST['return'] === 'view') {
    $returnTo = 'newsletter-subscriber.php?email=' . rawurlencode($email);
}

redirect($returnTo);
