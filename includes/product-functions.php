<?php
/* ===================================================================
   PRODUCT FUNCTIONS
   -------------------------------------------------------------------
   Database queries related to fetching PRODUCTS for the public-facing
   pages (homepage, shop). Kept separate from includes/functions.php,
   which is for small generic helpers only.
=================================================================== */

require_once __DIR__ . '/db.php';


/* ==========================================
   PRODUCT IMAGE PLACEHOLDER
   Used whenever a product has no uploaded
   images yet.
========================================== */

const PRODUCT_IMAGE_PLACEHOLDER = 'assets/images/products/placeholder.svg';


/* ==========================================
   GET A PRODUCT'S PRIMARY IMAGE PATH
   Falls back to the placeholder if the
   product has no images uploaded yet.
========================================== */

function get_product_primary_image(int $productId): string
{
    $stmt = db()->prepare(
        'SELECT file_path FROM product_images
         WHERE product_id = ?
         ORDER BY is_primary DESC, display_order ASC
         LIMIT 1'
    );
    $stmt->execute([$productId]);

    $path = $stmt->fetchColumn();

    return $path ?: PRODUCT_IMAGE_PLACEHOLDER;
}


/* ==========================================
   WORK OUT A PRODUCT'S BADGE
   Returns "SALE", "Best Seller", "New", or
   null (no badge) - same rule used on both
   the homepage and the shop page.
========================================== */

function get_product_badge(array $product): ?string
{
    if ((float) $product['mrp'] > (float) $product['sell_price']) {
        return 'SALE';
    }

    if (!empty($product['is_bestseller'])) {
        return 'Best Seller';
    }

    // "New" = added within the last 30 days.
    if (!empty($product['created_at'])) {
        $daysOld = (strtotime('now') - strtotime($product['created_at'])) / 86400;
        if ($daysOld <= 30) {
            return 'New';
        }
    }

    return null;
}


/* ==========================================
   FORMAT THE "PURPOSE" FIELD FOR DISPLAY
   Turns "Confidence, Protection, Focus" into
   "Confidence • Protection • Focus"
========================================== */

function format_purpose_bullets(?string $purpose): string
{
    if (empty($purpose)) {
        return '';
    }

    $parts = array_map('trim', explode(',', $purpose));

    return implode(' • ', $parts);
}


/* ==========================================
   HOMEPAGE: GET PRODUCTS IN A COLLECTION
   e.g. get_collection_products('career-success')
========================================== */

function get_collection_products(string $collectionSlug): array
{
    $stmt = db()->prepare(
        'SELECT p.*
         FROM products p
         INNER JOIN collection_products cp ON cp.product_id = p.id
         INNER JOIN collections col ON col.id = cp.collection_id
         WHERE col.slug = ?
           AND col.status = "active"
           AND p.status = "active"
         ORDER BY cp.display_order ASC'
    );
    $stmt->execute([$collectionSlug]);

    return $stmt->fetchAll();
}


/* ==========================================
   PRODUCT DETAIL PAGE: GET ONE PRODUCT BY SLUG
   Returns the product row (with its category
   name joined in), or null if not found /
   not active.
========================================== */

function get_product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.status = "active"
         LIMIT 1'
    );
    $stmt->execute([$slug]);

    $product = $stmt->fetch();

    return $product ?: null;
}


/* ==========================================
   PRODUCT DETAIL PAGE: GET ALL IMAGES
   (for the gallery + thumbnails). The
   primary image always comes first.
========================================== */

function get_product_images(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT file_path, alt_text, is_primary
         FROM product_images
         WHERE product_id = ?
         ORDER BY is_primary DESC, display_order ASC'
    );
    $stmt->execute([$productId]);

    $images = $stmt->fetchAll();

    // No images uploaded yet - show the placeholder instead of an
    // empty gallery.
    if (empty($images)) {
        $images = [[
            'file_path'  => PRODUCT_IMAGE_PLACEHOLDER,
            'alt_text'   => null,
            'is_primary' => 1,
        ]];
    }

    return $images;
}


/* ==========================================
   PRODUCT DETAIL PAGE: RELATED PRODUCTS
   Same category, excludes the current
   product, 4 max.
========================================== */

function get_related_products(int $categoryId, int $excludeProductId, int $limit = 4): array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.category_id = ?
           AND p.id != ?
           AND p.status = "active"
         ORDER BY p.display_order ASC, p.created_at DESC
         LIMIT ' . $limit
    );
    $stmt->execute([$categoryId, $excludeProductId]);

    return $stmt->fetchAll();
}


/* ==========================================
   DISCOUNT PERCENTAGE
   Returns e.g. 15 for a product that is 15%
   off, or null if there's no discount.
========================================== */

function get_discount_percentage(float $mrp, float $sellPrice): ?int
{
    if ($mrp <= 0 || $sellPrice >= $mrp) {
        return null;
    }

    return (int) round((($mrp - $sellPrice) / $mrp) * 100);
}


/* ==========================================
   SHOP: GET CATEGORIES THAT HAVE AT LEAST
   ONE ACTIVE PRODUCT (for the filter dropdown)
========================================== */

function get_shop_categories(): array
{
    return db()->query(
        'SELECT c.id, c.name, c.slug
         FROM categories c
         INNER JOIN products p ON p.category_id = c.id
         WHERE p.status = "active"
         GROUP BY c.id
         ORDER BY c.display_order ASC, c.name ASC'
    )->fetchAll();
}


/* ==========================================
   SHOP: GET PRODUCTS (FILTERED, SORTED,
   PAGINATED)
   -------------------------------------------------
   $filters can contain:
     'category' => category slug, or 'all'
     'price'    => '0-299' | '300-499' | '500-999' | '1000' | 'all'
     'sort'     => 'featured' | 'newest' | 'price-low' | 'price-high' | 'name'

   Returns ['products' => [...], 'total' => int]
========================================== */

function get_shop_products(array $filters, int $page, int $perPage): array
{
    $where  = ['p.status = "active"'];
    $params = [];

    /* ---------- Category filter ---------- */

    if (!empty($filters['category']) && $filters['category'] !== 'all') {
        $where[]  = 'c.slug = ?';
        $params[] = $filters['category'];
    }

    /* ---------- Price bracket filter ---------- */

    switch ($filters['price'] ?? 'all') {

        case '0-299':
            $where[] = 'p.sell_price BETWEEN 0 AND 299';
            break;

        case '300-499':
            $where[] = 'p.sell_price BETWEEN 300 AND 499';
            break;

        case '500-999':
            $where[] = 'p.sell_price BETWEEN 500 AND 999';
            break;

        case '1000':
            $where[] = 'p.sell_price >= 1000';
            break;

        // 'all' (or anything unrecognised) - no price filter applied.
    }

    /* ---------- Concern filter ---------- */

    // Matches against products.purpose (the comma-separated intent
    // list on the product database sheet, e.g. "Confidence,
    // Protection, Focus"). LIKE is used deliberately - purpose is
    // free-form text rather than a fixed vocabulary. LIKE wildcards
    // in the incoming value are escaped so a crafted value can't turn
    // this into a match-all.
    if (!empty($filters['concern']) && $filters['concern'] !== 'all') {
        $concern = str_replace(['%', '_'], ['\\%', '\\_'], $filters['concern']);
        $where[] = 'LOWER(p.purpose) LIKE LOWER(?)';
        $params[] = '%' . $concern . '%';
    }

    /* ---------- Zodiac filter ---------- */

    // products.zodiac is a plain sign name, but can hold a comma
    // separated list on some rows, so the exact match is backed by a
    // LIKE fallback (case-insensitive via LOWER on both sides).
    if (!empty($filters['zodiac']) && $filters['zodiac'] !== 'all') {
        $zodiac = str_replace(['%', '_'], ['\\%', '\\_'], $filters['zodiac']);
        $where[] = 'p.zodiac IS NOT NULL AND (LOWER(p.zodiac) = LOWER(?) OR LOWER(p.zodiac) LIKE LOWER(?))';
        array_push($params, $zodiac, '%' . $zodiac . '%');
    }

    /* ---------- Free-text search (Phase 6) ---------- */

    // The header search icon now points at shop.php?q=..., which reuses
    // this same shop query rather than a separate search engine. Matches
    // across product name, category name, short/full description,
    // purpose/concern list and zodiac sign - the fields a customer is
    // most likely to type. LIKE wildcards in the incoming term are
    // escaped (same pattern as the concern/zodiac filters above) so a
    // crafted value can't be widened into a match-all. All values go
    // through prepared-statement parameters.
    if (!empty($filters['q'])) {
        $term   = str_replace(['%', '_'], ['\\%', '\\_'], trim($filters['q']));
        $where[] = '(
            LOWER(p.name) LIKE LOWER(?)
            OR LOWER(c.name) LIKE LOWER(?)
            OR LOWER(p.short_description) LIKE LOWER(?)
            OR LOWER(p.full_description) LIKE LOWER(?)
            OR LOWER(p.purpose) LIKE LOWER(?)
            OR LOWER(p.zodiac) LIKE LOWER(?)
        )';
        array_push(
            $params,
            '%' . $term . '%', '%' . $term . '%',
            '%' . $term . '%', '%' . $term . '%',
            '%' . $term . '%', '%' . $term . '%'
        );
    }

    $whereSql = implode(' AND ', $where);

    /* ---------- Sorting ---------- */

    $orderBy = match ($filters['sort'] ?? 'featured') {
        'newest'     => 'p.created_at DESC',
        'price-low'  => 'p.sell_price ASC',
        'price-high' => 'p.sell_price DESC',
        'name'       => 'p.name ASC',
        default      => 'p.featured DESC, p.display_order ASC', // 'featured'
    };

    /* ---------- Count total matches (for pagination) ---------- */

    $countStmt = db()->prepare(
        "SELECT COUNT(*)
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE {$whereSql}"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    /* ---------- Fetch this page of products ---------- */

    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare(
        "SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE {$whereSql}
         ORDER BY {$orderBy}
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    return [
        'products' => $products,
        'total'    => $total,
    ];
}


/* ==========================================
   HOMEPAGE: FEATURED PRODUCTS
   Reuses the existing products.featured flag
   (already admin-toggleable on the product
   form, already used as the default shop
   sort order) - nothing new to curate.
========================================== */

function get_featured_products(int $limit = 8): array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.featured = 1
           AND p.status = "active"
         ORDER BY p.display_order ASC, p.created_at DESC
         LIMIT ' . $limit
    );
    $stmt->execute();

    return $stmt->fetchAll();
}


/* ==========================================
   HOMEPAGE: BEST SELLING PRODUCTS
   -------------------------------------------------------------------
   Ranked from REAL order data (order_items joined to orders),
   summing quantity sold per product across orders that were not
   cancelled and did not fail payment - this is a true sales signal,
   not an opinion. payment_status IN ('pending','paid') deliberately
   keeps legitimate COD orders (which start 'pending' and may stay
   that way until delivery) while excluding 'failed' and 'refunded'
   sales that were reversed.

   FALLBACK: a brand-new store has no order history yet, so real
   sales data alone would leave this section empty. If there are
   fewer than $limit real sellers, the remainder is filled from
   products.is_bestseller (an existing admin-set flag, already used
   for the "Best Seller" ribbon badge elsewhere) - this is the
   store's own explicit curation, not invented data, and is used
   only to pad out real results, never to override them. A product
   already selected by real sales is never duplicated from the
   fallback. If neither source has anything, an empty array is
   returned - the caller shows an honest empty state rather than
   fabricating a "Best Sellers" list.
========================================== */

function get_best_selling_products(int $limit = 8): array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, SUM(oi.quantity) AS units_sold
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         INNER JOIN products p ON p.id = oi.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.status = "active"
           AND o.order_status != "cancelled"
           AND o.payment_status IN ("pending", "paid")
         GROUP BY p.id
         ORDER BY units_sold DESC, p.display_order ASC
         LIMIT ' . $limit
    );
    $stmt->execute();
    $products = $stmt->fetchAll();

    if (count($products) >= $limit) {
        return $products;
    }

    $alreadyPicked = array_column($products, 'id');
    $remaining     = $limit - count($products);

    $placeholders = empty($alreadyPicked) ? '' : (' AND p.id NOT IN (' . implode(',', array_fill(0, count($alreadyPicked), '?')) . ')');

    $fallbackStmt = db()->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.is_bestseller = 1
           AND p.status = "active"'
        . $placeholders .
        ' ORDER BY p.display_order ASC, p.created_at DESC
         LIMIT ' . $remaining
    );
    $fallbackStmt->execute($alreadyPicked);

    return array_merge($products, $fallbackStmt->fetchAll());
}
