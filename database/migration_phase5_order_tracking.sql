-- ===================================================================
-- MIGRATION: Order Tracking & Timeline (Phase 5)
-- -------------------------------------------------------------------
-- Adds:
--   1. orders.courier_partner / orders.awb_number / orders.tracking_url
--      - nullable shipping/tracking details an admin enters on the
--        admin order detail page once an order is shipped. Shown to
--        the customer on their order page when the order is Shipped
--        or Delivered.
--   2. order_status_history table - one row per REAL status/payment
--      milestone (order placed, payment confirmed, processing,
--      shipped, delivered, cancelled), recorded by
--      log_order_status_event() / update_order_status() /
--      update_order_payment_status() in includes/order-functions.php
--      and includes/payment-functions.php. Entries are only ever
--      written from actual transitions - never synthesised.
--
-- Non-destructive and idempotent (guarded ALTER + CREATE TABLE IF
-- NOT EXISTS), matching this project's migration convention:
--   - Existing orders keep every column/value they already have.
--   - Safe to re-run; the new columns/table simply won't be created
--     twice.
--   - Fresh installs get the identical structure from schema.sql.
-- ===================================================================

-- -------------------------------------------------------------------
-- 1. orders tracking columns (MySQL < 8.0.29 / some MariaDB versions
--    have no "ADD COLUMN IF NOT EXISTS", so each one is guarded via
--    information_schema, same as the Phase 4D migration).
-- -------------------------------------------------------------------

SET @col_courier = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'courier_partner'
);

SET @ddl = IF(
    @col_courier = 0,
    'ALTER TABLE orders ADD COLUMN courier_partner VARCHAR(100) NULL AFTER notes',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_awb = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'awb_number'
);

SET @ddl = IF(
    @col_awb = 0,
    'ALTER TABLE orders ADD COLUMN awb_number VARCHAR(100) NULL AFTER courier_partner',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_url = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'tracking_url'
);

SET @ddl = IF(
    @col_url = 0,
    'ALTER TABLE orders ADD COLUMN tracking_url VARCHAR(255) NULL AFTER awb_number',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------
-- 2. order_status_history table
--    Timeline events cascade with their order (deleting an order
--    removes its history) and are indexed for fast per-order lookup.
-- -------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS order_status_history (

    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    order_status    VARCHAR(30)  NOT NULL,   -- order_status vocab + 'payment_confirmed' marker
    note            VARCHAR(255) NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_status_history_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,

    INDEX idx_order_status_history_order (order_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill: give every pre-existing order its real "Order Placed"
-- event, sourced from the order's own created_at (not a guess). New
-- orders get this row from create_order() itself, so this only ever
-- touches orders that predate the table. Idempotent - an order with
-- any history is skipped.
INSERT INTO order_status_history (order_id, order_status, created_at)
SELECT o.id, 'pending', o.created_at
FROM orders o
WHERE NOT EXISTS (
    SELECT 1 FROM order_status_history h WHERE h.order_id = o.id
);
