<?php

/**
 * AhgAnnotationsServiceProvider - Service provider for AHG Annotations
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

namespace AhgAnnotations\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the IIIF Web Annotations storage package.
 *
 * Loads /api/annotations routes (Annotot-shaped) and auto-installs the
 * ahg_iiif_annotation table on first boot if it's missing. Matches the
 * AhgAuditTrailServiceProvider precedent for self-installing packages.
 */
class AhgAnnotationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');

        // Guard the hasTable() call itself — composer's post-autoload-dump
        // runs `php artisan package:discover` in CI before any DB is wired,
        // and Laravel's default sqlite fallback throws when the file is
        // absent. Skip silently in that case; install retries on next boot
        // once a real DB is reachable.
        try {
            if (!Schema::hasTable('ahg_iiif_annotation')) {
                $this->installSchema();
            }
        } catch (\Throwable $e) {
            // No DB connection — nothing to install yet.
        }
    }

    private function installSchema(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/install.sql');
        if ($sql === false || trim($sql) === '') return;
        try {
            DB::unprepared($sql);
        } catch (\Throwable $e) {
            // Don't 500 the entire app on a schema-install hiccup; the
            // table may have been created by a parallel boot or by a manual
            // mysql import. Log and move on. Hits the standard exception
            // handler and lands in ahg_error_log for follow-up.
            \Log::warning('[ahg-annotations] schema install failed: ' . $e->getMessage());
        }
    }
}
