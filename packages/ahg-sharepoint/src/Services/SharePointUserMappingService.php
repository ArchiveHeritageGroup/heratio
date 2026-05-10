<?php

namespace AhgSharePoint\Services;

use AhgSharePoint\Repositories\SharePointUserMappingRepository;
use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointUserMappingService.
 *
 * @phase 2.B
 */
class SharePointUserMappingService
{
    public function __construct(
        private SharePointUserMappingRepository $mappings,
    ) {
    }

    public function resolve(array $claims): ?int
    {
        $oid = (string) ($claims['oid'] ?? '');
        if ($oid === '') {
            return null;
        }

        $mapping = $this->mappings->findByAadOid($oid);
        if ($mapping !== null) {
            $this->mappings->touchLastSeen((int) $mapping->id);
            return (int) $mapping->atom_user_id;
        }

        if (!$this->autoCreateEnabled()) {
            return null;
        }

        $heratioUserId = $this->createHeratioUser($claims);
        if ($heratioUserId === null) {
            return null;
        }

        $this->mappings->create([
            'aad_object_id' => $oid,
            'aad_upn' => $claims['upn'] ?? null,
            'aad_email' => $claims['email'] ?? null,
            'atom_user_id' => $heratioUserId,
            'created_by' => 'auto',
            'last_seen_at' => now(),
        ]);

        return $heratioUserId;
    }

    private function autoCreateEnabled(): bool
    {
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'sharepoint')
            ->where('setting_key', 'sharepoint_push_user_create_enabled')
            ->first();
        if ($row === null) {
            return true; // default true per locked decision
        }
        return (string) $row->setting_value === 'true' || (string) $row->setting_value === '1';
    }

    /**
     * Create a Heratio user record. Heratio's user table uses Eloquent;
     * Phase 2.B integration step delegates to the ahg-acl package's user
     * creator (or whichever package owns user provisioning in Heratio).
     */
    private function createHeratioUser(array $claims): ?int
    {
        // TODO (Phase 2.B integration):
        //   1. Resolve Heratio's user creation service (likely AhgAcl\Services\UserService).
        //   2. Create user with username = upn or email, email = email claim,
        //      role = configurable default (default 'editor').
        //   3. Return user.id.
        throw new \RuntimeException(
            'SharePointUserMappingService::createHeratioUser not implemented yet — '
            . 'wire to Heratio\'s user provisioning service in ahg-acl.'
        );
    }
}
