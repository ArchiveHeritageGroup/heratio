-- ahg-library dropdowns for fine payment and the cash desk (#1474).
-- metadata.is_cash marks a tender that goes into the cash drawer, so it counts
-- towards the expected cash at reconciliation. Add tenders in the Dropdown
-- Manager, which asks for this on each one. A tender with no value cannot be used.
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, sort_order, is_default, is_active, metadata) VALUES
('library_payment_method', 'Library Payment Method', 'library', 'cash', 'Cash', 10, 1, 1, '{"is_cash": true}'),
('library_payment_method', 'Library Payment Method', 'library', 'card', 'Card', 20, 0, 1, '{"is_cash": false}'),
('library_payment_method', 'Library Payment Method', 'library', 'eft', 'Electronic transfer', 30, 0, 1, '{"is_cash": false}'),
('library_payment_method', 'Library Payment Method', 'library', 'mobile', 'Mobile payment', 40, 0, 1, '{"is_cash": false}'),
('library_fine_status', 'Library Fine Status', 'library', 'outstanding', 'Outstanding', 10, 1, 1, NULL),
('library_fine_status', 'Library Fine Status', 'library', 'paid', 'Paid', 20, 0, 1, NULL),
('library_fine_status', 'Library Fine Status', 'library', 'waived', 'Waived', 30, 0, 1, NULL),
('library_cash_session_status', 'Library Cash Session Status', 'library', 'open', 'Open', 10, 1, 1, NULL),
('library_cash_session_status', 'Library Cash Session Status', 'library', 'closed', 'Closed - awaiting review', 20, 0, 1, NULL),
('library_cash_session_status', 'Library Cash Session Status', 'library', 'reconciled', 'Reconciled', 30, 0, 1, NULL);

-- Library MARC source record dropdowns (heratio#1476). Idempotent.
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, sort_order, is_default, is_active) VALUES
('library_marc_record_event', 'MARC Record Event', 'library', 'import', 'Imported', 10, 1, 1),
('library_marc_record_event', 'MARC Record Event', 'library', 'baseline', 'Baseline before first edit', 20, 0, 1),
('library_marc_record_event', 'MARC Record Event', 'library', 'edit', 'Edited', 30, 0, 1),
('library_marc_record_event', 'MARC Record Event', 'library', 'replay', 'Mapping replayed', 40, 0, 1),
('library_marc_source_format', 'MARC Source Format', 'library', 'marcxml', 'MARCXML (exact file)', 10, 1, 1),
('library_marc_source_format', 'MARC Source Format', 'library', 'marcxml_record', 'MARCXML (record from a batch file)', 20, 0, 1),
('library_marc_source_format', 'MARC Source Format', 'library', 'iso2709', 'MARC21 binary (ISO 2709)', 30, 0, 1),
('library_marc_source_format', 'MARC Source Format', 'library', 'generated', 'Generated from the catalogue', 40, 0, 1);
