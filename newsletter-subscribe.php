<?php
/* ===================================================================
   NEWSLETTER - SUBSCRIBE
   -------------------------------------------------------------------
   POST-only endpoint for the homepage .newsletter-form. Validates
   the email and adds the contact to the Brevo pending list.
   Redirects back to the homepage newsletter section with a flash
   message. Confirmation mail is sent by Brevo Automation.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/newsletter-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#newsletter');
}

csrf_verify();

$email  = normalize_email((string) ($_POST['email'] ?? ''));
$result = newsletter_subscribe($email);

if ($result === 'invalid') {
    flash_set('newsletter_error', 'Please enter a valid email address.');
} elseif ($result === 'pending') {
    flash_set('newsletter_success', 'Please check your inbox and confirm your email address to complete your subscription.');
} elseif ($result === 'already') {
    flash_set('newsletter_success', 'You are already subscribed. Thank you.');
} else {
    flash_set('newsletter_error', 'We could not complete your subscription right now. Please try again later.');
}

redirect('index.php#newsletter');
