<?php

/**
 * VersionControlSmokeTest - ServiceProvider + DiffComputer + route sanity.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgVersionControl\Providers\AhgVersionControlServiceProvider;
use AhgVersionControl\Services\DiffComputer;
use Tests\TestCase;

class VersionControlSmokeTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(AhgVersionControlServiceProvider::class, $providers);
    }

    public function test_diff_computer_can_be_instantiated(): void
    {
        $svc = $this->app->make(DiffComputer::class);
        $this->assertInstanceOf(DiffComputer::class, $svc);
    }

    public function test_version_list_route_is_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'version-control.list');
        $this->assertNotNull($route, 'version-control.list route should be registered');
    }

    public function test_version_diff_route_is_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'version-control.diff');
        $this->assertNotNull($route, 'version-control.diff route should be registered');
    }
}
