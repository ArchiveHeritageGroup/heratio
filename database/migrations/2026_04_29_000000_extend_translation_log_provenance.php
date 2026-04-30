<?php

/**
 * Extend ahg_translation_log with provenance fields for Phase 4.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Adds the columns Heratio Phase 4 needs to track WHO authored a translation
 * (created_by) and WHETHER it was machine- or human-generated (source), plus
 * the actual saved value and target culture for audit replay.
 *
 * The base ahg_translation_log already exists (created by ahg-translation
 * package install). This migration only ADDs missing columns; it does NOT
 * drop or rename anything.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ahg_translation_log')) {
            // Defensive: create from scratch if the package install never ran.
            Schema::create('ahg_translation_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('object_id')->nullable()->index();
                $table->string('field_name', 64)->nullable();
                $table->string('source_culture', 8)->nullable();
                $table->string('target_culture', 8)->nullable();
                $table->string('endpoint', 255)->nullable();
                $table->integer('http_status')->nullable();
                $table->boolean('ok')->default(false)->index();
                $table->text('error')->nullable();
                $table->integer('elapsed_ms')->nullable();
                $table->dateTime('created_at')->useCurrent();
            });
        }

        // Add provenance columns if they're missing.
        $cols = Schema::getColumnListing('ahg_translation_log');

        Schema::table('ahg_translation_log', function (Blueprint $table) use ($cols) {
            if (!in_array('value', $cols, true)) {
                // The translated value that was saved; null when only logging an attempt.
                $table->longText('value')->nullable()->after('error');
            }
            if (!in_array('source', $cols, true)) {
                // 'ai' = unreviewed machine output, 'human' = human-confirmed/edited.
                $table->string('source', 8)->nullable()->after('value');
            }
            if (!in_array('created_by_user_id', $cols, true)) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('source');
            }
            if (!in_array('confirmed', $cols, true)) {
                // True when a human reviewed the translation before saving.
                $table->boolean('confirmed')->default(false)->after('created_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ahg_translation_log')) {
            return;
        }

        Schema::table('ahg_translation_log', function (Blueprint $table) {
            $cols = Schema::getColumnListing('ahg_translation_log');
            $drop = array_intersect(['value', 'source', 'created_by_user_id', 'confirmed'], $cols);
            if (!empty($drop)) {
                $table->dropColumn(array_values($drop));
            }
        });
    }
};
