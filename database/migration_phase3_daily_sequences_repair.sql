-- ===================================================================
-- REPAIR MIGRATION: Phase 3 daily_sequences (idempotent)
-- -------------------------------------------------------------------
-- Use this if you are not sure whether `daily_sequences` was ever
-- created on this database. schema.sql has included it since Phase 3
-- (see migration_phase3_daily_sequences.sql), but a database that was
-- provisioned before that change and never had that migration applied
-- to it will be missing the table entirely - every call to
-- generate_order_number()/generate_invoice_number() (both in
-- includes/order-functions.php) then fails with
-- SQLSTATE[42S02] "Base table or table not found", which create_order()
-- catches, rolls back, and rethrows - surfacing to the customer as
-- checkout's generic "Something went wrong while placing your order."
--
-- This script is safe to run:
--   - on a database that doesn't have `daily_sequences` yet (creates it)
--   - on a database that already has it, from schema.sql or from
--     migration_phase3_daily_sequences.sql (no-op, table left untouched
--     - existing rows/counters are never altered)
-- In both cases it only creates what's missing and never drops, alters,
-- or truncates an existing table. Unlike
-- migration_phase3_daily_sequences.sql, this script does NOT drop the
-- legacy `order_number_sequences` table - it only repairs the one thing
-- this script is scoped to (`daily_sequences`); reports its presence
-- below for visibility only. If you're also migrating away from
-- `order_number_sequences`, use migration_phase3_daily_sequences.sql
-- for that part.
--
-- If you're setting up the database fresh, just use schema.sql - it
-- already includes daily_sequences and you don't need this script.
-- ===================================================================


-- ===================================================================
-- STEP 0: report current state (before)
-- ===================================================================

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_sequences')
        AS daily_sequences_before,
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_number_sequences')
        AS legacy_order_number_sequences_present_before;


-- ===================================================================
-- STEP 1: daily_sequences
-- IF NOT EXISTS is safe here - if this table already exists we skip
-- it untouched (existing counters/rows preserved). It does NOT verify
-- the existing table's columns match; if `daily_sequences` already
-- exists with a different shape than expected, this step silently
-- does nothing - compare the column check in STEP 2 below against
-- schema.sql's "08. DAILY SEQUENCES" definition if that's a concern.
-- ===================================================================

CREATE TABLE IF NOT EXISTS daily_sequences (

    sequence_type       VARCHAR(20)     NOT NULL,   -- 'order' or 'invoice'
    sequence_date       DATE            NOT NULL,
    next_number         INT UNSIGNED    NOT NULL DEFAULT 1,

    PRIMARY KEY (sequence_type, sequence_date)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- STEP 2: report final state (after) - compare against STEP 0's output
-- ===================================================================

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_sequences')
        AS daily_sequences_after,
    (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_number_sequences')
        AS legacy_order_number_sequences_present_after;

-- Column shape check - confirms the table (whether just-created or
-- pre-existing) matches what generate_daily_sequence_number() in
-- includes/order-functions.php expects: sequence_type, sequence_date,
-- next_number, with (sequence_type, sequence_date) as the primary key.
-- Safe even if the table somehow still doesn't exist: this simply
-- returns zero rows rather than erroring, unlike a direct
-- "FROM daily_sequences" query would.
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_sequences'
ORDER BY ORDINAL_POSITION;
