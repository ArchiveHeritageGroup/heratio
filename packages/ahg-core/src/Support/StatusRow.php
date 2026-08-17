<?php

/**
 * Write `status` rows with a properly allocated id (#1470).
 *
 * `status` is a class-table-inheritance child of `object`: a status row's id is
 * supposed to BE the id of an `object` row with class_name 'QubitStatus'. But
 * `status.id` is also AUTO_INCREMENT, so an insert that omits the id silently
 * takes one from status's own counter instead - an id `object` has not reached
 * yet.
 *
 * Twenty call sites did exactly that. On heratio.org it had happened 417 times,
 * pushing status's counter to 914643 while object's was at 914262, so every
 * newly allocated object id then collided with an existing status row and saving
 * a description failed with:
 *
 *   SQLSTATE[23000]: Duplicate entry '914261' for key 'status.PRIMARY'
 *
 * v1.154.620 lifted object's AUTO_INCREMENT clear of the collision range, which
 * stopped the failures but only bought distance - the two id spaces would drift
 * together again. This class is the cure: one place that allocates the object
 * row, so no caller has to remember.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgCore\Support;

use Illuminate\Support\Facades\DB;

class StatusRow
{
    /**
     * Allocate an `object` row for a status and return its id.
     *
     * Every status row must own one of these. Callers should not insert into
     * `status` without going through this or one of the writers below.
     */
    public static function allocateId(): int
    {
        return (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitStatus',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Replace the status of this type for this object.
     *
     * Deletes any existing row for (object_id, type_id) and writes a new one, so
     * an object never ends up with two publication statuses. Returns the new
     * status row id.
     *
     * @param  array<string,mixed>  $extra  additional columns (e.g. serial_number)
     */
    public static function set(int $objectId, int $typeId, int $statusId, array $extra = []): int
    {
        return DB::transaction(function () use ($objectId, $typeId, $statusId, $extra) {
            DB::table('status')
                ->where('object_id', $objectId)
                ->where('type_id', $typeId)
                ->delete();

            $id = self::allocateId();

            DB::table('status')->insert(array_merge($extra, [
                'id' => $id,
                'object_id' => $objectId,
                'type_id' => $typeId,
                'status_id' => $statusId,
            ]));

            return $id;
        });
    }

    /**
     * Drop-in for `updateOrInsert` on the status table.
     *
     * UPDATES an existing row in place - keeping its id, which a delete-and-
     * reinsert would change for no reason - and allocates an object row only
     * when actually inserting. This is the shape seven call sites used, and the
     * insert half is where they were silently taking an id from status's own
     * counter.
     *
     * @param  array<string,mixed>  $extra
     */
    public static function put(int $objectId, int $typeId, int $statusId, array $extra = []): void
    {
        $updated = DB::table('status')
            ->where('object_id', $objectId)
            ->where('type_id', $typeId)
            ->update(array_merge($extra, ['status_id' => $statusId]));

        if ($updated > 0) {
            return;
        }

        DB::table('status')->insert(array_merge($extra, [
            'id' => self::allocateId(),
            'object_id' => $objectId,
            'type_id' => $typeId,
            'status_id' => $statusId,
        ]));
    }

    /**
     * Write the status only if this object has none of this type.
     *
     * Returns the existing status_id when one is already present, so callers can
     * report what is now in force without a second query.
     *
     * @param  array<string,mixed>  $extra
     */
    public static function ensure(int $objectId, int $typeId, int $statusId, array $extra = []): int
    {
        $existing = DB::table('status')
            ->where('object_id', $objectId)
            ->where('type_id', $typeId)
            ->value('status_id');

        if ($existing !== null) {
            return (int) $existing;
        }

        self::set($objectId, $typeId, $statusId, $extra);

        return $statusId;
    }
}
