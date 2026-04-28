<?php

/**
 * VectorSearchController — public-ish vector-similarity search.
 *
 *   GET /api/search/semantic?q=...&limit=20&collection=anc_records
 *   GET /api/search/semantic/health
 *   GET /api/search/semantic/similar/{ioId}?limit=12
 *
 * Returns JSON. When the AI server or Qdrant is unreachable, returns
 * 503 with a clear "service degraded" payload — never throws.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgSearch\Controllers;

use AhgSearch\Services\VectorSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VectorSearchController extends Controller
{
    public function __construct(protected VectorSearchService $vector)
    {
    }

    public function search(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        if (trim($q) === '') {
            return response()->json(['ok' => false, 'reason' => 'q parameter is required'], 422);
        }
        $limit      = max(1, min(100, (int) $request->query('limit', 20)));
        $collection = $request->query('collection');

        $result = $this->vector->searchSimilar($q, $limit, $collection ?: null);

        if (! $result['ok']) {
            return response()->json($result, 503);
        }
        return response()->json($result);
    }

    public function similar(Request $request, int $ioId): JsonResponse
    {
        if ($ioId <= 0) {
            return response()->json(['ok' => false, 'reason' => 'invalid ioId'], 422);
        }
        $limit      = max(1, min(50, (int) $request->query('limit', 12)));
        $collection = $request->query('collection');

        $result = $this->vector->searchSimilarToPoint($ioId, $limit, $collection ?: null);

        if (! $result['ok']) {
            return response()->json($result, 503);
        }
        return response()->json($result);
    }

    public function health(): JsonResponse
    {
        $h = $this->vector->health();
        return response()->json($h, $h['ok'] ? 200 : 503);
    }
}
