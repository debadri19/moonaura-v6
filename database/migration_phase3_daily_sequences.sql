-- ===================================================================
-- MIGRATION: Phase 3 - daily_sequences (replaces order_number_sequences)
-- -------------------------------------------------------------------
-- Run this if your database still has the OLD "order_number_sequences"
-- table from Phase 2C. It replaces year-based order numbering
-- (MOA-YYYY-NNNNNN) with the new daily-reset format used from Phase 3
-- onward:
--   Order numbers:   MOAOD<YYYYMMDD><4-digit sequence>
--   Invoice numbers: MOAINV<YYYYMMDD><4-digit sequence>
--
-- If you're setting up the database fresh, just use schema.sql - it
-- already includes daily_sequences and not the old table.
--
-- NOTE: this does NOT rewrite existing orders.order_number values -
-- old orders keep their original MOA-YYYY-NNNNNN numbers exactly as
-- they were (no historical data is altered). Only NEW orders created
-- after this migration get the new format.
-- ===================================================================

CREATE TABLE IF NOT EXISTS daily_sequences (

    sequence_type       VARCHAR(20)     NOT NULL,
    sequence_date       DATE            NOT NULL,
    next_number         INT UNSIGNED    NOT NULL DEFAULT 1,

    PRIMARY KEY (sequence_type, sequence_date)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS order_number_sequences;
