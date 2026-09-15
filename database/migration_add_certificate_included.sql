-- ===================================================================
-- MIGRATION: products.certificate_included
-- -------------------------------------------------------------------
-- Adds a per-product Yes/No flag that controls whether the product
-- detail page shows the "Certificate of Authenticity Included"
-- trust badge. When 0 (No), that badge is omitted entirely.
--
-- TINYINT(1) matches products.featured / products.is_bestseller.
-- Default 1 (Yes) preserves the previous always-shown behaviour
-- for existing products.
--
-- Non-destructive and idempotent (information_schema-guarded ALTER,
-- same pattern as migration_phase5f_gst_tax.sql). Safe to re-run;
-- fresh installs get the identical column from schema.sql.
-- ===================================================================

SET @col_certificate_included = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'certificate_included'
);

SET @ddl_certificate_included = IF(
    @col_certificate_included = 0,
    'ALTER TABLE products ADD COLUMN certificate_included TINYINT(1) NOT NULL DEFAULT 1 AFTER is_bestseller',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_certificate_included;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
