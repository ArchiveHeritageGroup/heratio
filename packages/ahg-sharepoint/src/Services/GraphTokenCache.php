<?php

namespace AhgSharePoint\Services;

use Illuminate\Support\Facades\Cache;

/**
 * GraphTokenCache — Laravel cache + ahg_settings-backed access token store.
 *
 * Mirror of AtomExtensions\SharePoint\Services\GraphTokenCache.
 *
 * Tokens are short-lived (~60 min); cache miss triggers re-acquisition via
 * client-credentials flow.
 *
 * @phase 1
 */
class GraphTokenCache
{
    private const CACHE_PREFIX = 'sharepoint.token.';

    public function get(int $tenantId): ?string
    {
        // TODO: 1) check Laravel cache; 2) check ahg_settings fallback; 3) null if expired/absent.
        return Cache::get(self::CACHE_PREFIX . $tenantId);
    }

    public function put(int $tenantId, string $token, int $expiresInSeconds): void
    {
        // 60s safety margin
        Cache::put(self::CACHE_PREFIX . $tenantId, $token, max(0, $expiresInSeconds - 60));
        // TODO: also persist to ahg_settings group=sharepoint_runtime for cross-process visibility.
    }

    public function invalidate(int $tenantId): void
    {
        Cache::forget(self::CACHE_PREFIX . $tenantId);
    }
}
