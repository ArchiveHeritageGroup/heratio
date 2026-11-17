-- =============================================================================
-- SOUTH AFRICA GRAP Region - GRAP 103 Heritage Asset Accounting
-- Countries: South Africa
-- Regulatory Body: National Treasury / Accounting Standards Board (ASB)
-- =============================================================================

-- Register the accounting standard
INSERT INTO heritage_accounting_standard
(code, name, country, region_code, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('GRAP103', 'GRAP 103 Heritage Assets', 'South Africa', 'south_africa_grap',
 'Generally Recognised Accounting Practice - dedicated heritage assets standard for SA public sector. Required for all government museums, archives, and heritage institutions.',
 1, '["cost", "fair_value", "deemed_cost", "nominal"]',
 '["asset_class", "measurement_basis", "carrying_amount", "restrictions", "conservation", "significance"]', 1, 1)
ON DUPLICATE KEY UPDATE region_code='south_africa_grap', description=VALUES(description);

-- Mark region as installed
UPDATE heritage_regional_config SET is_installed = 1, installed_at = NOW() WHERE region_code = 'south_africa_grap';

-- =============================================================================
-- GRAP 103 Compliance Rules (SA National Treasury Requirements)
-- =============================================================================

-- Get standard ID
SET @std_id = (SELECT id FROM heritage_accounting_standard WHERE code = 'GRAP103');

-- Recognition Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'recognition', 'REC001', 'Asset Class Required',
 'Heritage asset must have an asset class per GRAP 103 classification',
 'required_field', 'asset_class_id', NULL,
 'Asset class is required for GRAP 103 compliance', 'GRAP 103.14', 'error', 1),

(@std_id, 'recognition', 'REC002', 'Recognition Date Required',
 'Date when asset was recognised must be recorded for audit purposes',
 'required_field', 'recognition_date', NULL,
 'Recognition date is required', 'GRAP 103.14', 'error', 2),

(@std_id, 'recognition', 'REC003', 'Significance Statement',
 'Heritage significance must be documented per National Treasury guidelines',
 'required_field', 'significance_statement', NULL,
 'Heritage significance statement is required', 'GRAP 103.74', 'warning', 3),

(@std_id, 'recognition', 'REC004', 'Recognition Status',
 'Asset must have explicit recognition status (recognised/not recognised/pending)',
 'required_field', 'recognition_status', NULL,
 'Recognition status must be specified', 'GRAP 103.14', 'error', 4),

(@std_id, 'recognition', 'REC005', 'Recognition Reason',
 'Reason for recognition status must be documented',
 'required_field', 'recognition_status_reason', NULL,
 'Recognition status reason should be documented', 'GRAP 103.15', 'warning', 5)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Measurement Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'measurement', 'MEA001', 'Measurement Basis Required',
 'Measurement basis must be specified (cost, fair value, deemed cost, or nominal)',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis is required', 'GRAP 103.26', 'error', 10),

(@std_id, 'measurement', 'MEA002', 'Carrying Amount Required',
 'Current carrying amount must be recorded and greater than zero for recognised assets',
 'value_check', 'current_carrying_amount', '>0',
 'Current carrying amount must be greater than zero', 'GRAP 103.26-28', 'error', 11),

(@std_id, 'measurement', 'MEA003', 'Acquisition Date',
 'Acquisition date should be recorded for asset tracking',
 'required_field', 'acquisition_date', NULL,
 'Acquisition date is recommended', 'GRAP 103.36', 'warning', 12),

(@std_id, 'measurement', 'MEA004', 'Acquisition Method',
 'Method of acquisition must be documented (purchase, donation, transfer)',
 'required_field', 'acquisition_method', NULL,
 'Acquisition method should be documented', 'GRAP 103.36', 'warning', 13),

(@std_id, 'measurement', 'MEA005', 'Initial Carrying Amount',
 'Initial carrying amount at recognition',
 'required_field', 'initial_carrying_amount', NULL,
 'Initial carrying amount should be recorded', 'GRAP 103.26', 'warning', 14),

(@std_id, 'measurement', 'MEA006', 'Fair Value at Acquisition',
 'Fair value at acquisition date for donated items',
 'required_field', 'fair_value_at_acquisition', NULL,
 'Fair value at acquisition should be recorded for donations', 'GRAP 103.37', 'info', 15)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Disclosure Rules
INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'disclosure', 'DIS001', 'Restrictions on Use',
 'Any restrictions on use must be disclosed per GRAP 103.74(a)',
 'required_field', 'restrictions_on_use', NULL,
 'Restrictions on use should be documented', 'GRAP 103.74(a)', 'warning', 20),

(@std_id, 'disclosure', 'DIS002', 'Conservation Requirements',
 'Conservation requirements must be disclosed per GRAP 103.74(b)',
 'required_field', 'conservation_requirements', NULL,
 'Conservation requirements should be documented', 'GRAP 103.74(b)', 'warning', 21),

(@std_id, 'disclosure', 'DIS003', 'Restrictions on Disposal',
 'Any restrictions on disposal must be disclosed',
 'required_field', 'restrictions_on_disposal', NULL,
 'Restrictions on disposal should be documented', 'GRAP 103.74(c)', 'warning', 22),

(@std_id, 'disclosure', 'DIS004', 'Donor Information',
 'Donor details for contributed/donated items',
 'required_field', 'donor_name', NULL,
 'Donor information should be recorded for donations', 'GRAP 103.74', 'info', 23),

(@std_id, 'disclosure', 'DIS005', 'Donor Restrictions',
 'Any restrictions imposed by donor',
 'required_field', 'donor_restrictions', NULL,
 'Donor restrictions should be documented', 'GRAP 103.74', 'info', 24),

(@std_id, 'disclosure', 'DIS006', 'Current Location',
 'Physical location of the asset',
 'required_field', 'current_location', NULL,
 'Asset location should be recorded', 'Best Practice', 'info', 25),

(@std_id, 'disclosure', 'DIS007', 'Condition Assessment',
 'Current condition of the heritage asset',
 'required_field', 'condition_rating', NULL,
 'Condition rating should be assessed', 'Best Practice', 'info', 26),

(@std_id, 'disclosure', 'DIS008', 'Insurance Coverage',
 'Insurance details for valuable heritage assets',
 'required_field', 'insurance_value', NULL,
 'Insurance coverage should be documented', 'National Treasury Guideline', 'warning', 27)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- =============================================================================
-- South Africa-Specific Report Configurations
-- =============================================================================

UPDATE heritage_regional_config
SET report_formats = JSON_OBJECT(
    'national_treasury', JSON_OBJECT(
        'name', 'National Treasury AFS Heritage Schedule',
        'format', 'NT AFS Template',
        'fields', JSON_ARRAY('asset_class', 'description', 'acquisition_date', 'measurement_basis', 'carrying_amount', 'restrictions')
    ),
    'asset_register', JSON_OBJECT(
        'name', 'Heritage Asset Register',
        'format', 'GRAP 103 Schedule',
        'fields', JSON_ARRAY('asset_class', 'description', 'recognition_date', 'measurement_basis', 'initial_amount', 'current_amount', 'location')
    ),
    'reconciliation', JSON_OBJECT(
        'name', 'Movement Schedule',
        'format', 'GRAP 103.74(e)',
        'fields', JSON_ARRAY('opening_balance', 'additions', 'disposals', 'revaluations', 'impairments', 'closing_balance')
    ),
    'compliance_report', JSON_OBJECT(
        'name', 'GRAP 103 Compliance Report',
        'format', 'Auditor General Format',
        'fields', JSON_ARRAY('total_assets', 'recognised', 'not_recognised', 'compliance_score', 'issues')
    )
),
config_data = JSON_OBJECT(
    'currencies', JSON_ARRAY('ZAR'),
    'financial_year_start', '04-01',
    'financial_year_end', '03-31',
    'valuation_methods', JSON_ARRAY('cost', 'fair_value', 'deemed_cost', 'nominal'),
    'depreciation_heritage', 'not_permitted',
    'capitalisation_threshold', 0,
    'revaluation_frequency_years', 5,
    'national_treasury_submission', true,
    'auditor_general_audit', true
)
WHERE region_code = 'south_africa_grap';
