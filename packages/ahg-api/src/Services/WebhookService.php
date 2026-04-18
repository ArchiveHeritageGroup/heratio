<?php

/**
 * WebhookService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgApi\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookService
{
    protected array $validEvents = [
        'item.created', 'item.updated', 'item.deleted',
        'item.published', 'item.unpublished',
    ];

    protected array $validEntityTypes = [
        'informationobject', 'actor', 'repository', 'accession', 'term',
    ];

    /**
     * Trigger webhooks for an event.
     */
    public function trigger(string $event, string $entityType, int $entityId, array $data = []): void
    {
        $webhooks = DB::table('ahg_webhook')
            ->where('is_active', 1)
            ->where('failure_count', '<', 5)
            ->get()
            ->filter(function ($webhook) use ($event, $entityType) {
                $events = json_decode($webhook->events, true) ?: [];
                $types = json_decode($webhook->entity_types, true) ?: [];
                return in_array($event, $events) && in_array($entityType, $types);
            });

        foreach ($webhooks as $webhook) {
            $deliveryId = DB::table('ahg_webhook_delivery')->insertGetId([
                'webhook_id' => $webhook->id,
                'event_type' => $event,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload' => json_encode([
                    'event' => $event,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'timestamp' => now()->toIso8601String(),
                    'data' => $data,
                ]),
                'status' => 'pending',
                'attempt_count' => 0,
                'created_at' => now(),
            ]);

            DB::table('ahg_webhook')
                ->where('id', $webhook->id)
                ->update(['last_triggered_at' => now()]);

            $this->deliver($deliveryId);
        }
    }

    /**
     * Deliver a single webhook payload.
     */
    public function deliver(int $deliveryId): bool
    {
        $delivery = DB::table('ahg_webhook_delivery')->where('id', $deliveryId)->first();
        if (!$delivery) {
            return false;
        }

        $webhook = DB::table('ahg_webhook')->where('id', $delivery->webhook_id)->first();
        if (!$webhook) {
            return false;
        }

        $payload = $delivery->payload;
        $signature = hash_hmac('sha256', $payload, $webhook->secret);

        DB::table('ahg_webhook_delivery')->where('id', $deliveryId)->update([
            'attempt_count' => DB::raw('attempt_count + 1'),
            'status' => 'pending',
        ]);

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => "sha256={$signature}",
                    'X-Webhook-Delivery' => (string) $deliveryId,
                    'User-Agent' => 'Heratio-Webhook/1.0',
                ])
                ->withOptions(['allow_redirects' => false])
                ->post($webhook->url, json_decode($payload, true));

            $statusCode = $response->status();
            $success = $statusCode >= 200 && $statusCode < 300;

            DB::table('ahg_webhook_delivery')->where('id', $deliveryId)->update([
                'response_code' => $statusCode,
                'response_body' => substr($response->body(), 0, 65535),
                'status' => $success ? 'success' : 'failed',
                'delivered_at' => $success ? now() : null,
                'next_retry_at' => $success ? null : $this->nextRetryTime($delivery->attempt_count + 1),
            ]);

            if ($success) {
                DB::table('ahg_webhook')->where('id', $webhook->id)->update(['failure_count' => 0]);
            } else {
                $this->handleFailure($webhook, $delivery);
            }

            return $success;
        } catch (\Throwable $e) {
            DB::table('ahg_webhook_delivery')->where('id', $deliveryId)->update([
                'response_body' => substr($e->getMessage(), 0, 65535),
                'status' => 'failed',
                'next_retry_at' => $this->nextRetryTime($delivery->attempt_count + 1),
            ]);
            $this->handleFailure($webhook, $delivery);
            return false;
        }
    }

    protected function handleFailure(object $webhook, object $delivery): void
    {
        $attempts = ($delivery->attempt_count ?? 0) + 1;
        if ($attempts >= 5) {
            DB::table('ahg_webhook')->where('id', $webhook->id)
                ->update(['failure_count' => DB::raw('failure_count + 1')]);
        }
    }

    protected function nextRetryTime(int $attempt): ?string
    {
        if ($attempt >= 5) {
            return null;
        }
        $delays = [60, 120, 240, 480, 960];
        $seconds = $delays[$attempt - 1] ?? 960;
        return now()->addSeconds($seconds)->toDateTimeString();
    }

    /**
     * Process pending retries.
     */
    public function processRetries(): int
    {
        $pending = DB::table('ahg_webhook_delivery')
            ->where('status', 'failed')
            ->where('attempt_count', '<', 5)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->limit(50)
            ->get();

        $processed = 0;
        foreach ($pending as $delivery) {
            $this->deliver($delivery->id);
            $processed++;
        }

        return $processed;
    }

    /**
     * Regenerate a webhook's secret.
     */
    public function regenerateSecret(int $webhookId): string
    {
        $secret = Str::random(64);
        DB::table('ahg_webhook')->where('id', $webhookId)->update([
            'secret' => $secret,
            'updated_at' => now(),
        ]);
        return $secret;
    }

    public function getValidEvents(): array
    {
        return $this->validEvents;
    }

    public function getValidEntityTypes(): array
    {
        return $this->validEntityTypes;
    }
}
