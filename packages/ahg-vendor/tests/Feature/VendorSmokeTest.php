<?php

/**
 * VendorSmokeTest - ServiceProvider + VendorStatusService + route sanity.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgVendor\Providers\AhgVendorServiceProvider;
use AhgVendor\Services\VendorStatusService;
use Tests\TestCase;

class VendorSmokeTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(AhgVendorServiceProvider::class, $providers);
    }

    public function test_vendor_status_service_can_be_instantiated(): void
    {
        $svc = $this->app->make(VendorStatusService::class);
        $this->assertInstanceOf(VendorStatusService::class, $svc);
    }

    public function test_vendor_dashboard_route_is_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'ahgvendor.index');
        $this->assertNotNull($route, 'ahgvendor.index route should be registered');
    }

    public function test_vendor_view_namespace_is_loaded(): void
    {
        $finder = $this->app['view']->getFinder();
        $hints = method_exists($finder, 'getHints') ? $finder->getHints() : [];
        $this->assertArrayHasKey('vendor', $hints);
    }
}
