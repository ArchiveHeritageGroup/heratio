<?php

/**
 * ShareLinkSmokeTest - sanity check that the share-link package wires into the
 * Heratio container correctly. No DB writes, no HTTP, no token math beyond a
 * shape assertion.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgShareLink\Providers\AhgShareLinkServiceProvider;
use AhgShareLink\Services\TokenService;
use Tests\TestCase;

class ShareLinkSmokeTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(AhgShareLinkServiceProvider::class, $providers);
    }

    public function test_token_service_can_be_instantiated(): void
    {
        $svc = $this->app->make(TokenService::class);
        $this->assertInstanceOf(TokenService::class, $svc);
    }

    public function test_recipient_route_is_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'share-link.recipient');
        $this->assertNotNull($route, 'share-link.recipient route should be registered');
    }
}
