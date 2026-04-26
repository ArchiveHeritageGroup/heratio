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
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'icip');
        $this->ensureOcapColumns();
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
            if (Schema::hasTable('icip_community') && !Schema::hasColumn('icip_community', 'ocap_assertion')) {
                DB::statement('ALTER TABLE icip_community ADD COLUMN ocap_assertion JSON NULL AFTER notes');
            }
            if (Schema::hasTable('icip_object_summary') && !Schema::hasColumn('icip_object_summary', 'possession_assertion')) {
                DB::statement("ALTER TABLE icip_object_summary ADD COLUMN possession_assertion VARCHAR(50) NULL AFTER community_ids");
            }
            if (Schema::hasTable('icip_config')) {
                $exists = DB::table('icip_config')->where('config_key', 'ocap_enabled')->exists();
                if (!$exists) {
                    DB::table('icip_config')->insert([
                        'config_key'   => 'ocap_enabled',
                        'config_value' => '0',
                        'description'  => 'Enable OCAP® overlay (Ownership, Control, Access, Possession). Pluggable per-market — typical First Nations data sovereignty markets: Canada (CAN), Australia (AUS), Aotearoa (NZL).',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Don't break boot if the install hasn't run yet
        }
    }
}
