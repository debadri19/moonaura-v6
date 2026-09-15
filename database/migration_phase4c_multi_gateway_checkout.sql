-- ===================================================================
-- MIGRATION: Multi-gateway checkout (Phase 4C)
-- -------------------------------------------------------------------
-- Replaces the single active_payment_gateway model with one
-- *_enabled flag per online gateway (cashfree_enabled,
-- razorpay_enabled, phonepe_enabled) plus a default_payment_gateway
-- setting that only controls which enabled option is pre-selected at
-- checkout - never which options are shown. Multiple gateways can now
-- be enabled at once; checkout.php shows one radio per enabled
-- gateway, plus Cash on Delivery if cod_enabled is on.
--
-- No table structure changes - `settings` is already a generic
-- key-value store, this is new rows only.
--
-- Idempotent (INSERT IGNORE, matching this project's existing
-- migration convention) and derives cashfree_enabled/razorpay_enabled/
-- default_payment_gateway from whatever this database's own
-- active_payment_gateway value currently is, so re-running this on
-- any existing deployment preserves that deployment's current
-- checkout behavior exactly - it does not assume every database is
-- on 'cashfree' (even though that is the current default on a fresh
-- Phase 3A+ install per seed.sql and migration_phase3a_switch_to_cashfree.sql).
--
-- active_payment_gateway itself is left in place, unused by any code
-- after this migration - not deleted, so nothing is destructive here.
--
-- phonepe_enabled is seeded '0' and has no effect either way yet -
-- PhonePe is not a registered gateway in PaymentManager.php's
-- $gatewayClasses (see that file's docblock for what's required
-- before it can be). Flipping this setting to '1' before then does
-- nothing - getEnabledGatewayNames() only returns gateways that are
-- BOTH enabled AND have a registered class.
-- ===================================================================

INSERT IGNORE INTO settings (setting_key, setting_value)
SELECT 'default_payment_gateway', COALESCE(
    (SELECT setting_value FROM settings WHERE setting_key = 'active_payment_gateway'),
    'cashfree'
);

INSERT IGNORE INTO settings (setting_key, setting_value)
SELECT 'cashfree_enabled', IF(
    (SELECT setting_value FROM settings WHERE setting_key = 'active_payment_gateway') = 'razorpay',
    '0', '1'
);

INSERT IGNORE INTO settings (setting_key, setting_value)
SELECT 'razorpay_enabled', IF(
    (SELECT setting_value FROM settings WHERE setting_key = 'active_payment_gateway') = 'razorpay',
    '1', '0'
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('phonepe_enabled', '0');

-- cod_enabled already exists from Phase 3A - not touched here.
