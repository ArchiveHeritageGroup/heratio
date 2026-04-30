<?php

/**
 * Canonicalise icip_sensitivity onto information_object + actor.
 *
 * Phase 2b deepening: ICIP sensitivity classification was originally added to
 * museum_metadata (museum-only). To scale across all 7 entity types — IO,
 * Museum, Library, Gallery, DAM (all extend information_object via
 * class_table_inheritance) and Actor / Repository (extend actor) — the
 * canonical home for the URI is the parent table.
 *
 * Migration steps:
 *   1. Add information_object.icip_sensitivity + index
 *   2. Add actor.icip_sensitivity + index
 *   3. Backfill: copy any existing museum_metadata.icip_sensitivity values
 *      into information_object.icip_sensitivity for the same object_id
 *   4. Drop museum_metadata.icip_sensitivity column + its index
 *
 * Issue #36 Phase 2b — single source of truth across entity types.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. information_object.icip_sensitivity
        if (Schema::hasTable('information_object') && ! Schema::hasColumn('information_object', 'icip_sensitivity')) {
            Schema::table('information_object', function (Blueprint $table) {
                $table->string('icip_sensitivity', 512)->nullable()
                    ->after('source_culture')
                    ->comment('Full ICIP SKOS URI; resolved via VocabularyResolverService');
                $table->index([DB::raw('icip_sensitivity(191)')], 'idx_io_icip');
            });
        }

        // 2. actor.icip_sensitivity
        if (Schema::hasTable('actor') && ! Schema::hasColumn('actor', 'icip_sensitivity')) {
            Schema::table('actor', function (Blueprint $table) {
                $table->string('icip_sensitivity', 512)->nullable()
                    ->after('source_culture')
                    ->comment('Full ICIP SKOS URI; resolved via VocabularyResolverService');
                $table->index([DB::raw('icip_sensitivity(191)')], 'idx_actor_icip');
            });
        }

        // 3. Backfill from museum_metadata if it exists
        if (Schema::hasColumn('museum_metadata', 'icip_sensitivity')) {
            DB::statement('
                UPDATE information_object io
                JOIN museum_metadata mm ON mm.object_id = io.id
                SET io.icip_sensitivity = mm.icip_sensitivity
                WHERE mm.icip_sensitivity IS NOT NULL
                  AND (io.icip_sensitivity IS NULL OR io.icip_sensitivity = "")
            ');
        }

        // 4. Drop the museum-only column (no longer canonical)
        if (Schema::hasColumn('museum_metadata', 'icip_sensitivity')) {
            Schema::table('museum_metadata', function (Blueprint $table) {
                try {
                    $table->dropIndex('idx_museum_metadata_icip');
                } catch (\Throwable $e) { /* index may have been removed already */ }
                $table->dropColumn('icip_sensitivity');
            });
        }
    }

    public function down(): void
    {
        // Re-add museum-only column
        if (Schema::hasTable('museum_metadata') && ! Schema::hasColumn('museum_metadata', 'icip_sensitivity')) {
            Schema::table('museum_metadata', function (Blueprint $table) {
                $table->string('icip_sensitivity', 512)->nullable()->after('cultural_group');
                $table->index([DB::raw('icip_sensitivity(191)')], 'idx_museum_metadata_icip');
            });

            // Backfill back from information_object
            DB::statement('
                UPDATE museum_metadata mm
                JOIN information_object io ON io.id = mm.object_id
                SET mm.icip_sensitivity = io.icip_sensitivity
                WHERE io.icip_sensitivity IS NOT NULL
            ');
        }

        if (Schema::hasColumn('information_object', 'icip_sensitivity')) {
            Schema::table('information_object', function (Blueprint $table) {
                try { $table->dropIndex('idx_io_icip'); } catch (\Throwable $e) {}
                $table->dropColumn('icip_sensitivity');
            });
        }
        if (Schema::hasColumn('actor', 'icip_sensitivity')) {
            Schema::table('actor', function (Blueprint $table) {
                try { $table->dropIndex('idx_actor_icip'); } catch (\Throwable $e) {}
                $table->dropColumn('icip_sensitivity');
            });
        }
    }
};
