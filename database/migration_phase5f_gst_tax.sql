-- ===================================================================
-- MIGRATION: GST & Tax Architecture (Phase 5F)
-- -------------------------------------------------------------------
-- Adds product-wise GST + GST-inclusive tax snapshot columns.
--
--  1. products.gst_rate
--       Per-product GST rate (%). Prices are GST-INCLUSIVE, so this
--       rate is used to DERIVE the taxable value / GST from the
--       existing sell_price, never added on top of it. Existing
--       products get the safe default 0.00 (no tax on historical
--       behaviour) - the admin sets real rates per product later.
--
--  2. orders (order-level snapshot)
--       taxable_value   - sum of line taxable values (GST-inclusive
--                         price / (1 + rate/100))
--       cgst_amount     - intra-state CGST portion of order GST
--       sgst_amount     - intra-state SGST portion of order GST
--       igst_amount     - inter-state IGST portion of order GST
--       tax_type        - 'intra' (CGST+SGST) | 'inter' (IGST) |
--                         NULL when the seller state is not configured
--                         (see business_state setting below)
--       (gst_rate / gst_amount already exist - gst_amount holds the
--        total derived GST; these columns break it down. Existing
--        orders keep 0.00 / NULL and never change.)
--
--  3. order_items (per-line snapshot)
--       taxable_value, cgst_amount, sgst_amount, igst_amount
--       Per-line breakdown of the same numbers, so historical orders
--       remain stable even if a product's GST rate changes later
--       (order_items already snapshots gst_rate + line_total).
--
--  4. settings.business_state
--       The seller's own registered state. Used with the order's
--       shipping state (order_addresses.state) to decide intra-state
--       (CGST + SGST) vs inter-state (IGST). Default '' = not yet
--       configured; while empty the order's tax_type stays NULL and
--       the GST is split conservatively as CGST+SGST (documented in
--       includes/tax-functions.php). This is a single config key,
--       NOT a new address architecture - order_addresses.state already
--       captures the shipping state at checkout.
--
-- Non-destructive and idempotent (information_schema-guarded ALTERs,
-- same pattern as migration_phase5e_admin_2fa.sql for MySQL < 8.0.29
-- and MariaDB compatibility). Safe to re-run; fresh installs get the
-- identical columns from schema.sql.
-- ===================================================================

SET @col_products_gst = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'gst_rate'
);

SET @ddl_products_gst = IF(
    @col_products_gst = 0,
    'ALTER TABLE products ADD COLUMN gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER sell_price',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_products_gst;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_orders_taxable = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'taxable_value'
);

SET @ddl_orders_taxable = IF(
    @col_orders_taxable = 0,
    'ALTER TABLE orders ADD COLUMN taxable_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER gst_amount',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_orders_taxable;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_orders_cgst = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'cgst_amount'
);

SET @ddl_orders_cgst = IF(
    @col_orders_cgst = 0,
    'ALTER TABLE orders ADD COLUMN cgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER taxable_value',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_orders_cgst;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_orders_sgst = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'sgst_amount'
);

SET @ddl_orders_sgst = IF(
    @col_orders_sgst = 0,
    'ALTER TABLE orders ADD COLUMN sgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER cgst_amount',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_orders_sgst;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_orders_igst = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'igst_amount'
);

SET @ddl_orders_igst = IF(
    @col_orders_igst = 0,
    'ALTER TABLE orders ADD COLUMN igst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER sgst_amount',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_orders_igst;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_orders_tax_type = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'tax_type'
);

SET @ddl_orders_tax_type = IF(
    @col_orders_tax_type = 0,
    'ALTER TABLE orders ADD COLUMN tax_type VARCHAR(10) NULL AFTER igst_amount',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_orders_tax_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_items_taxable = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'taxable_value'
);

SET @ddl_items_taxable = IF(
    @col_items_taxable = 0,
    'ALTER TABLE order_items ADD COLUMN taxable_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER gst_rate',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_items_taxable;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_items_cgst = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'cgst_amount'
);

SET @ddl_items_cgst = IF(
    @col_items_cgst = 0,
    'ALTER TABLE order_items ADD COLUMN cgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER taxable_value',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_items_cgst;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_items_sgst = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'sgst_amount'
);

SET @ddl_items_sgst = IF(
    @col_items_sgst = 0,
    'ALTER TABLE order_items ADD COLUMN sgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER cgst_amount',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_items_sgst;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_items_igst = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'igst_amount'
);

SET @ddl_items_igst = IF(
    @col_items_igst = 0,
    'ALTER TABLE order_items ADD COLUMN igst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER sgst_amount',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_items_igst;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- business_state: the seller's registered state for intra/inter-state
-- tax-type resolution. Idempotent INSERT ... ON DUPLICATE KEY.
INSERT INTO settings (setting_key, setting_value) VALUES ('business_state', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
