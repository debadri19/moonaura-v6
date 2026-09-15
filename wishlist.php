<?php
/* ===================================================================
   WISHLIST PAGE
   -------------------------------------------------------------------
   Session-based, no login required - see includes/wishlist-functions.php
   for why. Page structure mirrors cart.php (h1 + container, same
   empty-state shape); the saved items themselves reuse shop.php's
   exact .product-card markup and assets/css/home.css styling, so a
   saved product looks identical here to how it looks on the shop
   page - same badge, same wishlist heart (already active), same
   Add to Cart / Buy Now buttons.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-functions.php';
require_once __DIR__ . '/includes/product-image-variants.php';
require_once __DIR__ . '/includes/wishlist-functions.php';

$wishlistItems = get_wishlist_items_with_details();

// Add to Cart on this page should bring the customer right back here,
// same "redirect_to" convention every other product-card already uses.
$currentPageUrl = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Wishlist | MoonAura Crystals</title>
    <meta name="robots" content="noindex, follow">

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
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/wishlist.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="wishlist-page">

        <div class="container">

            <h1>Your Wishlist</h1>

            <?php if (empty($wishlistItems)): ?>

                <!-- ==========================================
                     EMPTY WISHLIST
                ========================================== -->
                <div class="empty-state wishlist-empty-state">
                    <span class="wishlist-empty-icon" aria-hidden="true">
                        <i class="fa-solid fa-heart"></i>
                    </span>
                    <p>Your wishlist is empty.</p>
                    <span class="wishlist-empty-divider" aria-hidden="true"></span>
                    <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
                </div>

            <?php else: ?>

                <p class="wishlist-count-text">
                    <?= count($wishlistItems) ?> saved item<?= count($wishlistItems) === 1 ? '' : 's' ?>
                </p>

                <div class="product-grid">

                    <?php foreach ($wishlistItems as $wishlistItem): ?>

                        <?php
                            $product   = $wishlistItem['product'];
                            $badge     = get_product_badge($product);
                            $imagePath = get_product_primary_image((int) $product['id']);
                            $purpose   = format_purpose_bullets($product['purpose']);
                        ?>

                        <article class="product-card">

                            <?php if ($badge): ?>
                                <span class="product-badge"><?= h($badge) ?></span>
                            <?php endif; ?>

                            <form method="post" action="wishlist-toggle.php" class="wishlist-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <input type="hidden" name="redirect_to" value="<?= h($currentPageUrl) ?>">
                                <button type="submit" class="wishlist-btn active" aria-label="Remove from Wishlist">
                                    <i class="fa-solid fa-heart"></i>
                                </button>
                            </form>

                            <div class="product-image">
                                <a href="product.php?slug=<?= h($product['slug']) ?>">
                                    <img src="<?= h(product_image_variant_url($imagePath, 'card')) ?>" alt="<?= h($product['name']) ?>">
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
                                    <p><?= h($purpose) ?></p>
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

                                <?php if (!$wishlistItem['is_available']): ?>

                                    <p class="wishlist-unavailable">Currently unavailable</p>

                                <?php else: ?>

                                    <div class="product-actions">

                                        <form method="post" action="cart-add.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="redirect_to" value="<?= h($currentPageUrl) ?>">
                                            <button type="submit" class="cart-btn" aria-label="Add to Cart">
                                                <i class="fa-solid fa-cart-shopping"></i>
                                            </button>
                                        </form>

                                        <a href="product.php?slug=<?= h($product['slug']) ?>" class="product-btn">
                                            View
                                        </a>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
