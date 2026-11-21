<?php

/**
 * TreeviewController - Controller for Heratio
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



namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Services\TreeviewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreeviewController extends Controller
{
    /**
     * A short preview of one record for the hierarchy's hover card: title,
     * reference, level, dates, the opening of its scope and content, and its
     * thumbnail. A visitor sees only records the public may see (published,
     * not restricted); a signed-in user sees what the tree already shows them.
     */
    public function preview(int $id): JsonResponse
    {
        if (! auth()->check() && ! app(\AhgCore\Services\DisclosureGate::class)->allows($id)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = (new TreeviewService())->preview($id);

        return $data
            ? response()->json($data)->header('Cache-Control', 'private, max-age=300')
            : response()->json(['error' => 'Not found'], 404);
    }

    /**
     * JSON API endpoint for treeview operations.
     *
     * Params:
     *   - id (required): Node ID
     *   - show: item|children|prevSiblings|nextSiblings (default: children)
     *   - limit: max items (default 10, max 50)
     *   - offset: pagination offset (default 0)
     */
    public function treeview(Request $request): JsonResponse
    {
        $id = (int) $request->get('id');
        if (!$id) {
            return response()->json(['error' => 'Missing required parameter: id'], 400);
        }

        $show = $request->get('show', 'children');
        $limit = min((int) ($request->get('limit', 10)), 50);
        $offset = (int) $request->get('offset', 0);
        $culture = app()->getLocale();

        $service = new TreeviewService($culture);

        switch ($show) {
            case 'item':
                $ancestors = $service->getAncestors($id);
                return response()->json([
                    'ancestors' => $ancestors,
                ]);

            case 'children':
                $result = $service->getChildren($id, $limit, $offset);
                return response()->json($result);

            case 'prevSiblings':
                $result = $service->getSiblings($id, 'prev', $limit);
                return response()->json($result);

            case 'nextSiblings':
                $result = $service->getSiblings($id, 'next', $limit);
                return response()->json($result);

            default:
                return response()->json(['error' => 'Invalid show parameter'], 400);
        }
    }

    /**
     * Full initial treeview data load.
     *
     * Params:
     *   - id (required): Current node ID
     *
     * Returns JSON with ancestors, current node, children, siblings.
     */
    public function treeviewData(Request $request): JsonResponse
    {
        $id = (int) $request->get('id');
        if (!$id) {
            return response()->json(['error' => 'Missing required parameter: id'], 400);
        }

        $culture = app()->getLocale();
        $service = new TreeviewService($culture);

        $data = $service->getTreeViewData($id);

        return response()->json($data);
    }

    /**
     * Drag-drop sort endpoint.
     *
     * Params:
     *   - id (required): Node to move
     *   - target (required): Node to place after
     *
     * Auth required (middleware applied in routes).
     */
    public function treeviewSort(Request $request): JsonResponse
    {
        $id = (int) $request->get('id');
        $target = (int) $request->get('target');
        $position = (string) $request->get('position', 'after');

        if (!$id || !$target || !in_array($position, ['after', 'before'], true)) {
            return response()->json(['error' => 'Missing or invalid parameters: id, target, position'], 400);
        }

        // heratio#1582: one move for every tree surface. The sibling becomes a
        // position under the shared parent; TreeMoveService checks update on
        // the record and its parent, and orders records without nested-set
        // bounds by sibling order (the lft shift alone could not).
        $node = \Illuminate\Support\Facades\DB::table('information_object')->where('id', $id)->first(['id', 'parent_id']);
        $after = \Illuminate\Support\Facades\DB::table('information_object')->where('id', $target)->first(['id', 'parent_id']);
        if (!$node || !$after || (int) $node->parent_id !== (int) $after->parent_id || $id === $target) {
            return response()->json(['error' => 'Move failed. Nodes must be siblings.'], 422);
        }

        $moves = app(\AhgInformationObjectManage\Services\TreeMoveService::class);
        $others = array_values(array_diff($moves->childIds((int) $node->parent_id), [$id]));
        $index = (int) array_search($target, $others, true) + ($position === 'after' ? 1 : 0);
        $result = $moves->move($id, (int) $node->parent_id, $index, false, \Illuminate\Support\Facades\Auth::id());

        if ($result['ok']) {
            return response()->json(['success' => true] + $result);
        }

        return response()->json(['error' => $result['status'] === 403 ? 'Insufficient permissions' : $result['error']], $result['status']);
    }
}
