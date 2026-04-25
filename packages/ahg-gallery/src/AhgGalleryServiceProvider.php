<?php

namespace AhgGallery;

use Illuminate\Support\ServiceProvider;

class AhgGalleryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ahg-gallery');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgGallery\Console\Commands\ImportGalleryCsvCommand::class,
                \AhgGallery\Console\Commands\SeedGalleryDemoCommand::class,
            ]);
        }
    }
}
