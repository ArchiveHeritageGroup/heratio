<?php

namespace AhgMetadataExtraction\Providers;

use AhgMetadataExtraction\Services\MetadataExtractionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AhgMetadataExtractionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetadataExtractionService::class, function () {
            return new MetadataExtractionService();
        });
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-metadata-extraction');
    }
}
