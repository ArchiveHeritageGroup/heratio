<?php

/**
 * AhgPreservationServiceProvider
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgPreservation\Providers;

use AhgPreservation\Console\PremisExportCommand;
use AhgPreservation\Console\PreservationScanCommand;
use AhgPreservation\Console\RunFixitySchedulesCommand;
use AhgPreservation\Services\BagItService;
use AhgPreservation\Services\FixityScanService;
use AhgPreservation\Services\OaisLifecycleService;
use AhgPreservation\Services\PremisRightsService;
use AhgPreservation\Services\PremisXmlSerializer;
use AhgPreservation\Services\PreservationService;
use AhgPreservation\Services\PronomIdentificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgPreservationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PreservationService::class);
        $this->app->singleton(BagItService::class);
        $this->app->singleton(OaisLifecycleService::class);
        $this->app->singleton(PronomIdentificationService::class);
        $this->app->singleton(PremisRightsService::class);
        $this->app->singleton(PremisXmlSerializer::class);
        $this->app->singleton(FixityScanService::class, function ($app) {
            return new FixityScanService($app->make(PreservationService::class));
        });
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-preservation');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunFixitySchedulesCommand::class,
                PremisExportCommand::class,
                PreservationScanCommand::class,
            ]);
        }

        $this->bootCronRegistration();
        $this->bootPremisRightsTable();
        $this->bootPremisObjectColumns();
    }

    /**
     * #1179 - make premis_object self-sufficient as a preservation record:
     * its own checksum, significant properties, preservation-master flag and a
     * link to the access digital object. Additive + sentinel-guarded.
     */
    protected function bootPremisObjectColumns(): void
    {
        try {
            // NB: premis_object.id is a FK to object.id (AtoM shared-PK) - it is
            // 1:1 with a digital object, NOT auto-increment; callers set id = the
            // digital object's id.
            if (! Schema::hasTable('premis_object') || Schema::hasColumn('premis_object', 'checksum')) {
                return;
            }
            Schema::table('premis_object', function ($t) {
                $t->string('checksum', 128)->nullable();
                $t->string('checksum_algorithm', 16)->nullable();
                $t->json('significant_properties')->nullable();
                $t->boolean('is_preservation_master')->nullable();
                $t->integer('digital_object_id')->nullable();
            });
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }

    /**
     * Idempotent auto-seed of ahg_premis_rights (Issue #653 Phase 1).
     *
     * Mirrors the package convention: probe via Schema::hasTable and create
     * the table if absent so fresh installs and overlay installs on legacy
     * DBs never need manual SQL execution.
     */
    protected function bootPremisRightsTable(): void
    {
        try {
            if (Schema::hasTable('ahg_premis_rights')) {
                return;
            }
            DB::statement(<<<'SQL'
                CREATE TABLE IF NOT EXISTS ahg_premis_rights (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    information_object_id INT NOT NULL,
                    rights_basis VARCHAR(32) NOT NULL,
                    rights_granted_act VARCHAR(64) NOT NULL,
                    rights_granted_restriction TEXT,
                    applicable_dates_start DATE NULL,
                    applicable_dates_end DATE NULL,
                    source_xml LONGTEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ahg_premis_rights_io (information_object_id),
                    INDEX idx_ahg_premis_rights_basis (rights_basis),
                    INDEX idx_ahg_premis_rights_act (rights_granted_act)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }

    /**
     * Idempotently register the scheduled-fixity entry in cron_schedule.
     *
     * Runs once per app boot if the entry is missing — keeps fresh installs
     * (and overlay-installs onto existing AtoM DBs) from needing manual setup.
     */
    protected function bootCronRegistration(): void
    {
        try {
            if (! Schema::hasTable('cron_schedule')) {
                return;
            }
            // The legacy slug `preservation-fixity` already exists in some installs and points
            // at an older command. We register the P3.5 scheduled-runner under a distinct slug.
            $exists = DB::table('cron_schedule')->where('slug', 'preservation-fixity-scheduled')->exists();
            if ($exists) {
                return;
            }
            DB::table('cron_schedule')->insert([
                'slug'             => 'preservation-fixity-scheduled',
                'name'             => 'Scheduled Fixity Verification (P3.5)',
                'description'      => 'Walk preservation_workflow_schedule (workflow_type=fixity_check) and verify checksums on stale digital objects. Writes preservation_workflow_run audit rows + PREMIS fixityCheck events.',
                'category'         => 'preservation',
                'artisan_command'  => 'ahg:preservation-fixity-run',
                'is_enabled'       => 1,
                'cron_expression'  => '17 4 * * *',
                'timeout_minutes'  => 120,
                'duration_hint'    => 'long',
                'log_file'         => 'logs/preservation-fixity-scheduled.log',
            ]);
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }
}
