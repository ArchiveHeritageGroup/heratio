-- ============================================================================
-- ahg-privacy Phase 4 (#1109) - DPIA <-> ROPA integration, high-risk screening
-- flags on the Article 30 register, and the DPIA status-change audit log.
--
-- Idempotent: ADD COLUMN statements re-run harmlessly because the installer
-- (AhgPrivacyServiceProvider::installSqlFile) swallows "duplicate column name".
-- privacy_dpia_log uses CREATE TABLE IF NOT EXISTS.
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
-- Licensed AGPL-3.0-or-later.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- ROPA <-> DPIA linkage + high-risk screening overrides on the Article 30
-- register. dpia_required is set by the auto-screen (DpiaRiskService); the
-- three *_override columns let a DPO force a determination that the heuristic
-- would otherwise miss (NULL = let the heuristic decide).
-- ----------------------------------------------------------------------------
ALTER TABLE `ahg_processing_activity` ADD COLUMN `dpia_required` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;
ALTER TABLE `ahg_processing_activity` ADD COLUMN `dpia_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `dpia_required`;
ALTER TABLE `ahg_processing_activity` ADD COLUMN `dpia_date` DATE NULL AFTER `dpia_completed`;
ALTER TABLE `ahg_processing_activity` ADD COLUMN `dpia_screening_note` TEXT NULL AFTER `dpia_date`;
ALTER TABLE `ahg_processing_activity` ADD COLUMN `special_category_override` TINYINT(1) NULL AFTER `dpia_screening_note`;
ALTER TABLE `ahg_processing_activity` ADD COLUMN `large_scale_profiling_override` TINYINT(1) NULL AFTER `special_category_override`;
ALTER TABLE `ahg_processing_activity` ADD COLUMN `biometric_override` TINYINT(1) NULL AFTER `large_scale_profiling_override`;

-- ----------------------------------------------------------------------------
-- DPIA status-change audit trail. One row per lifecycle event (created,
-- updated, review, signoff, archive) and per auto-screen flag change on a
-- linked ROPA entry. Soft links only (no hard FKs) so the install never fails
-- on ordering or a missing parent table.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `privacy_dpia_log` (
  `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dpia_id`                BIGINT UNSIGNED NULL,
  `processing_activity_id` BIGINT UNSIGNED NULL,
  `action`                 VARCHAR(64) NOT NULL,
  `from_status`            VARCHAR(32) NULL,
  `to_status`              VARCHAR(32) NULL,
  `user_id`                BIGINT UNSIGNED NULL,
  `note`                   TEXT NULL,
  `ip_address`             VARCHAR(64) NULL,
  `created_at`             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dpia_log_dpia` (`dpia_id`),
  KEY `idx_dpia_log_activity` (`processing_activity_id`),
  KEY `idx_dpia_log_action` (`action`),
  KEY `idx_dpia_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
