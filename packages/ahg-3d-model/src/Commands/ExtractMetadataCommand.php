<?php

/**
 * ExtractMetadataCommand - Console command for Heratio
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
 */

namespace Ahg3dModel\Commands;

use Ahg3dModel\Services\ModelMetadataExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #1178 - backfill auto-extracted technical metadata onto existing
 * object_3d_model rows (format version, compression, bounding box,
 * vertex/face/texture counts, PBR maps). Only fills empty fields unless --force.
 */
class ExtractMetadataCommand extends Command
{
    protected $signature = 'ahg:3d-extract-metadata {--id= : One model id} {--force : Overwrite already-populated fields}';

    protected $description = 'Backfill auto-extracted technical metadata onto existing 3D models';

    public function handle(ModelMetadataExtractor $extractor): int
    {
        $base = rtrim((string) config('heratio.uploads_path'), '/');
        $q = DB::table('object_3d_model');
        if ($this->option('id')) {
            $q->where('id', (int) $this->option('id'));
        }
        $models = $q->get();
        $this->info("Processing {$models->count()} model(s)...");

        $force = (bool) $this->option('force');
        $done = 0;
        foreach ($models as $m) {
            $abs = $base.'/'.ltrim((string) $m->file_path, '/');
            if (! is_file($abs)) {
                $this->warn("  #{$m->id} {$m->filename} - file missing, skip");

                continue;
            }
            $meta = $extractor->extract($abs, (string) ($m->format ?: pathinfo($abs, PATHINFO_EXTENSION)));
            if (! $meta) {
                $this->warn("  #{$m->id} {$m->filename} - nothing extracted");

                continue;
            }
            // Only fill empty columns unless --force.
            if (! $force) {
                foreach (array_keys($meta) as $k) {
                    if (isset($m->$k) && $m->$k !== null && $m->$k !== '' && $m->$k !== 0) {
                        unset($meta[$k]);
                    }
                }
            }
            if (! $meta) {
                $this->line("  #{$m->id} {$m->filename} - already populated");

                continue;
            }
            DB::table('object_3d_model')->where('id', $m->id)->update($meta + ['updated_at' => now()]);
            $this->info("  #{$m->id} {$m->filename} -> ".implode(', ', array_keys($meta)));
            $done++;
        }

        $this->info("Updated {$done} model(s).");

        return 0;
    }
}
