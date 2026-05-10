<?php

namespace AhgSharePoint\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Repositories\SharePointTenantRepository.
 *
 * Secrets are NEVER stored in this table. client_secret_ref points to an
 * encrypted blob in ahg_settings; resolveSecret() decrypts via Laravel Crypt.
 *
 * @phase 1
 */
class SharePointTenantRepository
{
    public function find(int $id): ?object
    {
        return DB::table('sharepoint_tenant')->where('id', $id)->first();
    }

    /** @return array<int, object> */
    public function all(): array
    {
        return DB::table('sharepoint_tenant')->orderBy('name')->get()->all();
    }

    public function create(array $attributes): int
    {
        return (int) DB::table('sharepoint_tenant')->insertGetId($attributes);
    }

    public function update(int $id, array $attributes): void
    {
        DB::table('sharepoint_tenant')->where('id', $id)->update($attributes);
    }

    public function delete(int $id): void
    {
        DB::table('sharepoint_tenant')->where('id', $id)->delete();
    }

    public function resolveSecret(int $tenantId): string
    {
        // TODO:
        //   1. Fetch row, read client_secret_ref.
        //   2. Pull ciphertext from ahg_settings group=sharepoint, key=client_secret_ref.
        //   3. \Illuminate\Support\Facades\Crypt::decryptString($ciphertext) (or shared ahg-core EncryptionService).
        //   4. Return plaintext (do NOT log).
        throw new \RuntimeException('SharePointTenantRepository::resolveSecret not implemented yet');
    }
}
