<?php

/**
 * WhatsAppChannel - WhatsApp Business (Meta Cloud API) channel adapter.
 *
 * Responsibilities:
 *   - verify()        : answer Meta's GET webhook-verification handshake
 *   - validateSignature() : (optional) check X-Hub-Signature-256 against app_secret
 *   - parseInbound()  : pull {from, text, message_id} out of a webhook payload
 *   - handleInbound() : route an inbound text through ChatbotService::dispatch()
 *                       and send the reply back via the Cloud API
 *   - send()          : POST a text message to a recipient
 *
 * Every secret is read from config('ahg-ai-chatbot.whatsapp.*'), which itself
 * reads env - nothing is hardcoded. The whole channel is inert unless
 * config('ahg-ai-chatbot.whatsapp.enabled') is true; the controller enforces
 * that gate before any method here runs.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiChatbot\Channels;

use AhgAiChatbot\Services\ChatbotService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function __construct(
        protected ChatbotService $chatbot,
    ) {}

    /**
     * Is the channel switched on AND minimally configured?
     */
    public function isEnabled(): bool
    {
        return (bool) config('ahg-ai-chatbot.whatsapp.enabled', false);
    }

    /**
     * Meta GET verification: when hub.mode=subscribe and hub.verify_token
     * matches our configured token, echo hub.challenge back verbatim.
     *
     * @return string|null  the challenge to echo, or null to reject (403)
     */
    public function verify(?string $mode, ?string $token, ?string $challenge): ?string
    {
        $expected = (string) config('ahg-ai-chatbot.whatsapp.verify_token', '');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
            return $challenge;
        }

        return null;
    }

    /**
     * Optional payload-integrity check. Returns true when no app_secret is
     * configured (validation disabled) or when the HMAC matches.
     */
    public function validateSignature(?string $signatureHeader, string $rawBody): bool
    {
        $secret = (string) config('ahg-ai-chatbot.whatsapp.app_secret', '');
        if ($secret === '') {
            return true; // validation not configured
        }
        if (! is_string($signatureHeader) || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Extract the first inbound text message from a webhook payload.
     *
     * @return array{from: string, text: string, message_id: ?string}|null
     */
    public function parseInbound(array $payload): ?array
    {
        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                foreach (($value['messages'] ?? []) as $message) {
                    if (($message['type'] ?? null) !== 'text') {
                        continue;
                    }
                    $from = $message['from'] ?? null;
                    $text = $message['text']['body'] ?? null;
                    if ($from && is_string($text) && trim($text) !== '') {
                        return [
                            'from'       => (string) $from,
                            'text'       => (string) $text,
                            'message_id' => $message['id'] ?? null,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Process a webhook payload end-to-end: parse -> dispatch -> reply.
     *
     * @return array{processed: bool, reply_sent: bool}
     */
    public function handleInbound(array $payload): array
    {
        $inbound = $this->parseInbound($payload);
        if ($inbound === null) {
            // Status callbacks / non-text messages are acknowledged but ignored.
            return ['processed' => false, 'reply_sent' => false];
        }

        // Per-sender session, stable across messages.
        $sessionId = hash('sha256', 'whatsapp:' . $inbound['from']);

        try {
            $result = $this->chatbot->dispatch($sessionId, $inbound['text'], null);
            $reply = $result['reply'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('[whatsapp] dispatch failed: ' . $e->getMessage());
            $reply = null;
        }

        if (! is_string($reply) || trim($reply) === '') {
            $reply = __('Sorry, I could not answer that right now. Please try again shortly.');
        }

        $sent = $this->send($inbound['from'], $reply);

        return ['processed' => true, 'reply_sent' => $sent];
    }

    /**
     * Send a plain-text WhatsApp message via the Cloud API.
     */
    public function send(string $to, string $text): bool
    {
        $token = (string) config('ahg-ai-chatbot.whatsapp.access_token', '');
        $phoneId = (string) config('ahg-ai-chatbot.whatsapp.phone_number_id', '');
        $base = rtrim((string) config('ahg-ai-chatbot.whatsapp.api_base', 'https://graph.facebook.com'), '/');
        $version = (string) config('ahg-ai-chatbot.whatsapp.api_version', 'v21.0');

        if ($token === '' || $phoneId === '') {
            Log::warning('[whatsapp] send skipped - access_token / phone_number_id not configured');

            return false;
        }

        $url = "{$base}/{$version}/{$phoneId}/messages";

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'text',
                    'text'              => ['body' => mb_substr($text, 0, 4096)],
                ]);

            if (! $response->successful()) {
                Log::warning('[whatsapp] send failed: HTTP ' . $response->status() . ' ' . $response->body());

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('[whatsapp] send exception: ' . $e->getMessage());

            return false;
        }
    }
}
