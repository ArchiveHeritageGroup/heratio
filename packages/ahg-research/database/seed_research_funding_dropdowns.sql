-- heratio#1222 - Research OS: Research Funding tracker - dropdown seed.
--
-- INSERT IGNORE keyed on (taxonomy, code) is idempotent; a site that hand-edits
-- a value keeps its edits on re-run. Three taxonomies, all surfaced in the
-- Dropdown Manager and all VARCHAR-backed (never ENUM, never a hardcoded
-- <option> list in a view):
--
--   research_funder_type    - the kind of funding body.
--   research_funding_status - the lifecycle status of the funding line.
--   research_currency       - ISO 4217 currency codes. A seed of COMMON codes
--                             across regions; NO single currency is canonical or
--                             default. An administrator can add any other code.
--
-- International and jurisdiction-neutral: no value here assumes any one country,
-- funder or currency. is_default is 0 for every currency on purpose - the system
-- must never lead the researcher to one country's money.

-- ---------------------------------------------------------------------------
-- Funder type
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_funder_type', 'Research Funder Type', 'research', 'government',       'Government',            'primary',   'landmark',           10, 0, 1, NOW()),
('research_funder_type', 'Research Funder Type', 'research', 'research_council', 'Research council',      'info',      'university',         20, 0, 1, NOW()),
('research_funder_type', 'Research Funder Type', 'research', 'foundation',       'Foundation',            'success',   'hand-holding-heart', 30, 0, 1, NOW()),
('research_funder_type', 'Research Funder Type', 'research', 'charity',          'Charity / non-profit',  'warning',   'ribbon',             40, 0, 1, NOW()),
('research_funder_type', 'Research Funder Type', 'research', 'industry',         'Industry / commercial', 'dark',      'industry',           50, 0, 1, NOW()),
('research_funder_type', 'Research Funder Type', 'research', 'internal',         'Internal / institutional', 'secondary', 'building',        60, 0, 1, NOW()),
('research_funder_type', 'Research Funder Type', 'research', 'other',            'Other',                 'secondary', 'asterisk',           70, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Funding status
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_funding_status', 'Research Funding Status', 'research', 'applied',   'Applied',   'warning',   'paper-plane',   10, 1, 1, NOW()),
('research_funding_status', 'Research Funding Status', 'research', 'awarded',   'Awarded',   'success',   'award',         20, 0, 1, NOW()),
('research_funding_status', 'Research Funding Status', 'research', 'active',    'Active',    'primary',   'play-circle',   30, 0, 1, NOW()),
('research_funding_status', 'Research Funding Status', 'research', 'completed', 'Completed', 'secondary', 'check-circle',  40, 0, 1, NOW()),
('research_funding_status', 'Research Funding Status', 'research', 'declined',  'Declined',  'danger',    'times-circle',  50, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Currency (ISO 4217) - common codes across regions; NO default currency.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_currency', 'Research Currency (ISO 4217)', 'research', 'USD', 'USD - US Dollar',          'secondary', 'coins', 10, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'EUR', 'EUR - Euro',               'secondary', 'coins', 20, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'GBP', 'GBP - Pound Sterling',     'secondary', 'coins', 30, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'ZAR', 'ZAR - South African Rand', 'secondary', 'coins', 40, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'AUD', 'AUD - Australian Dollar',  'secondary', 'coins', 50, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'CAD', 'CAD - Canadian Dollar',    'secondary', 'coins', 60, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'JPY', 'JPY - Japanese Yen',       'secondary', 'coins', 70, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'CHF', 'CHF - Swiss Franc',        'secondary', 'coins', 80, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'CNY', 'CNY - Chinese Yuan',       'secondary', 'coins', 90, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'INR', 'INR - Indian Rupee',       'secondary', 'coins', 100, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'BRL', 'BRL - Brazilian Real',     'secondary', 'coins', 110, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'NZD', 'NZD - New Zealand Dollar', 'secondary', 'coins', 120, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'SEK', 'SEK - Swedish Krona',      'secondary', 'coins', 130, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'NOK', 'NOK - Norwegian Krone',    'secondary', 'coins', 140, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'SGD', 'SGD - Singapore Dollar',   'secondary', 'coins', 150, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'KES', 'KES - Kenyan Shilling',    'secondary', 'coins', 160, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'NGN', 'NGN - Nigerian Naira',     'secondary', 'coins', 170, 0, 1, NOW()),
('research_currency', 'Research Currency (ISO 4217)', 'research', 'AED', 'AED - UAE Dirham',         'secondary', 'coins', 180, 0, 1, NOW());
