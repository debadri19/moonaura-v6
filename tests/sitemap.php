<?php
/* ===================================================================
   Dynamic sitemap harness
   -------------------------------------------------------------------
   Run with:  php tests/sitemap.php
=================================================================== */

putenv('SITE_URL=http://127.0.0.1:8000');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/sitemap-functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$base = rtrim(SITE_URL, '/');
$stamp = bin2hex(random_bytes(4));

$activeCategorySlug = 'sitemap-cat-active-' . $stamp;
$inactiveCategorySlug = 'sitemap-cat-inactive-' . $stamp;
$activeProductSlug = 'sitemap-prod-active-' . $stamp;
$draftProductSlug = 'sitemap-prod-draft-' . $stamp;
$activeConcernSlug = 'sitemap-concern-active-' . $stamp;
$inactiveConcernSlug = 'sitemap-concern-inactive-' . $stamp;

$activeCategoryId = 0;
$inactiveCategoryId = 0;
$activeProductId = 0;
$draftProductId = 0;
$activeConcernId = 0;
$inactiveConcernId = 0;

try {
    $insertCategory = db()->prepare(
        'INSERT INTO categories (name, slug, image_folder_name, display_order, status)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insertCategory->execute(['Sitemap Active ' . $stamp, $activeCategorySlug, 'bracelets', 90, 'active']);
    $activeCategoryId = (int) db()->lastInsertId();
    $insertCategory->execute(['Sitemap Inactive ' . $stamp, $inactiveCategorySlug, 'bracelets', 91, 'inactive']);
    $inactiveCategoryId = (int) db()->lastInsertId();

    $insertProduct = db()->prepare(
        'INSERT INTO products
            (sku, name, slug, category_id, image_folder, mrp, sell_price, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertProduct->execute([
        'SM-A-' . $stamp,
        'Sitemap Active Product ' . $stamp,
        $activeProductSlug,
        $activeCategoryId,
        'bracelets/sitemap-active',
        499.00,
        399.00,
        'active',
    ]);
    $activeProductId = (int) db()->lastInsertId();
    $insertProduct->execute([
        'SM-D-' . $stamp,
        'Sitemap Draft Product ' . $stamp,
        $draftProductSlug,
        $activeCategoryId,
        'bracelets/sitemap-draft',
        499.00,
        399.00,
        'draft',
    ]);
    $draftProductId = (int) db()->lastInsertId();

    $insertConcern = db()->prepare(
        'INSERT INTO concern_categories (name, slug, display_order, status)
         VALUES (?, ?, ?, ?)'
    );
    $insertConcern->execute(['Sitemap Concern Active ' . $stamp, $activeConcernSlug, 90, 'active']);
    $activeConcernId = (int) db()->lastInsertId();
    $insertConcern->execute(['Sitemap Concern Inactive ' . $stamp, $inactiveConcernSlug, 91, 'inactive']);
    $inactiveConcernId = (int) db()->lastInsertId();

    $urls = sitemap_public_urls();
    $locs = array_column($urls, 'loc');
    $xml = sitemap_build_xml($urls);

    check($xml !== '', 'xml is not empty');
    check(str_starts_with($xml, '<?xml version="1.0" encoding="UTF-8"?>'), 'xml declaration present');
    check(str_contains($xml, 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'), 'urlset namespace present');
    check(!str_contains($xml, '<changefreq>'), 'no changefreq');
    check(!str_contains($xml, '<priority>'), 'no priority');

    $document = new DOMDocument();
    check($document->loadXML($xml) === true, 'xml parses');
    check($document->documentElement !== null, 'xml has root');
    check($document->documentElement->localName === 'urlset', 'root is urlset');
    check($document->documentElement->namespaceURI === 'http://www.sitemaps.org/schemas/sitemap/0.9', 'namespace uri');

    $expected = [
        $base . '/index.php',
        $base . '/shop.php',
        $base . '/about.php',
        $base . '/support.php',
        $base . '/policy.php',
        $base . '/concerns.php',
        $base . '/product.php?slug=' . urlencode($activeProductSlug),
        $base . '/shop.php?category=' . urlencode($activeCategorySlug),
        $base . '/concern.php?slug=' . urlencode($activeConcernSlug),
    ];

    foreach ($expected as $loc) {
        check(in_array($loc, $locs, true), 'includes ' . $loc);
    }

    $forbidden = [
        $base . '/product.php?slug=' . urlencode($draftProductSlug),
        $base . '/shop.php?category=' . urlencode($inactiveCategorySlug),
        $base . '/concern.php?slug=' . urlencode($inactiveConcernSlug),
        $base . '/cart.php',
        $base . '/checkout.php',
        $base . '/wishlist.php',
        $base . '/account/login.php',
        $base . '/account/register.php',
        $base . '/account/dashboard.php',
        $base . '/dashboard/dashboard.php',
        $base . '/payment.php',
        $base . '/order-success.php',
        $base . '/maintenance.php',
        $base . '/cart-add.php',
    ];

    foreach ($forbidden as $loc) {
        check(!in_array($loc, $locs, true), 'excludes ' . $loc);
    }

    check(count($locs) === count(array_unique($locs)), 'no duplicate locs');

    foreach ($locs as $loc) {
        check(str_starts_with($loc, $base . '/'), 'host consistent: ' . $loc);
        check(!str_contains($loc, '/dashboard/'), 'no dashboard url: ' . $loc);
        check(!str_contains($loc, '/account/'), 'no account url: ' . $loc);
    }

    $activeProductLoc = $base . '/product.php?slug=' . urlencode($activeProductSlug);
    $foundLastmod = false;
    foreach ($urls as $url) {
        if ($url['loc'] === $activeProductLoc) {
            check(!empty($url['lastmod']), 'active product has lastmod');
            check(preg_match('/^\d{4}-\d{2}-\d{2}$/', $url['lastmod']) === 1, 'lastmod is Y-m-d');
            $foundLastmod = true;
        }
    }
    check($foundLastmod, 'found active product url entry');

    echo "OK xml\n";
    echo "OK catalog-filter\n";
    echo "OK private-urls\n";
    echo "OK lastmod\n";
} finally {
    if ($activeProductId > 0 || $draftProductId > 0) {
        $deleteProducts = db()->prepare('DELETE FROM products WHERE id IN (?, ?)');
        $deleteProducts->execute([$activeProductId, $draftProductId]);
    }
    if ($activeConcernId > 0 || $inactiveConcernId > 0) {
        $deleteConcerns = db()->prepare('DELETE FROM concern_categories WHERE id IN (?, ?)');
        $deleteConcerns->execute([$activeConcernId, $inactiveConcernId]);
    }
    if ($activeCategoryId > 0 || $inactiveCategoryId > 0) {
        $deleteCategories = db()->prepare('DELETE FROM categories WHERE id IN (?, ?)');
        $deleteCategories->execute([$activeCategoryId, $inactiveCategoryId]);
    }
}
