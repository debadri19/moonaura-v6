<?php
/* ===================================================================
   CLI: BACKFILL PRODUCT IMAGE VARIANTS (Phase 5A)
   -------------------------------------------------------------------
   Generates missing card/sm WebP siblings for existing masters.
   Does not rewrite product_images.file_path or display pages.

   Usage:
     php scripts/backfill-product-image-variants.php
     php scripts/backfill-product-image-variants.php --force
     php scripts/backfill-product-image-variants.php --from-disk
=================================================================== */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

$force    = in_array('--force', $argv, true);
$fromDisk = in_array('--from-disk', $argv, true);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/product-image-variants.php';

function backfill_collect_master_paths_from_db(): array
{
    require_once dirname(__DIR__) . '/includes/db.php';

    $stmt = db()->query('SELECT file_path FROM product_images ORDER BY id ASC');
    $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $masters = [];
    foreach ($paths as $path) {
        $path = (string) $path;
        if ($path === '') {
            continue;
        }
        $masters[] = $path;
    }

    return $masters;
}

function backfill_collect_master_paths_from_disk(): array
{
    $root = product_images_root();
    if (!is_dir($root)) {
        return [];
    }

    $masters = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $filename = $fileInfo->getFilename();
        if (strcasecmp($filename, 'placeholder.svg') === 0) {
            continue;
        }

        if (preg_match('/-(card|sm)\.webp$/i', $filename) === 1) {
            continue;
        }

        if (product_image_master_stem($filename) === null) {
            continue;
        }

        $absolute = str_replace('\\', '/', $fileInfo->getPathname());
        $rootNormalized = rtrim(str_replace('\\', '/', $root), '/');
        if (!str_starts_with($absolute, $rootNormalized . '/')) {
            continue;
        }

        $relative = 'assets/images/products/' . substr($absolute, strlen($rootNormalized) + 1);
        if (resolve_stored_product_image_path($relative) === null) {
            continue;
        }

        $masters[] = $relative;
    }

    sort($masters);

    return $masters;
}

$source = 'database';
$masters = [];

if ($fromDisk) {
    $source = 'disk';
    $masters = backfill_collect_master_paths_from_disk();
} else {
    try {
        $masters = backfill_collect_master_paths_from_db();
    } catch (Throwable $e) {
        fwrite(STDERR, "Database unavailable (" . $e->getMessage() . "); scanning disk instead.\n");
        $source = 'disk';
        $masters = backfill_collect_master_paths_from_disk();
    }
}

$processed = 0;
$generated = 0;
$skipped   = 0;
$failed    = 0;

echo "Backfilling product image variants from {$source}" . ($force ? ' (force)' : '') . ".\n";
echo 'Masters found: ' . count($masters) . "\n";

foreach ($masters as $masterRelativePath) {
    $processed++;
    $result = generate_product_image_variants($masterRelativePath, $force);

    $generated += count($result['generated']);
    $skipped   += count($result['skipped']);
    $failed    += count($result['failed']);

    if ($result['generated'] !== []) {
        echo '  generated ' . $masterRelativePath . ' -> ' . implode(',', $result['generated']) . "\n";
    }

    if ($result['failed'] !== []) {
        echo '  failed    ' . $masterRelativePath . ' -> ' . implode(',', $result['failed']) . "\n";
    }
}

echo "Done. processed={$processed} generated={$generated} skipped={$skipped} failed={$failed}\n";

exit($failed > 0 ? 1 : 0);
