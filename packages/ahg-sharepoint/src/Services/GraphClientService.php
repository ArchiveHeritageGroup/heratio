<?php

namespace AhgSharePoint\Services;

use Illuminate\Support\Facades\Http;

/**
 * GraphClientService — hand-rolled Microsoft Graph wrapper.
 *
 * Mirror of AtomExtensions\SharePoint\Services\GraphClientService.
 *
 * Decision (locked 2026-05-10): no microsoft/microsoft-graph SDK.
 * Uses Laravel Http facade (Guzzle-backed) for outbound calls.
 *
 * @phase 1
 */
class GraphClientService
{
    public function __construct(private GraphTokenCache $cache)
    {
    }

    public function acquireToken(int $tenantId): string
    {
        // TODO (Phase 1):
        //   1. Look up tenant row + decrypted client_secret via SharePointTenantRepository.
        //   2. Check $this->cache; return if non-expired.
        //   3. POST to login.microsoftonline.com/{tenant}/oauth2/v2.0/token.
        //   4. Persist via $this->cache->put().
        throw new \RuntimeException('GraphClientService::acquireToken not implemented yet');
    }

    /**
     * On-Behalf-Of flow — exchange a user's bearer token for a Graph token
     * impersonating that user. Used by manual-push file fetch path so
     * Heratio never bypasses the user's SharePoint permissions.
     */
    public function acquireOboToken(int $tenantId, string $userToken, string $graphScope): string
    {
        // TODO (Phase 2.B):
        //   POST /oauth2/v2.0/token with grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer,
        //   requested_token_use=on_behalf_of, assertion=$userToken, scope=$graphScope.
        //   Cache by (tenantId, oid claim, scope, hash($userToken)) — short TTL.
        throw new \RuntimeException('GraphClientService::acquireOboToken not implemented yet');
    }

    public function get(int $tenantId, string $path, array $headers = []): array
    {
        throw new \RuntimeException('GraphClientService::get not implemented yet');
    }

    public function post(int $tenantId, string $path, array $body, array $headers = []): array
    {
        throw new \RuntimeException('GraphClientService::post not implemented yet');
    }

    public function patch(int $tenantId, string $path, array $body, array $headers = []): array
    {
        throw new \RuntimeException('GraphClientService::patch not implemented yet');
    }

    public function delete(int $tenantId, string $path, array $headers = []): void
    {
        throw new \RuntimeException('GraphClientService::delete not implemented yet');
    }

    public function downloadDriveItem(int $tenantId, string $siteId, string $driveId, string $itemId, string $destinationPath): void
    {
        throw new \RuntimeException('GraphClientService::downloadDriveItem not implemented yet');
    }

    public function getListItemFields(int $tenantId, string $siteId, string $driveId, string $itemId): array
    {
        throw new \RuntimeException('GraphClientService::getListItemFields not implemented yet');
    }
}
