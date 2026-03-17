<?php

namespace AhgFavorites\Providers;

use AhgFavorites\Services\FavoritesService;
use AhgFavorites\Services\FolderService;
use Illuminate\Support\ServiceProvider;

class AhgFavoritesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FavoritesService::class);
        $this->app->singleton(FolderService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-favorites');
    }
}
