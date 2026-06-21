-- Compliance Control Catalog - a vendor- and jurisdiction-agnostic catalogue of
-- governance/privacy/access controls and the regulatory obligations that map to
-- them. Backs the legal-mapping annex of the Industry AI for RM/Archives
-- framework: implementers query "for regime X, which controls + recommended
-- configuration apply". Content is regime-neutral (no product names, no single
-- jurisdiction defaulted), add local regimes as ahg_compliance_mapping rows.

CREATE TABLE IF NOT EXISTS `ahg_compliance_control` (
    `control_id`         VARCHAR(16)  NOT NULL,
    `control_name`       VARCHAR(255) NOT NULL,
    `category`           VARCHAR(64)  NOT NULL DEFAULT 'governance',
    `objective`          TEXT         NULL,
    `recommended_config` TEXT         NULL,
    `standards_refs`     VARCHAR(512) NULL,
    `sort_order`         INT          NOT NULL DEFAULT 100,
    `created_at`         TIMESTAMP    NULL,
    `updated_at`         TIMESTAMP    NULL,
    PRIMARY KEY (`control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ahg_compliance_mapping` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `regime`             VARCHAR(128) NOT NULL,
    `jurisdiction`       VARCHAR(128) NULL,
    `scope`              VARCHAR(512) NULL,
    `obligation`         VARCHAR(512) NULL,
    `control_id`         VARCHAR(16)  NOT NULL,
    `recommended_config` TEXT         NULL,
    `created_at`         TIMESTAMP    NULL,
    `updated_at`         TIMESTAMP    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ccm` (`regime`, `control_id`),
    KEY `ix_ccm_control` (`control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Controls (regime-neutral) ────────────────────────────────────────────────
INSERT IGNORE INTO `ahg_compliance_control`
    (`control_id`, `control_name`, `category`, `objective`, `recommended_config`, `standards_refs`, `sort_order`, `created_at`, `updated_at`) VALUES
('C-PRV-01', 'Lawful Basis & Purpose Limitation', 'privacy',
 'Establish and record a lawful basis and a defined purpose for each dataset, limit processing to that purpose.',
 'Require a configured lawful_basis per dataset, record a processing-activity (RoPA) entry, enable purpose labels at ingest.',
 'ISO/IEC 27701, data-protection regimes', 10, NOW(), NOW()),
('C-PRV-02', 'Data Subject Rights Handling', 'privacy',
 'Provide workflows for access, rectification, erasure, restriction, portability and objection, with SLA tracking.',
 'Enable a subject-request workflow with SLA timers, link requests to affected records, record fulfilment evidence.',
 'data-protection regimes', 20, NOW(), NOW()),
('C-PRV-03', 'Privacy Impact Assessment', 'privacy',
 'Require a documented risk assessment for large-scale or special-category processing, linked to the pipeline.',
 'Require a DPIA for qualifying datasets, link the DPIA to the ingestion pipeline, record residual-risk sign-off.',
 'ISO/IEC 29134', 30, NOW(), NOW()),
('C-RES-02', 'Data Residency & Transfer Constraint', 'residency',
 'Constrain storage location and cross-border transfer to lawful regions or adequate safeguards.',
 'Set a deployment-region flag, encrypt in transit and at rest, configure a cross-region replication + transfer-safeguard policy.',
 'ISO/IEC 27001', 40, NOW(), NOW()),
('C-ACC-05', 'Access-to-Information Request Handling', 'access',
 'Respond to public-access / freedom-of-information requests within the statutory timeline, publish non-exempt records.',
 'Provide a request workflow + redaction queue + SLA timers + an audit-proof response export.',
 'access-to-information statutes', 50, NOW(), NOW()),
('C-SEC-03', 'Special-Category Data Safeguards', 'security',
 'Tag and minimise special-category data (e.g. health), bind processors contractually, meet breach-notification windows.',
 'Apply special-category tagging, require processor agreements, configure breach-notification timers + contacts.',
 'ISO/IEC 27001, sectoral health regimes', 60, NOW(), NOW()),
('C-GOV-01', 'AI Provenance & Model Governance', 'governance',
 'Capture machine-readable provenance for every AI-derived assertion, register models with version and evaluation metrics.',
 'Record append-only provenance events (model name/version/config/confidence), maintain a model registry with evaluation sets and drift monitoring.',
 'ISO/IEC 42001, ISO/IEC 23894, W3C PROV-O', 70, NOW(), NOW()),
('C-GOV-02', 'Human-in-the-Loop Review', 'governance',
 'Require human validation for high-risk actions (disposal, transfer, sensitive access).',
 'Drive confidence-threshold review queues, assign reviewer roles, record every review decision.',
 'ISO 15489', 80, NOW(), NOW()),
('C-AUD-01', 'Tamper-Evident Audit & Chain-of-Custody', 'audit',
 'Maintain signed, append-only audit logs and exportable chain-of-custody manifests.',
 'Use an append-only log store with server-signed entries, support a chain-of-custody export (e.g. METS / bagit).',
 'ISO 16363', 90, NOW(), NOW());

-- ── Regime → control mappings (illustrative, regime-neutral examples) ─────────
INSERT IGNORE INTO `ahg_compliance_mapping`
    (`regime`, `jurisdiction`, `scope`, `obligation`, `control_id`, `recommended_config`, `created_at`, `updated_at`) VALUES
('General data-protection regulation', 'Regional (e.g. EU)', 'Personal data of covered residents', 'Lawful basis and purpose limitation for processing personal data', 'C-PRV-01', 'Configure lawful_basis, record RoPA, enable purpose labels', NOW(), NOW()),
('General data-protection regulation', 'Regional (e.g. EU)', 'Personal data of covered residents', 'Data-subject rights (access, erasure, portability, objection)', 'C-PRV-02', 'Subject-request workflow with SLA tracking', NOW(), NOW()),
('General data-protection regulation', 'Regional (e.g. EU)', 'Personal data of covered residents', 'Impact assessment for large-scale / special-category processing', 'C-PRV-03', 'Require a DPIA linked to the pipeline', NOW(), NOW()),
('General data-protection regulation', 'Regional (e.g. EU)', 'Personal data of covered residents', 'Cross-border transfer restrictions / adequate safeguards', 'C-RES-02', 'Region flag, transfer safeguards, encrypted replication', NOW(), NOW()),
('Access-to-information statute', 'Configurable', 'Records held by public bodies', 'Publication / access on request, exemptions, statutory timelines', 'C-ACC-05', 'Redaction queue, SLA timers, audit-proof export', NOW(), NOW()),
('Sectoral health-data regime', 'Configurable', 'Health / special-category data', 'Special-category handling, processor agreements, breach notification', 'C-SEC-03', 'Special-category tagging, processor agreements, breach timers', NOW(), NOW()),
('Sectoral health-data regime', 'Configurable', 'Health / special-category data', 'Individual access to held personal/health data', 'C-PRV-02', 'Subject-request workflow scoped to health records', NOW(), NOW());
