<?php
/* ===================================================================
   ADMIN - CATEGORY LIST
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Categories';
$activePage = 'categories';

$successMessage = flash_get('success');
$errorMessage   = flash_get('error');


/* ==========================================
   FETCH CATEGORIES + HOW MANY PRODUCTS
   EACH ONE HAS (so we know if it's safe
   to delete)
========================================== */

$categories = db()->query(
    'SELECT
        c.id, c.name, c.slug, c.image_folder_name, c.display_order, c.status,
        COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.display_order ASC, c.name ASC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Categories | MoonAura Admin</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
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

                <div class="admin-toolbar admin-toolbar-end">
                    <a href="category-form.php" class="admin-btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Add Category
                    </a>
                </div>

                <div class="admin-table-card">

                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Image Folder</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($categories)): ?>

                                <tr>
                                    <td colspan="7" class="admin-table-empty">
                                        No categories yet. Add your first one above.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($categories as $category): ?>

                                    <tr>
                                        <td><?= h((string) $category['display_order']) ?></td>
                                        <td><?= h($category['name']) ?></td>
                                        <td><code><?= h($category['slug']) ?></code></td>
                                        <td><code><?= h($category['image_folder_name']) ?></code></td>
                                        <td><?= (int) $category['product_count'] ?></td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= h($category['status']) ?>">
                                                <?= h(ucfirst($category['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="admin-table-actions">

                                            <a href="category-form.php?id=<?= (int) $category['id'] ?>" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>

                                            <form
                                                method="post"
                                                action="category-delete.php"
                                                onsubmit="return confirm('Delete this category? This cannot be undone.');"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                                <button type="submit" class="admin-icon-btn-danger" title="Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>

                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</body>
</html>
