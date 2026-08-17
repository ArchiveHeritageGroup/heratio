<?php

/**
 * Give every `status` row an id that belongs to a QubitStatus object (#1470).
 *
 * The previous migration fixed the rows whose id pointed at NOTHING. Checking the
 * result showed the wider column was in a worse state: `status.id` was never
 * meaningful (it equals object_id in 1 row out of 9906), and most values were
 * ids taken from status's own AUTO_INCREMENT counter that happen to COLLIDE with
 * real object rows of other kinds - information objects, terms, digital objects.
 *
 * On dev that was 9621 rows resolving to an unrelated entity against 285
 * resolving correctly. A join on status.id therefore succeeds and returns
 * nonsense, which is worse than resolving to nothing: nothing is detectable, and
 * wrong-but-present is not.
 *
 * This rehomes every remaining row onto its own freshly allocated QubitStatus
 * object, so the column finally means one thing throughout.
 *
 * SAFE FOR THE SAME REASONS as the orphan backfill, re-verified before writing
 * this: no foreign key anywhere points at status.id, no code reads it (rows are
 * located by object_id + type_id), and status.id has no FK to object.id. Only
 * the id changes; every other column is untouched.
 *
 * The objects previously (coincidentally) sharing those ids are NOT modified -
 * they keep their own rows and ids. Only the status row moves off them.
 *
 * Batched so a large table does not sit in one transaction, and idempotent: a
 * second run finds nothing left to move.
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
        $checksumBefore = $this->tupleChecksum();

        // Every row whose id does not belong to a QubitStatus object: either it
        // resolves to another kind of entity, or (should the orphan backfill not
        // have run) to nothing at all.
        $targets = DB::table('status as s')
            ->leftJoin('object as o', 'o.id', '=', 's.id')
            ->where(function ($w) {
                $w->whereNull('o.id')->orWhere('o.class_name', '!=', 'QubitStatus');
            })
            ->orderBy('s.id')
            ->pluck('s.id')
            ->all();

        if ($targets === []) {
            return;
        }

        $moved = 0;
        $skipped = 0;

        foreach (array_chunk($targets, 200) as $chunk) {
            DB::transaction(function () use ($chunk, &$moved, &$skipped) {
                foreach ($chunk as $oldId) {
                    $newId = (int) DB::table('object')->insertGetId([
                        'class_name' => 'QubitStatus',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // A rewritten id must not land on another status row's id.
                    if (DB::table('status')->where('id', $newId)->exists()) {
                        DB::table('object')->where('id', $newId)->delete();
                        $skipped++;

                        continue;
                    }

                    DB::table('status')->where('id', $oldId)->update(['id' => $newId]);
                    $moved++;
                }
            });
        }

        $after = DB::table('status')->count();
        $checksumAfter = $this->tupleChecksum();
        $remaining = DB::table('status as s')
            ->leftJoin('object as o', 'o.id', '=', 's.id')
            ->where(function ($w) {
                $w->whereNull('o.id')->orWhere('o.class_name', '!=', 'QubitStatus');
            })
            ->count();

        Log::info("#1470 status rehome: {$moved} moved, {$skipped} skipped, {$remaining} not on a QubitStatus row; rows {$before} -> {$after}.");

        // Rewriting a primary key must not lose a row or alter any other value.
        if ($before !== $after) {
            Log::error("#1470 status rehome CHANGED THE ROW COUNT ({$before} -> {$after}) - investigate.");
        }
        if ($checksumBefore !== $checksumAfter) {
            Log::error("#1470 status rehome ALTERED A NON-ID VALUE (checksum {$checksumBefore} -> {$checksumAfter}) - investigate.");
        }
    }

    /** CRC32 sum over every (object_id, type_id, status_id) - must not change. */
    private function tupleChecksum(): ?string
    {
        try {
            return (string) (DB::selectOne(
                "SELECT SUM(CRC32(CONCAT_WS('|', object_id, type_id, status_id))) AS c FROM status"
            )->c ?? '');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function down(): void
    {
        // Not reversible: the previous ids were arbitrary collisions with
        // unrelated objects, so there is nothing meaningful to restore.
    }
};
