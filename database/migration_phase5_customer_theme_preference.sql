-- ===================================================================
-- MIGRATION: Dark Mode Phase 5 - customer theme preference
-- -------------------------------------------------------------------
-- Adds customers.theme_preference so an authenticated customer can
-- keep Light / Dark / System across browsers and sessions.
--
-- NULL means "no account preference yet":
--   - Existing customers keep working with no data backfill.
--   - Login does not invent a theme from browser state.
--   - Guest localStorage continues to control the theme.
--
-- ENUM matches the existing client modes exactly. Invalid writes are
-- rejected in PHP before they reach this column.
--
-- Non-destructive and idempotent (information_schema-guarded ALTER,
-- same pattern as migration_add_certificate_included.sql). Safe to
-- re-run; fresh installs get the identical column from schema.sql.
--
-- DO NOT run this against production from application code.
-- ===================================================================

SET @col_theme_preference = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'theme_preference'
);

SET @ddl_theme_preference = IF(
    @col_theme_preference = 0,
    'ALTER TABLE customers ADD COLUMN theme_preference ENUM(\'light\',\'dark\',\'system\') NULL DEFAULT NULL AFTER status',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_theme_preference;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
