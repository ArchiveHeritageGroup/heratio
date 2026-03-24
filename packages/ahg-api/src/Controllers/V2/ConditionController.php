<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConditionController extends BaseApiController
{
    protected string $table = 'ahg_condition_assessment';

    /**
     * GET /api/v2/conditions
     */
    public function index(Request $request): JsonResponse
    {
        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        if (!$this->tableExists()) {
            return $this->success(['conditions' => [], 'message' => 'Condition assessment module not installed.']);
        }

        $query = DB::table($this->table);
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $rows = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginated($rows, $total, $page, $limit, '/api/v2/conditions');
    }

    /**
     * POST /api/v2/conditions
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->tableExists()) {
            return $this->error('Not Available', 'Condition assessment module not installed.', 501);
        }

        $input = $request->validate([
            'object_id' => 'required|integer',
            'condition_type' => 'nullable|string|max:100',
            'condition_rating' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'assessor' => 'nullable|string|max:200',
            'assessment_date' => 'nullable|date',
            'recommendations' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);

        $input['created_at'] = now();
        $input['updated_at'] = now();
        $input['created_by'] = $this->apiUserId($request);

        $id = DB::table($this->table)->insertGetId($input);

        return $this->success(['id' => $id, 'message' => 'Condition assessment created.'], 201);
    }

    /**
     * GET /api/v2/conditions/{id}
     */
    public function show(int $id): JsonResponse
    {
        if (!$this->tableExists()) {
            return $this->error('Not Available', 'Condition assessment module not installed.', 501);
        }

        $condition = DB::table($this->table)->where('id', $id)->first();
        if (!$condition) {
            return $this->error('Not Found', 'Condition not found.', 404);
        }

        return $this->success($condition);
    }

    /**
     * PUT /api/v2/conditions/{id}
     */
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->tableExists()) {
            return $this->error('Not Available', 'Condition assessment module not installed.', 501);
        }

        $condition = DB::table($this->table)->where('id', $id)->first();
        if (!$condition) {
            return $this->error('Not Found', 'Condition not found.', 404);
        }

        $input = $request->validate([
            'condition_type' => 'nullable|string|max:100',
            'condition_rating' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'assessor' => 'nullable|string|max:200',
            'assessment_date' => 'nullable|date',
            'recommendations' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);

        $input['updated_at'] = now();
        DB::table($this->table)->where('id', $id)->update($input);

        return $this->success(['id' => $id, 'message' => 'Condition updated.']);
    }

    /**
     * DELETE /api/v2/conditions/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->tableExists()) {
            return $this->error('Not Available', 'Condition assessment module not installed.', 501);
        }

        $deleted = DB::table($this->table)->where('id', $id)->delete();
        if (!$deleted) {
            return $this->error('Not Found', 'Condition not found.', 404);
        }

        return $this->success(['message' => 'Condition deleted.']);
    }

    /**
     * GET /api/v2/descriptions/{slug}/conditions
     */
    public function forDescription(string $slug): JsonResponse
    {
        $objectId = $this->slugToId($slug);
        if (!$objectId) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        if (!$this->tableExists()) {
            return $this->success(['conditions' => []]);
        }

        $conditions = DB::table($this->table)
            ->where('object_id', $objectId)
            ->orderByDesc('created_at')
            ->get();

        return $this->success(['conditions' => $conditions]);
    }

    /**
     * GET /api/v2/conditions/{id}/photos
     */
    public function photos(int $id): JsonResponse
    {
        if (!$this->tableExists('ahg_condition_photo')) {
            return $this->success(['photos' => []]);
        }

        $photos = DB::table('ahg_condition_photo')
            ->where('condition_id', $id)
            ->orderBy('sort_order')
            ->get();

        return $this->success(['photos' => $photos]);
    }

    /**
     * POST /api/v2/conditions/{id}/photos
     */
    public function uploadPhoto(int $id, Request $request): JsonResponse
    {
        if (!$this->tableExists()) {
            return $this->error('Not Available', 'Condition assessment module not installed.', 501);
        }

        $condition = DB::table($this->table)->where('id', $id)->first();
        if (!$condition) {
            return $this->error('Not Found', 'Condition not found.', 404);
        }

        $request->validate(['photo' => 'required|image|max:20480']); // 20MB

        $file = $request->file('photo');
        $path = sprintf('conditions/%d/%s', $id, now()->format('Y'));
        $filename = $file->hashName();
        $file->storeAs($path, $filename, 'public');

        $photoId = null;
        if ($this->tableExists('ahg_condition_photo')) {
            $photoId = DB::table('ahg_condition_photo')->insertGetId([
                'condition_id' => $id,
                'filename' => $file->getClientOriginalName(),
                'path' => "{$path}/{$filename}",
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sort_order' => 0,
                'created_at' => now(),
            ]);
        }

        return $this->success([
            'photo_id' => $photoId,
            'path' => "{$path}/{$filename}",
            'filename' => $file->getClientOriginalName(),
        ], 201);
    }

    /**
     * DELETE /api/v2/conditions/{id}/photos/{photoId}
     */
    public function deletePhoto(int $id, int $photoId): JsonResponse
    {
        if (!$this->tableExists()) {
            return $this->error('Not Available', 'Condition assessment module not installed.', 501);
        }

        $condition = DB::table($this->table)->where('id', $id)->first();
        if (!$condition) {
            return $this->error('Not Found', 'Condition not found.', 404);
        }

        if (!$this->tableExists('ahg_condition_photo')) {
            return $this->error('Not Available', 'Condition photo module not installed.', 501);
        }

        $photo = DB::table('ahg_condition_photo')
            ->where('id', $photoId)
            ->where('condition_id', $id)
            ->first();

        if (!$photo) {
            return $this->error('Not Found', 'Photo not found.', 404);
        }

        // Delete physical file
        if (!empty($photo->path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
        }

        DB::table('ahg_condition_photo')->where('id', $photoId)->delete();

        return $this->success(['message' => 'Photo deleted.']);
    }

    protected function tableExists(?string $table = null): bool
    {
        $table = $table ?? $this->table;
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
