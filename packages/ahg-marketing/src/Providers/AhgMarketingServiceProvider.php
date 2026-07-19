<?php

/**
 * AhgMarketingServiceProvider - registers the self-contained marketing pages:
 * the AtoM comparison page (/compare/atom), the migration-assessment lead form
 * (/migration/assessment GET + POST), and their own standalone Blade layout.
 *
 * All routes are two path segments, so they sit safely outside the locked
 * `/{slug}` single-segment catch-all in ahg-information-object-manage and can be
 * registered with a plain loadRoutesFrom (see reference_slug_catchall_route_precedence).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 *
 * @license AGPL-3.0-or-later
 */

namespace AhgMarketing\Providers;

use Illuminate\Support\ServiceProvider;

class AhgMarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'marketing');
    }
}
