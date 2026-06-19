<?php

/**
 * AhgRicServiceProvider - RIC-O Services Provider
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgRic\Providers;

use AhgRic\Services\FusekiSyncService;
use AhgRic\Services\RelationshipService;
use AhgRic\Services\RicEntityService;
use AhgRic\Services\RicSerializationService;
use AhgRic\Services\ShaclValidationService;
use AhgRic\Services\SparqlQueryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AhgRicServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Expose RicApiClient as a singleton so controllers can inject it.
        $this->app->singleton(\AhgRic\Http\RicApiClient::class, function () {
            return new \AhgRic\Http\RicApiClient();
        });

        // Register RelationshipService
        $this->app->singleton(RelationshipService::class);

        // Register RicSerializationService
        $this->app->singleton(RicSerializationService::class);

        // Register ShaclValidationService
        $this->app->singleton(ShaclValidationService::class);

        // Register SparqlQueryService
        $this->app->singleton(SparqlQueryService::class);

        // Register RicEntityService
        $this->app->singleton(RicEntityService::class, function () {
            return new RicEntityService(app()->getLocale());
        });

        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ahg-ric.php',
            'ahg-ric'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load web routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Always load /api/ric/v1/* routes locally. Per the separation plan
        // (memory: project_ric_separation_plan.md) the relational ric_* tables
        // stay in Heratio; OpenRiC handles graph/SPARQL READS only. Even
        // post-split, write surfaces (modal entity create, relation editor)
        // need a local endpoint that accepts the admin's session cookie —
        // otherwise the embedded JS would have to ship an API key to call
        // OpenRiC cross-origin, which we don't do. Reads can still go to
        // RIC_API_BASE = config('ric.api_url') for graph traversal.
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ric');

        // #1321 deprecate-not-delete register. Idempotent; probe + create wrapped
        // in one try (reference_ci_schema_hastable) so a brand-new install never
        // fatals the provider boot.
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('ric_deprecated_entity')) {
                \Illuminate\Support\Facades\DB::statement(
                    'CREATE TABLE IF NOT EXISTS `ric_deprecated_entity` (
                        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `entity_type` VARCHAR(40) NOT NULL,
                        `entity_id` INT NOT NULL,
                        `reason` TEXT NULL,
                        `superseded_by_iri` VARCHAR(1024) NULL,
                        `deprecated_by` VARCHAR(190) NULL,
                        `deprecated_at` DATETIME NOT NULL,
                        UNIQUE KEY `uq_ric_deprecated` (`entity_type`, `entity_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }

            // #1321 AI-assertion provenance register: which entities/edges were
            // machine-inferred (so export can stamp PROV-O and distinguish them
            // from asserted fact). Idempotent.
            if (! \Illuminate\Support\Facades\Schema::hasTable('ric_inferred_assertion')) {
                \Illuminate\Support\Facades\DB::statement(
                    'CREATE TABLE IF NOT EXISTS `ric_inferred_assertion` (
                        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `entity_type` VARCHAR(40) NOT NULL,
                        `entity_id` INT NOT NULL,
                        `predicate` VARCHAR(190) NULL,
                        `model` VARCHAR(190) NOT NULL,
                        `confidence` DECIMAL(5,4) NULL,
                        `receipt_id` VARCHAR(190) NULL,
                        `human_confirmed` VARCHAR(190) NULL,
                        `created_at` DATETIME NOT NULL,
                        UNIQUE KEY `uq_ric_inferred` (`entity_type`, `entity_id`, `predicate`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal: deprecation / provenance emission stays inert until the tables exist.
        }

        // Register artisan commands (only when running in console — cheap guard).
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgRic\Console\Commands\VerifySplit::class,
                \AhgRic\Console\Commands\IssueKey::class,
                \AhgRic\Console\Commands\RebuildNestedSet::class,
                \AhgRic\Console\Commands\SeedDemo::class,
                // #77 phase 2: Fuseki integrity + orphan cleanup
                \AhgRic\Console\Commands\FusekiIntegrityCheckCommand::class,
                \AhgRic\Console\Commands\FusekiOrphanCleanupCommand::class,
                // #139: bulk-load RiC agent/place instances into Fuseki
                \AhgRic\Console\Commands\FusekiInstanceLoadCommand::class,
                // #1197/#1214: push CIDOC-CRM named graphs into Fuseki
                \AhgRic\Console\Commands\CrmGraphSyncCommand::class,
                // #1319: RiC-O SHACL conformance gate
                \AhgRic\Console\Commands\RicConformanceCommand::class,
            ]);

            // #77 phase 2: schedule integrity check from fuseki_integrity_schedule
            // setting (cron expression; empty disables the schedule). Orphan
            // cleanup runs daily at 03:30; the command itself no-ops when
            // fuseki_orphan_retention_days = 0. Both schedules are registered
            // unconditionally so a config-time setting change is picked up
            // without needing an artisan re-cache.
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                try {
                    $raw = trim((string) (\Illuminate\Support\Facades\DB::table('ahg_settings')
                        ->where('setting_key', 'fuseki_integrity_schedule')
                        ->value('setting_value') ?? '0 4 * * *'));
                    // The settings form stores friendly tokens (daily/weekly/
                    // monthly/disabled); map them to cron expressions. A raw
                    // 5-field cron string is accepted as-is. 'disabled'/'' = off.
                    $tokenMap = [
                        'daily'    => '0 4 * * *',
                        'weekly'   => '0 4 * * 0',
                        'monthly'  => '0 4 1 * *',
                        'disabled' => '',
                        ''         => '',
                    ];
                    $cron = array_key_exists($raw, $tokenMap) ? $tokenMap[$raw] : $raw;
                    if ($cron !== '') {
                        $schedule->command('ahg:fuseki-integrity-check --quiet-success')
                            ->cron($cron)
                            ->withoutOverlapping(60);
                    }
                } catch (\Throwable $e) {
                    // Settings table missing on a brand-new install: skip the
                    // schedule registration silently. Once the table exists
                    // and the operator saves the setting, the next boot picks
                    // it up.
                }
                $schedule->command('ahg:fuseki-orphan-cleanup')
                    ->dailyAt('03:30')
                    ->withoutOverlapping(60);
            });
        }

        // Expose the RiC API base URL to every Blade view so embedded JS can
        // resolve `/api/ric/v1` OR a post-split external service URL with
        // zero template changes.
        \Illuminate\Support\Facades\View::share(
            'ricApiBase',
            rtrim(config('ric.api_url') ?: url('/api/ric/v1'), '/')
        );

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/ahg-ric.php' => config_path('ahg-ric.php'),
        ], 'ahg-ric-config');

        // Publish assets
        $this->publishes([
            __DIR__ . '/../../resources' => resource_path('views/vendor/ahg-ric'),
        ], 'ahg-ric-views');
    }
}
