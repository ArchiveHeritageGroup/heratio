<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends BaseApiController
{
    /**
     * GET /api/v2/events — Browse webhook delivery events (audit trail).
     */
    public function index(Request $request): JsonResponse
    {
        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $query = DB::table('ahg_webhook_delivery as d')
            ->join('ahg_webhook as w', 'd.webhook_id', '=', 'w.id');

        if ($eventType = $request->get('event_type')) {
            $query->where('d.event_type', $eventType);
        }
        if ($entityType = $request->get('entity_type')) {
            $query->where('d.entity_type', $entityType);
        }
        if ($status = $request->get('status')) {
            $query->where('d.status', $status);
        }

        $total = $query->count();

        $rows = $query
            ->select('d.id', 'd.webhook_id', 'w.name as webhook_name', 'd.event_type',
                'd.entity_type', 'd.entity_id', 'd.status', 'd.attempt_count',
                'd.response_code', 'd.created_at', 'd.delivered_at')
            ->orderByDesc('d.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginated($rows, $total, $page, $limit, '/api/v2/events');
    }

    /**
     * GET /api/v2/events/{id}
     */
    public function show(int $id): JsonResponse
    {
        $event = DB::table('ahg_webhook_delivery as d')
            ->join('ahg_webhook as w', 'd.webhook_id', '=', 'w.id')
            ->where('d.id', $id)
            ->select('d.*', 'w.name as webhook_name', 'w.url as webhook_url')
            ->first();

        if (!$event) {
            return $this->error('Not Found', 'Event not found.', 404);
        }

        $event->payload = json_decode($event->payload, true);

        return $this->success($event);
    }

    /**
     * GET /api/v2/events/correlation/{id} — Events sharing same entity_id.
     */
    public function correlation(int $entityId): JsonResponse
    {
        $events = DB::table('ahg_webhook_delivery')
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($e) {
                $e->payload = json_decode($e->payload, true);
                return $e;
            });

        return $this->success(['events' => $events]);
    }
}
