-- ===================================================================
-- MIGRATION: Customer Forgot / Reset Password (Phase 5B)
-- -------------------------------------------------------------------
-- Adds the customer_password_resets table - the customer-side mirror
-- of admin_password_resets (Phase 2D), backing the new
-- account/forgot-password.php and account/reset-password.php pages.
--
-- The token itself is never stored - only its SHA-256 hash, the same
-- principle as password_hash for actual passwords. The raw token only
-- ever exists in the reset link itself. "used_at" makes every token
-- single-use; "expires_at" makes it time-limited (60 minutes).
--
-- Non-destructive and idempotent (CREATE TABLE IF NOT EXISTS),
-- matching this project's migration convention:
--   - Existing customers, orders and every other table are untouched.
--   - Safe to re-run; the table simply won't be created twice.
--   - Fresh installs get the identical structure from schema.sql.
-- ===================================================================

CREATE TABLE IF NOT EXISTS customer_password_resets (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    customer_id         INT UNSIGNED    NOT NULL,
    token_hash          VARCHAR(255)    NOT NULL,

    expires_at          DATETIME        NOT NULL,
    used_at             DATETIME        NULL,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_customer_password_resets_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE CASCADE,

    INDEX idx_customer_password_resets_customer (customer_id),
    INDEX idx_customer_password_resets_token_hash (token_hash)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
