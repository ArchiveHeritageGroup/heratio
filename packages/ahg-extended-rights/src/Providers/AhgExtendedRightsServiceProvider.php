<?php

namespace AhgExtendedRights\Providers;

use Illuminate\Support\ServiceProvider;

class AhgExtendedRightsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\AhgExtendedRights\Services\ExtendedRightsService::class);
        $this->app->singleton(\AhgExtendedRights\Services\EmbargoService::class);
        $this->app->singleton(\AhgExtendedRights\Services\EmbargoNotificationService::class);
        $this->app->singleton(\AhgExtendedRights\Services\RetentionScheduleService::class);
        $this->app->singleton(\AhgExtendedRights\Services\DisposalWorkflowService::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgExtendedRights\Commands\EmbargoProcessCommand::class,
                \AhgExtendedRights\Commands\EmbargoReportCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-extended-rights');
    }
}
