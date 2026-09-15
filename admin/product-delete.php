<?php
/* ===================================================================
   ADMIN - DELETE PRODUCT
   -------------------------------------------------------------------
   Deleting the product row automatically deletes its product_images
   rows too (ON DELETE CASCADE in the schema). This script also
   removes the actual image FILES from disk, and the now-empty
   product image folder.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/product-image-variants.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products.php');
}

csrf_verify();

$productId = (int) ($_POST['id'] ?? 0);

if ($productId <= 0) {
    flash_set('error', 'Invalid product.');
    redirect('products.php');
}

/* ==========================================
   GATHER IMAGE FILE PATHS + FOLDER BEFORE
   DELETING (we need this info before the
   database rows disappear)
========================================== */

$stmt = db()->prepare('SELECT file_path FROM product_images WHERE product_id = ?');
$stmt->execute([$productId]);
$imagePaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = db()->prepare('SELECT image_folder FROM products WHERE id = ?');
$stmt->execute([$productId]);
$imageFolder = $stmt->fetchColumn();

/* ==========================================
   DELETE THE PRODUCT
   (cascades to product_images and
   collection_products automatically)
========================================== */

$stmt = db()->prepare('DELETE FROM products WHERE id = ?');
$stmt->execute([$productId]);

/* ==========================================
   DELETE THE ACTUAL IMAGE FILES + FOLDER
========================================== */

foreach ($imagePaths as $path) {
    $masterRelativePath = (string) $path;
    delete_product_image_variants($masterRelativePath);

    $absolutePath = resolve_stored_product_image_path($masterRelativePath);
    if ($absolutePath !== null && is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

if ($imageFolder) {
    $absoluteFolder = resolve_product_image_directory((string) $imageFolder);
    if ($absoluteFolder !== null && is_dir($absoluteFolder) && count(scandir($absoluteFolder)) === 2) {
        rmdir($absoluteFolder);
    }
}

flash_set('success', 'Product deleted.');
redirect('products.php');
