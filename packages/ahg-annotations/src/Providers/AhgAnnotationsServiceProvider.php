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

use Illuminate\Database\Schema\Blueprint;
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
        Route::middleware('web')->group(__DIR__.'/../../routes/web.php');

        // Guard the hasTable() call itself - composer's post-autoload-dump
        // runs `php artisan package:discover` in CI before any DB is wired,
        // and Laravel's default sqlite fallback throws when the file is
        // absent. Skip silently in that case; install retries on next boot
        // once a real DB is reachable.
        //
        // Per reference_ci_schema_hastable.md the probe and the install
        // share one outer try/catch so a fresh DB doesn't fault between
        // them.
        try {
            if (! Schema::hasTable('ahg_iiif_annotation')) {
                $this->installSchema();
            }
            // Schema builder runs additive ALTER TABLE statements
            // portably across MySQL + MariaDB - MySQL 8 rejects the
            // ADD KEY IF NOT EXISTS syntax we used to ship inline in
            // install.sql, so the column-add path lives here now.
            $this->backfillSchema();
        } catch (\Throwable $e) {
            // No DB connection - nothing to install yet.
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgAnnotations\Console\BackfillNerColumnsCommand::class,
            ]);
        }
    }

    private function installSchema(): void
    {
        $sql = file_get_contents(__DIR__.'/../../database/install.sql');
        if ($sql === false || trim($sql) === '') {
            return;
        }
        try {
            DB::unprepared($sql);
        } catch (\Throwable $e) {
            // Don't 500 the entire app on a schema-install hiccup; the
            // table may have been created by a parallel boot or by a manual
            // mysql import. Log and move on. Hits the standard exception
            // handler and lands in ahg_error_log for follow-up.
            \Log::warning('[ahg-annotations] schema install failed: '.$e->getMessage());
        }
    }

    /**
     * Idempotent column-and-index backfill for older installs. Uses
     * Laravel's Schema builder so the SQL emitted is portable across
     * MySQL 8 and MariaDB. Every step is gated on hasColumn/hasIndex so
     * the call is cheap on a fully-migrated DB.
     */
    private function backfillSchema(): void
    {
        if (! Schema::hasTable('ahg_iiif_annotation')) {
            return;
        }

        try {
            Schema::table('ahg_iiif_annotation', function (Blueprint $table) {
                // #100 follow-up: per-project scope + visibility.
                if (! Schema::hasColumn('ahg_iiif_annotation', 'project_id')) {
                    $table->integer('project_id')->nullable()->after('information_object_id');
                    $table->index('project_id', 'project_idx');
                }
                if (! Schema::hasColumn('ahg_iiif_annotation', 'visibility')) {
                    $table->string('visibility', 20)->default('private')->after('project_id');
                    $table->index('visibility', 'visibility_idx');
                }

                // #648 Phase 1: body-side selector denormalisation + ETag.
                if (! Schema::hasColumn('ahg_iiif_annotation', 'body_selector_json')) {
                    $table->json('body_selector_json')->nullable()->after('body_json');
                }
                if (! Schema::hasColumn('ahg_iiif_annotation', 'etag')) {
                    $table->char('etag', 40)->nullable()->after('body_selector_json');
                    $table->index('etag', 'etag_idx');
                }

                // #697 finishing pass: NER provenance columns.
                if (! Schema::hasColumn('ahg_iiif_annotation', 'ner_entity_type')) {
                    $table->string('ner_entity_type', 64)->nullable()->after('etag');
                    $table->index('ner_entity_type', 'ner_entity_type_idx');
                }
                if (! Schema::hasColumn('ahg_iiif_annotation', 'ner_confidence')) {
                    $table->decimal('ner_confidence', 5, 4)->nullable()->after('ner_entity_type');
                }
                if (! Schema::hasColumn('ahg_iiif_annotation', 'ner_run_id')) {
                    $table->string('ner_run_id', 64)->nullable()->after('ner_confidence');
                    $table->index('ner_run_id', 'ner_run_idx');
                }
            });
        } catch (\Throwable $e) {
            \Log::warning('[ahg-annotations] schema backfill failed: '.$e->getMessage());
        }
    }
}
