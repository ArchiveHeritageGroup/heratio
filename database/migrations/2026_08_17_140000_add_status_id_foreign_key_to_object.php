<?php

/**
 * Give `status.id` the foreign key every other CTI child already has (#1470).
 *
 * `object` has 24 class-table-inheritance children. All 24 declare
 *
 *     FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE
 *
 * and none of them has AUTO_INCREMENT on that column. `status` was the sole
 * outlier on both counts: no such foreign key, and an auto-increment counter of
 * its own. That combination is the whole reason this defect survived so long -
 * an insert omitting the id got a number from the wrong counter and the database
 * had no way to object.
 *
 * The previous migration removed the AUTO_INCREMENT. This adds the missing key,
 * so the invariant stops being a convention that twenty-odd call sites have to
 * remember and becomes something the schema enforces. After it, an id that is
 * not a real `object` row is rejected outright.
 *
 * ON DELETE CASCADE matches the siblings: removing a status's own object row
 * removes the status row with it.
 *
 * Safe to apply because v1.154.623/624 put every status row onto its own
 * QubitStatus object row - verified as 9,906 of 9,906 on dev, 827 of 827 on
 * heratio.org and 10 of 10 on sasa. The migration re-checks anyway and skips
 * rather than fail a deploy, since an unenforced invariant is better than a
 * blocked release.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FK = 'status_FK_4';

    public function up(): void
    {
        if (! Schema::hasTable('status') || ! Schema::hasTable('object')) {
            return;
        }
        if ($this->fkExists()) {
            return;
        }

        // The column must not still be auto-incrementing: adding the key while a
        // counter can hand out ids object has not reached would just move the
        // failure somewhere less obvious.
        $extra = DB::selectOne(
            'SELECT EXTRA FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['status', 'id']
        )->EXTRA ?? '';

        if (str_contains(strtolower((string) $extra), 'auto_increment')) {
            Log::warning('Not adding status_FK_4: status.id is still AUTO_INCREMENT.');

            return;
        }

        $bad = DB::table('status as s')
            ->leftJoin('object as o', 'o.id', '=', 's.id')
            ->where(function ($w) {
                $w->whereNull('o.id')->orWhere('o.class_name', '!=', 'QubitStatus');
            })
            ->count();

        if ($bad > 0) {
            Log::warning("Not adding status_FK_4: {$bad} status row(s) are not on a QubitStatus object row.");

            return;
        }

        try {
            DB::statement(
                'ALTER TABLE `status` ADD CONSTRAINT `'.self::FK.'` '.
                'FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE'
            );
            Log::info('status_FK_4 added: status.id now references object.id, matching all 24 sibling CTI children.');
        } catch (\Throwable $e) {
            // Never block a deploy over a hardening constraint.
            Log::warning('Could not add status_FK_4: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('status') || ! $this->fkExists()) {
            return;
        }
        DB::statement('ALTER TABLE `status` DROP FOREIGN KEY `'.self::FK.'`');
    }

    private function fkExists(): bool
    {
        try {
            return DB::table('information_schema.key_column_usage')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', 'status')
                ->where('column_name', 'id')
                ->where('referenced_table_name', 'object')
                ->exists();
        } catch (\Throwable) {
            return true;   // cannot tell - do not attempt the ALTER
        }
    }
};
