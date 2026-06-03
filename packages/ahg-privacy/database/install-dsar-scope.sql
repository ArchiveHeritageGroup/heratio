-- ============================================================================
-- ahg-privacy (#1108 deliverable 5) - DSAR <-> information_object scope link.
--
-- Records which archival descriptions a DSAR covers. When an IO is added to a
-- DSAR's scope (or when the DSAR moves to "processing") an information_object
-- _privacy profile is pre-populated so the officer can mark fields for
-- redaction as part of the response. Soft links only (no hard FKs) so the
-- install never fails on table ordering.
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
-- Licensed AGPL-3.0-or-later.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `privacy_dsar_object` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dsar_id`               BIGINT UNSIGNED NOT NULL,
  `information_object_id` BIGINT UNSIGNED NOT NULL,
  `privacy_id`            BIGINT UNSIGNED NULL,
  `created_by`            BIGINT UNSIGNED NULL,
  `created_at`            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dsar_object` (`dsar_id`, `information_object_id`),
  KEY `idx_dsar_object_dsar` (`dsar_id`),
  KEY `idx_dsar_object_io` (`information_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
