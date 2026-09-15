-- ===================================================================
-- REPAIR MIGRATION: order_addresses.landmark column (idempotent)
-- -------------------------------------------------------------------
-- Use this if you are not sure whether the `landmark` column was ever
-- added to `order_addresses` on this database.
--
-- ROOT CAUSE: `order_addresses` was first created by
-- migration_add_orders.sql (Phase 2A) WITHOUT a `landmark` column.
-- The column was added later, in Phase 2C, by
-- migration_add_order_number_sequences_and_landmark.sql:
--     ALTER TABLE order_addresses ADD COLUMN landmark VARCHAR(255) NULL AFTER address_line2;
-- schema.sql has included `landmark` in `order_addresses` ever since
-- (see its "07. ORDER ADDRESSES" section), and
-- includes/order-functions.php's create_order() has written to it
-- ever since (it's the only INSERT/UPDATE anywhere in the codebase
-- that touches `order_addresses` - confirmed by searching every
-- `order_addresses` reference project-wide; the only other places
-- that touch the table, account/order-detail.php and
-- order-success.php, both do `SELECT *`, which doesn't reference the
-- column by name and isn't affected either way).
--
-- THE BUG: migration_add_order_number_sequences_and_landmark.sql's
-- `ALTER TABLE ... ADD COLUMN` has no idempotency guard (MySQL has no
-- `ADD COLUMN IF NOT EXISTS` in the versions this project targets).
-- A database that had `order_addresses` created by
-- migration_add_orders.sql, but never had
-- migration_add_order_number_sequences_and_landmark.sql applied to it
-- afterwards, is left with `order_addresses` but no `landmark` column
-- on it - so every `INSERT INTO order_addresses (..., landmark, ...)`
-- in create_order() fails with
-- SQLSTATE[42S22]: Column not found: 1054 Unknown column 'landmark'.
-- This is the same class of schema-drift gap as the `daily_sequences`
-- issue fixed by migration_phase3_daily_sequences_repair.sql - a
-- migration that assumed a linear apply-every-migration-in-order
-- history, on a database where that didn't actually happen.
--
-- This script is safe to run:
--   - on a database where `order_addresses` has no `landmark` column
--     yet (adds it)
--   - on a database that already has `landmark` on `order_addresses`,
--     whether from schema.sql or from
--     migration_add_order_number_sequences_and_landmark.sql (no-op,
--     column left untouched - existing values are never altered)
--   - on a database where `order_addresses` itself doesn't exist yet
--     (no-op - see STEP 1; run migration_add_orders.sql or
--     schema.sql first in that case, this script only repairs the
--     `landmark` column on a table that already exists)
-- In every case it only adds what's missing and never drops or alters
-- any existing column, row, or table.
--
-- If you're setting up the database fresh, just use schema.sql - it
-- already includes `landmark` and you don't need this script.
-- ===================================================================


-- ===================================================================
-- STEP 0: report current state (before)
-- ===================================================================

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_addresses')
        AS order_addresses_table_exists_before,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_addresses'
          AND COLUMN_NAME = 'landmark')
        AS landmark_column_exists_before;


-- ===================================================================
-- STEP 1: order_addresses.landmark
-- Plain "ADD COLUMN" has no IF NOT EXISTS in the MySQL versions this
-- project targets, so this is done via a conditional dynamic
-- statement (same technique as the fk_orders_customer step in
-- migration_add_phase2d_accounts_repair.sql): only builds and runs
-- the ALTER TABLE if `order_addresses` exists AND `landmark` doesn't
-- already exist on it; otherwise it's a no-op SELECT that reports
-- why nothing happened.
-- ===================================================================

SET @order_addresses_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_addresses'
);

SET @landmark_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_addresses'
      AND COLUMN_NAME = 'landmark'
);

SET @add_landmark_sql := (
    CASE
        WHEN @order_addresses_exists = 0
            THEN 'SELECT "order_addresses table does not exist - nothing to repair, run migration_add_orders.sql or schema.sql first" AS step_1_result'
        WHEN @landmark_exists > 0
            THEN 'SELECT "landmark column already exists on order_addresses - skipped, no changes made" AS step_1_result'
        ELSE 'ALTER TABLE order_addresses ADD COLUMN landmark VARCHAR(255) NULL AFTER address_line2'
    END
);

PREPARE stmt FROM @add_landmark_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ===================================================================
-- STEP 2: report final state (after) - compare against STEP 0's output
-- ===================================================================

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_addresses')
        AS order_addresses_table_exists_after,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_addresses'
          AND COLUMN_NAME = 'landmark')
        AS landmark_column_exists_after;

-- Full column shape check - confirms `order_addresses` (whatever its
-- starting state) now matches what create_order() in
-- includes/order-functions.php expects to write to: id, order_id,
-- full_name, phone, address_line1, address_line2, landmark, city,
-- state, postal_code, country, created_at. Safe even if the table
-- doesn't exist: returns zero rows rather than erroring, unlike a
-- direct "FROM order_addresses" query would.
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_addresses'
ORDER BY ORDINAL_POSITION;
