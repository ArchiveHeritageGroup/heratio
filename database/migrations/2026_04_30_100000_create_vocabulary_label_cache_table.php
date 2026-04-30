<?php

/**
 * vocabulary_label_cache — MySQL-backed cache for SKOS multilingual labels
 * resolved from controlled vocabularies (RiC-O, ISAD, AAT/TGN/LCSH, ICIP)
 * loaded into Fuseki. See issue #36.
 *
 * Usage flow:
 *   1) `ahg:vocabulary-import` loads OWL/Turtle into Fuseki + bulk-fills cache
 *   2) `VocabularyResolverService::preferredLabel($uri, $culture)` reads cache
 *   3) On cache miss: SPARQL → Fuseki → write-through into this table
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
        if (Schema::hasTable('vocabulary_label_cache')) {
            return;
        }

        Schema::create('vocabulary_label_cache', function (Blueprint $table) {
            $table->id();
            $table->string('uri', 512);
            $table->string('culture', 8);
            $table->text('preferred_label');
            $table->json('alt_labels')->nullable();
            $table->string('source_vocabulary', 64)->nullable()
                ->comment("e.g. 'ric-o', 'aat', 'lcsh', 'icip', 'isad', 'spectrum'");
            $table->string('sparql_endpoint', 255)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['uri', 'culture'], 'uk_uri_culture');
            // 191 prefix on uri because utf8mb4 + 512 char column hits MySQL key-length limits
            $table->index([\Illuminate\Support\Facades\DB::raw('uri(191)')], 'idx_uri');
            $table->index('source_vocabulary', 'idx_source_vocabulary');
            $table->index('expires_at', 'idx_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocabulary_label_cache');
    }
};
