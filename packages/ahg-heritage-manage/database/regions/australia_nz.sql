-- =============================================================================
-- Australia & New Zealand Region - AASB 116 / PBE IPSAS 17
-- Countries: Australia, New Zealand
-- Regulatory Body: AASB (Australia) / XRB (New Zealand)
-- =============================================================================

INSERT INTO heritage_accounting_standard
(code, name, country, region_code, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('AASB116', 'AASB 116 / PBE IPSAS 17', 'Australia / New Zealand', 'australia_nz',
 'Australian Accounting Standards Board 116 Property, Plant & Equipment including heritage assets. Based on IPSAS. Also covers NZ PBE IPSAS 17.',
 0, '["cost", "revaluation", "fair_value"]',
 '["measurement_basis", "depreciation_method", "useful_life", "reconciliation"]', 1, 1)
ON DUPLICATE KEY UPDATE region_code='australia_nz', description=VALUES(description);

UPDATE heritage_regional_config SET is_installed = 1, installed_at = NOW() WHERE region_code = 'australia_nz';

SET @std_id = (SELECT id FROM heritage_accounting_standard WHERE code = 'AASB116');

INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'recognition', 'REC001', 'Asset Class Required',
 'Asset must be classified',
 'required_field', 'asset_class_id', NULL,
 'Asset class is required', 'AASB 116.7', 'error', 1),

(@std_id, 'recognition', 'REC002', 'Recognition Date',
 'Date of recognition',
 'required_field', 'recognition_date', NULL,
 'Recognition date should be recorded', 'AASB 116.7', 'warning', 2),

(@std_id, 'recognition', 'REC003', 'Heritage Significance',
 'Cultural significance',
 'required_field', 'significance_statement', NULL,
 'Heritage significance should be documented', 'AASB 116 Aus7.1', 'warning', 3),

(@std_id, 'measurement', 'MEA001', 'Measurement Basis',
 'Cost or revaluation model',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis should be specified', 'AASB 116.29', 'warning', 10),

(@std_id, 'measurement', 'MEA002', 'Carrying Amount',
 'Current carrying amount',
 'value_check', 'current_carrying_amount', '>=0',
 'Carrying amount should be recorded', 'AASB 116.30', 'warning', 11),

(@std_id, 'disclosure', 'DIS001', 'Measurement Disclosure',
 'Disclose measurement basis',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis must be disclosed', 'AASB 116.73', 'warning', 20),

(@std_id, 'disclosure', 'DIS002', 'Conservation',
 'Conservation policy',
 'required_field', 'conservation_requirements', NULL,
 'Conservation policy should be documented', 'AASB 116 Aus73.1', 'info', 22)
ON DUPLICATE KEY UPDATE name=VALUES(name);

UPDATE heritage_regional_config
SET report_formats = JSON_OBJECT(
    'aas_schedule', JSON_OBJECT(
        'name', 'AAS Property Schedule',
        'format', 'AASB',
        'fields', JSON_ARRAY('asset_class', 'description', 'measurement_basis', 'carrying_amount', 'revaluation_date')
    )
),
config_data = JSON_OBJECT(
    'currencies', JSON_ARRAY('AUD', 'NZD'),
    'financial_year_start', '07-01',
    'revaluation_model_permitted', true,
    'fair_value_hierarchy', true
)
WHERE region_code = 'australia_nz';
