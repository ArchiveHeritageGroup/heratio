<?php

namespace AhgSharePoint\Controllers;

use AhgSharePoint\Services\SharePointWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Graph webhook receiver. Phase 2.A.
 *
 * **PUBLIC, NO CSRF.** Auth boundary is the clientState match against
 * sharepoint_subscription.client_state.
 *
 * Two flows:
 *   1. Subscription validation handshake — Graph GETs ?validationToken=...
 *      We MUST echo it as text/plain 200 within 10s.
 *   2. Notification delivery — Graph POSTs JSON {value:[{...},{...}]}
 *      Validate clientState, INSERT sharepoint_event, enqueue
 *      IngestSharePointEventJob, return 202.
 *
 * Mirror of atom-ahg-plugins/ahgSharePointPlugin executeWebhook action.
 */
class SharePointWebhookController extends Controller
{
    public function __construct(private SharePointWebhookHandler $handler)
    {
    }

    public function receive(Request $request): Response|JsonResponse
    {
        // Validation handshake
        $validationToken = $request->query('validationToken');
        if ($validationToken !== null && $validationToken !== '') {
            return response($validationToken, 200, ['Content-Type' => 'text/plain']);
        }

        if (!$request->isMethod('POST')) {
            return response('Method not allowed', 405, ['Content-Type' => 'text/plain']);
        }

        $payload = $request->json()->all();
        if (!is_array($payload)) {
            return response('Invalid JSON', 400, ['Content-Type' => 'text/plain']);
        }

        $result = $this->handler->handleNotifications($payload);

        return response()->json([
            'accepted' => $result['accepted'],
            'dropped' => $result['dropped'],
        ], 202);
    }
}
