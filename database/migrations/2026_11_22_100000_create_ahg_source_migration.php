<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1495: run and record tables for vendor source migration, plus the
 * Dropdown Manager rows its mapping files resolve against. The SQL is idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(base_path('packages/ahg-data-migration/database/source_migration.sql')));
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_source_migration_record');
        Schema::dropIfExists('ahg_source_migration_run');
        DB::table('ahg_dropdown')->whereIn('taxonomy', [
            'source_level_calm', 'source_record_type_calm', 'source_migration_piece_policy',
            'source_migration_date_policy', 'source_migration_status',
        ])->delete();
    }
};
