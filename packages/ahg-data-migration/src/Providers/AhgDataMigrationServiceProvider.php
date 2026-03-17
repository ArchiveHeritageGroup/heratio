<?php

namespace AhgDataMigration\Providers;

use AhgDataMigration\Services\DataMigrationService;
use Illuminate\Support\ServiceProvider;

class AhgDataMigrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DataMigrationService::class, function ($app) {
            return new DataMigrationService();
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-data-migration');
    }
}
