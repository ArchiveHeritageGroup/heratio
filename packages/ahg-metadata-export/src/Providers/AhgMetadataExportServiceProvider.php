<?php

/**
 * AhgMetadataExportServiceProvider - boot routes, views, and best-effort
 * auto-install of the metadata-export schema (including the #662 Phase 3
 * RAD + DACS sidecar tables).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

namespace AhgMetadataExport\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgMetadataExportServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-metadata-export');

        // EAD PDF finding-aid generator (#657 Phase 1, item 4) +
        // whole-collection CIDOC-CRM graph dump (#1197 / #1204).
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgMetadataExport\Console\Commands\EadFindingAidCommand::class,
                \AhgMetadataExport\Console\Commands\ExportCidocGraphCommand::class,
            ]);
        }

        // Best-effort install for the RAD + DACS sidecar tables introduced
        // by #662 Phase 3. Wrap the whole thing in one outer try/catch so
        // CI environments without a DB don't blow up boot - mirrors the
        // pattern used by ahg-annotations / ahg-research providers and
        // documented in memory/reference_ci_schema_hastable.md.
        try {
            if (! Schema::hasTable('ahg_io_rad') || ! Schema::hasTable('ahg_io_dacs') || ! Schema::hasTable('metadata_export_config')) {
                $this->installSchema();
            }
        } catch (\Throwable $e) {
            // No DB connection - nothing to install yet. Boot continues.
        }
    }

    private function installSchema(): void
    {
        $sqlPath = __DIR__.'/../../database/install.sql';
        $sql = @file_get_contents($sqlPath);
        if ($sql === false || trim($sql) === '') {
            return;
        }
        try {
            DB::unprepared($sql);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-metadata-export] schema install failed: '.$e->getMessage());
        }
    }
}
