<?php

namespace AhgTranslation\Providers;

use AhgTranslation\Console\Commands\ImportJsonToDbCommand;
use AhgTranslation\Translation\DbAwareLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgTranslationServiceProvider extends ServiceProvider
{
    /**
     * Decorate the framework's translation.loader with our DB-first wrapper
     * before Laravel's TranslationServiceProvider builds the translator
     * singleton. The decorator transparently passes through PHP-array and
     * namespaced loads; only the JSON-namespace path (the `__('Some key')`
     * call site) merges ui_string rows on top of file values.
     *
     * Issue #57 - unify UI-string storage.
     */
    public function register(): void
    {
        $this->app->extend('translation.loader', function ($loader, $app) {
            return new DbAwareLoader($loader);
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-translation');

        // Auto-install ui_string + ui_string_change on first boot (idempotent).
        // Outer try/catch covers both the hasTable probe and the install: CI
        // runs without a real DB and the probe falls through to the default
        // sqlite driver, which throws on missing database.sqlite. Without the
        // outer try, that throw propagates up and fails the package:discover
        // step (reference_ci_schema_hastable.md).
        try {
            if (!Schema::hasTable('ui_string') || !Schema::hasTable('ui_string_change')) {
                $this->installSchema();
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-translation] schema install failed: ' . $e->getMessage());
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportJsonToDbCommand::class,
            ]);
        }
    }

    private function installSchema(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/install.sql');
        if ($sql === false || trim($sql) === '') return;
        try {
            DB::unprepared($sql);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-translation] schema install failed: ' . $e->getMessage());
        }
    }
}
