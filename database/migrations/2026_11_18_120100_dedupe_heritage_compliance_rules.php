<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1779: heritage_compliance_rule had no unique key, so install.sql's
 * INSERT IGNORE ignored nothing and every forced re-run of the package
 * installs added the base rules again (545 rows, 103 distinct, on heratio.org).
 *
 * Keep one row per (standard_id, code). Where two different rules share a
 * code (install.sql's short base set and a regional pack's fuller set), the
 * regional pack's rule wins: it is the variant first created later. Within a
 * variant the oldest row is kept, so any edit an administrator made to it
 * survives. Then add the unique key, which makes INSERT IGNORE work and lets
 * the regional packs use ON DUPLICATE KEY UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('heritage_compliance_rule')) {
            return;
        }

        $rows = DB::table('heritage_compliance_rule')
            ->orderBy('id')
            ->get(['id', 'standard_id', 'code', 'category', 'name', 'field_name', 'created_at']);

        $delete = [];
        foreach ($rows->groupBy(fn ($r) => $r->standard_id.'|'.$r->code) as $group) {
            $variants = $group->groupBy(fn ($r) => $r->category.'|'.$r->name.'|'.$r->field_name);
            $winner = $variants->sortByDesc(fn ($v) => (string) $v->min('created_at'))->first();
            $keep = $winner->min('id');
            foreach ($group as $r) {
                if ($r->id !== $keep) {
                    $delete[] = $r->id;
                }
            }
        }
        foreach (array_chunk($delete, 500) as $ids) {
            DB::table('heritage_compliance_rule')->whereIn('id', $ids)->delete();
        }

        $hasKey = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_compliance_rule' AND INDEX_NAME = 'uq_standard_code'");
        if ((int) $hasKey->n === 0) {
            DB::statement('ALTER TABLE `heritage_compliance_rule` ADD UNIQUE KEY `uq_standard_code` (`standard_id`, `code`)');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `heritage_compliance_rule` DROP INDEX `uq_standard_code`');
    }
};
