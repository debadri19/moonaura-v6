-- ===================================================================
-- MIGRATION: Product Search, Stock Management & Login Security (Phase 6)
-- -------------------------------------------------------------------
-- Adds:
--   1. orders.stock_deducted_at (DATETIME, nullable)
--      - The idempotency marker for the Phase 6 stock lifecycle (see
--        includes/stock-functions.php). NULL  = this order does not
--        currently hold inventory; NOT NULL = stock was deducted and is
--        reserved until the order is cancelled (which restores it).
--        Being NULL for every pre-existing order is correct: existing
--        orders predate stock management and are deliberately NOT
--        retroactively deducted (non-destructive, backward compatible).
--   2. login_attempts table
--      - Brute-force lockout bookkeeping for BOTH admin and customer
--        login (see includes/login-security.php). One row per attempted
--        email address, keeping only the minimal state needed: how many
--        consecutive failed attempts have happened, when, and until
--        when the address is locked.
--
-- Non-destructive and idempotent (guarded ALTER + CREATE TABLE IF NOT
-- EXISTS), matching this project's migration convention:
--   - Existing orders/rows keep every column/value they already have.
--   - Safe to re-run; the column/table simply won't be created twice.
--   - Fresh installs get the identical structure from schema.sql.
-- ===================================================================

-- -------------------------------------------------------------------
-- 1. orders.stock_deducted_at (guarded via information_schema, same
--    pattern as the Phase 5 / Phase 4D migrations for MySQL < 8.0.29
--    and MariaDB compatibility).
-- -------------------------------------------------------------------

SET @col_stock = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'stock_deducted_at'
);

SET @ddl = IF(
    @col_stock = 0,
    'ALTER TABLE orders ADD COLUMN stock_deducted_at DATETIME NULL AFTER tracking_url',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------
-- 2. login_attempts table
--    email is normalized (lowercased/trimmed, same normalize_email()
--    the login functions use) and unique, so a repeated attacker or a
--    legitimate user always updates the SAME row. No foreign keys -
--    the row exists even when the email belongs to no account, which
--    is exactly what keeps the lockout from revealing which emails
--    have accounts.
-- -------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS login_attempts (

    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(191) NOT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_failed_at  DATETIME     NULL,
    locked_until    DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_login_attempts_email (email)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
