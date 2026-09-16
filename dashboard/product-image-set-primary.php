<?php
/* ===================================================================
   ADMIN - SET PRIMARY PRODUCT IMAGE
   -------------------------------------------------------------------
   Phase C1: added an AJAX JSON response branch alongside the original
   POST + redirect + flash behavior, which is left completely unchanged
   for non-JS / AJAX-failure fallback. Detected via the same
   X-Requested-With header admin-dashboard.js's existing polling
   request already uses. require_admin_login()/csrf_verify() and every
   existing validation/lookup step below run exactly as before for
   both branches - the AJAX branch only changes how the outcome is
   reported back.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function c1_set_primary_ajax_error(string $message, int $status = 400): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products.php');
}

csrf_verify();

$productId = (int) ($_POST['product_id'] ?? 0);
$imageId   = (int) ($_POST['image_id'] ?? 0);

if ($productId <= 0 || $imageId <= 0) {
    if ($isAjax) {
        c1_set_primary_ajax_error('Invalid image.');
    }
    flash_set('error', 'Invalid image.');
    redirect('products.php');
}

// Make sure the image actually belongs to this product before changing anything.
$stmt = db()->prepare('SELECT id FROM product_images WHERE id = ? AND product_id = ?');
$stmt->execute([$imageId, $productId]);

if (!$stmt->fetch()) {
    if ($isAjax) {
        c1_set_primary_ajax_error('Image not found.', 404);
    }
    flash_set('error', 'Image not found.');
    redirect('product-form.php?id=' . $productId);
}

// Clear the old primary, then set the new one - kept as two simple
// statements rather than a transaction, matching the beginner-friendly
// style used throughout this project.
$clear = db()->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = ?');
$clear->execute([$productId]);

$set = db()->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ?');
$set->execute([$imageId]);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'    => true,
        'message'    => 'Primary image updated.',
        'product_id' => $productId,
        'image_id'   => $imageId,
    ]);
    exit;
}

flash_set('success', 'Primary image updated.');
redirect('product-form.php?id=' . $productId);
