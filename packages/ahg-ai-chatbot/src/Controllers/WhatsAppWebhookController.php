<?php

/**
 * WhatsAppWebhookController - public inbound webhook for the WhatsApp channel.
 *
 *   GET  /webhooks/whatsapp  - Meta verification handshake
 *   POST /webhooks/whatsapp  - inbound message delivery
 *
 * The whole channel is gated by config('ahg-ai-chatbot.whatsapp.enabled');
 * when off, both verbs 404 so the surface does not exist until an operator
 * provisions credentials. The POST verb is exempt from CSRF (it is an external
 * webhook) - it is mounted on the `api` middleware group by the provider and
 * additionally validated by the optional X-Hub-Signature-256 check.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiChatbot\Controllers;

use AhgAiChatbot\Channels\WhatsAppChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WhatsAppWebhookController extends Controller
{
    public function __construct(protected WhatsAppChannel $channel) {}

    /**
     * GET /webhooks/whatsapp - verification handshake.
     */
    public function verify(Request $request): Response
    {
        if (! $this->channel->isEnabled()) {
            abort(404);
        }

        $challenge = $this->channel->verify(
            $request->query('hub_mode', $request->query('hub.mode')),
            $request->query('hub_verify_token', $request->query('hub.verify_token')),
            $request->query('hub_challenge', $request->query('hub.challenge')),
        );

        if ($challenge === null) {
            return response('Forbidden', 403);
        }

        // Meta expects the raw challenge string echoed back as 200 text.
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * POST /webhooks/whatsapp - inbound delivery.
     *
     * Always 200s on a structurally-valid request so Meta does not retry; the
     * actual work (dispatch + reply) happens inline and failures are logged,
     * not surfaced as non-2xx.
     */
    public function receive(Request $request): JsonResponse
    {
        if (! $this->channel->isEnabled()) {
            abort(404);
        }

        // Optional signature validation against the configured app_secret.
        if (! $this->channel->validateSignature(
            $request->header('X-Hub-Signature-256'),
            $request->getContent()
        )) {
            return response()->json(['ok' => false, 'reason' => 'invalid signature'], 403);
        }

        $payload = $request->json()->all();
        if (! is_array($payload)) {
            return response()->json(['ok' => true, 'processed' => false]);
        }

        $result = $this->channel->handleInbound($payload);

        return response()->json(['ok' => true] + $result);
    }
}
