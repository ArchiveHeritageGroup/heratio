-- =============================================================================
-- 09_library_cash_desk.sql - fine payment, receipts and cash desk (#1474)
-- =============================================================================
-- Mirrors packages/ahg-library/database/migrations/
-- 2026_11_22_100000_create_library_cash_desk_tables.php, because a fresh install
-- and the CI database load database/core/0*.sql and do not run package
-- migrations. Every statement is idempotent.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `library_cash_session` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int DEFAULT NULL,
  `currency` varchar(3) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `open_key` varchar(40) DEFAULT NULL COMMENT 'Set only while open: one open session per operator',
  `opening_float` decimal(10,2) NOT NULL DEFAULT 0.00,
  `opened_by` int NOT NULL,
  `opened_at` datetime NOT NULL,
  `cash_takings` decimal(10,2) DEFAULT NULL,
  `other_takings` decimal(10,2) DEFAULT NULL,
  `expected_cash` decimal(10,2) DEFAULT NULL,
  `counted_cash` decimal(10,2) DEFAULT NULL,
  `variance` decimal(10,2) DEFAULT NULL,
  `close_notes` text,
  `closed_by` int DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reconciled_by` int DEFAULT NULL,
  `reconciled_at` datetime DEFAULT NULL,
  `reconcile_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cash_session_open` (`open_key`),
  KEY `idx_cash_session_branch` (`branch_id`),
  KEY `idx_cash_session_status` (`status`),
  KEY `idx_cash_session_opened` (`opened_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_fine_receipt` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(30) DEFAULT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `session_id` bigint unsigned NOT NULL,
  `patron_id` bigint unsigned NOT NULL,
  `currency` varchar(3) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(30) NOT NULL,
  `is_cash` tinyint(1) NOT NULL DEFAULT 0,
  `payment_reference` varchar(100) DEFAULT NULL,
  `tendered_amount` decimal(10,2) DEFAULT NULL,
  `change_given` decimal(10,2) DEFAULT NULL,
  `issued_by` int NOT NULL,
  `issued_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fine_receipt_number` (`receipt_number`),
  UNIQUE KEY `uq_fine_receipt_idem` (`idempotency_key`),
  KEY `idx_fine_receipt_session` (`session_id`),
  KEY `idx_fine_receipt_patron` (`patron_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_fine_receipt_line` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt_id` bigint unsigned NOT NULL,
  `fine_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fine_line_receipt` (`receipt_id`),
  KEY `idx_fine_line_fine` (`fine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_fine_waiver` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fine_id` bigint unsigned NOT NULL,
  `patron_id` bigint unsigned NOT NULL,
  `branch_id` int DEFAULT NULL,
  `currency` varchar(3) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `waived_by` int NOT NULL,
  `waived_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fine_waiver_fine` (`fine_id`),
  KEY `idx_fine_waiver_branch` (`branch_id`),
  KEY `idx_fine_waiver_at` (`waived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
