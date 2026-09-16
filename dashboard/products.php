<?php
/* ===================================================================
   ADMIN - PRODUCT LIST
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/product-image-variants.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Products';
$activePage = 'products';

$successMessage = flash_get('success');
$errorMessage   = flash_get('error');

$search = trim($_GET['search'] ?? '');


/* ==========================================
   FETCH PRODUCTS (with category name and
   primary image), optionally filtered by
   a search term on name or SKU.
========================================== */

$sql = 'SELECT
            p.id, p.name, p.sku, p.slug, p.sell_price, p.mrp, p.gst_rate,
            p.stock_quantity, p.stock_status, p.status,
            c.name AS category_name,
            (SELECT file_path FROM product_images
             WHERE product_id = p.id
             ORDER BY is_primary DESC, display_order ASC
             LIMIT 1) AS thumbnail
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id';

$params = [];

if ($search !== '') {
    $sql .= ' WHERE p.name LIKE ? OR p.sku LIKE ?';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= ' ORDER BY p.display_order ASC, p.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Products | MoonAura Admin</title>

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

                <div class="admin-toolbar">

                    <form method="get" action="products.php" class="admin-search-form">
                        <input
                            type="text"
                            name="search"
                            value="<?= h($search) ?>"
                            placeholder="Search by name or SKU..."
                        >
                        <button type="submit" class="admin-btn-secondary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </form>

                    <a href="product-form.php" class="admin-btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Add Product
                    </a>

                </div>

                <div class="admin-table-card">

                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>GST</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($products)): ?>

                                <tr>
                                    <td colspan="9" class="admin-table-empty">
                                        <?= $search !== '' ? 'No products match your search.' : 'No products yet. Add your first one above.' ?>
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($products as $product): ?>

                                    <tr>
                                        <td>
                                            <?php if ($product['thumbnail']): ?>
                                                <img
                                                    src="<?= h(product_image_variant_url($product['thumbnail'], 'sm')) ?>"
                                                    alt="<?= h($product['name']) ?>"
                                                    class="admin-table-thumb"
                                                >
                                            <?php else: ?>
                                                <div class="admin-table-thumb admin-table-thumb-empty">
                                                    <i class="fa-solid fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($product['name']) ?></td>
                                        <td><code><?= h($product['sku']) ?></code></td>
                                        <td><?= h($product['category_name'] ?? '—') ?></td>
                                        <td>
                                            <?= h(format_price((float) $product['sell_price'])) ?>
                                            <?php if ((float) $product['mrp'] > (float) $product['sell_price']): ?>
                                                <span class="admin-strike"><?= h(format_price((float) $product['mrp'])) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= h(rtrim(rtrim(sprintf('%.2F', (float) $product['gst_rate']), '0'), '.')) ?>
                                            %
                                        </td>
                                        <td>
                                            <?= (int) $product['stock_quantity'] ?>
                                            <span class="admin-badge admin-badge-<?= h(str_replace('_', '-', $product['stock_status'])) ?>">
                                                <?= h(ucfirst(str_replace('_', ' ', $product['stock_status']))) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= h($product['status']) ?>">
                                                <?= h(ucfirst($product['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="admin-table-actions">

                                            <a href="product-form.php?id=<?= (int) $product['id'] ?>" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>

                                            <form
                                                method="post"
                                                action="product-delete.php"
                                                onsubmit="return confirm('Delete this product and all its images? This cannot be undone.');"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
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
