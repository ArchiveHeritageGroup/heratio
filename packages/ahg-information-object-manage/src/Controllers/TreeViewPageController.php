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
 *   3. POST /informationobject/tree-move         - move-by-drag, now served by
 *      TreeMoveController (heratio#1582).
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
use AhgCore\Support\I18nFallback;

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
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as i18n', 'io.id', $culture))
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'io.identifier', I18nFallback::col('i18n', 'title', 'title'), 's.slug')
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
            'canEdit' => \AhgCore\Services\AclService::hasPermission(auth()->id(), 'update', (int) $resource->id),
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
                'ioi.title', 's.slug',
                DB::raw('EXISTS (SELECT 1 FROM information_object c WHERE c.parent_id = io.id) AS has_children')
            )
            ->orderBy('io.lft');

        if (!$canSeeDrafts) {
            $query->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')->where('st.type_id', '=', 158);
            })->where('st.status_id', 160);
        }

        $rows = $query->limit(500)->get();

        $nodes = $rows->map(function ($r) {
            $hasChildren = (bool) $r->has_children;
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
}
