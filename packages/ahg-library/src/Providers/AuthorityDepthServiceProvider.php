<?php

namespace AhgLibrary\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** heratio#1475 item 3 - authority hierarchy and vocabulary sync. */
class AuthorityDepthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../../routes/authority.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgLibrary\Console\Commands\AuthoritySyncCommand::class,
                \AhgLibrary\Console\Commands\AuthorityBackfillHierarchyCommand::class,
            ]);
        }
    }
}
