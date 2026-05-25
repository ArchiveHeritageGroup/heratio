<?php

/**
 * EmailBounceController
 *
 * Phase 2 of #674 (Email + notifications). Receives bounce + complaint
 * webhooks from the upstream mail provider and reflects them into the
 * user table so subsequent send paths can suppress to a bouncing address.
 *
 * Endpoint: POST /webhooks/email/bounce (public, HMAC-validated)
 *
 * Auth: shared secret in ahg_settings.email_bounce_webhook_secret. Sender
 * must supply X-AHG-Signature: sha256=<hmac-hex> where hmac is computed
 * over the raw request body. The secret + chosen provider live in the
 * email settings group; rotate by re-running the migration or editing
 * the row.
 *
 * Supported payload shapes (auto-detected, also forceable via
 * ahg_settings.email_bounce_webhook_provider):
 *   - postmark  : { RecordType, Type, Email, Description, MessageID, BouncedAt }
 *   - ses       : SNS-wrapped JSON with {"notificationType":"Bounce",
 *                 "bounce":{"bounceType":"Permanent","bouncedRecipients":[...]}}
 *   - sparkpost : { msys: { message_event / bounce_event: {rcpt_to, bounce_class} } }
 *   - mailgun   : { event-data: { event:"failed", severity:"permanent",
 *                                 recipient, reason }}
 *   - generic   : { email, type:"hard"|"soft"|"complaint", reason, message_id,
 *                   occurred_at }
 *
 * On hard bounce (or complaint) the recipient's user.email_bounced_at is
 * set to the bounce timestamp. Soft bounces are logged only; a recipient
 * accumulating >=5 soft bounces within a rolling 30-day window is auto-
 * promoted to hard.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EmailBounceController extends Controller
{
    /** Threshold for soft -> hard promotion. */
    private const SOFT_PROMOTION_THRESHOLD = 5;

    /** Rolling window for soft-bounce promotion (days). */
    private const SOFT_PROMOTION_WINDOW_DAYS = 30;

    public function receive(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ahg_email_bounce')) {
            return response()->json(['error' => 'bounce table not installed'], 503);
        }

        $rawBody = (string) $request->getContent();

        if (! $this->verifySignature($request, $rawBody)) {
            Log::warning('email_bounce.signature_invalid', [
                'remote' => $request->ip(),
                'len' => strlen($rawBody),
            ]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'malformed json'], 400);
        }

        $provider = $this->resolveProvider($payload);
        $events = $this->normalise($payload, $provider);

        if ($events === []) {
            // Acknowledge but log - lots of providers send heartbeat /
            // unrelated event types we should just no-op on.
            return response()->json(['ok' => true, 'processed' => 0]);
        }

        $processed = 0;
        foreach ($events as $event) {
            try {
                $this->recordBounce($event, $payload, $provider);
                $processed++;
            } catch (\Throwable $e) {
                Log::error('email_bounce.record_failed', [
                    'error' => $e->getMessage(),
                    'event' => $event,
                ]);
            }
        }

        return response()->json(['ok' => true, 'processed' => $processed]);
    }

    /**
     * Validate the HMAC signature on the raw body.
     *
     * Accepts header X-AHG-Signature (preferred) or X-Hub-Signature-256
     * (GitHub-style) or X-Postmark-Signature (provider-specific). Each
     * header carries either a plain hex digest or `sha256=<hex>`.
     */
    private function verifySignature(Request $request, string $rawBody): bool
    {
        $secret = $this->setting('email_bounce_webhook_secret');
        if ($secret === null || $secret === '') {
            // Failsafe: refuse if not configured.
            return false;
        }

        $signature = (string) (
            $request->header('X-AHG-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Postmark-Signature')
            ?? ''
        );
        if ($signature === '') {
            return false;
        }
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Detect which provider sent this payload. Falls back to the
     * operator-configured ahg_settings.email_bounce_webhook_provider, then
     * 'generic'.
     */
    private function resolveProvider(array $payload): string
    {
        if (isset($payload['RecordType']) || isset($payload['Type']) && isset($payload['Email'])) {
            return 'postmark';
        }
        if (isset($payload['notificationType']) || isset($payload['Message'])) {
            return 'ses';
        }
        if (isset($payload['msys'])) {
            return 'sparkpost';
        }
        if (isset($payload['event-data']) || isset($payload['signature'])) {
            return 'mailgun';
        }
        if (isset($payload['email']) && isset($payload['type'])) {
            return 'generic';
        }

        return (string) ($this->setting('email_bounce_webhook_provider') ?: 'generic');
    }

    /**
     * Map a provider payload to a flat list of normalised event arrays:
     *   ['email', 'bounce_type', 'bounce_subtype', 'reason',
     *    'message_id', 'occurred_at']
     */
    private function normalise(array $payload, string $provider): array
    {
        return match ($provider) {
            'postmark' => $this->normalisePostmark($payload),
            'ses' => $this->normaliseSes($payload),
            'sparkpost' => $this->normaliseSparkpost($payload),
            'mailgun' => $this->normaliseMailgun($payload),
            default => $this->normaliseGeneric($payload),
        };
    }

    private function normalisePostmark(array $p): array
    {
        if (empty($p['Email'])) {
            return [];
        }
        $type = strtolower((string) ($p['Type'] ?? 'unknown'));
        $bounceType = match (true) {
            in_array($type, ['hardbounce', 'badrecipient', 'unknown', 'dnserror', 'manuallydeactivated'], true) => 'hard',
            in_array($type, ['transient', 'softbounce', 'mailboxfull', 'smtpapierror'], true) => 'soft',
            in_array($type, ['spamcomplaint', 'spamnotification'], true) => 'complaint',
            default => 'unknown',
        };

        return [[
            'email' => (string) $p['Email'],
            'bounce_type' => $bounceType,
            'bounce_subtype' => $type,
            'reason' => (string) ($p['Description'] ?? $p['Details'] ?? ''),
            'message_id' => (string) ($p['MessageID'] ?? ''),
            'occurred_at' => $this->parseTs($p['BouncedAt'] ?? null),
        ]];
    }

    private function normaliseSes(array $p): array
    {
        // SNS wraps the SES message in a JSON string under `Message`.
        if (isset($p['Message']) && is_string($p['Message'])) {
            $decoded = json_decode($p['Message'], true);
            if (is_array($decoded)) {
                $p = $decoded;
            }
        }

        $events = [];
        if (($p['notificationType'] ?? '') === 'Bounce' && isset($p['bounce'])) {
            $perm = ($p['bounce']['bounceType'] ?? '') === 'Permanent';
            foreach (($p['bounce']['bouncedRecipients'] ?? []) as $rcpt) {
                $events[] = [
                    'email' => (string) ($rcpt['emailAddress'] ?? ''),
                    'bounce_type' => $perm ? 'hard' : 'soft',
                    'bounce_subtype' => (string) ($p['bounce']['bounceSubType'] ?? ''),
                    'reason' => (string) ($rcpt['diagnosticCode'] ?? ''),
                    'message_id' => (string) ($p['mail']['messageId'] ?? ''),
                    'occurred_at' => $this->parseTs($p['bounce']['timestamp'] ?? null),
                ];
            }
        }
        if (($p['notificationType'] ?? '') === 'Complaint' && isset($p['complaint'])) {
            foreach (($p['complaint']['complainedRecipients'] ?? []) as $rcpt) {
                $events[] = [
                    'email' => (string) ($rcpt['emailAddress'] ?? ''),
                    'bounce_type' => 'complaint',
                    'bounce_subtype' => (string) ($p['complaint']['complaintFeedbackType'] ?? ''),
                    'reason' => 'spam complaint',
                    'message_id' => (string) ($p['mail']['messageId'] ?? ''),
                    'occurred_at' => $this->parseTs($p['complaint']['timestamp'] ?? null),
                ];
            }
        }

        return array_values(array_filter($events, fn ($e) => $e['email'] !== ''));
    }

    private function normaliseSparkpost(array $p): array
    {
        // SparkPost wraps multiple events in arrays under msys.
        $candidates = [];
        foreach (($p['msys'] ?? []) as $event) {
            $candidates[] = $event;
        }
        if (is_array($p) && isset($p[0])) {
            foreach ($p as $row) {
                if (isset($row['msys'])) {
                    foreach ($row['msys'] as $event) {
                        $candidates[] = $event;
                    }
                }
            }
        }
        $events = [];
        foreach ($candidates as $event) {
            $rcpt = (string) ($event['rcpt_to'] ?? '');
            if ($rcpt === '') {
                continue;
            }
            $class = (string) ($event['bounce_class'] ?? '');
            $bounceType = match (true) {
                in_array($class, ['10', '20', '21', '30', '90'], true) => 'hard',
                in_array($class, ['40', '50', '60', '70'], true) => 'soft',
                $class !== '' && (int) $class >= 1 => 'soft',
                default => 'unknown',
            };
            if (($event['type'] ?? '') === 'spam_complaint' || ($event['fbtype'] ?? '') !== '') {
                $bounceType = 'complaint';
            }
            $events[] = [
                'email' => $rcpt,
                'bounce_type' => $bounceType,
                'bounce_subtype' => $class,
                'reason' => (string) ($event['raw_reason'] ?? $event['reason'] ?? ''),
                'message_id' => (string) ($event['message_id'] ?? ''),
                'occurred_at' => $this->parseTs($event['timestamp'] ?? null),
            ];
        }

        return $events;
    }

    private function normaliseMailgun(array $p): array
    {
        $event = $p['event-data'] ?? $p;
        if (! is_array($event) || empty($event['recipient'])) {
            return [];
        }
        $eventType = (string) ($event['event'] ?? '');
        $severity = (string) ($event['severity'] ?? '');
        $bounceType = match (true) {
            $eventType === 'complained' => 'complaint',
            $eventType === 'failed' && $severity === 'permanent' => 'hard',
            $eventType === 'failed' && $severity === 'temporary' => 'soft',
            default => 'unknown',
        };

        return [[
            'email' => (string) $event['recipient'],
            'bounce_type' => $bounceType,
            'bounce_subtype' => $eventType.($severity !== '' ? ':'.$severity : ''),
            'reason' => (string) (($event['delivery-status']['message'] ?? $event['reason']) ?? ''),
            'message_id' => (string) ($event['message']['headers']['message-id'] ?? ''),
            'occurred_at' => $this->parseTs($event['timestamp'] ?? null),
        ]];
    }

    private function normaliseGeneric(array $p): array
    {
        if (empty($p['email'])) {
            return [];
        }
        $type = strtolower((string) ($p['type'] ?? 'unknown'));
        $bounceType = match ($type) {
            'hard', 'permanent', 'bounce' => 'hard',
            'soft', 'transient', 'temporary', 'deferred' => 'soft',
            'complaint', 'spam', 'abuse' => 'complaint',
            default => 'unknown',
        };

        return [[
            'email' => (string) $p['email'],
            'bounce_type' => $bounceType,
            'bounce_subtype' => (string) ($p['subtype'] ?? ''),
            'reason' => (string) ($p['reason'] ?? ''),
            'message_id' => (string) ($p['message_id'] ?? ''),
            'occurred_at' => $this->parseTs($p['occurred_at'] ?? null),
        ]];
    }

    private function parseTs($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            if (is_int($raw) || ctype_digit((string) $raw)) {
                return date('Y-m-d H:i:s', (int) $raw);
            }

            return date('Y-m-d H:i:s', strtotime((string) $raw) ?: time());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Persist the event + apply user-level effects.
     */
    private function recordBounce(array $event, array $rawPayload, string $provider): void
    {
        $email = strtolower(trim((string) $event['email']));
        if ($email === '') {
            return;
        }

        $occurredAt = $event['occurred_at'] ?? date('Y-m-d H:i:s');

        $id = DB::table('ahg_email_bounce')->insertGetId([
            'email' => $email,
            'bounce_type' => $event['bounce_type'] ?? 'unknown',
            'bounce_subtype' => $event['bounce_subtype'] ?? null,
            'reason' => $event['reason'] ?? null,
            'message_id' => $event['message_id'] ?? null,
            'provider' => $provider,
            'occurred_at' => $occurredAt,
            'payload_json' => json_encode($rawPayload, JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);

        $action = $this->applyBounce($email, $event['bounce_type'] ?? 'unknown', $occurredAt);

        if ($action !== 'none') {
            DB::table('ahg_email_bounce')
                ->where('id', $id)
                ->update(['processed_at' => now()]);
        }
    }

    /**
     * Hard bounce + complaint = immediate suppression. Soft bounce checks
     * the rolling window and promotes to hard once threshold is crossed.
     */
    private function applyBounce(string $email, string $bounceType, ?string $occurredAt): string
    {
        if (! Schema::hasTable('user') || ! Schema::hasColumn('user', 'email_bounced_at')) {
            return 'none';
        }

        if (in_array($bounceType, ['hard', 'complaint'], true)) {
            DB::table('user')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->update(['email_bounced_at' => $occurredAt ?: now()]);

            return 'suppressed';
        }

        if ($bounceType !== 'soft') {
            return 'none';
        }

        // Soft promotion: count distinct soft events in the rolling window.
        $cutoff = date('Y-m-d H:i:s', strtotime('-'.self::SOFT_PROMOTION_WINDOW_DAYS.' days'));
        $softCount = DB::table('ahg_email_bounce')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('bounce_type', 'soft')
            ->where('occurred_at', '>=', $cutoff)
            ->count();

        if ($softCount >= self::SOFT_PROMOTION_THRESHOLD) {
            DB::table('user')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->update(['email_bounced_at' => $occurredAt ?: now()]);

            return 'promoted';
        }

        return 'soft_logged';
    }

    private function setting(string $key): ?string
    {
        if (! Schema::hasTable('ahg_settings')) {
            return null;
        }

        return DB::table('ahg_settings')
            ->where('setting_key', $key)
            ->value('setting_value');
    }
}
