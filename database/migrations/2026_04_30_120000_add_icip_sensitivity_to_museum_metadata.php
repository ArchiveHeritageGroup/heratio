<?php

/**
 * Add icip_sensitivity URI column to museum_metadata so museum objects can
 * carry an ICIP cultural-sensitivity classification resolved via the
 * VocabularyResolverService against the ICIP SKOS vocabulary in Fuseki.
 *
 * Stores the full ontology URI (e.g.
 * "https://heratio.theahg.co.za/vocabulary/icip#GenderRestricted") rather
 * than a code, so the value is portable across deployments and
 * resolver-friendly without a join table.
 *
 * Issue #36 Phase 2b — ICIP wiring demo.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('museum_metadata')) {
            return;
        }
        if (Schema::hasColumn('museum_metadata', 'icip_sensitivity')) {
            return;
        }
        Schema::table('museum_metadata', function (Blueprint $table) {
            $table->string('icip_sensitivity', 512)->nullable()
                ->after('cultural_group')
                ->comment('Full URI in the ICIP SKOS vocabulary, e.g. https://heratio.theahg.co.za/vocabulary/icip#SacredSecret');
            $table->index([\Illuminate\Support\Facades\DB::raw('icip_sensitivity(191)')], 'idx_museum_metadata_icip');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('museum_metadata')) {
            return;
        }
        Schema::table('museum_metadata', function (Blueprint $table) {
            $table->dropIndex('idx_museum_metadata_icip');
            $table->dropColumn('icip_sensitivity');
        });
    }
};
