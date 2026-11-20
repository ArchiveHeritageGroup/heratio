<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1737: a retention schedule has a type (from the Dropdown Manager,
 * taxonomy rm_retention_schedule_type) - for example "Schedule of records
 * other than correspondence" - and the disposal authority number the archives
 * authority issued for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rm_retention_schedule')) {
            Schema::table('rm_retention_schedule', function (Blueprint $t) {
                if (! Schema::hasColumn('rm_retention_schedule', 'schedule_type')) {
                    $t->string('schedule_type', 64)->nullable()->after('title');
                }
                if (! Schema::hasColumn('rm_retention_schedule', 'disposal_authority_number')) {
                    $t->string('disposal_authority_number', 100)->nullable()->after('authority');
                }
            });
        }
        if (Schema::hasTable('ahg_dropdown') && ! DB::table('ahg_dropdown')->where('taxonomy', 'rm_retention_schedule_type')->exists()) {
            foreach ([
                ['general', 'General retention schedule', 10],
                ['file_plan', 'File plan (correspondence)', 20],
                ['records_other_than_correspondence', 'Schedule of records other than correspondence', 30],
            ] as [$code, $label, $order]) {
                DB::table('ahg_dropdown')->insert([
                    'taxonomy' => 'rm_retention_schedule_type', 'taxonomy_label' => 'Retention schedule type', 'taxonomy_section' => 'records',
                    'code' => $code, 'label' => $label, 'sort_order' => $order, 'is_default' => $code === 'general' ? 1 : 0, 'is_active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Additive only.
    }
};
