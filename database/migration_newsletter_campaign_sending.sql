-- ===================================================================
-- MIGRATION: Newsletter campaign sending (Admin Phase 3)
-- -------------------------------------------------------------------
-- Adds send/schedule fields to newsletter_campaigns and a send-history
-- table. Does not change customers, orders, or storefront newsletter
-- signup tables. Apply after database/migration_newsletter_admin.sql.
-- Re-running ALTER statements may error if columns already exist.
-- ===================================================================

ALTER TABLE newsletter_campaigns
    MODIFY status ENUM('draft','scheduled','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft';

ALTER TABLE newsletter_campaigns
    ADD COLUMN scheduled_at DATETIME NULL AFTER status,
    ADD COLUMN started_at DATETIME NULL AFTER scheduled_at,
    ADD COLUMN completed_at DATETIME NULL AFTER started_at,
    ADD COLUMN recipient_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER completed_at,
    ADD COLUMN sent_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER recipient_count,
    ADD COLUMN failed_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER sent_count,
    ADD COLUMN failure_reason VARCHAR(255) NULL AFTER failed_count,
    ADD COLUMN locked_at DATETIME NULL AFTER failure_reason;

ALTER TABLE newsletter_campaigns
    ADD INDEX idx_newsletter_campaigns_scheduled (status, scheduled_at);


CREATE TABLE IF NOT EXISTS newsletter_campaign_sends (

    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campaign_id         INT UNSIGNED    NOT NULL,
    campaign_name       VARCHAR(160)    NOT NULL,
    status              ENUM('sending','sent','failed','cancelled') NOT NULL,
    is_test             TINYINT(1)      NOT NULL DEFAULT 0,
    recipient_count     INT UNSIGNED    NOT NULL DEFAULT 0,
    sent_count          INT UNSIGNED    NOT NULL DEFAULT 0,
    failed_count        INT UNSIGNED    NOT NULL DEFAULT 0,
    scheduled_at        DATETIME        NULL,
    started_at          DATETIME        NULL,
    completed_at        DATETIME        NULL,
    failure_reason      VARCHAR(255)    NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_newsletter_sends_campaign (campaign_id),
    INDEX idx_newsletter_sends_created (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS newsletter_campaign_recipients (

    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campaign_id         INT UNSIGNED    NOT NULL,
    email               VARCHAR(190)    NOT NULL,
    name                VARCHAR(160)    NULL,
    status              ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    error_message       VARCHAR(255)    NULL,
    sent_at             DATETIME        NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_newsletter_campaign_recipient (campaign_id, email),
    INDEX idx_newsletter_recipients_status (campaign_id, status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
