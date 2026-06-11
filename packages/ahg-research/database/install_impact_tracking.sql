-- heratio#1241 - Research OS #19 (moonshot 25): Impact Tracking.
--
-- After a project's work is PUBLISHED (a research_submission row carrying a DOI,
-- produced by Publication Studio #1232), Heratio tracks the downstream IMPACT of
-- that output - citations, mentions and dataset reuse - by polling PUBLIC
-- bibliographic services. The DOIs are sourced READ-ONLY from
-- research_submission (status = published/accepted AND doi present); this table
-- never alters research_submission and only ever receives additive inserts.
--
-- The polling (see ImpactTrackingService) goes to OpenAlex
-- (https://api.openalex.org - cited_by_count plus the cited-by works list) and
-- Crossref event data (https://api.crossref.org / Event Data) over Laravel's
-- Http client. These are PUBLIC bibliographic services, NOT AI services, so they
-- are called DIRECTLY - never through the AHG AI gateway. Each call has a short
-- timeout and a descriptive User-Agent, is wrapped in its own try/catch, and a
-- failure simply yields no new signals (the build and tests never depend on the
-- network).
--
-- signal_type is a VARCHAR (NOT a MySQL ENUM) per project rules; it holds one of
-- the codes citation | mention | dataset_reuse | other, with the canonical list
-- living in the ImpactTrackingService.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Auto-installed on boot from the
-- AhgResearchServiceProvider. Existing tables are NEVER altered.

CREATE TABLE IF NOT EXISTS `research_impact_signal` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`    INT NOT NULL,
  `submission_id` BIGINT UNSIGNED NULL,          -- research_submission.id when the signal is tied to a specific published output (nullable)
  `doi`           VARCHAR(255) NULL,             -- the published output's DOI the signal was found against
  `signal_type`   VARCHAR(40) NOT NULL DEFAULT 'citation', -- citation | mention | dataset_reuse | other (ImpactTrackingService::TYPES)
  `title`         VARCHAR(500) NULL,             -- the citing/mentioning work's title (or a count summary)
  `detail`        TEXT NULL,
  `url`           VARCHAR(1000) NULL,            -- canonical URL of the citing work / mention (drives idempotency)
  `source`        VARCHAR(60) NULL,             -- openalex | crossref-event | manual ...
  `detected_at`   DATETIME NULL,
  `created_at`    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risig_project_idx` (`project_id`),
  KEY `risig_project_type_idx` (`project_id`, `signal_type`),
  KEY `risig_submission_idx` (`submission_id`),
  KEY `risig_doi_idx` (`doi`),
  KEY `risig_source_idx` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
