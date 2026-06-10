<?php

namespace AhgSemanticSearch\Providers;

use Illuminate\Support\ServiceProvider;

class AhgSemanticSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-semantic-search');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSemanticSearch\Console\Commands\KmGraphSyncCommand::class,
                \AhgSemanticSearch\Console\Commands\ScholarshipDiscoverCommand::class,
            ]);
        }
    }
}
