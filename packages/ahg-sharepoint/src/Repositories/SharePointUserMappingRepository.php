<?php

namespace AhgSharePoint\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Repositories\SharePointUserMappingRepository.
 *
 * @phase 2.B
 */
class SharePointUserMappingRepository
{
    public function findByAadOid(string $oid): ?object
    {
        return DB::table('sharepoint_user_mapping')->where('aad_object_id', $oid)->first();
    }

    public function findByAtomUserId(int $userId): ?object
    {
        return DB::table('sharepoint_user_mapping')->where('atom_user_id', $userId)->first();
    }

    /** @return array<int, object> */
    public function all(): array
    {
        return DB::table('sharepoint_user_mapping')->orderByDesc('created_at')->get()->all();
    }

    public function create(array $attributes): int
    {
        $attributes['created_at'] ??= now();
        return (int) DB::table('sharepoint_user_mapping')->insertGetId($attributes);
    }

    public function touchLastSeen(int $id): void
    {
        DB::table('sharepoint_user_mapping')->where('id', $id)->update(['last_seen_at' => now()]);
    }

    public function delete(int $id): void
    {
        DB::table('sharepoint_user_mapping')->where('id', $id)->delete();
    }
}
