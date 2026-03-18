<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class BatchController extends BaseApiController
{
    /**
     * POST /api/v2/batch
     *
     * Process up to 100 CRUD operations in a single request.
     */
    public function process(Request $request): JsonResponse
    {
        $input = $request->validate([
            'operations' => 'required|array|min:1|max:100',
            'operations.*.operation' => 'required|in:create,update,delete',
            'operations.*.entity' => 'required|in:description',
            'operations.*.slug' => 'nullable|string',
            'operations.*.data' => 'nullable|array',
        ]);

        $results = [];
        $success = 0;
        $failed = 0;

        $descController = App::make(DescriptionController::class);

        foreach ($input['operations'] as $index => $op) {
            try {
                $result = $this->executeOp($descController, $op, $request);
                $results[] = array_merge(['index' => $index], $result);
                if ($result['success']) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'operation' => $op['operation'],
                    'entity' => $op['entity'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $this->success([
            'total' => count($input['operations']),
            'success' => $success,
            'failed' => $failed,
            'operations' => $results,
        ]);
    }

    protected function executeOp(DescriptionController $ctrl, array $op, Request $parentRequest): array
    {
        $operation = $op['operation'];
        $slug = $op['slug'] ?? null;
        $data = $op['data'] ?? [];

        // Create a sub-request with the operation data
        $subRequest = Request::create('/', 'POST', $data);
        $subRequest->attributes = $parentRequest->attributes;

        $response = match ($operation) {
            'create' => $ctrl->store($subRequest),
            'update' => $slug ? $ctrl->update($slug, $subRequest) : throw new \Exception('Slug required for update'),
            'delete' => $slug ? $ctrl->destroy($slug) : throw new \Exception('Slug required for delete'),
        };

        $body = json_decode($response->getContent(), true);

        return [
            'success' => $body['success'] ?? ($response->getStatusCode() < 400),
            'operation' => $operation,
            'entity' => $op['entity'],
            'id' => $body['data']['id'] ?? null,
            'slug' => $body['data']['slug'] ?? $slug,
            'error' => $body['message'] ?? null,
        ];
    }
}
