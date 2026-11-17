-- =============================================================================
-- USA Government Region - GASB 34 Heritage Assets
-- Countries: United States (Government/Public Sector)
-- Regulatory Body: Governmental Accounting Standards Board (GASB)
-- =============================================================================

INSERT INTO heritage_accounting_standard
(code, name, country, region_code, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('GASB34', 'GASB Statement 34', 'United States', 'usa_government',
 'Governmental Accounting Standards Board Statement 34 - Basic Financial Statements for State and Local Governments. Covers infrastructure and collections.',
 0, '["cost", "fair_value"]',
 '["collection_description", "capitalisation_policy"]', 1, 1)
ON DUPLICATE KEY UPDATE region_code='usa_government', description=VALUES(description);

UPDATE heritage_regional_config SET is_installed = 1, installed_at = NOW() WHERE region_code = 'usa_government';

SET @std_id = (SELECT id FROM heritage_accounting_standard WHERE code = 'GASB34');

-- Recognition Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'recognition', 'REC001', 'Collection Designation',
 'Must indicate if part of collection',
 'required_field', 'asset_class_id', NULL,
 'Asset class/collection designation required', 'GASB 34.27', 'error', 1),

(@std_id, 'recognition', 'REC002', 'Acquisition Date',
 'Date asset was acquired',
 'required_field', 'acquisition_date', NULL,
 'Acquisition date should be recorded', 'GASB 34.18', 'warning', 2),

(@std_id, 'recognition', 'REC003', 'Acquisition Method',
 'How asset was acquired',
 'required_field', 'acquisition_method', NULL,
 'Acquisition method should be documented', 'GASB 34.18', 'warning', 3)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Measurement Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'measurement', 'MEA001', 'Historical Cost',
 'Historical cost if capitalised',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis should be specified', 'GASB 34.18', 'warning', 10),

(@std_id, 'measurement', 'MEA002', 'Carrying Amount',
 'Current carrying amount',
 'value_check', 'current_carrying_amount', '>=0',
 'Carrying amount should be recorded', 'GASB 34.19', 'warning', 11)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Disclosure Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'disclosure', 'DIS001', 'Collection Description',
 'Description of collection if not capitalised',
 'required_field', 'significance_statement', NULL,
 'Collection description recommended', 'GASB 34.118', 'info', 20),

(@std_id, 'disclosure', 'DIS002', 'Collection Criteria',
 'Criteria for adding to collection',
 'required_field', 'heritage_significance', NULL,
 'Collection criteria should be documented', 'GASB 34.27', 'info', 21),

(@std_id, 'disclosure', 'DIS003', 'Conservation Policy',
 'Policy for preservation',
 'required_field', 'conservation_requirements', NULL,
 'Conservation policy should be documented', 'GASB 34.27', 'info', 22)
ON DUPLICATE KEY UPDATE name=VALUES(name);

UPDATE heritage_regional_config
SET report_formats = JSON_OBJECT(
    'cafr', JSON_OBJECT(
        'name', 'CAFR Capital Assets Schedule',
        'format', 'GASB 34',
        'fields', JSON_ARRAY('asset_class', 'description', 'cost', 'accumulated_depreciation', 'net_value')
    )
),
config_data = JSON_OBJECT(
    'currencies', JSON_ARRAY('USD'),
    'financial_year_options', JSON_ARRAY('01-01', '07-01', '10-01'),
    'capitalisation_threshold', 5000,
    'modified_approach_eligible', true
)
WHERE region_code = 'usa_government';
