INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, sort_order, is_default, is_active) VALUES
('library_ledger_doc_type','Ledger Document Type','library','invoice','Supplier invoice',10,1,1),
('library_ledger_doc_type','Ledger Document Type','library','credit_note','Supplier credit note',20,0,1),
('library_ledger_status','Ledger Document Status','library','draft','Draft',10,1,1),
('library_ledger_status','Ledger Document Status','library','posted','Posted',20,0,1),
('library_ledger_status','Ledger Document Status','library','void','Void',30,0,1),
('library_ledger_credit_reason','Credit Note Reason','library','return','Goods returned to supplier',10,1,1),
('library_ledger_credit_reason','Credit Note Reason','library','billing_correction','Billing correction (goods kept)',20,0,1),
('library_tax_rounding','Tax Rounding Rule','library','line','Round on each line',10,1,1),
('library_tax_rounding','Tax Rounding Rule','library','document','Round on the document total per rate',20,0,1);
