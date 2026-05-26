<?php

/**
 * HttpSmsGateway — sends OTP SMS via a generic HTTP POST endpoint.
 *
 * Configuration (read from ahg_settings, group 'security'):
 *   sms_http_endpoint   — full URL of the provider HTTP API.
 *   sms_http_token      — optional Bearer token; sent as Authorization
 *                         header when present.
 *   sms_http_to_field   — form field name for the destination phone
 *                         (default: "to").
 *   sms_http_body_field — form field name for the message body
 *                         (default: "body" / "text" — leave as "body"
 *                         unless your provider differs).
 *   sms_http_method     — POST (default) or GET.
 *
 * Returns false on any non-2xx response or transport error.
 *
 * Suitable for: in-house SMS gateways, Clickatell HTTP API, BulkSMS,
 * any provider with a single-endpoint POST. Per-provider drivers
 * (Twilio, Vonage) are tracked as a Phase 2 follow-up.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgSecurityClearance\Services;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\Http;

class HttpSmsGateway implements SmsGatewayInterface
{
    public function send(string $to, string $body): bool
    {
        $endpoint = (string) (AhgSettingsService::get('sms_http_endpoint') ?? '');
        if ($endpoint === '') {
            \Log::warning('sms.http.no_endpoint', [
                'message' => 'sms_http_endpoint setting missing; SMS not sent.',
                'to' => $to,
            ]);

            return false;
        }

        $token = (string) (AhgSettingsService::get('sms_http_token') ?? '');
        $toField = (string) (AhgSettingsService::get('sms_http_to_field') ?? 'to');
        $bodyField = (string) (AhgSettingsService::get('sms_http_body_field') ?? 'body');
        $method = strtoupper((string) (AhgSettingsService::get('sms_http_method') ?? 'POST'));

        $payload = [
            $toField => $to,
            $bodyField => $body,
        ];

        try {
            $request = Http::timeout(10);
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = match ($method) {
                'GET' => $request->get($endpoint, $payload),
                default => $request->asForm()->post($endpoint, $payload),
            };

            if (! $response->successful()) {
                \Log::warning('sms.http.failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                return false;
            }

            \Log::info('sms.http.sent', ['to' => $to, 'status' => $response->status()]);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('sms.http.exception', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
