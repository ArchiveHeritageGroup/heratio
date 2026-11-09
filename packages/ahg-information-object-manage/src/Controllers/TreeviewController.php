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

        // The route's acl:update is a global permission; reordering rewrites this
        // record's place in the hierarchy, so check update on the record itself too.
        $node = \Illuminate\Support\Facades\DB::table('information_object')->where('id', $id)->first();
        if (!$node || !\AhgCore\Services\AclService::check($node, 'update')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $culture = app()->getLocale();
        $service = new TreeviewService($culture);

        $success = $position === 'before'
            ? $service->moveBefore($id, $target)
            : $service->moveAfter($id, $target);

        if ($success) {
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Move failed. Nodes must be siblings.'], 422);
    }
}
