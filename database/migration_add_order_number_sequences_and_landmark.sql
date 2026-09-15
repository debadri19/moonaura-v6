-- ===================================================================
-- MIGRATION: Phase 2C - landmark column + order_number_sequences
-- -------------------------------------------------------------------
-- Only needed if your database was created BEFORE these were added
-- to schema.sql. If you're setting up the database fresh, just use
-- schema.sql - it already includes both.
-- ===================================================================

ALTER TABLE order_addresses
    ADD COLUMN landmark VARCHAR(255) NULL AFTER address_line2;

CREATE TABLE order_number_sequences (

    year                INT UNSIGNED    PRIMARY KEY,
    next_number         INT UNSIGNED    NOT NULL DEFAULT 1

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
