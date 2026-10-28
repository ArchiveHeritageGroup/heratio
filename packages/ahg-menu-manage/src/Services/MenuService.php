<?php

/**
 * MenuService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgMenuManage\Services;

use AhgCore\Services\HierarchyQueryService;
use Illuminate\Support\Facades\DB;

/**
 * Menu CRUD Service
 *
 * Instance-based service for menu operations.
 * The hierarchy is parent_id, menu_closure and the sibling-order table;
 * lft/rgt is rebuilt from them after every structural change.
 *
 * Tables: menu (with lft/rgt MPTT), menu_i18n (label, description).
 * ROOT_ID = 1 (the invisible root node).
 */
class MenuService
{
    public const ROOT_ID = 1;

    /**
     * Protected menu names that cannot be deleted or renamed.
     * These are core menus required for system functionality.
     */
    protected const PROTECTED_NAMES = [
        'mainMenu',
        'browse',
        'add',
        'manage',
        'import',
        'admin',
        'browseInstitution',
        'staticPagesMenu',
        'clipboard',
    ];

    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
    }

    /**
     * Get the full menu tree as a flat array with depth, in sibling order.
     * Excludes the ROOT node.
     *
     * @return array List of menu items with depth, parent_id, name, label, protected status
     */
    public function getTree(): array
    {
        $query = DB::table('menu as m')
            ->leftJoin('menu_i18n as mi', function ($join) {
                $join->on('m.id', '=', 'mi.id')
                    ->where('mi.culture', '=', $this->culture);
            })
            ->select(
                'm.id',
                'm.parent_id',
                'm.name',
                'm.path',
                'm.lft',
                'm.rgt',
                'm.source_culture',
                'mi.label',
                'mi.description'
            );
        $hierarchy = app(HierarchyQueryService::class);
        $hierarchy->applySiblingOrder($query, 'menu', 'm.id', 'm.lft');
        $query->orderBy('m.id');

        // Depth and children from parent_id and sibling order (heratio#1541):
        // the stored lft/rgt is null on fresh installs.
        $depth = [self::ROOT_ID => -1];
        $result = [];
        foreach ($hierarchy->nestRows($query->get(), self::ROOT_ID) as $row) {
            if ((int) $row->id === self::ROOT_ID) {
                continue;
            }
            $depth[(int) $row->id] = $depth[(int) $row->parent_id] + 1;
            $item = (array) $row;
            $item['depth'] = $depth[(int) $row->id];
            $item['isProtected'] = $this->isProtected($item['id']);
            $item['hasChildren'] = ($row->rgt - $row->lft) > 1;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Get a single menu item by ID with i18n data.
     *
     * @return array|null Menu data or null if not found
     */
    public function getById(int $id): ?array
    {
        $menu = DB::table('menu as m')
            ->leftJoin('menu_i18n as mi', function ($join) {
                $join->on('m.id', '=', 'mi.id')
                    ->where('mi.culture', '=', $this->culture);
            })
            ->where('m.id', $id)
            ->select(
                'm.id',
                'm.parent_id',
                'm.name',
                'm.path',
                'm.lft',
                'm.rgt',
                'm.source_culture',
                'm.serial_number',
                'm.created_at',
                'm.updated_at',
                'mi.label',
                'mi.description'
            )
            ->first();

        if (! $menu) {
            return null;
        }

        return [
            'id' => (int) $menu->id,
            'parentId' => $menu->parent_id ? (int) $menu->parent_id : null,
            'name' => $menu->name ?? '',
            'path' => $menu->path ?? '',
            'lft' => (int) $menu->lft,
            'rgt' => (int) $menu->rgt,
            'hasChildren' => DB::table('menu')->where('parent_id', $id)->exists(),
            'sourceCulture' => $menu->source_culture,
            'serialNumber' => (int) $menu->serial_number,
            'createdAt' => $menu->created_at,
            'updatedAt' => $menu->updated_at,
            'label' => $menu->label ?? '',
            'description' => $menu->description ?? '',
            'isProtected' => $this->isProtected($id),
        ];
    }

    /**
     * Get parent choices for dropdown (indented by depth).
     *
     * Returns an array of [id => indented_label] suitable for a <select>.
     * Excludes ROOT but includes top-level menus.
     *
     * @return array Associative array of id => indented label
     */
    public function getParentChoices(): array
    {
        $tree = $this->getTree();

        $choices = [];
        // Include ROOT as top-level option
        $choices[self::ROOT_ID] = '(Top level)';

        foreach ($tree as $item) {
            $indent = str_repeat('-- ', $item['depth']);
            $label = $item['label'] ?: $item['name'] ?: '(unnamed)';
            $choices[$item['id']] = $indent.$label;
        }

        return $choices;
    }

    /**
     * Check if a menu item is protected (core menu).
     */
    public function isProtected(int $id): bool
    {
        if ($id === self::ROOT_ID) {
            return true;
        }

        $name = DB::table('menu')
            ->where('id', $id)
            ->value('name');

        if (! $name) {
            return false;
        }

        return in_array($name, self::PROTECTED_NAMES, true);
    }

    /**
     * Recompute the MPTT nested set (lft/rgt) for the whole menu tree from the
     * parent_id hierarchy. Fresh installs seed menu rows with NULL lft/rgt, so
     * any nested-set operation (create, reorder) would otherwise fail. A depth-
     * first walk from ROOT_ID assigns contiguous lft/rgt; any node unreachable
     * from the root (dangling parent_id) is appended afterwards so it still gets
     * a valid, non-overlapping range. Moves, reorders and deletes call it
     * after changing parent_id and sibling order, so lft/rgt stays a derived
     * copy (heratio#1541).
     */
    public function rebuildNestedSet(): void
    {
        DB::transaction(function () {
            // Children in sibling order, so a rebuild keeps the order editors set.
            $query = DB::table('menu')->select('menu.id', 'menu.parent_id');
            app(HierarchyQueryService::class)->applySiblingOrder($query, 'menu', 'menu.id', 'menu.lft');
            $rows = $query->orderBy('menu.id')->get();

            $children = [];
            foreach ($rows as $r) {
                $children[$r->parent_id ?? 0][] = $r->id;
            }

            $counter = 1;
            $visited = [];
            $assign = function (int $id) use (&$assign, &$children, &$counter, &$visited) {
                if (isset($visited[$id])) {
                    return;
                }
                $visited[$id] = true;
                $lft = $counter++;
                foreach ($children[$id] ?? [] as $childId) {
                    $assign($childId);
                }
                $rgt = $counter++;
                DB::table('menu')->where('id', $id)->update(['lft' => $lft, 'rgt' => $rgt]);
            };

            $assign(self::ROOT_ID);

            // Defensive: cover any orphaned nodes not reachable from the root.
            foreach ($rows as $r) {
                if (! isset($visited[$r->id])) {
                    $assign((int) $r->id);
                }
            }
        });
    }

    /**
     * Create a new menu item.
     *
     * Inserts at the end of the parent's children (just before parent's rgt).
     * Uses MPTT gap-opening to maintain nested set integrity.
     *
     * @return int The new menu ID
     */
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $parentId = (int) ($data['parentId'] ?? self::ROOT_ID);

            // Get parent node
            $parent = DB::table('menu')
                ->where('id', $parentId)
                ->select('id', 'lft', 'rgt')
                ->first();

            if (! $parent) {
                throw new \RuntimeException('Parent menu not found.');
            }

            // Self-heal: fresh installs seed menu rows with NULL lft/rgt. With
            // a NULL rgt the gap-opening below becomes where('rgt','>=',NULL),
            // which throws "Illegal operator and value combination". Rebuild the
            // nested set once, then re-read the parent.
            if ($parent->lft === null || $parent->rgt === null) {
                $this->rebuildNestedSet();
                $parent = DB::table('menu')
                    ->where('id', $parentId)
                    ->select('id', 'lft', 'rgt')
                    ->first();
            }

            // Insert position: just before parent's rgt (end of children)
            $insertAt = $parent->rgt;

            // Step 1: Open gap of 2 (for a leaf node: lft, rgt)
            DB::table('menu')
                ->where('rgt', '>=', $insertAt)
                ->increment('rgt', 2);

            DB::table('menu')
                ->where('lft', '>', $insertAt)
                ->increment('lft', 2);

            // Step 2: Insert the new menu node
            $now = date('Y-m-d H:i:s');
            $newId = DB::table('menu')->insertGetId([
                'parent_id' => $parentId,
                'name' => $data['name'] ?? null,
                'path' => $data['path'] ?? null,
                'lft' => $insertAt,
                'rgt' => $insertAt + 1,
                'source_culture' => $this->culture,
                'created_at' => $now,
                'updated_at' => $now,
                'serial_number' => 0,
            ]);

            // #1333 dual-write: maintain the menu closure tree alongside lft/rgt.
            app(\AhgCore\Services\ClosureMaintenanceService::class)
                ->addNode('menu', (int) $newId, (int) $parentId);

            // Step 3: Insert i18n record
            $i18nData = [
                'id' => $newId,
                'culture' => $this->culture,
            ];
            if (isset($data['label'])) {
                $i18nData['label'] = $data['label'];
            }
            if (isset($data['description'])) {
                $i18nData['description'] = $data['description'];
            }
            DB::table('menu_i18n')->insert($i18nData);

            return $newId;
        });
    }

    /**
     * Update an existing menu item.
     *
     * Updates menu fields and i18n data. If parent changed, moves the
     * node within the MPTT tree.
     */
    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $current = DB::table('menu')
                ->where('id', $id)
                ->select('id', 'parent_id', 'name', 'path', 'lft', 'rgt')
                ->first();

            if (! $current) {
                throw new \RuntimeException('Menu item not found.');
            }

            // Update menu fields (only non-protected fields)
            $menuUpdate = [];
            if (isset($data['name']) && ! $this->isProtected($id)) {
                $menuUpdate['name'] = $data['name'];
            }
            if (array_key_exists('path', $data)) {
                $menuUpdate['path'] = $data['path'];
            }

            if (! empty($menuUpdate)) {
                $menuUpdate['updated_at'] = date('Y-m-d H:i:s');
                $menuUpdate['serial_number'] = DB::raw('serial_number + 1');
                DB::table('menu')->where('id', $id)->update($menuUpdate);
            } else {
                DB::table('menu')->where('id', $id)->update([
                    'updated_at' => date('Y-m-d H:i:s'),
                    'serial_number' => DB::raw('serial_number + 1'),
                ]);
            }

            // Update i18n
            $i18nData = [];
            if (array_key_exists('label', $data)) {
                $i18nData['label'] = $data['label'];
            }
            if (array_key_exists('description', $data)) {
                $i18nData['description'] = $data['description'];
            }
            if (! empty($i18nData)) {
                $exists = DB::table('menu_i18n')
                    ->where('id', $id)
                    ->where('culture', $this->culture)
                    ->exists();

                if ($exists) {
                    DB::table('menu_i18n')
                        ->where('id', $id)
                        ->where('culture', $this->culture)
                        ->update($i18nData);
                } else {
                    $i18nData['id'] = $id;
                    $i18nData['culture'] = $this->culture;
                    DB::table('menu_i18n')->insert($i18nData);
                }
            }

            // Handle parent change (MPTT move)
            $newParentId = isset($data['parentId']) ? (int) $data['parentId'] : null;
            if ($newParentId !== null && $newParentId !== (int) $current->parent_id) {
                $this->moveToParent($id, $newParentId);
            }
        });
    }

    /**
     * Delete a menu item and its descendants.
     *
     * Removes the node (and subtree) from the MPTT tree and closes the gap.
     *
     * @throws \RuntimeException if the menu is protected
     */
    public function delete(int $id): void
    {
        if ($this->isProtected($id)) {
            throw new \RuntimeException('Cannot delete a protected menu item.');
        }

        DB::transaction(function () use ($id) {
            if (! DB::table('menu')->where('id', $id)->exists()) {
                throw new \RuntimeException('Menu item not found.');
            }

            // The subtree from the closure: a lft/rgt range swept in other
            // items whenever the bounds were stale (heratio#1541).
            $subtreeIds = app(HierarchyQueryService::class)->descendantIds('menu', $id, true);

            DB::table('menu_i18n')->whereIn('id', $subtreeIds)->delete();
            DB::table('menu')->whereIn('id', $subtreeIds)->delete();

            // #1333 dual-write: menu_closure rows cascade via the FK; clear the
            // FK-less sibling sidecar for the deleted subtree.
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_node_sibling_order')) {
                DB::table('ahg_node_sibling_order')
                    ->where('entity', 'menu')
                    ->whereIn('node_id', $subtreeIds)
                    ->delete();
            }

            $this->rebuildNestedSet();
        });
    }

    /**
     * Swap a menu item with its previous ($step = -1) or next ($step = 1)
     * sibling. Swapping lft/rgt values left each item's children behind, under
     * the other sibling; this reorders the siblings and rebuilds the bounds.
     */
    public function moveSibling(int $id, int $step): void
    {
        DB::transaction(function () use ($id, $step) {
            $parentId = DB::table('menu')->where('id', $id)->value('parent_id');
            $query = DB::table('menu')->where('menu.parent_id', $parentId)->select('menu.id');
            app(HierarchyQueryService::class)->applySiblingOrder($query, 'menu', 'menu.id', 'menu.lft');
            $ids = $query->orderBy('menu.id')->pluck('menu.id')->map(fn ($v) => (int) $v)->all();

            $at = array_search($id, $ids, true);
            if ($at === false || ! isset($ids[$at + $step])) {
                return;
            }
            [$ids[$at], $ids[$at + $step]] = [$ids[$at + $step], $ids[$at]];

            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_node_sibling_order')) {
                foreach ($ids as $order => $siblingId) {
                    DB::table('ahg_node_sibling_order')->updateOrInsert(
                        ['entity' => 'menu', 'node_id' => $siblingId],
                        ['parent_id' => $parentId, 'sibling_order' => $order]
                    );
                }
                $this->rebuildNestedSet();
            }
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Private MPTT helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Move a node to a different parent (append as last child).
     *
     * @param  int  $id  Node ID to move
     * @param  int  $newParentId  New parent ID
     * @return bool True on success
     */
    private function moveToParent(int $id, int $newParentId): bool
    {
        $node = DB::table('menu')->where('id', $id)->select('id', 'parent_id')->first();

        if (! $node || ! DB::table('menu')->where('id', $newParentId)->exists()) {
            return false;
        }

        // Cannot move a node to be a descendant of itself
        $hierarchy = app(HierarchyQueryService::class);
        if ($hierarchy->isWithin('menu', $newParentId, $id)) {
            return false;
        }

        DB::table('menu')
            ->where('id', $id)
            ->update(['parent_id' => $newParentId]);

        // #1333 dual-write: move the menu closure subtree (appended as the new
        // parent's last child), close the old parent's order, rebuild lft/rgt.
        $closureSvc = app(\AhgCore\Services\ClosureMaintenanceService::class);
        $closureSvc->moveNode('menu', (int) $id, (int) $newParentId);
        $this->rebuildNestedSet();
        $closureSvc->resyncSiblingOrder('menu', $node->parent_id !== null ? (int) $node->parent_id : null);

        return true;
    }
}
