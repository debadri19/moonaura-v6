-- ===================================================================
-- MIGRATION: Switch active payment gateway to Cashfree
-- -------------------------------------------------------------------
-- Razorpay's code (RazorpayGateway.php, webhook-razorpay.php) stays
-- in the project fully working and re-enable-able - this migration
-- only changes which gateway PaymentManager currently reads as
-- active. To go back to Razorpay later, run:
--   UPDATE settings SET setting_value = 'razorpay' WHERE setting_key = 'active_payment_gateway';
-- (or use the future Admin Settings UI once it exists) - no code
-- changes needed either way.
--
-- If you're setting up the database fresh, just use schema.sql +
-- seed.sql - seed.sql already seeds 'cashfree' as the default.
-- ===================================================================

UPDATE settings
SET setting_value = 'cashfree'
WHERE setting_key = 'active_payment_gateway';

-- Safe even if the row somehow doesn't exist yet (e.g. Phase 3A's
-- migration was never run on this database) - creates it instead.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('active_payment_gateway', 'cashfree');
