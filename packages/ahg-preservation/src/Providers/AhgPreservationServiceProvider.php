<?php

/**
 * AhgPreservationServiceProvider
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgPreservation\Providers;

use AhgPreservation\Console\RunFixitySchedulesCommand;
use AhgPreservation\Services\BagItService;
use AhgPreservation\Services\OaisLifecycleService;
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
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-preservation');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunFixitySchedulesCommand::class,
            ]);
        }

        $this->bootCronRegistration();
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
