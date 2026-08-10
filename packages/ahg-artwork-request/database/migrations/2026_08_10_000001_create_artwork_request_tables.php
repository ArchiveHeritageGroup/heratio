<?php

/*
 * #1459 - Artwork placement requests (ported from ahgArtworkRequestPlugin 0.3.1).
 *
 * Four tables, deliberately separate from ahg_loan. ahg_loan models a loan to
 * another institution (partner_institution NOT NULL, couriers, customs, facility
 * reports); a colleague hanging a painting in their office is none of those, and
 * filling a NOT NULL partner column with something untrue on every internal
 * booking is how a collection database rots. Approval hands off to ahg_loan.
 *
 * No ENUM columns: VARCHAR with a COMMENT listing the valid values, so adding a
 * state later is not a migration.
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
        if (! Schema::hasTable('artwork_request')) {
            DB::unprepared(<<<'SQL'
CREATE TABLE `artwork_request` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_number` VARCHAR(50) NOT NULL,
    `requester_user_id` INT UNSIGNED NULL,
    `requester_name` VARCHAR(255) NULL,
    `requester_email` VARCHAR(255) NULL,
    `department` VARCHAR(255) NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'draft'
        COMMENT 'draft, submitted, approved, declined, withdrawn, fulfilled, returned, cancelled',
    `purpose` VARCHAR(100) NULL COMMENT 'office, boardroom, shared workspace, event, other',
    `justification` TEXT NULL,
    `requested_from` DATE NULL,
    `requested_to` DATE NULL,
    `placement_building` VARCHAR(255) NULL,
    `placement_floor` VARCHAR(50) NULL,
    `placement_room` VARCHAR(100) NULL,
    `placement_occupant` VARCHAR(255) NULL,
    `placement_notes` TEXT NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `review_notes` TEXT NULL,
    `decision_channel` VARCHAR(20) NOT NULL DEFAULT 'system' COMMENT 'system, offline',
    `loan_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_artwork_request_number` (`request_number`),
    KEY `idx_artwork_request_status` (`status`),
    KEY `idx_artwork_request_requester` (`requester_user_id`),
    KEY `idx_artwork_request_dates` (`requested_from`, `requested_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        if (! Schema::hasTable('artwork_request_object')) {
            DB::unprepared(<<<'SQL'
CREATE TABLE `artwork_request_object` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id` BIGINT UNSIGNED NOT NULL,
    `information_object_id` INT UNSIGNED NOT NULL,
    `object_title` VARCHAR(500) NULL,
    `object_identifier` VARCHAR(255) NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'requested'
        COMMENT 'requested, approved, declined, issued, returned',
    `conflict_note` TEXT NULL,
    `issued_at` DATETIME NULL,
    `returned_at` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_aro_request` (`request_id`),
    KEY `idx_aro_object` (`information_object_id`),
    KEY `idx_aro_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        if (! Schema::hasTable('artwork_request_approver')) {
            DB::unprepared(<<<'SQL'
CREATE TABLE `artwork_request_approver` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `department` VARCHAR(255) NULL,
    `email_notifications` TINYINT(1) NOT NULL DEFAULT 1,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ara_user_dept` (`user_id`, `department`),
    KEY `idx_ara_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        if (! Schema::hasTable('artwork_request_log')) {
            DB::unprepared(<<<'SQL'
CREATE TABLE `artwork_request_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id` BIGINT UNSIGNED NOT NULL,
    `event` VARCHAR(50) NOT NULL
        COMMENT 'created, submitted, approved, declined, withdrawn, issued, returned, reminded, reminded_due_soon, note',
    `actor_user_id` INT UNSIGNED NULL,
    `actor_name` VARCHAR(255) NULL,
    `detail` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_arl_request` (`request_id`),
    KEY `idx_arl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('artwork_request_log');
        Schema::dropIfExists('artwork_request_approver');
        Schema::dropIfExists('artwork_request_object');
        Schema::dropIfExists('artwork_request');
    }
};
