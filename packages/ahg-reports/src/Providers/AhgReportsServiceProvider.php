<?php

namespace AhgReports\Providers;

use AhgReports\Services\ReportService;
use Illuminate\Support\ServiceProvider;

class AhgReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReportService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-reports');
    }
}
