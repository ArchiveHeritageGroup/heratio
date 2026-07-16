<?php

/**
 * Heratio - Term Taxonomy package service provider.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems / The Archive and
 * Heritage Group (Pty) Ltd. Released under the AGPL-3.0-or-later licence.
 */

namespace AhgTermTaxonomy\Providers;

use AhgTermTaxonomy\Console\SkosValidateCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgTermTaxonomyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-term-taxonomy');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // #661 Phase 3 -- ensure ahg_term_cross_match table exists. Wrapped in
        // a single try so a missing connection during CI / asset-only builds
        // never blocks the rest of the boot pipeline (see
        // reference_ci_schema_hastable.md). All probes go through the same DB
        // grant so any failure mode collapses to "skip table install".
        try {
            if (! Schema::hasTable('ahg_term_cross_match')) {
                $sql = @file_get_contents(__DIR__.'/../../database/install.sql');
                if ($sql) {
                    DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            // No DB during boot (CI / asset compile). Safe to skip.
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                SkosValidateCommand::class,
            ]);
        }
    }
}
