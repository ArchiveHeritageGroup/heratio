<?php

/**
 * PackageCleanupCommand - prune stale, never-exported preservation packages.
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

namespace AhgPreservation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping for heratio#1436. A failed or abandoned BagIt build leaves a
 * preservation_package row in draft / failed / building state with no
 * export_path (the bytes never made it to disk). createPackage now reuses such a
 * row instead of multiplying drafts, but this command reaps the ones that are
 * genuinely stale - older than --hours and still un-exported - together with
 * their linked object / event rows and any stale bag/work directory.
 *
 * Mirrors ahg:portable-cleanup for the portable-export store.
 *
 * Usage:
 *   php artisan ahg:preservation-cleanup
 *   php artisan ahg:preservation-cleanup --hours=48 --dry-run
 */
class PackageCleanupCommand extends Command
{
    protected $signature = 'ahg:preservation-cleanup
        {--hours=24 : Minimum age (hours) of an un-exported draft/failed/building package before it is pruned}
        {--dry-run : Report what would be removed without deleting}';

    protected $description = 'Prune stale draft/failed preservation packages that were never exported (heratio#1436).';

    public function handle(): int
    {
        if (! Schema::hasTable('preservation_package')) {
            $this->warn('preservation_package table missing - nothing to do.');

            return self::SUCCESS;
        }

        $hours = max(1, (int) $this->option('hours'));
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        // Un-exported = status draft/failed/building AND no export file on record.
        // updated_at (falling back to created_at) must predate the cutoff so a
        // package still being worked on is never reaped.
        $stale = DB::table('preservation_package')
            ->whereIn('status', ['draft', 'failed', 'building'])
            ->where(function ($q) {
                $q->whereNull('export_path')->orWhere('export_path', '');
            })
            ->where(function ($q) use ($cutoff) {
                $q->where('updated_at', '<', $cutoff)
                    ->orWhere(function ($qq) use ($cutoff) {
                        $qq->whereNull('updated_at')->where('created_at', '<', $cutoff);
                    });
            })
            ->get(['id', 'name', 'status', 'source_path']);

        $this->info(sprintf('[preservation_package] stale_unexported=%d (older than %dh)%s',
            $stale->count(), $hours, $dry ? ' (dry-run)' : ''));

        if ($stale->isEmpty()) {
            return self::SUCCESS;
        }
        if ($dry) {
            foreach ($stale as $r) {
                $this->line(sprintf('  would prune #%d [%s] %s', $r->id, $r->status, (string) $r->name));
            }

            return self::SUCCESS;
        }

        $rows = 0;
        $dirs = 0;
        foreach ($stale as $r) {
            // Remove any half-written bag/work directory left on disk.
            if (! empty($r->source_path) && is_dir($r->source_path) && $this->looksLikeBagDir($r->source_path)) {
                $this->rmTree($r->source_path);
                $dirs++;
            }
            foreach (['preservation_package_object', 'preservation_package_event'] as $child) {
                if (Schema::hasTable($child)) {
                    DB::table($child)->where('package_id', $r->id)->delete();
                }
            }
            DB::table('preservation_package')->where('id', $r->id)->delete();
            $rows++;
        }

        $this->info("deleted_packages={$rows} removed_dirs={$dirs}");

        return self::SUCCESS;
    }

    /**
     * Guard against ever recursing into anything but a preservation staging dir
     * (defence in depth - source_path is system-written, not user input).
     */
    private function looksLikeBagDir(string $path): bool
    {
        $real = realpath($path) ?: $path;

        return str_contains($real, '/preservation/');
    }

    private function rmTree(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
