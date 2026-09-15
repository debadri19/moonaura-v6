-- ===================================================================
-- MIGRATION: Concern Category taxonomy (Homepage/Frontend UX pass)
-- -------------------------------------------------------------------
-- Adds a dedicated, structured product classification for
-- "Shop By Concern" (homepage cards + /concerns listing pages),
-- deliberately SEPARATE from the existing free-text products.purpose
-- field (product-benefit copy shown on the product detail page,
-- untouched by this migration).
--
-- Modeled directly on the existing collections/collection_products
-- pattern (same shape: a named/slugged parent table + a many-to-many
-- pivot), so the two new tables below are structurally consistent
-- with what's already in this schema:
--
--   concern_categories   the 13 approved presets (fixed vocabulary -
--                         admins assign products to these, they do
--                         not create new ones from the frontend).
--
--   product_concerns     pivot: which products belong to which
--                         concern(s). A product can belong to more
--                         than one concern (e.g. Green Aventurine ->
--                         Career Success + Wealth & Prosperity).
--
-- Non-destructive and idempotent (CREATE TABLE IF NOT EXISTS +
-- INSERT IGNORE), matching this project's migration convention:
--   - Safe to re-run; nothing is created or inserted twice.
--   - Fresh installs get the identical structure + seed rows from
--     schema.sql / seed.sql instead of this file.
-- ===================================================================

CREATE TABLE IF NOT EXISTS concern_categories (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name                VARCHAR(100)    NOT NULL,              -- e.g. "Love & Relationships"
    slug                VARCHAR(100)    NOT NULL UNIQUE,        -- e.g. "love-and-relationships"
    display_order       INT UNSIGNED    NOT NULL DEFAULT 0,
    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_concern_categories_status (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Pivot table: which products are assigned to which concern(s).

CREATE TABLE IF NOT EXISTS product_concerns (

    product_id          INT UNSIGNED    NOT NULL,
    concern_category_id INT UNSIGNED    NOT NULL,

    PRIMARY KEY (product_id, concern_category_id),

    CONSTRAINT fk_product_concerns_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_product_concerns_concern
        FOREIGN KEY (concern_category_id) REFERENCES concern_categories(id)
        ON DELETE CASCADE,

    INDEX idx_product_concerns_concern (concern_category_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- SEED: the 13 approved Concern Category presets, in the given order.
-- Fixed vocabulary - do not silently rename, merge, remove, or
-- replace these values (see PROJECT_STATUS.md).
-- ===================================================================

INSERT IGNORE INTO concern_categories (name, slug, display_order) VALUES
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
