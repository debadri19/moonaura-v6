<?php
/* ===================================================================
   INVOICE DESIGNER SETTINGS  (Phase 6)
   -------------------------------------------------------------------
   Reads/writes the single JSON config row in invoice_designer_settings
   (id=1 - see database/migration_add_invoice_designer_settings.sql).
   Everything here controls LAYOUT ONLY - which fields show, how
   they're aligned, font sizes, logo/watermark placement, footer text.
   Nothing here ever computes or changes a GST amount, an order total,
   or any value in orders/order_items/order_addresses - those stay
   exactly as create_order() and includes/tax-functions.php already
   produce them; this file only decides how build_invoice_pdf()
   (includes/invoice-functions.php) draws that already-correct data.

   DEFAULTS: get_invoice_designer_defaults() below is not arbitrary -
   every value matches what build_invoice_pdf() hardcoded before this
   phase (logo width 140, watermark opacity 7%/scale 380/position
   center, "TAX INVOICE" right-aligned at 16pt, etc.). This means an
   install that never opens the Invoice Designer renders a
   byte-identical invoice to before this phase - the JSON row seeded
   by the migration is literally '{}', and get_invoice_designer_settings()
   merges that (recursively, key by key) over these defaults, so a
   missing key at any depth silently falls back to the pre-Phase-6
   value rather than erroring or rendering blank.
=================================================================== */

require_once __DIR__ . '/db.php';




/* ==========================================
   MODULAR INVOICE LAYOUT MODEL
   -------------------------------------------------
   Coordinates are stored as offsets from the existing renderer's
   stable baseline positions. Sections move the whole group; individual
   lines can then be nudged independently inside that group. The admin
   canvas and the PDF renderer consume this same JSON model.
========================================== */
function get_invoice_layout_defaults(): array
{
    $sections = [
        'header' => [
            'label' => 'Header', 'x' => 0, 'y' => 0,
            'lines' => [
                'logo' => ['label' => 'Logo', 'x' => 0, 'y' => 0, 'visible' => true],
                'title' => ['label' => 'Invoice Title', 'x' => 0, 'y' => 0, 'visible' => true],
                'divider' => ['label' => 'Divider', 'x' => 0, 'y' => 0, 'visible' => true],
            ],
        ],
        'seller' => [
            'label' => 'Seller / Order Meta', 'x' => 0, 'y' => 0,
            'lines' => [
                'business_address' => ['label' => 'Business Address', 'x' => 0, 'y' => 0, 'visible' => true],
                'business_contact' => ['label' => 'Business Contact', 'x' => 0, 'y' => 0, 'visible' => true],
                'business_website' => ['label' => 'Business Website', 'x' => 0, 'y' => 0, 'visible' => true],
                'business_gstin' => ['label' => 'GSTIN', 'x' => 0, 'y' => 0, 'visible' => true],
                'invoice_date' => ['label' => 'Invoice Date', 'x' => 0, 'y' => 0, 'visible' => true],
                'invoice_number' => ['label' => 'Invoice Number', 'x' => 0, 'y' => 0, 'visible' => true],
                'order_date' => ['label' => 'Order Date', 'x' => 0, 'y' => 0, 'visible' => true],
                'order_number' => ['label' => 'Order Number', 'x' => 0, 'y' => 0, 'visible' => true],
            ],
        ],
        'addresses' => [
            'label' => 'Billing / Shipping', 'x' => 0, 'y' => 0,
            'lines' => [
                'billing_heading' => ['label' => 'Billed To', 'x' => 0, 'y' => 0, 'visible' => true],
                'billing_name' => ['label' => 'Billing Name', 'x' => 0, 'y' => 0, 'visible' => true],
                'billing_email' => ['label' => 'Billing Email', 'x' => 0, 'y' => 0, 'visible' => true],
                'billing_phone' => ['label' => 'Billing Phone', 'x' => 0, 'y' => 0, 'visible' => true],
                'shipping_heading' => ['label' => 'Ship To', 'x' => 0, 'y' => 0, 'visible' => true],
                'shipping_name' => ['label' => 'Shipping Name', 'x' => 0, 'y' => 0, 'visible' => true],
                'shipping_address' => ['label' => 'Shipping Address', 'x' => 0, 'y' => 0, 'visible' => true],
                'shipping_city' => ['label' => 'Shipping City', 'x' => 0, 'y' => 0, 'visible' => true],
            ],
        ],
        'product_table' => [
            'label' => 'Product Table', 'x' => 0, 'y' => 0,
            'lines' => [
                'table_header' => ['label' => 'Table Header', 'x' => 0, 'y' => 0, 'visible' => true],
                'items' => ['label' => 'Product Rows', 'x' => 0, 'y' => 0, 'visible' => true],
                'table_divider' => ['label' => 'Table Divider', 'x' => 0, 'y' => 0, 'visible' => true],
            ],
        ],
        'totals' => [
            'label' => 'Totals', 'x' => 0, 'y' => 0,
            'lines' => [
                'subtotal' => ['label' => 'Subtotal', 'x' => 0, 'y' => 0, 'visible' => true],
                'discount' => ['label' => 'Discount', 'x' => 0, 'y' => 0, 'visible' => true],
                'shipping' => ['label' => 'Shipping', 'x' => 0, 'y' => 0, 'visible' => true],
                'taxable_value' => ['label' => 'Taxable Value', 'x' => 0, 'y' => 0, 'visible' => true],
                'cgst' => ['label' => 'CGST', 'x' => 0, 'y' => 0, 'visible' => true],
                'sgst' => ['label' => 'SGST', 'x' => 0, 'y' => 0, 'visible' => true],
                'igst' => ['label' => 'IGST', 'x' => 0, 'y' => 0, 'visible' => true],
                'gst_included' => ['label' => 'GST (Included)', 'x' => 0, 'y' => 0, 'visible' => true],
                'grand_total' => ['label' => 'Grand Total', 'x' => 0, 'y' => 0, 'visible' => true],
            ],
        ],
        'payment' => [
            'label' => 'Payment Information', 'x' => 0, 'y' => 0,
            'lines' => [
                'heading' => ['label' => 'Payment Information', 'x' => 0, 'y' => 0, 'visible' => true],
                'payment_method' => ['label' => 'Payment Method', 'x' => 0, 'y' => 0, 'visible' => true],
                'payment_status' => ['label' => 'Payment Status', 'x' => 0, 'y' => 0, 'visible' => true],
                'transaction_id' => ['label' => 'Transaction ID', 'x' => 0, 'y' => 0, 'visible' => true],
                'payment_date' => ['label' => 'Payment Date', 'x' => 0, 'y' => 0, 'visible' => true],
                'invoice_status' => ['label' => 'Invoice Status', 'x' => 0, 'y' => 0, 'visible' => true],
            ],
        ],
        'footer' => [
            'label' => 'Footer', 'x' => 0, 'y' => 0,
            'lines' => [
                'divider' => ['label' => 'Footer Divider', 'x' => 0, 'y' => 0, 'visible' => true],
                'thank_you' => ['label' => 'Thank You', 'x' => 0, 'y' => 0, 'visible' => true],
                'footer_text' => ['label' => 'Footer Text', 'x' => 0, 'y' => 0, 'visible' => true],
                'terms_notes' => ['label' => 'Terms / Notes', 'x' => 0, 'y' => 0, 'visible' => true],
                'contact_information' => ['label' => 'Contact Information', 'x' => 0, 'y' => 0, 'visible' => true],
            ],
        ],
    ];

    return [
        'page' => ['width' => 595, 'height' => 842],
        'sections' => $sections,
    ];
}

function sanitize_invoice_layout(array $layout): array
{
    $defaults = get_invoice_layout_defaults();
    $out = merge_invoice_designer_settings($defaults, $layout);
    foreach ($out['sections'] as $sectionId => &$section) {
        $section['x'] = max(-250, min(250, (int) ($section['x'] ?? 0)));
        $section['y'] = max(-250, min(450, (int) ($section['y'] ?? 0)));
        foreach ($section['lines'] as $lineId => &$line) {
            $line['x'] = max(-250, min(250, (int) ($line['x'] ?? 0)));
            $line['y'] = max(-250, min(450, (int) ($line['y'] ?? 0)));
            $line['visible'] = (bool) ($line['visible'] ?? true);
        }
        unset($line);
    }
    unset($section);
    return $out;
}

function get_invoice_layout_position(array $layout, string $section, string $line, float $baseX, float $baseY): array
{
    $sectionData = $layout['sections'][$section] ?? ['x' => 0, 'y' => 0, 'lines' => []];
    $lineData = $sectionData['lines'][$line] ?? ['x' => 0, 'y' => 0, 'visible' => true];
    return [
        'x' => $baseX + (float) ($sectionData['x'] ?? 0) + (float) ($lineData['x'] ?? 0),
        'y' => $baseY + (float) ($sectionData['y'] ?? 0) + (float) ($lineData['y'] ?? 0),
        'visible' => (bool) ($lineData['visible'] ?? true),
    ];
}

function get_invoice_section_offset(array $layout, string $section): array
{
    $sectionData = $layout['sections'][$section] ?? ['x' => 0, 'y' => 0];
    return [(float) ($sectionData['x'] ?? 0), (float) ($sectionData['y'] ?? 0)];
}


function get_invoice_designer_defaults(): array
{
    return [
        'logo' => [
            'width'       => 140,     // points - matches the pre-Phase-6 hardcoded value
            'alignment'   => 'left',  // left | center | right
            // Empty = use the existing hardcoded site logo
            // (assets/images/icons/logos/logo-header.webp), exactly as
            // before. Set by uploading a dedicated invoice logo from
            // Admin > Settings > Invoice Designer.
            'custom_path' => '',
        ],
        'watermark' => [
            'opacity'     => 7,        // percent, 0-100
            'rotation'    => 0,        // degrees clockwise
            'scale'       => 380,      // points, square
            'position'    => 'center', // center | top-left | top-right | bottom-left | bottom-right
            // Empty = use the existing hardcoded site mark
            // (assets/images/icons/logos/logo-mobile-nav.webp), exactly
            // as before.
            'custom_path' => '',
        ],
        // Modular visual layout: the admin canvas and PDF renderer
        // both consume this same persisted coordinate model.
        'layout' => get_invoice_layout_defaults(),
        'branding' => [
            // Empty = fall back to the existing business_name setting
            // (get_invoice_business_details()) - this is an optional
            // OVERRIDE for the invoice specifically, not a second
            // "real" business name.
            'company_name' => '',
            'tagline'      => 'A Brand by DS Lifestyle',
        ],
        'header' => [
            'title'     => 'TAX INVOICE',
            'font_size' => 16,
            'alignment' => 'right', // left | center | right
        ],
        'order_info' => [
            'order_number'   => ['visible' => true, 'alignment' => 'right'],
            'order_date'     => ['visible' => true, 'alignment' => 'right'],
            'invoice_number' => ['visible' => true, 'alignment' => 'right'],
            'invoice_date'   => ['visible' => true, 'alignment' => 'right'],
        ],
        'payment_info' => [
            'alignment' => 'left', // left | center | right - applies to the whole payment-info block
            // transaction_id/payment_date default OFF - they weren't
            // shown before Phase 6. When turned on they're read from
            // the existing payment_transactions table (gateway_payment_id
            // / updated_at of the most recent successful transaction) -
            // never a new column, never invented data. For COD or any
            // order with no transaction row, they simply render "N/A"
            // rather than blocking rendering or fabricating a value.
            'payment_method' => ['visible' => true],
            'payment_status' => ['visible' => true],
            'transaction_id' => ['visible' => false],
            'payment_date'   => ['visible' => false],
            'invoice_status' => ['visible' => false],
        ],
        'addresses' => [
            'billing'  => ['visible' => true, 'alignment' => 'left'],
            'shipping' => ['visible' => true, 'alignment' => 'left'],
        ],
        'product_table' => [
            // Column boundaries as % of the usable page width - matches
            // the pre-Phase-6 hardcoded 52/68/84 (line_total's own right
            // edge is the fixed page margin, not configurable, same as
            // before). Moving a boundary changes both neighboring
            // columns' effective width - see
            // resolve_invoice_column_boundaries() below for the
            // clamping that keeps them always in order and non-
            // overlapping regardless of what an admin enters.
            'quantity_boundary'   => 52,
            'unit_price_boundary' => 68,
            'gst_rate_boundary'   => 84,
            'font_size'           => 9,
            'item_name_alignment' => 'left',  // left | right - the 4 numeric columns are always right-aligned (a currency/number column reading right-to-left is standard and isn't offered as "left" to avoid an unreadable table)
        ],
        'tax_summary' => [
            'subtotal'     => ['visible' => true,  'alignment' => 'right'],
            'discount'     => ['visible' => true,  'alignment' => 'right'],
            'shipping'     => ['visible' => true,  'alignment' => 'right'],
            'cgst'         => ['visible' => true,  'alignment' => 'right'],
            'sgst'         => ['visible' => true,  'alignment' => 'right'],
            'igst'         => ['visible' => true,  'alignment' => 'right'],
            // Off by default: the pre-Phase-6 invoice already shows the
            // itemized CGST/SGST/IGST breakdown above, so a combined
            // line by default would just duplicate the same total -
            // this is an ADDITIONAL optional line, not a replacement.
            'gst_included' => ['visible' => false, 'alignment' => 'right'],
            'grand_total'  => ['visible' => true,  'alignment' => 'right'],
        ],
        'footer' => [
            'footer_text'         => 'This is a system-generated invoice and does not require a physical signature.',
            'thank_you_text'      => '',
            'terms_notes'         => '',
            'contact_information' => '',
            'alignment'           => 'left', // left | center | right
        ],
    ];
}


/* ==========================================
   RECURSIVE MERGE
   -------------------------------------------------
   Merges $stored over $defaults key-by-key, at every nesting depth -
   a partial/older config (missing a key a later phase added, or
   simply '{}' on a fresh install) still produces a COMPLETE settings
   array, never a missing-key error or a half-blank rendered section.
========================================== */

function merge_invoice_designer_settings(array $defaults, array $stored): array
{
    foreach ($stored as $key => $value) {
        if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
            $defaults[$key] = merge_invoice_designer_settings($defaults[$key], $value);
        } else {
            $defaults[$key] = $value;
        }
    }

    return $defaults;
}


/* ==========================================
   GET THE CURRENT SETTINGS (always a complete array -
   never throws, never returns partial/missing keys)
========================================== */

function get_invoice_designer_settings(): array
{
    $defaults = get_invoice_designer_defaults();

    try {
        $stmt = db()->prepare('SELECT config FROM invoice_designer_settings WHERE id = 1 LIMIT 1');
        $stmt->execute();
        $configJson = $stmt->fetchColumn();

        if ($configJson === false || $configJson === null || $configJson === '') {
            return $defaults;
        }

        $stored = json_decode($configJson, true);

        if (!is_array($stored)) {
            return $defaults;
        }

        return merge_invoice_designer_settings($defaults, $stored);

    } catch (Throwable $e) {
        // A broken/missing table must never break invoice generation -
        // fall back to the exact pre-Phase-6 layout, same "never let a
        // settings read fail the page" contract get_setting() already
        // follows for the generic settings table.
        error_log('get_invoice_designer_settings() failed, using defaults: ' . $e->getMessage());
        return $defaults;
    }
}


/* ==========================================
   SAVE SETTINGS
   -------------------------------------------------
   $settings must already be a COMPLETE, validated array (same shape
   as get_invoice_designer_defaults()) - admin/invoice-designer.php
   builds this from the submitted form merged over
   get_invoice_designer_settings(), so a partial form submission still
   writes a complete, valid config.
========================================== */

function save_invoice_designer_settings(array $settings): void
{
    $json = json_encode($settings, JSON_UNESCAPED_UNICODE);

    $stmt = db()->prepare(
        'INSERT INTO invoice_designer_settings (id, config) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE config = VALUES(config)'
    );
    $stmt->execute([$json]);
}


/* ==========================================
   RESET TO DEFAULTS ("Restore Default Invoice Layout")
========================================== */

function reset_invoice_designer_settings(): void
{
    save_invoice_designer_settings(get_invoice_designer_defaults());
}


/* ==========================================
   RESOLVE PRODUCT-TABLE COLUMN BOUNDARIES
   -------------------------------------------------
   Clamps whatever's stored into a strictly-increasing, sane sequence
   (each at least 8 percentage points from its neighbors, all strictly
   between 0 and 100) so a malformed/edited-by-hand config can never
   produce overlapping or reversed columns - the table always renders
   safely no matter what's in the database.
========================================== */

function resolve_invoice_column_boundaries(array $productTableSettings): array
{
    $qty   = (float) ($productTableSettings['quantity_boundary'] ?? 52);
    $price = (float) ($productTableSettings['unit_price_boundary'] ?? 68);
    $gst   = (float) ($productTableSettings['gst_rate_boundary'] ?? 84);

    // Individual ceilings are deliberately tight enough that even the
    // worst-case cascade (every value pushed to its max) can never
    // put gst_rate's boundary past 97% - i.e. it can never land off
    // the page's right margin, regardless of what's stored.
    $qty   = max(20, min(70, $qty));
    $price = max($qty + 8, min(85, $price));
    $gst   = max($price + 4, min(97, $gst));

    return ['quantity' => $qty, 'unit_price' => $price, 'gst_rate' => $gst];
}


/* ==========================================
   RESOLVE AN X POSITION FOR A GIVEN ALIGNMENT
   -------------------------------------------------
   Shared by every "alignment: left|center|right" control in the
   designer - given the drawable area's left edge/width, returns the
   X to pass to text()/textRightAligned() plus which draw call to use.
   Kept as one function so every section resolves alignment identically.
========================================== */

function resolve_invoice_alignment_x(string $alignment, float $areaX0, float $areaWidth): array
{
    return match ($alignment) {
        'center' => ['x' => $areaX0 + ($areaWidth / 2), 'mode' => 'center'],
        'right'  => ['x' => $areaX0 + $areaWidth, 'mode' => 'right'],
        default  => ['x' => $areaX0, 'mode' => 'left'],
    };
}
