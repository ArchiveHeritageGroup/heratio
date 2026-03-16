<?php

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

        if (!$id || !$target) {
            return response()->json(['error' => 'Missing required parameters: id, target'], 400);
        }

        $culture = app()->getLocale();
        $service = new TreeviewService($culture);

        $success = $service->moveAfter($id, $target);

        if ($success) {
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Move failed. Nodes must be siblings.'], 422);
    }
}
