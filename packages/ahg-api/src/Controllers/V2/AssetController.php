<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssetController extends BaseApiController
{
    protected string $table = 'heritage_asset';
    protected string $valTable = 'heritage_valuation_history';

    /**
     * GET /api/v2/assets
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->tableExists($this->table)) {
            return $this->success(['assets' => [], 'message' => 'Heritage asset module not installed.']);
        }

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $query = DB::table($this->table);
        $total = $query->count();

        $rows = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginated($rows, $total, $page, $limit, '/api/v2/assets');
    }

    /**
     * POST /api/v2/assets
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->tableExists($this->table)) {
            return $this->error('Not Available', 'Heritage asset module not installed.', 501);
        }

        $input = $request->validate([
            'object_id' => 'required|integer',
            'asset_number' => 'nullable|string|max:100',
            'asset_type' => 'nullable|string|max:100',
            'insurance_value' => 'nullable|numeric',
            'insurance_currency' => 'nullable|string|max:3',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
        ]);

        $input['created_at'] = now();
        $input['updated_at'] = now();

        $id = DB::table($this->table)->insertGetId($input);

        return $this->success(['id' => $id, 'message' => 'Heritage asset created.'], 201);
    }

    /**
     * GET /api/v2/assets/{id}
     */
    public function show(int $id): JsonResponse
    {
        if (!$this->tableExists($this->table)) {
            return $this->error('Not Available', 'Heritage asset module not installed.', 501);
        }

        $asset = DB::table($this->table)->where('id', $id)->first();
        if (!$asset) {
            return $this->error('Not Found', 'Asset not found.', 404);
        }

        return $this->success($asset);
    }

    /**
     * PUT /api/v2/assets/{id}
     */
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->tableExists($this->table)) {
            return $this->error('Not Available', 'Heritage asset module not installed.', 501);
        }

        $asset = DB::table($this->table)->where('id', $id)->first();
        if (!$asset) {
            return $this->error('Not Found', 'Asset not found.', 404);
        }

        $input = $request->validate([
            'asset_number' => 'nullable|string|max:100',
            'asset_type' => 'nullable|string|max:100',
            'insurance_value' => 'nullable|numeric',
            'insurance_currency' => 'nullable|string|max:3',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
        ]);

        $input['updated_at'] = now();
        DB::table($this->table)->where('id', $id)->update($input);

        return $this->success(['id' => $id, 'message' => 'Asset updated.']);
    }

    /**
     * GET /api/v2/descriptions/{slug}/asset
     */
    public function forDescription(string $slug): JsonResponse
    {
        $objectId = $this->slugToId($slug);
        if (!$objectId) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        if (!$this->tableExists($this->table)) {
            return $this->success(null);
        }

        $asset = DB::table($this->table)->where('object_id', $objectId)->first();

        return $this->success($asset);
    }

    /**
     * GET /api/v2/valuations
     */
    public function valuations(Request $request): JsonResponse
    {
        if (!$this->tableExists($this->valTable)) {
            return $this->success(['valuations' => [], 'message' => 'Valuation module not installed.']);
        }

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $query = DB::table($this->valTable);
        $total = $query->count();

        $rows = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginated($rows, $total, $page, $limit, '/api/v2/valuations');
    }

    /**
     * POST /api/v2/valuations
     */
    public function storeValuation(Request $request): JsonResponse
    {
        if (!$this->tableExists($this->valTable)) {
            return $this->error('Not Available', 'Valuation module not installed.', 501);
        }

        $input = $request->validate([
            'asset_id' => 'required|integer',
            'valuation_type' => 'nullable|string|max:100',
            'value' => 'required|numeric',
            'currency' => 'nullable|string|max:3',
            'valuation_date' => 'nullable|date',
            'valuator' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
        ]);

        $input['created_at'] = now();
        $input['updated_at'] = now();

        $id = DB::table($this->valTable)->insertGetId($input);

        return $this->success(['id' => $id, 'message' => 'Valuation created.'], 201);
    }

    /**
     * GET /api/v2/assets/{id}/valuations
     */
    public function assetValuations(int $id): JsonResponse
    {
        if (!$this->tableExists($this->valTable)) {
            return $this->success(['valuations' => []]);
        }

        $valuations = DB::table($this->valTable)
            ->where('asset_id', $id)
            ->orderByDesc('valuation_date')
            ->get();

        return $this->success(['valuations' => $valuations]);
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
