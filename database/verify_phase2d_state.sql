-- ===================================================================
-- VERIFY: Phase 2D accounts migration state
-- -------------------------------------------------------------------
-- READ-ONLY. Makes no changes. Run this first (or just look at the
-- output of migration_add_phase2d_accounts_repair.sql, which prints
-- the same checks before it does anything).
--
-- Answers exactly the 4 questions:
--   1. Does `customers` exist?
--   2. Does `customer_addresses` exist?
--   3. Does `admin_password_resets` exist?
--   4. Does the `orders.user_id -> customers(id)` FK
--      (fk_orders_customer) exist?
-- ===================================================================

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers')
        AS customers_table_exists,

    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_addresses')
        AS customer_addresses_table_exists,

    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_password_resets')
        AS admin_password_resets_table_exists,

    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND CONSTRAINT_NAME = 'fk_orders_customer')
        AS orders_customer_fk_exists;

-- If `customers` exists, also check its columns match what
-- migration_add_phase2d_accounts.sql expects (id, name, email, phone,
-- password_hash, status, created_at, updated_at). A mismatch here
-- would mean the table was created by hand/differently, and the
-- repair script's "skip if exists" logic would NOT fix that - it
-- would need a manual look before proceeding.
-- Safe even if `customers` doesn't exist yet: INFORMATION_SCHEMA.COLUMNS
-- simply returns zero rows in that case, it never errors - unlike a
-- direct "FROM customers" query would.
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
ORDER BY ORDINAL_POSITION;

-- How many existing orders have a non-NULL user_id that does NOT
-- correspond to a row in `customers`. If this is anything other than
-- 0, the FK ALTER TABLE step in the repair migration will fail
-- (correctly) rather than corrupt data - worth knowing in advance.
--
-- This can't be a plain "FROM orders LEFT JOIN customers" query,
-- because MySQL parses/validates a statement (including that FROM
-- clause) before running it - so referencing `customers` directly
-- errors immediately if the table doesn't exist yet, even though the
-- row count would otherwise just be informational. Built and run
-- conditionally instead: if `customers` doesn't exist, we already
-- know the FK step can't apply, so we report that instead of running
-- a query against a table that isn't there.

SET @customers_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
);

SET @orphan_check_sql := IF(
    @customers_exists = 1,
    'SELECT COUNT(*) AS orphaned_order_user_ids
     FROM orders o
     LEFT JOIN customers c ON c.id = o.user_id
     WHERE o.user_id IS NOT NULL AND c.id IS NULL',
    'SELECT "customers table does not exist yet - orphan check skipped, N/A" AS orphan_check_result'
);

PREPARE stmt FROM @orphan_check_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
