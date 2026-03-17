<?php

namespace AhgAiServices\Providers;

use AhgAiServices\Services\LlmService;
use AhgAiServices\Services\NerService;
use Illuminate\Support\ServiceProvider;

class AhgAiServicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmService::class);
        $this->app->singleton(NerService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ai-services');
    }
}
