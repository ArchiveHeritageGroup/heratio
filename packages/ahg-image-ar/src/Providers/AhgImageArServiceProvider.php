<?php

/**
 * AhgImageArServiceProvider — Heratio
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

namespace AhgImageAr\Providers;

use AhgImageAr\Services\KenBurnsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgImageArServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KenBurnsService::class, fn () => new KenBurnsService());
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-image-ar');

        $this->commands([
            \AhgImageAr\Commands\ImageArCommand::class,
        ]);

        $this->ensureInstalled();
    }

    protected function ensureInstalled(): void
    {
        try {
            if (!Schema::hasTable('image_ar_settings') || !Schema::hasTable('object_image_ar')) {
                DB::unprepared(file_get_contents(__DIR__ . '/../../database/install.sql'));
            }
            if (DB::table('image_ar_settings')->count() === 0) {
                DB::unprepared(file_get_contents(__DIR__ . '/../../database/seed_settings.sql'));
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-image-ar] install/seed failed: ' . $e->getMessage());
        }
    }
}
