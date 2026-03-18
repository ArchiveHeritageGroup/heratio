<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends BaseApiController
{
    /**
     * GET /api/v2/sync/changes — Get changes since a given timestamp.
     */
    public function changes(Request $request): JsonResponse
    {
        $request->validate([
            'since' => 'required|date',
            'entity_types' => 'nullable|string', // comma-separated
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $since = $request->get('since');
        $limit = min(500, (int) $request->get('limit', 100));
        $types = $request->get('entity_types')
            ? explode(',', $request->get('entity_types'))
            : ['informationobject'];

        $changes = [];

        if (in_array('informationobject', $types)) {
            $ioChanges = DB::table('information_object as io')
                ->join('object', 'io.id', '=', 'object.id')
                ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
                ->join('slug', 'io.id', '=', 'slug.object_id')
                ->where('ioi.culture', $this->culture)
                ->where('io.id', '!=', 1)
                ->where('object.updated_at', '>=', $since)
                ->select('io.id', 'slug.slug', 'ioi.title', 'io.identifier',
                    'object.created_at', 'object.updated_at')
                ->orderBy('object.updated_at')
                ->limit($limit)
                ->get()
                ->map(fn ($r) => [
                    'entity_type' => 'informationobject',
                    'id' => $r->id,
                    'slug' => $r->slug,
                    'title' => $r->title,
                    'identifier' => $r->identifier,
                    'action' => $r->created_at >= $since ? 'created' : 'updated',
                    'updated_at' => $r->updated_at,
                ]);

            $changes = array_merge($changes, $ioChanges->toArray());
        }

        if (in_array('actor', $types)) {
            $actorChanges = DB::table('actor')
                ->join('object', 'actor.id', '=', 'object.id')
                ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
                ->join('slug', 'actor.id', '=', 'slug.object_id')
                ->where('actor_i18n.culture', $this->culture)
                ->where('object.class_name', 'QubitActor')
                ->where('actor.parent_id', '!=', 0)
                ->where('object.updated_at', '>=', $since)
                ->select('actor.id', 'slug.slug', 'actor_i18n.authorized_form_of_name as name',
                    'object.created_at', 'object.updated_at')
                ->orderBy('object.updated_at')
                ->limit($limit)
                ->get()
                ->map(fn ($r) => [
                    'entity_type' => 'actor',
                    'id' => $r->id,
                    'slug' => $r->slug,
                    'name' => $r->name,
                    'action' => $r->created_at >= $since ? 'created' : 'updated',
                    'updated_at' => $r->updated_at,
                ]);

            $changes = array_merge($changes, $actorChanges->toArray());
        }

        // Sort by updated_at
        usort($changes, fn ($a, $b) => $a['updated_at'] <=> $b['updated_at']);

        return $this->success([
            'since' => $since,
            'count' => count($changes),
            'changes' => array_slice($changes, 0, $limit),
        ]);
    }

    /**
     * POST /api/v2/sync/batch — Batch sync operations from mobile.
     */
    public function batch(Request $request): JsonResponse
    {
        $input = $request->validate([
            'operations' => 'required|array|min:1|max:100',
            'operations.*.entity_type' => 'required|string',
            'operations.*.action' => 'required|in:create,update',
            'operations.*.slug' => 'nullable|string',
            'operations.*.data' => 'required|array',
            'operations.*.client_id' => 'nullable|string',
        ]);

        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($input['operations'] as $idx => $op) {
            try {
                // For now, only informationobject sync is supported
                if ($op['entity_type'] !== 'informationobject') {
                    throw new \Exception("Sync not yet supported for entity type: {$op['entity_type']}");
                }

                if ($op['action'] === 'create') {
                    $subRequest = Request::create('/', 'POST', $op['data']);
                    $subRequest->attributes = $request->attributes;
                    $ctrl = app(DescriptionController::class);
                    $response = $ctrl->store($subRequest);
                    $body = json_decode($response->getContent(), true);

                    $results[] = [
                        'index' => $idx,
                        'success' => $body['success'] ?? false,
                        'client_id' => $op['client_id'] ?? null,
                        'server_id' => $body['data']['id'] ?? null,
                        'slug' => $body['data']['slug'] ?? null,
                    ];
                    $success++;
                } elseif ($op['action'] === 'update' && !empty($op['slug'])) {
                    $subRequest = Request::create('/', 'PUT', $op['data']);
                    $subRequest->attributes = $request->attributes;
                    $ctrl = app(DescriptionController::class);
                    $response = $ctrl->update($op['slug'], $subRequest);
                    $body = json_decode($response->getContent(), true);

                    $results[] = [
                        'index' => $idx,
                        'success' => $body['success'] ?? false,
                        'client_id' => $op['client_id'] ?? null,
                        'slug' => $op['slug'],
                    ];
                    $success++;
                } else {
                    throw new \Exception('Invalid action or missing slug for update.');
                }
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'index' => $idx,
                    'success' => false,
                    'client_id' => $op['client_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $this->success([
            'total' => count($input['operations']),
            'success' => $success,
            'failed' => $failed,
            'results' => $results,
        ]);
    }
}
