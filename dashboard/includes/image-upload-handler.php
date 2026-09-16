<?php
/* ===================================================================
   PRODUCT IMAGE UPLOAD HANDLER
   -------------------------------------------------------------------
   One function, used by product-form.php, that takes whatever was
   uploaded in the "images" file field, validates it, saves it to
   disk, and records it in the product_images table.
=================================================================== */

const PRODUCT_IMAGE_MAX_BYTES  = 5 * 1024 * 1024; // 5 MB per image
const PRODUCT_IMAGE_ALLOWED    = ['image/jpeg', 'image/png', 'image/webp'];

require_once __DIR__ . '/../../includes/product-image-variants.php';


/* ==========================================
   HANDLE UPLOADED IMAGES FOR A PRODUCT
   -------------------------------------------------
   $productId          - the product these images belong to
   $destinationFolder  - relative path under assets/images/products/
                         e.g. "bracelets/tiger-eye-bracelet"

   Returns an array of error messages (empty array = all good).
========================================== */

function handle_product_image_uploads(int $productId, string $destinationFolder): array
{
    $errors = [];

    // Nothing uploaded - not an error, just nothing to do.
    if (empty($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        return $errors;
    }

    $absoluteFolder = resolve_product_image_directory($destinationFolder);

    if ($absoluteFolder === null) {
        return ['Could not save images to an invalid product folder.'];
    }

    if (!is_dir($absoluteFolder)) {
        mkdir($absoluteFolder, 0755, true);
    }

    // Find the current highest display_order so new images go at the end.
    $stmt = db()->prepare('SELECT COALESCE(MAX(display_order), -1) FROM product_images WHERE product_id = ?');
    $stmt->execute([$productId]);
    $nextOrder = ((int) $stmt->fetchColumn()) + 1;

    // Does this product already have any image? (used to decide is_primary)
    $stmt = db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
    $stmt->execute([$productId]);
    $hasExistingImages = ((int) $stmt->fetchColumn()) > 0;

    $fileCount = count($_FILES['images']['name']);

    for ($i = 0; $i < $fileCount; $i++) {

        // Skip empty file inputs (happens when fewer files are chosen
        // than the form allows).
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $originalName = $_FILES['images']['name'][$i];
        $tmpPath      = $_FILES['images']['tmp_name'][$i];
        $fileSize     = $_FILES['images']['size'][$i];
        $mimeType     = mime_content_type($tmpPath);

        /* ---------- Validate ---------- */

        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "\"{$originalName}\" failed to upload. Please try again.";
            continue;
        }

        if (!in_array($mimeType, PRODUCT_IMAGE_ALLOWED, true)) {
            $errors[] = "\"{$originalName}\" is not a JPG, PNG, or WEBP image.";
            continue;
        }

        if ($fileSize > PRODUCT_IMAGE_MAX_BYTES) {
            $errors[] = "\"{$originalName}\" is too large (max " . format_file_size(PRODUCT_IMAGE_MAX_BYTES) . ').';
            continue;
        }

        /* ---------- Save the file with a safe, unique name ---------- */

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        };

        $safeFileName = ($nextOrder + 1) . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination  = $absoluteFolder . '/' . $safeFileName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            $errors[] = "Could not save \"{$originalName}\" on the server.";
            continue;
        }

        /* ---------- Record it in the database ---------- */

        $relativePath = 'assets/images/products/' . $destinationFolder . '/' . $safeFileName;
        $isPrimary    = (!$hasExistingImages && $nextOrder === 0) ? 1 : 0;

        $stmt = db()->prepare(
            'INSERT INTO product_images (product_id, file_path, display_order, is_primary)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$productId, $relativePath, $nextOrder, $isPrimary]);

        $variantResult = generate_product_image_variants($relativePath);
        if ($variantResult['failed'] !== []) {
            error_log(
                'Product image variants failed for ' . $relativePath . ': ' .
                implode(',', $variantResult['failed'])
            );
        }

        $nextOrder++;
        $hasExistingImages = true;
    }

    return $errors;
}


/* ==========================================
   KEEP products.image_count IN SYNC
   Call this any time images are added or
   removed for a product.
========================================== */

function sync_product_image_count(int $productId): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
    $stmt->execute([$productId]);
    $count = (int) $stmt->fetchColumn();

    $update = db()->prepare('UPDATE products SET image_count = ? WHERE id = ?');
    $update->execute([$count, $productId]);
}
