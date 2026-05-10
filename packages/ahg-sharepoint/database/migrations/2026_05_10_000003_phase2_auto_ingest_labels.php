<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.A — add the auto-ingest label allowlist to sharepoint_drive.
 * Mirrored in atom-ahg-plugins/ahgSharePointPlugin/database/migrations/.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sharepoint_drive')) {
            return;
        }
        if (!Schema::hasColumn('sharepoint_drive', 'auto_ingest_labels')) {
            Schema::table('sharepoint_drive', function ($table) {
                $table->text('auto_ingest_labels')->nullable()
                    ->comment('JSON array of compliance tag names that trigger auto-ingest in mode B');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sharepoint_drive') && Schema::hasColumn('sharepoint_drive', 'auto_ingest_labels')) {
            Schema::table('sharepoint_drive', function ($table) {
                $table->dropColumn('auto_ingest_labels');
            });
        }
    }
};
