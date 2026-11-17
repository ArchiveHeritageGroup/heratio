-- =============================================================================
-- Canada Region - PSAS 3150 Tangible Capital Assets
-- Countries: Canada
-- Regulatory Body: Public Sector Accounting Board (PSAB)
-- =============================================================================

INSERT INTO heritage_accounting_standard
(code, name, country, region_code, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('PSAS3150', 'PSAS 3150 Tangible Capital Assets', 'Canada', 'canada_psas',
 'Public Sector Accounting Standard 3150 - Tangible Capital Assets including heritage and works of art.',
 0, '["cost", "deemed_cost"]',
 '["measurement_basis", "useful_life", "restrictions"]', 1, 1)
ON DUPLICATE KEY UPDATE region_code='canada_psas', description=VALUES(description);

UPDATE heritage_regional_config SET is_installed = 1, installed_at = NOW() WHERE region_code = 'canada_psas';

SET @std_id = (SELECT id FROM heritage_accounting_standard WHERE code = 'PSAS3150');

INSERT INTO heritage_compliance_rule
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
VALUES
(@std_id, 'recognition', 'REC001', 'Asset Classification',
 'Tangible capital asset must be classified',
 'required_field', 'asset_class_id', NULL,
 'Asset class is required', 'PS 3150.08', 'error', 1),

(@std_id, 'recognition', 'REC002', 'Recognition Date',
 'Date of recognition',
 'required_field', 'recognition_date', NULL,
 'Recognition date should be recorded', 'PS 3150.10', 'warning', 2),

(@std_id, 'measurement', 'MEA001', 'Historical Cost',
 'Record at historical cost where determinable',
 'required_field', 'measurement_basis', NULL,
 'Measurement basis should be specified', 'PS 3150.15', 'warning', 10),

(@std_id, 'measurement', 'MEA002', 'Carrying Amount',
 'Net book value',
 'value_check', 'current_carrying_amount', '>=0',
 'Carrying amount should be recorded', 'PS 3150.15', 'warning', 11),

(@std_id, 'disclosure', 'DIS001', 'Heritage Disclosure',
 'Disclose heritage assets if not recognised',
 'required_field', 'significance_statement', NULL,
 'Heritage significance should be documented', 'PS 3150.42', 'info', 20),

(@std_id, 'disclosure', 'DIS002', 'Restrictions',
 'Any restrictions on use or disposal',
 'required_field', 'restrictions_on_use', NULL,
 'Restrictions should be disclosed', 'PS 3150.42', 'info', 22)
ON DUPLICATE KEY UPDATE name=VALUES(name);

UPDATE heritage_regional_config
SET report_formats = JSON_OBJECT(
    'public_accounts', JSON_OBJECT(
        'name', 'Public Accounts TCA Schedule',
        'format', 'PSAB',
        'fields', JSON_ARRAY('asset_class', 'description', 'cost', 'accumulated_amortization', 'net_book_value')
    )
),
config_data = JSON_OBJECT(
    'currencies', JSON_ARRAY('CAD'),
    'financial_year_start', '04-01',
    'amortization_term', 'useful_life',
    'works_of_art_exempt', true
)
WHERE region_code = 'canada_psas';
