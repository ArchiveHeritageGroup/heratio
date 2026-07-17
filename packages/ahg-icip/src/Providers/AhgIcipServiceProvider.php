<?php

/**
 * AhgIcipServiceProvider - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgIcip\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgIcipServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register the audit alias BEFORE loading routes that reference it, so
        // the 'audit.icip' middleware key resolves when the route groups boot.
        // It records ICIP access when icip_config.audit_all_icip_access = 1.
        $this->app['router']->aliasMiddleware('audit.icip', \AhgIcip\Middleware\AuditIcipAccess::class);

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'icip');
        $this->ensureOcapColumns();
        $this->ensureProtocolColumns();
        $this->ensureGovernanceTables();
        $this->ensureRegionPacks();
    }

    /**
     * #1388 / #1406 P2 - CARE provenance for label assignment.
     *
     * icip_label_assignment_log is an APPEND-ONLY audit of every TK/BC label
     * apply/withdraw: who did it, on whose authority, whether AI-proposed (an
     * AhgInferenceReceipt id), and the community. CARE Principle "Responsibility"
     * + "Ethics": the record of who controls a label is itself part of the
     * governance, so it is never mutated or deleted. Idempotent (CREATE TABLE
     * IF NOT EXISTS), mirrors the install.sql style.
     */
    private function ensureGovernanceTables(): void
    {
        try {
            DB::statement(
                'CREATE TABLE IF NOT EXISTS icip_label_assignment_log (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    information_object_id INT NOT NULL,
                    label_type_id INT NULL,
                    community_id INT NULL,
                    action VARCHAR(16) NOT NULL,
                    applied_by VARCHAR(34) NULL,
                    authority VARCHAR(255) NULL,
                    inference_receipt_id BIGINT NULL,
                    notes TEXT NULL,
                    actor_id INT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ala_object (information_object_id),
                    INDEX idx_ala_type (label_type_id),
                    INDEX idx_ala_action (action),
                    INDEX idx_ala_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            // #1406 P2c - Community Steward: the per-community actor(s) authorised to
            // apply/withdraw THAT community's labels with applied_by='community'
            // (#1388 Principle 4). A community with no stewards falls back to staff
            // ICIP-write governance, so this never breaks the existing workflow.
            DB::statement(
                'CREATE TABLE IF NOT EXISTS icip_community_steward (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    community_id INT NOT NULL,
                    user_id INT NOT NULL,
                    created_by INT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_steward (community_id, user_id),
                    INDEX idx_cs_community (community_id),
                    INDEX idx_cs_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $e) {
            // Don't break boot if the DB isn't reachable yet
        }
    }

    /**
     * #1388 / #1406 P5 - pluggable per-region packs.
     *
     * A "region pack" is the jurisdiction context a community operates within
     * (region_module on icip_community / icip_tk_label_type / *_protocol). This
     * registry makes region_module a first-class, documented concept instead of a
     * free-text field, and seeds the first packs (International-neutral default +
     * South Africa + SADC).
     *
     * DELIBERATELY ships NO named communities and NO invented community labels:
     * that would impose identity, the opposite of #1388 Principle 1 (self-
     * identification) and CARE. Communities are added by the institution/their
     * stewards through the governance UI and tagged to a region here. A pack
     * carries only public jurisdiction/legal-framework context.
     *
     * Idempotent (CREATE TABLE IF NOT EXISTS + INSERT IGNORE by code).
     */
    private function ensureRegionPacks(): void
    {
        try {
            DB::statement(
                'CREATE TABLE IF NOT EXISTS icip_region_module (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(64) NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    jurisdiction VARCHAR(255) NULL,
                    frameworks JSON NULL,
                    care_note TEXT NULL,
                    display_order INT DEFAULT 100,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_region_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $packs = [
                [
                    'code' => 'international', 'name' => 'International (jurisdiction-neutral)',
                    'jurisdiction' => null, 'display_order' => 0,
                    'frameworks' => ['CARE Principles', 'UNDRIP', 'Local Contexts (TK/BC Labels & Notices)', 'SKOS'],
                    'care_note' => 'The neutral core. Communities self-identify; no jurisdiction-specific regime is assumed.',
                ],
                [
                    'code' => 'za', 'name' => 'South Africa', 'jurisdiction' => 'South Africa',
                    'display_order' => 10,
                    'frameworks' => [
                        'Protection, Promotion, Development and Management of Indigenous Knowledge Act 6 of 2019',
                        'National Indigenous Knowledge Systems Office (NIKSO)',
                        'Nagoya Protocol / Access and Benefit-sharing (ABS)',
                        'Protection of Personal Information Act (POPIA)',
                    ],
                    'care_note' => 'South African deployments. Communities are added by their stewards and self-identify; this pack ships only the legal/jurisdiction context, not identities.',
                ],
                [
                    'code' => 'sadc', 'name' => 'SADC (Southern African Development Community)',
                    'jurisdiction' => 'SADC region', 'display_order' => 20,
                    'frameworks' => [
                        'ARIPO Swakopmund Protocol on the Protection of Traditional Knowledge and Expressions of Folklore',
                        'Nagoya Protocol / Access and Benefit-sharing (ABS)',
                        'CARE Principles',
                    ],
                    'care_note' => 'Southern African deployments beyond South Africa. Self-identification and steward governance as per the core.',
                ],
            ];
            foreach ($packs as $p) {
                DB::table('icip_region_module')->insertOrIgnore([
                    'code'          => $p['code'],
                    'name'          => $p['name'],
                    'jurisdiction'  => $p['jurisdiction'],
                    'frameworks'    => json_encode($p['frameworks']),
                    'care_note'     => $p['care_note'],
                    'display_order' => $p['display_order'],
                    'is_active'     => 1,
                    'created_at'    => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Don't break boot if the DB isn't reachable yet
        }
    }

    /**
     * #1388 / #1406 P2 - bridge the ICIP governance layer to the jurisdiction-neutral
     * enforcement engine ({@see \AhgCore\Services\TermProtocolGate}), and neutralise
     * the (originally AIATSIS/Australian) community model for the international market.
     *
     * Idempotent, mirrors {@see ensureOcapColumns()}:
     *  - icip_tk_label_type gains default_access_condition (maps a Local Contexts
     *    label to an engine condition open|attribution|non_commercial|community_voice|
     *    restricted|sacred_secret|seasonal|gendered), plus region_module + is_local_contexts.
     *    The 22 seeded labels are given sensible defaults ONCE, on column creation, so a
     *    later steward/admin edit in the catalog is never clobbered on the next boot.
     *  - icip_community gains self_identified_term (Principle 1 - render the community's
     *    OWN term, never hard-code "Indigenous"), region_module, care_statement (CARE), pid,
     *    and its Australian `state_territory NOT NULL` is relaxed to NULL so non-AU
     *    communities are representable.
     */
    private function ensureProtocolColumns(): void
    {
        // Local Contexts label code -> engine access_condition. RESTRICTED members
        // (sacred_secret/restricted/gendered/seasonal/community_voice) hide the record
        // from the public; the rest stay viewable with the obligation on the badge/export.
        $map = [
            'tk_s'  => 'sacred_secret',   // Secret / Sacred
            'tk_co' => 'restricted',      // Community Use Only
            'tk_wr' => 'gendered',        // Women Restricted
            'tk_mr' => 'gendered',        // Men Restricted
            'tk_ss' => 'seasonal',        // Seasonal
            'tk_cv' => 'community_voice',  // Community Voice
            'tk_a'  => 'attribution',     // Attribution
            'tk_cl' => 'attribution',     // Clan
            'tk_f'  => 'attribution',     // Family
            'tk_nc' => 'non_commercial',  // Non-Commercial
            'bc_p'  => 'attribution',     // BC Provenance
            'bc_cl' => 'attribution',     // BC Clan
            'bc_cnc' => 'non_commercial', // BC Commercial / Non-Commercial
            // all others (tk_mc, tk_o, tk_v, tk_cs, tk_wg, tk_mg, bc_mc, bc_o, bc_r)
            // remain 'open' - advisory badge only.
        ];

        try {
            if (Schema::hasTable('icip_tk_label_type')) {
                if (! Schema::hasColumn('icip_tk_label_type', 'default_access_condition')) {
                    DB::statement("ALTER TABLE icip_tk_label_type ADD COLUMN default_access_condition VARCHAR(32) NOT NULL DEFAULT 'open' AFTER category");
                    // seed the mapping ONCE, only on creation of the column
                    foreach ($map as $code => $cond) {
                        DB::table('icip_tk_label_type')->where('code', $code)->update(['default_access_condition' => $cond]);
                    }
                }
                if (! Schema::hasColumn('icip_tk_label_type', 'region_module')) {
                    DB::statement('ALTER TABLE icip_tk_label_type ADD COLUMN region_module VARCHAR(64) NULL AFTER local_contexts_url');
                }
                if (! Schema::hasColumn('icip_tk_label_type', 'is_local_contexts')) {
                    DB::statement('ALTER TABLE icip_tk_label_type ADD COLUMN is_local_contexts TINYINT(1) NOT NULL DEFAULT 1 AFTER region_module');
                }
            }

            if (Schema::hasTable('icip_community')) {
                if (! Schema::hasColumn('icip_community', 'self_identified_term')) {
                    DB::statement('ALTER TABLE icip_community ADD COLUMN self_identified_term VARCHAR(255) NULL AFTER name');
                }
                if (! Schema::hasColumn('icip_community', 'region_module')) {
                    DB::statement('ALTER TABLE icip_community ADD COLUMN region_module VARCHAR(64) NULL AFTER region');
                }
                if (! Schema::hasColumn('icip_community', 'care_statement')) {
                    DB::statement('ALTER TABLE icip_community ADD COLUMN care_statement TEXT NULL AFTER notes');
                }
                if (! Schema::hasColumn('icip_community', 'pid')) {
                    DB::statement('ALTER TABLE icip_community ADD COLUMN pid VARCHAR(255) NULL AFTER care_statement');
                }
                // Relax the Australian `state_territory NOT NULL` for non-AU markets.
                $col = DB::selectOne(
                    'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    ['icip_community', 'state_territory']
                );
                if ($col && strtoupper($col->IS_NULLABLE) === 'NO') {
                    DB::statement('ALTER TABLE icip_community MODIFY state_territory VARCHAR(57) NULL');
                }
            }
        } catch (\Throwable $e) {
            // Don't break boot if the install hasn't run yet
        }
    }

    /**
     * Idempotent OCAP overlay schema migration.
     *
     * OCAP® (Ownership, Control, Access, Possession) is a First Nations data-sovereignty
     * framework. Pluggable per market — disabled by default unless icip_config has
     * `ocap_enabled = 1`. Adds two columns:
     *   - icip_community.ocap_assertion (JSON)         which of the 4 principles the community asserts
     *   - icip_object_summary.possession_assertion     which party holds possession ('community' | 'repository' | 'shared' | null)
     */
    private function ensureOcapColumns(): void
    {
        try {
            if (Schema::hasTable('icip_community') && ! Schema::hasColumn('icip_community', 'ocap_assertion')) {
                DB::statement('ALTER TABLE icip_community ADD COLUMN ocap_assertion JSON NULL AFTER notes');
            }
            if (Schema::hasTable('icip_object_summary') && ! Schema::hasColumn('icip_object_summary', 'possession_assertion')) {
                DB::statement('ALTER TABLE icip_object_summary ADD COLUMN possession_assertion VARCHAR(50) NULL AFTER community_ids');
            }
            if (Schema::hasTable('icip_config')) {
                $exists = DB::table('icip_config')->where('config_key', 'ocap_enabled')->exists();
                if (! $exists) {
                    DB::table('icip_config')->insert([
                        'config_key' => 'ocap_enabled',
                        'config_value' => '0',
                        'description' => 'Enable OCAP® overlay (Ownership, Control, Access, Possession). Pluggable per-market — typical First Nations data sovereignty markets: Canada (CAN), Australia (AUS), Aotearoa (NZL).',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Don't break boot if the install hasn't run yet
        }
    }
}
