<?php

namespace AhgSharePoint\Services;

use AhgSharePoint\Jobs\IngestSharePointEventJob;
use AhgSharePoint\Repositories\SharePointEventRepository;
use AhgSharePoint\Repositories\SharePointSubscriptionRepository;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointWebhookHandler.
 *
 * @phase 2.A
 */
class SharePointWebhookHandler
{
    public function __construct(
        private SharePointSubscriptionRepository $subscriptions,
        private SharePointEventRepository $events,
    ) {
    }

    public function handleValidationToken(?string $validationToken): ?string
    {
        if ($validationToken === null || $validationToken === '') {
            return null;
        }
        return $validationToken;
    }

    /**
     * @return array{accepted:int, dropped:int, queued_event_ids:array<int>}
     */
    public function handleNotifications(array $payload): array
    {
        $accepted = 0;
        $dropped = 0;
        $queuedIds = [];

        foreach ($payload['value'] ?? [] as $note) {
            $subscriptionId = (string) ($note['subscriptionId'] ?? '');
            $clientState = (string) ($note['clientState'] ?? '');
            $changeType = (string) ($note['changeType'] ?? 'updated');
            $resourceData = $note['resourceData'] ?? [];

            $sub = $this->subscriptions->findBySubscriptionId($subscriptionId);
            if ($sub === null || !hash_equals((string) $sub->client_state, $clientState)) {
                $dropped++;
                continue;
            }

            $itemId = isset($resourceData['id']) ? (string) $resourceData['id'] : null;
            $etag = isset($resourceData['eTag']) ? (string) $resourceData['eTag'] : null;

            $eventId = $this->events->create([
                'subscription_id' => $sub->id,
                'drive_id' => $sub->drive_id,
                'sp_item_id' => $itemId,
                'sp_etag' => $etag,
                'change_type' => $changeType,
                'raw_payload' => json_encode($note, JSON_UNESCAPED_SLASHES),
            ]);

            $this->dispatchIngestJob($eventId);
            $accepted++;
            $queuedIds[] = $eventId;
        }

        return ['accepted' => $accepted, 'dropped' => $dropped, 'queued_event_ids' => $queuedIds];
    }

    private function dispatchIngestJob(int $eventId): void
    {
        try {
            IngestSharePointEventJob::dispatch($eventId)->onQueue('integrations');
            $this->events->update($eventId, ['status' => 'queued']);
        } catch (\Throwable $e) {
            $this->events->update($eventId, ['last_error' => 'queue dispatch failed: ' . $e->getMessage()]);
        }
    }
}
