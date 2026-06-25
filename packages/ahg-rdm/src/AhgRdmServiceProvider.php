<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * ahg-rdm service provider (#1337/#1338).
 *
 * Sovereign research-data-management module - a thin orchestration layer over
 * ahg-ingest (file storage), ahg-research (projects), ahg-ai-services (NER),
 * ahg-core (DOI) and ahg-research OdrlService/AiDisclosureService. This package
 * adds only the Dataset wrapper + (later) the POPIA scan/gate; everything else
 * is wiring into existing services.
 */
class AhgRdmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ahg-rdm');

        // First-boot install: create rdm_dataset/rdm_dataset_file if missing and
        // seed the dataset_status dropdown. Idempotent + best-effort (retries
        // next boot on failure); mirrors the ahg-research/ahg-display pattern.
        $this->app->booted(function () {
            try {
                // Run install.sql when any rdm table is missing OR the verdict
                // column hasn't been added yet (install.sql is fully idempotent:
                // CREATE IF NOT EXISTS + a guarded ADD COLUMN), so an existing
                // install picks up the #1339 findings table + verdict column.
                $needsInstall = ! Schema::hasTable('rdm_dataset')
                    || ! Schema::hasTable('rdm_scan_finding')
                    || ! Schema::hasColumn('rdm_dataset', 'verdict');
                if ($needsInstall) {
                    $sql = @file_get_contents(__DIR__.'/../database/install.sql');
                    if (is_string($sql) && trim($sql) !== '') {
                        DB::unprepared($sql);
                    }
                }
                if (Schema::hasTable('ahg_dropdown')
                    && ! DB::table('ahg_dropdown')->where('taxonomy', 'dataset_status')->exists()) {
                    $seed = @file_get_contents(__DIR__.'/../database/seed_dropdowns.sql');
                    if (is_string($seed) && trim($seed) !== '') {
                        DB::unprepared($seed);
                    }
                }
            } catch (\Throwable $e) {
                // non-fatal; retried on next boot
            }
        });
    }
}
