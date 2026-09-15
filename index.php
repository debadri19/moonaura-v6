<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-functions.php';
require_once __DIR__ . '/includes/product-image-variants.php';
require_once __DIR__ . '/includes/wishlist-functions.php';
require_once __DIR__ . '/includes/concern-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';

// Products for the new homepage discovery sections (Featured, Best
// Selling - see includes/product-functions.php for selection logic).
$featuredProducts    = get_featured_products(8);
$bestSellingProducts = get_best_selling_products(8);
$newsletterSuccess   = flash_get('newsletter_success');
$newsletterError     = flash_get('newsletter_error');

// The 12 zodiac signs already supported by the product system
// (products.zodiac / shop.php's existing zodiac filter) - reused
// as-is, not a second classification. Card labels only; each links
// to the existing shop.php?zodiac=... filter.
const HOMEPAGE_ZODIAC_SIGNS = [
    ['name' => 'Aries',       'glyph' => '♈'],
    ['name' => 'Taurus',      'glyph' => '♉'],
    ['name' => 'Gemini',      'glyph' => '♊'],
    ['name' => 'Cancer',      'glyph' => '♋'],
    ['name' => 'Leo',         'glyph' => '♌'],
    ['name' => 'Virgo',       'glyph' => '♍'],
    ['name' => 'Libra',       'glyph' => '♎'],
    ['name' => 'Scorpio',     'glyph' => '♏'],
    ['name' => 'Sagittarius', 'glyph' => '♐'],
    ['name' => 'Capricorn',   'glyph' => '♑'],
    ['name' => 'Aquarius',    'glyph' => '♒'],
    ['name' => 'Pisces',      'glyph' => '♓'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MoonAura Crystals | Natural Crystal Bracelets, Rings & Healing Stones</title>

    <!-- Favicon -->
    <link
        rel="icon"
        type="image/webp"
        href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>"
    >

    <!-- Apple Touch Icon -->
    <link
        rel="apple-touch-icon"
        href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>"
    >

    <!-- Swiper CSS -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
    >

    <!-- CSS Links-->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/home.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

</head>

<body>

    <!-- Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="page-content">

        <!-- ==================================================
            HERO SECTION
        ================================================== -->

        <section class="hero">

            <div class="container hero-container">

                <!-- =========================
                    HERO CONTENT
                ========================== -->

                <div class="hero-content">

                    <span class="hero-subtitle">

                        Authentic Natural Crystals

                    </span>

                    <h1 class="hero-title">

                        Discover the Power of <br>
                        <span>Natural Crystals</span>

                    </h1>

                    <p class="hero-description">

                        Authentic crystal bracelets, rings, pyramids and spiritual décor crafted to bring positivity, balance and elegance into your everyday life.

                    </p>

                    <div class="hero-buttons">

                        <a
                            href="shop.php"
                            class="btn btn-primary"
                        >

                            Shop Now

                        </a>

                    </div>


                </div>


                <!-- =========================
                    HERO IMAGE
                ========================== -->

                <div class="hero-image">

                    <img
                        src="<?= asset_url('assets/images/banners/hero-banner-wide.webp') ?>"
                        alt="MoonAura Crystal Collection"
                        loading="eager"
                    >

                </div>

            </div>

        </section>

        <!-- ==================================================
            FEATURED PRODUCTS
            (products.featured flag - existing, admin-toggleable)
        ================================================== -->

        <section class="home-products" id="featured-products">

            <div class="container">

                <div class="section-heading">
                    <span class="section-subtitle">Handpicked For You</span>
                    <h2 class="section-title">Featured Products</h2>
                    <p class="section-description">
                        A curated edit of our most-loved crystals, chosen for
                        their beauty and energy.
                    </p>
                </div>

                <div class="product-grid">

                    <?php if (empty($featuredProducts)): ?>

                        <p style="padding: 20px 0; color: var(--text-light);">
                            Featured products are coming soon.
                        </p>

                    <?php else: ?>

                        <?php foreach ($featuredProducts as $product): ?>

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
                                    <input type="hidden" name="redirect_to" value="index.php">
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

                                    <h3 class="product-title">
                                        <a href="product.php?slug=<?= h($product['slug']) ?>">
                                            <?= h($product['name']) ?>
                                        </a>
                                    </h3>

                                    <?php if ($purpose !== ''): ?>
                                        <p class="product-benefits">
                                            <?= h($purpose) ?>
                                        </p>
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

                                        <a
                                            href="product.php?slug=<?= h($product['slug']) ?>"
                                            class="product-btn"
                                        >
                                            View Product
                                        </a>

                                        <form method="post" action="cart-add.php" style="display: contents;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="redirect_to" value="index.php">
                                            <button
                                                class="cart-btn"
                                                type="submit"
                                                aria-label="Add to Cart"
                                            >

                                                <i class="fa-solid fa-cart-plus"></i>

                                            </button>
                                        </form>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </section>

        <!-- ==================================================
            BEST SELLING PRODUCTS
            (ranked from real order data where available - see
            get_best_selling_products() in
            includes/product-functions.php for the full logic
            and fallback rules)
        ================================================== -->

        <section class="home-products home-products-alt" id="best-selling-products">

            <div class="container">

                <div class="section-heading">
                    <span class="section-subtitle">Customer Favorites</span>
                    <h2 class="section-title">Best Selling Products</h2>
                    <p class="section-description">
                        The crystals our customers reach for again and again.
                    </p>
                </div>

                <div class="product-grid">

                    <?php if (empty($bestSellingProducts)): ?>

                        <p style="padding: 20px 0; color: var(--text-light);">
                            Best sellers are coming soon.
                        </p>

                    <?php else: ?>

                        <?php foreach ($bestSellingProducts as $product): ?>

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
                                    <input type="hidden" name="redirect_to" value="index.php">
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

                                    <h3 class="product-title">
                                        <a href="product.php?slug=<?= h($product['slug']) ?>">
                                            <?= h($product['name']) ?>
                                        </a>
                                    </h3>

                                    <?php if ($purpose !== ''): ?>
                                        <p class="product-benefits">
                                            <?= h($purpose) ?>
                                        </p>
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

                                        <a
                                            href="product.php?slug=<?= h($product['slug']) ?>"
                                            class="product-btn"
                                        >
                                            View Product
                                        </a>

                                        <form method="post" action="cart-add.php" style="display: contents;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="redirect_to" value="index.php">
                                            <button
                                                class="cart-btn"
                                                type="submit"
                                                aria-label="Add to Cart"
                                            >

                                                <i class="fa-solid fa-cart-plus"></i>

                                            </button>
                                        </form>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </section>

        <!-- ==================================================
            SHOP BY CONCERN
            (Concern Category - a separate, structured
            classification from products.purpose; see
            includes/concern-functions.php. Product->concern
            assignment happens in admin/product-form.php.)
        ================================================== -->

        <section class="shop-by-concern" id="shop-by-concern">

            <div class="container">

                <div class="section-heading">
                    <span class="section-subtitle">Guided By Your Needs</span>
                    <h2 class="section-title">Shop By Concern</h2>
                    <p class="section-description">
                        Find the crystals aligned with what matters to you
                        right now.
                    </p>
                </div>

                <div class="concern-grid">

                    <a href="concern.php?slug=love-and-relationships" class="concern-card">
                        <span class="concern-icon"><i class="fa-solid fa-heart"></i></span>
                        <span class="concern-name">Love &amp; Relationships</span>
                    </a>

                    <a href="concern.php?slug=career-success" class="concern-card">
                        <span class="concern-icon"><i class="fa-solid fa-briefcase"></i></span>
                        <span class="concern-name">Career Success</span>
                    </a>

                    <a href="concern.php?slug=education-and-focus" class="concern-card">
                        <span class="concern-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                        <span class="concern-name">Education &amp; Focus</span>
                    </a>

                    <a href="concerns.php" class="concern-card concern-card-all">
                        <span class="concern-icon"><i class="fa-solid fa-layer-group"></i></span>
                        <span class="concern-name">View All Concerns</span>
                    </a>

                </div>

            </div>

        </section>

        <!-- ==================================================
            SHOP BY ZODIAC SIGN
            (reuses the existing products.zodiac architecture /
            shop.php?zodiac=... filter - no second zodiac system)
        ================================================== -->

        <section class="shop-by-zodiac" id="shop-by-zodiac">

            <div class="container">

                <div class="section-heading">
                    <span class="section-subtitle">Written In The Stars</span>
                    <h2 class="section-title">Shop By Zodiac Sign</h2>
                    <p class="section-description">
                        Discover crystals aligned with your zodiac sign's
                        energy.
                    </p>
                </div>

                <div class="zodiac-grid">

                    <?php foreach (HOMEPAGE_ZODIAC_SIGNS as $sign): ?>

                        <a href="shop.php?zodiac=<?= h($sign['name']) ?>" class="zodiac-card">
                            <span class="zodiac-glyph"><?= $sign['glyph'] ?></span>
                            <span class="zodiac-name"><?= h($sign['name']) ?></span>
                        </a>

                    <?php endforeach; ?>

                </div>

            </div>

        </section>

        <!-- =========================
            Why MoonAura
        ========================== -->

        <!-- =========================
            WHY MOONAURA
            (reuses the shared .why-choose/.why-choose-grid/
            .why-choose-card component in style.css - the same
            component about.php and shop.php use. Same cards/icons,
            not a second implementation.)
        ========================== -->

        <section class="why-choose">

            <div class="container">

                <div class="section-heading">

                    <span class="section-subtitle">
                        WHY MOONAURA
                    </span>

                    <h2 class="section-title">
                        Why MoonAura Crystals?
                    </h2>

                    <p class="section-description">
                        Discover authentic natural crystals thoughtfully curated for their beauty,
                        quality and positive energy. Every piece is crafted to bring elegance,
                        balance and timeless charm to your everyday life.
                    </p>

                </div>

                <div class="why-choose-grid">

                    <article class="why-choose-card">

                        <div class="why-choose-icon">
                            <i class="fa-solid fa-gem"></i>
                        </div>

                        <h3 class="why-choose-title">
                            Authentic Crystals
                        </h3>

                        <div class="why-choose-divider"></div>

                        <p class="why-choose-description">
                            Every crystal is carefully sourced and selected for its natural beauty, quality, and authenticity.
                        </p>

                    </article>

                    <article class="why-choose-card">

                        <div class="why-choose-icon">
                            <i class="fa-solid fa-certificate"></i>
                        </div>

                        <h3 class="why-choose-title">
                            Certificate Included
                        </h3>

                        <div class="why-choose-divider"></div>

                        <p class="why-choose-description">
                            Every purchase includes an authenticity certificate for added trust, confidence, and peace of mind.
                        </p>

                    </article>

                    <article class="why-choose-card">

                        <div class="why-choose-icon">
                            <i class="fa-solid fa-shield-heart"></i>
                        </div>

                        <h3 class="why-choose-title">
                            Trusted Experience
                        </h3>

                        <div class="why-choose-divider"></div>

                        <p class="why-choose-description">
                            From secure packaging to careful quality checks, every
                            order is handled with professionalism and attention to detail.
                        </p>

                    </article>

                    <article class="why-choose-card">

                        <div class="why-choose-icon">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>

                        <h3 class="why-choose-title">
                            Pan India Shipping
                        </h3>

                        <div class="why-choose-divider"></div>

                        <p class="why-choose-description">
                            Fast and reliable delivery across India with secure packaging and order tracking for a worry-free shopping experience.
                        </p>

                    </article>

                </div>

            </div>

        </section>

        <!-- =========================
            Testimonials
        ========================== -->

        <section class="testimonials">

            <div class="container">

                <div class="section-heading">

                    <span class="section-subtitle">

                        TESTIMONIALS

                    </span>

                    <h2 class="section-title">

                        Loved by Crystal Enthusiasts Across India

                    </h2>

                    <p class="section-description">

                        Hear what our happy customers have to say about the quality,
                        craftsmanship, and experience of shopping with MoonAura Crystals.

                    </p>

                </div>

                <div class="swiper testimonialSwiper">

                    <div class="swiper-wrapper">

                        <!-- Testimonial Card -->

                        <div class="swiper-slide">

                            <article class="testimonial-card">

                                <div class="rating">

                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>

                                </div>

                                <p class="testimonial-text">

                                    Beautiful quality crystals and elegant packaging.
                                    The bracelet exceeded my expectations.

                                </p>

                                <div class="testimonial-avatar">

                                    <span>SC</span>

                                </div>

                                <div class="testimonial-info">

                                    <h4 class="testimonial-name">

                                        Soma Chakraborty

                                    </h4>

                                    <span class="verified-badge">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Verified Buyer

                                    </span>

                                </div>

                            </article>

                        </div>

                        <!-- Testimonial Card -->

                        <div class="swiper-slide">

                            <article class="testimonial-card">

                                <div class="rating">

                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>

                                </div>

                                <p class="testimonial-text">

                                    Loved the craftsmanship and authenticity.
                                    Will definitely shop again.

                                </p>

                                <div class="testimonial-avatar">

                                    <span>SS</span>

                                </div>

                                <div class="testimonial-info">

                                    <h4 class="testimonial-name">

                                        Srimanti Sinha

                                    </h4>

                                    <span class="verified-badge">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Verified Buyer

                                    </span>

                                </div>

                            </article>

                        </div>

                        <!-- Testimonial Card -->

                        <div class="swiper-slide">

                            <article class="testimonial-card">

                                <div class="rating">

                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>

                                </div>

                                <p class="testimonial-text">

                                    Fast delivery, secure packaging and genuine
                                    products. Highly recommended.

                                </p>

                                <div class="testimonial-avatar">

                                    <span>LM</span>

                                </div>

                                <div class="testimonial-info">

                                    <h4 class="testimonial-name">

                                        Laxmi Mathoor

                                    </h4>

                                    <span class="verified-badge">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Verified Buyer

                                    </span>

                                </div>

                            </article>

                        </div>

                        <!-- Testimonial Card -->

                        <div class="swiper-slide">

                            <article class="testimonial-card">

                                <div class="rating">

                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>

                                </div>

                                <p class="testimonial-text">

                                    The crystal bracelet is absolutely beautiful and
                                    feels premium. Loved every little detail.

                                </p>

                                <div class="testimonial-avatar">

                                    <span>AR</span>

                                </div>

                                <div class="testimonial-info">

                                    <h4 class="testimonial-name">

                                        Ananya Reddy

                                    </h4>

                                    <span class="verified-badge">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Verified Buyer

                                    </span>

                                </div>

                            </article>

                        </div>

                        <!-- Testimonial Card -->

                        <div class="swiper-slide">

                            <article class="testimonial-card">

                                <div class="rating">

                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>

                                </div>

                                <p class="testimonial-text">

                                    Excellent quality and exactly as shown in the
                                    pictures. The packaging was impressive.

                                </p>

                                <div class="testimonial-avatar">

                                    <span>KN</span>

                                </div>

                                <div class="testimonial-info">

                                    <h4 class="testimonial-name">

                                        Kavya Nair

                                    </h4>

                                    <span class="verified-badge">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Verified Buyer

                                    </span>

                                </div>

                            </article>

                        </div>

                        <!-- Testimonial Card -->

                        <div class="swiper-slide">

                            <article class="testimonial-card">

                                <div class="rating">

                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>

                                </div>

                                <p class="testimonial-text">

                                    The quality is exceptional, and the bracelet looks
                                    even more beautiful in person. Truly worth every penny.

                                </p>

                                <div class="testimonial-avatar">

                                    <span>NB</span>

                                </div>

                                <div class="testimonial-info">

                                    <h4 class="testimonial-name">

                                        Nandini Banerjee

                                    </h4>

                                    <span class="verified-badge">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Verified Buyer

                                    </span>

                                </div>

                            </article>

                        </div>

                        <!-- Testimonial Card -->

                        <div class="swiper-slide">

                            <article class="testimonial-card">

                                <div class="rating">

                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>

                                </div>

                                <p class="testimonial-text">

                                    Beautiful craftsmanship, timely delivery, and secure
                                    packaging. I'm very happy with my purchase.

                                </p>

                                <div class="testimonial-avatar">

                                    <span>NS</span>

                                </div>

                                <div class="testimonial-info">

                                    <h4 class="testimonial-name">

                                        Neha Sharma

                                    </h4>

                                    <span class="verified-badge">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Verified Buyer

                                    </span>

                                </div>

                            </article>

                        </div>

                        <!-- Testimonial Card -->

                        <div class="swiper-slide">

                            <article class="testimonial-card">

                                <div class="rating">

                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>

                                </div>

                                <p class="testimonial-text">

                                    Authentic crystals with a premium finish. The entire
                                    shopping experience was smooth and hassle-free.

                                </p>

                                <div class="testimonial-avatar">

                                    <span>RS</span>

                                </div>

                                <div class="testimonial-info">

                                    <h4 class="testimonial-name">

                                        Riya Singh

                                    </h4>

                                    <span class="verified-badge">

                                        <i class="fa-solid fa-circle-check"></i>

                                        Verified Buyer

                                    </span>

                                </div>

                            </article>

                        </div>

                    </div>

                    <div class="swiper-pagination"></div>

                </div>

            </div>

        </section>

        <!-- ==================================================
            INSTAGRAM
        ================================================== -->

        <section class="instagram">

            <div class="container">

                <!-- ==================================================
                    SECTION HEADING
                ================================================== -->

                <div class="section-heading">

                    <span class="section-subtitle">

                        FOLLOW US

                    </span>

                    <h2 class="section-title">

                        Join the MoonAura Community

                    </h2>

                    <p class="section-description">

                        Discover crystal styling inspiration, customer stories,
                        behind-the-scenes moments, and our latest arrivals.

                    </p>

                </div>

                <!-- ==================================================
                    INSTAGRAM SWIPER
                ================================================== -->

                <div class="swiper instagram-swiper">

                    <div class="swiper-wrapper">

                        <!-- Slide 1 -->

                        <div class="swiper-slide">

                            <a  href="https://www.instagram.com/crystalsmoonaura"
                                class="instagram-card"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <img
                                    src="<?= asset_url('assets/images/instagram/1.webp') ?>"
                                    alt="MoonAura Instagram Post 1"
                                    loading="lazy"
                                >

                                <div class="instagram-overlay">

                                    <i class="fa-brands fa-instagram"></i>

                                </div>

                            </a>

                        </div>

                        <!-- Slide 2 -->

                        <div class="swiper-slide">

                            <a  href="https://www.instagram.com/crystalsmoonaura"
                                class="instagram-card"
                                target="_blank"
                                rel="noopener noreferrer"
                            >

                                <img
                                    src="<?= asset_url('assets/images/instagram/2.webp') ?>"
                                    alt="MoonAura Instagram Post 2"
                                    loading="lazy"
                                >

                                <div class="instagram-overlay">

                                    <i class="fa-brands fa-instagram"></i>

                                </div>

                            </a>

                        </div>

                        <!-- Slide 3 -->

                        <div class="swiper-slide">

                            <a  href="https://www.instagram.com/crystalsmoonaura"
                                class="instagram-card"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <img
                                    src="<?= asset_url('assets/images/instagram/3.webp') ?>"
                                    alt="MoonAura Instagram Post 3"
                                    loading="lazy"
                                >

                                <div class="instagram-overlay">

                                    <i class="fa-brands fa-instagram"></i>

                                </div>

                            </a>

                        </div>

                        <!-- Slide 4 -->

                        <div class="swiper-slide">

                            <a  href="https://www.instagram.com/crystalsmoonaura"
                                class="instagram-card"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <img
                                    src="<?= asset_url('assets/images/instagram/4.webp') ?>"
                                    alt="MoonAura Instagram Post 4"
                                    loading="lazy"
                                >

                                <div class="instagram-overlay">

                                    <i class="fa-brands fa-instagram"></i>

                                </div>

                            </a>

                        </div>

                        <!-- Slide 5 -->

                        <div class="swiper-slide">

                            <a  href="https://www.instagram.com/crystalsmoonaura"
                                class="instagram-card"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <img
                                    src="<?= asset_url('assets/images/instagram/5.webp') ?>"
                                    alt="MoonAura Instagram Post 5"
                                    loading="lazy"
                                >

                                <div class="instagram-overlay">

                                    <i class="fa-brands fa-instagram"></i>

                                </div>

                            </a>

                        </div>

                        <!-- Slide 6 -->

                        <div class="swiper-slide">

                            <a  href="https://www.instagram.com/crystalsmoonaura"
                                class="instagram-card"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <img
                                    src="<?= asset_url('assets/images/instagram/6.webp') ?>"
                                    alt="MoonAura Instagram Post 6"
                                    loading="lazy"
                                >

                                <div class="instagram-overlay">

                                    <i class="fa-brands fa-instagram"></i>

                                </div>

                            </a>

                        </div>

                    </div>

                    <!-- Pagination -->

                    <div class="swiper-pagination instagram-pagination"></div>

                </div>

                <!-- ==================================================
                    FOLLOW BUTTON
                ================================================== -->

                <div class="instagram-button">

                    <a
                        href="https://www.instagram.com/crystalsmoonaura"
                        class="btn btn-primary"
                        target="_blank"
                        rel="noopener"
                    >

                        <i class="fa-brands fa-instagram"></i>

                        Follow @CrystalsMoonaura

                    </a>

                </div>

            </div>

        </section>

        <!-- =================================================
                        Newsletter & WhatsApp
        ================================================== -->

        <section class="newsletter" id="newsletter">

            <div class="container">

                <div class="newsletter-box">

                    <span class="newsletter-subtitle">

                        STAY CONNECTED

                    </span>

                    <h2 class="newsletter-title">

                        Get Crystal Updates & Exclusive Offers

                    </h2>

                    <p class="newsletter-description">

                        Subscribe to receive crystal guidance, new arrivals,
                        exclusive offers, and special discounts directly in your inbox.

                    </p>

                    <form class="newsletter-form" method="post" action="newsletter-subscribe.php">

                        <?= csrf_field() ?>

                        <input
                            type="email"
                            name="email"
                            placeholder="Enter your email address"
                            required
                        >

                        <button type="submit">

                            Subscribe

                        </button>

                    </form>

                    <p class="newsletter-consent">
                        By clicking “Subscribe,” you agree to receive marketing emails from MoonAura Crystals.
                    </p>

                    <?php if ($newsletterSuccess): ?>
                        <p class="newsletter-feedback newsletter-feedback-success"><?= h($newsletterSuccess) ?></p>
                    <?php elseif ($newsletterError): ?>
                        <p class="newsletter-feedback newsletter-feedback-error"><?= h($newsletterError) ?></p>
                    <?php endif; ?>

                    <p class="newsletter-note">

                        No spam. Unsubscribe anytime.

                    </p>

                </div>

            </div>

        </section>

    </main>

    <!-- =========================
            Footer
    ========================== -->


    <!-- Footer -->
    <?php
    if (!empty($featuredProducts)) {
        ga4_queue_event('view_item_list', [
            'item_list_id'   => 'featured',
            'item_list_name' => 'Featured Products',
            'items'          => ga4_items_from_products($featuredProducts),
            'currency'       => ga4_currency(),
        ]);
    }
    if (!empty($bestSellingProducts)) {
        ga4_queue_event('view_item_list', [
            'item_list_id'   => 'best_selling',
            'item_list_name' => 'Best Selling Products',
            'items'          => ga4_items_from_products($bestSellingProducts),
            'currency'       => ga4_currency(),
        ]);
    }
    ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- #29: #backToTop is now provided by the shared footer
         (includes/footer.php) so it's site-wide - the
         Home-only duplicate that used to live here has been
         removed. -->

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- Load Common Header & Footer -->
    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>

</html>