<?php

/**
 * TreeViewPageController - Dedicated full-width tree-view page + sync + move.
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
 *
 * Migrated from PSIS atom-ahg-plugins/ahgInformationObjectManagePlugin/lib/Action/
 * informationObjectTreeViewAction.class.php (issue #742).
 *
 * Three endpoints:
 *   1. GET  /informationobject/{slug}/tree-view  - full-width jstree page,
 *      ancestor chain pre-expanded, current node highlighted.
 *   2. POST /informationobject/tree-sync/{id}    - re-fetch a single subtree
 *      (children of {id}) as jstree JSON. Used by the "Sync" button after
 *      external edits.
 *   3. POST /informationobject/tree-move         - move-by-drag: parent_id
 *      update with full nested-set lft/rgt rewrite of the moved subtree.
 *
 * Lock note: NEVER reached from show.blade.php. The show-page sidebar uses
 * the existing partials/_treeview.blade.php + TreeviewController endpoints
 * (which we do not touch).
 */

namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Services\TreeviewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreeViewPageController extends Controller
{
    private const ROOT_ID = 1;

    /**
     * GET /informationobject/{slug}/tree-view
     */
    public function show(string $slug)
    {
        $culture = app()->getLocale();

        $resource = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();

        if (!$resource) {
            abort(404);
        }

        $service = new TreeviewService($culture);
        $ancestors = $service->getAncestors((int) $resource->id);

        // Pre-expand chain: real IO ids of every ancestor + the current node.
        $expandIds = array_map(fn ($a) => (int) $a['id'], $ancestors);
        $expandIds[] = (int) $resource->id;

        return view('ahg-io-manage::tree.view', [
            'resource' => $resource,
            'ancestors' => $ancestors,
            'expandIds' => $expandIds,
            'canEdit' => auth()->check(),
        ]);
    }

    /**
     * POST /informationobject/tree-sync/{id}
     *
     * Returns the children of {id} as jstree nodes - same shape as
     * HierarchyDataController::data(). Used after external edits to
     * refresh a single subtree without a full page reload.
     */
    public function sync(int $id): JsonResponse
    {
        $culture = app()->getLocale();
        // Only editors/admins may see unpublished (draft) nodes; a plain
        // authenticated account is filtered to published, same as anonymous
        // (parity with HierarchyDataController - closes the draft-title leak).
        $canSeeDrafts = auth()->check() && \AhgCore\Services\AclService::canAdmin(auth()->id());

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.parent_id', $id)
            ->select(
                'io.id', 'io.identifier', 'io.lft', 'io.rgt',
                'io.parent_id', 'io.level_of_description_id',
                'ioi.title', 's.slug'
            )
            ->orderBy('io.lft');

        if (!$canSeeDrafts) {
            $query->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')->where('st.type_id', '=', 158);
            })->where('st.status_id', 160);
        }

        $rows = $query->limit(500)->get();

        $nodes = $rows->map(function ($r) {
            $hasChildren = ($r->rgt - $r->lft) > 1;
            $title = $r->title ?: ($r->identifier ?: ('#' . $r->id));

            return [
                'id' => 'io-' . (int) $r->id,
                'text' => $title,
                'children' => $hasChildren,
                'a_attr' => [
                    'href' => $r->slug ? url('/' . $r->slug) : '#',
                ],
                'data' => [
                    'real_id' => (int) $r->id,
                    'slug' => $r->slug,
                    'identifier' => $r->identifier,
                    'has_children' => $hasChildren,
                ],
            ];
        })->all();

        return response()->json([
            'ok' => true,
            'parent_id' => $id,
            'children' => $nodes,
        ]);
    }

    /**
     * POST /informationobject/tree-move
     *
     * Body: { id: <node_id>, new_parent_id: <parent_id>, position?: <int> }
     *
     * Re-parents the node and rebuilds the lft/rgt range for the moved
     * subtree. Transactional - rolls back if any step fails.
     *
     * NOTE: We do not allow:
     *   - moving the root (id=1) anywhere
     *   - moving a node into its own descendant (would create a cycle)
     *   - moving anything to be a child of itself
     */
    public function move(Request $request): JsonResponse
    {
        $id = (int) $request->input('id', 0);
        $newParentId = (int) $request->input('new_parent_id', 0);

        if (!$id || !$newParentId) {
            return response()->json([
                'ok' => false,
                'error' => 'Missing required parameters: id, new_parent_id',
            ], 422);
        }

        if ($id === self::ROOT_ID) {
            return response()->json([
                'ok' => false,
                'error' => 'Cannot move the root node.',
            ], 422);
        }

        if ($id === $newParentId) {
            return response()->json([
                'ok' => false,
                'error' => 'A node cannot be its own parent.',
            ], 422);
        }

        $node = DB::table('information_object')
            ->where('id', $id)
            ->select('id', 'lft', 'rgt', 'parent_id')
            ->first();

        $newParent = DB::table('information_object')
            ->where('id', $newParentId)
            ->select('id', 'lft', 'rgt', 'parent_id')
            ->first();

        if (!$node || !$newParent) {
            return response()->json([
                'ok' => false,
                'error' => 'Node or new parent not found.',
            ], 404);
        }

        // Forbid moves into the node's own subtree.
        if ($newParent->lft >= $node->lft && $newParent->rgt <= $node->rgt) {
            return response()->json([
                'ok' => false,
                'error' => 'Cannot move a node into its own descendant.',
            ], 422);
        }

        if ($node->parent_id === $newParent->id) {
            // No-op: same parent.
            return response()->json([
                'ok' => true,
                'noop' => true,
            ]);
        }

        $width = $node->rgt - $node->lft + 1;

        // #1333: capture the moved node's ancestor chain BEFORE any mutation so
        // we can fire a single ES ancestor-delta after commit.
        $hierarchy = app(\AhgCore\Services\HierarchyQueryService::class);
        $oldAncestors = $hierarchy->ancestorIds('information_object', (int) $id);

        DB::beginTransaction();
        try {
            // Step 1: temporarily negate the moved subtree's lft/rgt so
            // shifts that follow don't touch them.
            DB::table('information_object')
                ->where('lft', '>=', $node->lft)
                ->where('rgt', '<=', $node->rgt)
                ->update([
                    'lft' => DB::raw('lft * -1'),
                    'rgt' => DB::raw('rgt * -1'),
                ]);

            // Step 2: close the gap left behind.
            DB::table('information_object')
                ->where('lft', '>', $node->rgt)
                ->decrement('lft', $width);

            DB::table('information_object')
                ->where('rgt', '>', $node->rgt)
                ->decrement('rgt', $width);

            // Step 3: re-read the new parent (its rgt may have shifted
            // when the gap closed).
            $freshParent = DB::table('information_object')
                ->where('id', $newParentId)
                ->select('lft', 'rgt')
                ->first();

            $insertAt = $freshParent->rgt;

            // Step 4: open a gap of `width` at the insertion point.
            DB::table('information_object')
                ->where('lft', '>=', $insertAt)
                ->increment('lft', $width);

            DB::table('information_object')
                ->where('rgt', '>=', $insertAt)
                ->increment('rgt', $width);

            // Step 5: restore the negated subtree at the insertion point.
            $offset = $insertAt - $node->lft;
            DB::table('information_object')
                ->where('lft', '<', 0)
                ->update([
                    'lft' => DB::raw('(lft * -1) + ' . $offset),
                    'rgt' => DB::raw('(rgt * -1) + ' . $offset),
                ]);

            // Step 6: update parent_id on the moved node itself.
            DB::table('information_object')
                ->where('id', $id)
                ->update(['parent_id' => $newParentId]);

            \AhgCore\Support\AuditLog::captureMutation($id, 'information_object', 'move', [
                'data' => [
                    'old_parent_id' => (int) $node->parent_id,
                    'new_parent_id' => $newParentId,
                ],
            ]);

            // #1333 dual-write: true reparent - move the closure subtree and
            // re-derive sibling order for the old and new parents.
            $closureSvc = app(\AhgCore\Services\ClosureMaintenanceService::class);
            $closureSvc->moveNode('information_object', (int) $id, (int) $newParentId);
            $closureSvc->resyncSiblingOrder('information_object', $node->parent_id !== null ? (int) $node->parent_id : null);
            $closureSvc->resyncSiblingOrder('information_object', (int) $newParentId);

            DB::commit();

            // #1333: one async _update_by_query repoints the `ancestors` of the
            // whole moved subtree in ES (the delta is identical for every node in
            // the subtree). Best-effort - never fails the move.
            $newAncestors = $hierarchy->ancestorIds('information_object', (int) $id);
            app(\AhgSearch\Services\ElasticsearchService::class)
                ->updateSubtreeAncestorsOnMove((int) $id, $oldAncestors, $newAncestors);

            return response()->json([
                'ok' => true,
                'id' => $id,
                'new_parent_id' => $newParentId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ok' => false,
                'error' => 'Move failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
