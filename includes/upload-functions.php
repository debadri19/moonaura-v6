<?php
/* ===================================================================
   GENERIC IMAGE UPLOAD HELPER
   -------------------------------------------------------------------
   Same validation conventions as dashboard/includes/image-upload-handler.php
   (MIME whitelist checked via mime_content_type() - never trust the
   client's claimed type - size cap, randomized filename so an
   uploaded file can never overwrite another or be guessed), but
   standalone rather than tied to the product_images table, since this
   is now needed in two places that handler doesn't cover:
     - dashboard/settings.php: admin uploads a UPI QR code image
     - manual-upi-payment.php: a CUSTOMER (not logged in, not an
       admin) uploads an optional payment screenshot

   That second case is a different trust boundary than every other
   upload in this project so far - public, unauthenticated submission
   - so this is deliberately stricter about validation, not looser,
   even though it's a smaller/simpler function.
=================================================================== */

const UPLOAD_IMAGE_MAX_BYTES = 5 * 1024 * 1024; // 5 MB, matches product image limit
const UPLOAD_IMAGE_ALLOWED   = ['image/jpeg', 'image/png', 'image/webp'];


/* ==========================================
   SAVE ONE UPLOADED IMAGE
   -------------------------------------------------
   $file               - a single item from $_FILES (e.g. $_FILES['qr_image'])
   $absoluteDestFolder - full server path to save into (created if missing)
   $filenamePrefix     - short string prepended to the random filename,
                         purely for readability when browsing the
                         folder on disk (e.g. "qr", "screenshot")

   Returns ['path' => string, 'error' => null] on success, or
   ['path' => null, 'error' => string] on failure. Never returns an
   error for "no file chosen" on an optional upload - check
   $file['error'] === UPLOAD_ERR_NO_FILE yourself first if the field
   is optional, same as the product image handler does.
========================================== */

function save_uploaded_image(array $file, string $absoluteDestFolder, string $filenamePrefix): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'The file failed to upload. Please try again.'];
    }

    $mimeType = mime_content_type($file['tmp_name']);

    if (!in_array($mimeType, UPLOAD_IMAGE_ALLOWED, true)) {
        return ['path' => null, 'error' => 'Please upload a JPG, PNG, or WEBP image.'];
    }

    if ($file['size'] > UPLOAD_IMAGE_MAX_BYTES) {
        return ['path' => null, 'error' => 'That image is too large (max ' . format_file_size(UPLOAD_IMAGE_MAX_BYTES) . ').'];
    }

    if (!is_dir($absoluteDestFolder)) {
        mkdir($absoluteDestFolder, 0755, true);
    }

    $extension = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    };

    $safeFileName = $filenamePrefix . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination  = $absoluteDestFolder . '/' . $safeFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['path' => null, 'error' => 'Could not save the uploaded image on the server.'];
    }

    return ['path' => $safeFileName, 'error' => null];
}
