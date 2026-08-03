<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1408 (option A - moderation-only, open submission).
 *
 * Let community (non-researcher) members submit metadata corrections/additions
 * from a shared portable package into the curator review queue. Adds the
 * submitter identity and makes researcher_id optional (a community submission
 * has no researcher workspace). Everything still lands as status='open' and
 * applies nothing until a curator approves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_metadata_suggestion')) {
            return;
        }

        Schema::table('research_metadata_suggestion', function (Blueprint $t) {
            if (! Schema::hasColumn('research_metadata_suggestion', 'submitter_type')) {
                $t->string('submitter_type', 20)->default('researcher')->after('researcher_id');
            }
            if (! Schema::hasColumn('research_metadata_suggestion', 'submitter_name')) {
                $t->string('submitter_name', 191)->nullable()->after('submitter_type');
            }
            if (! Schema::hasColumn('research_metadata_suggestion', 'submitter_email')) {
                $t->string('submitter_email', 191)->nullable()->after('submitter_name');
            }
        });

        // researcher_id must be nullable for community submissions (no doctrine/dbal
        // dependency - a plain MODIFY, idempotent to re-run).
        try {
            DB::statement('ALTER TABLE research_metadata_suggestion MODIFY researcher_id INT NULL');
        } catch (\Throwable $e) {
            // Older MySQL / already nullable - non-fatal.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('research_metadata_suggestion')) {
            return;
        }
        Schema::table('research_metadata_suggestion', function (Blueprint $t) {
            foreach (['submitter_email', 'submitter_name', 'submitter_type'] as $c) {
                if (Schema::hasColumn('research_metadata_suggestion', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
