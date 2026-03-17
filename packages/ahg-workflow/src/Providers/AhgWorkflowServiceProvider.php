<?php

namespace AhgWorkflow\Providers;

use AhgWorkflow\Services\WorkflowService;
use Illuminate\Support\ServiceProvider;

class AhgWorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkflowService::class, function () {
            return new WorkflowService();
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-workflow');
    }
}
