<?php

/**
 * PurgeCombineTrashCommand - Console command for Heratio
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

namespace AhgPdfTools\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * #1177 - final purge of quarantined combine source files. CombineFolderCommand
 * --clear-source MOVES the combined pages into <storage>/pdf-combine-trash/<stamp>/
 * instead of deleting them; this command removes the folders whose quarantine age
 * exceeds the retention window (ahg_settings pdf_combine_trash_days, default 7),
 * logging exactly what it drops. Runs daily on the scheduler.
 */
class PurgeCombineTrashCommand extends Command
{
    protected $signature = 'ahg:purge-combine-trash {--days= : Override retention days (else ahg_settings pdf_combine_trash_days, default 7)} {--dry-run : List what would be purged without deleting}';

    protected $description = 'Purge quarantined combine source files past the retention window (#1177)';

    public function handle(): int
    {
        $base = rtrim((string) config('heratio.storage_path'), '/').'/pdf-combine-trash';
        if (! is_dir($base)) {
            $this->info('No combine quarantine folder; nothing to purge.');

            return 0;
        }

        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) (DB::table('ahg_settings')->where('setting_key', 'pdf_combine_trash_days')->value('setting_value') ?? 7);
        $days = max(0, $days);
        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');

        $purgedDirs = 0;
        $purgedFiles = 0;
        $kept = 0;
        foreach (glob($base.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $movedAt = null;
            $oj = @file_get_contents($dir.'/_origin.json');
            if ($oj) {
                $d = json_decode($oj, true);
                if (! empty($d['moved_at'])) {
                    try {
                        $movedAt = Carbon::parse($d['moved_at']);
                    } catch (\Throwable $e) {
                    }
                }
            }
            if (! $movedAt) {
                $movedAt = Carbon::createFromTimestamp(@filemtime($dir) ?: time());
            }
            if ($movedAt->gt($cutoff)) {
                $kept++;
                continue;   // still inside the retention window
            }
            $files = glob($dir.'/*') ?: [];
            $n = count($files);
            $this->line(($dry ? '[dry-run] ' : '').'purge '.$dir.' ('.$n.' file(s), quarantined '.$movedAt->toDateString().')');
            if (! $dry) {
                foreach ($files as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
            }
            $purgedDirs++;
            $purgedFiles += $n;
        }

        $this->info(($dry ? 'Would purge ' : 'Purged ')."{$purgedDirs} quarantine folder(s), {$purgedFiles} file(s); kept {$kept} still within {$days}-day retention.");

        return 0;
    }
}
