<?php

/**
 * StorageMovementService - where things have been, and where they are now
 * (heratio#1514, atom-ahg-plugins#193).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3.0 or later. This
 * file is part of Heratio. See <https://www.gnu.org/licenses/> for details.
 */

namespace AhgStorageManage\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Port of the AtoM ahgStorageManagePlugin StorageMovementService, on the same
 * tables, so both platforms read one schema.
 *
 * ahg_storage_movement is the record: one append-only row per move, per subject.
 * A move entered wrongly is corrected by a further move, never by editing or
 * deleting a row - a history that can be quietly rewritten proves nothing about
 * the custody of the holdings, which is the only reason to keep one.
 *
 * ahg_physical_object_location is the current-state index beside it, written in
 * the same transaction, so a browse screen reads one row per object instead of
 * working out the latest movement. Same split as parent_id and its closure.
 *
 * A null location reads differently for the two subjects: for an object it is a
 * first placement or a removal from storage; for a location it is the root of
 * the hierarchy, since the columns hold its old and new parent.
 *
 * Two kinds of subject:
 *  - physical_object, a box or other container moving between locations;
 *  - storage_location, a location moving within the hierarchy. One event for the
 *    thing that actually moved. What sat under it is a closure query, so fanning
 *    the move out over every object underneath would only duplicate what the
 *    hierarchy already says, and would go stale the moment the tree changed.
 *
 * A bulk move is one row per subject sharing a batch id, so per-subject history
 * stays a plain lookup and "what moved together" is a lookup by batch.
 */
class StorageMovementService
{
    public const SUBJECT_OBJECT = 'physical_object';

    public const SUBJECT_LOCATION = 'storage_location';

    public const SUBJECTS = [self::SUBJECT_OBJECT, self::SUBJECT_LOCATION];

    public function __construct(private string $culture = 'en') {}

    // ---------- Write -------------------------------------------------

    /**
     * Move one physical object to a location, or out of storage with null.
     *
     * @param  array  $context  note, user_id, username, batch_id, moved_at
     * @return array the movement row as written
     */
    public function moveObject(int $objectId, ?int $toLocationId, array $context = []): array
    {
        $from = $this->currentLocationOf($objectId);

        if ($from === $toLocationId) {
            throw new RuntimeException('That object is already in that location.');
        }

        if ($toLocationId !== null) {
            $this->requireLocation($toLocationId);
        }

        return DB::transaction(function () use ($objectId, $from, $toLocationId, $context) {
            if ($toLocationId === null) {
                DB::table('ahg_physical_object_location')->where('physical_object_id', $objectId)->delete();
            } else {
                // One row per object: upsert rather than insert, or a second move
                // would violate the primary key.
                DB::table('ahg_physical_object_location')->updateOrInsert(
                    ['physical_object_id' => $objectId],
                    ['location_id' => $toLocationId, 'updated_at' => now()]
                );
            }

            return $this->record(self::SUBJECT_OBJECT, $objectId, $this->objectName($objectId), $from, $toLocationId, $context);
        });
    }

    /**
     * Move many objects to one location in a single batch.
     *
     * Objects already in the destination are skipped rather than logged as moves
     * that did not happen. The whole batch is one transaction: a bulk move that
     * half succeeded would leave the shelf list disagreeing with the history.
     *
     * @return array the movement rows written
     */
    public function moveObjects(array $objectIds, ?int $toLocationId, array $context = []): array
    {
        $context['batch_id'] = $context['batch_id'] ?? $this->newBatchId();

        return DB::transaction(function () use ($objectIds, $toLocationId, $context) {
            $written = [];

            foreach (array_unique(array_map('intval', $objectIds)) as $objectId) {
                if ($this->currentLocationOf($objectId) === $toLocationId) {
                    continue;
                }

                $written[] = $this->moveObject($objectId, $toLocationId, $context);
            }

            return $written;
        });
    }

    /** Record that a location itself moved within the hierarchy. */
    public function recordLocationMove(int $locationId, ?int $fromParentId, ?int $toParentId, array $context = []): array
    {
        return $this->record(
            self::SUBJECT_LOCATION,
            $locationId,
            $this->locationName($locationId),
            $fromParentId,
            $toParentId,
            $context
        );
    }

    // ---------- Read --------------------------------------------------

    /** Every move of one subject, newest first. */
    public function historyFor(string $subjectType, int $subjectId): array
    {
        $this->requireSubjectType($subjectType);

        return $this->rows(
            DB::table('ahg_storage_movement')
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->orderByDesc('moved_at')
                ->orderByDesc('id')
        );
    }

    /** Everything that moved in one batch. */
    public function batch(string $batchId): array
    {
        return $this->rows(
            DB::table('ahg_storage_movement')->where('batch_id', $batchId)->orderBy('id')
        );
    }

    /** Moves into or out of one location, newest first. */
    public function historyForLocation(int $locationId, int $limit = 100): array
    {
        return $this->rows(
            DB::table('ahg_storage_movement')
                ->where(function ($q) use ($locationId) {
                    $q->where('from_location_id', $locationId)->orWhere('to_location_id', $locationId);
                })
                ->orderByDesc('moved_at')
                ->orderByDesc('id')
                ->limit(max(1, $limit))
        );
    }

    /** The physical objects currently in a location. */
    public function objectsIn(int $locationId): array
    {
        return $this->rows(
            DB::table('ahg_physical_object_location as pol')
                ->leftJoin('physical_object_i18n as i', function ($join) {
                    $join->on('i.id', '=', 'pol.physical_object_id')->where('i.culture', '=', $this->culture);
                })
                ->where('pol.location_id', $locationId)
                ->orderBy('i.name')
                ->select('pol.physical_object_id', 'pol.location_id', 'pol.updated_at', 'i.name')
        );
    }

    /**
     * Everything in a location and in all that sits beneath it: the boxes in a
     * carton, the cartons on a pallet, the pallets in a bay (heratio#1528). One
     * closure join. Each row says which location the object is actually in, and
     * at what depth below this one (0 = held here directly), so the list reads
     * as a shelf list.
     */
    public function objectsUnder(int $locationId): array
    {
        return $this->rows(
            DB::table('ahg_storage_location_closure as c')
                ->join('ahg_physical_object_location as pol', 'pol.location_id', '=', 'c.descendant')
                ->join('ahg_storage_location as l', 'l.id', '=', 'pol.location_id')
                ->leftJoin('physical_object_i18n as i', function ($join) {
                    $join->on('i.id', '=', 'pol.physical_object_id')->where('i.culture', '=', $this->culture);
                })
                ->where('c.ancestor', $locationId)
                ->orderBy('c.depth')
                ->orderBy('l.name')
                ->orderBy('i.name')
                ->select(
                    'pol.physical_object_id', 'pol.location_id', 'pol.updated_at', 'i.name',
                    'l.name as location_name', 'l.slug as location_slug', 'l.location_type', 'c.depth'
                )
        );
    }

    /**
     * Physical objects that are in no location yet, for placing (heratio#1528).
     *
     * Capped, because an archive that has just switched the tree on has every
     * box it owns in this list. The search narrows it; the total says how many
     * there are in all.
     *
     * @return array rows (physical_object_id, name), total
     */
    public function unplacedObjects(string $search = '', int $limit = 100): array
    {
        $query = DB::table('physical_object as p')
            ->leftJoin('ahg_physical_object_location as pol', 'pol.physical_object_id', '=', 'p.id')
            ->leftJoin('physical_object_i18n as i', function ($join) {
                $join->on('i.id', '=', 'p.id')->where('i.culture', '=', $this->culture);
            })
            ->whereNull('pol.physical_object_id');

        $search = trim($search);
        if ($search !== '') {
            $query->where('i.name', 'LIKE', '%'.addcslashes($search, '%_\\').'%');
        }

        $total = (clone $query)->count();

        return [
            'rows' => $this->rows(
                $query->orderBy('i.name')->orderBy('p.id')->limit(max(1, $limit))
                    ->select('p.id as physical_object_id', 'i.name')
            ),
            'total' => (int) $total,
        ];
    }

    /** Where an object is now, or null when it is not in storage. */
    public function currentLocationOf(int $objectId): ?int
    {
        $row = DB::table('ahg_physical_object_location')->where('physical_object_id', $objectId)->first();

        return $row ? (int) $row->location_id : null;
    }

    /** A batch id for a bulk move. */
    public function newBatchId(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }

    // ---------- Internals ---------------------------------------------

    /** Write one movement row, with the snapshots that keep it readable. */
    protected function record(string $subjectType, int $subjectId, ?string $subjectName, ?int $fromId, ?int $toId, array $context): array
    {
        $this->requireSubjectType($subjectType);

        $row = [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'from_location_id' => $fromId,
            'from_location_name' => $fromId === null ? null : $this->locationName($fromId),
            'to_location_id' => $toId,
            'to_location_name' => $toId === null ? null : $this->locationName($toId),
            'batch_id' => $context['batch_id'] ?? null,
            'note' => isset($context['note']) ? trim((string) $context['note']) : null,
            'moved_at' => $context['moved_at'] ?? now(),
        ];

        $row += $this->actor($context);

        $id = DB::table('ahg_storage_movement')->insertGetId($row);

        return ['id' => (int) $id] + $row;
    }

    /**
     * Who moved it: the id for joining, the name for reading.
     *
     * The id carries no foreign key on purpose. History has to outlive the
     * account that made it, and a deleted user must not take the record of what
     * they did with them. An explicit user_id or username in the context wins,
     * so a command or an import can say who it acted for.
     */
    protected function actor(array $context): array
    {
        if (array_key_exists('user_id', $context) || array_key_exists('username', $context)) {
            return [
                'user_id' => isset($context['user_id']) ? (int) $context['user_id'] : null,
                'username' => $context['username'] ?? null,
            ];
        }

        $user = auth()->user();

        if ($user === null) {
            return ['user_id' => null, 'username' => null];   // console, queue, or not signed in
        }

        return [
            'user_id' => (int) $user->id,
            'username' => $user->username ?? $user->email ?? null,
        ];
    }

    protected function requireSubjectType(string $subjectType): void
    {
        if (! in_array($subjectType, self::SUBJECTS, true)) {
            throw new RuntimeException('Unknown movement subject type: '.$subjectType);
        }
    }

    protected function requireLocation(int $locationId): void
    {
        if (! DB::table('ahg_storage_location')->where('id', $locationId)->exists()) {
            throw new RuntimeException('Storage location not found.');
        }
    }

    protected function locationName(int $locationId): ?string
    {
        $row = DB::table('ahg_storage_location')->where('id', $locationId)->first();

        return $row ? $row->name : null;
    }

    /** The object's name in this culture, for the snapshot. */
    protected function objectName(int $objectId): ?string
    {
        $row = DB::table('physical_object_i18n')
            ->where('id', $objectId)
            ->where('culture', $this->culture)
            ->first();

        return $row ? $row->name : null;
    }

    protected function rows($query): array
    {
        return array_map(static fn ($row) => (array) $row, $query->get()->all());
    }
}
