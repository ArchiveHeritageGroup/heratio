<?php

namespace AhgSharePoint\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Repositories\SharePointDriveRepository.
 *
 * @phase 1
 */
class SharePointDriveRepository
{
    public function find(int $id): ?object
    {
        return DB::table('sharepoint_drive')->where('id', $id)->first();
    }

    /** @return array<int, object> */
    public function forTenant(int $tenantId): array
    {
        return DB::table('sharepoint_drive')
            ->where('tenant_id', $tenantId)
            ->orderBy('site_title')
            ->get()
            ->all();
    }

    /** @return array<int, object> */
    public function ingestEnabled(): array
    {
        return DB::table('sharepoint_drive')->where('ingest_enabled', 1)->get()->all();
    }

    public function create(array $attributes): int
    {
        return (int) DB::table('sharepoint_drive')->insertGetId($attributes);
    }

    public function update(int $id, array $attributes): void
    {
        DB::table('sharepoint_drive')->where('id', $id)->update($attributes);
    }

    public function delete(int $id): void
    {
        DB::table('sharepoint_drive')->where('id', $id)->delete();
    }
}
