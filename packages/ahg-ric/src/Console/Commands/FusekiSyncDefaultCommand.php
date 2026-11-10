<?php

/**
 * FusekiSyncDefaultCommand - set the RiC triple-store sync switch
 * (ahg_settings.fuseki_sync_enabled) to match whether Fuseki is there.
 *
 * FusekiSyncService treats a missing row as "on", so an install without
 * Fuseki kept trying to reach it and the nightly orphan cleanup failed every
 * night (heratio#1598).
 *
 *   ahg:ric:fuseki-sync-default          Run by bin/install and docker/init.sh.
 *                                        Writes the row only when it is missing:
 *                                        1 if Fuseki answers, 0 if not. An
 *                                        existing row is never changed, so
 *                                        updates keep their current behaviour.
 *   ahg:ric:fuseki-sync-default --setup  Run after configuring Fuseki
 *                                        (docs/ric-sync-setup.md). Turns sync on
 *                                        once the endpoint answers; changes
 *                                        nothing and exits non-zero if it does not.
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

namespace AhgRic\Console\Commands;

use AhgRic\Support\FusekiEndpoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FusekiSyncDefaultCommand extends Command
{
    protected $signature = 'ahg:ric:fuseki-sync-default
        {--setup : Turn RiC sync on now that Fuseki is configured (fails if the endpoint does not answer)}';

    protected $description = 'Set fuseki_sync_enabled from whether the Fuseki endpoint answers (install default, or --setup)';

    private const KEY = 'fuseki_sync_enabled';

    public function handle(FusekiEndpoint $endpoint): int
    {
        if (! Schema::hasTable('ahg_settings')) {
            $this->warn('[fuseki-sync-default] ahg_settings not installed yet; nothing to do.');

            return self::SUCCESS;
        }

        $current = DB::table('ahg_settings')->where('setting_key', self::KEY)->value('setting_value');
        $url = $endpoint->resolve()['url'];
        $answers = ! empty($url) && $endpoint->reachable($url);

        if ($this->option('setup')) {
            if (empty($url)) {
                $this->error('[fuseki-sync-default] No Fuseki URL configured (RIC_FUSEKI_URL in .env, or fuseki_endpoint in RiC settings). Sync left unchanged.');

                return self::FAILURE;
            }
            if (! $answers) {
                $this->error("[fuseki-sync-default] Fuseki is not answering at {$url}. Sync left unchanged.");

                return self::FAILURE;
            }
            $this->write('1');
            $this->info("[fuseki-sync-default] Fuseki answers at {$url}; RiC sync turned on.");

            return self::SUCCESS;
        }

        if ($current !== null) {
            $this->info("[fuseki-sync-default] fuseki_sync_enabled is already set ({$current}); left unchanged.");

            return self::SUCCESS;
        }

        $this->write($answers ? '1' : '0');
        $this->info($answers
            ? "[fuseki-sync-default] Fuseki answers at {$url}; RiC sync on."
            : '[fuseki-sync-default] No Fuseki answering; RiC sync off. After setting Fuseki up, run: php artisan ahg:ric:fuseki-sync-default --setup');

        return self::SUCCESS;
    }

    private function write(string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => self::KEY],
            [
                'setting_value' => $value,
                'setting_group' => 'fuseki',
                'setting_type' => 'boolean',
                'description' => 'Master switch for RiC sync to the Fuseki triple store',
                'updated_at' => now(),
            ]
        );
    }
}
