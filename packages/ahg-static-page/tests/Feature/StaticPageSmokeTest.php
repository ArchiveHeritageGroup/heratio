<?php

/**
 * StaticPageSmokeTest - ServiceProvider + route registration sanity.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgStaticPage\Providers\AhgStaticPageServiceProvider;
use Tests\TestCase;

class StaticPageSmokeTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(AhgStaticPageServiceProvider::class, $providers);
    }

    public function test_about_route_is_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'staticpage.about');
        $this->assertNotNull($route, 'staticpage.about route should be registered');
    }

    public function test_view_namespace_is_loaded(): void
    {
        $finder = $this->app['view']->getFinder();
        $hints = method_exists($finder, 'getHints') ? $finder->getHints() : [];
        $this->assertArrayHasKey('ahg-static-page', $hints);
    }
}
