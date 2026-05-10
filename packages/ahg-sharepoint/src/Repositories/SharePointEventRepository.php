<?php

namespace AhgSharePoint\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Repositories\SharePointEventRepository.
 *
 * @phase 2.A
 */
class SharePointEventRepository
{
    public function find(int $id): ?object
    {
        return DB::table('sharepoint_event')->where('id', $id)->first();
    }

    public function create(array $attributes): int
    {
        $attributes['received_at'] ??= now();
        $attributes['status'] ??= 'received';
        return (int) DB::table('sharepoint_event')->insertGetId($attributes);
    }

    public function update(int $id, array $attributes): void
    {
        DB::table('sharepoint_event')->where('id', $id)->update($attributes);
    }

    public function markStatus(int $id, string $status, ?string $error = null): void
    {
        $update = ['status' => $status];
        if ($error !== null) {
            $update['last_error'] = $error;
        }
        if (in_array($status, ['completed', 'skipped_duplicate', 'failed', 'skipped_not_allowlisted'], true)) {
            $update['processed_at'] = now();
        }
        DB::table('sharepoint_event')->where('id', $id)->update($update);
    }

    public function incrementAttempts(int $id): void
    {
        DB::table('sharepoint_event')->where('id', $id)->increment('attempts');
    }

    public function isDuplicate(int $driveId, ?string $itemId, ?string $etag, int $excludeEventId): bool
    {
        if ($itemId === null || $etag === null) {
            return false;
        }
        return DB::table('sharepoint_event')
            ->where('drive_id', $driveId)
            ->where('sp_item_id', $itemId)
            ->where('sp_etag', $etag)
            ->where('status', 'completed')
            ->where('id', '<>', $excludeEventId)
            ->exists();
    }

    /** @return array<string, int> */
    public function statusCounts(?string $sinceSql = '24 HOUR'): array
    {
        $query = DB::table('sharepoint_event')->select('status', DB::raw('COUNT(*) as n'))->groupBy('status');
        if ($sinceSql !== null) {
            $query->whereRaw("received_at >= DATE_SUB(NOW(), INTERVAL {$sinceSql})");
        }
        $rows = $query->get()->all();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->status] = (int) $row->n;
        }
        return $out;
    }
}
