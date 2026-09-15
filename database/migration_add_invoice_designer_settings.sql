-- ===================================================================
-- MIGRATION: Invoice Designer settings (Phase 6)
-- -------------------------------------------------------------------
-- Adds ONE new table to store the Invoice Designer's configuration -
-- everything an admin controls from Admin > Settings > Invoice
-- Designer (logo/watermark placement, branding text, header title,
-- which order/payment/address fields show and how they're aligned,
-- product-table column widths, tax-summary line visibility, footer
-- text). Nothing else is touched:
--
--   - orders / order_items / order_addresses (the immutable order
--     snapshot every invoice is rendered FROM) are completely
--     untouched - this migration adds no columns to them.
--   - GST calculation, tax-functions.php, and create_order() are
--     untouched - this table only controls how an already-computed,
--     already-stored order is LAID OUT on the page, never what the
--     numbers are.
--   - The existing `settings` table (business_name, business_gstin,
--     etc. - see includes/invoice-functions.php's
--     get_invoice_business_details()) is untouched too. Its
--     setting_value column is VARCHAR(255), far too small for a
--     structured, many-field designer configuration, which is why
--     this is a dedicated table rather than another settings row.
--
-- Single-row singleton (id is always 1) - config is one JSON blob in
-- a LONGTEXT column; see includes/invoice-designer-functions.php for
-- the exact shape and defaults (the defaults reproduce today's
-- hardcoded invoice layout exactly, so an existing install with no
-- admin customization yet renders byte-identical invoices before and
-- after this migration).
--
-- Non-destructive and idempotent (CREATE TABLE IF NOT EXISTS +
-- INSERT IGNORE for the one default row), matching this project's
-- migration convention - safe to re-run.
-- ===================================================================

CREATE TABLE IF NOT EXISTS invoice_designer_settings (

    id          TINYINT UNSIGNED NOT NULL PRIMARY KEY,

    config      LONGTEXT         NOT NULL,   -- JSON blob (see includes/invoice-designer-functions.php)

    updated_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The single settings row (id=1). Application code always reads/
-- writes id=1 only - see get_invoice_designer_settings() /
-- save_invoice_designer_settings() - this INSERT just guarantees the
-- row exists so a fresh read never has to special-case "no row yet"
-- separately from "row exists with defaults". '{}' is deliberately
-- empty: get_invoice_designer_settings() merges whatever's stored
-- over its own PHP-side defaults array, so an empty/partial JSON
-- blob (this row, or an older version of the config after a future
-- field is added) still produces a complete, valid settings array.
INSERT IGNORE INTO invoice_designer_settings (id, config) VALUES (1, '{}');
