<?php
/* ===================================================================
   ADMIN - ADD / EDIT CATEGORY
   -------------------------------------------------------------------
   One form handles both cases:
   - No "id" in the URL          -> Add new category
   - "id" in the URL (?id=5)     -> Edit existing category
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$admin      = current_admin();
$activePage = 'categories';

$categoryId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEditing  = $categoryId !== null;

$pageTitle = $isEditing ? 'Edit Category' : 'Add Category';

$errors = [];

// Default (empty) values for the "Add" form.
$category = [
    'name'              => '',
    'slug'              => '',
    'image_folder_name' => '',
    'display_order'     => 0,
    'status'            => 'active',
];


/* ==========================================
   LOAD EXISTING CATEGORY (EDIT MODE)
========================================== */

if ($isEditing) {

    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$categoryId]);
    $found = $stmt->fetch();

    if (!$found) {
        flash_set('error', 'Category not found.');
        redirect('categories.php');
    }

    $category = $found;
}


/* ==========================================
   HANDLE FORM SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    // Take whatever was submitted so the form can redisplay it if there
    // are errors, without losing what the admin already typed.
    $category['name']              = trim($_POST['name'] ?? '');
    $category['image_folder_name'] = trim($_POST['image_folder_name'] ?? '');
    $category['display_order']     = (int) ($_POST['display_order'] ?? 0);
    $category['status']            = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $slugInput = trim($_POST['slug'] ?? '');

    /* ---------- Validation ---------- */

    if ($category['name'] === '') {
        $errors[] = 'Category name is required.';
    }

    if ($category['image_folder_name'] === '') {
        $errors[] = 'Image folder name is required (must match the folder inside assets/images/products/).';
    } else {
        $safeFolder = sanitize_category_image_folder_name($category['image_folder_name']);
        if ($safeFolder === null) {
            $errors[] = 'Image folder name is invalid. Use a single folder name with letters, numbers, hyphens, or underscores only.';
        } else {
            $category['image_folder_name'] = $safeFolder;
        }
    }

    if ($category['display_order'] < 0) {
        $errors[] = 'Display order cannot be negative.';
    }

    /* ---------- Build the slug ---------- */

    if (empty($errors)) {

        $baseSlug = generate_slug($slugInput !== '' ? $slugInput : $category['name']);

        if ($baseSlug === '') {
            $errors[] = 'Could not generate a valid slug from that name.';
        } else {
            $category['slug'] = make_unique_slug($baseSlug, 'categories', $categoryId);
        }
    }

    /* ---------- Save ---------- */

    if (empty($errors)) {

        if ($isEditing) {

            $stmt = db()->prepare(
                'UPDATE categories
                 SET name = ?, slug = ?, image_folder_name = ?, display_order = ?, status = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $category['name'],
                $category['slug'],
                $category['image_folder_name'],
                $category['display_order'],
                $category['status'],
                $categoryId,
            ]);

            flash_set('success', 'Category updated successfully.');

        } else {

            $stmt = db()->prepare(
                'INSERT INTO categories (name, slug, image_folder_name, display_order, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $category['name'],
                $category['slug'],
                $category['image_folder_name'],
                $category['display_order'],
                $category['status'],
            ]);

            flash_set('success', 'Category created successfully.');
        }

        redirect('categories.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title><?= h($pageTitle) ?> | MoonAura Admin</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= versioned_asset('admin/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-body">

    <div class="admin-wrapper">

        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

        <div class="admin-main">

            <?php include __DIR__ . '/includes/admin-header.php'; ?>

            <div class="admin-content">

                <?php if (!empty($errors)): ?>
                    <div class="admin-alert admin-alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="admin-toolbar admin-toolbar-end admin-product-form-toolbar">
                    <a href="categories.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Categories
                    </a>
                    <button type="submit" form="admin-category-form" class="admin-btn-primary">
                        <i class="fa-solid <?= $isEditing ? 'fa-check' : 'fa-plus' ?>"></i>
                        <?= $isEditing ? 'Update Category' : 'Create Category' ?>
                    </button>
                </div>

                <div class="admin-form-card">

                    <form
                        method="post"
                        action="category-form.php<?= $isEditing ? '?id=' . $categoryId : '' ?>"
                        id="admin-category-form"
                    >

                        <?= csrf_field() ?>

                        <label for="name">Category Name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= h($category['name']) ?>"
                            placeholder="e.g. Bracelets"
                            required
                        >

                        <label for="slug">
                            Slug
                            <span class="admin-field-hint">(leave blank to generate automatically from the name)</span>
                        </label>
                        <input
                            type="text"
                            id="slug"
                            name="slug"
                            value="<?= h($category['slug']) ?>"
                            placeholder="e.g. bracelets"
                        >

                        <label for="image_folder_name">
                            Image Folder Name
                            <span class="admin-field-hint">(must match a folder inside assets/images/products/)</span>
                        </label>
                        <input
                            type="text"
                            id="image_folder_name"
                            name="image_folder_name"
                            value="<?= h($category['image_folder_name']) ?>"
                            placeholder="e.g. bracelets"
                            required
                        >

                        <label for="display_order">Display Order</label>
                        <input
                            type="number"
                            id="display_order"
                            name="display_order"
                            value="<?= h((string) $category['display_order']) ?>"
                            min="0"
                        >

                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active"   <?= $category['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $category['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>

                    </form>

                </div>

            </div>

        </div>

    </div>

</body>
</html>
