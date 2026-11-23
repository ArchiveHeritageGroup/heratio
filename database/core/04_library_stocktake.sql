-- =============================================================================
-- 04_library_stocktake.sql - physical stocktake (#1474 item 5)
-- =============================================================================
-- Mirrors packages/ahg-library/database/migrations/
-- 2026_11_22_100000_create_library_stocktake_tables.php for fresh installs and
-- the CI database, which load database/core/0*.sql and never run migrations.

CREATE TABLE IF NOT EXISTS `library_stocktake` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `branch_id` INT NULL,
  `shelf_from` VARCHAR(100) NULL,
  `shelf_to` VARCHAR(100) NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'open',
  `created_by` INT UNSIGNED NULL,
  `started_at` DATETIME NOT NULL,
  `reconciled_at` DATETIME NULL,
  `signed_off_by` INT UNSIGNED NULL,
  `signed_off_at` DATETIME NULL,
  `sign_off_note` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stocktake_branch` (`branch_id`),
  KEY `idx_stocktake_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_stocktake_scan` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stocktake_id` BIGINT UNSIGNED NOT NULL,
  `barcode` VARCHAR(50) NOT NULL,
  `scanned_shelf` VARCHAR(100) NULL,
  `scan_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `scanned_by` INT UNSIGNED NULL,
  `scanned_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stocktake_scan` (`stocktake_id`, `barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_stocktake_line` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stocktake_id` BIGINT UNSIGNED NOT NULL,
  `copy_id` BIGINT UNSIGNED NULL,
  `barcode` VARCHAR(50) NULL,
  `library_item_id` BIGINT UNSIGNED NULL,
  `information_object_id` INT UNSIGNED NULL,
  `copy_status` VARCHAR(30) NULL,
  `home_shelf` VARCHAR(100) NULL,
  `scanned_shelf` VARCHAR(100) NULL,
  `classification` VARCHAR(30) NOT NULL,
  `reason_code` VARCHAR(40) NULL,
  `heritage_asset_id` INT UNSIGNED NULL,
  `carrying_snapshot` DECIMAL(18,2) NULL,
  `proposed_amount` DECIMAL(18,2) NULL,
  `impairment_amount` DECIMAL(18,2) NULL,
  `oci_movement_ids` VARCHAR(255) NULL,
  `posted_by` INT UNSIGNED NULL,
  `posted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stocktake_line_class` (`stocktake_id`, `classification`),
  KEY `idx_stocktake_line_asset` (`heritage_asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_stocktake_posting` (
  `copy_id` BIGINT UNSIGNED NOT NULL,
  `line_id` BIGINT UNSIGNED NOT NULL,
  `stocktake_id` BIGINT UNSIGNED NOT NULL,
  `posted_by` INT UNSIGNED NULL,
  `posted_at` DATETIME NOT NULL,
  PRIMARY KEY (`copy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_default`, `is_active`, `created_at`) VALUES
('library_stocktake_status', 'Stocktake Status', 'library', 'open', 'Counting', 10, 0, 1, NOW()),
('library_stocktake_status', 'Stocktake Status', 'library', 'reconciled', 'Reconciled', 20, 0, 1, NOW()),
('library_stocktake_status', 'Stocktake Status', 'library', 'signed_off', 'Signed off', 30, 0, 1, NOW()),
('library_stocktake_class', 'Stocktake Classification', 'library', 'found', 'Found', 10, 0, 1, NOW()),
('library_stocktake_class', 'Stocktake Classification', 'library', 'missing', 'Missing', 20, 0, 1, NOW()),
('library_stocktake_class', 'Stocktake Classification', 'library', 'misfiled', 'Misfiled', 30, 0, 1, NOW()),
('library_stocktake_class', 'Stocktake Classification', 'library', 'unexpected', 'Unexpected', 40, 0, 1, NOW()),
('library_stocktake_class', 'Stocktake Classification', 'library', 'on_loan', 'On loan', 50, 0, 1, NOW()),
('library_stocktake_class', 'Stocktake Classification', 'library', 'elsewhere', 'Accounted for elsewhere', 60, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'on_home_shelf', 'On its home shelf', 10, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'no_home_shelf', 'Seen, but the copy has no home shelf recorded', 20, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'wrong_shelf', 'Seen on a different shelf', 30, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'outside_range', 'Seen here, but its home shelf is outside this count', 40, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'not_scanned', 'Not seen during the count', 50, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'status_out_no_loan', 'Marked checked out, but no open loan exists', 60, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'open_loan', 'On an open loan', 70, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'open_loan_on_shelf', 'Seen on the shelf, but a loan is still open', 80, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'returned_during_count', 'Returned after the count started', 90, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'held_status', 'Copy status places it off the shelf', 100, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'unknown_barcode', 'Barcode is not in the catalogue', 110, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'status_excluded', 'Catalogue says this copy is no longer held', 120, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'other_branch', 'Belongs to another branch', 130, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'no_branch', 'Copy has no branch recorded', 140, 0, 1, NOW()),
('library_stocktake_reason', 'Stocktake Reason', 'library', 'no_barcode', 'Copy has no barcode, so it cannot be counted - check by hand', 150, 0, 1, NOW()),
('library_stocktake_excluded_status', 'Copy Statuses Not Expected On The Shelf', 'library', 'withdrawn', 'Withdrawn', 10, 0, 1, NOW()),
('library_stocktake_excluded_status', 'Copy Statuses Not Expected On The Shelf', 'library', 'lost', 'Lost', 20, 0, 1, NOW()),
('library_stocktake_excluded_status', 'Copy Statuses Not Expected On The Shelf', 'library', 'missing', 'Missing', 30, 0, 1, NOW()),
('library_stocktake_excluded_status', 'Copy Statuses Not Expected On The Shelf', 'library', 'disposed', 'Disposed', 40, 0, 1, NOW()),
('library_stocktake_excluded_status', 'Copy Statuses Not Expected On The Shelf', 'library', 'written_off', 'Written off', 50, 0, 1, NOW());
