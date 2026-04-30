<?php

/**
 * TreeviewService - Service for Heratio
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



namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Facades\DB;

/**
 * Treeview service for information object hierarchy.
 *
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgInformationObjectManagePlugin/lib/Services/TreeviewService.php
 *
 * Uses nested set (lft/rgt) on the information_object table to provide
 * hierarchical navigation data for the sidebar treeview.
 */
class TreeviewService
{
    public const ROOT_ID = 1;

    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
    }

    /**
     * Get full initial treeview data for a node (ancestors + current + children/siblings).
     *
     * Used for the sidebar treeview initial render.
     *
     * @return array Complete treeview data structure
     */
    public function getTreeViewData(int $id, int $limit = 10): array
    {
        $isAuthenticated = auth()->check();
        $numberOfSiblings = $limit;

        $node = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $id)
            ->select(
                'io.id', 'io.identifier', 'io.lft', 'io.rgt',
                'io.parent_id', 'io.level_of_description_id',
                'ioi.title', 's.slug'
            )
            ->first();

        if (!$node) {
            return ['error' => 'Node not found'];
        }

        $ancestors = $this->getAncestors($id);
        $currentNode = $this->formatNode($node);

        $hasChildren = DB::table('information_object')
            ->where('parent_id', $id)
            ->exists();

        // Count total children
        $totalChildren = 0;
        if ($hasChildren) {
            $totalChildren = DB::table('information_object')
                ->where('parent_id', $id)
                ->count();
        }

        $children = [];
        $prevSiblings = [];
        $nextSiblings = [];
        $hasPrevSiblings = false;
        $hasNextSiblings = false;

        if ($hasChildren) {
            $childResult = $this->getChildren($id, $numberOfSiblings + 1);
            $children = $childResult['items'];
            $hasNextSiblings = count($children) > $numberOfSiblings;
            if ($hasNextSiblings) {
                array_pop($children);
            }
        } elseif ($node->parent_id != self::ROOT_ID) {
            // Show siblings when current node has no children
            $prevResult = $this->getSiblings($id, 'prev', $numberOfSiblings);
            $prevSiblings = $prevResult['items'];
            $hasPrevSiblings = $prevResult['hasMore'];

            $nextResult = $this->getSiblings($id, 'next', $numberOfSiblings);
            $nextSiblings = $nextResult['items'];
            $hasNextSiblings = $nextResult['hasMore'];
        }

        return [
            'ancestors' => $ancestors,
            'current' => $currentNode,
            'hasChildren' => $hasChildren,
            'totalChildren' => $totalChildren,
            'children' => $children,
            'hasMore' => $hasNextSiblings,
            'prevSiblings' => $prevSiblings,
            'nextSiblings' => $nextSiblings,
            'hasPrevSiblings' => $hasPrevSiblings,
            'hasNextSiblings' => $hasNextSiblings,
        ];
    }

    /**
     * Get paginated children for lazy loading.
     *
     * @return array ['items' => [...], 'hasMore' => bool]
     */
    public function getChildren(int $parentId, int $limit = 10, int $offset = 0): array
    {
        $isAuthenticated = auth()->check();

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.parent_id', $parentId)
            ->select(
                'io.id', 'io.identifier', 'io.lft', 'io.rgt',
                'io.parent_id', 'io.level_of_description_id',
                'ioi.title', 's.slug'
            );

        // Publication status filtering for unauthenticated users
        if (!$isAuthenticated) {
            $query->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })->where('st.status_id', 160);
        }

        $query->orderBy('io.lft');

        // Get total count for hasMore
        $totalChildren = (clone $query)->count();

        // Apply offset and limit
        $items = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($parentId) {
                $node = $this->formatNode($row);
                $node['parentId'] = $parentId;
                return $node;
            })
            ->all();

        return [
            'items' => $items,
            'hasMore' => ($offset + $limit) < $totalChildren,
        ];
    }

    /**
     * Walk up the tree and return array of ancestors from root down.
     *
     * Excludes ROOT_ID (1) from results.
     *
     * @return array Array of node arrays ordered from root to parent
     */
    public function getAncestors(int $id): array
    {
        $node = DB::table('information_object')
            ->where('id', $id)
            ->select('lft', 'rgt')
            ->first();

        if (!$node) {
            return [];
        }

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.lft', '<', $node->lft)
            ->where('io.rgt', '>', $node->rgt)
            ->where('io.id', '!=', self::ROOT_ID)
            ->select(
                'io.id', 'io.identifier', 'io.lft', 'io.rgt',
                'io.parent_id', 'io.level_of_description_id',
                'ioi.title', 's.slug'
            )
            ->orderBy('io.lft');

        return $query->get()->map(function ($row) {
            return $this->formatNode($row);
        })->all();
    }

    /**
     * Get previous or next siblings of a node.
     *
     * For 'next': lft > current.rgt AND parent_id = current.parent_id, ordered by lft ASC.
     * For 'prev': rgt < current.lft AND parent_id = current.parent_id, ordered by lft DESC.
     *
     * @return array ['items' => [...], 'hasMore' => bool]
     */
    public function getSiblings(int $id, string $direction = 'next', int $limit = 5): array
    {
        $isAuthenticated = auth()->check();

        $node = DB::table('information_object')
            ->where('id', $id)
            ->select('id', 'parent_id', 'lft', 'rgt')
            ->first();

        if (!$node || $node->parent_id == self::ROOT_ID) {
            return ['items' => [], 'hasMore' => false];
        }

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.parent_id', $node->parent_id)
            ->where('io.id', '!=', $node->id)
            ->select(
                'io.id', 'io.identifier', 'io.lft', 'io.rgt',
                'io.parent_id', 'io.level_of_description_id',
                'ioi.title', 's.slug'
            );

        // Publication status filtering for unauthenticated users
        if (!$isAuthenticated) {
            $query->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })->where('st.status_id', 160);
        }

        if ($direction === 'next') {
            $query->where('io.lft', '>', $node->rgt)
                ->orderBy('io.lft', 'asc');
        } else {
            $query->where('io.rgt', '<', $node->lft)
                ->orderBy('io.lft', 'desc');
        }

        // Get total count
        $totalCount = (clone $query)->count();

        // Get limited results (+1 to detect hasMore)
        $items = $query->limit($limit + 1)
            ->get()
            ->map(function ($row) use ($node) {
                $n = $this->formatNode($row);
                $n['parentId'] = $node->parent_id;
                return $n;
            })
            ->all();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        // Reverse for previous siblings (queried in descending order)
        if ($direction === 'prev') {
            $items = array_reverse($items);
        }

        return [
            'items' => $items,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * Move a node after another node (drag-drop reorder).
     *
     * Uses the nested set negate-close-open-restore pattern.
     * Both nodes must share the same parent (siblings only).
     *
     * @return bool Success
     */
    public function moveAfter(int $id, int $targetId): bool
    {
        $node = DB::table('information_object')
            ->where('id', $id)
            ->select('id', 'lft', 'rgt', 'parent_id')
            ->first();

        $after = DB::table('information_object')
            ->where('id', $targetId)
            ->select('id', 'lft', 'rgt', 'parent_id')
            ->first();

        if (!$node || !$after || $node->parent_id !== $after->parent_id) {
            return false;
        }

        $width = $node->rgt - $node->lft + 1;
        $newPos = $after->rgt + 1;

        DB::beginTransaction();

        try {
            // Step 1: Temporarily set node values to negative (to avoid conflicts)
            DB::table('information_object')
                ->where('lft', '>=', $node->lft)
                ->where('rgt', '<=', $node->rgt)
                ->update([
                    'lft' => DB::raw('lft * -1'),
                    'rgt' => DB::raw('rgt * -1'),
                ]);

            // Step 2: Close the gap left by the moved node
            DB::table('information_object')
                ->where('lft', '>', $node->rgt)
                ->decrement('lft', $width);

            DB::table('information_object')
                ->where('rgt', '>', $node->rgt)
                ->decrement('rgt', $width);

            // Recalculate newPos if it was affected by the gap closure
            if ($newPos > $node->rgt) {
                $newPos -= $width;
            }

            // Step 3: Open gap at the new position
            DB::table('information_object')
                ->where('lft', '>=', $newPos)
                ->increment('lft', $width);

            DB::table('information_object')
                ->where('rgt', '>=', $newPos)
                ->increment('rgt', $width);

            // Step 4: Move the node to the new position
            $offset = $newPos - $node->lft;
            DB::table('information_object')
                ->where('lft', '<', 0)
                ->update([
                    'lft' => DB::raw('(lft * -1) + ' . $offset),
                    'rgt' => DB::raw('(rgt * -1) + ' . $offset),
                ]);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }
    }

    /**
     * Format a database row into a treeview node array.
     */
    private function formatNode(object $row): array
    {
        $hasChildren = ($row->rgt - $row->lft) > 1;

        return [
            'id' => (int) $row->id,
            'title' => $row->title ?? '',
            'slug' => $row->slug ?? '',
            'identifier' => $row->identifier ?? '',
            'levelOfDescriptionId' => $row->level_of_description_id ? (int) $row->level_of_description_id : null,
            'parentId' => isset($row->parent_id) ? (int) $row->parent_id : null,
            'lft' => (int) $row->lft,
            'rgt' => (int) $row->rgt,
            'hasChildren' => $hasChildren,
        ];
    }
}
