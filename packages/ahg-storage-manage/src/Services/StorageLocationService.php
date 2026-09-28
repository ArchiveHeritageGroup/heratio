<?php

/**
 * StorageLocationService - hierarchical storage locations (heratio#1514).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3.0 or later. This
 * file is part of Heratio. See <https://www.gnu.org/licenses/> for details.
 */

namespace AhgStorageManage\Services;

use AhgCore\Services\ClosureMaintenanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Building, floor, room, shelf, and so on down.
 *
 * Port of the AtoM ahgStorageManagePlugin StorageLocationService, on the same
 * tables (ahg_storage_location + ahg_storage_location_closure), so both
 * platforms read one schema (atom-ahg-plugins#193, option 2).
 *
 * parent_id is the source of truth. The closure table is a maintained index of
 * every (ancestor, descendant, depth) pair, kept in step by the shared
 * ClosureMaintenanceService inside the same transaction as the row write, and
 * re-derivable with `php artisan ahg:build-closure --table=ahg_storage_location`.
 * Subtree, path and cycle checks are single indexed queries.
 *
 * `level` is denormalised depth, kept for ordering; after a move the subtree's
 * levels are reset in one UPDATE joined through the closure.
 *
 * Location types come from the Dropdown Manager (taxonomy storage_location_type),
 * capacity units from the existing capacity_unit taxonomy - never hardcoded.
 */
class StorageLocationService
{
    public const ENTITY = 'ahg_storage_location';

    public const TYPE_TAXONOMY = 'storage_location_type';

    public const UNIT_TAXONOMY = 'capacity_unit';

    /** Depth ceiling for in-memory tree assembly. */
    public const MAX_DEPTH = 100;

    public function __construct(
        private ?ClosureMaintenanceService $closure = null,
        private ?StorageMovementService $movements = null,
    ) {
        $this->closure ??= new ClosureMaintenanceService;
        $this->movements ??= new StorageMovementService;
    }

    // ---------- Dropdowns ---------------------------------------------

    /** code => label, active values of one Dropdown Manager taxonomy. */
    public function options(string $taxonomy): array
    {
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'code')
            ->all();
    }

    // ---------- Read --------------------------------------------------

    public function getById(int $id): ?object
    {
        return DB::table('ahg_storage_location')->where('id', $id)->first();
    }

    public function getBySlug(string $slug): ?object
    {
        return DB::table('ahg_storage_location')->where('slug', $slug)->first();
    }

    /** Flat list, filtered by search text, type or parent. */
    public function getLocations(array $params = []): array
    {
        $query = DB::table('ahg_storage_location')->orderBy('level')->orderBy('name');

        if (! empty($params['search'])) {
            $search = '%'.addcslashes(trim($params['search']), '%_\\').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)->orWhere('description', 'LIKE', $search);
            });
        }

        if (! empty($params['type'])) {
            $query->where('location_type', $params['type']);
        }

        if (array_key_exists('parent_id', $params)) {
            $params['parent_id'] === null
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', (int) $params['parent_id']);
        }

        return $query->get()->all();
    }

    public function getChildren(?int $parentId): array
    {
        return $this->getLocations(['parent_id' => $parentId]);
    }

    /** Every location below $id, at any depth, shallowest first. */
    public function getDescendants(int $id): array
    {
        return DB::table('ahg_storage_location_closure as c')
            ->join('ahg_storage_location as l', 'l.id', '=', 'c.descendant')
            ->where('c.ancestor', $id)
            ->where('c.depth', '>', 0)
            ->orderBy('l.level')->orderBy('l.name')
            ->select('l.*')
            ->get()->all();
    }

    /** Root to leaf: the ancestors of $id and $id itself. */
    public function getPath(int $id): array
    {
        return DB::table('ahg_storage_location_closure as c')
            ->join('ahg_storage_location as l', 'l.id', '=', 'c.ancestor')
            ->where('c.descendant', $id)
            ->orderByDesc('c.depth')
            ->select('l.*')
            ->get()->all();
    }

    /**
     * The tree below $parentId, or the whole tree from the roots. One query,
     * assembled in memory; each node gets a `children` array.
     */
    public function getTree(?int $parentId = null): array
    {
        $rows = $parentId === null
            ? $this->getLocations()
            : $this->getDescendants($parentId);

        $byParent = [];
        foreach ($rows as $row) {
            $byParent[$row->parent_id === null ? 0 : (int) $row->parent_id][] = $row;
        }

        return $this->branch($byParent, $parentId ?? 0, 0);
    }

    // ---------- Write -------------------------------------------------

    public function create(array $data): int
    {
        $row = $this->validated($data, null);
        $row['level'] = $this->levelFor($row['parent_id'] ?? null);
        $row['created_at'] = $row['updated_at'] = now();

        return DB::transaction(function () use ($row) {
            $id = (int) DB::table('ahg_storage_location')->insertGetId($row);
            $this->closure->addNode(self::ENTITY, $id, $row['parent_id'] ?? null);

            return $id;
        });
    }

    /** Fields left out of $data are left alone. An empty parent_id moves to the root. */
    public function update(int $id, array $data): void
    {
        $current = $this->getById($id);
        if ($current === null) {
            throw new RuntimeException('Storage location not found.');
        }

        $row = $this->validated($data, $id);

        $currentParent = $current->parent_id === null ? null : (int) $current->parent_id;
        $moved = array_key_exists('parent_id', $row) && $row['parent_id'] !== $currentParent;
        if (array_key_exists('parent_id', $row) && ! $moved) {
            unset($row['parent_id']);
        }

        if ($moved) {
            $this->assertNotInsideOwnSubtree($id, $row['parent_id']);
            $row['level'] = $this->levelFor($row['parent_id']);
        }

        $row['updated_at'] = now();

        DB::transaction(function () use ($id, $row, $moved, $currentParent, $data) {
            DB::table('ahg_storage_location')->where('id', $id)->update($row);

            if ($moved) {
                $this->closure->moveNode(self::ENTITY, $id, $row['parent_id']);
                DB::update(
                    'UPDATE ahg_storage_location l
                     JOIN ahg_storage_location_closure c ON c.descendant = l.id AND c.ancestor = ?
                     SET l.level = ? + c.depth',
                    [$id, (int) $row['level']]
                );

                // One event for the location that actually moved, in the same
                // transaction as the move itself: a hierarchy change the history
                // does not show is a gap nobody can account for later. What sat
                // underneath is a closure query, so logging the subtree too would
                // only restate the tree and go stale the moment it changed.
                $this->movements->recordLocationMove($id, $currentParent, $row['parent_id'], [
                    'note' => $data['movement_note'] ?? null,
                ]);
            }
        });
    }

    /** Refuses a location with children rather than letting the FK orphan them to the root. */
    public function delete(int $id): void
    {
        if (DB::table('ahg_storage_location')->where('parent_id', $id)->exists()) {
            throw new RuntimeException('Cannot delete a location that has children. Delete or move the children first.');
        }

        if (DB::table('ahg_physical_object_location')->where('location_id', $id)->exists()) {
            throw new RuntimeException('Cannot delete a location that still holds objects. Move them elsewhere first.');
        }

        // The movement FKs are RESTRICT, so the database would refuse this anyway,
        // with an integrity error nobody can act on. Said plainly here instead:
        // the history is the point of the log, and a location named in it cannot
        // be removed without taking part of that record away.
        if (DB::table('ahg_storage_movement')
            ->where('from_location_id', $id)->orWhere('to_location_id', $id)->exists()) {
            throw new RuntimeException('Cannot delete a location that appears in the movement history. The history is kept as a record of where holdings have been.');
        }

        DB::transaction(function () use ($id) {
            $this->closure->removeNode(self::ENTITY, $id);
            DB::table('ahg_storage_location')->where('id', $id)->delete();
        });
    }

    // ---------- Internals ---------------------------------------------

    private function validated(array $data, ?int $id): array
    {
        $creating = $id === null;
        $row = [];

        if ($creating || array_key_exists('name', $data)) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Location name is required.');
            }
            $row['name'] = Str::limit($name, 255, '');
            $row['slug'] = $this->uniqueSlug($row['name'], $id);
        }

        if ($creating || array_key_exists('location_type', $data)) {
            $type = (string) ($data['location_type'] ?? '');
            if (! array_key_exists($type, $this->options(self::TYPE_TAXONOMY))) {
                throw new RuntimeException('Invalid location type.');
            }
            $row['location_type'] = $type;
        }

        if (array_key_exists('parent_id', $data)) {
            $row['parent_id'] = empty($data['parent_id']) ? null : (int) $data['parent_id'];
        }

        foreach (['description', 'notes', 'capacity_unit'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) $data[$field]);
                $row[$field] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('capacity_value', $data)) {
            $value = $data['capacity_value'];
            $row['capacity_value'] = ($value === null || $value === '') ? null : (float) $value;
        }

        return $row;
    }

    private function levelFor(?int $parentId): int
    {
        if ($parentId === null) {
            return 0;
        }

        $parent = $this->getById($parentId);
        if ($parent === null) {
            throw new RuntimeException('Parent location not found.');
        }

        return (int) $parent->level + 1;
    }

    /** The new parent must not be $id or anything under it: one closure lookup. */
    private function assertNotInsideOwnSubtree(int $id, ?int $parentId): void
    {
        if ($parentId !== null && DB::table('ahg_storage_location_closure')
            ->where('ancestor', $id)->where('descendant', $parentId)->exists()) {
            throw new RuntimeException('Cannot move a location inside itself.');
        }
    }

    /** "Room 1" in two buildings is ordinary, so collisions get -2, -3, ... */
    private function uniqueSlug(string $name, ?int $id): string
    {
        $base = Str::limit(Str::slug($name), 240, '') ?: 'location-'.Str::lower(Str::random(8));

        $taken = fn (string $slug) => DB::table('ahg_storage_location')
            ->where('slug', $slug)
            ->when($id !== null, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        $slug = $base;
        for ($n = 2; $taken($slug); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    private function branch(array $byParent, int $parentKey, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [];
        }

        $out = [];
        foreach ($byParent[$parentKey] ?? [] as $node) {
            $node->children = $this->branch($byParent, (int) $node->id, $depth + 1);
            $out[] = $node;
        }

        return $out;
    }
}
