<?php

namespace AhgSearch\Providers;

use AhgSearch\Services\BlendedSearchService;
use AhgSearch\Services\ElasticsearchService;
use AhgSearch\Services\SearchAnalyticsService;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ahg-search.php', 'ahg-search');

        $this->app->singleton(ElasticsearchService::class);
        $this->app->singleton(VectorSearchService::class);
        $this->app->singleton(BlendedSearchService::class);
        $this->app->singleton(SearchAnalyticsService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSearch\Commands\EsReindexCommand::class,
            ]);
        }

        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');

        // #1095 - JSON discovery API. Mounted on the stateless `api` group so
        // POST requests don't require a CSRF token (they are token/throttle
        // protected instead). Per-route throttle is declared inside the file.
        if (is_file(__DIR__.'/../../routes/api.php')) {
            \Illuminate\Support\Facades\Route::middleware('api')
                ->group(__DIR__.'/../../routes/api.php');
        }

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-search');

        // #650 Phase 3 - auto-install the analytics log table on first boot.
        // Same defensive try/catch pattern as the rest of the codebase so a
        // missing DB / migration race never blocks app boot. See
        // reference_ci_schema_hastable.md - the hasTable() probe stays
        // inside the try.
        try {
            if (! Schema::hasTable('ahg_search_query_log')) {
                $sql = file_get_contents(__DIR__.'/../../database/install.sql');
                if ($sql !== false) {
                    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                        if ($stmt === '' || str_starts_with($stmt, '--')) {
                            continue;
                        }
                        DB::unprepared($stmt.';');
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('ahg-search install.sql auto-run skipped: '.$e->getMessage());
        }
    }
}
