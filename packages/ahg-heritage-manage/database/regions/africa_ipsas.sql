-- =============================================================================
-- AFRICA IPSAS Region - IPSAS 45 Heritage Asset Accounting
-- Countries: Zimbabwe, Kenya, Nigeria, Ghana, Tanzania, Uganda, Rwanda, Botswana, Zambia, Malawi
-- =============================================================================

-- Register the accounting standard
INSERT INTO heritage_accounting_standard
(code, name, country, region_code, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('IPSAS45', 'IPSAS 45 Property, Plant & Equipment', 'International (Africa)', 'africa_ipsas',
 'International Public Sector Accounting Standard for heritage assets. Used by Zimbabwe, Kenya, Nigeria, Ghana, Tanzania, Uganda, Rwanda, Botswana.',
 0, '["cost", "fair_value", "deemed_cost", "nominal"]',
 '["asset_class", "measurement_basis", "useful_life", "depreciation_method", "reconciliation"]', 1, 1)
ON DUPLICATE KEY UPDATE region_code='africa_ipsas', description=VALUES(description);

-- Mark region as installed
UPDATE heritage_regional_config SET is_installed = 1, installed_at = NOW() WHERE region_code = 'africa_ipsas';

-- =============================================================================
-- IPSAS 45 Compliance Rules
-- =============================================================================

-- Get standard ID
SET @std_id = (SELECT id FROM heritage_accounting_standard WHERE code = 'IPSAS45');

-- Recognition Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'recognition', 'REC001', 'Asset Class Required',
 'Heritage asset must be classified according to IPSAS categories',
 'required_field', 'asset_class_id', NULL,
 'Asset class is required for IPSAS 45 compliance', 'IPSAS 45.14', 'error', 1),

(@std_id, 'recognition', 'REC002', 'Recognition Date Required',
 'Date when asset was recognised in financial statements',
 'required_field', 'recognition_date', NULL,
 'Recognition date is required', 'IPSAS 45.14', 'error', 2),

(@std_id, 'recognition', 'REC003', 'Heritage Significance',
 'Documentation of heritage characteristics and significance',
 'required_field', 'significance_statement', NULL,
 'Heritage significance should be documented', 'IPSAS 45.5', 'warning', 3),

(@std_id, 'recognition', 'REC004', 'Recognition Status',
 'Asset must have clear recognition status',
 'required_field', 'recognition_status', NULL,
 'Recognition status must be specified', 'IPSAS 45.14', 'error', 4)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Measurement Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'measurement', 'MEA001', 'Measurement Basis Required',
 'Measurement model (cost or revaluation) must be specified',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis must be specified', 'IPSAS 45.43', 'error', 10),

(@std_id, 'measurement', 'MEA002', 'Carrying Amount Required',
 'Current carrying amount must be recorded for recognised assets',
 'value_check', 'current_carrying_amount', '>=0',
 'Carrying amount must be recorded', 'IPSAS 45.43', 'error', 11),

(@std_id, 'measurement', 'MEA003', 'Acquisition Date',
 'Date of acquisition should be recorded',
 'required_field', 'acquisition_date', NULL,
 'Acquisition date is recommended', 'IPSAS 45.26', 'warning', 12),

(@std_id, 'measurement', 'MEA004', 'Acquisition Method',
 'How the asset was acquired (purchase, donation, transfer)',
 'required_field', 'acquisition_method', NULL,
 'Acquisition method should be documented', 'IPSAS 45.26', 'warning', 13),

(@std_id, 'measurement', 'MEA005', 'Currency Specified',
 'Currency must be specified for monetary values',
 'required_field', 'acquisition_currency', NULL,
 'Currency should be specified', 'IPSAS 45', 'info', 14)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Disclosure Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'disclosure', 'DIS001', 'Measurement Basis Disclosure',
 'Disclose measurement basis used for each class of heritage asset',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis must be disclosed', 'IPSAS 45.88', 'warning', 20),

(@std_id, 'disclosure', 'DIS002', 'Restrictions on Disposal',
 'Disclose any restrictions on disposal of heritage assets',
 'required_field', 'restrictions_on_disposal', NULL,
 'Restrictions on disposal should be disclosed', 'IPSAS 45.88', 'info', 21),

(@std_id, 'disclosure', 'DIS003', 'Conservation Requirements',
 'Disclose conservation and preservation requirements',
 'required_field', 'conservation_requirements', NULL,
 'Conservation requirements should be documented', 'IPSAS 45.88', 'info', 22),

(@std_id, 'disclosure', 'DIS004', 'Restrictions on Use',
 'Disclose any restrictions on use of heritage assets',
 'required_field', 'restrictions_on_use', NULL,
 'Restrictions on use should be disclosed', 'IPSAS 45.88', 'info', 23),

(@std_id, 'disclosure', 'DIS005', 'Insurance Coverage',
 'Insurance coverage should be documented for valuable assets',
 'required_field', 'insurance_value', NULL,
 'Insurance coverage should be documented', 'Best Practice', 'info', 24)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- =============================================================================
-- Africa-Specific Report Configurations
-- =============================================================================

UPDATE heritage_regional_config
SET report_formats = JSON_OBJECT(
    'asset_register', JSON_OBJECT(
        'name', 'Heritage Asset Register',
        'format', 'IPSAS Schedule',
        'fields', JSON_ARRAY('asset_class', 'description', 'acquisition_date', 'measurement_basis', 'carrying_amount', 'location')
    ),
    'reconciliation', JSON_OBJECT(
        'name', 'Asset Reconciliation Statement',
        'format', 'IPSAS 45.88(e)',
        'fields', JSON_ARRAY('opening_balance', 'additions', 'disposals', 'revaluations', 'impairments', 'closing_balance')
    ),
    'valuation_summary', JSON_OBJECT(
        'name', 'Valuation Summary Report',
        'format', 'Auditor General Format',
        'fields', JSON_ARRAY('asset_class', 'count', 'total_cost', 'total_fair_value', 'total_carrying_amount')
    )
),
config_data = JSON_OBJECT(
    'currencies', JSON_ARRAY('USD', 'ZWL', 'KES', 'NGN', 'GHS', 'TZS', 'UGX', 'RWF', 'BWP', 'ZMW', 'MWK'),
    'financial_year_options', JSON_ARRAY('01-01', '04-01', '07-01'),
    'valuation_methods', JSON_ARRAY('cost', 'fair_value', 'deemed_cost', 'nominal'),
    'depreciation_heritage', 'optional'
)
WHERE region_code = 'africa_ipsas';
