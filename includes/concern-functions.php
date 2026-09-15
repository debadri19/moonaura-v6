<?php
/* ===================================================================
   CONCERN CATEGORY FUNCTIONS
   -------------------------------------------------------------------
   Database queries for the "Concern Category" taxonomy (Homepage
   "Shop By Concern", /concerns listing pages, admin product form).

   IMPORTANT: this is a SEPARATE classification from products.purpose
   (free-text product-benefit copy shown on the product detail page -
   see includes/product-functions.php's format_purpose_bullets()).
   Nothing in this file reads or writes products.purpose.
=================================================================== */

require_once __DIR__ . '/db.php';


/* ==========================================
   CONCERN ICON MAPPING
   -------------------------------------------------------------------
   Single source of truth for which Font Awesome icon represents each
   concern, so /concerns.php and concern.php always agree (instead of
   each hardcoding its own icon). All classes below are verified
   present in Font Awesome Free's Solid set (this project loads FA
   6.7.2 free via CDN - no Pro-only icons here, e.g. NOT fa-sparkles,
   which is Pro-only).

   Does NOT affect the homepage "Shop By Concern" teaser cards in
   index.php - those keep their own hardcoded icons as-is.

   Unknown/new concern slugs fall back to fa-gem (the same icon this
   mapping replaces as the default), so a future 14th preset never
   renders a blank icon.
========================================== */

function get_concern_icon_class(string $slug): string
{
    $icons = [
        'love-and-relationships' => 'fa-heart',
        'career-success'         => 'fa-briefcase',
        'education-and-focus'    => 'fa-graduation-cap',
        'wealth-and-prosperity'  => 'fa-coins',
        'protection'             => 'fa-shield-halved',
        'confidence-and-courage' => 'fa-bolt',
        'peace-and-healing'      => 'fa-heart-pulse',
        'spiritual-growth'       => 'fa-wand-magic-sparkles',
        'evil-eye-protection'    => 'fa-eye',
        'money-and-prosperity'   => 'fa-sack-dollar',
        'peace-and-calm'         => 'fa-moon',
        'positive-energy'        => 'fa-sun',
        'study-and-focus'        => 'fa-book',
    ];

    return $icons[$slug] ?? 'fa-gem';
}


/* ==========================================
   GET ALL ACTIVE CONCERN CATEGORIES
   (Homepage "Shop By Concern" cards, admin
   product form checkbox list)
========================================== */

function get_active_concern_categories(): array
{
    return db()->query(
        'SELECT id, name, slug
         FROM concern_categories
         WHERE status = "active"
         ORDER BY display_order ASC'
    )->fetchAll();
}


/* ==========================================
   GET ALL ACTIVE CONCERN CATEGORIES, WITH
   THE COUNT OF ACTIVE PRODUCTS IN EACH
   (Full /concerns.php listing page)
========================================== */

function get_concern_categories_with_counts(): array
{
    return db()->query(
        'SELECT cc.id, cc.name, cc.slug,
                COUNT(pc.product_id) AS product_count
         FROM concern_categories cc
         LEFT JOIN product_concerns pc ON pc.concern_category_id = cc.id
         LEFT JOIN products p ON p.id = pc.product_id AND p.status = "active"
         WHERE cc.status = "active"
         GROUP BY cc.id, cc.name, cc.slug, cc.display_order
         ORDER BY cc.display_order ASC'
    )->fetchAll();
}


/* ==========================================
   GET ONE CONCERN CATEGORY BY SLUG
   (Individual concern listing page)
========================================== */

function get_concern_category_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, slug
         FROM concern_categories
         WHERE slug = ? AND status = "active"
         LIMIT 1'
    );
    $stmt->execute([$slug]);

    $concern = $stmt->fetch();

    return $concern ?: null;
}


/* ==========================================
   GET ALL ACTIVE PRODUCTS ASSIGNED TO A
   CONCERN CATEGORY
   (Individual concern listing page)
========================================== */

function get_products_by_concern(int $concernCategoryId): array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p
         INNER JOIN product_concerns pc ON pc.product_id = p.id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE pc.concern_category_id = ?
           AND p.status = "active"
         ORDER BY p.display_order ASC, p.created_at DESC'
    );
    $stmt->execute([$concernCategoryId]);

    return $stmt->fetchAll();
}


/* ==========================================
   GET THE CONCERN CATEGORY IDs A PRODUCT IS
   CURRENTLY ASSIGNED TO
   (Admin product form - pre-check the boxes
   when editing)
========================================== */

function get_product_concern_ids(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT concern_category_id FROM product_concerns WHERE product_id = ?'
    );
    $stmt->execute([$productId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}


/* ==========================================
   SAVE A PRODUCT'S CONCERN CATEGORY
   ASSIGNMENTS
   (Admin product form - called after the
   product itself is saved, for both create
   and edit. Replaces the full assignment set
   with $concernCategoryIds - an empty array
   clears all assignments.)
========================================== */

function save_product_concerns(int $productId, array $concernCategoryIds): void
{
    $concernCategoryIds = array_values(array_unique(array_map('intval', $concernCategoryIds)));

    $db = db();

    $stmt = $db->prepare('DELETE FROM product_concerns WHERE product_id = ?');
    $stmt->execute([$productId]);

    if (empty($concernCategoryIds)) {
        return;
    }

    $insert = $db->prepare(
        'INSERT INTO product_concerns (product_id, concern_category_id) VALUES (?, ?)'
    );

    foreach ($concernCategoryIds as $concernCategoryId) {
        if ($concernCategoryId > 0) {
            $insert->execute([$productId, $concernCategoryId]);
        }
    }
}
