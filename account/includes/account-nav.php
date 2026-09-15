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
        <summary class="account-nav-toggle">
            <?= h($accountNavCurrent) ?>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
        </summary>
        <div class="account-nav-panel">
            <a href="dashboard.php" class="<?= ($activeAccountPage ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="orders.php" class="<?= ($activeAccountPage ?? '') === 'orders' ? 'active' : '' ?>">Orders</a>
            <a href="addresses.php" class="<?= ($activeAccountPage ?? '') === 'addresses' ? 'active' : '' ?>">Saved Addresses</a>
            <a href="profile.php" class="<?= ($activeAccountPage ?? '') === 'profile' ? 'active' : '' ?>">Profile</a>
            <a href="change-password.php" class="<?= ($activeAccountPage ?? '') === 'change-password' ? 'active' : '' ?>">Change Password</a>
            <a href="logout.php">Logout</a>
        </div>
    </details>
</nav>
