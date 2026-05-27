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
    }
}
