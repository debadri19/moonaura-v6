-- ===================================================================
-- MIGRATION: Manual UPI QR Payment (Phase 4D)
-- -------------------------------------------------------------------
-- Adds "Manual UPI QR Payment" as a fourth payment method, using the
-- exact same enabled/disabled architecture as Razorpay/COD
-- (Phase 4C). Manual UPI is NOT a "gateway" in PaymentManager's sense
-- - like COD, it has no external API, no createOrder()/verifyPayment()/
-- webhook cycle, so it is not registered in PaymentManager's
-- $gatewayClasses and is never a candidate for default_payment_gateway
-- (same as COD already wasn't). checkout.php manages it directly,
-- exactly the way it already manages COD.
--
-- New settings (generic key-value rows, no schema change to `settings`
-- itself):
--   manual_upi_enabled  - '0' by default (off until an admin
--                          configures real UPI details and turns it on)
--   upi_id              - the UPI ID shown to customers, e.g. 'name@bank'
--   upi_account_name    - the account/business name shown alongside it
--   upi_qr_image_path   - relative path to the uploaded QR image,
--                          set via Admin > Settings; empty until an
--                          admin uploads one
--
-- New column on `payment_transactions` (already the correct place for
-- this - see payment-functions.php's docblock: "one row per attempt"):
--   screenshot_path     - optional customer-uploaded proof-of-payment
--                          image, nullable, populated by
--                          manual-upi-payment.php's submission form.
--                          gateway_payment_id on that same row stores
--                          the UTR/reference number the customer
--                          entered - no new column needed for that,
--                          it is exactly what that column is already
--                          for on the other gateways (their payment ID).
--
-- Idempotent (INSERT IGNORE / conditional ALTER), matching this
-- project's existing migration convention.
-- ===================================================================

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('manual_upi_enabled', '0'),
('upi_id', ''),
('upi_account_name', ''),
('upi_qr_image_path', '');

-- Conditional ALTER (MySQL has no "ADD COLUMN IF NOT EXISTS" before
-- 8.0.29 / MariaDB equivalents, so this guards it manually - safe to
-- re-run this migration on a database that already has the column).
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'screenshot_path'
);

SET @ddl = IF(
    @col_exists = 0,
    'ALTER TABLE payment_transactions ADD COLUMN screenshot_path VARCHAR(255) NULL AFTER raw_response',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
