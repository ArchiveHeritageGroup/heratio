<?php

/**
 * AhgImageArServiceProvider — Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgImageAr\Providers;

use AhgImageAr\Services\AnimationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgImageArServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AnimationService::class, fn () => new AnimationService());
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
            $this->upgradeColumns();
            if (DB::table('image_ar_settings')->count() === 0) {
                DB::unprepared(file_get_contents(__DIR__ . '/../../database/seed_settings.sql'));
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-image-ar] install/seed failed: ' . $e->getMessage());
        }
    }

    protected function upgradeColumns(): void
    {
        $columns = [
            'mp4_width'           => 'INT DEFAULT NULL AFTER `mp4_duration_secs`',
            'mp4_height'          => 'INT DEFAULT NULL AFTER `mp4_width`',
            'mp4_fps'             => 'INT DEFAULT NULL AFTER `mp4_height`',
            'ai_model'            => 'VARCHAR(64) DEFAULT NULL AFTER `mp4_motion`',
            'ai_prompt'           => 'TEXT DEFAULT NULL AFTER `ai_model`',
            'ai_seed'             => 'BIGINT DEFAULT NULL AFTER `ai_prompt`',
            'ai_motion_bucket_id' => 'INT DEFAULT NULL AFTER `ai_seed`',
            'generation_secs'     => 'DECIMAL(7,2) DEFAULT NULL AFTER `ai_motion_bucket_id`',
        ];
        foreach ($columns as $col => $def) {
            if (!Schema::hasColumn('object_image_ar', $col)) {
                DB::statement("ALTER TABLE `object_image_ar` ADD COLUMN `{$col}` {$def}");
            }
        }
    }
}
