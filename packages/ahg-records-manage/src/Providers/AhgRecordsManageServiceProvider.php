<?php

/**
 * AhgRecordsManageServiceProvider — boots the Records Management module.
 *
 * On first boot, runs install.sql + seed_dropdowns.sql if a sentinel row is
 * missing (the canonical Heratio package install pattern, idempotent + safe to
 * re-run).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgRecordsManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-records');

        $this->bootInstallIfNeeded();
    }

    /**
     * Idempotent install + seed.
     *
     * Runs once when:
     *   - `rm_retention_schedule` table is missing (first install), or
     *   - the dropdown sentinel row is missing (e.g. install.sql ran but seed didn't).
     *
     * Subsequent boots short-circuit with a single SHOW-TABLES check.
     */
    protected function bootInstallIfNeeded(): void
    {
        try {
            if (! Schema::hasTable('rm_retention_schedule')) {
                $sql = @file_get_contents(__DIR__ . '/../../database/install.sql');
                if ($sql !== false && trim($sql) !== '') {
                    DB::unprepared($sql);
                    Log::info('ahg-records-manage: install.sql applied (first-boot)');
                }
            }

            $hasSeed = DB::table('ahg_dropdown')
                ->where('taxonomy', 'rm_disposal_action')
                ->where('code', 'destroy')
                ->exists();

            if (! $hasSeed) {
                $seed = @file_get_contents(__DIR__ . '/../../database/seed_dropdowns.sql');
                if ($seed !== false && trim($seed) !== '') {
                    DB::unprepared($seed);
                    Log::info('ahg-records-manage: seed_dropdowns.sql applied (first-boot)');
                }
            }
        } catch (\Throwable $e) {
            // Never block boot on install failure — log and continue.
            Log::warning('ahg-records-manage boot install skipped: ' . $e->getMessage());
        }
    }
}
