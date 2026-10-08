<?php

/**
 * StoragePlacementService - the physical storage form as the way into the
 * storage location tree (heratio#1545, parity with atom-ahg-plugins v3.116.x).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3.0 or later. This
 * file is part of Heratio. See <https://www.gnu.org/licenses/> for details.
 */

namespace AhgStorageManage\Services;

use AhgCore\Services\AclService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Port of the AtoM ahgStorageManagePlugin StoragePlacementService, on the same
 * tables.
 *
 * The box form keeps its seven location fields, Building down to Shelf. On save
 * each filled level is found beneath the one above it (name compared without
 * regard to case) or, for editors and administrators, created there. The box is
 * then placed in the innermost one through StorageMovementService, so the move
 * is logged like any other. Empty levels are skipped: Building + Room puts the
 * room straight under the building.
 *
 * The flat text columns in physical_object_extended are still saved by the form
 * as before, so reports that read them keep working.
 *
 * Unlike AtoM, Heratio has one database connection per request, so the
 * placement runs in the request, after the box row is written, with no need to
 * defer it to a shutdown function.
 */
class StoragePlacementService
{
    /** The form's location fields, outermost first. Each is also a location type. */
    public const LEVELS = ['building', 'floor', 'room', 'aisle', 'bay', 'rack', 'shelf'];

    /** saveFromForm results */
    public const UNCHANGED = 'unchanged';

    public const MOVED = 'moved';

    public const NOT_ALLOWED = 'not_allowed';

    private StorageLocationService $locations;

    private StorageMovementService $movements;

    public function __construct(private string $culture = 'en')
    {
        $this->movements = new StorageMovementService($culture);
        $this->locations = new StorageLocationService(null, $this->movements);
    }

    /** Whether the tree is installed on this instance. */
    public static function available(): bool
    {
        try {
            return Schema::hasTable('ahg_storage_location') && Schema::hasTable('ahg_physical_object_location');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Editors and administrators may add places from the box form; everybody
     * else picks from what exists.
     */
    public static function mayCreate(?object $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && AclService::canAdmin((int) $user->id);
    }

    /**
     * The box's current place split into the form's levels, or null when the
     * box is not placed. Locations of other types on the path (a container, a
     * storage unit) have no field and are left out.
     *
     * @return array<string, string>|null level => name
     */
    public function levelsFor(int $objectId): ?array
    {
        $locationId = $this->movements->currentLocationOf($objectId);
        if ($locationId === null) {
            return null;
        }

        $levels = [];
        foreach ($this->locations->getPath($locationId) as $row) {
            if (in_array($row->location_type, self::LEVELS, true)) {
                $levels[$row->location_type] = (string) $row->name;
            }
        }

        return $levels;
    }

    /**
     * The innermost location the levels describe.
     *
     * @param  array<string, mixed>  $levels  level => name, as posted
     * @param  bool  $mayCreate  create missing levels, or give up
     * @return int|false|null null when no level is filled; false when a level is
     *                        missing and may not be created
     */
    public function resolve(array $levels, bool $mayCreate): int|false|null
    {
        $parentId = null;

        foreach (self::LEVELS as $type) {
            $name = StorageLocationService::placeName($type, (string) ($levels[$type] ?? ''));
            if ($name === '') {
                continue;
            }

            $id = $this->locations->findChild($parentId, $type, $name);

            if ($id === null) {
                if (! $mayCreate) {
                    return false;
                }
                $id = $this->locations->create(['name' => $name, 'location_type' => $type, 'parent_id' => $parentId]);
            }

            $parentId = $id;
        }

        return $parentId;
    }

    /**
     * Place a box from the form's posted levels.
     *
     * Nothing happens when the posted levels match where the box already is,
     * which also keeps a box that sits in a container below its shelf from
     * being pulled up to the shelf on an unrelated save. Clearing every level
     * leaves the box where it is: taking a box out of storage is a deliberate
     * move, made from the Move screen, not a side effect of an empty form.
     *
     * @param  array  $context  note, user_id, username for the movement log
     * @return string one of UNCHANGED, MOVED, NOT_ALLOWED
     */
    public function saveFromForm(int $objectId, array $levels, bool $mayCreate, array $context = []): string
    {
        $posted = self::normalise($levels);
        if ($posted === [] || $posted === self::normalise($this->levelsFor($objectId) ?? [])) {
            return self::UNCHANGED;
        }

        return DB::transaction(function () use ($objectId, $levels, $mayCreate, $context) {
            $target = $this->resolve($levels, $mayCreate);
            if ($target === false) {
                return self::NOT_ALLOWED;
            }

            if ($target === null || $this->movements->currentLocationOf($objectId) === $target) {
                return self::UNCHANGED;
            }

            $this->movements->moveObject($objectId, $target, $context + ['note' => 'Physical storage form']);

            return self::MOVED;
        });
    }

    /**
     * For the box's own page: its place as a path, outermost first, and its
     * latest moves. Null when the tree is not installed.
     *
     * @return array{path: array, moves: array}|null
     */
    public function placementView(int $objectId, int $moves = 5): ?array
    {
        if (! self::available()) {
            return null;
        }

        $locationId = $this->movements->currentLocationOf($objectId);

        return [
            'location' => $locationId === null ? null : $this->locations->getById($locationId),
            'path' => $locationId === null ? [] : $this->locations->getPath($locationId),
            'moves' => array_slice($this->movements->historyFor(StorageMovementService::SUBJECT_OBJECT, $objectId), 0, max(1, $moves)),
        ];
    }

    /** level => lower-cased trimmed name, empty levels dropped, in level order. */
    private static function normalise(array $levels): array
    {
        $out = [];
        foreach (self::LEVELS as $type) {
            $name = mb_strtolower(StorageLocationService::placeName($type, (string) ($levels[$type] ?? '')));
            if ($name !== '') {
                $out[$type] = $name;
            }
        }

        return $out;
    }
}
