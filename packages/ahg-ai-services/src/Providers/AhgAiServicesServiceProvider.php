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
        $this->app->singleton(\AhgAiServices\Services\HtrService::class);
        // #665 Phase 4 - OCR services
        $this->app->singleton(\AhgAiServices\Services\OcrLlmCorrector::class, function ($app) {
            return new \AhgAiServices\Services\OcrLlmCorrector($app->make(LlmService::class));
        });
        $this->app->singleton(\AhgAiServices\Services\OcrService::class, function ($app) {
            return new \AhgAiServices\Services\OcrService(
                $app->make(LlmService::class),
                $app->make(\AhgAiServices\Services\OcrLlmCorrector::class),
            );
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ai-services');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgAiServices\Commands\AiNerExtractCommand::class,
                \AhgAiServices\Commands\AiTranslateCommand::class,
                \AhgAiServices\Commands\AiProcessPendingCommand::class,
                \AhgAiServices\Commands\AiSuggestDescriptionCommand::class,
                \AhgAiServices\Commands\AiHtrCommand::class,
                \AhgAiServices\Commands\AiSpellcheckCommand::class,
                \AhgAiServices\Commands\AiNerSyncCommand::class,
                \AhgAiServices\Commands\AiSyncEntityCacheCommand::class,
                \AhgAiServices\Commands\AiInstallCommand::class,
                \AhgAiServices\Commands\AiConditionScanCommand::class,
                \AhgAiServices\Commands\AiConditionStatusCommand::class,
                \AhgAiServices\Commands\AiSummarizeCommand::class,
                \AhgAiServices\Commands\QdrantIndexCommand::class,
                \AhgAiServices\Commands\QdrantImageIndexCommand::class,
                \AhgAiServices\Commands\LlmHealthCheckCommand::class,
                // #665 Phase 4
                \AhgAiServices\Commands\TesseractListLanguagesCommand::class,
                \AhgAiServices\Commands\OcrPageCommand::class,
            ]);
        }
    }
}
