-- ============================================================================
-- ahg-privacy Phase 1 (#669) - PII scan report + Article 30 register + DPIA
-- ============================================================================
-- Issue #669 Phase 1. Net-new sidecar tables alongside the existing PSIS-port
-- privacy_* tables. The Phase 1 tables use the ahg_* prefix because the spec
-- in #669 calls them out explicitly and they are deliberately decoupled from
-- the larger privacy_processing_activity register (which is still the PSIS
-- ROPA workbench for SA POPIA UI). The two registers are linkable via
-- ahg_processing_activity.linked_psis_id (FK soft-link, nullable).
--
-- Idempotent: every CREATE is IF NOT EXISTS and every INSERT is INSERT IGNORE.
-- Auto-seeded by AhgPrivacyServiceProvider on first boot.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- PII scan reports: one row per scan invocation against an information_object.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ahg_pii_scan_report` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `information_object_id` INT UNSIGNED DEFAULT NULL COMMENT 'information_object.id - nullable for ad-hoc text scans',
  `scan_started_at` DATETIME NOT NULL,
  `scan_finished_at` DATETIME DEFAULT NULL,
  `hits_total` INT NOT NULL DEFAULT 0,
  `hits_by_type` JSON DEFAULT NULL COMMENT 'Map of type=>count (email, phone, national_id, credit_card, ip, date_of_birth)',
  `findings` JSON DEFAULT NULL COMMENT 'Optional verbose finding list - capped at 500 entries',
  `jurisdiction` VARCHAR(32) NOT NULL DEFAULT 'gdpr' COMMENT 'gdpr, popia, uk_gdpr, ccpa',
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewed, redacted, accepted_risk',
  `scanned_by_user_id` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pii_io` (`information_object_id`),
  KEY `idx_pii_status` (`status`),
  KEY `idx_pii_juris` (`jurisdiction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- GDPR Article 30 - Record of Processing Activities (RoPA) register.
-- Distinct from privacy_processing_activity which is the SA POPIA workbench
-- inherited from PSIS; this table is the regulator-aligned Article 30 export
-- surface used by the privacy:article-30-export CLI.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ahg_processing_activity` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `purpose` TEXT NOT NULL,
  `lawful_basis` VARCHAR(64) NOT NULL DEFAULT 'legitimate_interests' COMMENT 'consent, contract, legal_obligation, vital_interests, public_task, legitimate_interests',
  `categories_of_data` JSON DEFAULT NULL COMMENT 'Array of strings - contact, identifiers, financial, health, biometric, special_category',
  `categories_of_subjects` JSON DEFAULT NULL COMMENT 'Array of strings - users, researchers, donors, employees, public',
  `recipients` JSON DEFAULT NULL COMMENT 'Array of strings - internal, processors, regulators, third_parties',
  `retention_period` VARCHAR(255) DEFAULT NULL,
  `security_measures` TEXT DEFAULT NULL,
  `transfers_outside_eea` TINYINT(1) NOT NULL DEFAULT 0,
  `safeguards` TEXT DEFAULT NULL COMMENT 'SCC, BCR, adequacy decision, derogation',
  `dpo_contact` VARCHAR(255) DEFAULT NULL,
  `linked_psis_id` INT UNSIGNED DEFAULT NULL COMMENT 'Optional link to legacy privacy_processing_activity.id',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ahg_pa_active` (`is_active`),
  KEY `idx_ahg_pa_basis` (`lawful_basis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- DPIA register (GDPR Article 35). Multi-step workflow: draft -> review ->
-- completed -> archived. Sign-off writes a row through the audit-trail
-- ChainedAuditWriter so the assessment is tamper-evident (#676 Phase 5).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ahg_dpia` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `processing_activity_id` INT UNSIGNED DEFAULT NULL COMMENT 'ahg_processing_activity.id (nullable until linked)',
  `description` TEXT DEFAULT NULL,
  `necessity_proportionality` TEXT DEFAULT NULL COMMENT 'Step 1: necessity and proportionality assessment',
  `risks_to_subjects` TEXT DEFAULT NULL COMMENT 'Step 2: identified risks to data subjects',
  `measures_to_mitigate` TEXT DEFAULT NULL COMMENT 'Step 3: mitigation measures',
  `residual_risks` TEXT DEFAULT NULL COMMENT 'Step 3: residual risk after mitigation',
  `dpo_opinion` TEXT DEFAULT NULL COMMENT 'Step 4: DPO consultation opinion',
  `dpo_consulted_at` DATE DEFAULT NULL,
  `completed_at` DATE DEFAULT NULL,
  `signed_off_by_user_id` INT DEFAULT NULL,
  `signed_off_at` DATETIME DEFAULT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'draft' COMMENT 'draft, review, completed, archived',
  `created_by_user_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dpia_status` (`status`),
  KEY `idx_dpia_activity` (`processing_activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Default Article 30 register seeds for Heratio (5 activities).
-- Idempotent: keyed by `name` (UNIQUE side-effect via INSERT IGNORE on the
-- functional unique we install below).
-- ---------------------------------------------------------------------------
-- Idempotent: MySQL 8.0 has no "ADD UNIQUE KEY IF NOT EXISTS", and a bare ADD
-- throws 1061 (duplicate key) on the second install-bootstrap pass, which skips
-- the whole file. Guard via information_schema so re-runs are no-ops (#1136).
SET @ddl := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.STATISTICS
      WHERE table_schema = DATABASE()
        AND table_name = 'ahg_processing_activity'
        AND index_name = 'uk_ahg_pa_name'
    ),
    'SELECT 1',
    'ALTER TABLE `ahg_processing_activity` ADD UNIQUE KEY `uk_ahg_pa_name` (`name`)'
  )
);
PREPARE _s FROM @ddl; EXECUTE _s; DEALLOCATE PREPARE _s;

INSERT IGNORE INTO `ahg_processing_activity`
  (`name`, `purpose`, `lawful_basis`, `categories_of_data`, `categories_of_subjects`,
   `recipients`, `retention_period`, `security_measures`, `transfers_outside_eea`,
   `safeguards`, `dpo_contact`, `is_active`)
VALUES
  ('User authentication',
   'Authenticate users accessing the Heratio application and enforce role-based access.',
   'contract',
   JSON_ARRAY('identifiers', 'contact'),
   JSON_ARRAY('users', 'employees'),
   JSON_ARRAY('internal'),
   'Active account + 12 months after deactivation',
   'TLS in transit, Argon2id at rest, MFA optional, session timeout, ACL middleware.',
   0, NULL, NULL, 1),
  ('Archival cataloguing',
   'Create and maintain ISAD(G), ISAAR(CPF), ISDIAH, RiC and Spectrum 5.1 descriptive records for archival, library, museum and gallery holdings.',
   'public_task',
   JSON_ARRAY('identifiers', 'contact', 'special_category'),
   JSON_ARRAY('public', 'donors', 'researchers'),
   JSON_ARRAY('internal', 'public_research'),
   'Permanent (institutional archival retention)',
   'ACL middleware, ODRL policy enforcement on access, audit trail on edits.',
   0, NULL, NULL, 1),
  ('AI inference logging',
   'Log AI inference activity (HTR, OCR, NER, summarisation, translation) for EU AI Act Article 12 traceability, including signed inference receipts.',
   'legal_obligation',
   JSON_ARRAY('identifiers', 'content_metadata'),
   JSON_ARRAY('users', 'researchers'),
   JSON_ARRAY('internal'),
   '6 years (Article 12 retention)',
   'Ed25519-signed receipts, JCS canonicalisation, append-only audit chain.',
   0, NULL, NULL, 1),
  ('Audit trail',
   'Record administrative and data-access events for accountability, anomaly investigation and tamper-evident retention.',
   'legal_obligation',
   JSON_ARRAY('identifiers', 'ip_address', 'actions'),
   JSON_ARRAY('users', 'employees'),
   JSON_ARRAY('internal'),
   '7 years',
   'Hash-chained rows (SHA-256 + Ed25519), restricted read access, daily verification.',
   0, NULL, NULL, 1),
  ('Email notifications',
   'Send transactional notifications - reservations, account events, DSAR responses, scheduled exports.',
   'legitimate_interests',
   JSON_ARRAY('contact', 'identifiers'),
   JSON_ARRAY('users', 'researchers'),
   JSON_ARRAY('internal', 'email_relay'),
   'Notification log retained 24 months',
   'TLS to relay, DKIM signing, opt-out per category.',
   0, NULL, NULL, 1);

SET FOREIGN_KEY_CHECKS = 1;
