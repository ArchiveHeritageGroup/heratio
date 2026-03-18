<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends BaseApiController
{
    /**
     * GET /api/v2/audit — Browse API request logs.
     */
    public function index(Request $request): JsonResponse
    {
        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $query = DB::table('ahg_api_log');

        if ($method = $request->get('method')) {
            $query->where('method', strtoupper($method));
        }
        if ($endpoint = $request->get('endpoint')) {
            $query->where('endpoint', 'like', "%{$endpoint}%");
        }
        if ($statusCode = $request->get('status_code')) {
            $query->where('status_code', $statusCode);
        }
        if ($apiKeyId = $request->get('api_key_id')) {
            $query->where('api_key_id', $apiKeyId);
        }
        if ($from = $request->get('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->where('created_at', '<=', $to);
        }

        $total = $query->count();

        $rows = $query
            ->select('id', 'api_key_id', 'user_id', 'method', 'endpoint', 'status_code',
                'response_size', 'ip_address', 'duration_ms', 'created_at')
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginated($rows, $total, $page, $limit, '/api/v2/audit');
    }

    /**
     * GET /api/v2/audit/{id}
     */
    public function show(int $id): JsonResponse
    {
        $entry = DB::table('ahg_api_log')->where('id', $id)->first();

        if (!$entry) {
            return $this->error('Not Found', 'Audit entry not found.', 404);
        }

        return $this->success($entry);
    }
}
