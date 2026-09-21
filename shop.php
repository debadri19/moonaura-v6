<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-functions.php';
require_once __DIR__ . '/includes/product-image-variants.php';
require_once __DIR__ . '/includes/wishlist-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';
require_once __DIR__ . '/includes/meta-pixel-functions.php';

/* ==========================================
   READ FILTERS/SORT/PAGE FROM THE URL
   e.g. shop.php?category=bracelets&price=300-499&sort=newest&page=2
========================================== */

$filters = [
    'category' => $_GET['category'] ?? 'all',
    'price'    => $_GET['price'] ?? 'all',
    'concern'  => $_GET['concern'] ?? 'all',
    'zodiac'   => $_GET['zodiac'] ?? 'all',
    'sort'     => $_GET['sort'] ?? 'featured',
    'q'        => trim((string) ($_GET['q'] ?? '')),
];

$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage     = 12; // matches the "Showing 12 Products" text in the design

/* ==========================================
   FETCH PRODUCTS FOR THIS PAGE + TOTAL COUNT
========================================== */

$result         = get_shop_products($filters, $currentPage, $perPage);
$shopProducts   = $result['products'];
$totalProducts  = (int) $result['total'];
$displayedCount = is_array($shopProducts) ? count($shopProducts) : 0;
$totalPages     = (int) ceil($totalProducts / $perPage);

// Categories for the filter dropdown (only ones with active products).
$shopCategories = get_shop_categories();

// Used so "Add to Cart" brings the customer back to this exact
// filtered/paginated view instead of a generic page.
$currentPageUrl = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
<?php theme_boot(); ?>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">

    <title>Shop | MoonAura Crystals</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">


    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- CSS Links -->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/home.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/about-us.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/shop.css') ?>">

</head>

<body>

    <!-- Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- =========================
         SHOP HERO
         (reuses the exact .hero/.hero-container/.hero-content/
         .hero-image component already defined in home.css and used
         by index.php - no separate hero implementation here. CTA
         buttons intentionally omitted per this batch's spec.)
    ========================== -->

    <section class="hero">

        <div class="container hero-container">

            <div class="hero-content">

                <span class="hero-subtitle">
                    MoonAura Collection
                </span>

                <h1 class="hero-title">
                    Discover Our<br>
                    <span>Natural Crystal Collection</span>
                </h1>

                <p class="hero-description">
                    Explore our carefully handpicked collection of authentic crystal
                    bracelets, rings, pendants and spiritual accessories. Every piece is
                    selected for its natural beauty, quality craftsmanship and timeless
                    elegance.
                </p>

            </div>

            <div class="hero-image">

                <img
                    src="<?= asset_url('assets/images/banners/hero-banner-wide.webp') ?>"
                    alt="MoonAura Crystal Collection"
                    loading="eager"
                >

            </div>

        </div>

    </section>

<!-- =========================
     SHOP TOOLBAR
========================== -->

<section class="shop-toolbar" id="shop-products">

    <div class="container">

        <div class="shop-toolbar-wrapper">

            <div class="shop-filters">

                <form method="get" action="shop.php" id="shopFilterForm" class="shop-search-form">

                    <div class="shop-search-box">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="q"
                            value="<?= h($filters['q']) ?>"
                            placeholder="Search crystals, gemstones, benefits..."
                            aria-label="Search products"
                            autocomplete="off"
                            <?= $filters['q'] !== '' ? 'autofocus' : '' ?>
                        >
                    </div>

                    <!-- ==========================================
                         #18 SHOP FILTER POPUPS
                         -------------------------------------------
                         Each filter is now a compact icon button that
                         opens a MoonAura-themed popup (assets/js/shop-filters.js).
                         The original <select> for each filter is kept
                         exactly as before (same id/name/options/onchange)
                         so it stays the real, authoritative form input -
                         shop.php's filter/query logic below is completely
                         untouched. JS only keeps the hidden select's
                         value in sync with whatever the customer picks in
                         the popup, then submits #shopFilterForm exactly
                         like the old onchange handler did.

                         .shop-filter-native hides the <select> visually
                         (not display:none, so it stays in the DOM/tab
                         order for JS) without exposing its native option
                         list. If JavaScript is disabled, the <noscript>
                         block right after each filter's popup un-hides
                         that native select and hides the (non-functional
                         without JS) popup button/popup, so filtering still
                         works exactly as it did before this change.
                    ========================================== -->

                    <div class="shop-filter-selects">

                    <div class="shop-filter" data-filter="category">

                        <button
                            type="button"
                            class="shop-filter-btn<?= $filters['category'] !== 'all' ? ' active' : '' ?>"
                            id="categoryFilterBtn"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-controls="categoryFilterPopup"
                            aria-label="Filter by category"
                        >
                            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        </button>

                        <div class="shop-filter-popup" id="categoryFilterPopup" role="listbox" aria-label="Category options" hidden>
                            <div class="shop-filter-popup-scroll">
                                <button type="button" class="shop-filter-option<?= $filters['category'] === 'all' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['category'] === 'all' ? 'true' : 'false' ?>" data-select="categoryFilter" data-value="all">All Categories</button>
                                <?php foreach ($shopCategories as $shopCategory): ?>
                                    <button type="button" class="shop-filter-option<?= $filters['category'] === $shopCategory['slug'] ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['category'] === $shopCategory['slug'] ? 'true' : 'false' ?>" data-select="categoryFilter" data-value="<?= h($shopCategory['slug']) ?>"><?= h($shopCategory['name']) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <select id="categoryFilter" name="category" class="shop-filter-native" title="Filter by category" aria-label="Filter by category" onchange="document.getElementById('shopFilterForm').submit()">
                            <option value="all">All Categories</option>
                            <?php foreach ($shopCategories as $shopCategory): ?>
                                <option
                                    value="<?= h($shopCategory['slug']) ?>"
                                    <?= $filters['category'] === $shopCategory['slug'] ? 'selected' : '' ?>
                                >
                                    <?= h($shopCategory['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <noscript><style>#categoryFilterBtn,#categoryFilterPopup{display:none!important}#categoryFilter{position:static!important;width:auto!important;height:48px!important;opacity:1!important;clip:auto!important;clip-path:none!important;overflow:visible!important;margin:0!important;pointer-events:auto!important}</style></noscript>

                    </div>

                    <div class="shop-filter" data-filter="concern">

                        <button
                            type="button"
                            class="shop-filter-btn<?= $filters['concern'] !== 'all' ? ' active' : '' ?>"
                            id="concernFilterBtn"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-controls="concernFilterPopup"
                            aria-label="Filter by concern"
                        >
                            <i class="fa-solid fa-heart" aria-hidden="true"></i>
                        </button>

                        <div class="shop-filter-popup" id="concernFilterPopup" role="listbox" aria-label="Concern options" hidden>
                            <div class="shop-filter-popup-scroll">
                                <button type="button" class="shop-filter-option<?= $filters['concern'] === 'all' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['concern'] === 'all' ? 'true' : 'false' ?>" data-select="concernFilter" data-value="all">All Concerns</button>
                                <button type="button" class="shop-filter-option<?= $filters['concern'] === 'Love' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['concern'] === 'Love' ? 'true' : 'false' ?>" data-select="concernFilter" data-value="Love">Love</button>
                                <button type="button" class="shop-filter-option<?= $filters['concern'] === 'Career' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['concern'] === 'Career' ? 'true' : 'false' ?>" data-select="concernFilter" data-value="Career">Career</button>
                                <button type="button" class="shop-filter-option<?= $filters['concern'] === 'Money' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['concern'] === 'Money' ? 'true' : 'false' ?>" data-select="concernFilter" data-value="Money">Money</button>
                                <button type="button" class="shop-filter-option<?= $filters['concern'] === 'Health' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['concern'] === 'Health' ? 'true' : 'false' ?>" data-select="concernFilter" data-value="Health">Health</button>
                                <button type="button" class="shop-filter-option<?= $filters['concern'] === 'Protection' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['concern'] === 'Protection' ? 'true' : 'false' ?>" data-select="concernFilter" data-value="Protection">Protection</button>
                            </div>
                        </div>

                        <select id="concernFilter" name="concern" class="shop-filter-native" title="Filter by concern" aria-label="Filter by concern" onchange="document.getElementById('shopFilterForm').submit()">
                            <option value="all"       <?= $filters['concern'] === 'all'       ? 'selected' : '' ?>>All Concerns</option>
                            <option value="Love"      <?= $filters['concern'] === 'Love'      ? 'selected' : '' ?>>Love</option>
                            <option value="Career"    <?= $filters['concern'] === 'Career'    ? 'selected' : '' ?>>Career</option>
                            <option value="Money"     <?= $filters['concern'] === 'Money'     ? 'selected' : '' ?>>Money</option>
                            <option value="Health"    <?= $filters['concern'] === 'Health'    ? 'selected' : '' ?>>Health</option>
                            <option value="Protection"<?= $filters['concern'] === 'Protection' ? 'selected' : '' ?>>Protection</option>
                        </select>

                        <noscript><style>#concernFilterBtn,#concernFilterPopup{display:none!important}#concernFilter{position:static!important;width:auto!important;height:48px!important;opacity:1!important;clip:auto!important;clip-path:none!important;overflow:visible!important;margin:0!important;pointer-events:auto!important}</style></noscript>

                    </div>

                    <div class="shop-filter" data-filter="zodiac">

                        <button
                            type="button"
                            class="shop-filter-btn<?= $filters['zodiac'] !== 'all' ? ' active' : '' ?>"
                            id="zodiacFilterBtn"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-controls="zodiacFilterPopup"
                            aria-label="Filter by zodiac sign"
                        >
                            <i class="fa-solid fa-circle-nodes" aria-hidden="true"></i>
                        </button>

                        <div class="shop-filter-popup" id="zodiacFilterPopup" role="listbox" aria-label="Zodiac sign options" hidden>
                            <div class="shop-filter-popup-scroll">
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'all' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'all' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="all">All Zodiac Signs</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Aries' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Aries' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Aries">Aries</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Taurus' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Taurus' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Taurus">Taurus</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Gemini' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Gemini' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Gemini">Gemini</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Cancer' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Cancer' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Cancer">Cancer</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Leo' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Leo' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Leo">Leo</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Virgo' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Virgo' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Virgo">Virgo</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Libra' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Libra' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Libra">Libra</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Scorpio' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Scorpio' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Scorpio">Scorpio</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Sagittarius' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Sagittarius' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Sagittarius">Sagittarius</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Capricorn' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Capricorn' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Capricorn">Capricorn</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Aquarius' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Aquarius' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Aquarius">Aquarius</button>
                                <button type="button" class="shop-filter-option<?= $filters['zodiac'] === 'Pisces' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['zodiac'] === 'Pisces' ? 'true' : 'false' ?>" data-select="zodiacFilter" data-value="Pisces">Pisces</button>
                            </div>
                        </div>

                        <select id="zodiacFilter" name="zodiac" class="shop-filter-native" title="Filter by zodiac sign" aria-label="Filter by zodiac sign" onchange="document.getElementById('shopFilterForm').submit()">
                            <option value="all"        <?= $filters['zodiac'] === 'all'        ? 'selected' : '' ?>>All Zodiac Signs</option>
                            <option value="Aries"      <?= $filters['zodiac'] === 'Aries'      ? 'selected' : '' ?>>Aries</option>
                            <option value="Taurus"     <?= $filters['zodiac'] === 'Taurus'     ? 'selected' : '' ?>>Taurus</option>
                            <option value="Gemini"     <?= $filters['zodiac'] === 'Gemini'     ? 'selected' : '' ?>>Gemini</option>
                            <option value="Cancer"     <?= $filters['zodiac'] === 'Cancer'     ? 'selected' : '' ?>>Cancer</option>
                            <option value="Leo"        <?= $filters['zodiac'] === 'Leo'        ? 'selected' : '' ?>>Leo</option>
                            <option value="Virgo"      <?= $filters['zodiac'] === 'Virgo'      ? 'selected' : '' ?>>Virgo</option>
                            <option value="Libra"      <?= $filters['zodiac'] === 'Libra'      ? 'selected' : '' ?>>Libra</option>
                            <option value="Scorpio"    <?= $filters['zodiac'] === 'Scorpio'    ? 'selected' : '' ?>>Scorpio</option>
                            <option value="Sagittarius"<?= $filters['zodiac'] === 'Sagittarius' ? 'selected' : '' ?>>Sagittarius</option>
                            <option value="Capricorn"  <?= $filters['zodiac'] === 'Capricorn'  ? 'selected' : '' ?>>Capricorn</option>
                            <option value="Aquarius"   <?= $filters['zodiac'] === 'Aquarius'   ? 'selected' : '' ?>>Aquarius</option>
                            <option value="Pisces"     <?= $filters['zodiac'] === 'Pisces'     ? 'selected' : '' ?>>Pisces</option>
                        </select>

                        <noscript><style>#zodiacFilterBtn,#zodiacFilterPopup{display:none!important}#zodiacFilter{position:static!important;width:auto!important;height:48px!important;opacity:1!important;clip:auto!important;clip-path:none!important;overflow:visible!important;margin:0!important;pointer-events:auto!important}</style></noscript>

                    </div>

                    <div class="shop-filter" data-filter="price">

                        <button
                            type="button"
                            class="shop-filter-btn<?= $filters['price'] !== 'all' ? ' active' : '' ?>"
                            id="priceFilterBtn"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-controls="priceFilterPopup"
                            aria-label="Filter by price"
                        >
                            <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                        </button>

                        <div class="shop-filter-popup" id="priceFilterPopup" role="listbox" aria-label="Price options" hidden>
                            <div class="shop-filter-popup-scroll">
                                <button type="button" class="shop-filter-option<?= $filters['price'] === 'all' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['price'] === 'all' ? 'true' : 'false' ?>" data-select="priceFilter" data-value="all">Price</button>
                                <button type="button" class="shop-filter-option<?= $filters['price'] === '0-299' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['price'] === '0-299' ? 'true' : 'false' ?>" data-select="priceFilter" data-value="0-299">₹0 – ₹299</button>
                                <button type="button" class="shop-filter-option<?= $filters['price'] === '300-499' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['price'] === '300-499' ? 'true' : 'false' ?>" data-select="priceFilter" data-value="300-499">₹300 – ₹499</button>
                                <button type="button" class="shop-filter-option<?= $filters['price'] === '500-999' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['price'] === '500-999' ? 'true' : 'false' ?>" data-select="priceFilter" data-value="500-999">₹500 – ₹999</button>
                                <button type="button" class="shop-filter-option<?= $filters['price'] === '1000' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['price'] === '1000' ? 'true' : 'false' ?>" data-select="priceFilter" data-value="1000">₹1000+</button>
                            </div>
                        </div>

                        <select id="priceFilter" name="price" class="shop-filter-native" title="Filter by price" aria-label="Filter by price" onchange="document.getElementById('shopFilterForm').submit()">
                            <option value="all"     <?= $filters['price'] === 'all'     ? 'selected' : '' ?>>Price</option>
                            <option value="0-299"   <?= $filters['price'] === '0-299'   ? 'selected' : '' ?>>₹0 – ₹299</option>
                            <option value="300-499" <?= $filters['price'] === '300-499' ? 'selected' : '' ?>>₹300 – ₹499</option>
                            <option value="500-999" <?= $filters['price'] === '500-999' ? 'selected' : '' ?>>₹500 – ₹999</option>
                            <option value="1000"    <?= $filters['price'] === '1000'    ? 'selected' : '' ?>>₹1000+</option>
                        </select>

                        <noscript><style>#priceFilterBtn,#priceFilterPopup{display:none!important}#priceFilter{position:static!important;width:auto!important;height:48px!important;opacity:1!important;clip:auto!important;clip-path:none!important;overflow:visible!important;margin:0!important;pointer-events:auto!important}</style></noscript>

                    </div>

                    <div class="shop-filter" data-filter="sort">

                        <button
                            type="button"
                            class="shop-filter-btn<?= $filters['sort'] !== 'featured' ? ' active' : '' ?>"
                            id="sortFilterBtn"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-controls="sortFilterPopup"
                            aria-label="Sort products"
                        >
                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        </button>

                        <div class="shop-filter-popup" id="sortFilterPopup" role="listbox" aria-label="Sort options" hidden>
                            <div class="shop-filter-popup-scroll">
                                <button type="button" class="shop-filter-option<?= $filters['sort'] === 'featured' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['sort'] === 'featured' ? 'true' : 'false' ?>" data-select="sortProducts" data-value="featured">Featured</button>
                                <button type="button" class="shop-filter-option<?= $filters['sort'] === 'newest' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['sort'] === 'newest' ? 'true' : 'false' ?>" data-select="sortProducts" data-value="newest">Newest First</button>
                                <button type="button" class="shop-filter-option<?= $filters['sort'] === 'price-low' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['sort'] === 'price-low' ? 'true' : 'false' ?>" data-select="sortProducts" data-value="price-low">Price: Low to High</button>
                                <button type="button" class="shop-filter-option<?= $filters['sort'] === 'price-high' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['sort'] === 'price-high' ? 'true' : 'false' ?>" data-select="sortProducts" data-value="price-high">Price: High to Low</button>
                                <button type="button" class="shop-filter-option<?= $filters['sort'] === 'name' ? ' selected' : '' ?>" role="option" aria-selected="<?= $filters['sort'] === 'name' ? 'true' : 'false' ?>" data-select="sortProducts" data-value="name">Alphabetically</button>
                            </div>
                        </div>

                        <select id="sortProducts" name="sort" class="shop-filter-native" title="Sort products" aria-label="Sort products" onchange="document.getElementById('shopFilterForm').submit()">
                            <option value="featured"   <?= $filters['sort'] === 'featured'   ? 'selected' : '' ?>>Featured</option>
                            <option value="newest"     <?= $filters['sort'] === 'newest'     ? 'selected' : '' ?>>Newest First</option>
                            <option value="price-low"  <?= $filters['sort'] === 'price-low'  ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="price-high" <?= $filters['sort'] === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="name"       <?= $filters['sort'] === 'name'       ? 'selected' : '' ?>>Alphabetically</option>
                        </select>

                        <noscript><style>#sortFilterBtn,#sortFilterPopup{display:none!important}#sortProducts{position:static!important;width:auto!important;height:48px!important;opacity:1!important;clip:auto!important;clip-path:none!important;overflow:visible!important;margin:0!important;pointer-events:auto!important}</style></noscript>

                    </div>

                    </div>

                </form>

            </div>

        </div>

    </div>

</section>

<!-- =========================
     PRODUCT GRID
========================== -->

<section class="shop-products">

    <div class="container">

        <div class="product-grid">

                    <?php if (empty($shopProducts)): ?>

                        <p class="shop-empty-state">
                            <?php if ($filters['q'] !== ''): ?>
                                No products found matching
                                &ldquo;<?= h($filters['q']) ?>&rdquo;.
                                Try a different search or clear your filters.
                            <?php else: ?>
                                No products match your selected filters.
                                Try clearing one or two filters.
                            <?php endif; ?>
                        </p>

                    <?php else: ?>

                        <?php foreach ($shopProducts as $product): ?>

                            <?php
                                $badge     = get_product_badge($product);
                                $imagePath = get_product_primary_image((int) $product['id']);
                                $purpose   = format_purpose_bullets($product['purpose']);
                                $cardStockQuantity = (int) ($product['stock_quantity'] ?? 0);
                                $cardStockStatus   = $product['stock_status'] ?? 'in_stock';
                                $cardOutOfStock    = $cardStockQuantity <= 0 || $cardStockStatus === 'out_of_stock';
                                $cardLowStock      = !$cardOutOfStock && $cardStockQuantity >= 1 && $cardStockQuantity <= 3;
                            ?>

                            <article class="product-card">

                                <?php if ($badge): ?>
                                    <span class="product-badge"><?= h($badge) ?></span>
                                <?php endif; ?>

                                <form method="post" action="wishlist-toggle.php" class="wishlist-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <input type="hidden" name="redirect_to" value="<?= h($currentPageUrl) ?>">
                                    <?php $inWishlist = is_in_wishlist((int) $product['id']); ?>
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

                                    <?php if ($cardOutOfStock): ?>
                                        <p class="product-card-stock product-card-stock-out">Out of Stock</p>
                                    <?php elseif ($cardLowStock): ?>
                                        <p class="product-card-stock product-card-stock-low">Only <?= $cardStockQuantity ?> left in stock</p>
                                    <?php else: ?>
                                        <p class="product-card-stock product-card-stock-empty">&nbsp;</p>
                                    <?php endif; ?>

                                    <div class="product-actions">

                                        <a href="product.php?slug=<?= h($product['slug']) ?>" class="product-btn">
                                            View Product
                                        </a>

                                        <?php if ($cardOutOfStock): ?>
                                            <span class="cart-btn is-disabled" aria-disabled="true" title="Out of Stock">
                                                <i class="fa-solid fa-cart-plus"></i>
                                            </span>
                                        <?php else: ?>
                                        <form method="post" action="cart-add.php" style="display: contents;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="redirect_to" value="<?= h($currentPageUrl) ?>">
                                            <button class="cart-btn" type="submit" aria-label="Add to Cart">
                                                <i class="fa-solid fa-cart-plus"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

        </div>

    </div>

</section>

<!-- =========================
     PAGINATION
========================== -->

<section class="shop-pagination">

    <div class="container">

        <?php if ($totalPages > 1): ?>

            <?php
                // 'q' is empty when no search was made - drop it from the
                // pagination links so they don't carry a pointless ?q=.
                $paginationQuery = $filters;
                if ($paginationQuery['q'] === '') {
                    unset($paginationQuery['q']);
                }
            ?>

            <div class="pagination">

                <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>

                    <?php
                        $pageUrl = 'shop.php?' . http_build_query(array_merge($paginationQuery, ['page' => $pageNumber]));
                    ?>

                    <a
                        href="<?= h($pageUrl) ?>"
                        class="page-btn <?= $pageNumber === $currentPage ? 'active' : '' ?>"
                    >
                        <?= $pageNumber ?>
                    </a>

                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>

                    <?php
                        $nextUrl = 'shop.php?' . http_build_query(array_merge($paginationQuery, ['page' => $currentPage + 1]));
                    ?>

                    <a href="<?= h($nextUrl) ?>" class="page-btn next-btn">
                        Next
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</section>

<!-- =========================
     WHY CHOOSE MOONAURA
     (reuses the shared .why-choose/.why-choose-grid/.why-choose-card
     component in style.css - the same component index.php and
     about.php use. Same cards/icons, not a second implementation.)
========================== -->

<section class="why-choose">

    <div class="container">

        <div class="section-heading">

            <span class="section-subtitle">
                Why Choose MoonAura?
            </span>

            <h2 class="section-title">
                Shop With Confidence
            </h2>

            <p class="section-description">
                Every order is packed with care and backed by our commitment to authenticity, quality, and customer satisfaction.
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
                    Every crystal is carefully sourced and selected for its natural
                    beauty, quality, and authenticity.
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
                    Every purchase includes an authenticity certificate
                    for added trust, confidence, and peace of mind.
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
                    Fast and reliable delivery across India with secure packaging
                    and order tracking for a worry-free shopping experience.
                </p>

            </article>

        </div>

    </div>

</section>

<!-- =========================
     NEED HELP CHOOSING
     (reuses the exact .need-help/.help-box/.help-buttons component
     already defined in about-us.css and used by about.php's own
     Need Help section. The extra .shop-need-help class is only a
     scoping hook for this page's second button - see shop.css - it
     doesn't change the shared component itself.)
========================== -->

<section class="need-help shop-need-help">

    <div class="container">

        <div class="help-box">

            <span class="section-subtitle">
                NEED HELP?
            </span>

            <h2>
                Need Help Choosing the Right Crystal?
            </h2>

            <p>
                Our team will help you select the perfect crystal based on your goals, preferences, and energy needs.
            </p>

            <div class="help-buttons">

                <a href="https://wa.me/919242319596" class="btn btn-primary">

                    <i class="fa-brands fa-whatsapp"></i>

                    Chat on WhatsApp

                </a>

                <a href="support.php" class="btn btn-outline">

                    <i class="fa-solid fa-envelope"></i>

                    Contact Support

                </a>

            </div>

        </div>

    </div>

</section>

<!-- =========================
     FOOTER
========================== -->

<?php
$shopListItems = !empty($shopProducts) ? ga4_items_from_products($shopProducts) : [];

if (!empty($shopProducts)) {
    ga4_queue_event('view_item_list', [
        'item_list_id'   => 'shop',
        'item_list_name' => 'Shop',
        'items'          => $shopListItems,
        'currency'       => ga4_currency(),
    ]);
}

/* ==========================================
   META PHASE 2
   -------------------------------------------------
   A submitted search (?q=...) fires the standard "Search" event and
   nothing else. A normal browse/category listing fires a single
   ViewContent (content_type: product_group) per page load - never
   one event per product and never on incidental re-renders.
========================================== */

if ($filters['q'] !== '') {
    meta_pixel_track_search($filters['q']);
} elseif ($shopListItems) {
    meta_pixel_track_list_view($shopListItems, 'Shop');
}
?>
<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- =========================
     SCRIPTS
========================== -->

<script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
<script src="<?= versioned_asset('assets/js/shop-filters.js') ?>"></script>

</body>
</html>
