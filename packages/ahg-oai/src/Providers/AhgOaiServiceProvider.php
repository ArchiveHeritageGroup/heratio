<?php

namespace AhgOai\Providers;

use Illuminate\Support\ServiceProvider;

class AhgOaiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgOai\Console\Commands\OaiMarkDeletedCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-oai');
    }
}
