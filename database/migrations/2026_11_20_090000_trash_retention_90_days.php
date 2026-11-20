<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1734: deleted files stay in the trash 90 days, not 30 - agreed with
 * PSIS for the shared uploads folder, since a missing file can go unnoticed
 * for days. Only the untouched default is raised; files already in the trash
 * get the longer retention too.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ahg_settings')) {
            DB::table('ahg_settings')->where('setting_key', 'trash_retention_days')->where('setting_value', '30')
                ->update(['setting_value' => '90']);
            if (Schema::hasColumn('ahg_settings', 'description')) {
                DB::table('ahg_settings')->where('setting_key', 'trash_retention_days')->update(['description' => 'Days a deleted digital object stays in the trash (Admin > Deleted files) before the purge removes its files. 0 deletes at once. The purge lists what it will remove a day ahead.']);
            }
        }
        if (Schema::hasTable('ahg_trash')) {
            DB::table('ahg_trash')->where('status', 'trashed')
                ->whereRaw('purge_after < deleted_at + INTERVAL 90 DAY')
                ->update(['purge_after' => DB::raw('deleted_at + INTERVAL 90 DAY')]);
        }
    }

    public function down(): void
    {
        // Keeps the longer retention.
    }
};
