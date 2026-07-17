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
