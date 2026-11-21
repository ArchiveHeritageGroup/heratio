<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1524: an offline gazetteer for checking printed place names.
 *
 * Loaded from a gazetteer dump on disk (GeoNames country files, or any file in
 * the same tab-separated shape) by ahg:gazetteer-load - never fetched from the
 * web. One row per spelling of a place (its name, ASCII name and each
 * alternate name), keyed by name_key: lower case with case, spaces, hyphens
 * and apostrophes squashed out, so the check is one indexed lookup.
 *
 * feature_class and country_code are data from the dump, not enumerations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ahg_gazetteer_place')) {
            return;
        }
        Schema::create('ahg_gazetteer_place', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('source', 30);
            $t->string('source_id', 40);
            $t->string('name', 255);
            $t->string('name_key', 191);
            $t->string('feature_class', 10)->nullable();
            $t->string('feature_code', 10)->nullable();
            $t->string('country_code', 10)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->index('name_key');
            $t->unique(['source', 'source_id', 'name_key'], 'ahg_gazetteer_place_src_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_gazetteer_place');
    }
};
