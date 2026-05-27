<?php

/**
 * HierarchyDataController - jstree JSON feed for the IO browse hierarchy.
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
 * informationObjectHierarchyDataAction.class.php (issue #742).
 *
 * Returns the jstree-formatted JSON the new full-width tree page consumes.
 * Lazy-loads children on demand using parent_id; an initial GET without
 * ?root_id returns the top-level (parent_id = ROOT_ID).
 */

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HierarchyDataController extends Controller
{
    /** Same nested-set root as TreeviewService::ROOT_ID. */
    private const ROOT_ID = 1;

    /**
     * GET /informationobject/browse/hierarchyData?root_id=...
     *
     * Returns a jstree node array. Each node has:
     *   - id (string, e.g. "io-42")
     *   - text (HTML-safe title)
     *   - children (bool: lazy-load placeholder if it has any)
     *   - a_attr.href (slug-routed show URL)
     *   - data.slug, data.identifier, data.real_id (raw int)
     *
     * When the optional ?id=... param is supplied, jstree convention says
     * we serve children of that node. Without either we serve the top of
     * the tree.
     */
    public function data(Request $request): JsonResponse
    {
        $rootId = (int) $request->get('root_id', $request->get('id', 0));
        if ($rootId === 0 || $rootId === -1) {
            // jstree fires id=# on the initial load - treat that as the
            // top-level request.
            $rootId = self::ROOT_ID;
        }

        $culture = app()->getLocale();
        $isAuthenticated = auth()->check();

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.parent_id', $rootId)
            ->select(
                'io.id', 'io.identifier', 'io.lft', 'io.rgt',
                'io.parent_id', 'io.level_of_description_id',
                'ioi.title', 's.slug'
            )
            ->orderBy('io.lft');

        if (!$isAuthenticated) {
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
                    'level_of_description_id' => $r->level_of_description_id ? (int) $r->level_of_description_id : null,
                ],
            ];
        })->all();

        return response()->json($nodes);
    }
}
