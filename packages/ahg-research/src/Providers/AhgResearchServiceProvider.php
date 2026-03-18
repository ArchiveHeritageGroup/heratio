<?php

namespace AhgResearch\Providers;

use Illuminate\Support\ServiceProvider;

class AhgResearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'research');
    }

    public function register(): void
    {
        //
    }
}
