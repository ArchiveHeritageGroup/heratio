<?php

/**
 * Stop `status.id` collisions from breaking record edits.
 *
 * `status` is a class-table-inheritance child of `object`: a status row's id is
 * supposed to BE the id of an `object` row with class_name 'QubitStatus', which
 * is why InformationObjectController inserts into `object` first and reuses that
 * id. But `status.id` is also AUTO_INCREMENT, so any insert that omits the id
 * silently takes one from status's own counter instead - an id `object` has not
 * reached yet.
 *
 * On heratio.org that had happened 417 times, pushing status's counter to
 * 914643 while object's was still at 914262. Every new object id from there
 * collided with an already-used status id, and saving a description with a
 * publication status failed with:
 *
 *   SQLSTATE[23000]: Duplicate entry '914261' for key 'status.PRIMARY'
 *
 * - a user-visible 500 on the edit form, with roughly 380 more collisions
 * queued behind it.
 *
 * This lifts object's AUTO_INCREMENT clear of the highest id in EITHER table, so
 * no newly-allocated object id can hit an existing status row. Skipping id
 * ranges costs nothing; both columns are bigint.
 *
 * This is a guard, not the cure. The cure is for all twenty call sites that
 * insert into `status` without an id to allocate an object row first - tracked
 * separately. Until then this keeps the two id spaces from meeting again.
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
    public function up(): void
    {
        if (! Schema::hasTable('object') || ! Schema::hasTable('status')) {
            return;
        }

        try {
            $maxObject = (int) DB::table('object')->max('id');
            $maxStatus = (int) DB::table('status')->max('id');
            $target = max($maxObject, $maxStatus) + 64;   // headroom for in-flight writes

            $current = (int) (DB::selectOne(
                'SELECT AUTO_INCREMENT AS ai FROM information_schema.tables
                  WHERE table_schema = DATABASE() AND table_name = ?',
                ['object']
            )->ai ?? 0);

            if ($current >= $target) {
                return;   // already clear
            }

            // AUTO_INCREMENT cannot be parameterised.
            DB::statement('ALTER TABLE `object` AUTO_INCREMENT = '.(int) $target);

            $orphans = DB::table('status as s')
                ->leftJoin('object as o', 'o.id', '=', 's.id')
                ->whereNull('o.id')
                ->count();

            Log::info("object.AUTO_INCREMENT lifted from {$current} to {$target} to clear status id collisions ({$orphans} status rows have ids that are not object rows).");
        } catch (\Throwable $e) {
            // A guard that cannot be applied must not block the deploy; the
            // collision only bites on the next id allocation.
            Log::warning('Could not lift object.AUTO_INCREMENT: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // Never lower an AUTO_INCREMENT - it would hand out ids already in use.
    }
};
