-- ============================================================================
-- ahg-cart — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgCartPlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install — Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- ahgCartPlugin - E-Commerce Database Schema
-- ============================================================
-- Version: 2.0.0
-- Author: The Archive and Heritage Group
-- Supports: Standard Request Mode + E-Commerce Mode
-- ============================================================

-- Cart table (holds items before checkout)
CREATE TABLE IF NOT EXISTS `cart` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT DEFAULT NULL,
  `session_id` VARCHAR(255) DEFAULT NULL,
  `archival_description_id` INT DEFAULT NULL,
  `archival_description` VARCHAR(1024) DEFAULT NULL,
  `slug` VARCHAR(1024) DEFAULT NULL,
  `product_type_id` INT DEFAULT NULL,
  `quantity` INT DEFAULT 1,
  `unit_price` DECIMAL(10,2) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cart_user` (`user_id`),
  INDEX `idx_cart_description` (`archival_description_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-Commerce Settings (per repository)
CREATE TABLE IF NOT EXISTS `ahg_ecommerce_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `repository_id` INT DEFAULT NULL,
  `is_enabled` TINYINT(1) DEFAULT 0,
  `currency` VARCHAR(3) DEFAULT 'ZAR',
  `vat_rate` DECIMAL(5,2) DEFAULT 15.00,
  `vat_number` VARCHAR(50) DEFAULT NULL,
  `payment_gateway` VARCHAR(50) DEFAULT 'payfast',
  `payfast_merchant_id` VARCHAR(50) DEFAULT NULL,
  `payfast_merchant_key` VARCHAR(100) DEFAULT NULL,
  `payfast_passphrase` VARCHAR(100) DEFAULT NULL,
  `payfast_sandbox` TINYINT(1) DEFAULT 1,
  `stripe_public_key` VARCHAR(255) DEFAULT NULL,
  `stripe_secret_key` VARCHAR(255) DEFAULT NULL,
  `stripe_sandbox` TINYINT(1) DEFAULT 1,
  `terms_conditions` TEXT DEFAULT NULL,
  `confirmation_email_template` TEXT DEFAULT NULL,
  `admin_notification_email` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ecommerce_repo` (`repository_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Types (Digital Download, Print, etc.)
CREATE TABLE IF NOT EXISTS `ahg_product_type` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_digital` TINYINT(1) DEFAULT 1,
  `requires_shipping` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Pricing (per repository, per product type)
CREATE TABLE IF NOT EXISTS `ahg_product_pricing` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `repository_id` INT DEFAULT NULL,
  `product_type_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_includes_vat` TINYINT(1) DEFAULT 1,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pricing_repo` (`repository_id`),
  INDEX `idx_pricing_type` (`product_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders
CREATE TABLE IF NOT EXISTS `ahg_order` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(50) NOT NULL,
  `user_id` INT NOT NULL,
  `repository_id` INT DEFAULT NULL,
  `status` VARCHAR(69) DEFAULT 'pending' COMMENT 'pending, paid, processing, completed, cancelled, refunded',
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `vat_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'ZAR',
  `customer_name` VARCHAR(255) DEFAULT NULL,
  `customer_email` VARCHAR(255) DEFAULT NULL,
  `customer_phone` VARCHAR(50) DEFAULT NULL,
  `billing_address` TEXT DEFAULT NULL,
  `shipping_address` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_order_number` (`order_number`),
  INDEX `idx_order_user` (`user_id`),
  INDEX `idx_order_status` (`status`),
  INDEX `idx_order_repo` (`repository_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items
CREATE TABLE IF NOT EXISTS `ahg_order_item` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `archival_description_id` INT DEFAULT NULL,
  `archival_description` VARCHAR(1024) DEFAULT NULL,
  `slug` VARCHAR(1024) DEFAULT NULL,
  `product_type_id` INT DEFAULT NULL,
  `product_name` VARCHAR(255) DEFAULT NULL,
  `quantity` INT DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `download_url` VARCHAR(1024) DEFAULT NULL,
  `download_expires_at` DATETIME DEFAULT NULL,
  `download_count` INT DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_order_item_order` (`order_id`),
  INDEX `idx_order_item_desc` (`archival_description_id`),
  FOREIGN KEY (`order_id`) REFERENCES `ahg_order`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments
CREATE TABLE IF NOT EXISTS `ahg_payment` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `payment_gateway` VARCHAR(50) NOT NULL,
  `transaction_id` VARCHAR(255) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'ZAR',
  `status` VARCHAR(60) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed, refunded',
  `gateway_response` JSON DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_payment_order` (`order_id`),
  INDEX `idx_payment_transaction` (`transaction_id`),
  FOREIGN KEY (`order_id`) REFERENCES `ahg_order`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Download Tokens (for secure digital delivery)
CREATE TABLE IF NOT EXISTS `ahg_download_token` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_item_id` INT NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `max_downloads` INT DEFAULT 5,
  `download_count` INT DEFAULT 0,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_download_token` (`token`),
  INDEX `idx_download_item` (`order_item_id`),
  FOREIGN KEY (`order_item_id`) REFERENCES `ahg_order_item`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default product types
INSERT IGNORE INTO `ahg_product_type` (`id`, `name`, `description`, `is_digital`, `requires_shipping`, `sort_order`) VALUES
(1, 'Digital Download - Low Resolution', 'Web-quality image (72dpi, max 1200px)', 1, 0, 10),
(2, 'Digital Download - High Resolution', 'Print-quality image (300dpi, full resolution)', 1, 0, 20),
(3, 'Digital Download - TIFF Master', 'Archival quality TIFF file', 1, 0, 30),
(4, 'Print - A4', 'Professional print on archival paper (A4)', 0, 1, 40),
(5, 'Print - A3', 'Professional print on archival paper (A3)', 0, 1, 50),
(6, 'Print - A2', 'Professional print on archival paper (A2)', 0, 1, 60),
(7, 'Publication License - Non-Commercial', 'License for non-commercial publication', 1, 0, 70),
(8, 'Publication License - Commercial', 'License for commercial publication', 1, 0, 80),
(9, 'Research Use Only', 'Free for academic research (watermarked)', 1, 0, 90)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert default global pricing (repository_id = NULL means global default)
INSERT IGNORE INTO `ahg_product_pricing` (`repository_id`, `product_type_id`, `name`, `description`, `price`, `is_active`) VALUES
(NULL, 1, 'Low Resolution Digital', 'Web-quality download', 50.00, 1),
(NULL, 2, 'High Resolution Digital', 'Print-quality download', 150.00, 1),
(NULL, 3, 'TIFF Master', 'Archival master file', 350.00, 1),
(NULL, 4, 'A4 Print', 'Professional A4 print', 250.00, 1),
(NULL, 5, 'A3 Print', 'Professional A3 print', 400.00, 1),
(NULL, 6, 'A2 Print', 'Professional A2 print', 600.00, 1),
(NULL, 7, 'Non-Commercial License', 'Publication rights (non-commercial)', 500.00, 1),
(NULL, 8, 'Commercial License', 'Publication rights (commercial)', 1500.00, 1),
(NULL, 9, 'Research Use', 'Free for academic research', 0.00, 1)
ON DUPLICATE KEY UPDATE price = VALUES(price);

SET FOREIGN_KEY_CHECKS = 1;
