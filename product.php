<?php
/* ===================================================================
   PRODUCT DETAIL PAGE
   -------------------------------------------------------------------
   Loads one product by its slug (e.g. product.php?slug=tiger-eye-bracelet)
   and displays its full details, image gallery, and related products
   from the same category.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-functions.php';
require_once __DIR__ . '/includes/product-image-variants.php';
require_once __DIR__ . '/includes/wishlist-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';
require_once __DIR__ . '/includes/meta-pixel-functions.php';

$slug = trim($_GET['slug'] ?? '');

$product = $slug !== '' ? get_product_by_slug($slug) : null;


/* ==========================================
   PRODUCT NOT FOUND -> PROPER 404
   -------------------------------------------------
   Sends a real 404 HTTP status (important for SEO
   and for not showing PHP errors/blank pages) and
   a friendly message, using the site's normal
   header/footer so it still looks like the site.
========================================== */

if (!$product) {

    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- Favicon -->
        <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
        <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
        <title>Product Not Found | MoonAura Crystals</title>
        <meta name="robots" content="noindex, follow">

        <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
        <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
        <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
        <link rel="stylesheet" href="<?= versioned_asset('assets/css/product.css') ?>">
    </head>
    <body>

        <?php include __DIR__ . '/includes/header.php'; ?>

        <section class="product-not-found">
            <div class="container">
                <h1>Product Not Found</h1>
                <p>Sorry, we couldn't find the product you're looking for. It may have been removed or the link may be incorrect.</p>
                <a href="shop.php" class="btn btn-primary">Back to Shop</a>
            </div>
        </section>

        <?php include __DIR__ . '/includes/footer.php'; ?>

    </body>
    </html>
    <?php
    exit;
}


/* ==========================================
   GATHER EVERYTHING THE PAGE NEEDS
========================================== */

$images           = get_product_images((int) $product['id']);
$relatedProducts  = get_related_products((int) $product['category_id'], (int) $product['id'], 4);
$discountPercent  = get_discount_percentage((float) $product['mrp'], (float) $product['sell_price']);
$fallbackBadge    = get_product_badge($product); // "Best Seller" / "New" / null (SALE is handled separately below via $discountPercent)
$purposeText      = format_purpose_bullets($product['purpose']);

/* ==========================================
   #30: STOCK AVAILABILITY
   -------------------------------------------------
   Reuses the existing inventory source of truth
   (products.stock_quantity / products.stock_status -
   the same columns validate_cart_stock() and the
   admin Stock Status field already read/write) -
   no second inventory system, purely a display-layer
   read of data that already exists. "Out of stock"
   is the OR of both signals so a manually-flagged
   out_of_stock product is treated the same as a
   product that's simply run out, exactly as #30
   specifies.
========================================== */

$stockQuantity = (int) ($product['stock_quantity'] ?? 0);
$stockStatus   = $product['stock_status'] ?? 'in_stock';
$isOutOfStock  = $stockQuantity <= 0 || $stockStatus === 'out_of_stock';
$isLowStock    = !$isOutOfStock && $stockQuantity >= 1 && $stockQuantity <= 3;


/* ==========================================
   SEO METADATA
========================================== */

$pageTitle = $product['meta_title'] ?: ($product['name'] . ' | MoonAura Crystals');

$metaDescription = $product['meta_description']
    ?: ($product['short_description'] ?: mb_substr(strip_tags((string) $product['full_description']), 0, 155));

$canonicalUrl = rtrim(SITE_URL, '/') . '/product.php?slug=' . urlencode($product['slug']);

$ogImageUrl = asset_url($images[0]['file_path']);


/* ==========================================
   JSON-LD PRODUCT SCHEMA (structured data)
========================================== */

$productSchema = [
    '@context'    => 'https://schema.org/',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'image'       => array_map(
        fn ($image) => asset_url($image['file_path']),
        $images
    ),
    'description' => $metaDescription,
    'sku'         => $product['sku'],
    'brand'       => [
        '@type' => 'Brand',
        'name'  => 'MoonAura Crystals',
    ],
    'offers' => [
        '@type'         => 'Offer',
        'url'           => $canonicalUrl,
        'priceCurrency' => 'INR',
        'price'         => number_format((float) $product['sell_price'], 2, '.', ''),
        'availability'  => $isOutOfStock
            ? 'https://schema.org/OutOfStock'
            : 'https://schema.org/InStock',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">

    <!-- ==========================================
         SEO METADATA
    ========================================== -->

    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($metaDescription) ?>">
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <meta property="og:description" content="<?= h($metaDescription) ?>">
    <meta property="og:image" content="<?= h($ogImageUrl) ?>">
    <meta property="og:url" content="<?= h($canonicalUrl) ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($pageTitle) ?>">
    <meta name="twitter:description" content="<?= h($metaDescription) ?>">
    <meta name="twitter:image" content="<?= h($ogImageUrl) ?>">

    <!-- Product Schema (JSON-LD) -->
    <script type="application/ld+json">
        <?= json_encode($productSchema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- CSS - reusing the site's existing stylesheets, plus product.css for the new gallery/breadcrumb/specs -->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/home.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/product.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- ==========================================
         BREADCRUMB
    ========================================== -->

    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span>&rsaquo;</span>
            <a href="shop.php">Shop</a>
            <?php if (!empty($product['category_name'])): ?>
                <span>&rsaquo;</span>
                <a href="shop.php?category=<?= h($product['category_slug']) ?>"><?= h($product['category_name']) ?></a>
            <?php endif; ?>
            <span>&rsaquo;</span>
            <span class="breadcrumb-current"><?= h($product['name']) ?></span>
        </nav>
    </div>

    <!-- ==========================================
         PRODUCT DETAIL
    ========================================== -->

    <section class="product-detail">

        <div class="container">

            <div class="product-detail-grid">

                <!-- ==========================================
                     IMAGE GALLERY
                ========================================== -->

                <div class="product-gallery">

                    <div class="product-gallery-main">
                        <form method="post" action="wishlist-toggle.php" class="wishlist-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <input type="hidden" name="redirect_to" value="product.php?slug=<?= h($product['slug']) ?>">
                            <?php $inWishlist = is_in_wishlist((int) $product['id']); ?>
                            <button
                                type="submit"
                                class="wishlist-btn<?= $inWishlist ? ' active' : '' ?>"
                                aria-label="<?= $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>"
                            >
                                <i class="fa-<?= $inWishlist ? 'solid' : 'regular' ?> fa-heart"></i>
                            </button>
                        </form>
                        <img
                            id="productMainImage"
                            src="<?= h(asset_url($images[0]['file_path'])) ?>"
                            alt="<?= h($images[0]['alt_text'] ?: $product['name']) ?>"
                        >
                    </div>

                    <?php if (count($images) > 1): ?>

                        <div class="product-gallery-thumbs">

                            <?php foreach ($images as $index => $image): ?>

                                <button
                                    type="button"
                                    class="product-gallery-thumb <?= $index === 0 ? 'active' : '' ?>"
                                    data-image="<?= h(asset_url($image['file_path'])) ?>"
                                    data-alt="<?= h($image['alt_text'] ?: $product['name']) ?>"
                                >
                                    <img
                                        src="<?= h(product_image_variant_url($image['file_path'], 'sm')) ?>"
                                        alt="<?= h($image['alt_text'] ?: $product['name']) ?>"
                                    >
                                </button>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- ==========================================
                     PRODUCT INFO
                ========================================== -->

                <div class="product-info">

                    <?php if (!empty($product['category_name'])): ?>
                        <span class="product-info-category"><?= h($product['category_name']) ?></span>
                    <?php endif; ?>

                    <h1><?= h($product['name']) ?></h1>

                    <div class="product-info-price">

                        <?php if ($discountPercent !== null): ?>
                            <span class="discount-badge"><?= $discountPercent ?>% OFF</span>
                        <?php elseif ($fallbackBadge): ?>
                            <span class="discount-badge"><?= h($fallbackBadge) ?></span>
                        <?php endif; ?>

                        <span class="sale-price">
                            <?= h(format_price((float) $product['sell_price'])) ?>
                        </span>

                        <?php if ($discountPercent !== null): ?>
                            <span class="old-price">
                                <?= h(format_price((float) $product['mrp'])) ?>
                            </span>
                        <?php endif; ?>

                    </div>

                    <?php if ($purposeText !== ''): ?>
                        <p class="product-info-purpose"><?= h($purposeText) ?></p>
                    <?php endif; ?>

                    <?php if ($isOutOfStock): ?>

                        <!-- #30: out of stock - no purchase forms at all.
                             No <form>, no submit button, no action, no
                             AJAX handler exists for these markers - this
                             is a plain non-interactive status element,
                             not a disabled control sitting on a live
                             form. -->
                        <div class="product-info-actions product-info-actions-unavailable">
                            <span class="btn btn-disabled" aria-disabled="true">Out of Stock</span>
                        </div>

                    <?php else: ?>

                        <?php if ($isLowStock): ?>
                            <p class="product-stock-message product-stock-low">Only <?= $stockQuantity ?> left in stock</p>
                        <?php endif; ?>

                        <div class="product-info-actions">
                            <form method="post" action="cart-add.php" style="display: contents;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <input type="hidden" name="redirect_to" value="product.php?slug=<?= h($product['slug']) ?>">
                                <button type="submit" class="btn btn-primary">Add to Cart</button>
                            </form>
                            <form method="post" action="buy-now.php" style="display: contents;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-outline">Buy Now</button>
                            </form>
                        </div>

                    <?php endif; ?>

                    <!-- ==========================================
                         TRUST BADGES
                         Certificate Included is per-product
                         (products.certificate_included). GST invoice
                         remains store-wide. Missing column defaults
                         to shown so pre-migration pages keep the
                         previous always-visible behaviour.
                    ========================================== -->

                    <div class="product-trust-badges">

                        <?php if (!array_key_exists('certificate_included', $product) || !empty($product['certificate_included'])): ?>
                            <div class="product-trust-badge">
                                <i class="fa-solid fa-certificate"></i>
                                Certificate of Authenticity Included
                            </div>
                        <?php endif; ?>

                        <div class="product-trust-badge">
                            <i class="fa-solid fa-file-invoice"></i>
                            GST Invoice Available on All Orders
                        </div>

                    </div>

                    <!-- ==========================================
                         SPECIFICATIONS
                    ========================================== -->

                    <div class="product-specs">

                        <h2 class="product-specs-heading">Specifications</h2>

                        <?php if (!empty($product['chakra'])): ?>
                            <div class="product-spec-item">
                                <span class="product-spec-label">Chakra</span>
                                <span class="product-spec-value"><?= h($product['chakra']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($product['zodiac'])): ?>
                            <div class="product-spec-item">
                                <span class="product-spec-label">Zodiac</span>
                                <span class="product-spec-value"><?= h($product['zodiac']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($product['crystal_type'])): ?>
                            <div class="product-spec-item">
                                <span class="product-spec-label">Material</span>
                                <span class="product-spec-value"><?= h($product['crystal_type']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($product['variant'])): ?>
                            <div class="product-spec-item">
                                <span class="product-spec-label">Size</span>
                                <span class="product-spec-value"><?= h($product['variant']) ?></span>
                            </div>
                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <!-- ==========================================
                 DESCRIPTION / BENEFITS / CARE
            ========================================== -->

            <?php if (!empty($product['full_description'])): ?>
                <div class="product-detail-section">
                    <h2>Product Description</h2>
                    <p><?= nl2br(h($product['full_description'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($product['primary_benefits'])): ?>
                <div class="product-detail-section">
                    <h2>Crystal Benefits</h2>
                    <p><?= nl2br(h($product['primary_benefits'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($product['care_instructions'])): ?>
                <div class="product-detail-section">
                    <h2>Care Instructions</h2>
                    <p><?= nl2br(h($product['care_instructions'])) ?></p>
                </div>
            <?php endif; ?>

        </div>

    </section>

    <!-- ==========================================
         RELATED PRODUCTS
    ========================================== -->

    <?php if (!empty($relatedProducts)): ?>

        <div class="related-products-divider" aria-hidden="true"></div>

        <section class="career-collection product-related-products">

            <div class="container">

                <div class="section-heading">
                    <h2 class="section-title">Related Products</h2>
                </div>

                <div class="product-grid">

                    <?php foreach ($relatedProducts as $relatedProduct): ?>

                        <?php
                            $relatedBadge     = get_product_badge($relatedProduct);
                            $relatedImagePath = get_product_primary_image((int) $relatedProduct['id']);
                        ?>

                        <div class="product-card">

                            <?php if ($relatedBadge): ?>
                                <span class="product-badge"><?= h($relatedBadge) ?></span>
                            <?php endif; ?>

                            <div class="product-image">
                                <a href="product.php?slug=<?= h($relatedProduct['slug']) ?>">
                                    <img
                                        src="<?= h(product_image_variant_url($relatedImagePath, 'card')) ?>"
                                        alt="<?= h($relatedProduct['name']) ?>"
                                        loading="lazy"
                                    >
                                </a>
                            </div>

                            <div class="product-content">

                                <h3 class="product-title">
                                    <a href="product.php?slug=<?= h($relatedProduct['slug']) ?>">
                                        <?= h($relatedProduct['name']) ?>
                                    </a>
                                </h3>

                                <div class="price">

                                    <?php if ((float) $relatedProduct['mrp'] > (float) $relatedProduct['sell_price']): ?>
                                        <span class="old-price"><?= h(format_price((float) $relatedProduct['mrp'])) ?></span>
                                    <?php endif; ?>

                                    <span class="sale-price"><?= h(format_price((float) $relatedProduct['sell_price'])) ?></span>

                                </div>

                                <div class="product-actions">
                                    <a href="product.php?slug=<?= h($relatedProduct['slug']) ?>" class="product-btn">
                                        View Product
                                    </a>
                                    <form method="post" action="cart-add.php" style="display: contents;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $relatedProduct['id'] ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="redirect_to" value="product.php?slug=<?= h($product['slug']) ?>">
                                        <button class="cart-btn" type="submit" aria-label="Add to Cart">
                                            <i class="fa-solid fa-cart-plus"></i>
                                        </button>
                                    </form>
                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </section>

    <?php endif; ?>

    <?php
    $viewItem = ga4_item_from_product($product, 1);
    ga4_queue_event('view_item', [
        'currency' => ga4_currency(),
        'value'    => (float) $viewItem['price'],
        'items'    => [$viewItem],
    ]);
    if (!empty($relatedProducts)) {
        ga4_queue_event('view_item_list', [
            'item_list_id'   => 'related',
            'item_list_name' => 'Related Products',
            'items'          => ga4_items_from_products($relatedProducts),
            'currency'       => ga4_currency(),
        ]);
    }

    // Meta Phase 2: ViewContent for this product detail page. Uses the
    // same $viewItem the GA4 event above already built - no new query.
    meta_pixel_track_product_view($viewItem);
    ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
    <script src="<?= versioned_asset('assets/js/product.js') ?>"></script>

</body>
</html>
