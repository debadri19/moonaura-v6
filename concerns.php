<?php
/* ===================================================================
   ALL CONCERNS PAGE
   -------------------------------------------------------------------
   Lists every active Concern Category (the 13 approved presets - see
   includes/concern-functions.php), each linking to its own filtered
   product listing (concern.php?slug=...). This is a SEPARATE
   classification from products.purpose - see
   includes/concern-functions.php's doc comment for the distinction.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/concern-functions.php';

$concernCategories = get_concern_categories_with_counts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php theme_boot(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop By Concern | MoonAura Crystals</title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/home.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="shop-by-concern" id="all-concerns">

        <div class="container">

            <div class="section-heading">
                <span class="section-subtitle">Guided By Your Needs</span>
                <h1 class="section-title">Shop By Concern</h1>
                <p class="section-description">
                    Browse every concern we curate crystals for, and find the
                    ones aligned with what matters to you right now.
                </p>
            </div>

            <?php if (empty($concernCategories)): ?>

                <div class="empty-state">
                    <i class="fa-solid fa-layer-group"></i>
                    <p>Concern categories are coming soon.</p>
                    <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
                </div>

            <?php else: ?>

                <div class="concern-grid">

                    <?php foreach ($concernCategories as $concern): ?>

                        <a href="concern.php?slug=<?= h($concern['slug']) ?>" class="concern-card">
                            <span class="concern-icon"><i class="fa-solid <?= h(get_concern_icon_class($concern['slug'])) ?>"></i></span>
                            <span class="concern-name"><?= h($concern['name']) ?></span>
                            <span class="concern-count">
                                <?= (int) $concern['product_count'] ?>
                                product<?= (int) $concern['product_count'] === 1 ? '' : 's' ?>
                            </span>
                        </a>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
