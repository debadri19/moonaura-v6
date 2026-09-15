-- ===================================================================
-- MIGRATION: Admin Two-Factor Authentication (Phase 5E)
-- -------------------------------------------------------------------
-- Adds three columns to admin_users backing the admin 2FA flow
-- (admin/2fa-setup.php + admin/2fa-verify.php):
--
--   two_factor_enabled    TINYINT(1) DEFAULT 0
--                         Whether this admin must pass a TOTP code at
--                         login. Set to 1 ONLY after the admin has
--                         verified a real OTP during setup.
--
--   two_factor_secret     VARCHAR(500) NULL
--                         The RFC 6238 TOTP secret, stored ENCRYPTED
--                         (AES-256-GCM, see includes/two-factor.php) -
--                         never in plain text. NULL while 2FA is off;
--                         while 2FA is being set up it holds the
--                         pending (still unverified) secret so the QR
--                         code stays stable across page reloads.
--
--   two_factor_enabled_at DATETIME NULL
--                         When 2FA was turned on (informational).
--
-- SECURITY NOTES:
--   - OTP values are never stored anywhere - codes are computed from
--     the secret at verify time and discarded.
--   - No recovery codes are stored (and none in plain text).
--   - Existing admins are untouched: every row gets two_factor_enabled
--     = 0, so no existing admin is suddenly locked out.
--
-- Non-destructive and idempotent (information_schema-guarded ALTERs,
-- same pattern as the Phase 5/6 migrations for MySQL < 8.0.29 and
-- MariaDB compatibility). Safe to re-run; fresh installs get the
-- identical columns from schema.sql.
-- ===================================================================

SET @col_enabled = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
      AND COLUMN_NAME = 'two_factor_enabled'
);

SET @ddl_enabled = IF(
    @col_enabled = 0,
    'ALTER TABLE admin_users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER last_login_at',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_enabled;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_secret = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
      AND COLUMN_NAME = 'two_factor_secret'
);

SET @ddl_secret = IF(
    @col_secret = 0,
    'ALTER TABLE admin_users ADD COLUMN two_factor_secret VARCHAR(500) NULL AFTER two_factor_enabled',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_secret;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @col_enabled_at = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
      AND COLUMN_NAME = 'two_factor_enabled_at'
);

SET @ddl_enabled_at = IF(
    @col_enabled_at = 0,
    'ALTER TABLE admin_users ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_secret',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_enabled_at;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
