<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add source tracking to ingest_session so SharePoint-driven ingests can
 * reuse the wizard pipeline without losing origin.
 * Mirrored in atom-ahg-plugins/ahgSharePointPlugin/database/migrations/.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ingest_session')) {
            // ahg-ingest hasn't been installed yet; defer.
            return;
        }
        if (!Schema::hasColumn('ingest_session', 'source')) {
            Schema::table('ingest_session', function ($table) {
                $table->string('source', 20)->default('wizard')
                    ->comment('wizard, sharepoint, api');
            });
        }
        if (!Schema::hasColumn('ingest_session', 'source_id')) {
            Schema::table('ingest_session', function ($table) {
                $table->integer('source_id')->nullable()
                    ->comment('Origin record id (e.g., sharepoint_event.id)');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ingest_session')) {
            return;
        }
        Schema::table('ingest_session', function ($table) {
            if (Schema::hasColumn('ingest_session', 'source_id')) {
                $table->dropColumn('source_id');
            }
            if (Schema::hasColumn('ingest_session', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
