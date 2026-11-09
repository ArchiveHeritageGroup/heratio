<?php

/**
 * heratio#1558 flag_condition: an AI condition assessment can now be dismissed
 * as well as confirmed. review_status is pending / confirmed / dismissed (the
 * values PopiaGateService uses for its own review queue); is_confirmed stays in
 * step for the screens and reports that read it. confirmed_by / confirmed_at
 * record whoever decided, either way.
 *
 * Idempotent: does nothing where the column already exists.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_ai_condition_assessment') || Schema::hasColumn('ahg_ai_condition_assessment', 'review_status')) {
            return;
        }
        Schema::table('ahg_ai_condition_assessment', function (Blueprint $table) {
            $table->string('review_status', 20)->default('pending')->after('is_confirmed')
                ->comment('pending, confirmed or dismissed; confirmed_by/at record who decided');
            $table->index('review_status', 'idx_aca_review_status');
        });
        DB::table('ahg_ai_condition_assessment')->where('is_confirmed', 1)->update(['review_status' => 'confirmed']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('ahg_ai_condition_assessment', 'review_status')) {
            Schema::table('ahg_ai_condition_assessment', function (Blueprint $table) {
                $table->dropIndex('idx_aca_review_status');
                $table->dropColumn('review_status');
            });
        }
    }
};
