<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrivacyController extends BaseApiController
{
    protected string $dsarTable = 'ahg_privacy_dsar';
    protected string $breachTable = 'ahg_privacy_breach';

    /**
     * GET /api/v2/privacy/dsars
     */
    public function dsarIndex(Request $request): JsonResponse
    {
        if (!$this->tableExists($this->dsarTable)) {
            return $this->success(['dsars' => [], 'message' => 'Privacy module not installed.']);
        }

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $query = DB::table($this->dsarTable);
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $rows = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginated($rows, $total, $page, $limit, '/api/v2/privacy/dsars');
    }

    /**
     * POST /api/v2/privacy/dsars
     */
    public function dsarStore(Request $request): JsonResponse
    {
        if (!$this->tableExists($this->dsarTable)) {
            return $this->error('Not Available', 'Privacy module not installed.', 501);
        }

        $input = $request->validate([
            'requester_name' => 'required|string|max:200',
            'requester_email' => 'required|email|max:200',
            'request_type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'jurisdiction' => 'nullable|string|max:50',
        ]);

        $input['status'] = 'received';
        $input['created_at'] = now();
        $input['updated_at'] = now();
        $input['created_by'] = $this->apiUserId($request);

        $id = DB::table($this->dsarTable)->insertGetId($input);

        return $this->success(['id' => $id, 'message' => 'DSAR created.'], 201);
    }

    /**
     * GET /api/v2/privacy/dsars/{id}
     */
    public function dsarShow(int $id): JsonResponse
    {
        if (!$this->tableExists($this->dsarTable)) {
            return $this->error('Not Available', 'Privacy module not installed.', 501);
        }

        $dsar = DB::table($this->dsarTable)->where('id', $id)->first();
        if (!$dsar) {
            return $this->error('Not Found', 'DSAR not found.', 404);
        }

        return $this->success($dsar);
    }

    /**
     * PUT /api/v2/privacy/dsars/{id}
     */
    public function dsarUpdate(int $id, Request $request): JsonResponse
    {
        if (!$this->tableExists($this->dsarTable)) {
            return $this->error('Not Available', 'Privacy module not installed.', 501);
        }

        $dsar = DB::table($this->dsarTable)->where('id', $id)->first();
        if (!$dsar) {
            return $this->error('Not Found', 'DSAR not found.', 404);
        }

        $input = $request->validate([
            'status' => 'nullable|string|max:50',
            'response_notes' => 'nullable|string',
            'completed_at' => 'nullable|date',
        ]);

        $input['updated_at'] = now();
        DB::table($this->dsarTable)->where('id', $id)->update($input);

        return $this->success(['id' => $id, 'message' => 'DSAR updated.']);
    }

    /**
     * GET /api/v2/privacy/breaches
     */
    public function breachIndex(Request $request): JsonResponse
    {
        if (!$this->tableExists($this->breachTable)) {
            return $this->success(['breaches' => [], 'message' => 'Privacy module not installed.']);
        }

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $query = DB::table($this->breachTable);
        $total = $query->count();

        $rows = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginated($rows, $total, $page, $limit, '/api/v2/privacy/breaches');
    }

    /**
     * POST /api/v2/privacy/breaches
     */
    public function breachStore(Request $request): JsonResponse
    {
        if (!$this->tableExists($this->breachTable)) {
            return $this->error('Not Available', 'Privacy module not installed.', 501);
        }

        $input = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'severity' => 'nullable|string|max:50',
            'discovered_at' => 'nullable|date',
            'affected_records' => 'nullable|integer',
            'jurisdiction' => 'nullable|string|max:50',
        ]);

        $input['status'] = 'reported';
        $input['created_at'] = now();
        $input['updated_at'] = now();
        $input['reported_by'] = $this->apiUserId($request);

        $id = DB::table($this->breachTable)->insertGetId($input);

        return $this->success(['id' => $id, 'message' => 'Breach report created.'], 201);
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
