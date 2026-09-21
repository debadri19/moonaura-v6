<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
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

    <title>Support | MoonAura Crystals</title>

    <!-- CSS Links-->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/support.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

</head>

<body>

    <!-- Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="support-hero">

        <div class="container support-hero-container">

            <div class="support-hero-content">

                <span class="support-hero-subtitle">

                    SUPPORT CENTER

                </span>

                <h1 class="support-hero-title">

                    We're Here to <span>Help</span>

                </h1>

                <p class="support-hero-description">

                    Have questions about your order, shipping, products or need assistance?
                    Our support team is always ready to help you with a smooth and hassle-free experience.

                </p>

            </div>

            <div class="support-hero-image">

                <img
                    src="<?= asset_url('assets/images/banners/support-banner-wide.webp') ?>"
                    alt="MoonAura Support"
                    loading="eager"
                >

            </div>

        </div>

    </section>

    <!-- =========================
         SUPPORT PAGE
    ========================== -->

    <section class="support-page">

        <div class="container">
            <!-- FAQ + Contact + Map -->
            <div class="support-grid">

                <!-- FAQ COLUMN -->
                <div class="support-faq" id="faq">

                    <div class="faq-card">

                        <h2 class="faq-card-title">
                            Frequently Asked Questions
                        </h2>

                        <div class="faq-search">

                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>

                            <input
                                type="search"
                                id="faqSearch"
                                placeholder="Search FAQs..."
                                autocomplete="off"
                                aria-label="Search FAQs"
                            >

                        </div>

                        <div class="faq-list" id="faqList">

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>Do you accept Cash on Delivery (COD)?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        MoonAura currently accepts prepaid orders only. Orders are
                                        confirmed only after successful payment confirmation, and
                                        processing begins once the payment is confirmed.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>Do you accept customised or made-to-order products?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Yes. Customised, personalised and made-to-order products are
                                        accepted. A 50% advance payment is required while placing the
                                        order, and production starts only after the advance is received.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>How does the 50% advance payment work?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        For customised, personalised or made-to-order products, a 50%
                                        advance payment is required while placing the order. The
                                        remaining 50% must be completed before dispatch.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>What happens if the remaining 50% is not paid within 7 days?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        If the remaining payment is not completed within 7 days, the
                                        order may be cancelled. The advance payment is non-refundable.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>Can I cancel my order?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Cancellation requests are accepted within 12 hours of placing
                                        the order. Orders cannot be cancelled after dispatch.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>Can a custom order be cancelled once production starts?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Customised, personalised or made-to-order products cannot be
                                        cancelled once production has begun, and the advance payment for
                                        custom orders is non-refundable.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>How long does processing and dispatch take?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Orders are generally processed within 1&ndash;2 business days after
                                        payment confirmation. Customised or made-to-order products may
                                        require additional production time, and dispatch timelines may
                                        vary during festivals, sales or unforeseen circumstances.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>How are shipping charges calculated?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Shipping charges are calculated during checkout unless a free
                                        shipping offer is applicable. Promotional free shipping offers
                                        are clearly mentioned on our website.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>How can I track my order?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Tracking details are shared once your order has been dispatched.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>What if my delivery fails due to an incorrect address or unavailability?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Please ensure your shipping address and contact details are
                                        accurate before placing your order. If delivery fails due to an
                                        incorrect address or customer unavailability, re-shipping
                                        charges may apply.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>Can courier delays affect my delivery?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Delivery timelines are estimates and may be affected by weather
                                        conditions, strikes, festivals or unforeseen courier delays.
                                        MoonAura Crystals is not responsible for delays caused by
                                        third-party courier partners.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>When am I eligible for a replacement?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Replacements are offered only in genuine, verified cases such as
                                        a wrong product delivered, a damaged product received, a
                                        manufacturing defect, or a missing item in the package.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>How do I claim a replacement?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Report the issue within 24 hours of delivery with a continuous
                                        unboxing video, along with clear photos or videos for
                                        verification.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>Which items are non-returnable and non-refundable?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Returns, replacements and refunds are not applicable for change
                                        of mind, colour, design, size or appearance preference, natural
                                        crystal inclusions or variations, damage due to misuse or
                                        improper care, used or worn products, customised or personalised
                                        products, or spiritual or healing expectations.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>How do refunds work?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Refunds are issued only for approved claims, processed within
                                        5&ndash;7 business days, and credited through the original payment
                                        method. Shipping charges, payment gateway charges and handling
                                        fees are non-refundable.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>What if a replacement product is unavailable?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        If a replacement product is unavailable, a refund may be issued
                                        after verification.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>How is my personal information used?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Personal information is collected only to process orders,
                                        provide customer support and improve your shopping experience.
                                        We never sell, rent or trade your personal information, and
                                        share it only with trusted partners when required to fulfil your
                                        order or comply with legal obligations.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>What terms apply when I place an order?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        By placing an order with MoonAura Crystals, you agree to all
                                        policies published on our policy page. We reserve the right to
                                        modify or update these policies at any time without prior notice.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>Are natural crystal variations considered defects?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Each natural crystal may differ in colour, inclusions, shape and
                                        texture. These natural variations are not manufacturing defects
                                        and are not eligible for return or replacement.
                                    </p>

                                </div>

                            </div>

                            <div class="faq-item">

                                <button class="faq-question" type="button" aria-expanded="false">

                                    <span>Do crystals come with guaranteed healing or medical benefits?</span>

                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>

                                </button>

                                <div class="faq-answer">

                                    <p>
                                        Crystal properties and metaphysical benefits are based on
                                        traditional beliefs and should not be interpreted as medical,
                                        scientific or psychological claims.
                                    </p>

                                </div>

                            </div>

                        </div>

                        <p class="faq-empty" id="faqEmpty" hidden>
                            No matching FAQs found
                        </p>

                    </div>

                </div>

                <!-- CONTACT DETAILS COLUMN -->
                <div class="support-info">

                    <div class="info-card">

                        <i class="fa-solid fa-location-dot"></i>

                        <div class="info-content">

                            <h3>Address</h3>

                            <p>
                                South Chanduria, Simurali<br>
                                Nadia, West Bengal – 741248
                            </p>

                        </div>

                    </div>

                    <div class="info-card">

                        <i class="fa-solid fa-phone"></i>

                        <div class="info-content">

                            <h3>Phone</h3>

                            <p>
                                <a href="tel:+919242319596">
                                    +91 92423 19596
                                </a>
                            </p>

                        </div>

                    </div>

                    <div class="info-card">

                        <i class="fa-solid fa-envelope"></i>

                        <div class="info-content">

                            <h3>Email</h3>

                            <p>
                                <a href="mailto:support@moonauracrystals.in">
                                    support@moonauracrystals.in
                                </a>
                            </p>

                        </div>

                    </div>

                    <div class="info-card">

                        <i class="fa-regular fa-clock"></i>

                        <div class="info-content">

                            <h3>Business Hours</h3>

                            <p>
                                Monday – Saturday<br>
                                10:00 AM – 6:00 PM
                            </p>

                        </div>

                    </div>

                </div>

                <!-- MAP COLUMN -->
                <div class="map-card">

                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d58738.77869927394!2d88.47924681644287!3d23.054093748590354!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39f8c12eeb46a52b%3A0x7e8960efa8619ddf!2sDS%20Lifestyle!5e0!3m2!1sen!2sin!4v1784534937119!5m2!1sen!2sin"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>

                </div>

            </div>

        </div>

    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Common JS -->
    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

    <!-- Support Page JS -->
    <script src="<?= versioned_asset('assets/js/support.js') ?>"></script>

</body>

</html>