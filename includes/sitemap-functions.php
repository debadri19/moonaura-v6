<?php
/* ===================================================================
   SITEMAP HELPERS
   -------------------------------------------------------------------
   Builds the public URL list for sitemap.php from the live catalog.
   Only indexable storefront URLs. lastmod is used only when a real
   updated_at value exists on products, categories, or concerns.
=================================================================== */

require_once __DIR__ . '/db.php';


function sitemap_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}


function sitemap_loc(string $path): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}


function sitemap_lastmod(?string $updatedAt): ?string
{
    if ($updatedAt === null) {
        return null;
    }

    $updatedAt = trim($updatedAt);

    if ($updatedAt === '' || str_starts_with($updatedAt, '0000-00-00')) {
        return null;
    }

    $timestamp = strtotime($updatedAt);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}


function sitemap_add_url(array &$urls, array &$seen, string $loc, ?string $lastmod = null): void
{
    if ($loc === '' || isset($seen[$loc])) {
        return;
    }

    $seen[$loc] = true;

    $entry = ['loc' => $loc];

    if ($lastmod !== null && $lastmod !== '') {
        $entry['lastmod'] = $lastmod;
    }

    $urls[] = $entry;
}


function sitemap_public_urls(): array
{
    $urls = [];
    $seen = [];

    foreach ([
        'index.php',
        'shop.php',
        'about.php',
        'support.php',
        'policy.php',
        'concerns.php',
    ] as $page) {
        sitemap_add_url($urls, $seen, sitemap_loc($page));
    }

    $productStmt = db()->prepare(
        'SELECT slug, updated_at
         FROM products
         WHERE status = ?
         ORDER BY id ASC'
    );
    $productStmt->execute(['active']);
    $products = $productStmt->fetchAll();

    foreach ($products as $product) {
        $slug = trim((string) ($product['slug'] ?? ''));

        if ($slug === '') {
            continue;
        }

        $loc = rtrim(SITE_URL, '/') . '/product.php?slug=' . urlencode($slug);
        sitemap_add_url($urls, $seen, $loc, sitemap_lastmod($product['updated_at'] ?? null));
    }

    $categoryStmt = db()->prepare(
        'SELECT slug, updated_at
         FROM categories
         WHERE status = ?
         ORDER BY display_order ASC, id ASC'
    );
    $categoryStmt->execute(['active']);
    $categories = $categoryStmt->fetchAll();

    foreach ($categories as $category) {
        $slug = trim((string) ($category['slug'] ?? ''));

        if ($slug === '') {
            continue;
        }

        $loc = rtrim(SITE_URL, '/') . '/shop.php?category=' . urlencode($slug);
        sitemap_add_url($urls, $seen, $loc, sitemap_lastmod($category['updated_at'] ?? null));
    }

    $concernStmt = db()->prepare(
        'SELECT slug, updated_at
         FROM concern_categories
         WHERE status = ?
         ORDER BY display_order ASC, id ASC'
    );
    $concernStmt->execute(['active']);
    $concerns = $concernStmt->fetchAll();

    foreach ($concerns as $concern) {
        $slug = trim((string) ($concern['slug'] ?? ''));

        if ($slug === '') {
            continue;
        }

        $loc = rtrim(SITE_URL, '/') . '/concern.php?slug=' . urlencode($slug);
        sitemap_add_url($urls, $seen, $loc, sitemap_lastmod($concern['updated_at'] ?? null));
    }

    return $urls;
}


function sitemap_build_xml(array $urls): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . sitemap_escape($url['loc']) . "</loc>\n";

        if (!empty($url['lastmod'])) {
            $xml .= '    <lastmod>' . sitemap_escape($url['lastmod']) . "</lastmod>\n";
        }

        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";

    return $xml;
}
