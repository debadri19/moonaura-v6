<?php
/* ===================================================================
   ADMIN - SETTINGS - INVOICE DESIGNER  (Phase 6)
   -------------------------------------------------------------------
   Lets an admin control how build_invoice_pdf() (includes/invoice-
   functions.php) lays out an invoice, without touching code: logo/
   watermark placement, branding text, header title, which order/
   payment/address fields show and how they're aligned, product-table
   column widths, tax-summary line visibility, footer text.

   This page NEVER computes or changes a GST amount, an order total,
   or anything in orders/order_items/order_addresses - see
   includes/invoice-designer-functions.php's doc comment. It only
   writes to the new invoice_designer_settings table (one JSON row).

   One form, one submit (matching dashboard/settings.php's own pattern) -
   plus two small always-available actions (logo/watermark
   upload/remove) that act immediately rather than waiting for the
   main Save, since a stray uploaded file with no matching "keep it"
   intent would be confusing.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/invoice-designer-functions.php';

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Invoice Designer';
$activePage = 'settings';

$settings = get_invoice_designer_settings();
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $action = $_POST['action'] ?? 'save';

    /* ---------- Reset to defaults ---------- */
    if ($action === 'reset') {
        reset_invoice_designer_settings();
        flash_set('success', 'Invoice layout restored to MoonAura defaults.');
        redirect('invoice-designer.php');
    }

    /* ---------- Logo upload / replace ---------- */
    if ($action === 'upload_logo' && !empty($_FILES['logo_file']) && $_FILES['logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $result = save_uploaded_image($_FILES['logo_file'], __DIR__ . '/../assets/uploads/invoice-logo', 'invoice-logo');
        if ($result['error'] !== null) {
            $errors[] = $result['error'];
        } else {
            // Replacing an existing custom logo - remove the old file
            // so uploads don't silently accumulate on disk.
            if ($settings['logo']['custom_path'] !== '') {
                @unlink(__DIR__ . '/../' . $settings['logo']['custom_path']);
            }
            $settings['logo']['custom_path'] = 'assets/uploads/invoice-logo/' . $result['path'];
            save_invoice_designer_settings($settings);
            flash_set('success', 'Invoice logo updated.');
            redirect('invoice-designer.php');
        }
    }

    /* ---------- Logo remove (falls back to the site's default header logo) ---------- */
    if ($action === 'remove_logo') {
        if ($settings['logo']['custom_path'] !== '') {
            @unlink(__DIR__ . '/../' . $settings['logo']['custom_path']);
            $settings['logo']['custom_path'] = '';
            save_invoice_designer_settings($settings);
        }
        flash_set('success', 'Custom invoice logo removed - using the default site logo again.');
        redirect('invoice-designer.php');
    }

    /* ---------- Watermark upload / replace ---------- */
    if ($action === 'upload_watermark' && !empty($_FILES['watermark_file']) && $_FILES['watermark_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $result = save_uploaded_image($_FILES['watermark_file'], __DIR__ . '/../assets/uploads/invoice-watermark', 'invoice-watermark');
        if ($result['error'] !== null) {
            $errors[] = $result['error'];
        } else {
            if ($settings['watermark']['custom_path'] !== '') {
                @unlink(__DIR__ . '/../' . $settings['watermark']['custom_path']);
            }
            $settings['watermark']['custom_path'] = 'assets/uploads/invoice-watermark/' . $result['path'];
            save_invoice_designer_settings($settings);
            flash_set('success', 'Invoice watermark updated.');
            redirect('invoice-designer.php');
        }
    }

    /* ---------- Watermark remove ---------- */
    if ($action === 'remove_watermark') {
        if ($settings['watermark']['custom_path'] !== '') {
            @unlink(__DIR__ . '/../' . $settings['watermark']['custom_path']);
            $settings['watermark']['custom_path'] = '';
            save_invoice_designer_settings($settings);
        }
        flash_set('success', 'Custom invoice watermark removed - using the default site mark again.');
        redirect('invoice-designer.php');
    }

    /* ---------- Main settings form ---------- */
    if ($action === 'save') {

        $alignment3 = fn ($v) => in_array($v, ['left', 'center', 'right'], true) ? $v : 'left';
        $watermarkPos = fn ($v) => in_array($v, ['center', 'top-left', 'top-right', 'bottom-left', 'bottom-right'], true) ? $v : 'center';
        $clampInt = fn ($v, $min, $max, $default) => is_numeric($v) ? (int) max($min, min($max, (int) $v)) : $default;
        $clampFloat = fn ($v, $min, $max, $default) => is_numeric($v) ? max($min, min($max, (float) $v)) : $default;

        $new = $settings; // start from current (preserves custom_path fields, untouched by this form)

        $layoutRaw = trim((string) ($_POST['invoice_layout_json'] ?? ''));
        if ($layoutRaw !== '') {
            $decodedLayout = json_decode($layoutRaw, true);
            if (is_array($decodedLayout)) {
                $new['layout'] = sanitize_invoice_layout($decodedLayout);
            } else {
                $errors[] = 'The visual invoice layout could not be read. The previous layout was kept.';
            }
        }

        $new['logo']['width']     = $clampInt($_POST['logo_width'] ?? null, 40, 300, 140);
        $new['logo']['alignment'] = $alignment3($_POST['logo_alignment'] ?? 'left');

        $new['watermark']['opacity']  = $clampFloat($_POST['watermark_opacity'] ?? null, 0, 100, 7);
        $new['watermark']['rotation'] = $clampFloat($_POST['watermark_rotation'] ?? null, -180, 180, 0);
        $new['watermark']['scale']    = $clampInt($_POST['watermark_scale'] ?? null, 100, 550, 380);
        $new['watermark']['position'] = $watermarkPos($_POST['watermark_position'] ?? 'center');

        $new['branding']['company_name'] = trim((string) ($_POST['branding_company_name'] ?? ''));
        $new['branding']['tagline']      = trim((string) ($_POST['branding_tagline'] ?? ''));

        $new['header']['title']     = trim((string) ($_POST['header_title'] ?? 'TAX INVOICE')) ?: 'TAX INVOICE';
        $new['header']['font_size'] = $clampInt($_POST['header_font_size'] ?? null, 10, 28, 16);
        $new['header']['alignment'] = $alignment3($_POST['header_alignment'] ?? 'right');

        foreach (['order_number', 'order_date', 'invoice_number', 'invoice_date'] as $field) {
            $new['order_info'][$field]['visible']   = isset($_POST["order_info_{$field}_visible"]);
            $new['order_info'][$field]['alignment'] = $alignment3($_POST["order_info_{$field}_alignment"] ?? 'right');
        }

        $new['payment_info']['alignment'] = $alignment3($_POST['payment_info_alignment'] ?? 'left');
        foreach (['payment_method', 'payment_status', 'transaction_id', 'payment_date', 'invoice_status'] as $field) {
            $new['payment_info'][$field]['visible'] = isset($_POST["payment_info_{$field}_visible"]);
        }

        foreach (['billing', 'shipping'] as $field) {
            $new['addresses'][$field]['visible']   = isset($_POST["address_{$field}_visible"]);
            $new['addresses'][$field]['alignment'] = $alignment3($_POST["address_{$field}_alignment"] ?? 'left');
        }

        $new['product_table']['quantity_boundary']   = $clampFloat($_POST['product_table_quantity_boundary'] ?? null, 20, 70, 52);
        $new['product_table']['unit_price_boundary']  = $clampFloat($_POST['product_table_unit_price_boundary'] ?? null, 28, 85, 68);
        $new['product_table']['gst_rate_boundary']    = $clampFloat($_POST['product_table_gst_rate_boundary'] ?? null, 32, 97, 84);
        $new['product_table']['font_size']            = $clampInt($_POST['product_table_font_size'] ?? null, 7, 12, 9);
        $new['product_table']['item_name_alignment']  = in_array($_POST['product_table_item_name_alignment'] ?? 'left', ['left', 'right'], true)
            ? $_POST['product_table_item_name_alignment'] : 'left';

        foreach (['subtotal', 'discount', 'shipping', 'cgst', 'sgst', 'igst', 'gst_included', 'grand_total'] as $field) {
            $new['tax_summary'][$field]['visible']   = isset($_POST["tax_summary_{$field}_visible"]);
            $new['tax_summary'][$field]['alignment'] = $alignment3($_POST["tax_summary_{$field}_alignment"] ?? 'right');
        }

        $new['footer']['footer_text']         = trim((string) ($_POST['footer_footer_text'] ?? ''));
        $new['footer']['thank_you_text']      = trim((string) ($_POST['footer_thank_you_text'] ?? ''));
        $new['footer']['terms_notes']         = trim((string) ($_POST['footer_terms_notes'] ?? ''));
        $new['footer']['contact_information'] = trim((string) ($_POST['footer_contact_information'] ?? ''));
        $new['footer']['alignment']           = $alignment3($_POST['footer_alignment'] ?? 'left');

        // resolve_invoice_column_boundaries() (already used at render
        // time) will re-clamp these into a strictly-increasing order
        // regardless of what's stored, so an admin entering values
        // that overlap can never produce a broken table - but we
        // still re-order the raw stored numbers here too, so the form
        // redisplays what will actually be used rather than the raw
        // (possibly reversed) input.
        $resolved = resolve_invoice_column_boundaries($new['product_table']);
        $new['product_table']['quantity_boundary']  = $resolved['quantity'];
        $new['product_table']['unit_price_boundary'] = $resolved['unit_price'];
        $new['product_table']['gst_rate_boundary']   = $resolved['gst_rate'];

        if (empty($errors)) {
            save_invoice_designer_settings($new);
            flash_set('success', 'Invoice layout saved.');
            redirect('invoice-designer.php');
        }

        $settings = $new; // redisplay what was submitted if something above added an error
    }
}

$successMessage = flash_get('success');

// Sample data for the live preview - never a real customer/order.
$previewOrder = [
    'id' => 0,
    'order_number'   => 'MOAOD20260101SAMPLE',
    'invoice_number' => 'MOAINV20260101SAMPLE',
    'invoice_generated_at' => date('Y-m-d H:i:s'),
    'created_at'     => date('Y-m-d H:i:s'),
    'customer_name'  => 'Aanya Sharma',
    'customer_email' => 'aanya.sharma@example.com',
    'customer_phone' => '9876543210',
    'subtotal'       => 1498.00,
    'discount'       => 100.00,
    'shipping_charge' => 0.00,
    'taxable_value'  => 1331.25,
    'cgst_amount'    => 33.28,
    'sgst_amount'    => 33.28,
    'igst_amount'    => 0.00,
    'gst_amount'     => 66.56,
    'gst_rate'       => 5.00,
    'tax_type'       => 'intra',
    'grand_total'    => 1398.00,
    'payment_method' => 'cod',
    'payment_status' => 'pending',
];
$previewItems = [
    ['product_name' => 'Rose Quartz Bracelet', 'quantity' => 1, 'unit_price' => 499.00, 'line_total' => 499.00, 'gst_rate' => 5.00],
    ['product_name' => 'Green Aventurine Bracelet (Career Success)', 'quantity' => 2, 'unit_price' => 499.50, 'line_total' => 999.00, 'gst_rate' => 5.00],
];
$previewAddress = [
    'full_name'     => 'Aanya Sharma',
    'address_line1' => '221B Baker Street',
    'address_line2' => 'Near Central Park',
    'landmark'      => 'Opposite City Mall',
    'city'          => 'Kolkata',
    'state'         => 'West Bengal',
    'postal_code'   => '700001',
];
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
    <link rel="stylesheet" href="<?= versioned_asset('dashboard/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-body">

    <div class="admin-wrapper">

        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

        <div class="admin-main">

            <?php include __DIR__ . '/includes/admin-header.php'; ?>

            <div class="admin-content">

                <div class="admin-toolbar settings-back-row admin-toolbar-end admin-nav-toolbar">
                    <a href="settings.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Settings
                    </a>
                    <button type="submit" name="action" value="save" form="invoiceDesignerForm" class="admin-btn-primary">Save Invoice Layout</button>
                    <button type="submit" name="action" value="reset" form="invoiceDesignerResetForm" class="admin-btn-danger" onclick="return confirm('Restore the default MoonAura invoice layout? This replaces all Invoice Designer customizations.');">Restore Default Invoice Layout</button>
                </div>

                <?php if ($successMessage): ?>
                    <div class="admin-alert admin-alert-success"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="admin-alert admin-alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="invoice-designer-layout">

                    <div class="invoice-layout-builder-card admin-form-card">
                        <div class="invoice-layout-builder-head">
                            <div>
                                <h3 class="admin-form-section-title">Visual Layout Builder</h3>
                                <p class="invoice-designer-hint">Drag a whole section to move the group. Drag an individual line inside a section to reposition only that line. The same saved coordinates are used by the real PDF renderer.</p>
                            </div>
                            <button type="button" class="admin-btn-secondary" id="invoiceLayoutResetBtn">Reset Visual Positions</button>
                        </div>
                        <div class="invoice-layout-builder-shell">
                            <div class="invoice-layout-canvas-wrap">
                                <div class="invoice-layout-canvas" id="invoiceLayoutCanvas" aria-label="Invoice layout canvas">
                                    <div class="invoice-layout-page-grid"></div>
                                </div>
                            </div>
                            <div class="invoice-layout-builder-help">
                                <strong>How it works</strong>
                                <span>Section = move the complete block</span>
                                <span>Line = move one text/image line</span>
                                <span>Saved positions are PDF points</span>
                            </div>
                        </div>
                        <input type="hidden" name="invoice_layout_json" id="invoiceLayoutJson" form="invoiceDesignerForm" value="<?= h(json_encode($settings['layout'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                    </div>

                    <div class="invoice-designer-form">

                        <form method="post" enctype="multipart/form-data" id="invoiceDesignerForm">
                            <?= csrf_field() ?>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Logo</h3>

                                <?php if ($settings['logo']['custom_path'] !== ''): ?>
                                    <p class="invoice-designer-hint">Current custom logo:</p>
                                    <img src="<?= h(asset_url($settings['logo']['custom_path'])) ?>" alt="Current invoice logo" class="invoice-designer-image-preview">
                                <?php else: ?>
                                    <p class="invoice-designer-hint">Using the default site logo. Upload a file below to use a dedicated invoice logo instead.</p>
                                <?php endif; ?>

                                <div class="invoice-designer-upload-row">
                                    <button type="button" class="admin-btn-secondary" onclick="document.getElementById('logoFileInput').click();">Choose Logo File</button>
                                    <input type="file" id="logoFileInput" name="logo_file" accept="image/jpeg,image/png,image/webp" style="display:none;" form="logoUploadForm" onchange="this.form.submit();">
                                    <?php if ($settings['logo']['custom_path'] !== ''): ?>
                                        <button type="submit" form="logoRemoveForm" class="admin-btn-danger" onclick="return confirm('Remove the custom invoice logo and use the default site logo again?');">Remove Logo</button>
                                    <?php endif; ?>
                                </div>

                                <label for="logo_width">Logo Width (points)</label>
                                <input type="number" id="logo_width" name="logo_width" min="40" max="300" value="<?= (int) $settings['logo']['width'] ?>">

                                <label for="logo_alignment">Logo Alignment</label>
                                <select id="logo_alignment" name="logo_alignment">
                                    <?php foreach (['left', 'center', 'right'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $settings['logo']['alignment'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Watermark</h3>

                                <?php if ($settings['watermark']['custom_path'] !== ''): ?>
                                    <p class="invoice-designer-hint">Current custom watermark:</p>
                                    <img src="<?= h(asset_url($settings['watermark']['custom_path'])) ?>" alt="Current invoice watermark" class="invoice-designer-image-preview">
                                <?php else: ?>
                                    <p class="invoice-designer-hint">Using the default site mark as the watermark.</p>
                                <?php endif; ?>

                                <div class="invoice-designer-upload-row">
                                    <button type="button" class="admin-btn-secondary" onclick="document.getElementById('watermarkFileInput').click();">Choose Watermark File</button>
                                    <input type="file" id="watermarkFileInput" name="watermark_file" accept="image/jpeg,image/png,image/webp" style="display:none;" form="watermarkUploadForm" onchange="this.form.submit();">
                                    <?php if ($settings['watermark']['custom_path'] !== ''): ?>
                                        <button type="submit" form="watermarkRemoveForm" class="admin-btn-danger" onclick="return confirm('Remove the custom watermark and use the default site mark again?');">Remove Watermark</button>
                                    <?php endif; ?>
                                </div>

                                <label for="watermark_opacity">Opacity (%)</label>
                                <input type="number" id="watermark_opacity" name="watermark_opacity" min="0" max="100" value="<?= (float) $settings['watermark']['opacity'] ?>">

                                <label for="watermark_rotation">Rotation (degrees, clockwise)</label>
                                <input type="number" id="watermark_rotation" name="watermark_rotation" min="-180" max="180" value="<?= (float) $settings['watermark']['rotation'] ?>">

                                <label for="watermark_scale">Scale (points, square)</label>
                                <input type="number" id="watermark_scale" name="watermark_scale" min="100" max="550" value="<?= (int) $settings['watermark']['scale'] ?>">

                                <label for="watermark_position">Position</label>
                                <select id="watermark_position" name="watermark_position">
                                    <?php foreach (['center' => 'Center', 'top-left' => 'Top Left', 'top-right' => 'Top Right', 'bottom-left' => 'Bottom Left', 'bottom-right' => 'Bottom Right'] as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= $settings['watermark']['position'] === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Branding</h3>

                                <label for="branding_company_name">Company Name Override <span class="invoice-designer-hint-inline">(saved branding metadata; not automatically printed in the current logo-only header)</span></label>
                                <input type="text" id="branding_company_name" name="branding_company_name" value="<?= h($settings['branding']['company_name']) ?>" placeholder="Leave blank to use Business Name">

                                <label for="branding_tagline">Business Tagline <span class="invoice-designer-hint-inline">(saved branding metadata; not automatically printed in the current header)</span></label>
                                <input type="text" id="branding_tagline" name="branding_tagline" value="<?= h($settings['branding']['tagline']) ?>" placeholder="e.g. A Brand by DS Lifestyle">

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Header</h3>

                                <label for="header_title">Invoice Title</label>
                                <input type="text" id="header_title" name="header_title" value="<?= h($settings['header']['title']) ?>">

                                <label for="header_font_size">Font Size</label>
                                <input type="number" id="header_font_size" name="header_font_size" min="10" max="28" value="<?= (int) $settings['header']['font_size'] ?>">

                                <label for="header_alignment">Alignment</label>
                                <select id="header_alignment" name="header_alignment">
                                    <?php foreach (['left', 'center', 'right'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $settings['header']['alignment'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Order Information</h3>

                                <div class="admin-checkbox-grid">
                                    <?php foreach (['order_number' => 'Order Number', 'order_date' => 'Order Date', 'invoice_number' => 'Invoice Number', 'invoice_date' => 'Invoice Date'] as $field => $label): ?>
                                        <label class="admin-checkbox-label">
                                            <input type="checkbox" name="order_info_<?= $field ?>_visible" <?= $settings['order_info'][$field]['visible'] ? 'checked' : '' ?>>
                                            <?= h($label) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div class="invoice-designer-field-row">
                                    <?php foreach (['order_number' => 'Order Number', 'order_date' => 'Order Date', 'invoice_number' => 'Invoice Number', 'invoice_date' => 'Invoice Date'] as $field => $label): ?>
                                        <div>
                                            <label for="order_info_<?= $field ?>_alignment"><?= h($label) ?> Alignment</label>
                                            <select id="order_info_<?= $field ?>_alignment" name="order_info_<?= $field ?>_alignment">
                                                <?php foreach (['left', 'center', 'right'] as $opt): ?>
                                                    <option value="<?= $opt ?>" <?= $settings['order_info'][$field]['alignment'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Payment Information</h3>

                                <div class="admin-checkbox-grid">
                                    <?php foreach (['payment_method' => 'Payment Method', 'payment_status' => 'Payment Status', 'transaction_id' => 'Transaction ID', 'payment_date' => 'Payment Date'] as $field => $label): ?>
                                        <label class="admin-checkbox-label">
                                            <input type="checkbox" name="payment_info_<?= $field ?>_visible" <?= $settings['payment_info'][$field]['visible'] ? 'checked' : '' ?>>
                                            <?= h($label) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <label for="payment_info_alignment">Section Alignment</label>
                                <select id="payment_info_alignment" name="payment_info_alignment">
                                    <?php foreach (['left', 'center', 'right'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $settings['payment_info']['alignment'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <p class="invoice-designer-hint">Transaction ID / Payment Date are read from the payment record already on file for each order (never invented) - they show "N/A" for COD or any order with no online payment attempt.</p>

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Billing &amp; Shipping Addresses</h3>

                                <div class="invoice-designer-field-row">
                                    <?php foreach (['billing' => 'Billing Address', 'shipping' => 'Shipping Address'] as $field => $label): ?>
                                        <div>
                                            <label class="admin-checkbox-label">
                                                <input type="checkbox" name="address_<?= $field ?>_visible" <?= $settings['addresses'][$field]['visible'] ? 'checked' : '' ?>>
                                                <?= h($label) ?>
                                            </label>
                                            <select name="address_<?= $field ?>_alignment">
                                                <?php foreach (['left', 'center', 'right'] as $opt): ?>
                                                    <option value="<?= $opt ?>" <?= $settings['addresses'][$field]['alignment'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <p class="invoice-designer-hint">Historical order data is never changed - hiding an address here only affects how future PDF renders look, not what was recorded for any order.</p>

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Product Table</h3>

                                <label for="product_table_font_size">Font Size</label>
                                <input type="number" id="product_table_font_size" name="product_table_font_size" min="7" max="12" value="<?= (int) $settings['product_table']['font_size'] ?>">

                                <label for="product_table_item_name_alignment">Item Name Alignment</label>
                                <select id="product_table_item_name_alignment" name="product_table_item_name_alignment">
                                    <option value="left" <?= $settings['product_table']['item_name_alignment'] === 'left' ? 'selected' : '' ?>>Left</option>
                                    <option value="right" <?= $settings['product_table']['item_name_alignment'] === 'right' ? 'selected' : '' ?>>Right</option>
                                </select>

                                <label for="product_table_quantity_boundary">Quantity Column Position (% across page)</label>
                                <input type="number" id="product_table_quantity_boundary" name="product_table_quantity_boundary" min="20" max="70" step="1" value="<?= (float) $settings['product_table']['quantity_boundary'] ?>">

                                <label for="product_table_unit_price_boundary">Unit Price Column Position (%)</label>
                                <input type="number" id="product_table_unit_price_boundary" name="product_table_unit_price_boundary" min="28" max="85" step="1" value="<?= (float) $settings['product_table']['unit_price_boundary'] ?>">

                                <label for="product_table_gst_rate_boundary">GST Rate Column Position (%)</label>
                                <input type="number" id="product_table_gst_rate_boundary" name="product_table_gst_rate_boundary" min="32" max="97" step="1" value="<?= (float) $settings['product_table']['gst_rate_boundary'] ?>">

                                <p class="invoice-designer-hint">Each column position is the right edge of that column, as a percentage of the page width. Moving one changes the effective width of it and its neighbor. Line Total always ends at the page's right margin. Values are automatically kept in a safe, non-overlapping order.</p>

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">GST / Tax Summary</h3>

                                <div class="invoice-designer-field-row invoice-designer-field-row-wrap">
                                    <?php foreach ([
                                        'subtotal' => 'Subtotal', 'discount' => 'Discount', 'shipping' => 'Shipping',
                                        'cgst' => 'CGST', 'sgst' => 'SGST', 'igst' => 'IGST',
                                        'gst_included' => 'GST (Included) - combined line', 'grand_total' => 'Grand Total',
                                    ] as $field => $label): ?>
                                        <div>
                                            <label class="admin-checkbox-label">
                                                <input type="checkbox" name="tax_summary_<?= $field ?>_visible" <?= $settings['tax_summary'][$field]['visible'] ? 'checked' : '' ?>>
                                                <?= h($label) ?>
                                            </label>
                                            <select name="tax_summary_<?= $field ?>_alignment">
                                                <?php foreach (['left', 'center', 'right'] as $opt): ?>
                                                    <option value="<?= $opt ?>" <?= $settings['tax_summary'][$field]['alignment'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <p class="invoice-designer-hint">CGST/SGST/IGST only appear on orders that actually have tax - a historical pre-GST order is unaffected either way. "GST (Included)" is an additional combined line (off by default) - enabling it doesn't remove the CGST/SGST/IGST breakdown above.</p>

                            </div>

                            <div class="admin-form-card">

                                <h3 class="admin-form-section-title">Footer</h3>

                                <label for="footer_thank_you_text">Thank You Text <span class="invoice-designer-hint-inline">(optional)</span></label>
                                <input type="text" id="footer_thank_you_text" name="footer_thank_you_text" value="<?= h($settings['footer']['thank_you_text']) ?>" placeholder="e.g. Thank you for shopping with us!">

                                <label for="footer_footer_text">Footer Text</label>
                                <input type="text" id="footer_footer_text" name="footer_footer_text" value="<?= h($settings['footer']['footer_text']) ?>">

                                <label for="footer_terms_notes">Terms / Notes <span class="invoice-designer-hint-inline">(optional)</span></label>
                                <input type="text" id="footer_terms_notes" name="footer_terms_notes" value="<?= h($settings['footer']['terms_notes']) ?>" placeholder="e.g. Goods once sold will not be exchanged.">

                                <label for="footer_contact_information">Contact Information <span class="invoice-designer-hint-inline">(optional)</span></label>
                                <input type="text" id="footer_contact_information" name="footer_contact_information" value="<?= h($settings['footer']['contact_information']) ?>" placeholder="e.g. support@moonauracrystals.in">

                                <label for="footer_alignment">Footer Alignment</label>
                                <select id="footer_alignment" name="footer_alignment">
                                    <?php foreach (['left', 'center', 'right'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $settings['footer']['alignment'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            </div>

                        </form>

                        <!-- Out-of-band forms (HTML5 form="" association) for the
                             logo/watermark upload+remove actions and the reset
                             action - kept separate from the main settings form so
                             each submits with an unambiguous single "action" value
                             and never touches/overwrites the layout fields above. -->
                        <form method="post" enctype="multipart/form-data" id="logoUploadForm" action="invoice-designer.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="upload_logo">
                        </form>
                        <form method="post" id="logoRemoveForm" action="invoice-designer.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_logo">
                        </form>
                        <form method="post" enctype="multipart/form-data" id="watermarkUploadForm" action="invoice-designer.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="upload_watermark">
                        </form>
                        <form method="post" id="watermarkRemoveForm" action="invoice-designer.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_watermark">
                        </form>
                        <form method="post" id="invoiceDesignerResetForm" action="invoice-designer.php">
                            <?= csrf_field() ?>
                        </form>

                    </div>

                    <div class="invoice-designer-preview">
                        <h3 class="admin-form-section-title">Live Preview <span class="invoice-designer-hint-inline">(sample order data - save changes to update)</span></h3>
                        <div class="invoice-designer-preview-frame-wrap">
                            <iframe src="invoice-designer-preview.php" class="invoice-designer-preview-frame" title="Invoice preview"></iframe>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>


<script>
(function () {
    // Invoice Designer is a standalone admin destination, not the Settings page.
    // Normalize the sidebar state client-side as a safeguard against stale/cached markup.
    document.querySelectorAll('.admin-nav a').forEach((link) => {
        const isInvoiceDesigner = (link.getAttribute('href') || '').split('?')[0] === 'invoice-designer.php';
        link.classList.toggle('active', isInvoiceDesigner);
        if (isInvoiceDesigner) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    });

    // Collapsible settings panels: keep the right-side controls compact by default.
    document.querySelectorAll('.invoice-designer-form form > .admin-form-card').forEach((card, index) => {
        const heading = card.querySelector(':scope > .admin-form-section-title');
        if (!heading || card.dataset.collapsibleReady === '1') return;
        const content = document.createElement('div');
        content.className = 'invoice-designer-card-content';
        while (heading.nextSibling) content.appendChild(heading.nextSibling);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'invoice-designer-card-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-controls', 'invoice-designer-panel-' + index);
        toggle.innerHTML = '<span>' + heading.textContent.trim() + '</span><i class="fa-solid fa-chevron-down invoice-designer-card-chevron" aria-hidden="true"></i>';
        heading.replaceWith(toggle);
        content.id = 'invoice-designer-panel-' + index;
        content.hidden = true;
        card.appendChild(content);
        card.classList.add('invoice-designer-collapsible');
        card.dataset.collapsibleReady = '1';

        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            content.hidden = open;
            card.classList.toggle('is-open', !open);
        });
    });

    const input = document.getElementById('invoiceLayoutJson');
    const canvas = document.getElementById('invoiceLayoutCanvas');
    const resetBtn = document.getElementById('invoiceLayoutResetBtn');
    const form = document.getElementById('invoiceDesignerForm');
    if (!input || !canvas || !form) return;

    const defaultLayout = <?= json_encode(get_invoice_layout_defaults(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let layout;
    try { layout = JSON.parse(input.value); } catch (e) { layout = structuredClone(defaultLayout); }

    const geometry = {
        header: {x: 40, y: 40, w: 515, h: 75, lines: {logo:[0,0,150,34], title:[350,10,160,22], divider:[0,68,515,3]}},
        seller: {x: 40, y: 120, w: 515, h: 88, lines: {business_address:[0,0,270,22], business_contact:[0,24,270,16], business_website:[0,40,270,14], business_gstin:[0,54,270,14], invoice_date:[285,0,230,16], invoice_number:[285,16,230,16], order_date:[285,32,230,16], order_number:[285,48,230,16]}},
        addresses: {x: 40, y: 200, w: 515, h: 72, lines: {billing_heading:[0,0,240,16], billing_name:[0,17,240,14], billing_email:[0,31,240,14], billing_phone:[0,45,240,14], shipping_heading:[270,0,245,16], shipping_name:[270,17,245,14], shipping_address:[270,31,245,14], shipping_city:[270,45,245,14]}},
        product_table: {x: 40, y: 292, w: 515, h: 120, lines: {table_header:[0,0,515,22], items:[0,24,515,72], table_divider:[0,98,515,3]}},
        totals: {x: 40, y: 440, w: 515, h: 190, lines: {subtotal:[340,0,175,16], discount:[340,16,175,16], shipping:[340,32,175,16], taxable_value:[340,48,175,16], cgst:[340,64,175,16], sgst:[340,80,175,16], igst:[340,96,175,16], gst_included:[340,112,175,16], grand_total:[300,140,215,22]}},
        payment: {x: 40, y: 645, w: 515, h: 108, lines: {heading:[0,0,515,18], payment_method:[0,24,515,14], payment_status:[0,37,515,14], transaction_id:[0,50,515,14], payment_date:[0,63,515,14], invoice_status:[0,76,515,14]}},
        footer: {x: 40, y: 755, w: 515, h: 70, lines: {divider:[0,0,515,3], thank_you:[0,12,515,12], footer_text:[0,25,515,12], terms_notes:[0,38,515,12], contact_information:[0,51,515,12]}}
    };

    const labels = Object.fromEntries(Object.entries(geometry).map(([k,v]) => [k, defaultLayout.sections[k]?.label || k]));

    function normalize() {
        if (!layout || typeof layout !== 'object') layout = structuredClone(defaultLayout);
        if (!layout.sections) layout.sections = structuredClone(defaultLayout.sections);
        for (const [sectionId, g] of Object.entries(geometry)) {
            layout.sections[sectionId] ||= structuredClone(defaultLayout.sections[sectionId]);
            layout.sections[sectionId].x = Number(layout.sections[sectionId].x || 0);
            layout.sections[sectionId].y = Number(layout.sections[sectionId].y || 0);
            layout.sections[sectionId].lines ||= {};
            for (const lineId of Object.keys(g.lines)) {
                layout.sections[sectionId].lines[lineId] ||= structuredClone(defaultLayout.sections[sectionId].lines[lineId]);
                layout.sections[sectionId].lines[lineId].x = Number(layout.sections[sectionId].lines[lineId].x || 0);
                layout.sections[sectionId].lines[lineId].y = Number(layout.sections[sectionId].lines[lineId].y || 0);
            }
        }
    }

    function persist() { input.value = JSON.stringify(layout); }

    function pointFromEvent(e, rect) {
        return { x: (e.clientX - rect.left) * 595 / rect.width, y: (e.clientY - rect.top) * 842 / rect.height };
    }

    function applyPosition(el, x, y, w, h) {
        el.style.left = (x / 595 * 100) + '%';
        el.style.top = (y / 842 * 100) + '%';
        el.style.width = (w / 595 * 100) + '%';
        el.style.height = Math.max(12, h) / 842 * 100 + '%';
    }

    function applyLocalPosition(el, x, y, w, h, containerW, containerH) {
        el.style.left = (x / containerW * 100) + '%';
        el.style.top = (y / containerH * 100) + '%';
        el.style.width = Math.max(8, w) / containerW * 100 + '%';
        el.style.height = Math.max(12, h) / containerH * 100 + '%';
    }

    function makeLine(sectionId, lineId, geo) {
        const line = layout.sections[sectionId].lines[lineId];
        const el = document.createElement('div');
        el.className = 'invoice-layout-line';
        el.dataset.section = sectionId;
        el.dataset.line = lineId;
        el.textContent = line.label || lineId;
        const secGeo = geometry[sectionId];
        const localX = geo[0] + Number(line.x || 0);
        const localY = geo[1] + Number(line.y || 0);
        applyLocalPosition(el, localX, localY, geo[2], geo[3], secGeo.w, secGeo.h);
        if (line.visible === false) el.classList.add('is-hidden-line');
        el.addEventListener('pointerdown', startDragLine);
        return el;
    }

    function makeSection(sectionId, geo) {
        const sec = layout.sections[sectionId];
        const el = document.createElement('div');
        el.className = 'invoice-layout-section';
        el.dataset.section = sectionId;
        applyPosition(el, geo.x + sec.x, geo.y + sec.y, geo.w, geo.h);

        const head = document.createElement('div');
        head.className = 'invoice-layout-section-head';
        const title = document.createElement('strong');
        title.textContent = labels[sectionId];
        const grip = document.createElement('span');
        grip.textContent = '↕ Drag section';
        head.append(title, grip);
        head.addEventListener('pointerdown', startDragSection);
        el.appendChild(head);

        for (const [lineId, lineGeo] of Object.entries(geo.lines)) {
            el.appendChild(makeLine(sectionId, lineId, lineGeo));
        }
        return el;
    }

    function syncPositions() {
        normalize();
        for (const [sectionId, geo] of Object.entries(geometry)) {
            const sec = layout.sections[sectionId];
            const sectionEl = canvas.querySelector(`.invoice-layout-section[data-section="${sectionId}"]`);
            if (!sectionEl) continue;
            applyPosition(sectionEl, geo.x + sec.x, geo.y + sec.y, geo.w, geo.h);
            for (const [lineId, lineGeo] of Object.entries(geo.lines)) {
                const line = sec.lines[lineId];
                const lineEl = sectionEl.querySelector(`.invoice-layout-line[data-line="${lineId}"]`);
                if (!lineEl) continue;
                applyLocalPosition(lineEl, lineGeo[0] + Number(line.x || 0), lineGeo[1] + Number(line.y || 0), lineGeo[2], lineGeo[3], geo.w, geo.h);
            }
        }
        persist();
    }

    function render() {
        normalize();
        canvas.querySelectorAll('.invoice-layout-section').forEach(x => x.remove());
        for (const [sectionId, geo] of Object.entries(geometry)) canvas.appendChild(makeSection(sectionId, geo));
        syncPositions();
    }

    let drag = null;
    let zCounter = 100;
    function bringSectionToFront(sectionEl) {
        zCounter += 1;
        sectionEl.style.zIndex = String(zCounter);
    }
    function startDragSection(e) {
        e.preventDefault();
        const sectionId = e.currentTarget.parentElement.dataset.section;
        const rect = canvas.getBoundingClientRect();
        const start = pointFromEvent(e, rect);
        const sec = layout.sections[sectionId];
        canvas.querySelectorAll('.invoice-layout-section').forEach(x => x.classList.remove('is-dragging'));
        e.currentTarget.parentElement.classList.add('is-dragging');
        bringSectionToFront(e.currentTarget.parentElement);
        drag = {kind:'section', sectionId, start, originalX:Number(sec.x||0), originalY:Number(sec.y||0)};
        e.currentTarget.setPointerCapture?.(e.pointerId);
        window.addEventListener('pointermove', onDrag);
        window.addEventListener('pointerup', endDrag, {once:true});
    }
    function startDragLine(e) {
        e.preventDefault();
        e.stopPropagation();
        const sectionId = e.currentTarget.dataset.section, lineId = e.currentTarget.dataset.line;
        const rect = canvas.getBoundingClientRect();
        const start = pointFromEvent(e, rect);
        const line = layout.sections[sectionId].lines[lineId];
        canvas.querySelectorAll('.invoice-layout-section').forEach(x => x.classList.remove('is-dragging'));
        e.currentTarget.parentElement.classList.add('is-dragging');
        e.currentTarget.classList.add('is-dragging');
        bringSectionToFront(e.currentTarget.parentElement);
        drag = {kind:'line', sectionId, lineId, start, originalX:Number(line.x||0), originalY:Number(line.y||0), lineEl:e.currentTarget};
        e.currentTarget.setPointerCapture?.(e.pointerId);
        window.addEventListener('pointermove', onDrag);
        window.addEventListener('pointerup', endDrag, {once:true});
    }
    function onDrag(e) {
        if (!drag) return;
        const rect = canvas.getBoundingClientRect(), now = pointFromEvent(e, rect);
        const dx = Math.round(now.x - drag.start.x), dy = Math.round(now.y - drag.start.y);
        if (drag.kind === 'section') {
            layout.sections[drag.sectionId].x = Math.max(-120, Math.min(120, drag.originalX + dx));
            layout.sections[drag.sectionId].y = Math.max(-140, Math.min(260, drag.originalY + dy));
        } else {
            const line = layout.sections[drag.sectionId].lines[drag.lineId];
            line.x = Math.max(-180, Math.min(180, drag.originalX + dx));
            line.y = Math.max(-100, Math.min(300, drag.originalY + dy));
        }
        syncPositions();
    }
    function endDrag() {
        if (drag?.lineEl) drag.lineEl.classList.remove('is-dragging');
        canvas.querySelectorAll('.invoice-layout-section').forEach(x => x.classList.remove('is-dragging'));
        if (drag?.lineEl) drag.lineEl.style.zIndex = '';
        drag = null;
        window.removeEventListener('pointermove', onDrag);
        persist();
    }

    resetBtn?.addEventListener('click', () => {
        layout = structuredClone(defaultLayout);
        render();
    });
    form.addEventListener('submit', persist);
    render();
})();
</script>
</body>
</html>
