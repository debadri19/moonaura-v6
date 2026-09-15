<?php
/* ===================================================================
   ADMIN - ADD / EDIT PRODUCT
   -------------------------------------------------------------------
   One form handles both cases:
   - No "id" in the URL          -> Add new product
   - "id" in the URL (?id=5)     -> Edit existing product

   Image upload for the product is also handled on this page (see
   includes/image-upload-handler.php), since a product must exist in
   the database before images can be linked to it.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tax-functions.php';
require_once __DIR__ . '/../includes/concern-functions.php';
require_once __DIR__ . '/includes/image-upload-handler.php';

require_admin_login();

$admin      = current_admin();
$activePage = 'products';

$productId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEditing = $productId !== null;

$pageTitle = $isEditing ? 'Edit Product' : 'Add Product';

$errors = [];
$imageWarnings = [];

// Default (empty) values for the "Add" form.
$product = [
    'sku'               => '',
    'name'              => '',
    'slug'              => '',
    'category_id'       => '',
    'crystal_type'      => '',
    'purpose'           => '',
    'zodiac'            => '',
    'chakra'            => '',
    'variant'           => '',
    'crystal_origin'    => '',
    'display_order'     => 0,
    'featured'          => 0,
    'is_bestseller'     => 0,
    'certificate_included' => 1,
    'mrp'               => '',
    'sell_price'        => '',
    'gst_rate'          => 0.00,
    'short_description' => '',
    'full_description'  => '',
    'care_instructions' => '',
    'primary_benefits'  => '',
    'weight'            => '',
    'meta_title'        => '',
    'meta_description'  => '',
    'stock_quantity'    => 0,
    'stock_status'      => 'in_stock',
    'status'            => 'active',
];


/* ==========================================
   LOAD EXISTING PRODUCT (EDIT MODE)
========================================== */

if ($isEditing) {

    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $found = $stmt->fetch();

    if (!$found) {
        flash_set('error', 'Product not found.');
        redirect('products.php');
    }

    $product = $found;
}


/* ==========================================
   CONCERN CATEGORIES
   -------------------------------------------------------------------
   Separate from Purpose (free-text product-benefit copy, above) -
   see includes/concern-functions.php. $selectedConcernIds holds
   which of the fixed presets this product is currently assigned to;
   pre-checks the boxes below in edit mode, and is overwritten by
   whatever was submitted if the form redisplays after a validation
   error (same pattern as every other field on this form).
========================================== */

$allConcernCategories = get_active_concern_categories();

$selectedConcernIds = ($isEditing && $productId)
    ? get_product_concern_ids($productId)
    : [];


/* ==========================================
   CATEGORY LIST (for the dropdown)
========================================== */

$categories = db()->query('SELECT id, name, image_folder_name FROM categories ORDER BY name ASC')->fetchAll();


/* ==========================================
   HANDLE FORM SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    // Take whatever was submitted so the form can redisplay it if there
    // are errors, without losing what the admin already typed.
    $product['sku']               = trim($_POST['sku'] ?? '');
    $product['name']              = trim($_POST['name'] ?? '');
    $product['category_id']       = (int) ($_POST['category_id'] ?? 0);
    $product['crystal_type']      = trim($_POST['crystal_type'] ?? '');
    $product['purpose']           = trim($_POST['purpose'] ?? '');
    $product['zodiac']            = trim($_POST['zodiac'] ?? '');
    $product['chakra']            = trim($_POST['chakra'] ?? '');
    $product['variant']           = trim($_POST['variant'] ?? '');
    $product['crystal_origin']    = trim($_POST['crystal_origin'] ?? '');
    $product['display_order']    = (int) ($_POST['display_order'] ?? 0);
    $product['featured']          = isset($_POST['featured']) ? 1 : 0;
    $product['is_bestseller']     = isset($_POST['is_bestseller']) ? 1 : 0;
    $product['certificate_included'] = ((int) ($_POST['certificate_included'] ?? 1) === 1) ? 1 : 0;
    $product['mrp']                = trim($_POST['mrp'] ?? '');
    $product['sell_price']        = trim($_POST['sell_price'] ?? '');
    $product['gst_rate']          = normalize_gst_rate($_POST['gst_rate'] ?? 0.00);
    $product['short_description'] = trim($_POST['short_description'] ?? '');
    $product['full_description']  = trim($_POST['full_description'] ?? '');
    $product['care_instructions'] = trim($_POST['care_instructions'] ?? '');
    $product['primary_benefits']  = trim($_POST['primary_benefits'] ?? '');
    $product['weight']            = trim($_POST['weight'] ?? '');
    $product['meta_title']        = trim($_POST['meta_title'] ?? '');
    $product['meta_description']  = trim($_POST['meta_description'] ?? '');
    $product['stock_quantity']    = (int) ($_POST['stock_quantity'] ?? 0);
    $product['stock_status']      = $_POST['stock_status'] ?? 'in_stock';
    $product['status']            = ($_POST['status'] ?? 'active') === 'draft' ? 'draft' : 'active';

    // Concern Category checkboxes - overwrite $selectedConcernIds with
    // whatever was actually submitted (an unchecked box simply isn't
    // present in $_POST, same as any other checkbox), so the form
    // redisplays the admin's actual choice if validation fails below.
    $selectedConcernIds = array_map('intval', $_POST['concern_categories'] ?? []);

    $slugInput = trim($_POST['slug'] ?? '');

    /* ---------- Validation ---------- */

    if ($product['sku'] === '') {
        $errors[] = 'SKU is required.';
    }

    if ($product['name'] === '') {
        $errors[] = 'Product name is required.';
    }

    if ($product['category_id'] <= 0) {
        $errors[] = 'Please choose a category.';
    }

    if ($product['mrp'] === '' || !is_numeric($product['mrp']) || (float) $product['mrp'] < 0) {
        $errors[] = 'MRP must be a valid, non-negative number.';
    }

    if ($product['sell_price'] === '' || !is_numeric($product['sell_price']) || (float) $product['sell_price'] < 0) {
        $errors[] = 'Sell Price must be a valid, non-negative number.';
    }

    // GST rate is validated/normalized by the shared tax layer, which
    // rejects negatives, values over 100, and >2-decimal input. A null
    // result means the submitted value was invalid. Prices are
    // GST-INCLUSIVE: this rate is used to DERIVE the tax out of the
    // sell price - it is never added on top.
    if ($product['gst_rate'] === null) {
        $errors[] = 'GST Rate must be a percentage between 0 and 100, with up to 2 decimal places.';
    }

    if ($product['stock_quantity'] < 0) {
        $errors[] = 'Stock quantity cannot be negative.';
    }

    if (!in_array($product['stock_status'], ['in_stock', 'out_of_stock', 'low_stock'], true)) {
        $errors[] = 'Invalid stock status.';
    }

    // SKU must be unique (across all products except this one, when editing).
    if ($product['sku'] !== '') {

        $sql    = 'SELECT id FROM products WHERE sku = ?' . ($isEditing ? ' AND id != ?' : '');
        $params = $isEditing ? [$product['sku'], $productId] : [$product['sku']];

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            $errors[] = 'That SKU is already used by another product.';
        }
    }

    /* ---------- Find the chosen category (needed for the slug + image folder) ---------- */

    $selectedCategory = null;

    if ($product['category_id'] > 0) {
        foreach ($categories as $cat) {
            if ((int) $cat['id'] === $product['category_id']) {
                $selectedCategory = $cat;
                break;
            }
        }

        if (!$selectedCategory) {
            $errors[] = 'The selected category no longer exists.';
        }
    }

    /* ---------- Build the slug + image folder path ---------- */

    if (empty($errors)) {

        $baseSlug = generate_slug($slugInput !== '' ? $slugInput : $product['name']);

        if ($baseSlug === '') {
            $errors[] = 'Could not generate a valid slug from that name.';
        } else {
            $product['slug']         = make_unique_slug($baseSlug, 'products', $productId);
            $product['image_folder'] = $selectedCategory['image_folder_name'] . '/' . $product['slug'];
            if (resolve_product_image_directory($product['image_folder']) === null) {
                $errors[] = 'The product image folder path is invalid.';
            }
        }
    }

    /* ---------- Save ---------- */

    if (empty($errors)) {

        if ($isEditing) {

            $stmt = db()->prepare(
                'UPDATE products SET
                    sku = ?, name = ?, slug = ?, category_id = ?,
                    crystal_type = ?, purpose = ?, zodiac = ?, chakra = ?, variant = ?, crystal_origin = ?,
                    image_folder = ?,
                    display_order = ?, featured = ?, is_bestseller = ?, certificate_included = ?,
                    mrp = ?, sell_price = ?, gst_rate = ?,
                    short_description = ?, full_description = ?, care_instructions = ?, primary_benefits = ?, weight = ?,
                    meta_title = ?, meta_description = ?,
                    stock_quantity = ?, stock_status = ?, status = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $product['sku'], $product['name'], $product['slug'], $product['category_id'],
                $product['crystal_type'], $product['purpose'], $product['zodiac'], $product['chakra'], $product['variant'], $product['crystal_origin'],
                $product['image_folder'],
                $product['display_order'], $product['featured'], $product['is_bestseller'], $product['certificate_included'],
                $product['mrp'], $product['sell_price'], $product['gst_rate'],
                $product['short_description'], $product['full_description'], $product['care_instructions'], $product['primary_benefits'], $product['weight'],
                $product['meta_title'], $product['meta_description'],
                $product['stock_quantity'], $product['stock_status'], $product['status'],
                $productId,
            ]);

        } else {

            $stmt = db()->prepare(
                'INSERT INTO products (
                    sku, name, slug, category_id,
                    crystal_type, purpose, zodiac, chakra, variant, crystal_origin,
                    image_folder, image_count,
                    display_order, featured, is_bestseller, certificate_included,
                    mrp, sell_price, gst_rate,
                    short_description, full_description, care_instructions, primary_benefits, weight,
                    meta_title, meta_description,
                    stock_quantity, stock_status, status
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $product['sku'], $product['name'], $product['slug'], $product['category_id'],
                $product['crystal_type'], $product['purpose'], $product['zodiac'], $product['chakra'], $product['variant'], $product['crystal_origin'],
                $product['image_folder'],
                $product['display_order'], $product['featured'], $product['is_bestseller'], $product['certificate_included'],
                $product['mrp'], $product['sell_price'], $product['gst_rate'],
                $product['short_description'], $product['full_description'], $product['care_instructions'], $product['primary_benefits'], $product['weight'],
                $product['meta_title'], $product['meta_description'],
                $product['stock_quantity'], $product['stock_status'], $product['status'],
            ]);

            $productId = (int) db()->lastInsertId();
            $isEditing = true;
        }

        /* ---------- Handle image uploads, if any ---------- */

        $imageWarnings = handle_product_image_uploads($productId, $product['image_folder']);
        sync_product_image_count($productId);

        /* ---------- Save Concern Category assignments ---------- */

        save_product_concerns($productId, $selectedConcernIds);

        if (empty($imageWarnings)) {
            flash_set('success', $isEditing ? 'Product saved successfully.' : 'Product created successfully.');
            redirect('product-form.php?id=' . $productId);
        }

        // If some images failed, stay on the page so the admin can see
        // the warnings instead of silently losing that feedback on redirect.
        flash_set('success', 'Product saved. See image warnings below.');
        $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
    }
}


/* ==========================================
   EXISTING IMAGES (for the gallery below
   the form, edit mode only)
========================================== */

$existingImages = [];

if ($isEditing && $productId) {
    $stmt = db()->prepare(
        'SELECT id, file_path, is_primary, display_order
         FROM product_images
         WHERE product_id = ?
         ORDER BY display_order ASC'
    );
    $stmt->execute([$productId]);
    $existingImages = $stmt->fetchAll();
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

                <?php if (!empty($imageWarnings)): ?>
                    <div class="admin-alert admin-alert-error">
                        <ul>
                            <?php foreach ($imageWarnings as $warning): ?>
                                <li><?= h($warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="admin-toolbar admin-toolbar-end admin-product-form-toolbar">
                    <a href="products.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back
                    </a>
                    <button type="submit" form="admin-product-form" class="admin-btn-primary">
                        <i class="fa-solid <?= $isEditing ? 'fa-check' : 'fa-plus' ?>"></i>
                        <?= $isEditing ? 'Save Product' : 'Create Product' ?>
                    </button>
                </div>

                <div class="admin-form-card admin-product-form-card">

                    <form
                        method="post"
                        action="product-form.php<?= $isEditing ? '?id=' . $productId : '' ?>"
                        enctype="multipart/form-data"
                        id="admin-product-form"
                        class="admin-product-form"
                    >

                        <div class="admin-product-form-col admin-product-form-images">

                        <?= csrf_field() ?>

                        <!-- ==========================================
                             IMAGES
                        ========================================== -->

                        <h3 class="admin-form-section-title">Images</h3>

                        <div class="admin-image-selector">
                            <p class="admin-image-selector-title">Select product photos</p>
                            <span class="admin-field-hint">JPG, PNG, or WEBP, max 5MB each. Files are saved when you <?= $isEditing ? 'save this product' : 'create the product' ?>.</span>
                            <label for="images">Upload Images</label>
                            <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                            <div class="admin-image-pending" id="admin-image-pending" hidden></div>
                        </div>

                        </div>

                        <div class="admin-product-form-col admin-product-form-col-left">

                        <!-- ==========================================
                             IDENTITY
                        ========================================== -->

                        <h3 class="admin-form-section-title">Identity</h3>

                        <label for="sku">SKU</label>
                        <input type="text" id="sku" name="sku" value="<?= h($product['sku']) ?>" required>

                        <label for="name">Product Name</label>
                        <input type="text" id="name" name="name" value="<?= h($product['name']) ?>" required>

                        <label for="slug">
                            Slug
                            <span class="admin-field-hint">(leave blank to generate automatically from the name)</span>
                        </label>
                        <input type="text" id="slug" name="slug" value="<?= h($product['slug']) ?>">

                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Choose a category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option
                                    value="<?= (int) $cat['id'] ?>"
                                    <?= (int) $product['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>
                                >
                                    <?= h($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- ==========================================
                             PRICING & INVENTORY
                        ========================================== -->

                        <h3 class="admin-form-section-title">Pricing & Inventory</h3>

                        <label for="mrp">MRP (₹)</label>
                        <input type="number" id="mrp" name="mrp" step="0.01" min="0" value="<?= h((string) $product['mrp']) ?>" required>

                        <label for="sell_price">Sell Price (₹)</label>
                        <input type="number" id="sell_price" name="sell_price" step="0.01" min="0" value="<?= h((string) $product['sell_price']) ?>" required>

                        <label for="gst_rate">
                            GST Rate (%)
                            <span class="admin-field-hint">
                                Prices are GST-INCLUSIVE - the tax is derived out of the Sell Price, never added on top.
                            </span>
                        </label>
                        <input
                            type="number"
                            id="gst_rate"
                            name="gst_rate"
                            step="0.01"
                            min="0"
                            max="100"
                            value="<?= h((string) ($product['gst_rate'] ?? 0.00)) ?>"
                            placeholder="0.00"
                        >

                        <label for="stock_quantity">Stock Quantity</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?= h((string) $product['stock_quantity']) ?>">

                        <label for="stock_status">Stock Status</label>
                        <select id="stock_status" name="stock_status">
                            <option value="in_stock"     <?= $product['stock_status'] === 'in_stock'     ? 'selected' : '' ?>>In Stock</option>
                            <option value="low_stock"    <?= $product['stock_status'] === 'low_stock'    ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out_of_stock" <?= $product['stock_status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>

                        <label for="weight">Weight <span class="admin-field-hint">(e.g. "8-10 grams")</span></label>
                        <input type="text" id="weight" name="weight" value="<?= h($product['weight']) ?>">

                        <!-- ==========================================
                             SEO
                        ========================================== -->

                        <h3 class="admin-form-section-title">SEO</h3>

                        <label for="meta_title">Meta Title</label>
                        <input type="text" id="meta_title" name="meta_title" value="<?= h($product['meta_title']) ?>">

                        <label for="meta_description">Meta Description</label>
                        <textarea id="meta_description" name="meta_description" rows="2"><?= h($product['meta_description']) ?></textarea>

                        </div>

                        <div class="admin-product-form-col admin-product-form-col-right">

                        <!-- ==========================================
                             CLASSIFICATION
                        ========================================== -->

                        <h3 class="admin-form-section-title">Classification</h3>

                        <label for="crystal_type">Crystal Type</label>
                        <input type="text" id="crystal_type" name="crystal_type" value="<?= h($product['crystal_type']) ?>">

                        <label for="purpose">Purpose <span class="admin-field-hint">(comma-separated, e.g. "Confidence, Protection, Focus")</span></label>
                        <input type="text" id="purpose" name="purpose" value="<?= h($product['purpose']) ?>">

                        <label for="zodiac">Zodiac</label>
                        <input type="text" id="zodiac" name="zodiac" value="<?= h($product['zodiac']) ?>">

                        <label for="chakra">Chakra</label>
                        <input type="text" id="chakra" name="chakra" value="<?= h($product['chakra']) ?>">

                        <label for="variant">Variant <span class="admin-field-hint">(e.g. "6mm", "8mm")</span></label>
                        <input type="text" id="variant" name="variant" value="<?= h($product['variant']) ?>">

                        <label for="crystal_origin">Crystal Origin</label>
                        <input type="text" id="crystal_origin" name="crystal_origin" value="<?= h($product['crystal_origin']) ?>">

                        <label>
                            Concern Category
                            <span class="admin-field-hint">(separate from Purpose above - used for the homepage "Shop By Concern" section and /concerns pages; select as many as apply)</span>
                        </label>
                        <div class="admin-checkbox-grid">
                            <?php foreach ($allConcernCategories as $concern): ?>
                                <label class="admin-checkbox-label">
                                    <input
                                        type="checkbox"
                                        name="concern_categories[]"
                                        value="<?= (int) $concern['id'] ?>"
                                        <?= in_array((int) $concern['id'], $selectedConcernIds, true) ? 'checked' : '' ?>
                                    >
                                    <?= h($concern['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- ==========================================
                             CONTENT
                        ========================================== -->

                        <h3 class="admin-form-section-title">Content</h3>

                        <label for="short_description">Short Description</label>
                        <textarea id="short_description" name="short_description" rows="2"><?= h($product['short_description']) ?></textarea>

                        <label for="full_description">Full Description</label>
                        <textarea id="full_description" name="full_description" rows="5"><?= h($product['full_description']) ?></textarea>

                        <label for="care_instructions">Care Instructions</label>
                        <textarea id="care_instructions" name="care_instructions" rows="3"><?= h($product['care_instructions']) ?></textarea>

                        <label for="primary_benefits">Primary Benefits</label>
                        <textarea id="primary_benefits" name="primary_benefits" rows="2"><?= h($product['primary_benefits']) ?></textarea>

                        <!-- ==========================================
                             DISPLAY OPTIONS
                        ========================================== -->

                        <h3 class="admin-form-section-title">Display Options</h3>

                        <label for="display_order">Display Order</label>
                        <input type="number" id="display_order" name="display_order" min="0" value="<?= h((string) $product['display_order']) ?>">

                        <label class="admin-checkbox-label">
                            <input type="checkbox" name="featured" <?= $product['featured'] ? 'checked' : '' ?>>
                            Show in Featured Products
                        </label>

                        <label class="admin-checkbox-label">
                            <input type="checkbox" name="is_bestseller" <?= $product['is_bestseller'] ? 'checked' : '' ?>>
                            Show in Best Sellers
                        </label>

                        <label for="certificate_included">Certificate Included</label>
                        <select id="certificate_included" name="certificate_included">
                            <?php $certificateIncluded = (int) ($product['certificate_included'] ?? 1); ?>
                            <option value="1" <?= $certificateIncluded === 1 ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= $certificateIncluded === 0 ? 'selected' : '' ?>>No</option>
                        </select>

                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active (visible)</option>
                            <option value="draft"  <?= $product['status'] === 'draft'  ? 'selected' : '' ?>>Draft (hidden)</option>
                        </select>

                        </div>

                    </form>

                    <?php if ($isEditing && !empty($existingImages)): ?>

                        <div class="admin-image-gallery">

                            <?php foreach ($existingImages as $image): ?>

                                <div class="admin-image-gallery-item" id="gallery-item-<?= (int) $image['id'] ?>" data-image-id="<?= (int) $image['id'] ?>">

                                    <img src="<?= h(product_image_variant_url($image['file_path'], 'sm')) ?>" alt="Product image">

                                    <span class="admin-badge admin-badge-active admin-image-primary-badge" <?= $image['is_primary'] ? '' : 'hidden' ?>>Primary</span>

                                    <div class="admin-image-gallery-actions">

                                        <form method="post" action="product-image-set-primary.php" class="js-set-primary-form" data-ajax <?= $image['is_primary'] ? 'hidden' : '' ?>>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                            <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                                            <button type="submit" class="admin-btn-secondary admin-btn-small">Set Primary</button>
                                        </form>

                                        <form
                                            method="post"
                                            action="product-image-delete.php"
                                            onsubmit="return confirm('Delete this image?');"
                                        >
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                            <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                                            <button type="submit" class="admin-icon-btn-danger admin-btn-small">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

    <script src="<?= versioned_asset('admin/assets/js/admin-phase-c1.js', 'assets/js/admin-phase-c1.js') ?>" defer></script>

</body>
</html>
