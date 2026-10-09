<?php
/* ===================================================================
   ADMIN - NEWSLETTER SUBSCRIBER DETAIL
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/newsletter-admin-functions.php';

require_admin_login();

$admin      = current_admin();
$activePage = 'newsletter';
$pageTitle  = 'Subscriber';

$email = normalize_email((string) ($_GET['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Subscriber not found.');
    redirect('newsletter.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'refresh') {
        $refresh = newsletter_admin_refresh_one($email);
        if ($refresh['ok']) {
            flash_set('success', 'Subscriber refreshed from Brevo.');
        } else {
            flash_set('error', $refresh['error'] !== '' ? $refresh['error'] : 'Could not refresh this subscriber.');
        }
        redirect('newsletter-subscriber.php?email=' . rawurlencode($email));
    }
}

$successMessage = flash_get('success');
$errorMessage   = flash_get('error');
$subscriber     = newsletter_admin_get_cached_subscriber($email);

if (!$subscriber) {
    flash_set('error', 'Subscriber not found in the local cache. Sync from Brevo first.');
    redirect('newsletter.php');
}

$liveName = (string) ($subscriber['name'] ?? '');
$liveLists = [];
$liveBlacklisted = null;
if (newsletter_is_configured()) {
    $live = newsletter_get_contact($email);
    if ((int) ($live['status'] ?? 0) === 200) {
        $body = is_array($live['body'] ?? null) ? $live['body'] : [];
        $fromLive = newsletter_admin_contact_name($body);
        if ($fromLive !== '') {
            $liveName = $fromLive;
        }
        $liveLists = newsletter_contact_list_ids($body);
        $liveBlacklisted = !empty($body['emailBlacklisted']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/admin-theme-boot.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Subscriber | MoonAura Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= versioned_asset('dashboard/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-body">

    <div class="admin-wrapper">

        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

        <div class="admin-main">

            <?php include __DIR__ . '/includes/admin-header.php'; ?>

            <div class="admin-content">

                <?php if ($successMessage): ?>
                    <div class="admin-alert admin-alert-success"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="admin-alert admin-alert-error"><?= h($errorMessage) ?></div>
                <?php endif; ?>

                <div class="admin-toolbar admin-toolbar-end admin-nav-toolbar">
                    <a href="newsletter.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Newsletter
                    </a>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="refresh">
                        <button type="submit" class="admin-btn-secondary">
                            <i class="fa-solid fa-rotate"></i>
                            Refresh from Brevo
                        </button>
                    </form>
                    <?php if (($subscriber['status'] ?? '') !== 'unsubscribed'): ?>
                        <form
                            method="post"
                            action="newsletter-unsubscribe.php"
                            onsubmit="return confirm('Unsubscribe this address from the newsletter? Customer accounts are not deleted.');"
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="email" value="<?= h($subscriber['email']) ?>">
                            <input type="hidden" name="return" value="view">
                            <button type="submit" class="admin-btn-danger">
                                <i class="fa-solid fa-user-minus"></i>
                                Unsubscribe
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="admin-form-card">
                    <h3 style="margin-top: 0; padding-top: 0; border-top: none; color: var(--primary);">Subscriber</h3>
                    <div class="admin-detail-row">
                        <span>Email</span>
                        <span><?= h($subscriber['email']) ?></span>
                    </div>
                    <div class="admin-detail-row">
                        <span>Name</span>
                        <span><?= h($liveName !== '' ? $liveName : '—') ?></span>
                    </div>
                    <div class="admin-detail-row">
                        <span>Status</span>
                        <span>
                            <span class="admin-badge <?= h(newsletter_admin_badge_class((string) $subscriber['status'])) ?>">
                                <?= h(ucfirst((string) $subscriber['status'])) ?>
                            </span>
                        </span>
                    </div>
                    <div class="admin-detail-row">
                        <span>Source</span>
                        <span><?= h(ucfirst((string) ($subscriber['source'] ?? 'brevo'))) ?></span>
                    </div>
                    <div class="admin-detail-row">
                        <span>Subscribed</span>
                        <span><?= h(newsletter_admin_format_datetime($subscriber['subscribed_at'] ?? null)) ?></span>
                    </div>
                    <div class="admin-detail-row">
                        <span>Confirmed</span>
                        <span><?= h(newsletter_admin_format_datetime($subscriber['confirmed_at'] ?? null)) ?></span>
                    </div>
                    <div class="admin-detail-row">
                        <span>Unsubscribed</span>
                        <span><?= h(newsletter_admin_format_datetime($subscriber['unsubscribed_at'] ?? null)) ?></span>
                    </div>
                    <div class="admin-detail-row">
                        <span>Last synced</span>
                        <span><?= h(newsletter_admin_format_datetime($subscriber['synced_at'] ?? null)) ?></span>
                    </div>
                    <?php if ($liveBlacklisted !== null): ?>
                        <div class="admin-detail-row">
                            <span>Brevo email blacklist</span>
                            <span><?= $liveBlacklisted ? 'Yes' : 'No' ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($liveLists !== []): ?>
                        <div class="admin-detail-row">
                            <span>Brevo list IDs</span>
                            <span><?= h(implode(', ', $liveLists)) ?></span>
                        </div>
                    <?php endif; ?>
                    <p class="admin-field-hint">Only fields provided by the existing newsletter/Brevo contact are shown. Customer account data is not modified here.</p>
                </div>

            </div>

        </div>

    </div>

</body>
</html>
