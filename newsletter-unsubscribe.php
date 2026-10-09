<?php
/* ===================================================================
   NEWSLETTER - PUBLIC UNSUBSCRIBE
   -------------------------------------------------------------------
   Signed opt-out from campaign emails. Reuses the existing Brevo
   blacklist/unlink flow. Does not delete customer accounts.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/newsletter-campaign-functions.php';

$email = normalize_email((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$exp   = (string) ($_GET['exp'] ?? $_POST['exp'] ?? '');
$sig   = (string) ($_GET['sig'] ?? $_POST['sig'] ?? '');

$valid = newsletter_verify_unsubscribe_token($email, $exp, $sig);
$done  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$valid) {
        $error = 'This unsubscribe link is invalid or has expired.';
    } else {
        $result = newsletter_admin_unsubscribe($email);
        if ($result['ok']) {
            $done = true;
        } else {
            $error = $result['error'] !== '' ? $result['error'] : 'Could not unsubscribe this address.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php theme_boot(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - MoonAura Crystals</title>
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

                    <?php if ($done): ?>

                        <h1 class="newsletter-title">You're unsubscribed</h1>
                        <p class="newsletter-description">
                            <?= h($email) ?> will no longer receive MoonAura Crystals newsletter campaigns.
                        </p>
                        <p class="newsletter-description">
                            Your customer account, if you have one, was not changed.
                        </p>

                    <?php elseif (!$valid): ?>

                        <h1 class="newsletter-title">Link expired</h1>
                        <p class="newsletter-description">
                            This unsubscribe link is invalid or has expired. If you still receive campaign emails, reply to that message and we will remove you.
                        </p>

                    <?php else: ?>

                        <h1 class="newsletter-title">Unsubscribe</h1>
                        <p class="newsletter-description">
                            Stop newsletter campaigns for <?= h($email) ?>? This does not delete your customer account.
                        </p>

                        <?php if ($error !== ''): ?>
                            <p class="newsletter-feedback newsletter-feedback-error"><?= h($error) ?></p>
                        <?php endif; ?>

                        <form class="newsletter-form" method="post" action="newsletter-unsubscribe.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="email" value="<?= h($email) ?>">
                            <input type="hidden" name="exp" value="<?= h($exp) ?>">
                            <input type="hidden" name="sig" value="<?= h($sig) ?>">
                            <button type="submit">Unsubscribe</button>
                        </form>

                    <?php endif; ?>

                    <form class="newsletter-form" method="get" action="shop.php">
                        <button type="submit">Continue Shopping</button>
                    </form>

                </div>

            </div>

        </section>

    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
