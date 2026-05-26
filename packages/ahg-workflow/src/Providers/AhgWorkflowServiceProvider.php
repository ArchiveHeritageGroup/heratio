<?php

namespace AhgWorkflow\Providers;

use AhgWorkflow\Services\WorkflowService;
use Illuminate\Support\ServiceProvider;

class AhgWorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkflowService::class, function () {
            return new WorkflowService;
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-workflow');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgWorkflow\Console\Commands\SeedSpectrumCommand::class,
                \AhgWorkflow\Console\Commands\SpectrumOverdueCommand::class,
                // Phase 3 of #674 — daily overdue-task notification sweep.
                \AhgWorkflow\Console\Commands\WorkflowNotifyOverdueCommand::class,
            ]);

            // Schedule daily at 09:00. withoutOverlapping() guards against
            // a slow run still working when the next nightly trigger fires.
            $this->app->afterResolving(\Illuminate\Console\Scheduling\Schedule::class, function (\Illuminate\Console\Scheduling\Schedule $schedule) {
                $schedule->command('workflow:notify-overdue')
                    ->dailyAt('09:00')
                    ->withoutOverlapping();
            });
        }
    }
}
