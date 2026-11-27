<?php

/**
 * Per-language text for the heritage landing page (27 Nov 2026).
 *
 * heritage_landing_config holds one tagline, subtext, search hint and set of
 * suggested searches for every language, so a site with Afrikaans enabled
 * still showed its English hero text. This table holds a translation of those
 * four fields per culture; the landing page uses it for the visitor's
 * language and falls back to the main row.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('heritage_landing_config_i18n')) {
            return;
        }
        Schema::create('heritage_landing_config_i18n', function (Blueprint $t) {
            $t->id();
            $t->integer('config_id');
            $t->string('culture', 16);
            $t->string('hero_tagline', 500)->nullable();
            $t->string('hero_subtext', 500)->nullable();
            $t->string('hero_search_placeholder', 255)->nullable();
            $t->json('suggested_searches')->nullable();
            $t->timestamps();
            $t->unique(['config_id', 'culture'], 'uq_heritage_landing_config_i18n');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heritage_landing_config_i18n');
    }
};
