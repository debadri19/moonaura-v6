-- ===================================================================
-- MIGRATION: Admin newsletter subscribers cache + campaign drafts
-- -------------------------------------------------------------------
-- Phase 1 / Phase 2 Admin newsletter management. Brevo Contacts remains
-- the source of truth for subscribers. This cache is for Admin search,
-- filters, CSV export, and sync status only. Campaign drafts are local
-- and are never sent by this migration.
-- Non-destructive and idempotent (CREATE TABLE IF NOT EXISTS).
-- ===================================================================

CREATE TABLE IF NOT EXISTS newsletter_subscribers (

    email               VARCHAR(190)    NOT NULL PRIMARY KEY,
    name                VARCHAR(160)    NULL,
    status              ENUM('pending','confirmed','unsubscribed') NOT NULL,
    source              VARCHAR(40)     NOT NULL DEFAULT 'brevo',
    brevo_id            BIGINT UNSIGNED NULL,
    subscribed_at       DATETIME        NULL,
    confirmed_at        DATETIME        NULL,
    unsubscribed_at     DATETIME        NULL,
    last_modified_at    DATETIME        NULL,
    synced_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_newsletter_subscribers_status (status),
    INDEX idx_newsletter_subscribers_name (name),
    INDEX idx_newsletter_subscribers_synced (synced_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS newsletter_campaigns (

    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(160)    NOT NULL,
    subject             VARCHAR(200)    NOT NULL DEFAULT '',
    preview_text        VARCHAR(255)    NULL,
    sender_name         VARCHAR(120)    NOT NULL,
    sender_email        VARCHAR(190)    NOT NULL,
    reply_to            VARCHAR(190)    NULL,
    body_html           LONGTEXT        NOT NULL,
    cta_text            VARCHAR(120)    NULL,
    cta_url             VARCHAR(500)    NULL,
    status              ENUM('draft')   NOT NULL DEFAULT 'draft',
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_newsletter_campaigns_status (status),
    INDEX idx_newsletter_campaigns_updated (updated_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
