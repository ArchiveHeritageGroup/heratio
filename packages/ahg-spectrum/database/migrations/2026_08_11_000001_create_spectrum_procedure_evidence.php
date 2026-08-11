<?php

/*
 * #1460 Phase 1 - generic per-procedure documented evidence/proof.
 *
 * One table keyed by (object_id, procedure_type[, procedure_id]) - the same
 * keying as spectrum_procedure_history - so a single service + partial gives
 * EVERY Collections Procedure flow documented evidence, with zero per-procedure
 * code. Supports both an uploaded file and a link to an existing digital_object.
 *
 * VARCHAR-with-COMMENT for the enum-ish columns (house pattern; no ENUM).
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spectrum_procedure_evidence')) {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE `spectrum_procedure_evidence` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `object_id` INT UNSIGNED NOT NULL COMMENT 'information_object id the procedure belongs to',
    `procedure_type` VARCHAR(50) NOT NULL COMMENT 'acquisition, condition, valuation, loan_in, loan_out, movement, deaccession, location, object_entry, object_exit, insurance, ...',
    `procedure_id` INT UNSIGNED NULL COMMENT 'specific procedure row, or NULL = evidence for the procedure_type in general',
    `evidence_kind` VARCHAR(10) NOT NULL DEFAULT 'upload' COMMENT 'upload, link',
    `filename` VARCHAR(255) NULL COMMENT 'stored filename (upload)',
    `original_name` VARCHAR(255) NULL COMMENT 'original upload filename',
    `mime_type` VARCHAR(100) NULL,
    `file_size` INT UNSIGNED NULL,
    `file_path` VARCHAR(500) NULL COMMENT 'path under the app uploads root (upload)',
    `digital_object_id` INT UNSIGNED NULL COMMENT 'existing digital_object.id (link)',
    `category` VARCHAR(40) NULL COMMENT 'certificate, receipt, report, agreement, photo, correspondence, other',
    `description` TEXT NULL,
    `evidence_date` DATE NULL,
    `uploaded_by` INT UNSIGNED NULL,
    `tenant_id` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_spe_object_proc` (`object_id`, `procedure_type`),
    KEY `idx_spe_procedure` (`procedure_id`),
    KEY `idx_spe_digital_object` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('spectrum_procedure_evidence');
    }
};
