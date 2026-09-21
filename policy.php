<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
<?php theme_boot(); ?>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="description"
          content="Read MoonAura Crystals' official policies regarding orders, shipping, cancellation, returns, refunds, privacy, terms and conditions.">

    <meta name="keywords"
          content="MoonAura Policies, Return Policy, Refund Policy, Shipping Policy, Privacy Policy">

    <meta name="author"
          content="MoonAura Crystals">

    <title>
        Policies | MoonAura Crystals
    </title>

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

    <!-- Google Fonts -->
    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- Main CSS -->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/policy.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">

</head>

<body>

    <!-- =====================================
                    HEADER
    ====================================== -->

    <?php include __DIR__ . '/includes/header.php'; ?>



    <!-- =========================
            HERO
    ========================== -->

    <section class="about-hero policy-hero">

        <div class="container">

            <span class="hero-tag">
                <i class="fa-solid fa-scroll"></i>
                MoonAura Policies
            </span>

            <h1>
                Our Policies
            </h1>

            <p>
                We believe in complete transparency. Please read our policies
                regarding orders, shipping, cancellations, returns, refunds,
                privacy and terms before placing your order.
            </p>

            <div class="policy-meta">

                <div class="policy-meta-item">

                    <i class="fa-regular fa-calendar"></i>

                    <div>

                        <span>Effective From</span>

                        <strong>July 2026</strong>

                    </div>

                </div>

                <div class="policy-meta-item">

                    <i class="fa-solid fa-shield-heart"></i>

                    <div>

                        <span>Applies To</span>

                        <strong>All MoonAura Orders</strong>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =========================
            POLICY PAGE
    ========================== -->

    <section class="policy-page page-section section-light">

        <div class="container">

            <div class="section-heading">

                <span class="section-subtitle">
                    MoonAura Policies
                </span>

                <h2 class="section-title">
                    Everything You Need to Know
                </h2>

                <p class="section-description">
                    This page explains our order process, shipping,
                    cancellation, replacement, refund, privacy policy,
                    terms & conditions and customer support.
                </p>

            </div>


            <!-- =========================
                QUICK NAVIGATION
            ========================== -->

            <div class="policy-nav">

                <a href="#order" class="policy-nav-item">

                    <i class="fa-solid fa-box-open"></i>

                    <span>Order</span>

                </a>

                <a href="#cancellation" class="policy-nav-item">

                    <i class="fa-solid fa-ban"></i>

                    <span>Cancellation</span>

                </a>

                <a href="#shipping" class="policy-nav-item">

                    <i class="fa-solid fa-truck-fast"></i>

                    <span>Shipping</span>

                </a>

                <a href="#replacement" class="policy-nav-item">

                    <i class="fa-solid fa-rotate-left"></i>

                    <span>Replacement</span>

                </a>

                <a href="#returns" class="policy-nav-item">

                    <div class="policy-nav-icon nav-danger-icon">
                        <i class="fa-solid fa-rotate-left"></i>
                    </div>

                    <span>Non Returnable</span>

                </a>

                <a href="#refund" class="policy-nav-item">

                    <i class="fa-solid fa-wallet"></i>

                    <span>Refund</span>

                </a>

                <a href="#privacy" class="policy-nav-item">

                    <i class="fa-solid fa-shield-halved"></i>

                    <span>Privacy</span>

                </a>

                <a href="#terms" class="policy-nav-item">

                    <i class="fa-solid fa-file-contract"></i>

                    <span>Terms</span>

                </a>

                <a href="#disclaimer" class="policy-nav-item">

                    <i class="fa-solid fa-gem"></i>

                    <span>Disclaimer</span>

                </a>

            </div>


            <!-- =========================
                ORDER
            ========================== -->

            <div class="policy-card scroll-offset" id="order">

                <div class="policy-card-header">

                    <div class="policy-icon">

                        <i class="fa-solid fa-box-open"></i>

                    </div>

                    <span class="policy-label">
                        Order Policy
                    </span>

                    <h2>
                        Order Placement Policy
                    </h2>

                </div>

                <p class="policy-intro">
                    Every order placed with MoonAura Crystals is processed with
                    care. Please review the following guidelines before placing
                    your order.
                </p>

                <h3>

                    <i class="fa-solid fa-circle-check"></i>

                    Regular Orders

                </h3>

                <ul class="policy-list">

                    <li>
                        We currently accept
                        <strong>Prepaid Orders Only.</strong>
                    </li>

                    <li>
                        Orders are confirmed only after successful payment
                        confirmation.
                    </li>

                    <li>
                        Processing begins only after payment confirmation.
                    </li>

                </ul>

                <h3>

                    <i class="fa-solid fa-wand-magic-sparkles"></i>

                    Customised / Personalised / Made-to-Order Products

                </h3>

                <ul class="policy-list">

                    <li>
                        50% advance payment is required while placing the order.
                    </li>

                    <li>
                        Remaining 50% payment must be completed before dispatch.
                    </li>

                    <li>
                        Production starts only after receiving the advance payment.
                    </li>

                    <li>
                        If the remaining payment is not completed within
                        <strong>7 days</strong>, the order may be cancelled.
                    </li>

                    <li>
                        Advance payment is
                        <strong>non-refundable.</strong>
                    </li>

                </ul>

            </div>


            <!-- =========================
                CANCELLATION
            ========================== -->

            <div class="policy-card theme-card card-padding scroll-offset" id="cancellation">

                <div class="policy-card-header">

                    <div class="policy-icon">

                        <i class="fa-solid fa-ban"></i>

                    </div>

                    <span class="policy-label">
                        Cancellation Policy
                    </span>

                    <h2>
                        Order Cancellation Policy
                    </h2>

                </div>

                <p class="policy-intro">
                    Orders can only be cancelled within the permitted time frame.
                    Once processing or production has started, cancellation may no
                    longer be possible.
                </p>

                <h3>

                    <i class="fa-solid fa-cart-shopping"></i>

                    Regular Orders

                </h3>

                <ul class="policy-list">

                    <li>
                        Cancellation requests are accepted within
                        <strong>12 hours</strong> of placing the order.
                    </li>

                    <li>
                        Orders cannot be cancelled after dispatch.
                    </li>

                    <li>
                        Approved refunds are processed within
                        <strong>5–7 business days.</strong>
                    </li>

                </ul>

                <h3>

                    <i class="fa-solid fa-gem"></i>

                    Custom Orders

                </h3>

                <ul class="policy-list">

                    <li>
                        Customised, personalised or made-to-order products cannot
                        be cancelled once production has begun.
                    </li>

                    <li>
                        The advance payment made for custom orders is
                        <strong>non-refundable.</strong>
                    </li>

                </ul>

            </div>


            <!-- =========================
                SHIPPING
            ========================== -->


            <div class="policy-card theme-card card-padding scroll-offset" id="shipping">

                <div class="policy-card-header">

                    <div class="policy-icon">

                        <i class="fa-solid fa-truck-fast"></i>

                    </div>

                    <span class="policy-label">
                        Shipping Policy
                    </span>

                    <h2>
                        Shipping & Delivery
                    </h2>

                </div>

                <p class="policy-intro">
                    We carefully pack every order to ensure it reaches you safely.
                    Delivery timelines may vary depending on your location and
                    courier partner.
                </p>

                <h3>

                    <i class="fa-regular fa-clock"></i>

                    Processing Time

                </h3>

                <ul class="policy-list">

                    <li>
                        Orders are generally processed within
                        <strong>1–2 business days</strong>
                        after payment confirmation.
                    </li>

                    <li>
                        Customised or made-to-order products may require additional
                        production time.
                    </li>

                    <li>
                        Dispatch timelines may vary during festivals, sales or
                        unforeseen circumstances.
                    </li>

                </ul>

                <h3>

                    <i class="fa-solid fa-money-bill-wave"></i>

                    Shipping Charges

                </h3>

                <ul class="policy-list">

                    <li>
                        Shipping charges are calculated during checkout unless a
                        free shipping offer is applicable.
                    </li>

                    <li>
                        Promotional free shipping offers will be clearly mentioned
                        on our website.
                    </li>

                </ul>

                <h3>

                    <i class="fa-solid fa-location-dot"></i>

                    Delivery Information

                </h3>

                <ul class="policy-list">

                    <li>
                        Please ensure your shipping address and contact details are
                        accurate before placing your order.
                    </li>

                    <li>
                        If delivery fails due to an incorrect address or customer
                        unavailability, re-shipping charges may apply.
                    </li>

                </ul>

                <h3>

                    <i class="fa-solid fa-route"></i>

                    Order Tracking

                </h3>

                <ul class="policy-list">

                    <li>
                        Tracking details will be shared once your order has been
                        dispatched.
                    </li>

                </ul>

                <h3>

                    <i class="fa-solid fa-cloud-rain"></i>

                    Courier Delays

                </h3>

                <ul class="policy-list">

                    <li>
                        Delivery timelines are estimates and may be affected by
                        weather conditions, strikes, festivals or unforeseen
                        courier delays.
                    </li>

                    <li>
                        MoonAura Crystals is not responsible for delays caused by
                        third-party courier partners.
                    </li>

                </ul>

            </div>


            <!-- =========================
                RETURN & REPLACEMENT
            ========================== -->

            <div class="policy-card theme-card card-padding scroll-offset" id="replacement">

                <div class="policy-card-header">

                    <div class="policy-icon">

                        <i class="fa-solid fa-rotate-left"></i>

                    </div>

                    <span class="policy-label">
                        Replacement Policy
                    </span>

                    <h2>
                        Return & Replacement
                    </h2>

                </div>

                <p class="policy-intro">
                    We offer replacement only in genuine cases where the issue is
                    verified by our support team.
                </p>

                <h3>

                    <i class="fa-solid fa-circle-check"></i>

                    Eligible Cases

                </h3>

                <ul class="policy-list">

                    <li>
                        Wrong product delivered.
                    </li>

                    <li>
                        Damaged product received.
                    </li>

                    <li>
                        Manufacturing defect.
                    </li>

                    <li>
                        Missing item in the package.
                    </li>

                </ul>

                <h3>

                    <i class="fa-solid fa-camera"></i>

                    Claim Requirements

                </h3>

                <ul class="policy-list">

                    <li>
                        Report the issue within
                        <strong>24 hours</strong>
                        of delivery.
                    </li>

                    <li>
                        A continuous unboxing video is mandatory.
                    </li>

                    <li>
                        Clear photos or videos must be provided for verification.
                    </li>

                </ul>

            </div>


            <!-- =========================
                NON RETURNABLE
            ========================== -->

            <div class="policy-card scroll-offset" id="returns">

                <div class="policy-card-header">

                    <div class="policy-icon card-danger-icon">
                        <i class="fa-solid fa-rotate-left"></i>
                    </div>

                    <span class="policy-label">
                        Non Returnable
                    </span>

                    <h2>
                        Non-Returnable & Non-Refundable Items
                    </h2>

                </div>

                <p class="policy-intro">
                    The following situations are not eligible for return, replacement
                    or refund.
                </p>

                <ul class="policy-list policy-list-danger">

                    <li>
                        Change of mind after purchase.
                    </li>

                    <li>
                        Colour, design, size or appearance preference.
                    </li>

                    <li>
                        Natural crystal inclusions, texture or colour variations.
                    </li>

                    <li>
                        Damage caused due to misuse or improper care.
                    </li>

                    <li>
                        Used or worn products.
                    </li>

                    <li>
                        Customised or personalised products.
                    </li>

                    <li>
                        Spiritual or healing expectations.
                    </li>

                </ul>

            </div>


            <!-- =========================
                REFUND
            ========================== -->

            <div class="policy-card theme-card card-padding scroll-offset" id="refund">

                <div class="policy-card-header">

                    <div class="policy-icon">

                        <i class="fa-solid fa-wallet"></i>

                    </div>

                    <span class="policy-label">
                        Refund Policy
                    </span>

                    <h2>
                        Refund Information
                    </h2>

                </div>

                <p class="policy-intro">
                    Refunds are issued only after a claim has been reviewed and
                    approved by our support team.
                </p>

                <ul class="policy-list">

                    <li>
                        Refunds are applicable only for approved claims.
                    </li>

                    <li>
                        Approved refunds are processed within
                        <strong>5–7 business days.</strong>
                    </li>

                    <li>
                        Refunds are credited through the original payment method.
                    </li>

                    <li>
                        Shipping charges, payment gateway charges and handling fees
                        are non-refundable.
                    </li>

                    <li>
                        If a replacement product is unavailable, a refund may be
                        issued after verification.
                    </li>

                </ul>

            </div>


            <!-- =========================
                PRIVACY
            ========================== -->

            <div class="policy-card theme-card card-padding scroll-offset" id="privacy">

                <div class="policy-card-header">

                    <div class="policy-icon">

                        <i class="fa-solid fa-shield-halved"></i>

                    </div>

                    <span class="policy-label">
                        Privacy Policy
                    </span>

                    <h2>
                        Your Privacy Matters
                    </h2>

                </div>

                <p class="policy-intro">
                    We respect your privacy and are committed to protecting your
                    personal information.
                </p>

                <ul class="policy-list">

                    <li>
                        Personal information is collected only to process orders,
                        provide customer support and improve your shopping
                        experience.
                    </li>

                    <li>
                        We never sell, rent or trade your personal information.
                    </li>

                    <li>
                        Information may only be shared with trusted partners when
                        required to fulfil your order or comply with legal
                        obligations.
                    </li>

                </ul>

            </div>


            <!-- =========================
                TERMS
            ========================== -->

            <div class="policy-card theme-card card-padding scroll-offset" id="terms">

                <div class="policy-card-header">

                    <div class="policy-icon">

                        <i class="fa-solid fa-file-contract"></i>

                    </div>

                    <span class="policy-label">
                        Terms & Conditions
                    </span>

                    <h2>
                        Website Terms
                    </h2>

                </div>

                <p class="policy-intro">
                    By using our website and placing an order, you agree to the
                    following terms.
                </p>

                <ul class="policy-list">

                    <li>
                        By placing an order with MoonAura Crystals, you agree to all
                        policies published on this page.
                    </li>

                    <li>
                        We reserve the right to modify or update these policies at
                        any time without prior notice.
                    </li>

                </ul>

            </div>


            <!-- =========================
                DISCLAIMER
            ========================== -->

            <div class="policy-card scroll-offset" id="disclaimer">

                <div class="policy-card-header">

                    <div class="policy-icon">

                        <i class="fa-solid fa-gem"></i>

                    </div>

                    <span class="policy-label">
                        Natural Crystal Disclaimer
                    </span>

                    <h2>
                        Crystal Information
                    </h2>

                </div>

                <p class="policy-intro">
                    Every natural crystal is unique. Minor variations are a part of
                    nature and make each piece one of a kind.
                </p>

                <ul class="policy-list">

                    <li>
                        Each natural crystal may differ in colour, inclusions,
                        shape and texture.
                    </li>

                    <li>
                        These natural variations are not manufacturing defects and
                        are not eligible for return or replacement.
                    </li>

                    <li>
                        Crystal properties and metaphysical benefits are based on
                        traditional beliefs and should not be interpreted as
                        medical, scientific or psychological claims.
                    </li>

                </ul>

            </div>


            <!-- =========================
                NEED HELP
            ========================== -->

            <div class="policy-help">

                <div class="policy-help-icon">

                    <i class="fa-solid fa-headset"></i>

                </div>

                <h2>

                    Need Assistance?

                </h2>

                <p>

                    If you have any questions regarding our policies, feel free to
                    contact our support team before placing your order.

                </p>

                <div class="policy-help-buttons">

                    <a href="https://wa.me/919242319596" class="btn btn-primary"
                    target="_blank" rel="noopener noreferrer">

                        <i class="fa-brands fa-whatsapp"></i>

                        WhatsApp Support

                    </a>

                    <a href="mailto:support@moonauracrystals.in"
                    class="btn btn-outline" target="_blank" rel="noopener noreferrer">

                        <i class="fa-solid fa-envelope"></i>

                        Email Support

                    </a>

                </div>

            </div>

        </div>

    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Load Common Header & Footer -->
    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>