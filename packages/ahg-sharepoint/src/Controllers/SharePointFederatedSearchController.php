<?php

namespace AhgSharePoint\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * AtoM-side federated search wrapper around Graph Search API. Phase 3.
 * Gated to staff (editor/admin) per locked decision §6 of the plan.
 */
class SharePointFederatedSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'not_implemented',
            'phase' => 3,
            'message' => 'Federated search ships in Phase 3.',
        ], 503);
    }
}
