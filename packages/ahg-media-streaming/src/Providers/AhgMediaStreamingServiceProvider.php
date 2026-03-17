<?php

namespace AhgMediaStreaming\Providers;

use AhgMediaStreaming\Services\StreamingService;
use AhgMediaStreaming\Services\TranscodingService;
use Illuminate\Support\ServiceProvider;

class AhgMediaStreamingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranscodingService::class, function () {
            return new TranscodingService();
        });

        $this->app->singleton(StreamingService::class, function ($app) {
            return new StreamingService($app->make(TranscodingService::class));
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
    }
}
