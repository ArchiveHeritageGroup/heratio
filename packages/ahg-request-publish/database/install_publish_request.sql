-- ============================================================================
-- ahg-request-publish - ahg_publish_request schema (Heratio #745)
-- ============================================================================
-- Lightweight, token-anchored publish-request flow that runs alongside the
-- legacy request_to_publish / request_to_publish_i18n tables from the AtoM
-- port. The legacy tables are untouched; new code uses ahg_publish_request.
--
-- Status values live in ahg_dropdown under taxonomy 'publish_request_status'
-- (pending / approved / rejected / edited). The dropdown is auto-seeded by
-- AhgRequestPublishServiceProvider on first boot.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ahg_publish_request` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `information_object_id` BIGINT UNSIGNED NULL,
  `submitter_email` VARCHAR(190) NOT NULL,
  `submitter_name` VARCHAR(190) NULL,
  `message_text` TEXT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `token` CHAR(40) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at` DATETIME NULL,
  `decided_by_user_id` BIGINT UNSIGNED NULL,
  `curator_notes` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_status` (`status`),
  KEY `idx_io` (`information_object_id`),
  KEY `idx_submitter_email` (`submitter_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
