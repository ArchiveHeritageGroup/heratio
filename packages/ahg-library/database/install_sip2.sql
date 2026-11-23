-- SIP2 self-check - terminal accounts, idempotent fee payments and patron PINs (#1475).
CREATE TABLE IF NOT EXISTS library_sip2_account (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  institution_id VARCHAR(64) NOT NULL,
  branch_id INT NULL COMMENT 'repository.id - the branch this terminal stands in',
  user_id INT UNSIGNED NULL COMMENT 'Staff user recorded on its transactions',
  require_patron_pin TINYINT(1) NOT NULL DEFAULT 1,
  can_checkout TINYINT(1) NOT NULL DEFAULT 1,
  can_checkin TINYINT(1) NOT NULL DEFAULT 1,
  can_block TINYINT(1) NOT NULL DEFAULT 0,
  can_pay TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_sip2_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_sip2_payment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  transaction_id VARCHAR(100) NOT NULL,
  patron_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) NOT NULL,
  payment_type VARCHAR(2) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_sip2_txn (account_id, transaction_id),
  KEY idx_sip2_payment_patron (patron_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_patron_pin (
  patron_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  pin_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
