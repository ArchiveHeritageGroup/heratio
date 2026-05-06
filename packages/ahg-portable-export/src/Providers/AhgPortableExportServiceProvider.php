<?php

namespace AhgPortableExport\Providers;

use Illuminate\Support\ServiceProvider;

class AhgPortableExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-portable-export');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgPortableExport\Console\Commands\BundleWorkerCommand::class,
            ]);

            // Daily safety-net sweep for any pending rows the queue dispatch
            // missed (e.g. queue worker was down when apiStart ran). Runs
            // 04:15 to give audit:prune (03:30) headroom. --all-pending
            // drains everything still pending; PortableCleanupCommand runs
            // separately on its own schedule.
            $this->app->afterResolving(\Illuminate\Console\Scheduling\Schedule::class, function (\Illuminate\Console\Scheduling\Schedule $schedule) {
                $schedule->command('ahg:portable-export-worker --all-pending')
                    ->dailyAt('04:15')
                    ->withoutOverlapping();
            });
        }
    }
}
