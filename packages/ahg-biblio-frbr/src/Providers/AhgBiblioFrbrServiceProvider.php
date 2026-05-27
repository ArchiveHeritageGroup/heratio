<?php

namespace AhgBiblioFrbr\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AhgBiblioFrbrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-biblio-frbr');

        Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgBiblioFrbr\Console\Commands\FrbrBackfillWorkKeysCommand::class,
            ]);
        }
    }
}
