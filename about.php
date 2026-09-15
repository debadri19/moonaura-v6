<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>About Us | MoonAura Crystals</title>

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

    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/about-us.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

</head>

<body>

    <!-- Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- ==================================================
        ABOUT HERO
    ================================================== -->

    <section class="about-hero">

        <div class="about-hero-image">

            <img
                src="<?= asset_url('assets/images/banners/about-hero.webp') ?>"
                alt="MoonAura Crystals About Hero">

        </div>

        <div class="about-hero-overlay"></div>

        <div class="container">

            <div class="about-hero-content">

                <span class="about-hero-subtitle">

                    ABOUT MOONAURA

                </span>

                <h1 class="about-hero-title">
                    Guided by <span>Moon.</span><br>
                    Inspired by the <span>Nature.</span>
                </h1>

                <p class="about-hero-description">

                    MoonAura Crystals was born from a passion for bringing authentic
                    natural crystals into everyday life. Every piece is thoughtfully
                    selected for its beauty, uniqueness, and craftsmanship—helping
                    you create a space filled with positivity, confidence, balance,
                    and timeless elegance.

                </p>

            </div>

        </div>

    </section>

    <!-- =========================
         OUR STORY
    ========================== -->

    <section class="our-story">

        <div class="container">

            <div class="story-wrapper">

                <div class="story-content">

                    <div class="section-label">

                        <span class="label-line"></span>

                        <span class="label-text">
                            OUR STORY
                        </span>

                    </div>

                    <h2 class="section-title">
                        Crafted with Passion,
                        <span>Inspired by Nature.</span>
                    </h2>

                    <p>
                        At MoonAura Crystals, we believe every crystal carries a unique energy and purpose. Our journey began with a simple vision—to make authentic natural crystals accessible to everyone seeking positivity, balance, and mindful living.
                    </p>

                    <p>
                        Every product is carefully selected, quality-checked, and thoughtfully packaged with love. From sourcing genuine crystals to ensuring a delightful unboxing experience, every detail reflects our commitment to authenticity and customer trust.
                    </p>

                </div>

                <div class="story-image">

                    <img
                        src="<?= asset_url('assets/images/banners/about-story.webp') ?>"
                        alt="MoonAura Crystals Story">

                </div>

            </div>

        </div>

    </section>

    <!-- =========================
        MISSION & VISION
    ========================== -->

    <section class="mission-vision page-section">

        <div class="container">

            <div class="section-heading">

                <span class="section-subtitle">
                    OUR PURPOSE
                </span>

                <h2 class="section-title">
                    Mission & Vision
                </h2>

                <p class="section-description">
                    Our purpose is to offer authentic natural crystals while creating
                    a trusted shopping experience built on quality, transparency,
                    and customer satisfaction.
                </p>

            </div>

            <div class="purpose-grid">

                <article class="purpose-card">

                    <div class="purpose-icon">

                        <i class="fa-solid fa-bullseye"></i>

                    </div>

                    <h3 class="purpose-title">
                        Our Mission
                    </h3>

                    <div class="purpose-divider"></div>

                    <p class="purpose-description">
                        To make authentic natural crystals accessible to everyone
                        through carefully curated collections, premium craftsmanship,
                        honest pricing, and exceptional customer service.
                    </p>

                </article>

                <article class="purpose-card">

                    <div class="purpose-icon">

                        <i class="fa-solid fa-eye"></i>

                    </div>

                    <h3 class="purpose-title">
                        Our Vision
                    </h3>

                    <div class="purpose-divider"></div>

                    <p class="purpose-description">
                        To become a trusted destination where every crystal inspires
                        positivity, confidence, balance, and meaningful connections
                        with nature.
                    </p>

                </article>

            </div>

        </div>

    </section>

    <!-- =========================
        WHY CHOOSE MOONAURA
    ========================== -->

    <section class="why-choose">

        <div class="container">

            <div class="section-heading">

                <span class="section-subtitle">
                    WHY CHOOSE MOONAURA
                </span>

                <h2 class="section-title">
                    What Makes MoonAura Different?
                </h2>

                <p class="section-description">
                    Every detail reflects our commitment to authenticity, quality, and
                    a thoughtful customer experience. From carefully selected natural
                    crystals to secure delivery, we strive to make every purchase
                    meaningful and trustworthy.
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
         OUR PROMISE
    ========================== -->

    <section class="our-promise page-section">

        <div class="container">

            <div class="section-heading">

                <span class="section-subtitle">
                    OUR PROMISE
                </span>

                <h2 class="section-title">
                    What You Can Expect From Every Order
                </h2>

                <p class="section-description">
                    Every MoonAura order is prepared with care, ensuring quality,
                    authenticity, and a smooth shopping experience from checkout
                    to delivery.
                </p>

            </div>

            <div class="promise-grid">

                <div class="promise-item">

                    <div class="promise-icon">
                        <i class="fa-solid fa-gem"></i>
                    </div>

                    <h3>
                        Authentic Natural Crystals
                    </h3>

                </div>

                <div class="promise-item">

                    <div class="promise-icon">
                        <i class="fa-solid fa-award"></i>
                    </div>

                    <h3>
                        Quality Checked Products
                    </h3>

                </div>

                <div class="promise-item">

                    <div class="promise-icon">
                        <i class="fa-solid fa-box"></i>
                    </div>

                    <h3>
                        Secure Packaging
                    </h3>

                </div>

                <div class="promise-item">

                    <div class="promise-icon">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>

                    <h3>
                        GST Invoice Included
                    </h3>

                </div>

                <div class="promise-item">

                    <div class="promise-icon">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>

                    <h3>
                        Pan India Shipping
                    </h3>

                </div>

                <div class="promise-item">

                    <div class="promise-icon">
                        <i class="fa-solid fa-headset"></i>
                    </div>

                    <h3>
                        Dedicated Customer Care
                    </h3>

                </div>

            </div>

        </div>

    </section>

    <!-- ==========================================
        NEED HELP
    ========================================== -->

    <section class="need-help page-section section-light">

        <div class="container">

            <div class="help-box">

                <span class="section-subtitle">
                    NEED HELP?
                </span>

                <h2>
                    We're Always Here to Help
                </h2>

                <p>
                    Have questions about our crystals, shipping,
                    orders, or choosing the right product?
                    Our team is happy to assist you.
                </p>

                <div class="help-buttons">

                    <a href="support.php" class="btn-primary">

                        Contact Us

                    </a>

                </div

            </div>

        </div>

    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Load Common Header & Footer -->
    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>

</html>