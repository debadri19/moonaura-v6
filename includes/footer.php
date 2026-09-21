<!-- ==================================================
     FOOTER
     (Converted from components/footer.html into a PHP include.
     Markup is unchanged - only internal links now point to the
     renamed .php pages instead of .html. The "faq.html" link was
     also pointed at support.php#faq since faq.html never existed -
     see chat notes for details.)
================================================== -->

<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/analytics-functions.php';
require_once __DIR__ . '/meta-pixel-functions.php';
?>

<footer class="footer">

    <div class="container">

        <div class="footer-top">

            <!-- ==================================================
                 BRAND
            ================================================== -->

            <div class="footer-brand">

                <a href="<?= site_url('index.php') ?>" class="footer-logo">

                    <img src="<?= asset_url('assets/images/icons/logos/logo-footer-white.webp') ?>" alt="MoonAura Crystals">

                </a>

                <p class="footer-tagline">

                    Guided by the Moon, Inspired by Nature.

                </p>

                <p class="footer-brand-credit">

                    A Brand By <strong>DS Lifestyle</strong>

                </p>

                <div class="footer-social">

                    <a href="https://www.instagram.com/crystalsmoonaura" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-instagram"></i>
                    </a>

                    <a href="https://www.facebook.com/crystalsmoonaura" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>

                    <a href="https://wa.me/919242319596" aria-label="WhatsApp" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>

                    <a href="https://www.youtube.com/@crystalsmoonaura" aria-label="Youtube" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-youtube"></i>
                    </a>

                </div>

            </div>

            <!-- ==================================================
                 COLLECTIONS
            ================================================== -->

            <div class="footer-links">

                <h4>Collections</h4>

                <ul>

                    <li><a href="<?= site_url('shop.php?category=bracelets') ?>">Bracelets</a></li>
                    <li><a href="<?= site_url('shop.php?category=anklets') ?>">Anklets</a></li>
                    <li><a href="<?= site_url('shop.php?category=rings') ?>">Rings</a></li>
                    <li><a href="<?= site_url('shop.php?category=pyramids') ?>">Pyramids</a></li>
                    <li><a href="<?= site_url('shop.php?category=crystal-trees') ?>">Trees</a></li>
                    <li><a href="<?= site_url('shop.php?category=yantras') ?>">Yantras</a></li>

                </ul>

            </div>

            <!-- ==================================================
                 SUPPORT
            ================================================== -->

            <div class="footer-links">

                <h4>Support</h4>

                <ul>

                    <li><a href="<?= site_url('support.php') ?>">Contact Us</a></li>
                    <li><a href="<?= site_url('support.php') ?>#faq">FAQs</a></li>
                    <li><a href="<?= site_url('policy.php') ?>">Policy</a></li>
                    <li><a href="<?= site_url('policy.php') ?>#terms">Terms & Conditions</a></li>

                </ul>

            </div>

            <!-- ==================================================
                 CONTACT
            ================================================== -->

            <div class="footer-contact">

                <h4>Contact</h4>

                <ul>

                    <li>

                        <i class="fa-solid fa-phone"></i>

                        <a href="tel:+919242319596">

                            +91 92423 19596

                        </a>

                    </li>

                    <li>

                        <i class="fa-solid fa-envelope"></i>

                        <a href="mailto:support@moonauracrystals.in">

                            support@moonauracrystals.in

                        </a>

                    </li>

                    <li>

                        <i class="fa-solid fa-location-dot"></i>

                        <span>

                            West Bengal, India

                        </span>

                    </li>

                </ul>

            </div>

        </div>

        <!-- ==================================================
             FOOTER BOTTOM
        ================================================== -->

        <div class="footer-bottom">

            <div class="footer-payment">

                <img src="<?= asset_url('assets/images/icons/payments/rupay.svg') ?>" alt="RuPay">
                <img src="<?= asset_url('assets/images/icons/payments/visa.svg') ?>" alt="Visa">
                <img src="<?= asset_url('assets/images/icons/payments/mastercard.svg') ?>" alt="Mastercard">
                <img src="<?= asset_url('assets/images/icons/payments/upi.svg') ?>" alt="UPI">

            </div>

            <div class="footer-copyright">

                <p>

                    © 2026 MoonAura Crystals. All Rights Reserved.

                </p>

            </div>

        </div>

    </div>

</footer>

<!-- ==================================================
     WHATSAPP FLOATING BUTTON
     (site-wide quick chat - fixed bottom-right, stacked
     above #backToTop so the two floating buttons never
     overlap. Uses the same phone number as the footer
     social WhatsApp icon.)
================================================== -->

<a
    href="https://wa.me/919242319596"
    class="whatsapp-float"
    target="_blank"
    rel="noopener"
    aria-label="Chat on WhatsApp"
>
    <i class="fa-brands fa-whatsapp"></i>
</a>

<!-- ==================================================
     #29 BACK TO TOP - SITE-WIDE
     (Moved here from index.php, which previously provided
     this markup on its own, outside the shared footer -
     so the button only ever existed on the Home page.
     Markup, icon, position and behavior are unchanged;
     assets/js/main.js's initBackToTop() already just does
     document.querySelector("#backToTop") and no-ops if the
     element isn't present, so making the shared footer
     provide it is sufficient to make it site-wide - no JS
     logic change was needed, only its stale comment.)
================================================== -->

<button id="backToTop" aria-label="Back to Top">

    <i class="fa-solid fa-chevron-up"></i>

</button>

<!-- ==================================================
     #21 PHASE B - GLOBAL NAVIGATION LOADING INDICATOR
     Shown briefly during normal same-site page
     navigation (links and page-changing forms).
     See assets/js/main.js for trigger/exclusion
     logic. Purely visual; never blocks navigation.
================================================== -->

<div id="pageLoaderOverlay" aria-hidden="true">
    <div class="page-loader-spinner"></div>
</div>

<!-- ==================================================
     THEME MANAGER — PHASE 1 FOUNDATION + PHASE 4
     Light / Dark / System state. Browser localStorage
     persistence only. No account sync, no preload.
     Loaded from the shared storefront footer so every
     customer page gets one theme API.
================================================== -->
<script src="<?= versioned_asset('assets/js/theme.js') ?>"></script>

<!-- ==================================================
     #21 PHASE A - CART / WISHLIST AJAX
     Loaded from the shared customer footer so every
     page with a wishlist/cart form actually gets the
     interceptors. Cache-busted so a stale copy cannot
     silently fall back to full form POST/reload.
================================================== -->
<script src="<?= versioned_asset('assets/js/cart-wishlist-ajax.js') ?>"></script>
<?php ga4_print_storefront_tag(); ?>
<?php meta_pixel_print_base_tag(); ?>
<?php meta_pixel_print_events(); ?>
