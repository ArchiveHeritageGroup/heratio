<?php

/**
 * StatisticsSmokeTest - ServiceProvider + route + StatisticsService sanity.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgStatistics\Providers\AhgStatisticsServiceProvider;
use AhgStatistics\Services\StatisticsService;
use Tests\TestCase;

class StatisticsSmokeTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(AhgStatisticsServiceProvider::class, $providers);
    }

    public function test_statistics_service_can_be_instantiated(): void
    {
        $svc = $this->app->make(StatisticsService::class);
        $this->assertInstanceOf(StatisticsService::class, $svc);
    }

    public function test_dashboard_route_is_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'statistics.dashboard');
        $this->assertNotNull($route, 'statistics.dashboard route should be registered');
    }
}
