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
$headerHelpActive    = nav_is_current('support.php') || nav_is_current('policy.php');
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
                        <a href="<?= site_url('index.php') ?>"<?= nav_link_attrs('index.php') ?>>Home</a>
                    </li>

                    <li>
                        <a href="<?= site_url('shop.php') ?>"<?= nav_link_attrs('shop.php') ?>>Shop</a>
                    </li>

                    <li class="dropdown">

                        <button
                            class="drop-btn<?= $headerHelpActive ? ' is-active' : '' ?>"
                            type="button"
                            aria-expanded="false"
                            <?php if ($headerHelpActive): ?>aria-current="true"<?php endif; ?>
                        >

                            Help

                            <i class="fa-solid fa-chevron-down"></i>

                        </button>

                        <div class="dropdown-content">

                            <a href="<?= site_url('policy.php') ?>"<?= nav_link_attrs('policy.php') ?>>
                                <i class="fa-solid fa-file-contract"></i>
                                Policies
                            </a>

                            <a href="<?= site_url('support.php') ?>"<?= nav_link_attrs('support.php', 'dropdown-support') ?>>
                                <i class="fa-solid fa-headset"></i>
                                Support
                            </a>

                            <a href="<?= site_url('policy.php') ?>#terms">
                                <i class="fa-solid fa-file-lines"></i>
                                Terms &amp; Conditions
                            </a>

                            <a href="<?= site_url('support.php') ?>#faq">
                                <i class="fa-solid fa-circle-question"></i>
                                FAQs
                            </a>

                        </div>

                    </li>

                    <li>
                        <a href="<?= site_url('about.php') ?>"<?= nav_link_attrs('about.php') ?>>About Us</a>
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

        <a href="<?= site_url('index.php') ?>"<?= nav_link_attrs('index.php') ?>>
            <span class="mobile-nav-icon" aria-hidden="true"><i class="fa-solid fa-house"></i></span>
            <span class="mobile-nav-label">Home</span>
        </a>

        <a href="<?= site_url('shop.php') ?>"<?= nav_link_attrs('shop.php') ?>>
            <span class="mobile-nav-icon" aria-hidden="true"><i class="fa-solid fa-bag-shopping"></i></span>
            <span class="mobile-nav-label">Shop</span>
        </a>

        <a href="<?= site_url('policy.php') ?>"<?= nav_link_attrs('policy.php') ?>>
            <span class="mobile-nav-icon" aria-hidden="true"><i class="fa-solid fa-file-contract"></i></span>
            <span class="mobile-nav-label">Policy</span>
        </a>

        <a href="<?= site_url('support.php') ?>"<?= nav_link_attrs('support.php') ?>>
            <span class="mobile-nav-icon" aria-hidden="true"><i class="fa-solid fa-headset"></i></span>
            <span class="mobile-nav-label">Support</span>
        </a>

        <a href="<?= site_url('policy.php') ?>#terms">
            <span class="mobile-nav-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
            <span class="mobile-nav-label">Terms &amp; Conditions</span>
        </a>

        <a href="<?= site_url('support.php') ?>#faq">
            <span class="mobile-nav-icon" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></span>
            <span class="mobile-nav-label">FAQs</span>
        </a>

        <a href="<?= site_url('about.php') ?>"<?= nav_link_attrs('about.php') ?>>
            <span class="mobile-nav-icon" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span>
            <span class="mobile-nav-label">About Us</span>
        </a>

    </nav>

    <p class="mobile-menu-tagline">Guided By Moon,<br>Inspired By Nature.</p>

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
