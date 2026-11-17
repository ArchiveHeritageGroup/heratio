-- =============================================================================
-- UK FRS Region - FRS 102 Section 34 Heritage Assets
-- Countries: United Kingdom, Ireland
-- Regulatory Body: Financial Reporting Council (FRC) / Charity Commission
-- =============================================================================

-- Register the accounting standard
INSERT INTO heritage_accounting_standard
(code, name, country, region_code, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('FRS102', 'FRS 102 Section 34 Heritage Assets', 'United Kingdom', 'uk_frs',
 'Financial Reporting Standard 102 Section 34.49-56 Heritage Assets. For UK charities, museums, and cultural institutions.',
 0, '["cost", "valuation", "nominal"]',
 '["nature_of_holdings", "policy", "carrying_amount", "restrictions"]', 1, 1)
ON DUPLICATE KEY UPDATE region_code='uk_frs', description=VALUES(description);

-- Mark region as installed
UPDATE heritage_regional_config SET is_installed = 1, installed_at = NOW() WHERE region_code = 'uk_frs';

-- Get standard ID
SET @std_id = (SELECT id FROM heritage_accounting_standard WHERE code = 'FRS102');

-- Recognition Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'recognition', 'REC001', 'Asset Class Required',
 'Heritage asset must be classified',
 'required_field', 'asset_class_id', NULL,
 'Asset class is required', 'FRS 102.34.49', 'error', 1),

(@std_id, 'recognition', 'REC002', 'Significance Statement',
 'Heritage characteristics must be documented',
 'required_field', 'significance_statement', NULL,
 'Heritage characteristics/significance should be documented', 'FRS 102.34.50', 'warning', 2),

(@std_id, 'recognition', 'REC003', 'Recognition Date',
 'Date of recognition/acquisition',
 'required_field', 'recognition_date', NULL,
 'Recognition date should be recorded', 'FRS 102.34.51', 'warning', 3)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Measurement Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'measurement', 'MEA001', 'Measurement Basis',
 'Indicate if cost or valuation basis used',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis should be specified', 'FRS 102.34.52', 'warning', 10),

(@std_id, 'measurement', 'MEA002', 'Carrying Amount',
 'Carrying amount if capitalised',
 'value_check', 'current_carrying_amount', '>=0',
 'Carrying amount should be recorded if capitalised', 'FRS 102.34.52', 'warning', 11)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Disclosure Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'disclosure', 'DIS001', 'Nature of Holdings',
 'Disclose nature and scale of heritage assets',
 'required_field', 'heritage_significance', NULL,
 'Nature of holdings should be documented', 'FRS 102.34.55', 'warning', 20),

(@std_id, 'disclosure', 'DIS002', 'Preservation Policy',
 'Preservation and management policy',
 'required_field', 'conservation_requirements', NULL,
 'Preservation policy should be documented', 'FRS 102.34.55', 'info', 21),

(@std_id, 'disclosure', 'DIS003', 'Accounting Policy',
 'Disclose accounting policy adopted',
 'required_field', 'measurement_basis', NULL,
 'Accounting policy should be documented', 'FRS 102.34.55(a)', 'warning', 22),

(@std_id, 'disclosure', 'DIS004', 'Restrictions',
 'Disclose any restrictions on disposal',
 'required_field', 'restrictions_on_disposal', NULL,
 'Restrictions on disposal should be documented', 'FRS 102.34.55(c)', 'info', 23)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- UK-Specific Report Configurations
UPDATE heritage_regional_config
SET report_formats = JSON_OBJECT(
    'charity_sorp', JSON_OBJECT(
        'name', 'Charities SORP Heritage Schedule',
        'format', 'SORP FRS 102',
        'fields', JSON_ARRAY('asset_class', 'description', 'valuation_policy', 'carrying_amount')
    ),
    'trustees_report', JSON_OBJECT(
        'name', 'Trustees Report - Collections Section',
        'format', 'Charity Commission',
        'fields', JSON_ARRAY('nature_of_holdings', 'preservation_policy', 'acquisitions', 'disposals')
    )
),
config_data = JSON_OBJECT(
    'currencies', JSON_ARRAY('GBP', 'EUR'),
    'financial_year_start', '04-01',
    'valuation_methods', JSON_ARRAY('cost', 'valuation', 'nominal', 'not_capitalised'),
    'capitalisation_optional', true,
    'charities_sorp', true
)
WHERE region_code = 'uk_frs';
