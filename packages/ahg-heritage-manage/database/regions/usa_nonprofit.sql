-- =============================================================================
-- USA Non-Profit Region - FASB ASC 958 Collections
-- Countries: United States (Museums, Galleries, Non-profit)
-- Regulatory Body: Financial Accounting Standards Board (FASB)
-- =============================================================================

INSERT INTO heritage_accounting_standard
(code, name, country, region_code, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('FASB958', 'FASB ASC 958 Collections', 'United States', 'usa_nonprofit',
 'Financial Accounting Standards Board ASC 958 - Not-for-profit entities collections. For museums, galleries, and cultural nonprofits.',
 0, '["cost", "fair_value", "nominal"]',
 '["collection_description", "capitalisation_policy", "stewardship"]', 1, 1)
ON DUPLICATE KEY UPDATE region_code='usa_nonprofit', description=VALUES(description);

UPDATE heritage_regional_config SET is_installed = 1, installed_at = NOW() WHERE region_code = 'usa_nonprofit';

SET @std_id = (SELECT id FROM heritage_accounting_standard WHERE code = 'FASB958');

INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'recognition', 'REC001', 'Collection Policy',
 'Document collection capitalisation policy',
 'required_field', 'asset_class_id', NULL,
 'Asset classification required', 'FASB 958-360-25', 'error', 1),

(@std_id, 'recognition', 'REC002', 'Acquisition Date',
 'Date item was acquired',
 'required_field', 'acquisition_date', NULL,
 'Acquisition date should be recorded', 'FASB 958-360-25-2', 'warning', 2),

(@std_id, 'recognition', 'REC003', 'Donor Information',
 'Donor details for contributed items',
 'required_field', 'donor_name', NULL,
 'Donor information recommended', 'FASB 958-605-25', 'info', 3),

(@std_id, 'measurement', 'MEA001', 'Fair Value',
 'Fair value at acquisition',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis should be specified', 'FASB 958-360-30-1', 'warning', 10),

(@std_id, 'disclosure', 'DIS001', 'Collection Description',
 'Description required for non-capitalised collections',
 'required_field', 'significance_statement', NULL,
 'Collection description recommended', 'FASB 958-360-50', 'warning', 20),

(@std_id, 'disclosure', 'DIS002', 'Stewardship Activities',
 'Description of stewardship activities',
 'required_field', 'conservation_requirements', NULL,
 'Stewardship activities should be documented', 'FASB 958-360-50-2', 'info', 22)
ON DUPLICATE KEY UPDATE name=VALUES(name);

UPDATE heritage_regional_config
SET report_formats = JSON_OBJECT(
    'form990', JSON_OBJECT(
        'name', 'Form 990 Schedule M Collections',
        'format', 'IRS',
        'fields', JSON_ARRAY('collection_type', 'description', 'revenue', 'expenses')
    )
),
config_data = JSON_OBJECT(
    'currencies', JSON_ARRAY('USD'),
    'capitalisation_optional', true,
    'form990_reporting', true
)
WHERE region_code = 'usa_nonprofit';
