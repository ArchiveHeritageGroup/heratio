<?php

/**
 * AhgFavoritesServiceProvider
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
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgFavorites\Providers;

use AhgFavorites\Services\FavoritesExportService;
use AhgFavorites\Services\FavoritesService;
use AhgFavorites\Services\FolderService;
use AhgFavorites\Services\ResearchBridgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgFavoritesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FavoritesService::class);
        $this->app->singleton(FolderService::class);
        $this->app->singleton(ResearchBridgeService::class);
        $this->app->singleton(FavoritesExportService::class);
    }

    public function boot(): void
    {
        try {
            // Probe + idempotent install. Skip if already present.
            if (! Schema::hasTable('favorites') || ! Schema::hasTable('favorites_folder')) {
                $sql = file_get_contents(__DIR__.'/../../database/install.sql');
                if ($sql) {
                    DB::unprepared($sql);
                }
            } elseif (Schema::hasTable('favorites') && ! Schema::hasColumn('favorites', 'url')) {
                // Older installs created the table before the custom-entity
                // URL column existed. Backfill it without touching anything
                // else (idempotent if already added).
                Schema::table('favorites', function ($t) {
                    $t->string('url', 1024)->nullable()->after('slug');
                });
            }

            // Seed dropdown values whenever the favorites taxonomy is empty.
            if (Schema::hasTable('ahg_dropdown')) {
                $missingTaxonomies = collect(['favorites_object_type', 'favorites_visibility', 'favorites_export_format'])
                    ->filter(fn ($tax) => ! DB::table('ahg_dropdown')->where('taxonomy', $tax)->exists())
                    ->values();
                if ($missingTaxonomies->isNotEmpty()) {
                    $sql = file_get_contents(__DIR__.'/../../database/install.sql');
                    if ($sql && preg_match('/-- Dropdown seeds.*$/s', $sql, $m)) {
                        // Run only the seed block to avoid re-attempting CREATE TABLE
                        DB::unprepared($m[0]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Boot must never fatal in production. CI catches install errors
            // separately via the dedicated migration.
            if (function_exists('logger')) {
                logger()->warning('[ahg-favorites] boot install failed: '.$e->getMessage());
            }
        }

        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-favorites');
    }
}
