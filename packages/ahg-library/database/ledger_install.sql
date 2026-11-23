-- Library invoicing sub-ledger (heratio#1475 item 5). Idempotent.
-- Status, type and rounding values come from ahg_dropdown; no ENUM columns.

CREATE TABLE IF NOT EXISTS library_tax_rate (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tax_code VARCHAR(30) NOT NULL,
  label VARCHAR(120) NOT NULL,
  rate_percent DECIMAL(7,4) NOT NULL,
  valid_from DATE NOT NULL,
  valid_to DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uk_ltr_code_from (tax_code, valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_ledger_sequence (
  seq_key VARCHAR(40) NOT NULL PRIMARY KEY,
  current_value BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_ledger_document (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doc_type VARCHAR(30) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  document_number VARCHAR(60) NULL,
  supplier_reference VARCHAR(100) NOT NULL,
  dedupe_key CHAR(40) NULL,
  vendor_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  credited_document_id BIGINT UNSIGNED NULL,
  tax_provider VARCHAR(60) NULL,
  document_date DATE NOT NULL,
  due_date DATE NULL,
  currency VARCHAR(3) NOT NULL,
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  variance_reason VARCHAR(255) NULL,
  notes TEXT NULL,
  posted_at DATETIME NULL,
  posted_by INT UNSIGNED NULL,
  voided_at DATETIME NULL,
  voided_by INT UNSIGNED NULL,
  void_reason VARCHAR(255) NULL,
  export_batch VARCHAR(40) NULL,
  exported_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uk_lld_number (document_number),
  UNIQUE KEY uk_lld_dedupe (dedupe_key),
  KEY idx_lld_status (status, doc_type),
  KEY idx_lld_vendor (vendor_id),
  KEY idx_lld_order (order_id),
  KEY idx_lld_credited (credited_document_id),
  KEY idx_lld_batch (export_batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_ledger_line (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  order_line_id BIGINT UNSIGNED NULL,
  credited_line_id BIGINT UNSIGNED NULL,
  description VARCHAR(500) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_code VARCHAR(30) NOT NULL,
  tax_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  gross_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  KEY idx_lll_document (document_id),
  KEY idx_lll_order_line (order_line_id),
  KEY idx_lll_credited (credited_line_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_ledger_payment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  reference VARCHAR(100) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  reversed_at DATETIME NULL,
  reversed_by INT UNSIGNED NULL,
  KEY idx_llp_document (document_id, payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Why a credit note was raised (code from ahg_dropdown library_ledger_credit_reason).
CREATE TABLE IF NOT EXISTS library_ledger_credit_reason (
  document_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  reason_code VARCHAR(30) NOT NULL,
  KEY idx_llcr_reason (reason_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
