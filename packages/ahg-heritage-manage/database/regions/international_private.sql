-- =============================================================================
-- International Private Sector Region - IAS 16
-- Countries: International (Private Sector Museums, Galleries)
-- Regulatory Body: International Accounting Standards Board (IASB)
-- =============================================================================

INSERT INTO heritage_accounting_standard
(code, name, country, region_code, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('IAS16', 'IAS 16 Property, Plant & Equipment', 'International (Private Sector)', 'international_private',
 'International Accounting Standard 16 for private sector museums, galleries and cultural institutions not covered by public sector standards.',
 0, '["cost", "revaluation"]',
 '["measurement_basis", "depreciation", "useful_life", "impairment"]', 1, 1)
ON DUPLICATE KEY UPDATE region_code='international_private', description=VALUES(description);

UPDATE heritage_regional_config SET is_installed = 1, installed_at = NOW() WHERE region_code = 'international_private';

SET @std_id = (SELECT id FROM heritage_accounting_standard WHERE code = 'IAS16');

INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'recognition', 'REC001', 'Asset Class Required',
 'Asset must be classified',
 'required_field', 'asset_class_id', NULL,
 'Asset class is required', 'IAS 16.7', 'error', 1),

(@std_id, 'recognition', 'REC002', 'Recognition Date',
 'Date of recognition',
 'required_field', 'recognition_date', NULL,
 'Recognition date should be recorded', 'IAS 16.7', 'warning', 2),

(@std_id, 'recognition', 'REC003', 'Future Economic Benefits',
 'Document expected benefits',
 'required_field', 'significance_statement', NULL,
 'Future benefits/significance should be documented', 'IAS 16.7(a)', 'warning', 3),

(@std_id, 'measurement', 'MEA001', 'Measurement Basis',
 'Cost or revaluation model',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis should be specified', 'IAS 16.29', 'error', 10),

(@std_id, 'measurement', 'MEA002', 'Carrying Amount',
 'Current carrying amount',
 'value_check', 'current_carrying_amount', '>=0',
 'Carrying amount should be recorded', 'IAS 16.30', 'warning', 11),

(@std_id, 'measurement', 'MEA003', 'Cost at Recognition',
 'Cost at initial recognition',
 'required_field', 'acquisition_cost', NULL,
 'Initial cost should be recorded', 'IAS 16.15', 'warning', 12),

(@std_id, 'disclosure', 'DIS001', 'Measurement Disclosure',
 'Disclose measurement basis used',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis must be disclosed', 'IAS 16.73(a)', 'warning', 20),

(@std_id, 'disclosure', 'DIS002', 'Restrictions',
 'Restrictions on title',
 'required_field', 'restrictions_on_use', NULL,
 'Restrictions should be disclosed', 'IAS 16.74(a)', 'info', 21)
ON DUPLICATE KEY UPDATE name=VALUES(name);

UPDATE heritage_regional_config
SET report_formats = JSON_OBJECT(
    'ifrs_schedule', JSON_OBJECT(
        'name', 'IFRS PPE Schedule',
        'format', 'IAS 16',
        'fields', JSON_ARRAY('asset_class', 'description', 'cost', 'revaluation', 'depreciation', 'carrying_amount')
    )
),
config_data = JSON_OBJECT(
    'currencies', JSON_ARRAY('USD', 'EUR', 'GBP', 'CHF'),
    'financial_year_options', JSON_ARRAY('01-01', '04-01', '07-01', '10-01'),
    'revaluation_model_permitted', true,
    'component_accounting', true
)
WHERE region_code = 'international_private';
