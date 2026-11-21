<?php

/**
 * TreeMoveController - tree moves by drag-and-drop or the "Move..." picker (heratio#1582).
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

use AhgInformationObjectManage\Services\TreeMoveService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TreeMoveController extends Controller
{
    public function __construct(private TreeMoveService $moves) {}

    /**
     * POST /informationobject/tree-move
     *
     * Body: id, new_parent_id, position (0-based among the new siblings; empty
     * = last), confirm (1 to allow a move into another fonds). A 409 with
     * code "confirm" asks the caller to confirm and resend. The answer holds
     * "undo": the body that moves the record back.
     */
    public function move(Request $request): JsonResponse
    {
        $v = $request->validate([
            'id' => 'required|integer|min:1',
            'new_parent_id' => 'required|integer|min:1',
            'position' => 'nullable|integer|min:0',
            'confirm' => 'nullable|boolean',
        ]);

        $result = $this->moves->move(
            (int) $v['id'],
            (int) $v['new_parent_id'],
            isset($v['position']) ? (int) $v['position'] : null,
            (bool) ($v['confirm'] ?? false),
            Auth::id()
        );

        return response()->json($result, $result['status']);
    }

    /** GET /informationobject/tree-move/targets?q=&id= - parents for the picker. */
    public function targets(Request $request): JsonResponse
    {
        $v = $request->validate(['q' => 'required|string|min:2|max:255', 'id' => 'nullable|integer|min:1']);

        return response()->json(['items' => $this->moves->searchTargets($v['q'], (int) ($v['id'] ?? 0), Auth::id())]);
    }

    /** GET /informationobject/tree-move/children/{id} - the position choices under a parent. */
    public function children(int $id): JsonResponse
    {
        if (! \AhgCore\Services\AclService::hasPermission(Auth::id(), 'update', $id)) {
            return response()->json(['error' => __('Insufficient permissions')], 403);
        }

        return response()->json(['items' => $this->moves->childOptions($id)]);
    }
}
