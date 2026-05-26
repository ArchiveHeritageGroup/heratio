<?php

/**
 * ThemeB5SmokeTest - ServiceProvider + ThemeService + view namespaces sanity.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgThemeB5\Providers\AhgThemeB5ServiceProvider;
use AhgThemeB5\Services\ThemeService;
use Tests\TestCase;

class ThemeB5SmokeTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(AhgThemeB5ServiceProvider::class, $providers);
    }

    public function test_theme_service_is_a_singleton(): void
    {
        $a = $this->app->make(ThemeService::class);
        $b = $this->app->make(ThemeService::class);
        $this->assertSame($a, $b, 'ThemeService should be bound as a singleton');
    }

    public function test_theme_view_namespaces_are_loaded(): void
    {
        $finder = $this->app['view']->getFinder();
        $hints = method_exists($finder, 'getHints') ? $finder->getHints() : [];
        $this->assertArrayHasKey('theme', $hints, 'theme:: namespace should be registered');
        $this->assertArrayHasKey('ahg-theme-b5', $hints, 'ahg-theme-b5:: alias namespace should be registered');
    }
}
