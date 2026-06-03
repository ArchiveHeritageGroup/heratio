-- ============================================================================
-- ahg-privacy Phase 3 (#1108) — Field-level structured redaction for archival
-- description metadata. Idempotent (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Controlled vocabulary of redaction reasons (POPIA/GDPR legal bases).
CREATE TABLE IF NOT EXISTS privacy_reason (
    id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) NOT NULL UNIQUE,
    label_en    VARCHAR(200) NOT NULL,
    label_af    VARCHAR(200) NULL,
    requires_review        BOOLEAN DEFAULT TRUE,
    requires_legal_review  BOOLEAN DEFAULT FALSE,
    sort_order  TINYINT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO privacy_reason (code, label_en, requires_review, requires_legal_review, sort_order) VALUES
('personal_data',        'Contains personal data',                                    TRUE,  FALSE, 1),
('special_category',     'Special category data (GDPR Art.9 / POPIA s.26)',           TRUE,  TRUE,  2),
('biometric',            'Biometric or facial recognition data',                      TRUE,  TRUE,  3),
('minor',                'Data subject is or may be a minor',                         TRUE,  TRUE,  4),
('legal_case',           'Related to legal proceedings',                              TRUE,  TRUE,  5),
('third_party',          'Contains third-party personal data',                        FALSE, FALSE, 6),
('erasure_request',      'Data subject erasure request (GDPR Art.17 / POPIA s.24)',   TRUE,  TRUE,  7),
('access_request',       'Data subject access request pending',                       TRUE,  FALSE, 8),
('cultural_sensitivity', 'Culturally sensitive personal data',                        TRUE,  FALSE, 9),
('confidential',         'Confidential personnel or institutional data',              FALSE, FALSE, 10);

-- One privacy profile per information_object.
CREATE TABLE IF NOT EXISTS information_object_privacy (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id  BIGINT UNSIGNED NOT NULL UNIQUE,
    privacy_reason_id      TINYINT UNSIGNED NOT NULL,
    redaction_status       ENUM('none','partial','full','pending') DEFAULT 'none',
    applied_by             BIGINT UNSIGNED NULL,
    applied_at             DATETIME NULL,
    legal_basis_reference  VARCHAR(500) NULL,
    notes                  TEXT NULL,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_iop_io (information_object_id),
    INDEX idx_iop_status (redaction_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-field redaction decisions under a profile.
CREATE TABLE IF NOT EXISTS information_object_privacy_field (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    privacy_id        BIGINT UNSIGNED NOT NULL,
    field_name        VARCHAR(100) NOT NULL,
    redaction_type    ENUM('full','partial','pseudonymised') DEFAULT 'full',
    redaction_pattern VARCHAR(100) NULL,
    reason            VARCHAR(500) NOT NULL,
    is_sensitive      BOOLEAN DEFAULT FALSE,
    reviewed_by       BIGINT UNSIGNED NULL,
    reviewed_at       DATETIME NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_privacy_field (privacy_id, field_name),
    INDEX idx_iopf_privacy (privacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit trail of who viewed/redacted/changed field privacy on an IO.
CREATE TABLE IF NOT EXISTS information_object_privacy_log (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id  BIGINT UNSIGNED NOT NULL,
    privacy_field_id       BIGINT UNSIGNED NULL,
    user_id                BIGINT UNSIGNED NULL,
    action                 VARCHAR(50) NOT NULL,
    field_name             VARCHAR(100) NULL,
    legal_basis            VARCHAR(500) NULL,
    detail                 TEXT NULL,
    ip_address             VARCHAR(45) NULL,
    user_agent             VARCHAR(500) NULL,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_iopl_io (information_object_id),
    INDEX idx_iopl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
