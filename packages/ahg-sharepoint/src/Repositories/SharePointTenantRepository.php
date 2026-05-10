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
        $tenant = $this->find($tenantId);
        if ($tenant === null) {
            throw new \RuntimeException("Tenant {$tenantId} not found");
        }
        $ref = (string) ($tenant->client_secret_ref ?? '');
        if ($ref === '') {
            throw new \RuntimeException("Tenant {$tenantId} has no client_secret_ref");
        }

        $row = DB::table('ahg_settings')
            ->where('setting_group', 'sharepoint')
            ->where('setting_key', $ref)
            ->first();
        if ($row === null || empty($row->setting_value)) {
            throw new \RuntimeException("Encrypted client_secret not found at ahg_settings(sharepoint, {$ref})");
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString((string) $row->setting_value);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to decrypt client_secret: ' . $e->getMessage(), 0, $e);
        }
    }

    public function persistSecret(int $tenantId, string $plaintextSecret): string
    {
        $ciphertext = \Illuminate\Support\Facades\Crypt::encryptString($plaintextSecret);
        $ref = "client_secret_{$tenantId}";

        DB::table('ahg_settings')->updateOrInsert(
            ['setting_group' => 'sharepoint', 'setting_key' => $ref],
            ['setting_value' => $ciphertext, 'updated_at' => now()],
        );

        $this->update($tenantId, ['client_secret_ref' => $ref]);
        return $ref;
    }
}
