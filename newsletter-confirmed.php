<?php
/* ===================================================================
   NEWSLETTER - CONFIRMED
   -------------------------------------------------------------------
   Public landing page after the visitor clicks the confirmation
   link in the Brevo automation email. Display only - does not
   change contacts, lists, or local data.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're All Set! | MoonAura Crystals</title>
    <meta name="robots" content="noindex, follow">

    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/home.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="page-content">

        <section class="newsletter">

            <div class="container">

                <div class="newsletter-box">

                    <img
                        class="newsletter-confirmed-logo"
                        src="<?= asset_url('assets/images/icons/logos/logo-footer-white.webp') ?>"
                        alt="MoonAura Crystals"
                    >

                    <h1 class="newsletter-title">

                        You're All Set!

                    </h1>

                    <p class="newsletter-description">

                        Your email address has been confirmed. Welcome to the MoonAura Crystals community.

                    </p>

                    <p class="newsletter-description">

                        You'll now receive our latest crystal updates, exclusive offers, and new launches.

                    </p>

                    <form class="newsletter-form" method="get" action="shop.php">

                        <button type="submit">

                            Continue Shopping

                        </button>

                    </form>

                </div>

            </div>

        </section>

    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
