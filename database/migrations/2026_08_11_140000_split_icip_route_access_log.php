<?php

/**
 * Split the two things that were both called `icip_access_log`.
 *
 * Two features claimed the same table name with incompatible shapes:
 *
 *  - Route audit (AuditIcipAccess middleware + ahg-icip install.sql):
 *      user_id, ip_address, path        - "who hit which /admin/icip page"
 *  - #1427 graded-access audit (LogsIcipAccess + the provider's boot self-heal):
 *      information_object_id, decision, restriction_types, reason
 *                                       - "who was allowed/denied which record, and why"
 *
 * Both used CREATE TABLE IF NOT EXISTS, so whichever ran first won and the
 * other silently lost: its INSERT throws an unknown-column error that the
 * caller catches and logs. In practice the provider self-heal runs on every
 * boot and wins, so the ROUTE audit has never recorded a row - the
 * "Log all ICIP record access" setting in admin has been inert. On a fresh
 * install where install.sql landed first the failure inverts and #1427's
 * accountability trail is the one that silently breaks, which is worse.
 *
 * The two logs answer different questions and #1427's is the one a source
 * community will ask us to account for (CARE 'authority to control'), so it
 * keeps the `icip_access_log` name and route hits move to their own table
 * rather than diluting it.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createRouteLog();

        // Defensive: on an install where install.sql won, `icip_access_log`
        // carries the ROUTE shape and #1427's audit is the broken one. Move
        // those rows to their proper home and rebuild the graded-access table.
        if (Schema::hasTable('icip_access_log')
            && Schema::hasColumn('icip_access_log', 'path')
            && ! Schema::hasColumn('icip_access_log', 'information_object_id')) {

            DB::statement(
                'INSERT INTO `icip_route_access_log` (user_id, ip_address, path, created_at)'
                .' SELECT user_id, ip_address, path, created_at FROM `icip_access_log`'
            );
            // Keep the original rather than dropping it - they are already
            // copied, but an audit trail is not something to delete on a guess.
            DB::statement('RENAME TABLE `icip_access_log` TO `icip_access_log_route_backup`');
            $this->createGradedAccessLog();
        }
    }

    public function down(): void
    {
        // Not reverted: merging the two logs back under one name is what caused
        // the fault, and dropping either would discard audit rows.
    }

    private function createRouteLog(): void
    {
        if (Schema::hasTable('icip_route_access_log')) {
            return;
        }

        DB::statement(
            'CREATE TABLE IF NOT EXISTS `icip_route_access_log` (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                path VARCHAR(1000) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_iral_user (user_id),
                INDEX idx_iral_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function createGradedAccessLog(): void
    {
        DB::statement(
            'CREATE TABLE IF NOT EXISTS `icip_access_log` (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                information_object_id INT NOT NULL,
                user_id INT NULL,
                decision VARCHAR(24) NOT NULL,
                restriction_types VARCHAR(255) NULL,
                reason VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ial_object (information_object_id),
                INDEX idx_ial_user (user_id),
                INDEX idx_ial_decision (decision),
                INDEX idx_ial_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
