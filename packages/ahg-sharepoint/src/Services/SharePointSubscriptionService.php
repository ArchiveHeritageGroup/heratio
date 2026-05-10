<?php

namespace AhgSharePoint\Services;

use AhgSharePoint\Repositories\SharePointDriveRepository;
use AhgSharePoint\Repositories\SharePointSubscriptionRepository;
use AhgSharePoint\Repositories\SharePointTenantRepository;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointSubscriptionService.
 *
 * @phase 2.A
 */
class SharePointSubscriptionService
{
    private const RENEWAL_DURATION_HOURS = 24 * 28;
    private const RENEW_BEFORE_HOURS = 24;

    public function __construct(
        private GraphClientService $graph,
        private SharePointTenantRepository $tenants,
        private SharePointDriveRepository $drives,
        private SharePointSubscriptionRepository $subscriptions,
    ) {
    }

    /**
     * @return array{drive_item:int, list:int}
     */
    public function subscribeDrive(int $driveId, string $publicWebhookUrl): array
    {
        $drive = $this->drives->find($driveId);
        if ($drive === null) {
            throw new \InvalidArgumentException("Drive {$driveId} not found");
        }
        if (!$drive->ingest_enabled) {
            throw new \DomainException("Drive {$driveId} is not ingest_enabled");
        }
        $tenant = $this->tenants->find((int) $drive->tenant_id);
        if ($tenant === null) {
            throw new \RuntimeException("Tenant {$drive->tenant_id} for drive {$driveId} not found");
        }

        $driveItemSubId = $this->createOne(
            $tenant, $drive,
            "/sites/{$drive->site_id}/drives/{$drive->drive_id}/root",
            $publicWebhookUrl,
        );

        $listResource = $this->resolveListResourcePath($tenant, $drive);
        $listSubId = $this->createOne($tenant, $drive, $listResource, $publicWebhookUrl);

        return ['drive_item' => $driveItemSubId, 'list' => $listSubId];
    }

    /**
     * @return array{renewed:int, errors:int}
     */
    public function renewExpiring(): array
    {
        $expiring = $this->subscriptions->expiringWithin('INTERVAL ' . self::RENEW_BEFORE_HOURS . ' HOUR');
        $renewed = 0;
        $errors = 0;
        foreach ($expiring as $sub) {
            try {
                $this->renewOne((int) $sub->id);
                $renewed++;
            } catch (\Throwable $e) {
                $errors++;
                $this->subscriptions->update((int) $sub->id, ['status' => 'error']);
            }
        }
        return ['renewed' => $renewed, 'errors' => $errors];
    }

    public function deleteSubscription(int $subscriptionRowId): void
    {
        $sub = $this->subscriptions->find($subscriptionRowId);
        if ($sub === null) {
            return;
        }
        $drive = $this->drives->find((int) $sub->drive_id);
        if ($drive !== null) {
            $tenant = $this->tenants->find((int) $drive->tenant_id);
            if ($tenant !== null) {
                try {
                    $this->graph->delete((int) $tenant->id, "/subscriptions/{$sub->subscription_id}");
                } catch (\Throwable $e) {
                    // best-effort
                }
            }
        }
        $this->subscriptions->delete($subscriptionRowId);
    }

    private function createOne(object $tenant, object $drive, string $resourcePath, string $publicWebhookUrl): int
    {
        $expiresAt = new \DateTimeImmutable('+' . self::RENEWAL_DURATION_HOURS . ' hours');

        $body = [
            'changeType' => 'updated',
            'notificationUrl' => $publicWebhookUrl,
            'resource' => $resourcePath,
            'expirationDateTime' => $expiresAt->format(\DateTimeInterface::ATOM),
            'clientState' => $tenant->webhook_client_state,
        ];

        $response = $this->graph->post((int) $tenant->id, '/subscriptions', $body);
        if (empty($response['id'])) {
            throw new \RuntimeException('Graph subscription create returned no id: ' . json_encode($response));
        }

        return $this->subscriptions->create([
            'drive_id' => $drive->id,
            'subscription_id' => $response['id'],
            'resource' => $resourcePath,
            'change_type' => 'updated',
            'notification_url' => $publicWebhookUrl,
            'client_state' => $tenant->webhook_client_state,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);
    }

    private function renewOne(int $subscriptionRowId): void
    {
        $sub = $this->subscriptions->find($subscriptionRowId);
        if ($sub === null) {
            return;
        }
        $drive = $this->drives->find((int) $sub->drive_id);
        if ($drive === null) {
            $this->subscriptions->markStatus($subscriptionRowId, 'error');
            return;
        }
        $tenant = $this->tenants->find((int) $drive->tenant_id);
        if ($tenant === null) {
            $this->subscriptions->markStatus($subscriptionRowId, 'error');
            return;
        }

        $newExpiry = new \DateTimeImmutable('+' . self::RENEWAL_DURATION_HOURS . ' hours');
        $this->graph->patch((int) $tenant->id, "/subscriptions/{$sub->subscription_id}", [
            'expirationDateTime' => $newExpiry->format(\DateTimeInterface::ATOM),
        ]);
        $this->subscriptions->markRenewed($subscriptionRowId, $newExpiry);
    }

    private function resolveListResourcePath(object $tenant, object $drive): string
    {
        $resp = $this->graph->get(
            (int) $tenant->id,
            "/sites/{$drive->site_id}/drives/{$drive->drive_id}/list?\$select=id",
        );
        if (empty($resp['id'])) {
            throw new \RuntimeException("Cannot resolve list id for drive {$drive->drive_id}");
        }
        return "/sites/{$drive->site_id}/lists/{$resp['id']}";
    }
}
