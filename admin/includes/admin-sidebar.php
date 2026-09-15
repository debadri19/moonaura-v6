<!-- ==================================================
     ADMIN SIDEBAR
     -------------------------------------------------
     $activePage is set by the page that includes this
     file, so the matching link can be highlighted.
     Products/Categories/Orders/Customers/Settings are live.

     #37 Settings Consolidation: Security (2fa-setup.php) and
     Invoice Designer (invoice-designer.php) no longer have their
     own sidebar entries - both pages still exist and work exactly
     as before, they're just reached via tiles on Settings now
     instead of a direct nav link. Nothing was deleted, so bookmarks
     / direct URLs to either page keep working unchanged.
================================================== -->

<aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin">

    <div class="admin-sidebar-head">
        <div class="admin-sidebar-logo">
            <img
                src="<?= asset_url('assets/images/icons/logos/logo-mobile-nav.webp') ?>"
                alt="MoonAura"
                class="admin-sidebar-logo-img"
            >
        </div>
        <button type="button" class="admin-sidebar-close" id="admin-sidebar-close" aria-label="Close menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="admin-nav">

        <a href="dashboard.php" title="Dashboard" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge"></i>
            Dashboard
        </a>

        <a href="products.php" title="Products" class="<?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
            <i class="fa-solid fa-gem"></i>
            Products
        </a>

        <a href="categories.php" title="Categories" class="<?= ($activePage ?? '') === 'categories' ? 'active' : '' ?>">
            <i class="fa-solid fa-layer-group"></i>
            Categories
        </a>

        <a href="orders.php" title="Orders" class="<?= ($activePage ?? '') === 'orders' ? 'active' : '' ?>">
            <i class="fa-solid fa-bag-shopping"></i>
            Orders
        </a>

        <a href="order-create.php" title="Create Order" class="<?= ($activePage ?? '') === 'order-create' ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-plus"></i>
            Create Order
        </a>

        <a href="customers.php" title="Customers" class="<?= ($activePage ?? '') === 'customers' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i>
            Customers
        </a>

        <a href="settings.php" title="Settings" class="<?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
            <i class="fa-solid fa-gear"></i>
            Settings
        </a>

    </nav>

    <button type="button" class="admin-sidebar-collapse" id="admin-sidebar-collapse" aria-label="Collapse sidebar" aria-pressed="false">
        <i class="fa-solid fa-chevron-left admin-sidebar-collapse-icon"></i>
    </button>

</aside>

<div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop"></div>
