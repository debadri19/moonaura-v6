-- ===================================================================
-- MIGRATION: Admin 2FA Recovery Codes + Security Log (Phase 5F.1)
-- -------------------------------------------------------------------
-- Additive enhancement to the Phase 5E admin TOTP 2FA system.
-- Existing tables (including admin_users and its 2FA columns) are
-- NOT modified - two brand-new tables are added:
--
--   admin_recovery_codes  one-time backup codes for admin login.
--                         Only SHA-256 hashes are stored - never the
--                         plaintext. Codes are grouped in batches:
--                         the most recently generated batch is the
--                         active one, so regenerating invalidates all
--                         earlier batches without deleting rows.
--                         "consumed_at" + an atomic
--                         UPDATE ... WHERE consumed_at IS NULL make
--                         every code usable exactly once.
--
--   admin_security_log    append-only audit trail for recovery-code
--                         events (generation, regeneration, download,
--                         use, reuse attempt, invalid attempt) plus
--                         IP address. Never stores code values.
--
-- Non-destructive and idempotent (CREATE TABLE IF NOT EXISTS),
-- matching this project's migration convention:
--   - Safe to re-run; the tables simply won't be created twice.
--   - Fresh installs get the identical structure from schema.sql.
-- ===================================================================

CREATE TABLE IF NOT EXISTS admin_recovery_codes (

    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    admin_id        INT UNSIGNED    NOT NULL,
    batch_id        VARCHAR(32)     NOT NULL,
    code_hash       CHAR(64)        NOT NULL,

    consumed_at     DATETIME        NULL,
    consumed_by_ip  VARCHAR(45)     NULL,

    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_admin_recovery_codes_admin
        FOREIGN KEY (admin_id) REFERENCES admin_users(id)
        ON DELETE CASCADE,

    INDEX idx_admin_recovery_codes_admin (admin_id),
    INDEX idx_admin_recovery_codes_batch (admin_id, batch_id),
    INDEX idx_admin_recovery_codes_hash (code_hash)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS admin_security_log (

    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    admin_id        INT UNSIGNED    NULL,
    event_type      VARCHAR(50)     NOT NULL,
    detail          VARCHAR(255)    NOT NULL DEFAULT '',
    ip_address      VARCHAR(45)     NULL,

    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_admin_security_log_admin
        FOREIGN KEY (admin_id) REFERENCES admin_users(id)
        ON DELETE CASCADE,

    INDEX idx_admin_security_log_admin (admin_id, created_at),
    INDEX idx_admin_security_log_type (event_type)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
