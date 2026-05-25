-- Heratio AI Compliance - EU AI Act Article 14 human oversight.
--
-- Three tables:
--   ai_oversight_policy: one row per AI service, halt + review controls.
--   ai_operator_attestation: annual user acknowledgement of automation-bias
--     training, required to approve AI output.
--   ai_review_decision: every confirm/override/reject plus Art. 14(5)
--     countersignature, cross-linked to ai_inference_log.

CREATE TABLE IF NOT EXISTS `ai_oversight_policy` (
    `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service`                     VARCHAR(32) NOT NULL,
    `requires_human_review`       TINYINT(1) NOT NULL DEFAULT 1,
    `confidence_threshold`        DECIMAL(4,3) NOT NULL DEFAULT 0.750,
    `dual_review_required`        TINYINT(1) NOT NULL DEFAULT 0,
    `halted`                      TINYINT(1) NOT NULL DEFAULT 0,
    `halted_reason`               VARCHAR(255) DEFAULT NULL,
    `halted_at`                   DATETIME DEFAULT NULL,
    `halted_by_user_id`           BIGINT UNSIGNED DEFAULT NULL,
    `automation_bias_prompt_text` VARCHAR(512) DEFAULT NULL,
    `created_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_service` (`service`),
    KEY `idx_halted` (`halted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_operator_attestation` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `attested_at`     DATETIME NOT NULL,
    `expires_at`      DATETIME NOT NULL,
    `version`         VARCHAR(16) NOT NULL,
    `chain_entry_hash` CHAR(64) DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_expires` (`user_id`, `expires_at`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_review_decision` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inference_log_id`      BIGINT UNSIGNED DEFAULT NULL,
    `service`               VARCHAR(32) NOT NULL,
    `reviewer_user_id`      BIGINT UNSIGNED NOT NULL,
    `decision`              VARCHAR(16) NOT NULL,
    `note`                  TEXT,
    `countersigner_user_id` BIGINT UNSIGNED DEFAULT NULL,
    `countersigned_at`      DATETIME DEFAULT NULL,
    `chain_entry_hash`      CHAR(64) DEFAULT NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_service_created` (`service`, `created_at`),
    KEY `idx_reviewer` (`reviewer_user_id`),
    KEY `idx_inference_log` (`inference_log_id`),
    KEY `idx_decision` (`decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
