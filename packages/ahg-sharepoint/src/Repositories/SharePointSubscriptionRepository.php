<?php

namespace AhgSharePoint\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Repositories\SharePointSubscriptionRepository.
 *
 * Per Phase 2 decision (plan §6.4): every ingest-enabled drive has TWO
 * subscriptions — one on /drives/{id}/root, one on /sites/{site}/lists/{list}.
 *
 * @phase 2.A
 */
class SharePointSubscriptionRepository
{
    public const RESOURCE_DRIVE_ITEM = 'driveItem';
    public const RESOURCE_LIST = 'list';

    public function find(int $id): ?object
    {
        return DB::table('sharepoint_subscription')->where('id', $id)->first();
    }

    public function findBySubscriptionId(string $subscriptionId): ?object
    {
        return DB::table('sharepoint_subscription')
            ->where('subscription_id', $subscriptionId)
            ->first();
    }

    /** @return array<int, object> */
    public function forDrive(int $driveId): array
    {
        return DB::table('sharepoint_subscription')
            ->where('drive_id', $driveId)
            ->get()
            ->all();
    }

    /** @return array<int, object> */
    public function expiringWithin(string $intervalSql = 'INTERVAL 12 HOUR'): array
    {
        return DB::table('sharepoint_subscription')
            ->where('status', 'active')
            ->whereRaw("expires_at < DATE_ADD(NOW(), {$intervalSql})")
            ->get()
            ->all();
    }

    public function create(array $attributes): int
    {
        return (int) DB::table('sharepoint_subscription')->insertGetId($attributes);
    }

    public function update(int $id, array $attributes): void
    {
        DB::table('sharepoint_subscription')->where('id', $id)->update($attributes);
    }

    public function markRenewed(int $id, \DateTimeInterface $newExpiry): void
    {
        DB::table('sharepoint_subscription')
            ->where('id', $id)
            ->update([
                'expires_at' => $newExpiry->format('Y-m-d H:i:s'),
                'last_renewed_at' => now(),
                'status' => 'active',
            ]);
    }

    public function markStatus(int $id, string $status): void
    {
        DB::table('sharepoint_subscription')
            ->where('id', $id)
            ->update(['status' => $status]);
    }

    public function delete(int $id): void
    {
        DB::table('sharepoint_subscription')->where('id', $id)->delete();
    }
}
