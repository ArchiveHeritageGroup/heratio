<?php

/**
 * ahg:gazetteer-load - load an offline gazetteer dump for the place-name check
 * of ahg:cross-reference --places (heratio#1524).
 *
 * Takes files already on disk: GeoNames country files (ZA.zip, LS.txt, ...
 * from the GeoNames export, CC BY 4.0) or any file in the same tab-separated
 * shape, such as a national names register converted to it. This command
 * never downloads anything.
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

namespace AhgAiServices\Commands;

use AhgAiServices\Services\GazetteerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GazetteerLoadCommand extends Command
{
    protected $signature = 'ahg:gazetteer-load
        {files* : Gazetteer dump files on disk (.txt or .zip, GeoNames columns)}
        {--source=geonames : Name recorded against these places}';

    protected $description = 'Load an offline gazetteer (for example GeoNames country files) for the place-name check';

    public function handle(GazetteerService $svc): int
    {
        if (! Schema::hasTable(GazetteerService::TABLE)) {
            $this->error('The gazetteer table is missing: run the migrations first.');

            return self::FAILURE;
        }
        $source = substr((string) $this->option('source'), 0, 30);
        foreach ($this->argument('files') as $file) {
            if (! is_readable($file)) {
                $this->error("Cannot read {$file}");

                return self::FAILURE;
            }
            $this->info(sprintf('%s: %d spelling(s) added.', $file, $svc->load($file, $source)));
        }
        $this->line(DB::table(GazetteerService::TABLE)->count().' spelling(s) in the gazetteer.');

        return self::SUCCESS;
    }
}
