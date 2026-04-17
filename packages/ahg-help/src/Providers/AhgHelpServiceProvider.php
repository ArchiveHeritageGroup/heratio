<?php

namespace AhgHelp\Providers;

use Illuminate\Support\ServiceProvider;

class AhgHelpServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-help');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgHelp\Commands\IngestHelpArticleCommand::class,
            ]);
        }
    }
}
