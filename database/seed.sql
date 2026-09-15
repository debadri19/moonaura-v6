-- ===================================================================
-- MOONAURA CRYSTALS - SEED DATA
-- ===================================================================
-- This only seeds reference data (categories + one collection).
--
-- Product rows are NOT inserted here on purpose - the Shop and
-- Product pages are not being made dynamic yet in this phase, so
-- there is no need to invent product data before that work starts.
--
-- Admin user creation is handled separately by admin/setup.php
-- (a one-time setup page), NOT by this file - so you never have a
-- hardcoded password sitting in a SQL file.
--
-- Run this AFTER importing schema.sql.
-- ===================================================================

-- ===================================================================
-- CATEGORIES
-- ===================================================================
-- "image_folder_name" matches the folder names that already exist
-- inside assets/images/products/ (kept exactly as-is, including the
-- inconsistent plural/singular naming, so image paths keep working).

INSERT INTO categories (name, slug, image_folder_name, display_order) VALUES
('Bracelets',        'bracelets',      'bracelets',      1),
('Rings',             'rings',          'ring',           2),
('Pendants',          'pendants',       'pendant',        3),
('Anklets',           'anklets',        'anklet',         4),
('Bangles',           'bangles',        'bangle',         5),
('Crystal Trees',     'crystal-trees',  'crystal-tree',   6),
('Crystal Pyramids',  'pyramids',       'pyramid',        7),
('Malas',             'malas',          'mala',           8),
('Keychains',         'keychains',      'keychain',       9),
('Purses',            'purses',         'purse',          10),
('Rakhis',            'rakhis',         'rakhi',          11),
('Showpieces',        'showpieces',     'showpiece',      12),
('Tumbled Stones',    'tumbled-stones', 'tumbled-stone',  13),
('Watches',           'watches',        'watche',         14),
('Yantras',           'yantras',        'yantra',         15);


-- ===================================================================
-- COLLECTIONS
-- ===================================================================
-- Matches the "Career Success Collection" already shown on the
-- homepage. No products are linked yet (see note above).

INSERT INTO collections (name, slug, display_order) VALUES
('Career Success Collection', 'career-success', 1);


-- ===================================================================
-- CONCERN CATEGORIES  (Homepage/Frontend UX pass)
-- ===================================================================
-- The 13 approved presets, in the given order. Fixed vocabulary - do
-- not silently rename, merge, remove, or replace these values.

INSERT INTO concern_categories (name, slug, display_order) VALUES
('Love & Relationships',   'love-and-relationships',   1),
('Career Success',         'career-success',           2),
('Education & Focus',      'education-and-focus',      3),
('Wealth & Prosperity',    'wealth-and-prosperity',    4),
('Protection',             'protection',               5),
('Confidence & Courage',   'confidence-and-courage',   6),
('Peace & Healing',        'peace-and-healing',        7),
('Spiritual Growth',       'spiritual-growth',         8),
('Evil Eye Protection',    'evil-eye-protection',      9),
('Money & Prosperity',     'money-and-prosperity',     10),
('Peace & Calm',           'peace-and-calm',           11),
('Positive Energy',        'positive-energy',          12),
('Study & Focus',          'study-and-focus',          13);


-- ===================================================================
-- SETTINGS  (Phase 3A, extended Phase 4C for multi-gateway checkout)
-- ===================================================================
-- Multi-gateway model: each online gateway has its own *_enabled
-- flag, so any combination can be live at once - checkout.php shows
-- one radio option per enabled gateway (plus COD if cod_enabled).
-- "default_payment_gateway" only controls which enabled option is
-- pre-selected - it is never trusted blindly: PaymentManager::
-- getDefaultGatewayName() falls back to the first enabled gateway if
-- this value doesn't name a currently-enabled one.
-- "active_payment_gateway" is kept (unused by code now) purely so an
-- older deployment's existing row isn't orphaned - nothing reads it
-- anymore, superseded by the *_enabled flags + default_payment_gateway.
-- "manual_upi_enabled"/"upi_id"/"upi_account_name"/"upi_qr_image_path"
-- (Phase 4D) - Manual UPI QR Payment, managed the same way as COD
-- (checkout.php handles it directly, not through PaymentManager).
-- Off by default until an admin configures real UPI details via
-- Admin > Settings and turns it on.

INSERT INTO settings (setting_key, setting_value) VALUES
('active_payment_gateway', 'razorpay'),
('default_payment_gateway', 'razorpay'),
('razorpay_enabled', '1'),
('cod_enabled', '1'),
('manual_upi_enabled', '0'),
('upi_id', ''),
('upi_account_name', ''),
('upi_qr_image_path', ''),
('business_state', '');


-- ===================================================================
-- INVOICE DESIGNER SETTINGS  (Phase 6)
-- -------------------------------------------------------------------
-- The single settings row. '{}' is deliberately empty -
-- get_invoice_designer_settings() merges it over its own PHP-side
-- defaults, which reproduce today's hardcoded invoice layout exactly.
-- ===================================================================

INSERT INTO invoice_designer_settings (id, config) VALUES (1, '{}');
