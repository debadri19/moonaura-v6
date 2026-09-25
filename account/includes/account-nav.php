<!-- ==================================================
     ACCOUNT NAV
     Same pill dropdown as the Customer Dashboard.
     $activeAccountPage is set by the page that
     includes this file, to highlight the current tab
     and label the toggle.
================================================== -->

<?php
$accountNavLabels = [
    'dashboard'       => 'Dashboard',
    'orders'          => 'Orders',
    'addresses'       => 'Saved Addresses',
    'profile'         => 'Profile',
    'change-password' => 'Change Password',
];
$accountNavCurrent = $accountNavLabels[$activeAccountPage ?? ''] ?? 'Dashboard';
?>

<nav class="account-nav-dropdown">
    <details class="account-nav-menu">
        <summary class="account-nav-toggle" aria-expanded="false">
            <?= h($accountNavCurrent) ?>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
        </summary>
        <div class="account-nav-panel">
            <a href="dashboard.php" class="<?= ($activeAccountPage ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="orders.php" class="<?= ($activeAccountPage ?? '') === 'orders' ? 'active' : '' ?>">Orders</a>
            <a href="addresses.php" class="<?= ($activeAccountPage ?? '') === 'addresses' ? 'active' : '' ?>">Saved Addresses</a>
            <a href="profile.php" class="<?= ($activeAccountPage ?? '') === 'profile' ? 'active' : '' ?>">Profile</a>
            <a href="change-password.php" class="<?= ($activeAccountPage ?? '') === 'change-password' ? 'active' : '' ?>">Change Password</a>
            <div class="account-nav-theme">
                <button
                    type="button"
                    class="account-nav-theme-btn"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="account-nav-theme-menu"
                >
                    Theme
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
                <div
                    class="theme-toggle account-nav-theme-panel"
                    id="account-nav-theme-menu"
                    role="menu"
                    aria-label="Color theme"
                >
                    <button type="button" class="theme-toggle-btn" role="menuitemradio" data-theme-mode="light" aria-pressed="false">
                        <i class="fa-solid fa-sun" aria-hidden="true"></i>
                        Light
                    </button>
                    <button type="button" class="theme-toggle-btn" role="menuitemradio" data-theme-mode="dark" aria-pressed="false">
                        <i class="fa-solid fa-moon" aria-hidden="true"></i>
                        Dark
                    </button>
                    <button type="button" class="theme-toggle-btn" role="menuitemradio" data-theme-mode="system" aria-pressed="false">
                        <i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i>
                        System
                    </button>
                </div>
            </div>
            <a href="logout.php" class="account-nav-logout">Logout</a>
        </div>
    </details>
</nav>
