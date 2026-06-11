-- heratio#1223 - Research OS Stage 8: Claim Ledger.
--
-- Sidecar table for the per-project Claim Ledger. The core claim text, status,
-- confidence, project + researcher ownership already live in `research_assertion`
-- and are NEVER altered. This table holds ONLY the additional Claim-Ledger fields
-- that `research_assertion` does not have, keyed 1:1 by assertion_id.
--
-- Evidence links reuse the existing `research_assertion_evidence` table; nothing
-- about evidence is duplicated here.
CREATE TABLE IF NOT EXISTS `research_claim_meta` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assertion_id` INT NOT NULL,
  `evidence_type` VARCHAR(80) NULL,
  `confidence_level` VARCHAR(40) NULL,
  `provenance_kind` VARCHAR(40) NULL DEFAULT 'original',
  `supporting_sources` TEXT NULL,
  `opposing_sources` TEXT NULL,
  `quotations` MEDIUMTEXT NULL,
  `method_theory_link` TEXT NULL,
  `researcher_notes` TEXT NULL,
  `unresolved_weaknesses` TEXT NULL,
  `ethical_concerns` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rcm_assertion_uniq` (`assertion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
