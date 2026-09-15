-- ===================================================================
-- MIGRATION: Persistent Customer Cart Foundation (Phase #25A)
-- -------------------------------------------------------------------
-- Adds customer_carts - one row per (customer, product) for a future
-- logged-in cart. This file is INFRASTRUCTURE ONLY:
--   - Existing session cart ($_SESSION['cart']) is unchanged.
--   - Login, logout, AJAX, wishlist and checkout are unchanged.
--   - Helpers in includes/customer-cart-functions.php are unused
--     until #25B / #25C.
--
-- Quantity range matches the existing session cart (1-99).
-- UNIQUE (customer_id, product_id) enforces one line per product.
--
-- Non-destructive and idempotent (CREATE TABLE IF NOT EXISTS),
-- matching this project's migration convention:
--   - Existing customers, products, orders and every other table
--     are untouched.
--   - Safe to re-run; the table simply will not be created twice.
--   - Fresh installs get the identical structure from schema.sql.
--
-- DO NOT run this against production from application code.
-- ===================================================================

CREATE TABLE IF NOT EXISTS customer_carts (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    customer_id         INT UNSIGNED    NOT NULL,
    product_id          INT UNSIGNED    NOT NULL,
    quantity            TINYINT UNSIGNED NOT NULL,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_customer_carts_customer_product
        UNIQUE (customer_id, product_id),

    CONSTRAINT fk_customer_carts_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_customer_carts_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    INDEX idx_customer_carts_customer (customer_id),
    INDEX idx_customer_carts_product (product_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
