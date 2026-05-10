<?php

namespace AhgSharePoint\Services;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Mirror of AtomExtensions\SharePoint\Services\GraphTokenValidatorService.
 *
 * @phase 2.B / 3
 */
class GraphTokenValidatorService
{
    private const JWKS_TTL_SECONDS = 3600;

    public function __construct(
        private SharePointTenantRepository $tenants,
    ) {
    }

    public function validate(string $bearerToken, int $expectedTenantId): array
    {
        $tenant = $this->tenants->find($expectedTenantId);
        if ($tenant === null) {
            throw new \RuntimeException("Tenant {$expectedTenantId} not found");
        }

        $jwks = $this->fetchJwks((string) $tenant->tenant_id);
        $keys = JWK::parseKeySet($jwks);

        try {
            $decoded = (array) JWT::decode($bearerToken, $keys);
        } catch (\Throwable $e) {
            throw new \RuntimeException('JWT decode failed: ' . $e->getMessage(), 0, $e);
        }

        $tid = (string) ($decoded['tid'] ?? '');
        if ($tid !== (string) $tenant->tenant_id) {
            throw new \RuntimeException('JWT tenant id mismatch');
        }

        $expectedAudience = $this->expectedAudience($tenant);
        $aud = $decoded['aud'] ?? '';
        if (!$this->audienceMatches($aud, $expectedAudience)) {
            throw new \RuntimeException('JWT audience mismatch');
        }

        $expectedIssuer = "https://login.microsoftonline.com/{$tid}/v2.0";
        if (!isset($decoded['iss']) || (string) $decoded['iss'] !== $expectedIssuer) {
            throw new \RuntimeException('JWT issuer mismatch');
        }

        if (empty($decoded['oid'])) {
            throw new \RuntimeException('JWT missing oid claim — cannot identify user');
        }

        return [
            'oid' => (string) $decoded['oid'],
            'upn' => isset($decoded['upn']) ? (string) $decoded['upn'] : null,
            'email' => isset($decoded['email']) ? (string) $decoded['email'] : null,
            'name' => isset($decoded['name']) ? (string) $decoded['name'] : null,
            'tid' => $tid,
            'scp' => isset($decoded['scp']) ? (string) $decoded['scp'] : null,
            'sub' => (string) ($decoded['sub'] ?? ''),
            '_raw' => $bearerToken,
        ];
    }

    private function expectedAudience(object $tenant): string
    {
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'sharepoint')
            ->where('setting_key', 'expected_jwt_audience')
            ->first();
        if ($row !== null && !empty($row->setting_value)) {
            return (string) $row->setting_value;
        }
        return "api://{$tenant->client_id}";
    }

    private function audienceMatches(mixed $audClaim, string $expected): bool
    {
        if (is_array($audClaim)) {
            return in_array($expected, $audClaim, true);
        }
        return (string) $audClaim === $expected;
    }

    private function fetchJwks(string $tenantId): array
    {
        $cacheKey = "sharepoint.jwks.{$tenantId}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/discovery/v2.0/keys";
        $resp = Http::timeout(5)->get($url);
        if (!$resp->successful()) {
            throw new \RuntimeException('Cannot fetch AAD JWKS at ' . $url);
        }
        $jwks = $resp->json();
        if (!is_array($jwks) || empty($jwks['keys'])) {
            throw new \RuntimeException('AAD JWKS body malformed');
        }

        Cache::put($cacheKey, $jwks, self::JWKS_TTL_SECONDS);
        return $jwks;
    }
}
