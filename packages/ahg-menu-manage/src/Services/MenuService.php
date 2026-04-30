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

use Illuminate\Support\Facades\DB;

/**
 * Menu CRUD Service
 *
 * Instance-based service for menu operations.
 * Handles MPTT nested set (lft/rgt) for menu hierarchy.
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
     * Get the full menu tree as a flat array with depth, sorted by lft.
     * Excludes the ROOT node.
     *
     * @return array List of menu items with depth, parent_id, name, label, protected status
     */
    public function getTree(): array
    {
        $items = DB::table('menu as m')
            ->leftJoin('menu_i18n as mi', function ($join) {
                $join->on('m.id', '=', 'mi.id')
                    ->where('mi.culture', '=', $this->culture);
            })
            ->where('m.id', '!=', self::ROOT_ID)
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
            )
            ->orderBy('m.lft')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        // Calculate depth for each item using the nested set
        $result = [];
        foreach ($items as $item) {
            $depth = 0;
            foreach ($items as $potentialParent) {
                if ($potentialParent['lft'] < $item['lft'] && $potentialParent['rgt'] > $item['rgt']) {
                    $depth++;
                }
            }
            $item['depth'] = $depth;
            $item['isProtected'] = $this->isProtected($item['id']);
            $item['hasChildren'] = ($item['rgt'] - $item['lft']) > 1;
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

        if (!$menu) {
            return null;
        }

        return [
            'id' => (int) $menu->id,
            'parentId' => $menu->parent_id ? (int) $menu->parent_id : null,
            'name' => $menu->name ?? '',
            'path' => $menu->path ?? '',
            'lft' => (int) $menu->lft,
            'rgt' => (int) $menu->rgt,
            'hasChildren' => ($menu->rgt - $menu->lft) > 1,
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
            $choices[$item['id']] = $indent . $label;
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

        if (!$name) {
            return false;
        }

        return in_array($name, self::PROTECTED_NAMES, true);
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

            if (!$parent) {
                throw new \RuntimeException('Parent menu not found.');
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

            if (!$current) {
                throw new \RuntimeException('Menu item not found.');
            }

            // Update menu fields (only non-protected fields)
            $menuUpdate = [];
            if (isset($data['name']) && !$this->isProtected($id)) {
                $menuUpdate['name'] = $data['name'];
            }
            if (array_key_exists('path', $data)) {
                $menuUpdate['path'] = $data['path'];
            }

            if (!empty($menuUpdate)) {
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
            if (!empty($i18nData)) {
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
            $node = DB::table('menu')
                ->where('id', $id)
                ->select('id', 'lft', 'rgt')
                ->first();

            if (!$node) {
                throw new \RuntimeException('Menu item not found.');
            }

            $width = $node->rgt - $node->lft + 1;

            // Get all IDs in the subtree (for i18n cleanup)
            $subtreeIds = DB::table('menu')
                ->where('lft', '>=', $node->lft)
                ->where('rgt', '<=', $node->rgt)
                ->pluck('id')
                ->all();

            // Step 1: Delete i18n records for all nodes in subtree
            if (!empty($subtreeIds)) {
                DB::table('menu_i18n')->whereIn('id', $subtreeIds)->delete();
            }

            // Step 2: Delete all nodes in the subtree
            DB::table('menu')
                ->where('lft', '>=', $node->lft)
                ->where('rgt', '<=', $node->rgt)
                ->delete();

            // Step 3: Close the gap
            DB::table('menu')
                ->where('lft', '>', $node->rgt)
                ->decrement('lft', $width);

            DB::table('menu')
                ->where('rgt', '>', $node->rgt)
                ->decrement('rgt', $width);
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Private MPTT helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Move a node to a different parent (append as last child).
     *
     * @param int $id          Node ID to move
     * @param int $newParentId New parent ID
     *
     * @return bool True on success
     */
    private function moveToParent(int $id, int $newParentId): bool
    {
        $node = DB::table('menu')
            ->where('id', $id)
            ->select('id', 'lft', 'rgt', 'parent_id')
            ->first();

        if (!$node) {
            return false;
        }

        $newParent = DB::table('menu')
            ->where('id', $newParentId)
            ->select('id', 'lft', 'rgt')
            ->first();

        if (!$newParent) {
            return false;
        }

        // Cannot move a node to be a descendant of itself
        if ($newParent->lft >= $node->lft && $newParent->rgt <= $node->rgt) {
            return false;
        }

        $width = $node->rgt - $node->lft + 1;

        // Step 1: Negate the subtree
        DB::table('menu')
            ->where('lft', '>=', $node->lft)
            ->where('rgt', '<=', $node->rgt)
            ->update([
                'lft' => DB::raw('lft * -1'),
                'rgt' => DB::raw('rgt * -1'),
            ]);

        // Step 2: Close the gap
        DB::table('menu')
            ->where('lft', '>', $node->rgt)
            ->decrement('lft', $width);

        DB::table('menu')
            ->where('rgt', '>', $node->rgt)
            ->decrement('rgt', $width);

        // Re-fetch the new parent (its values may have changed)
        $newParent = DB::table('menu')
            ->where('id', $newParentId)
            ->select('id', 'lft', 'rgt')
            ->first();

        $insertAt = $newParent->rgt;

        // Step 3: Open gap at new position
        DB::table('menu')
            ->where('lft', '>=', $insertAt)
            ->increment('lft', $width);

        DB::table('menu')
            ->where('rgt', '>=', $insertAt)
            ->increment('rgt', $width);

        // Step 4: Restore the subtree at new position
        $offset = $insertAt - $node->lft;
        DB::table('menu')
            ->where('lft', '<', 0)
            ->update([
                'lft' => DB::raw('(lft * -1) + ' . $offset),
                'rgt' => DB::raw('(rgt * -1) + ' . $offset),
            ]);

        // Step 5: Update parent_id
        DB::table('menu')
            ->where('id', $id)
            ->update(['parent_id' => $newParentId]);

        return true;
    }
}
