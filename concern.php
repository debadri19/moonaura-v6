<?php
/* ===================================================================
   INDIVIDUAL CONCERN PAGE
   -------------------------------------------------------------------
   Dynamically lists every active product assigned to one Concern
   Category (?slug=...). Reuses the exact shop.php/wishlist.php
   product-card markup (wishlist toggle, Add to Cart, pricing) - no
   new card system. Empty state if the concern has no products yet
   (never a blank page).
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-functions.php';
require_once __DIR__ . '/includes/product-image-variants.php';
require_once __DIR__ . '/includes/wishlist-functions.php';
require_once __DIR__ . '/includes/concern-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';
require_once __DIR__ . '/includes/meta-pixel-functions.php';

$slug    = trim((string) ($_GET['slug'] ?? ''));
$concern = $slug !== '' ? get_concern_category_by_slug($slug) : null;

$concernProducts = $concern ? get_products_by_concern((int) $concern['id']) : [];

// Add to Cart / Wishlist on this page should bring the customer right
// back here, same "redirect_to" convention every other product-card
// already uses (see wishlist.php / shop.php).
$currentPageUrl = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $concern ? h($concern['name']) . ' | MoonAura Crystals' : 'Shop By Concern | MoonAura Crystals' ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/home.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="shop-by-concern" id="concern-listing">

        <div class="container">

            <?php if (!$concern): ?>

                <div class="section-heading">
                    <span class="section-subtitle">Shop By Concern</span>
                    <h1 class="section-title">Concern Not Found</h1>
                    <p class="section-description">
                        We couldn't find that concern category.
                    </p>
                </div>

                <div class="empty-state">
                    <i class="fa-solid fa-layer-group"></i>
                    <p>That concern doesn't exist or is no longer available.</p>
                    <a href="concerns.php" class="btn btn-primary">View All Concerns</a>
                </div>

            <?php else: ?>

                <div class="section-heading">
                    <span class="section-subtitle">Shop By Concern</span>
                    <span class="concern-icon concern-icon-inline">
                        <i class="fa-solid <?= h(get_concern_icon_class($concern['slug'])) ?>"></i>
                    </span>
                    <h1 class="section-title"><?= h($concern['name']) ?></h1>
                    <p class="section-description">
                        Crystals curated for <?= h($concern['name']) ?>.
                    </p>
                </div>

                <?php if (empty($concernProducts)): ?>

                    <div class="empty-state">
                        <i class="fa-solid fa-gem"></i>
                        <p>No products are assigned to this concern yet.</p>
                        <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
                    </div>

                <?php else: ?>

                    <div class="product-grid">

                        <?php foreach ($concernProducts as $product): ?>

                            <?php
                                $badge      = get_product_badge($product);
                                $imagePath  = get_product_primary_image((int) $product['id']);
                                $purpose    = format_purpose_bullets($product['purpose']);
                                $inWishlist = is_in_wishlist((int) $product['id']);
                            ?>

                            <article class="product-card">

                                <?php if ($badge): ?>
                                    <span class="product-badge"><?= h($badge) ?></span>
                                <?php endif; ?>

                                <form method="post" action="wishlist-toggle.php" class="wishlist-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <input type="hidden" name="redirect_to" value="<?= h($currentPageUrl) ?>">
                                    <button
                                        type="submit"
                                        class="wishlist-btn<?= $inWishlist ? ' active' : '' ?>"
                                        aria-label="<?= $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>"
                                    >
                                        <i class="fa-<?= $inWishlist ? 'solid' : 'regular' ?> fa-heart"></i>
                                    </button>
                                </form>

                                <div class="product-image">
                                    <a href="product.php?slug=<?= h($product['slug']) ?>">
                                        <img
                                            src="<?= h(product_image_variant_url($imagePath, 'card')) ?>"
                                            alt="<?= h($product['name']) ?>"
                                            loading="lazy"
                                        >
                                    </a>
                                </div>

                                <div class="product-content">

                                    <span class="product-category">
                                        <?= h($product['category_name'] ?? '') ?>
                                    </span>

                                    <h3>
                                        <a href="product.php?slug=<?= h($product['slug']) ?>">
                                            <?= h($product['name']) ?>
                                        </a>
                                    </h3>

                                    <?php if ($purpose !== ''): ?>
                                        <p class="product-benefits"><?= h($purpose) ?></p>
                                    <?php else: ?>
                                        <p class="product-benefits">&nbsp;</p>
                                    <?php endif; ?>

                                    <div class="product-price">
                                        <span class="current-price">
                                            <?= h(format_price((float) $product['sell_price'])) ?>
                                        </span>
                                        <?php if ((float) $product['mrp'] > (float) $product['sell_price']): ?>
                                            <span class="old-price">
                                                <?= h(format_price((float) $product['mrp'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="product-actions">

                                        <a href="product.php?slug=<?= h($product['slug']) ?>" class="product-btn">
                                            View Product
                                        </a>

                                        <form method="post" action="cart-add.php" style="display: contents;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="redirect_to" value="<?= h($currentPageUrl) ?>">
                                            <button class="cart-btn" type="submit" aria-label="Add to Cart">
                                                <i class="fa-solid fa-cart-plus"></i>
                                            </button>
                                        </form>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>

    </section>

    <?php
    if ($concern && !empty($concernProducts)) {
        ga4_queue_event('view_item_list', [
            'item_list_id'   => 'concern_' . (string) ($concern['slug'] ?? $concern['id']),
            'item_list_name' => (string) $concern['name'],
            'items'          => ga4_items_from_products($concernProducts),
            'currency'       => ga4_currency(),
        ]);

        // Meta Phase 2: one ViewContent (product_group) for this
        // concern/category listing per page load.
        meta_pixel_track_list_view(
            ga4_items_from_products($concernProducts),
            (string) $concern['name']
        );
    }
    ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
