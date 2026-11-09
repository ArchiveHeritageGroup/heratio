<?php

/**
 * heratio#1585 - "Cite these results": a stored, citable snapshot of a browse
 * or AI-result view, and the inference receipt kept with each chatbot answer.
 *
 * The snapshot holds the parameters, date, count, version and index generation
 * only - never a record title, so a citation cannot leak a hidden record.
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
        if (! Schema::hasTable('ahg_cited_view')) {
            Schema::create('ahg_cited_view', function (Blueprint $t) {
                $t->id();
                $t->string('code', 12)->unique();
                $t->string('kind', 20);                 // browse | chatbot
                $t->json('params')->nullable();         // browse query parameters
                $t->string('query_text', 500)->nullable();
                $t->unsignedInteger('result_count')->nullable();
                $t->string('viewer_scope', 10);         // public | staff: whose view was counted
                $t->string('heratio_version', 20);
                $t->string('index_generation', 64)->nullable();
                $t->string('ai_model', 128)->nullable();
                $t->unsignedBigInteger('ai_receipt_seq')->nullable();
                $t->char('ai_receipt_hash', 64)->nullable();
                $t->json('record_ids')->nullable();     // AI result: the records it rested on
                $t->unsignedBigInteger('source_id')->nullable(); // e.g. ahg_ai_chatbot_message.id
                $t->unsignedInteger('created_by')->nullable();
                $t->dateTime('created_at');
                $t->index(['kind', 'source_id']);
            });
        }

        if (Schema::hasTable('ahg_ai_chatbot_message') && ! Schema::hasColumn('ahg_ai_chatbot_message', 'receipt_seq')) {
            Schema::table('ahg_ai_chatbot_message', function (Blueprint $t) {
                $t->unsignedBigInteger('receipt_seq')->nullable()->after('tokens_out');
                $t->char('receipt_hash', 64)->nullable()->after('receipt_seq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_cited_view');
        if (Schema::hasTable('ahg_ai_chatbot_message') && Schema::hasColumn('ahg_ai_chatbot_message', 'receipt_seq')) {
            Schema::table('ahg_ai_chatbot_message', fn (Blueprint $t) => $t->dropColumn(['receipt_seq', 'receipt_hash']));
        }
    }
};
