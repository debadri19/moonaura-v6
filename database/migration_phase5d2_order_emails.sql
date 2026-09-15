-- ===================================================================
-- MIGRATION: Order Email Notifications (Phase 5D Step 2)
-- -------------------------------------------------------------------
-- Adds:
--   1. order_email_log table - one row per transactional order email
--      ATTEMPT (order confirmation / shipped / delivered), recording
--      the outcome so that:
--        - a given email is only ever SENT once per order per type
--          (the dedup guard for repeated webhooks / re-submitted
--          status forms / retried callbacks);
--        - failures are visible in the database for ops/admin to
--          see exactly which emails were attempted, to whom, and
--          what went wrong.
--
--      Dedup semantics (implemented in includes/order-emails.php):
--        - only rows with status = 'sent' count as "already sent";
--        - a 'failed' attempt does NOT block a later retry (so a
--          transient SMTP outage can recover on the next trigger),
--          but a successful send is never repeated.
--
-- Non-destructive and idempotent (CREATE TABLE IF NOT EXISTS),
-- matching this project's migration convention. Fresh installs get
-- the identical table from schema.sql.
-- ===================================================================

CREATE TABLE IF NOT EXISTS order_email_log (

    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id        INT UNSIGNED NOT NULL,
    email_type      VARCHAR(40)  NOT NULL,   -- 'order_confirmation' | 'order_shipped' | 'order_delivered'
    recipient       VARCHAR(190) NOT NULL,   -- the customer email the attempt targeted
    subject         VARCHAR(190) NOT NULL,   -- the subject line actually sent

    -- 'sent' = accepted by the mailer / SMTP; 'failed' = the attempt
    -- errored (invalid recipient, SMTP unconfigured, transport error).
    -- Only 'sent' rows count towards the dedup guard.
    status          VARCHAR(20)  NOT NULL,
    error_message   TEXT         NULL,       -- truncated failure detail, never credentials

    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_email_log_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,

    INDEX idx_order_email_log_order (order_id),
    INDEX idx_order_email_log_order_type (order_id, email_type)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
