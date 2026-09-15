<?php
/* ===================================================================
   ADMIN - DELETE A SINGLE PRODUCT IMAGE
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/product-image-variants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/image-upload-handler.php';

require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products.php');
}

csrf_verify();

$productId = (int) ($_POST['product_id'] ?? 0);
$imageId   = (int) ($_POST['image_id'] ?? 0);

if ($productId <= 0 || $imageId <= 0) {
    flash_set('error', 'Invalid image.');
    redirect('products.php');
}

/* ==========================================
   FIND THE IMAGE (make sure it belongs to
   this product before touching anything)
========================================== */

$stmt = db()->prepare('SELECT file_path, is_primary FROM product_images WHERE id = ? AND product_id = ?');
$stmt->execute([$imageId, $productId]);
$image = $stmt->fetch();

if (!$image) {
    flash_set('error', 'Image not found.');
    redirect('product-form.php?id=' . $productId);
}

/* ==========================================
   DELETE THE FILE + DATABASE ROW
========================================== */

$masterRelativePath = (string) $image['file_path'];
delete_product_image_variants($masterRelativePath);

$absolutePath = resolve_stored_product_image_path($masterRelativePath);
if ($absolutePath !== null && is_file($absolutePath)) {
    unlink($absolutePath);
}

$stmt = db()->prepare('DELETE FROM product_images WHERE id = ?');
$stmt->execute([$imageId]);

/* ==========================================
   IF THAT WAS THE PRIMARY IMAGE, PROMOTE
   THE NEXT ONE (LOWEST DISPLAY ORDER)
========================================== */

if ($image['is_primary']) {

    $stmt = db()->prepare(
        'SELECT id FROM product_images WHERE product_id = ? ORDER BY display_order ASC LIMIT 1'
    );
    $stmt->execute([$productId]);
    $nextImageId = $stmt->fetchColumn();

    if ($nextImageId) {
        $update = db()->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ?');
        $update->execute([$nextImageId]);
    }
}

sync_product_image_count($productId);

flash_set('success', 'Image deleted.');
redirect('product-form.php?id=' . $productId);
