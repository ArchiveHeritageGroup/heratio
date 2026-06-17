-- Heratio AI Compliance - EU AI Act system inventory and risk tiering.
--
-- The model registry (ai_model_registry), risk register (ai_risk_register),
-- oversight policies and attestations are all per-service or per-model. What
-- was missing is the system-level register that the EU AI Act is actually
-- framed around (Art. 6 classification, Art. 52 transparency tiers): a list of
-- the AI systems the organisation provides or deploys, each with its role,
-- risk classification, lifecycle status, human-oversight measures, accountable
-- owner, and review schedule. This table fills that gap.
--
-- Standalone table, no FK to core tables, no ENUM columns per AHG rules.
--
-- NOTE on syntax. This file is parsed by AhgAiComplianceServiceProvider with a
-- naive explode-by-semicolon splitter. Do not use a raw semicolon inside any
-- string literal or comment line.

CREATE TABLE IF NOT EXISTS `ai_system` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                VARCHAR(255) NOT NULL,
    `description`         TEXT DEFAULT NULL,
    `purpose`             TEXT DEFAULT NULL COMMENT 'Intended purpose (Art. 6)',
    `provider`            VARCHAR(255) DEFAULT NULL COMMENT 'Provider or deployer name',
    `role`                VARCHAR(20) NOT NULL DEFAULT 'deployer' COMMENT 'provider, deployer, importer, distributor',
    `risk_classification` VARCHAR(20) NOT NULL DEFAULT 'minimal' COMMENT 'prohibited, high, limited, minimal',
    `lifecycle_status`    VARCHAR(20) NOT NULL DEFAULT 'development' COMMENT 'development, deployed, suspended, retired',
    `deployment_context`  TEXT DEFAULT NULL,
    `human_oversight`     TEXT DEFAULT NULL COMMENT 'Human oversight measures (Art. 14)',
    `owner`               VARCHAR(255) DEFAULT NULL COMMENT 'Accountable person or unit',
    `last_review_date`    DATE DEFAULT NULL,
    `next_review_date`    DATE DEFAULT NULL,
    `is_active`           TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_risk` (`risk_classification`),
    KEY `idx_status` (`lifecycle_status`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
