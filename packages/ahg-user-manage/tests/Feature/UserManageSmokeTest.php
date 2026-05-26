<?php

/**
 * UserManageSmokeTest - ServiceProvider + UserService + route sanity.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgUserManage\Providers\AhgUserManageServiceProvider;
use AhgUserManage\Services\UserService;
use Tests\TestCase;

class UserManageSmokeTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(AhgUserManageServiceProvider::class, $providers);
    }

    public function test_user_service_can_be_instantiated(): void
    {
        $svc = $this->app->make(UserService::class);
        $this->assertInstanceOf(UserService::class, $svc);
    }

    public function test_user_browse_route_is_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'user.browse');
        $this->assertNotNull($route, 'user.browse route should be registered');
    }

    public function test_user_profile_route_is_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'user.profile');
        $this->assertNotNull($route, 'user.profile route should be registered');
    }
}
