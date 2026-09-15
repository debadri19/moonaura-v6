-- ===================================================================
-- MIGRATION: Add orders, order_items, order_addresses (Phase 2A)
-- -------------------------------------------------------------------
-- Only needed if your database was created BEFORE these tables were
-- added to schema.sql. If you're setting up the database fresh,
-- just use schema.sql - it already includes them.
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 05. ORDERS  (Phase 2A - commerce foundation, no checkout UI yet)
-- ===================================================================
-- Every order is fully self-contained (customer name/email/phone are
-- stored directly here) so GUEST CHECKOUT works with no account
-- system at all. "user_id" is left nullable with NO foreign key yet -
-- there is no users/customers table to reference (out of scope for
-- this phase). When that table is built later, a guest order gets
-- linked to a real account with a single UPDATE on user_id, matched
-- by customer_email or customer_phone - nothing else on the order
-- changes, so order history is never altered by the linking step.
--
-- INVOICE STRATEGY: invoice_number and invoice_generated_at are both
-- nullable. There is deliberately no "invoice file path" column -
-- invoices are meant to be rendered on demand from this order's own
-- data in a future phase, not generated once and stored as a file.

CREATE TABLE orders (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Human-readable identifiers
    order_number        VARCHAR(20)     NOT NULL UNIQUE,         -- e.g. "MOA-2026-000001"
    invoice_number       VARCHAR(20)     NULL UNIQUE,            -- e.g. "INV-2026-000001" - set once an invoice is generated
    invoice_generated_at DATETIME        NULL,

    -- Customer details (captured directly - NOT a reference to an
    -- accounts table, so this works for guests today and members later)
    customer_name       VARCHAR(150)    NOT NULL,
    customer_email      VARCHAR(150)    NOT NULL,
    customer_phone      VARCHAR(20)     NOT NULL,

    -- Nullable link to a future registered account. No FK constraint
    -- yet - see note above.
    user_id             INT UNSIGNED    NULL,

    -- Money
    currency            CHAR(3)         NOT NULL DEFAULT 'INR',
    gst_rate            DECIMAL(5,2)    NOT NULL DEFAULT 0.00,   -- order-level rate (line items may carry their own too)
    subtotal            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,   -- sum of order_items.line_total, before discount/shipping/gst
    discount            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    shipping_charge     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    gst_amount          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    grand_total         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,   -- subtotal - discount + shipping_charge + gst_amount

    -- Status
    payment_status      ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    order_status        ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    payment_method       VARCHAR(50)     NULL,                   -- e.g. "razorpay", "cod" - set once a payment phase exists

    notes               TEXT            NULL,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    -- Indexed for the future guest-to-account linking described above,
    -- and for looking up "my orders" by email/phone before accounts exist.
    INDEX idx_orders_customer_email (customer_email),
    INDEX idx_orders_customer_phone (customer_phone),
    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_status (order_status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- 06. ORDER ITEMS
-- ===================================================================
-- Stores a full SNAPSHOT of the product at the time of purchase, so
-- editing or even deleting a product later never changes what a past
-- order shows. product_id is kept as a soft reference only (nullable,
-- ON DELETE SET NULL) - useful while the product still exists, never
-- required for the order to display correctly.

CREATE TABLE order_items (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id            INT UNSIGNED    NOT NULL,
    product_id          INT UNSIGNED    NULL,                    -- soft reference only - see note above

    -- Snapshot of the product exactly as it was when ordered
    product_name        VARCHAR(150)    NOT NULL,
    product_slug        VARCHAR(150)    NOT NULL,
    unit_price          DECIMAL(10,2)   NOT NULL,
    quantity            INT UNSIGNED    NOT NULL DEFAULT 1,
    gst_rate            DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    line_total          DECIMAL(10,2)   NOT NULL,                -- unit_price * quantity

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE SET NULL,

    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_product (product_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================================
-- 07. ORDER ADDRESSES
-- ===================================================================
-- One shipping address per order (UNIQUE on order_id). Kept as its
-- own table rather than columns on "orders" so it's easy to extend
-- later (e.g. a future billing address) without reshaping the orders
-- table itself.

CREATE TABLE order_addresses (

    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id            INT UNSIGNED    NOT NULL UNIQUE,

    full_name           VARCHAR(150)    NOT NULL,
    phone               VARCHAR(20)     NOT NULL,

    address_line1       VARCHAR(255)    NOT NULL,
    address_line2       VARCHAR(255)    NULL,
    city                VARCHAR(100)    NOT NULL,
    state               VARCHAR(100)    NOT NULL,
    postal_code         VARCHAR(12)     NOT NULL,
    country             VARCHAR(100)    NOT NULL DEFAULT 'India',

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_addresses_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



SET FOREIGN_KEY_CHECKS = 1;
