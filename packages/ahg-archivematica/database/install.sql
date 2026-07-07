-- ============================================================================
-- ahg-archivematica - install schema
-- ============================================================================
-- Archivematica connector for Heratio. Two tables:
--   am_link - maps a Heratio information_object <-> an Archivematica package
--             (transfer / SIP / AIP / DIP UUIDs).
--   am_job  - drives + monitors transfers in both directions.
--
-- Heratio rule: no MySQL ENUM columns - use VARCHAR for enumerated values.
-- Idempotent: CREATE TABLE IF NOT EXISTS so re-running on boot is safe.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

--
-- Table structure for table `am_link`
--
CREATE TABLE IF NOT EXISTS `am_link` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL COMMENT 'information_object.id this package is attached to',
  `transfer_uuid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sip_uuid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aip_uuid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dip_uuid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `am_pipeline_uuid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g. pending, linked, failed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  UNIQUE KEY `uniq_dip_uuid` (`dip_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `am_job`
--
CREATE TABLE IF NOT EXISTS `am_job` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL COMMENT 'information_object.id, if this job targets a description',
  `direction` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'to_am' COMMENT 'to_am | from_am (VARCHAR, not ENUM)',
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending | processing | complete | failed',
  `am_uuid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'transfer/SIP UUID being tracked',
  `microservice` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'current AM microservice / step name',
  `last_polled_at` timestamp NULL DEFAULT NULL,
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` json DEFAULT NULL COMMENT 'raw API request/response snapshot',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_direction` (`direction`),
  KEY `idx_status` (`status`),
  KEY `idx_am_uuid` (`am_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
