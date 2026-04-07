<?php

namespace AhgLibrary\Providers;

use Illuminate\Support\ServiceProvider;

class AhgLibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-library');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgLibrary\Console\Commands\ImportLibraryCsvCommand::class,
            ]);
        }
    }
}
