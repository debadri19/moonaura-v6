<?php
/* ===================================================================
   AUTHENTICATED THEME PREFERENCE SAVE (PHASE 5)
   -------------------------------------------------------------------
   Persists Light / Dark / System for the logged-in customer.
   Identity comes from the server-side session only. Guests and
   invalid modes are rejected. CSRF matches every other account write.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../includes/customer-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

csrf_verify();

function theme_save_respond(bool $ok, string $message, int $status, ?string $mode = null): void
{
    if (is_ajax_request()) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $ok,
            'mode'    => $mode,
            'message' => $message,
        ]);
        exit;
    }

    if ($ok) {
        flash_set('success', $message);
    } else {
        flash_set('error', $message);
    }

    redirect('profile.php');
}

if (!is_customer_logged_in()) {
    if (is_ajax_request()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'mode'    => null,
            'message' => 'Not authenticated',
        ]);
        exit;
    }

    redirect('login.php');
}

$mode = customer_theme_normalize((string) ($_POST['theme'] ?? $_POST['mode'] ?? ''));

if ($mode === null) {
    theme_save_respond(false, 'Invalid theme preference.', 400);
}

$customerId = (int) ($_SESSION['customer_id'] ?? 0);

if (!customer_theme_save($customerId, $mode)) {
    theme_save_respond(false, 'Could not save theme preference.', 500);
}

theme_save_respond(true, 'Theme preference saved.', 200, $mode);
