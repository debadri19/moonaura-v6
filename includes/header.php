<!-- ==================================================
     HEADER
     (Converted from components/header.html into a PHP include.
     Markup is unchanged - only internal links now point to the
     renamed .php pages instead of .html. Phase 2B adds a cart
     count badge next to the cart icon.)
================================================== -->

<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cart-functions.php';
require_once __DIR__ . '/wishlist-functions.php';
$headerCartCount     = cart_count();
$headerWishlistCount = wishlist_count();
?>

<header class="site-header">

    <div class="container">

        <div class="header-inner">

            <!-- =========================
                 LOGO
            ========================== -->

            <a href="<?= site_url('index.php') ?>" class="logo">

                <img src="<?= asset_url('assets/images/icons/logos/logo-header.webp') ?>" alt="MoonAura Crystals">

            </a>

            <!-- =========================
                 NAVIGATION
            ========================== -->

            <nav class="navbar">

                <ul>

                    <li>
                        <a href="<?= site_url('index.php') ?>">Home</a>
                    </li>

                    <li>
                        <a href="<?= site_url('shop.php') ?>">Shop</a>
                    </li>

                    <li class="dropdown">

                        <button
                            class="drop-btn"
                            type="button"
                            aria-expanded="false"
                        >

                            Help

                            <i class="fa-solid fa-chevron-down"></i>

                        </button>

                        <div class="dropdown-content">

                            <div class="dropdown-policies">

                                <div class="dropdown-policies-row">

                                    <a href="<?= site_url('policy.php') ?>" class="dropdown-policies-link">
                                        <i class="fa-solid fa-file-contract"></i>
                                        Policies
                                    </a>

                                    <button
                                        class="dropdown-policies-toggle"
                                        type="button"
                                        aria-expanded="false"
                                        aria-controls="desktop-policies-submenu"
                                        aria-label="Toggle policy menu"
                                    >
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>

                                </div>

                                <div class="dropdown-policies-submenu" id="desktop-policies-submenu">

                                    <a href="<?= site_url('policy.php') ?>#order">
                                        <i class="fa-solid fa-cart-shopping"></i>
                                        Order Policy
                                    </a>

                                    <a href="<?= site_url('policy.php') ?>#cancellation">
                                        <i class="fa-solid fa-ban"></i>
                                        Cancellation Policy
                                    </a>

                                    <a href="<?= site_url('policy.php') ?>#shipping">
                                        <i class="fa-solid fa-truck-fast"></i>
                                        Shipping Policy
                                    </a>

                                    <a href="<?= site_url('policy.php') ?>#replacement">
                                        <i class="fa-solid fa-rotate-left"></i>
                                        Replacement Policy
                                    </a>

                                    <a href="<?= site_url('policy.php') ?>#returns">
                                        <i class="fa-solid fa-arrow-rotate-left"></i>
                                        Return & Refund Policy
                                    </a>

                                    <a href="<?= site_url('policy.php') ?>#refund">
                                        <i class="fa-solid fa-wallet"></i>
                                        Refund Policy
                                    </a>

                                    <a href="<?= site_url('policy.php') ?>#privacy">
                                        <i class="fa-solid fa-user-shield"></i>
                                        Privacy Policy
                                    </a>

                                    <a href="<?= site_url('policy.php') ?>#terms">
                                        <i class="fa-solid fa-file-contract"></i>
                                        Terms & Conditions
                                    </a>

                                    <a href="<?= site_url('policy.php') ?>#disclaimer">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        Disclaimer
                                    </a>

                                </div>

                            </div>

                            <a href="<?= site_url('support.php') ?>" class="dropdown-support">
                                <i class="fa-solid fa-headset"></i>
                                Support
                            </a>

                        </div>

                    </li>

                    <li>
                        <a href="<?= site_url('about.php') ?>">About Us</a>
                    </li>

                </ul>

            </nav>

            <!-- =========================
                 HEADER ACTIONS
            ========================== -->

            <div class="header-actions">

                <button
                    type="button"
                    class="header-icon header-search-toggle"
                    aria-label="Search"
                    aria-expanded="false"
                    aria-controls="headerSearch"
                >
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>

                <a
                    href="<?= site_url('wishlist.php') ?>"
                    class="header-icon"
                    aria-label="Wishlist"
                >
                    <i class="fa-solid fa-heart"></i>
                    <?php if ($headerWishlistCount > 0): ?>
                        <span class="cart-count-badge"><?= (int) $headerWishlistCount ?></span>
                    <?php endif; ?>
                </a>

                <a
                    href="<?= site_url('account/dashboard.php') ?>"
                    class="header-icon"
                    aria-label="My Account"
                >
                    <i class="fa-regular fa-user"></i>
                </a>

            </div>

            <!-- =========================
                 MOBILE MENU BUTTON
            ========================== -->

            <button
                class="menu-toggle"
                type="button"
                aria-label="Open Menu"
                aria-expanded="false"
            >

                <i class="fa-solid fa-bars"></i>

            </button>

        </div>

    </div>

    <div class="header-search" id="headerSearch">

        <div class="container">

            <form method="get" action="<?= site_url('shop.php') ?>" class="header-search-form" role="search">

                <div class="header-search-box">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        type="search"
                        name="q"
                        placeholder="Search crystals, gemstones, benefits..."
                        aria-label="Search products"
                    >
                </div>

                <button type="submit" class="header-search-submit">Search</button>

                <button
                    type="button"
                    class="header-search-close"
                    aria-label="Close search"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>

            </form>

        </div>

    </div>

</header>

<div class="header-search-overlay" id="headerSearchOverlay"></div>

<!-- =========================
     MOBILE MENU OVERLAY
========================== -->

<div class="menu-overlay"></div>

<!-- =========================
     MOBILE MENU
========================== -->

<aside class="mobile-menu">

    <!-- =========================
         MOBILE MENU HEADER
    ========================== -->

    <div class="mobile-menu-header">

        <a href="<?= site_url('index.php') ?>" class="mobile-logo">

            <img src="<?= asset_url('assets/images/icons/logos/logo-mobile-nav.webp') ?>" alt="MoonAura Crystals">

        </a>

        <button
            class="menu-close"
            type="button"
            aria-label="Close Menu"
        >

            <i class="fa-solid fa-xmark"></i>

        </button>

    </div>

    <!-- =========================
         MOBILE NAVIGATION
    ========================== -->

    <nav class="mobile-nav">

        <a href="<?= site_url('index.php') ?>">Home</a>

        <a href="<?= site_url('shop.php') ?>">Shop</a>

        <div class="mobile-dropdown">

            <button
                class="mobile-drop-btn"
                type="button"
                aria-expanded="false"
            >

                <span>Help</span>

                <i class="fa-solid fa-chevron-down"></i>

            </button>

            <div class="mobile-dropdown-content">

                <div class="mobile-policies">

                    <div class="mobile-policies-row">

                        <a href="<?= site_url('policy.php') ?>">Policies</a>

                        <button
                            class="mobile-policies-toggle"
                            type="button"
                            aria-expanded="false"
                            aria-controls="mobile-policies-submenu"
                            aria-label="Toggle policy menu"
                        >
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>

                    </div>

                    <div class="mobile-policies-submenu" id="mobile-policies-submenu">

                        <a href="<?= site_url('policy.php') ?>#order">Order Policy</a>

                        <a href="<?= site_url('policy.php') ?>#cancellation">Cancellation Policy</a>

                        <a href="<?= site_url('policy.php') ?>#shipping">Shipping Policy</a>

                        <a href="<?= site_url('policy.php') ?>#replacement">Replacement Policy</a>

                        <a href="<?= site_url('policy.php') ?>#returns">Return & Refund Policy</a>

                        <a href="<?= site_url('policy.php') ?>#refund">Refund Policy</a>

                        <a href="<?= site_url('policy.php') ?>#privacy">Privacy Policy</a>

                        <a href="<?= site_url('policy.php') ?>#terms">Terms & Conditions</a>

                        <a href="<?= site_url('policy.php') ?>#disclaimer">Disclaimer</a>

                    </div>

                </div>

                <a href="<?= site_url('support.php') ?>" class="dropdown-support">Support</a>

            </div>

        </div>

        <a href="<?= site_url('about.php') ?>">About Us</a>

    </nav>

</aside>

<!-- ==================================================
     FLOATING CART BUTTON
     Customer-facing only (this include is never used
     by admin). Fixed bottom-right, stacked ABOVE the
     WhatsApp float, which itself sits above #backToTop.
================================================== -->

<a
    href="<?= site_url('cart.php') ?>"
    class="cart-float"
    aria-label="Shopping Cart"
>
    <i class="fa-solid fa-cart-shopping"></i>
    <?php if ($headerCartCount > 0): ?>
        <span class="cart-count-badge"><?= (int) $headerCartCount ?></span>
    <?php endif; ?>
</a>
