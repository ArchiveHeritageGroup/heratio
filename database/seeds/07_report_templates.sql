-- ============================================================================
-- Heratio standalone install — system report templates
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgReportBuilderPlugin/database/seed_templates.sql
-- on 2026-04-30. Phase 2 of the standalone install plan.
-- Idempotent — every INSERT uses INSERT IGNORE.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ahgReportBuilderPlugin - Pre-built Report Templates
-- These are system-level templates for common GLAM reporting needs

INSERT IGNORE INTO report_template (name, description, category, scope, structure, is_active, created_at) VALUES

-- NARSSA Annual Report Template
('NARSSA Annual Report', 'Standard annual report template for National Archives and Records Service compliance. Includes executive summary, statistical overview, accession summary, preservation status, and compliance checklist.', 'narssa', 'system', JSON_OBJECT(
    'sections', JSON_ARRAY(
        JSON_OBJECT('section_type', 'narrative', 'title', 'Executive Summary', 'content', '<p>Provide an overview of the reporting period including key achievements, challenges, and strategic objectives.</p>', 'position', 0),
        JSON_OBJECT('section_type', 'summary_card', 'title', 'Key Statistics', 'config', JSON_OBJECT('cards', JSON_ARRAY(
            JSON_OBJECT('label', 'Total Holdings', 'source', 'count:information_object'),
            JSON_OBJECT('label', 'New Accessions', 'source', 'count:accession'),
            JSON_OBJECT('label', 'Digital Objects', 'source', 'count:digital_object'),
            JSON_OBJECT('label', 'Repositories', 'source', 'count:repository')
        )), 'position', 1),
        JSON_OBJECT('section_type', 'chart', 'title', 'Accessions by Repository', 'config', JSON_OBJECT('chartType', 'bar', 'groupBy', 'repository_id', 'aggregate', 'count', 'dataSource', 'accession'), 'position', 2),
        JSON_OBJECT('section_type', 'table', 'title', 'Accession Register', 'config', JSON_OBJECT('dataSource', 'accession', 'columns', JSON_ARRAY('identifier', 'title', 'date', 'scope_and_content')), 'position', 3),
        JSON_OBJECT('section_type', 'narrative', 'title', 'Preservation Report', 'content', '<p>Summarize preservation activities including digitization, conservation treatments, and environmental monitoring results.</p>', 'position', 4),
        JSON_OBJECT('section_type', 'narrative', 'title', 'Compliance Statement', 'content', '<p>Detail compliance with NARSSA regulations, National Archives Act requirements, and any audit findings.</p>', 'position', 5),
        JSON_OBJECT('section_type', 'links', 'title', 'Reference Documents', 'position', 6)
    ),
    'data_source', 'accession',
    'cover_config', JSON_OBJECT('showDate', true, 'showStats', true)
), 1, NOW()),

-- GRAP 103 Heritage Asset Report
('GRAP 103 Heritage Asset Report', 'Heritage asset valuation and disclosure report template aligned with GRAP 103 / IPSAS 45 standards. Includes asset register, valuation summary, and disclosure notes.', 'grap103', 'system', JSON_OBJECT(
    'sections', JSON_ARRAY(
        JSON_OBJECT('section_type', 'narrative', 'title', 'Introduction', 'content', '<p>This report presents the heritage asset register and valuation in accordance with GRAP 103: Heritage Assets / IPSAS 45.</p>', 'position', 0),
        JSON_OBJECT('section_type', 'summary_card', 'title', 'Asset Overview', 'config', JSON_OBJECT('cards', JSON_ARRAY(
            JSON_OBJECT('label', 'Total Heritage Assets', 'source', 'count:information_object'),
            JSON_OBJECT('label', 'Valued Assets', 'source', 'custom'),
            JSON_OBJECT('label', 'Unvalued Assets', 'source', 'custom')
        )), 'position', 1),
        JSON_OBJECT('section_type', 'table', 'title', 'Heritage Asset Register', 'config', JSON_OBJECT('dataSource', 'information_object', 'columns', JSON_ARRAY('identifier', 'title', 'level_of_description_id', 'repository_id')), 'position', 2),
        JSON_OBJECT('section_type', 'chart', 'title', 'Assets by Level of Description', 'config', JSON_OBJECT('chartType', 'pie', 'groupBy', 'level_of_description_id', 'aggregate', 'count'), 'position', 3),
        JSON_OBJECT('section_type', 'narrative', 'title', 'Valuation Methodology', 'content', '<p>Describe the valuation methodology applied, including basis of measurement and any expert valuations obtained.</p>', 'position', 4),
        JSON_OBJECT('section_type', 'narrative', 'title', 'Disclosure Notes', 'content', '<p>Include required GRAP 103 disclosures: classes of heritage assets, measurement basis, carrying amounts, and impairment losses.</p>', 'position', 5)
    ),
    'data_source', 'information_object',
    'cover_config', JSON_OBJECT('showDate', true, 'showStats', true)
), 1, NOW()),

-- Accession Summary Report
('Accession Summary Report', 'Overview of accession activities with trends, statistics, and donor analysis.', 'accession', 'system', JSON_OBJECT(
    'sections', JSON_ARRAY(
        JSON_OBJECT('section_type', 'narrative', 'title', 'Accession Overview', 'content', '<p>Summary of accession activities for the reporting period.</p>', 'position', 0),
        JSON_OBJECT('section_type', 'summary_card', 'title', 'Statistics', 'config', JSON_OBJECT('cards', JSON_ARRAY(
            JSON_OBJECT('label', 'Total Accessions', 'source', 'count:accession'),
            JSON_OBJECT('label', 'This Year', 'source', 'custom'),
            JSON_OBJECT('label', 'Pending Processing', 'source', 'custom')
        )), 'position', 1),
        JSON_OBJECT('section_type', 'chart', 'title', 'Accessions Over Time', 'config', JSON_OBJECT('chartType', 'line', 'groupBy', 'date', 'aggregate', 'count', 'dataSource', 'accession'), 'position', 2),
        JSON_OBJECT('section_type', 'table', 'title', 'Recent Accessions', 'config', JSON_OBJECT('dataSource', 'accession', 'columns', JSON_ARRAY('identifier', 'title', 'date', 'source_of_acquisition')), 'position', 3)
    ),
    'data_source', 'accession',
    'cover_config', JSON_OBJECT('showDate', true)
), 1, NOW()),

-- Condition Assessment Report
('Condition Assessment Report', 'Template for documenting the physical condition of collection items. Suitable for archives, libraries, museums, and galleries.', 'condition', 'system', JSON_OBJECT(
    'sections', JSON_ARRAY(
        JSON_OBJECT('section_type', 'narrative', 'title', 'Assessment Overview', 'content', '<p>Document the scope, methodology, and findings of the condition assessment survey.</p>', 'position', 0),
        JSON_OBJECT('section_type', 'summary_card', 'title', 'Condition Summary', 'config', JSON_OBJECT('cards', JSON_ARRAY(
            JSON_OBJECT('label', 'Items Assessed', 'source', 'custom'),
            JSON_OBJECT('label', 'Good Condition', 'source', 'custom'),
            JSON_OBJECT('label', 'Fair Condition', 'source', 'custom'),
            JSON_OBJECT('label', 'Poor Condition', 'source', 'custom')
        )), 'position', 1),
        JSON_OBJECT('section_type', 'chart', 'title', 'Condition Distribution', 'config', JSON_OBJECT('chartType', 'doughnut', 'groupBy', 'condition_rating', 'aggregate', 'count'), 'position', 2),
        JSON_OBJECT('section_type', 'narrative', 'title', 'Methodology', 'content', '<p>Describe the assessment methodology, rating scale, and any standards applied (e.g., Spectrum 5.0 Condition Check procedure).</p>', 'position', 3),
        JSON_OBJECT('section_type', 'image_gallery', 'title', 'Condition Photos', 'config', JSON_OBJECT(), 'position', 4),
        JSON_OBJECT('section_type', 'narrative', 'title', 'Recommendations', 'content', '<p>List prioritized conservation treatment recommendations and preventive measures.</p>', 'position', 5)
    ),
    'data_source', 'information_object',
    'cover_config', JSON_OBJECT('showDate', true)
), 1, NOW());

SET FOREIGN_KEY_CHECKS = 1;
