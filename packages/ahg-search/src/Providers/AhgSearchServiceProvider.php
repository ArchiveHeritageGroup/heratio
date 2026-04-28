<?php

namespace AhgSearch\Providers;

use AhgSearch\Services\BlendedSearchService;
use AhgSearch\Services\ElasticsearchService;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Support\ServiceProvider;

class AhgSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ElasticsearchService::class);
        $this->app->singleton(VectorSearchService::class);
        $this->app->singleton(BlendedSearchService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSearch\Commands\EsReindexCommand::class,
            ]);
        }

        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-search');
    }
}
