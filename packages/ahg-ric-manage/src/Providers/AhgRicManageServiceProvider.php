<?php

/**
 * AhgRicManageServiceProvider
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL-3.0-or-later.
 *
 * Self-contained: the plugin boots and serves its edit/show surface with only
 * ahg/ric present. Everything it reaches beyond that is guarded, so it installs
 * standalone on a client server (#1425).
 */

namespace AhgRicManage\Providers;

use AhgRicManage\Database\Seeders\RicTemplateTermSeeder;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgRicManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ric-manage.php', 'ric-manage');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ric-manage');
        $this->publishes([
            __DIR__.'/../../config/ric-manage.php' => config_path('ric-manage.php'),
        ], 'ric-manage-config');

        // Self-seed the taxonomy-70 'ric' term so a fresh client install gets
        // "Records in Contexts" in the Display-standard dropdown with no manual
        // step. Cheap (single existence check) and never throws.
        try {
            RicTemplateTermSeeder::ensure();
        } catch (Throwable $e) {
            // Never block boot on seeding.
        }
    }
}
