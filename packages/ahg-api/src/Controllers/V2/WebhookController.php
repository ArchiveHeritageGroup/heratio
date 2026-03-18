<?php

namespace AhgApi\Controllers\V2;

use AhgApi\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookController extends BaseApiController
{
    public function __construct(protected WebhookService $webhookService)
    {
        parent::__construct();
    }

    /**
     * GET /api/v2/webhooks
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $this->apiUserId($request);
        $webhooks = DB::table('ahg_webhook')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($w) {
                $w->events = json_decode($w->events, true);
                $w->entity_types = json_decode($w->entity_types, true);
                unset($w->secret);
                return $w;
            });

        return $this->success(['webhooks' => $webhooks]);
    }

    /**
     * POST /api/v2/webhooks
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $this->apiUserId($request);
        $validEvents = $this->webhookService->getValidEvents();
        $validTypes = $this->webhookService->getValidEntityTypes();

        $input = $request->validate([
            'name' => 'required|string|max:100',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'in:' . implode(',', $validEvents),
            'entity_types' => 'required|array|min:1',
            'entity_types.*' => 'in:' . implode(',', $validTypes),
        ]);

        $secret = Str::random(64);

        $id = DB::table('ahg_webhook')->insertGetId([
            'user_id' => $userId,
            'name' => $input['name'],
            'url' => $input['url'],
            'secret' => $secret,
            'events' => json_encode($input['events']),
            'entity_types' => json_encode($input['entity_types']),
            'is_active' => 1,
            'failure_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->success([
            'id' => $id,
            'name' => $input['name'],
            'url' => $input['url'],
            'secret' => $secret, // Only shown on creation
            'events' => $input['events'],
            'entity_types' => $input['entity_types'],
            'is_active' => true,
        ], 201);
    }

    /**
     * GET /api/v2/webhooks/{id}
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $webhook = DB::table('ahg_webhook')
            ->where('id', $id)
            ->where('user_id', $this->apiUserId($request))
            ->first();

        if (!$webhook) {
            return $this->error('Not Found', 'Webhook not found.', 404);
        }

        $webhook->events = json_decode($webhook->events, true);
        $webhook->entity_types = json_decode($webhook->entity_types, true);
        unset($webhook->secret);

        return $this->success($webhook);
    }

    /**
     * PUT /api/v2/webhooks/{id}
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $webhook = DB::table('ahg_webhook')
            ->where('id', $id)
            ->where('user_id', $this->apiUserId($request))
            ->first();

        if (!$webhook) {
            return $this->error('Not Found', 'Webhook not found.', 404);
        }

        $validEvents = $this->webhookService->getValidEvents();
        $validTypes = $this->webhookService->getValidEntityTypes();

        $input = $request->validate([
            'name' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:500',
            'events' => 'nullable|array|min:1',
            'events.*' => 'in:' . implode(',', $validEvents),
            'entity_types' => 'nullable|array|min:1',
            'entity_types.*' => 'in:' . implode(',', $validTypes),
            'is_active' => 'nullable|boolean',
        ]);

        $update = ['updated_at' => now()];
        if (isset($input['name'])) $update['name'] = $input['name'];
        if (isset($input['url'])) $update['url'] = $input['url'];
        if (isset($input['events'])) $update['events'] = json_encode($input['events']);
        if (isset($input['entity_types'])) $update['entity_types'] = json_encode($input['entity_types']);
        if (isset($input['is_active'])) $update['is_active'] = $input['is_active'];

        DB::table('ahg_webhook')->where('id', $id)->update($update);

        return $this->success(['id' => $id, 'message' => 'Webhook updated.']);
    }

    /**
     * DELETE /api/v2/webhooks/{id}
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $deleted = DB::table('ahg_webhook')
            ->where('id', $id)
            ->where('user_id', $this->apiUserId($request))
            ->delete();

        if (!$deleted) {
            return $this->error('Not Found', 'Webhook not found.', 404);
        }

        return $this->success(['message' => 'Webhook deleted.']);
    }

    /**
     * GET /api/v2/webhooks/{id}/deliveries
     */
    public function deliveries(int $id, Request $request): JsonResponse
    {
        // Verify ownership
        $webhook = DB::table('ahg_webhook')
            ->where('id', $id)
            ->where('user_id', $this->apiUserId($request))
            ->first();

        if (!$webhook) {
            return $this->error('Not Found', 'Webhook not found.', 404);
        }

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $query = DB::table('ahg_webhook_delivery')->where('webhook_id', $id);
        $total = $query->count();

        $deliveries = $query
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginated($deliveries, $total, $page, $limit, "/api/v2/webhooks/{$id}/deliveries");
    }

    /**
     * POST /api/v2/webhooks/{id}/regenerate-secret
     */
    public function regenerateSecret(int $id, Request $request): JsonResponse
    {
        $webhook = DB::table('ahg_webhook')
            ->where('id', $id)
            ->where('user_id', $this->apiUserId($request))
            ->first();

        if (!$webhook) {
            return $this->error('Not Found', 'Webhook not found.', 404);
        }

        $newSecret = $this->webhookService->regenerateSecret($id);

        return $this->success(['id' => $id, 'secret' => $newSecret]);
    }
}
