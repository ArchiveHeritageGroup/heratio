<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1781: ahg_valuer and ahg_heritage_oci_movement were added to
 * ahg-heritage-manage's install.sql after the first release. PackageInstaller
 * only runs an install.sql when its FIRST table is missing, so every existing
 * install skipped them and the Valuer Registry and OCI Movements pages stayed
 * empty. The DDL below is the install.sql definition, unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_valuer')) {
            DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ahg_valuer` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `credential` VARCHAR(255) NULL COMMENT 'e.g. ASA, FRICS, MRICS, AIC member, registered conservator',
    `professional_body` VARCHAR(255) NULL COMMENT 'Issuing professional body (RICS, ASA, AIC, etc.)',
    `accreditation_number` VARCHAR(64) NULL,
    `email` VARCHAR(255) NULL,
    `phone` VARCHAR(64) NULL,
    `specialisations` JSON NULL COMMENT 'e.g. ["fine_art","manuscripts","natural_history"]',
    `active` TINYINT(1) DEFAULT 1,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`name`),
    KEY `idx_active` (`active`),
    KEY `idx_accreditation` (`accreditation_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }
        if (! Schema::hasTable('ahg_heritage_oci_movement')) {
            DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ahg_heritage_oci_movement` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `information_object_id` INT NULL COMMENT 'FK to information_object (jurisdiction-neutral)',
    `heritage_asset_id` INT UNSIGNED NULL COMMENT 'FK to heritage_asset (accounting subject)',
    `movement_type` VARCHAR(16) NOT NULL COMMENT 'revaluation_up|revaluation_down|impairment|reversal|disposal',
    `amount` DECIMAL(15,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'ZAR',
    `valuation_date` DATE NOT NULL,
    `valuer_id` INT UNSIGNED NULL,
    `valuation_method` VARCHAR(64) NULL COMMENT 'cost, fair_value, market, depreciated_replacement, nominal, indexation',
    `reason` TEXT NULL,
    `posted_to` VARCHAR(32) NOT NULL DEFAULT 'OCI' COMMENT 'OCI|P&L|Reserve',
    `affected_period_start` DATE NULL,
    `affected_period_end` DATE NULL,
    `created_by_user_id` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_io` (`information_object_id`),
    KEY `idx_asset` (`heritage_asset_id`),
    KEY `idx_type` (`movement_type`),
    KEY `idx_date` (`valuation_date`),
    KEY `idx_valuer` (`valuer_id`),
    KEY `idx_posted_to` (`posted_to`),
    KEY `idx_period` (`affected_period_start`, `affected_period_end`),
    CONSTRAINT `fk_oci_valuer` FOREIGN KEY (`valuer_id`) REFERENCES `ahg_valuer`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }
    }

    public function down(): void
    {
        // Not reversible: the tables may hold valuers and ledger entries by then.
    }
};
