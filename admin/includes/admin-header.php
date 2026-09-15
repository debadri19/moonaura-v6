<!-- ==================================================
     ADMIN TOP BAR
     -------------------------------------------------
     $pageTitle and $admin are set by the page that
     includes this file.
================================================== -->

<header class="admin-topbar">

    <div class="admin-topbar-start">
        <button type="button" class="admin-menu-toggle" id="admin-menu-toggle" aria-label="Open menu" aria-controls="admin-sidebar" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="admin-page-title"><?= h($pageTitle ?? 'Dashboard') ?></h1>
    </div>

    <div class="admin-topbar-user">

        <span class="admin-user-name">
            <i class="fa-regular fa-user"></i>
            <?= h($admin['name'] ?? 'Admin') ?>
        </span>

        <form method="post" action="logout.php" class="admin-logout-form" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="admin-logout-link admin-logout-button">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </button>
        </form>

    </div>

</header>

<script src="<?= versioned_asset('admin/assets/js/admin-nav.js', 'assets/js/admin-nav.js') ?>" defer></script>
<?php include __DIR__ . '/page-loader.php'; ?>
