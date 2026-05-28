<?php

/**
 * Issue #650 Phase 3 — search analytics
 *
 * Per-query log used to surface top queries, zero-result queries, and CTR
 * in the admin search analytics dashboard.
 *
 * Schema:
 *   - user_id is NULL for anonymous searches
 *   - anonymized_id is sha256(ip) when not logged in; used to count unique
 *     searchers without storing raw IPs (POPIA / GDPR friendlier)
 *   - filters_json holds the active filter set to correlate top queries
 *     to common refinements
 *   - click_position is NULL until the click-tracking POST flips it;
 *     that gives CTR per query without a separate join
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
        // Match what packages/ahg-search/database/install.sql defined
        // (idempotent — safe to re-run).
        if (! Schema::hasTable('ahg_search_query_log')) {
            Schema::create('ahg_search_query_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('anonymized_id', 64)->nullable()
                    ->comment('sha256(ip) for anonymous — POPIA / GDPR friendlier');
                $table->string('query', 512);
                $table->json('filters_json')->nullable();
                $table->integer('result_count')->unsigned()->default(0);
                $table->unsignedTinyInteger('click_position')->nullable()
                    ->comment('set by click-tracking POST; CTR derived from this + result_count');
                $table->dateTime('executed_at');
                $table->unsignedInteger('response_time_ms')->default(0);

                $table->index('executed_at', 'idx_search_log_executed');
                $table->index(['result_count', 'executed_at'], 'idx_search_log_zero');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_search_query_log');
    }
};