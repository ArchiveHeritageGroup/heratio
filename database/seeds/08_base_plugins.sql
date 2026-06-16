-- ============================================================================
-- 08_base_plugins.sql - register the FULL plugin set (all enabled except federation)
-- ============================================================================
-- A fresh install ships with every plugin registered AND enabled, so the nav,
-- the Browse menu and the Plugin Management page are fully populated out of the
-- box. The only exception is ahgFederationPlugin, which ships installed but
-- DISABLED (an admin enables it from /admin/ahgSettings/plugins when wanted).
--
-- The "all packages" block below is generated from the repo itself - the
-- packages/ahg-* folders + the §9 mapping in docs/standalone-install-plan.md +
-- each package's composer.json description. No production/DB dependency: any
-- clone reproduces it. Regenerate with tools/gen-base-plugins.php if packages
-- are added.
--
-- atom_plugin.name is UNIQUE: INSERT IGNORE registers missing rows; the trailing
-- UPDATEs force-enable the locked core set and force-disable federation, so the
-- seed is self-healing and idempotent (safe to re-run).
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- License: AGPL-3.0-or-later
-- ============================================================================

-- Foundation plugins (is_core / is_locked) - always enabled.
INSERT IGNORE INTO `atom_plugin`
    (`name`, `class_name`, `version`, `description`, `category`, `is_enabled`, `is_core`, `is_locked`, `admin_only`, `status`, `load_order`, `created_at`, `updated_at`)
VALUES
    ('ahgCorePlugin',              'ahgCorePluginConfiguration',              '1.0.0',  'Core utilities and shared services for AHG plugins',                                                  'core',  1, 1, 1, 0, 'enabled', 1,   NOW(), NOW()),
    ('sfPropelPlugin',             'sfPropelPlugin',                          NULL,     'Propel ORM integration',                                                                              'core',  1, 1, 1, 0, 'enabled', 10,  NOW(), NOW()),
    ('qbAclPlugin',                'qbAclPlugin',                             NULL,     'Access Control List management',                                                                      'core',  1, 1, 1, 0, 'enabled', 20,  NOW(), NOW()),
    ('sfPluginAdminPlugin',        'sfPluginAdminPlugin',                     NULL,     'Legacy plugin-admin UI. Heratio uses /admin/ahgSettings/plugins instead.',                            'core',  1, 1, 1, 0, 'enabled', 40,  NOW(), NOW()),
    ('ahgSettingsPlugin',          'ahgSettingsPluginConfiguration',          '1.0.1',  'AHG Settings - central configuration UI for theme, branding, plugins, audit, integrity and per-plugin tunables.', 'admin', 1, 1, 0, 0, 'enabled', 50,  NOW(), NOW()),
    ('ahgSecurityClearancePlugin', 'ahgSecurityClearancePluginConfiguration', '1.2.9',  'Security classification, user clearance, embargo, watermarking and extended rights management',       'ahg',   1, 1, 1, 0, 'enabled', 100, NOW(), NOW()),
    ('ahgThemeB5Plugin',           'arAHGThemeB5Plugin',                      '1.14.21','Modern Bootstrap 5 theme for Heratio',                                                                'ahg',   1, 1, 1, 0, 'enabled', 100, NOW(), NOW());

-- All remaining packages - generated from the repo; all enabled except federation.
INSERT IGNORE INTO `atom_plugin`
    (`name`, `class_name`, `version`, `description`, `category`, `is_enabled`, `is_core`, `is_locked`, `admin_only`, `status`, `load_order`, `created_at`, `updated_at`)
VALUES
    ('ahg3DModelPlugin', 'ahg3DModelPluginConfiguration', '1.0.0', '3D model thumbnail generation and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 201, NOW(), NOW()),
    ('ahgAccessRequestPlugin', 'ahgAccessRequestPluginConfiguration', '1.0.0', 'Access request management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 202, NOW(), NOW()),
    ('ahgAccessionManagePlugin', 'ahgAccessionManagePluginConfiguration', '1.0.0', 'Accession browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 203, NOW(), NOW()),
    ('ahgAclPlugin', 'ahgAclPluginConfiguration', '1.0.0', 'AHG ACL - Role-based access control, security classifications, and audit logging for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 204, NOW(), NOW()),
    ('ahgActorManagePlugin', 'ahgActorManagePluginConfiguration', '1.0.0', 'Actor browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 205, NOW(), NOW()),
    ('ahgAiChatbotPlugin', 'ahgAiChatbotPluginConfiguration', '1.0.0', 'AHG AI chatbot - conversational LLM interface over the Heratio archival catalogue. Grounded responses via RAG over catalogue metadata and domain knowledge.', 'ahg', 1, 0, 0, 0, 'enabled', 206, NOW(), NOW()),
    ('ahgAiCompliancePlugin', 'ahgAiCompliancePluginConfiguration', '1.0.0', 'EU AI Act Article 12 record-keeping for Heratio. Tamper-evident inference receipt chain, Ed25519 signing, public-key endpoint, verifier CLI, retention policy. Wraps ahg/inference-receipts for Laravel.', 'ahg', 1, 0, 0, 0, 'enabled', 207, NOW(), NOW()),
    ('ahgAIPlugin', 'ahgAIPluginConfiguration', '1.0.0', 'AHG AI Services - LLM integration, NER, summarization, translation, spellcheck for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 208, NOW(), NOW()),
    ('ahgAnnotationsPlugin', 'ahgAnnotationsPluginConfiguration', '1.0.0', 'W3C Web Annotation Data Model + Web Annotation Protocol persistence backend for Heratio. Closes #100; Phase 1 of #648.', 'ahg', 1, 0, 0, 0, 'enabled', 209, NOW(), NOW()),
    ('ahgAPIPlugin', 'ahgAPIPluginConfiguration', '1.0.0', 'REST API v1 for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 210, NOW(), NOW()),
    ('ahgApiPluginPlugin', 'ahgApiPluginPluginConfiguration', '1.0.0', 'Heratio ApiPlugin package (migrated from ahgAPIPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 211, NOW(), NOW()),
    ('ahgAuditTrailPlugin', 'ahgAuditTrailPluginConfiguration', '1.0.0', 'Audit trail browse for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 212, NOW(), NOW()),
    ('ahgAuthorityResolutionPlugin', 'ahgAuthorityResolutionPluginConfiguration', '1.0.0', 'AHG Authority Resolution Engine - Evidence-based resolution for persons, places, and organisations. Replaces name-only matching with an archivist-driven workflow that surfaces neighbourhood-context evidence, ranked candidates, and Fuseki-backed provenance.', 'ahg', 1, 0, 0, 0, 'enabled', 213, NOW(), NOW()),
    ('ahgBackupPlugin', 'ahgBackupPluginConfiguration', '1.0.0', 'Backup & Restore management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 214, NOW(), NOW()),
    ('ahgBiblioBfPlugin', 'ahgBiblioBfPluginConfiguration', '1.0.0', 'AHG BIBFRAME integration - serialises bibliographic records to/from BIBFRAME 2.0 RDF via the OpenRiC RiC-O service layer.', 'ahg', 1, 0, 0, 0, 'enabled', 215, NOW(), NOW()),
    ('ahgBiblioFrbrPlugin', 'ahgBiblioFrbrPluginConfiguration', '1.0.0', 'AHG FRBR integration - serialises bibliographic records to/from the FRBR conceptual model (Work, Expression, Item, Manifestation) via the OpenRiC RiC-O service layer.', 'ahg', 1, 0, 0, 0, 'enabled', 216, NOW(), NOW()),
    ('ahgC2paPlugin', 'ahgC2paPluginConfiguration', '1.0.0', 'Heratio - C2PA 2.1 content provenance manifests for AI-touched archival content. Builds, signs (Ed25519), and emits manifests as sidecars or JUMBF-embedded JPEGs.', 'ahg', 1, 0, 0, 0, 'enabled', 217, NOW(), NOW()),
    ('ahgCartPlugin', 'ahgCartPluginConfiguration', '1.0.0', 'Shopping cart & e-commerce for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 218, NOW(), NOW()),
    ('ahgCDPAPlugin', 'ahgCDPAPluginConfiguration', '1.0.0', 'Heratio Cdpa package (migrated from ahgCDPAPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 219, NOW(), NOW()),
    ('ahgConditionPlugin', 'ahgConditionPluginConfiguration', '1.0.0', 'Condition report photo annotation for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 220, NOW(), NOW()),
    ('ahgCustomFieldsPlugin', 'ahgCustomFieldsPluginConfiguration', '1.0.0', 'Custom field definitions and values management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 221, NOW(), NOW()),
    ('ahgDacsManagePlugin', 'ahgDacsManagePluginConfiguration', '1.0.0', 'Heratio DacsManage package (migrated from ahgDacsManagePlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 222, NOW(), NOW()),
    ('ahgDAMPlugin', 'ahgDAMPluginConfiguration', '1.0.0', 'Digital Asset Management (DAM) with IPTC/XMP metadata for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 223, NOW(), NOW()),
    ('ahgDataMigrationPlugin', 'ahgDataMigrationPluginConfiguration', '1.0.0', 'CSV/XML data import with field mapping, validation, preview, batch export, and job tracking for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 224, NOW(), NOW()),
    ('ahgDcManagePlugin', 'ahgDcManagePluginConfiguration', '1.0.0', 'Heratio DcManage package (migrated from ahgDcManagePlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 225, NOW(), NOW()),
    ('ahgDedupePlugin', 'ahgDedupePluginConfiguration', '1.0.0', 'Duplicate detection and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 226, NOW(), NOW()),
    ('ahgDiscoveryPlugin', 'ahgDiscoveryPluginConfiguration', '1.0.0', 'Heratio Discovery package (migrated from ahgDiscoveryPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 227, NOW(), NOW()),
    ('ahgDisplayPlugin', 'ahgDisplayPluginConfiguration', '1.0.0', 'GLAM Display plugin for Heratio - browse, search, profiles, fields, levels', 'ahg', 1, 0, 0, 0, 'enabled', 228, NOW(), NOW()),
    ('ahgDoiPlugin', 'ahgDoiPluginConfiguration', '1.0.0', 'AHG plugin: ahg-doi', 'ahg', 1, 0, 0, 0, 'enabled', 229, NOW(), NOW()),
    ('ahgDoiManagePlugin', 'ahgDoiManagePluginConfiguration', '1.0.0', 'DOI Management with DataCite integration for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 230, NOW(), NOW()),
    ('ahgDonorManagePlugin', 'ahgDonorManagePluginConfiguration', '1.0.0', 'Donor browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 231, NOW(), NOW()),
    ('ahgDropdownManagePlugin', 'ahgDropdownManagePluginConfiguration', '1.0.0', 'Dropdown Manager for managing taxonomy dropdowns in Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 232, NOW(), NOW()),
    ('ahgExhibitionPlugin', 'ahgExhibitionPluginConfiguration', '1.0.0', 'Exhibition management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 233, NOW(), NOW()),
    ('ahgExportPlugin', 'ahgExportPluginConfiguration', '1.0.0', 'Export management for Heratio (CSV, EAD, archival, authority, repository exports)', 'ahg', 1, 0, 0, 0, 'enabled', 234, NOW(), NOW()),
    ('ahgExtendedRightsPlugin', 'ahgExtendedRightsPluginConfiguration', '1.0.0', 'Extended rights management for Heratio: embargoes, orphan works, TK labels, rights statements, CC licenses, reports', 'ahg', 1, 0, 0, 0, 'enabled', 235, NOW(), NOW()),
    ('ahgFavoritesPlugin', 'ahgFavoritesPluginConfiguration', '1.0.0', 'Favorites & collections management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 236, NOW(), NOW()),
    ('ahgFederationPlugin', 'ahgFederationPluginConfiguration', '1.0.0', 'Federation and OAI-PMH peer harvesting for Heratio', 'ahg', 0, 0, 0, 0, 'disabled', 237, NOW(), NOW()),
    ('ahgFeedbackPlugin', 'ahgFeedbackPluginConfiguration', '1.0.0', 'Feedback submission for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 238, NOW(), NOW()),
    ('ahgFormsPlugin', 'ahgFormsPluginConfiguration', '1.0.0', 'Form template builder for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 239, NOW(), NOW()),
    ('ahgFtpUploadPlugin', 'ahgFtpUploadPluginConfiguration', '1.0.0', 'FTP/SFTP browser-based upload for CSV import digital objects in Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 240, NOW(), NOW()),
    ('ahgFunctionManagePlugin', 'ahgFunctionManagePluginConfiguration', '1.0.0', 'Function (ISDF) browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 241, NOW(), NOW()),
    ('ahgFunctionsDocsPlugin', 'ahgFunctionsDocsPluginConfiguration', '1.0.0', 'Browser-rendered surface for the auto-generated function/route catalogues (closes #126)', 'ahg', 1, 0, 0, 0, 'enabled', 242, NOW(), NOW()),
    ('ahgGalleryPlugin', 'ahgGalleryPluginConfiguration', '1.0.0', 'Gallery and art management for Heratio (artworks, artists, loans, valuations)', 'ahg', 1, 0, 0, 0, 'enabled', 243, NOW(), NOW()),
    ('ahgGISPlugin', 'ahgGISPluginConfiguration', '1.0.0', 'GIS spatial search (bounding box, radius, GeoJSON) for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 244, NOW(), NOW()),
    ('ahgGraphQLPlugin', 'ahgGraphQLPluginConfiguration', '1.0.0', 'Heratio Graphql package (migrated from ahgGraphQLPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 245, NOW(), NOW()),
    ('ahgHelpPlugin', 'ahgHelpPluginConfiguration', '1.0.0', 'Help Center for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 246, NOW(), NOW()),
    ('ahgHeritagePlugin', 'ahgHeritagePluginConfiguration', '1.0.0', 'Heritage Admin, Analytics, and Custodian dashboards for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 247, NOW(), NOW()),
    ('ahgICIPPlugin', 'ahgICIPPluginConfiguration', '1.0.0', 'Heratio Icip package (migrated from ahgICIPPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 248, NOW(), NOW()),
    ('ahgIiifCollectionPlugin', 'ahgIiifCollectionPluginConfiguration', '1.0.0', 'IIIF Collection management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 249, NOW(), NOW()),
    ('ahgImageArPlugin', 'ahgImageArPluginConfiguration', '1.0.0', 'Browser-based AR overlay for Heratio - turns a flat 2D image into an image-target that triggers a Ken Burns MP4 overlay when scanned with a phone camera (MindAR + A-Frame). Artivive-style, zero-install.', 'ahg', 1, 0, 0, 0, 'enabled', 250, NOW(), NOW()),
    ('ahgInferenceReceiptsPlugin', 'ahgInferenceReceiptsPluginConfiguration', '1.0.0', 'Tamper-evident receipts for AI inference calls: SHA-256 hash chain, RFC 8785 JCS canonicalization, Ed25519 signatures. Pure PHP, zero framework dependencies. EU AI Act Article 12 / NIST AI RMF aligned.', 'ahg', 1, 0, 0, 0, 'enabled', 251, NOW(), NOW()),
    ('ahgInformationObjectManagePlugin', 'ahgInformationObjectManagePluginConfiguration', '1.0.0', 'Information object (archival description) browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 252, NOW(), NOW()),
    ('ahgIngestPlugin', 'ahgIngestPluginConfiguration', '1.0.0', 'Ingestion manager for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 253, NOW(), NOW()),
    ('ahgIntegrityPlugin', 'ahgIntegrityPluginConfiguration', '1.0.0', 'Integrity check dashboard for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 254, NOW(), NOW()),
    ('ahgIPSASPlugin', 'ahgIPSASPluginConfiguration', '1.0.0', 'IPSAS heritage asset management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 255, NOW(), NOW()),
    ('ahgJobsPlugin', 'ahgJobsPluginConfiguration', '1.0.0', 'AHG Jobs - Background job management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 256, NOW(), NOW()),
    ('ahgJobsManagePlugin', 'ahgJobsManagePluginConfiguration', '1.0.0', 'Background jobs browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 257, NOW(), NOW()),
    ('ahgLabelPlugin', 'ahgLabelPluginConfiguration', '1.0.0', 'Heratio Label package (migrated from ahgLabelPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 258, NOW(), NOW()),
    ('ahgLandingPagePlugin', 'ahgLandingPagePluginConfiguration', '1.0.0', 'Landing page builder for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 259, NOW(), NOW()),
    ('ahgLibraryPlugin', 'ahgLibraryPluginConfiguration', '1.0.0', 'Library cataloguing and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 260, NOW(), NOW()),
    ('ahgLoanPlugin', 'ahgLoanPluginConfiguration', '1.0.0', 'Loan management for Heratio: agreements, objects, returns, condition reports, facility reports, courier tracking', 'ahg', 1, 0, 0, 0, 'enabled', 261, NOW(), NOW()),
    ('ahgMarketplacePlugin', 'ahgMarketplacePluginConfiguration', '1.0.0', 'Heratio Marketplace package (migrated from ahgMarketplacePlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 262, NOW(), NOW()),
    ('ahgMediaProcessingPlugin', 'ahgMediaProcessingPluginConfiguration', '1.0.0', 'AHG Media Processing - Image derivatives, watermarking, and format conversion for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 263, NOW(), NOW()),
    ('ahgMediaStreamingPlugin', 'ahgMediaStreamingPluginConfiguration', '1.0.0', 'Media streaming and transcoding for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 264, NOW(), NOW()),
    ('ahgMenuManagePlugin', 'ahgMenuManagePluginConfiguration', '1.0.0', 'Menu browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 265, NOW(), NOW()),
    ('ahgMetadataExportPlugin', 'ahgMetadataExportPluginConfiguration', '1.0.0', 'Metadata export in multiple RDF/linked data formats for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 266, NOW(), NOW()),
    ('ahgMetadataExtractionPlugin', 'ahgMetadataExtractionPluginConfiguration', '1.0.0', 'Metadata extraction from digital objects for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 267, NOW(), NOW()),
    ('ahgModsManagePlugin', 'ahgModsManagePluginConfiguration', '1.0.0', 'Heratio ModsManage package (migrated from ahgModsManagePlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 268, NOW(), NOW()),
    ('ahgMultiTenantPlugin', 'ahgMultiTenantPluginConfiguration', '1.0.0', 'Multi-tenant management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 269, NOW(), NOW()),
    ('ahgMuseumPlugin', 'ahgMuseumPluginConfiguration', '1.0.0', 'Museum cataloguing (CCO standard) browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 270, NOW(), NOW()),
    ('ahgNarssaPlugin', 'ahgNarssaPluginConfiguration', '1.0.0', 'Archive transfer manifest packager. Builds tar.gz packages with manifest.csv + METS wrapper + EAD2002 per-item descriptions + SHA-256 checksums, recorded in narssa_transfer / narssa_transfer_item. Suitable for NARSSA, NARA, TNA-UK, and equivalent national-archive transfer regimes.', 'ahg', 1, 0, 0, 0, 'enabled', 271, NOW(), NOW()),
    ('ahgNAZPlugin', 'ahgNAZPluginConfiguration', '1.0.0', 'Heratio Naz package (migrated from ahgNAZPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 272, NOW(), NOW()),
    ('ahgNMMZPlugin', 'ahgNMMZPluginConfiguration', '1.0.0', 'NMMZ (National Museums and Monuments of Zimbabwe) compliance management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 273, NOW(), NOW()),
    ('ahgOaiPlugin', 'ahgOaiPluginConfiguration', '1.0.0', 'OAI-PMH 2.0 endpoint for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 274, NOW(), NOW()),
    ('ahgObservabilityPlugin', 'ahgObservabilityPluginConfiguration', '1.0.0', 'Prometheus metrics + OpenTelemetry tracing for Heratio (issue #677 Phases 3-5)', 'ahg', 1, 0, 0, 0, 'enabled', 275, NOW(), NOW()),
    ('ahgOcflPlugin', 'ahgOcflPluginConfiguration', '1.0.0', 'OCFL v1.1 (Oxford Common File Layout) storage layer for Heratio - versioned, hash-addressable archival object store with sha512 fixity, deterministic inventory.json, and Laravel filesystem abstraction (local / S3 / Wasabi).', 'ahg', 1, 0, 0, 0, 'enabled', 276, NOW(), NOW()),
    ('ahgPdfToolsPlugin', 'ahgPdfToolsPluginConfiguration', '1.0.0', 'PDF text extraction and TIFF/PDF merging tools for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 277, NOW(), NOW()),
    ('ahgPortableExportPlugin', 'ahgPortableExportPluginConfiguration', '1.0.0', 'Portable export management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 278, NOW(), NOW()),
    ('ahgPreservationPlugin', 'ahgPreservationPluginConfiguration', '1.0.0', 'Digital preservation management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 279, NOW(), NOW()),
    ('ahgPrivacyPlugin', 'ahgPrivacyPluginConfiguration', '1.0.0', 'Heratio Privacy package (migrated from ahgPrivacyPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 280, NOW(), NOW()),
    ('ahgProvenancePlugin', 'ahgProvenancePluginConfiguration', '1.0.0', 'Provenance tracking and chain of custody for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 281, NOW(), NOW()),
    ('ahgProvenanceAiPlugin', 'ahgProvenanceAiPluginConfiguration', '1.0.0', 'AI inference provenance discipline for Heratio - MySQL+Fuseki dual-store, override-as-record-event, one-query defensibility (issue #61, ADR-0002)', 'ahg', 1, 0, 0, 0, 'enabled', 282, NOW(), NOW()),
    ('ahgRadManagePlugin', 'ahgRadManagePluginConfiguration', '1.0.0', 'Heratio RadManage package (migrated from ahgRadManagePlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 283, NOW(), NOW()),
    ('ahgRecordsManagePlugin', 'ahgRecordsManagePluginConfiguration', '1.0.0', 'Records Management module for Heratio - retention schedules, disposal workflows, file plan, compliance reporting. Disablable.', 'ahg', 1, 0, 0, 0, 'enabled', 284, NOW(), NOW()),
    ('ahgReportsPlugin', 'ahgReportsPluginConfiguration', '1.0.0', 'Reports dashboard for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 285, NOW(), NOW()),
    ('ahgRepositoryManagePlugin', 'ahgRepositoryManagePluginConfiguration', '1.0.0', 'Repository browse and management for Heratio (ISDIAH)', 'ahg', 1, 0, 0, 0, 'enabled', 286, NOW(), NOW()),
    ('ahgRequestPublishPlugin', 'ahgRequestPublishPluginConfiguration', '1.0.0', 'Request to Publish management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 287, NOW(), NOW()),
    ('ahgResearchPlugin', 'ahgResearchPluginConfiguration', '1.0.0', 'Research Portal: researcher registration, reading room bookings, projects, evidence sets, journal, bibliographies, reports, annotations, knowledge platform, admin management', 'ahg', 1, 0, 0, 0, 'enabled', 288, NOW(), NOW()),
    ('ahgResearcherManagePlugin', 'ahgResearcherManagePluginConfiguration', '1.0.0', 'Researcher Submissions management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 289, NOW(), NOW()),
    ('ahgResourcesyncPlugin', 'ahgResourcesyncPluginConfiguration', '1.0.0', 'ResourceSync 1.1 (sitemap-style) Source endpoint for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 290, NOW(), NOW()),
    ('ahgRicExplorerPlugin', 'ahgRicExplorerPluginConfiguration', '1.0.0', 'RiC (Records in Contexts) Dashboard for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 291, NOW(), NOW()),
    ('ahgRightsPlugin', 'ahgRightsPluginConfiguration', '1.0.0', 'AHG plugin: ahg-rights', 'ahg', 1, 0, 0, 0, 'enabled', 292, NOW(), NOW()),
    ('ahgRightsHolderManagePlugin', 'ahgRightsHolderManagePluginConfiguration', '1.0.0', 'Rights holder browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 293, NOW(), NOW()),
    ('ahgScanPlugin', 'ahgScanPluginConfiguration', '1.0.0', 'Scanner + capture entry points for Heratio (watched folders, scan API)', 'ahg', 1, 0, 0, 0, 'enabled', 294, NOW(), NOW()),
    ('ahgSearchPlugin', 'ahgSearchPluginConfiguration', '1.0.0', 'Global search with Elasticsearch for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 295, NOW(), NOW()),
    ('ahgSemanticSearchPlugin', 'ahgSemanticSearchPluginConfiguration', '1.0.0', 'Semantic search and search enhancement for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 296, NOW(), NOW()),
    ('ahgShareLinkPlugin', 'ahgShareLinkPluginConfiguration', '1.0.0', 'Time-limited, auditable share links for information_object records. Anonymous bearer-token access; HMAC-derived URL-safe tokens; classified-record gating; admin revocation; audit dual-write.', 'ahg', 1, 0, 0, 0, 'enabled', 297, NOW(), NOW()),
    ('ahgSharepointPlugin', 'ahgSharepointPluginConfiguration', '1.0.0', 'Microsoft 365 SharePoint integration for Heratio: tenant config, drive registration, manual delta sync (Phase 1); webhook records handoff (Phase 2); federated search + M365 connector feed (Phase 3).', 'ahg', 1, 0, 0, 0, 'enabled', 298, NOW(), NOW()),
    ('ahgSpectrumPlugin', 'ahgSpectrumPluginConfiguration', '1.0.0', 'Heratio Spectrum package (migrated from ahgSpectrumPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 299, NOW(), NOW()),
    ('ahgStaticPagePlugin', 'ahgStaticPagePluginConfiguration', '1.0.0', 'Static page display for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 300, NOW(), NOW()),
    ('ahgStatisticsPlugin', 'ahgStatisticsPluginConfiguration', '1.0.0', 'Usage statistics and analytics for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 301, NOW(), NOW()),
    ('ahgStorageManagePlugin', 'ahgStorageManagePluginConfiguration', '1.0.0', 'Physical storage browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 302, NOW(), NOW()),
    ('ahgTermTaxonomyPlugin', 'ahgTermTaxonomyPluginConfiguration', '1.0.0', 'Term and taxonomy browse and management for Heratio (with SKOS export + cross-vocab matches + SHACL)', 'ahg', 1, 0, 0, 0, 'enabled', 303, NOW(), NOW()),
    ('ahgTranslationPlugin', 'ahgTranslationPluginConfiguration', '1.0.0', 'UI-string translation + locale registry for Heratio (DbAwareLoader override layer)', 'ahg', 1, 0, 0, 0, 'enabled', 304, NOW(), NOW()),
    ('ahgUserManagePlugin', 'ahgUserManagePluginConfiguration', '1.0.0', 'User browse and management for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 305, NOW(), NOW()),
    ('ahgVendorPlugin', 'ahgVendorPluginConfiguration', '1.0.0', 'Heratio Vendor package (migrated from ahgVendorPlugin)', 'ahg', 1, 0, 0, 0, 'enabled', 306, NOW(), NOW()),
    ('ahgVersionControlPlugin', 'ahgVersionControlPluginConfiguration', '1.0.0', 'Version history with diff and restore for information_object and actor entities. Mirrors the AHG version-snapshot pattern used by reports, landing pages and heritage contributions.', 'ahg', 1, 0, 0, 0, 'enabled', 307, NOW(), NOW()),
    ('ahgWorkflowPlugin', 'ahgWorkflowPluginConfiguration', '1.0.0', 'Workflow management, task approval, SLA tracking, and publish gates for Heratio', 'ahg', 1, 0, 0, 0, 'enabled', 308, NOW(), NOW()),
    ('ahgZ3950Plugin', 'ahgZ3950PluginConfiguration', '1.0.0', 'Z39.50 client and server for Heratio - search and serve bibliographic records via the Z39.50 protocol (bib-1 attr set).', 'ahg', 1, 0, 0, 0, 'enabled', 309, NOW(), NOW());

-- Force-enable the locked foundation set (self-heal).
UPDATE `atom_plugin`
SET `is_enabled` = 1, `is_core` = 1, `status` = 'enabled'
WHERE `name` IN (
    'ahgCorePlugin', 'sfPropelPlugin', 'qbAclPlugin', 'sfPluginAdminPlugin',
    'ahgSettingsPlugin', 'ahgSecurityClearancePlugin', 'ahgThemeB5Plugin'
);

-- Federation ships installed but DISABLED by default (self-heal).
UPDATE `atom_plugin` SET `is_enabled` = 0, `status` = 'disabled' WHERE `name` = 'ahgFederationPlugin';
