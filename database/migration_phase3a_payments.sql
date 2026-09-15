-- ===================================================================
-- MIGRATION: Phase 3A - settings + payment_transactions
-- -------------------------------------------------------------------
-- Only needed if your database was created BEFORE these were added
-- to schema.sql/seed.sql. If you're setting up the database fresh,
-- just use schema.sql + seed.sql - they already include both.
-- ===================================================================

CREATE TABLE IF NOT EXISTS settings (

    setting_key         VARCHAR(100)    PRIMARY KEY,
    setting_value       VARCHAR(255)    NULL,

    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_transactions (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id            INT UNSIGNED    NOT NULL,
    gateway             VARCHAR(50)     NOT NULL,

    gateway_order_id    VARCHAR(100)    NULL,
    gateway_payment_id  VARCHAR(100)    NULL,
    gateway_signature   VARCHAR(255)    NULL,

    status              VARCHAR(30)     NOT NULL DEFAULT 'created',
    raw_response        TEXT            NULL,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_payment_transactions_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,

    INDEX idx_payment_transactions_order (order_id),
    INDEX idx_payment_transactions_gateway_order_id (gateway_order_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed values - INSERT IGNORE so re-running this migration never
-- overwrites a value you've already changed.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('active_payment_gateway', 'razorpay'),
('cod_enabled', '1');
