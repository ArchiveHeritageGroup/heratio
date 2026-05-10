<?php

namespace AhgSharePoint\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Services\GraphTokenCache.
 *
 * Tokens are short-lived (~60 min); cache miss triggers re-acquisition via
 * client-credentials flow.
 *
 * @phase 1
 */
class GraphTokenCache
{
    private const SAFETY_MARGIN_SECONDS = 60;
    private const CACHE_PREFIX = 'sharepoint.token.';

    public function get(int $tenantId): ?string
    {
        $cached = Cache::get(self::CACHE_PREFIX . $tenantId);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        // DB fallback for cross-process visibility
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'sharepoint_runtime')
            ->where('setting_key', "access_token_{$tenantId}")
            ->first();
        if ($row === null || empty($row->setting_value)) {
            return null;
        }
        $payload = json_decode($row->setting_value, true);
        if (!is_array($payload) || empty($payload['token']) || empty($payload['expires_at'])) {
            return null;
        }
        if ((int) $payload['expires_at'] - self::SAFETY_MARGIN_SECONDS <= time()) {
            return null;
        }
        Cache::put(self::CACHE_PREFIX . $tenantId, $payload['token'], (int) $payload['expires_at'] - time() - self::SAFETY_MARGIN_SECONDS);
        return (string) $payload['token'];
    }

    public function put(int $tenantId, string $token, int $expiresInSeconds): void
    {
        $expiresAt = time() + max(0, $expiresInSeconds);
        Cache::put(self::CACHE_PREFIX . $tenantId, $token, max(0, $expiresInSeconds - self::SAFETY_MARGIN_SECONDS));

        DB::table('ahg_settings')->updateOrInsert(
            ['setting_group' => 'sharepoint_runtime', 'setting_key' => "access_token_{$tenantId}"],
            [
                'setting_value' => json_encode(['token' => $token, 'expires_at' => $expiresAt]),
                'updated_at' => now(),
            ],
        );
    }

    public function invalidate(int $tenantId): void
    {
        Cache::forget(self::CACHE_PREFIX . $tenantId);
        DB::table('ahg_settings')
            ->where('setting_group', 'sharepoint_runtime')
            ->where('setting_key', "access_token_{$tenantId}")
            ->delete();
    }
}
