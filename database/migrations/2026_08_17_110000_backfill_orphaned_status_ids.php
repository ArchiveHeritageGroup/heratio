<?php

/**
 * Give every `status` row a real `object` id (#1470).
 *
 * `status` is a class-table-inheritance child of `object`, so a status row's id
 * should be the id of an `object` row with class_name 'QubitStatus'. Twenty call
 * sites inserted without an id and silently took one from status's own
 * AUTO_INCREMENT counter instead - 417 rows on heratio.org, 275 on dev - leaving
 * ids that belong to no object at all. Those sites were fixed in v1.154.622;
 * this repairs the rows they already wrote.
 *
 * WHY THIS IS SAFE TO DO. `status.id` is referenced by nothing:
 *
 *   - No foreign key anywhere points at it (checked on all three instances;
 *     status's own FKs are on object_id, type_id and status_id).
 *   - No code reads it as a meaningful value - status rows are always located by
 *     (object_id, type_id), never by id.
 *   - There is no FK from status.id to object.id either, which is precisely how
 *     the orphans arose and why nothing broke while they existed.
 *
 * So the id is free to change. Every other column is left exactly as it was.
 *
 * New ids come from `object`'s own counter, which v1.154.620 lifted clear of the
 * highest status id, and each candidate is checked against `status` before use -
 * a rewritten id must not land on another row's.
 *
 * Idempotent: a second run finds no orphans and does nothing.
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
        if (! Schema::hasTable('status') || ! Schema::hasTable('object')) {
            return;
        }

        $before = DB::table('status')->count();

        $orphans = DB::table('status as s')
            ->leftJoin('object as o', 'o.id', '=', 's.id')
            ->whereNull('o.id')
            ->orderBy('s.id')
            ->pluck('s.id')
            ->all();

        if ($orphans === []) {
            return;
        }

        $moved = 0;
        $skipped = 0;

        foreach ($orphans as $oldId) {
            try {
                DB::transaction(function () use ($oldId, &$moved, &$skipped) {
                    // Re-check inside the transaction: a concurrent write may have
                    // given this row an object in the meantime.
                    if (DB::table('object')->where('id', $oldId)->exists()) {
                        $skipped++;

                        return;
                    }

                    $newId = (int) DB::table('object')->insertGetId([
                        'class_name' => 'QubitStatus',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Must not collide with another status row's id.
                    if (DB::table('status')->where('id', $newId)->exists()) {
                        DB::table('object')->where('id', $newId)->delete();
                        $skipped++;

                        return;
                    }

                    DB::table('status')->where('id', $oldId)->update(['id' => $newId]);
                    $moved++;
                });
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning("Could not rehome status id {$oldId}: ".$e->getMessage());
            }
        }

        $after = DB::table('status')->count();
        $remaining = DB::table('status as s')
            ->leftJoin('object as o', 'o.id', '=', 's.id')
            ->whereNull('o.id')
            ->count();

        Log::info("#1470 status id backfill: {$moved} rehomed, {$skipped} skipped, {$remaining} orphans left; row count {$before} -> {$after}.");

        if ($before !== $after) {
            // Rewriting a primary key must never lose or duplicate a row.
            Log::error("#1470 status backfill CHANGED THE ROW COUNT ({$before} -> {$after}) - investigate.");
        }
    }

    public function down(): void
    {
        // Not reversible: the previous ids belonged to nothing, so there is
        // nothing to restore them to.
    }
};
