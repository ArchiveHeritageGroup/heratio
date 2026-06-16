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

        // Share whether an AI services key is loaded so views can hide the
        // AI Tools (Describe / NER / Summary / Translate / Animate) when none
        // is configured — those tools all call the AI gateway and would error
        // without a key. Guarded so a missing settings table never breaks boot.
        if (! $this->app->runningInConsole()) {
            \Illuminate\Support\Facades\View::share('aiConfigured', $this->aiKeyConfigured());
        }

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
    /**
     * Whether an AI services API key is configured. The live gateway key lives
     * in ahg_ner_settings.api_key (it shadows ahg_ai_settings); we check both
     * for setting_key='api_key' with a non-empty value. Used to gate the AI
     * Tools UI so a keyless install doesn't show tools that would error.
     */
    protected function aiKeyConfigured(): bool
    {
        try {
            foreach (['ahg_ner_settings', 'ahg_ai_settings'] as $t) {
                if (! Schema::hasTable($t)) {
                    continue;
                }
                $key = DB::table($t)->where('setting_key', 'api_key')->value('setting_value');
                if (is_string($key) && trim($key) !== '') {
                    return true;
                }
            }
        } catch (Throwable) {
            // never block view rendering
        }

        return false;
    }

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
