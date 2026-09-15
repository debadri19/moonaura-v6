-- ===================================================================
-- REPAIR MIGRATION: Phase 2D accounts (idempotent)
-- -------------------------------------------------------------------
-- Use this INSTEAD OF migration_add_phase2d_accounts.sql if you are
-- not sure whether that migration was already applied (fully or
-- partially) to this database. It checks INFORMATION_SCHEMA before
-- creating or altering anything, so it is safe to run:
--   - on a database that has none of Phase 2D yet
--   - on a database that has SOME of Phase 2D (e.g. `customers` was
--     created but `customer_addresses`/the FK weren't)
--   - on a database that already has ALL of Phase 2D
-- In every case it only creates what's missing and never touches
-- existing tables/rows. This is a one-off repair tool for exactly
-- this situation - migration_add_phase2d_accounts.sql remains the
-- canonical record of what Phase 2D introduced, for anyone setting
-- up a database that predates it cleanly.
--
-- Recommended: run database/verify_phase2d_state.sql first so you
-- know what to expect this script to do before you run it.
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;


-- ===================================================================
-- STEP 0: report current state (same checks as verify_phase2d_state.sql)
-- ===================================================================

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers')
        AS customers_before,
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_addresses')
        AS customer_addresses_before,
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_password_resets')
        AS admin_password_resets_before,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders' AND CONSTRAINT_NAME = 'fk_orders_customer')
        AS orders_fk_before;


-- ===================================================================
-- STEP 1: customers
-- IF NOT EXISTS is safe here - if this table already exists we skip
-- it untouched (existing rows preserved). It does NOT verify the
-- existing table's columns match; if `customers` already exists with
-- a different shape than expected, this step silently does nothing -
-- see verify_phase2d_state.sql's column check for that case.
-- ===================================================================

CREATE TABLE IF NOT EXISTS customers (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name                VARCHAR(150)    NOT NULL,
    email               VARCHAR(150)    NOT NULL UNIQUE,
    phone               VARCHAR(20)     NOT NULL UNIQUE,
    password_hash       VARCHAR(255)    NOT NULL,

    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- STEP 2: customer_addresses
-- ===================================================================

CREATE TABLE IF NOT EXISTS customer_addresses (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    customer_id         INT UNSIGNED    NOT NULL,

    full_name           VARCHAR(150)    NOT NULL,
    phone               VARCHAR(20)     NOT NULL,

    address_line1       VARCHAR(255)    NOT NULL,
    address_line2       VARCHAR(255)    NULL,
    landmark            VARCHAR(255)    NULL,
    city                VARCHAR(100)    NOT NULL,
    state               VARCHAR(100)    NOT NULL,
    postal_code         VARCHAR(12)     NOT NULL,
    country             VARCHAR(100)    NOT NULL DEFAULT 'India',

    is_default          TINYINT(1)      NOT NULL DEFAULT 0,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_customer_addresses_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE CASCADE,

    INDEX idx_customer_addresses_customer (customer_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- STEP 3: admin_password_resets
-- ===================================================================

CREATE TABLE IF NOT EXISTS admin_password_resets (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    admin_id            INT UNSIGNED    NOT NULL,
    token_hash          VARCHAR(255)    NOT NULL,

    expires_at          DATETIME        NOT NULL,
    used_at             DATETIME        NULL,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_admin_password_resets_admin
        FOREIGN KEY (admin_id) REFERENCES admin_users(id)
        ON DELETE CASCADE,

    INDEX idx_admin_password_resets_admin (admin_id),
    INDEX idx_admin_password_resets_token_hash (token_hash)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- STEP 4: orders.user_id -> customers(id) FK
-- Plain "ADD CONSTRAINT" has no IF NOT EXISTS in MySQL, so this is
-- done via a conditional dynamic statement: only builds and runs the
-- ALTER TABLE if fk_orders_customer doesn't already exist, otherwise
-- it's a no-op SELECT. This is the step that will loudly FAIL (by
-- design, not a bug in this script) if any existing orders.user_id
-- values don't correspond to a real customers.id - run
-- verify_phase2d_state.sql's orphan check first to know in advance.
-- ===================================================================

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND CONSTRAINT_NAME = 'fk_orders_customer'
);

SET @add_fk_sql := IF(
    @fk_exists = 0,
    'ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (user_id) REFERENCES customers(id) ON DELETE SET NULL',
    'SELECT "fk_orders_customer already exists - skipped, no changes made" AS step_4_result'
);

PREPARE stmt FROM @add_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET FOREIGN_KEY_CHECKS = 1;


-- ===================================================================
-- STEP 5: report final state (compare against STEP 0's output)
-- ===================================================================

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers')
        AS customers_after,
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_addresses')
        AS customer_addresses_after,
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_password_resets')
        AS admin_password_resets_after,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders' AND CONSTRAINT_NAME = 'fk_orders_customer')
        AS orders_fk_after;
