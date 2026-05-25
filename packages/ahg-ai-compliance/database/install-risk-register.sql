-- Heratio AI Compliance - EU AI Act Article 9 risk register.
--
-- One row per identified risk per AI service. Operator-edited via the
-- admin UI; auto-seeded on first boot with a default set of known risks
-- per service. Sign-off events emit a receipt to the #693 chain so the
-- review history is itself tamper-evident.

CREATE TABLE IF NOT EXISTS `ai_risk_register` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service`           VARCHAR(32) NOT NULL,
    `risk_description`  VARCHAR(512) NOT NULL,
    `severity`          VARCHAR(16) NOT NULL DEFAULT 'medium',
    `likelihood`        VARCHAR(16) NOT NULL DEFAULT 'medium',
    `intended_or_misuse` VARCHAR(32) NOT NULL DEFAULT 'intended',
    `affected_group`    VARCHAR(64) DEFAULT NULL,
    `mitigation`        TEXT,
    `residual_risk`     VARCHAR(16) NOT NULL DEFAULT 'low',
    `status`            VARCHAR(16) NOT NULL DEFAULT 'active',
    `last_reviewed_at`  DATETIME DEFAULT NULL,
    `reviewer_user_id`  BIGINT UNSIGNED DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_service` (`service`),
    KEY `idx_status` (`status`),
    KEY `idx_severity` (`severity`),
    KEY `idx_affected_group` (`affected_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Incident log: real-world reports flagged against a risk. Feeds the
-- weekly post-market monitoring digest.
CREATE TABLE IF NOT EXISTS `ai_risk_incident` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `risk_id`           BIGINT UNSIGNED NOT NULL,
    `reporter_user_id`  BIGINT UNSIGNED DEFAULT NULL,
    `description`       TEXT NOT NULL,
    `severity_observed` VARCHAR(16) NOT NULL DEFAULT 'medium',
    `inference_log_id`  BIGINT UNSIGNED DEFAULT NULL,
    `resolved_at`       DATETIME DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_risk_id` (`risk_id`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_incident_risk` FOREIGN KEY (`risk_id`) REFERENCES `ai_risk_register` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
