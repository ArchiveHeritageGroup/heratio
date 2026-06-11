-- heratio#1236 - Research OS moonshot 17: Contradiction Engine.
--
-- Per-project store of contradictions detected across the project's Claim Ledger
-- (research_assertion + research_assertion_evidence + research_claim_meta).
--
-- This is the ONLY table the slice owns. The Claim Ledger tables are NEVER
-- altered. Every finding references one or two existing claims by id; the scan
-- is idempotent (a stable signature de-duplicates re-runs), and the user can
-- dismiss or resolve a finding without ever touching the claims themselves.
--
-- All enumerated columns are VARCHAR (Dropdown Manager pattern) - never ENUM.
CREATE TABLE IF NOT EXISTS `research_contradiction` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `claim_a_id` INT NOT NULL,
  `claim_b_id` INT NULL,
  `kind` VARCHAR(60) NOT NULL DEFAULT 'ai_flagged',
    -- opposing_status | shared_source_conflict | confidence_drop | definition_drift | ai_flagged
  `signature` VARCHAR(190) NOT NULL DEFAULT '',
    -- stable hash of (kind + sorted claim ids) so a re-scan upserts rather than duplicates
  `detail` TEXT NULL,
  `severity` VARCHAR(40) NOT NULL DEFAULT 'medium',
    -- low | medium | high
  `status` VARCHAR(40) NOT NULL DEFAULT 'open',
    -- open | dismissed | resolved
  `source` VARCHAR(40) NOT NULL DEFAULT 'heuristic',
    -- heuristic | ai (gateway) - records how the finding was produced
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_signature_uniq` (`project_id`, `signature`),
  KEY `rc_project_status` (`project_id`, `status`),
  KEY `rc_claim_a` (`claim_a_id`),
  KEY `rc_claim_b` (`claim_b_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
