<?php

/**
 * AhgImageAnimateServiceProvider — Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgImageAnimate\Providers;

use AhgImageAnimate\Services\KenBurnsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgImageAnimateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KenBurnsService::class, fn () => new KenBurnsService());
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-image-animate');

        $this->commands([
            \AhgImageAnimate\Commands\ImageAnimateCommand::class,
        ]);

        $this->ensureInstalled();
    }

    /**
     * One-shot install + seed on first boot. Idempotent — uses CREATE TABLE
     * IF NOT EXISTS and INSERT IGNORE so it's safe to run on every request.
     */
    protected function ensureInstalled(): void
    {
        try {
            if (!Schema::hasTable('image_animate_settings') || !Schema::hasTable('object_image_animation')) {
                DB::unprepared(file_get_contents(__DIR__ . '/../../database/install.sql'));
            }
            $count = DB::table('image_animate_settings')->count();
            if ($count === 0) {
                DB::unprepared(file_get_contents(__DIR__ . '/../../database/seed_settings.sql'));
            }
        } catch (\Throwable $e) {
            // Don't break the request; surface via logs instead.
            \Log::warning('[ahg-image-animate] install/seed failed: ' . $e->getMessage());
        }
    }
}
