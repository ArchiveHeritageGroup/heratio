<?php

namespace AhgAiServices\Providers;

use AhgAiServices\Contracts\FaceDetectorInterface;
use AhgAiServices\Services\CostService;
use AhgAiServices\Services\EmbeddedMetadataContextService;
use AhgAiServices\Services\LlmService;
use AhgAiServices\Services\NerGazetteerService;
use AhgAiServices\Services\NerService;
use AhgAiServices\Services\NullFaceDetector;
use AhgAiServices\Services\QuotaService;
use AhgAiServices\Services\SuggestedConnectionsService;
use AhgAiServices\Services\TranslationMemoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgAiServicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmService::class);
        $this->app->singleton(NerService::class);
        $this->app->singleton(\AhgAiServices\Services\HtrService::class);
        $this->app->singleton(\AhgAiServices\Services\DonutService::class);
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

        // #667 Phase 1 - quota / cost / TM / gazetteer / face-detect.
        $this->app->singleton(QuotaService::class);
        $this->app->singleton(CostService::class, function ($app) {
            return new CostService($app->make(QuotaService::class));
        });
        $this->app->singleton(TranslationMemoryService::class);
        $this->app->singleton(NerGazetteerService::class);
        // #750 - per-request cache for embedded EXIF/IPTC/XMP context hints.
        $this->app->singleton(EmbeddedMetadataContextService::class);
        // #1210 - North Star generative scholarship: suggested-connections engine.
        $this->app->singleton(SuggestedConnectionsService::class, function ($app) {
            return new SuggestedConnectionsService($app->make(LlmService::class));
        });
        $this->app->singleton(FaceDetectorInterface::class, function ($app) {
            return new NullFaceDetector(
                $app->make(QuotaService::class),
                $app->make(CostService::class),
            );
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ai-services');

        $this->ensureSchema();

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

    /**
     * #667 Phase 1 - auto-install the new tables (quota, cost, pricing,
     * translation_memory, ner_custom_entity) when they are absent.
     * Follows the reference_ci_schema_hastable.md outer-try pattern so a
     * missing DB connection during CI bootstrap never blocks boot.
     */
    protected function ensureSchema(): void
    {
        try {
            $needed = !Schema::hasTable('ahg_ai_quota')
                || !Schema::hasTable('ahg_ai_call_cost')
                || !Schema::hasTable('ahg_ai_pricing')
                || !Schema::hasTable('ahg_translation_memory')
                || !Schema::hasTable('ahg_ner_custom_entity')
                || !Schema::hasTable('ahg_suggested_connection');
            if (!$needed) {
                return;
            }
            $sql = @file_get_contents(__DIR__ . '/../../database/install.sql');
            if (!$sql) {
                return;
            }
            foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                $lines = preg_split("/\r?\n/", trim($stmt));
                while ($lines && (trim($lines[0]) === '' || str_starts_with(ltrim($lines[0]), '--'))) {
                    array_shift($lines);
                }
                $stmt = trim(implode("\n", $lines));
                if ($stmt === '') {
                    continue;
                }
                try {
                    DB::statement($stmt);
                } catch (Throwable) {
                    // Idempotent re-runs may collide with already-present
                    // FK-bearing legacy rows; never abort boot.
                }
            }
        } catch (Throwable) {
            // never block boot
        }
    }
}
