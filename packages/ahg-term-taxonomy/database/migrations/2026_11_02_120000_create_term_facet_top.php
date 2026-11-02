<?php

/**
 * term_facet_top - the term page's facet panels (top 10 co-occurring terms per
 * facet taxonomy) for terms on thousands of descriptions, built nightly by
 * ahg:term-facets-build. A cold page for a 127,000-record term took 21 s to
 * aggregate live at ANC (heratio#1573).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('term_facet_top')) {
            return;
        }
        Schema::create('term_facet_top', function (Blueprint $t) {
            $t->unsignedInteger('term_id');
            $t->unsignedInteger('taxonomy_id');
            $t->unsignedInteger('other_term_id');
            $t->unsignedInteger('cnt');
            $t->dateTime('built_at');
            $t->primary(['term_id', 'taxonomy_id', 'other_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_facet_top');
    }
};
