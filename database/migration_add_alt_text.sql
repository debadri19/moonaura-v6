-- ===================================================================
-- MIGRATION: Add alt_text to product_images
-- -------------------------------------------------------------------
-- Only needed if your database was created BEFORE this column was
-- added to schema.sql. If you're setting up the database fresh,
-- just use schema.sql - it already includes this column.
-- ===================================================================

ALTER TABLE product_images
    ADD COLUMN alt_text VARCHAR(255) NULL AFTER file_path;
