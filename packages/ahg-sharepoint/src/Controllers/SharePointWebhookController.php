<?php

namespace AhgSharePoint\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Graph webhook receiver. Phase 2.
 *
 * **PUBLIC, NO CSRF, NO AUTH.** Auth boundary is the clientState match against
 * sharepoint_subscription.client_state.
 *
 * Phase 1: returns 503 so a misconfigured Graph subscription fails loudly.
 */
class SharePointWebhookController extends Controller
{
    public function receive(Request $request): Response
    {
        // Phase 1 — fail loudly. Phase 2 wires the real handler:
        //   1. If query.validationToken present, echo as text/plain 200 (subscription create handshake).
        //   2. Else iterate notifications[]; for each, match clientState, INSERT sharepoint_event,
        //      dispatch sharepoint:ingest-event job, return 202.

        return response('SharePoint webhook receiver not enabled (Phase 2). See ahg-sharepoint docs.', 503)
            ->header('Content-Type', 'text/plain');
    }
}
