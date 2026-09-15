<?php
/* ===================================================================
   PRODUCT IMAGE VARIANTS (Phase 5A)
   -------------------------------------------------------------------
   Generates card (480) and sm (180) WebP siblings beside a master
   product image. Masters in product_images.file_path are never
   rewritten. Display pages keep using the master until Phase 5B/5C.
=================================================================== */

const PRODUCT_IMAGE_VARIANT_SIZES = [
    'card' => 480,
    'sm'   => 180,
];

const PRODUCT_IMAGE_VARIANT_WEBP_QUALITY = 82;


function product_image_variant_names(): array
{
    return array_keys(PRODUCT_IMAGE_VARIANT_SIZES);
}

function product_image_master_stem(string $filename): ?string
{
    $filename = basename($filename);
    $dot = strrpos($filename, '.');
    if ($dot === false || $dot === 0) {
        return null;
    }

    $stem = substr($filename, 0, $dot);
    if ($stem === '' || preg_match('/-(card|sm)$/', $stem) === 1) {
        return null;
    }

    return $stem;
}

function product_image_variant_relative_path(string $masterRelativePath, string $variant): ?string
{
    if (!isset(PRODUCT_IMAGE_VARIANT_SIZES[$variant])) {
        return null;
    }

    $masterAbsolute = resolve_stored_product_image_path($masterRelativePath);
    if ($masterAbsolute === null) {
        return null;
    }

    $stem = product_image_master_stem($masterAbsolute);
    if ($stem === null) {
        return null;
    }

    $masterRelativePath = ltrim(str_replace('\\', '/', trim($masterRelativePath)), '/');
    $directory = dirname($masterRelativePath);
    if ($directory === '.' || $directory === '/' || $directory === '') {
        return null;
    }

    $variantRelative = $directory . '/' . $stem . '-' . $variant . '.webp';

    if (resolve_stored_product_image_path($variantRelative) === null) {
        return null;
    }

    return $variantRelative;
}

function product_image_variant_url(string $masterRelativePath, string $variant = 'card'): string
{
    $masterRelativePath = trim($masterRelativePath);

    if ($masterRelativePath === '') {
        return asset_url('');
    }

    if ($variant !== 'master') {
        $variantRelative = product_image_variant_relative_path($masterRelativePath, $variant);
        if ($variantRelative !== null) {
            $absolute = resolve_stored_product_image_path($variantRelative);
            if ($absolute !== null && is_file($absolute)) {
                return asset_url($variantRelative);
            }
        }
    }

    return asset_url($masterRelativePath);
}

function generate_product_image_variants(string $masterRelativePath, bool $force = false): array
{
    $result = [
        'generated' => [],
        'skipped'   => [],
        'failed'    => [],
    ];

    if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
        $result['failed'][] = 'gd';
        return $result;
    }

    $masterAbsolute = resolve_stored_product_image_path($masterRelativePath);
    if ($masterAbsolute === null || !is_file($masterAbsolute)) {
        $result['failed'][] = 'missing-master';
        return $result;
    }

    $masterMtime = (int) filemtime($masterAbsolute);

    foreach (PRODUCT_IMAGE_VARIANT_SIZES as $name => $maxSize) {
        $variantRelative = product_image_variant_relative_path($masterRelativePath, $name);
        if ($variantRelative === null) {
            $result['failed'][] = $name;
            continue;
        }

        $variantAbsolute = resolve_stored_product_image_path($variantRelative);
        if ($variantAbsolute === null || $variantAbsolute === $masterAbsolute) {
            $result['failed'][] = $name;
            continue;
        }

        if (
            !$force
            && is_file($variantAbsolute)
            && (int) filemtime($variantAbsolute) >= $masterMtime
            && filesize($variantAbsolute) > 0
        ) {
            $result['skipped'][] = $name;
            continue;
        }

        if (write_product_image_variant($masterAbsolute, $variantAbsolute, $maxSize)) {
            $result['generated'][] = $name;
            continue;
        }

        $result['failed'][] = $name;
    }

    return $result;
}

function delete_product_image_variants(string $masterRelativePath): void
{
    foreach (product_image_variant_names() as $name) {
        $variantRelative = product_image_variant_relative_path($masterRelativePath, $name);
        if ($variantRelative === null) {
            continue;
        }

        $variantAbsolute = resolve_stored_product_image_path($variantRelative);
        if ($variantAbsolute === null) {
            continue;
        }

        $masterAbsolute = resolve_stored_product_image_path($masterRelativePath);
        if ($masterAbsolute !== null && $variantAbsolute === $masterAbsolute) {
            continue;
        }

        if (is_file($variantAbsolute)) {
            unlink($variantAbsolute);
        }
    }
}

function write_product_image_variant(string $masterAbsolute, string $destinationAbsolute, int $maxSize): bool
{
    if ($maxSize < 1 || $masterAbsolute === $destinationAbsolute) {
        return false;
    }

    if (!path_is_inside_directory(product_images_root(), $destinationAbsolute)) {
        return false;
    }

    $raw = @file_get_contents($masterAbsolute);
    if ($raw === false || $raw === '') {
        return false;
    }

    $source = @imagecreatefromstring($raw);
    if ($source === false) {
        return false;
    }

    $destination = null;
    $tempPath = null;

    try {
        if (function_exists('imageistruecolor') && !imageistruecolor($source) && function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($source);
        }

        $sourceWidth  = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);
            return false;
        }

        $scale = min($maxSize / $sourceWidth, $maxSize / $sourceHeight, 1.0);
        $destWidth  = max(1, (int) round($sourceWidth * $scale));
        $destHeight = max(1, (int) round($sourceHeight * $scale));

        $destination = imagecreatetruecolor($destWidth, $destHeight);
        if ($destination === false) {
            imagedestroy($source);
            return false;
        }

        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
        imagefilledrectangle($destination, 0, 0, $destWidth, $destHeight, $transparent);

        $copied = imagecopyresampled(
            $destination,
            $source,
            0,
            0,
            0,
            0,
            $destWidth,
            $destHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($source);
        $source = null;

        if (!$copied) {
            imagedestroy($destination);
            return false;
        }

        $directory = dirname($destinationAbsolute);
        $tempPath = $directory . '/.' . basename($destinationAbsolute) . '.tmp.' . bin2hex(random_bytes(3));

        if (!path_is_inside_directory(product_images_root(), $tempPath)) {
            imagedestroy($destination);
            return false;
        }

        $written = imagewebp($destination, $tempPath, PRODUCT_IMAGE_VARIANT_WEBP_QUALITY);
        imagedestroy($destination);
        $destination = null;

        if (!$written || !is_file($tempPath) || filesize($tempPath) < 1) {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
            return false;
        }

        if (!rename($tempPath, $destinationAbsolute)) {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
            return false;
        }

        return true;
    } catch (Throwable $e) {
        error_log('write_product_image_variant() failed: ' . $e->getMessage());

        if ($source !== null) {
            imagedestroy($source);
        }
        if ($destination !== null) {
            imagedestroy($destination);
        }
        if ($tempPath !== null && is_file($tempPath)) {
            unlink($tempPath);
        }

        return false;
    }
}
