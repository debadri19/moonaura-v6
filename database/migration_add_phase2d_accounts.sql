-- ===================================================================
-- MIGRATION: Phase 2D - customer accounts + admin password resets
-- -------------------------------------------------------------------
-- Only needed if your database was created BEFORE these were added
-- to schema.sql. If you're setting up the database fresh, just use
-- schema.sql - it already includes all of this.
--
-- IMPORTANT: this also adds the orders.user_id -> customers(id) FK
-- that Phase 2A always intended to add once customers existed. Run
-- the CREATE TABLE customers statement below FIRST (already handled
-- by this file's order), then the ALTER TABLE at the bottom.
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- 09. CUSTOMERS  (Phase 2D - customer accounts)
-- ===================================================================

CREATE TABLE customers (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name                VARCHAR(150)    NOT NULL,
    email               VARCHAR(150)    NOT NULL UNIQUE,
    phone               VARCHAR(20)     NOT NULL UNIQUE,
    password_hash       VARCHAR(255)    NOT NULL,               -- created with PHP password_hash()

    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- 10. CUSTOMER ADDRESSES  (saved addresses - separate from
--     order_addresses, which are per-order historical snapshots)
-- ===================================================================

CREATE TABLE customer_addresses (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    customer_id         INT UNSIGNED    NOT NULL,

    full_name           VARCHAR(150)    NOT NULL,
    phone               VARCHAR(20)     NOT NULL,

    address_line1       VARCHAR(255)    NOT NULL,
    address_line2       VARCHAR(255)    NULL,
    landmark            VARCHAR(255)    NULL,
    city                VARCHAR(100)    NOT NULL,
    state               VARCHAR(100)    NOT NULL,
    postal_code         VARCHAR(12)     NOT NULL,
    country             VARCHAR(100)    NOT NULL DEFAULT 'India',

    is_default          TINYINT(1)      NOT NULL DEFAULT 0,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_customer_addresses_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE CASCADE,

    INDEX idx_customer_addresses_customer (customer_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- 11. ADMIN PASSWORD RESETS  (Phase 2D - admin "Forgot Password")
-- ===================================================================
-- The token itself is never stored - only its SHA-256 hash, the same
-- principle as password_hash for actual passwords. The raw token only
-- ever exists in the reset link itself. "used_at" makes every token
-- single-use; "expires_at" makes it time-limited.

CREATE TABLE admin_password_resets (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    admin_id            INT UNSIGNED    NOT NULL,
    token_hash          VARCHAR(255)    NOT NULL,

    expires_at          DATETIME        NOT NULL,
    used_at             DATETIME        NULL,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_admin_password_resets_admin
        FOREIGN KEY (admin_id) REFERENCES admin_users(id)
        ON DELETE CASCADE,

    INDEX idx_admin_password_resets_admin (admin_id),
    INDEX idx_admin_password_resets_token_hash (token_hash)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Finally, add the FK that orders.user_id was always meant to get
-- once a customers table existed (see schema.sql's orders table
-- comments from Phase 2A/2C).
ALTER TABLE orders
    ADD CONSTRAINT fk_orders_customer
    FOREIGN KEY (user_id) REFERENCES customers(id)
    ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
