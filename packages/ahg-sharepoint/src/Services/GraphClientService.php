<?php

namespace AhgSharePoint\Services;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use Illuminate\Support\Facades\Http;

/**
 * Mirror of AtomExtensions\SharePoint\Services\GraphClientService.
 *
 * Decision (locked 2026-05-10): no microsoft/microsoft-graph SDK.
 * Uses Laravel Http facade (Guzzle-backed) for outbound calls.
 *
 * @phase 1
 */
class GraphClientService
{
    private const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    private const GRAPH_DEFAULT_SCOPE = 'https://graph.microsoft.com/.default';
    private const DOWNLOAD_TIMEOUT_SECONDS = 60;

    public function __construct(
        private GraphTokenCache $cache,
        private ?SharePointTenantRepository $tenants = null,
    ) {
        $this->tenants = $this->tenants ?? new SharePointTenantRepository();
    }

    public function acquireToken(int $tenantId): string
    {
        $cached = $this->cache->get($tenantId);
        if ($cached !== null) {
            return $cached;
        }

        $tenant = $this->tenants->find($tenantId);
        if ($tenant === null) {
            throw new \RuntimeException("Tenant {$tenantId} not found");
        }
        $secret = $this->tenants->resolveSecret($tenantId);

        $resp = Http::asForm()->timeout(15)->post(
            sprintf(self::TOKEN_URL, $tenant->tenant_id),
            [
                'client_id' => $tenant->client_id,
                'client_secret' => $secret,
                'scope' => self::GRAPH_DEFAULT_SCOPE,
                'grant_type' => 'client_credentials',
            ],
        );

        if (!$resp->successful()) {
            $this->tenants->update($tenantId, [
                'last_error' => substr('token acquire failed: HTTP ' . $resp->status() . ' ' . $resp->body(), 0, 65000),
                'status' => 'error',
            ]);
            throw new \RuntimeException(
                "Token acquisition failed for tenant {$tenantId}: HTTP {$resp->status()} {$resp->body()}",
            );
        }

        $payload = $resp->json();
        if (!is_array($payload) || empty($payload['access_token'])) {
            throw new \RuntimeException('Token response malformed');
        }

        $token = (string) $payload['access_token'];
        $expiresIn = (int) ($payload['expires_in'] ?? 3600);
        $this->cache->put($tenantId, $token, $expiresIn);

        $this->tenants->update($tenantId, [
            'last_token_at' => now(),
            'last_error' => null,
            'status' => 'active',
        ]);

        return $token;
    }

    public function acquireOboToken(int $tenantId, string $userToken, string $graphScope): string
    {
        $tenant = $this->tenants->find($tenantId);
        if ($tenant === null) {
            throw new \RuntimeException("Tenant {$tenantId} not found");
        }
        $secret = $this->tenants->resolveSecret($tenantId);

        $resp = Http::asForm()->timeout(15)->post(
            sprintf(self::TOKEN_URL, $tenant->tenant_id),
            [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'client_id' => $tenant->client_id,
                'client_secret' => $secret,
                'assertion' => $userToken,
                'scope' => $graphScope,
                'requested_token_use' => 'on_behalf_of',
            ],
        );

        if (!$resp->successful()) {
            throw new \RuntimeException("OBO token exchange failed: HTTP {$resp->status()} {$resp->body()}");
        }
        $payload = $resp->json();
        if (!is_array($payload) || empty($payload['access_token'])) {
            throw new \RuntimeException('OBO token response malformed');
        }
        return (string) $payload['access_token'];
    }

    public function get(int $tenantId, string $path, array $headers = []): array
    {
        return $this->request($tenantId, 'GET', $path, null, $headers);
    }

    public function post(int $tenantId, string $path, array $body, array $headers = []): array
    {
        return $this->request($tenantId, 'POST', $path, $body, $headers);
    }

    public function patch(int $tenantId, string $path, array $body, array $headers = []): array
    {
        return $this->request($tenantId, 'PATCH', $path, $body, $headers);
    }

    public function delete(int $tenantId, string $path, array $headers = []): void
    {
        $this->request($tenantId, 'DELETE', $path, null, $headers, expectJson: false);
    }

    public function downloadDriveItem(int $tenantId, string $siteId, string $driveId, string $itemId, string $destinationPath): void
    {
        $token = $this->acquireToken($tenantId);
        $url = $this->resolveBase($tenantId)
            . "/sites/{$siteId}/drives/{$driveId}/items/{$itemId}/content";

        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Stream via Guzzle's sink option for large files (no in-memory cap)
        $resp = Http::withToken($token)
            ->timeout(self::DOWNLOAD_TIMEOUT_SECONDS)
            ->withOptions(['sink' => $destinationPath, 'allow_redirects' => true])
            ->get($url);

        if (!$resp->successful()) {
            throw new \RuntimeException(
                "downloadDriveItem failed: HTTP {$resp->status()} for {$url}",
            );
        }
    }

    public function getListItemFields(int $tenantId, string $siteId, string $driveId, string $itemId): array
    {
        $resp = $this->get(
            $tenantId,
            "/sites/{$siteId}/drives/{$driveId}/items/{$itemId}/listItem?\$expand=fields",
        );
        return is_array($resp['fields'] ?? null) ? $resp['fields'] : [];
    }

    private function request(int $tenantId, string $method, string $path, ?array $body, array $headers, bool $expectJson = true): array
    {
        $token = $this->acquireToken($tenantId);
        $url = $this->resolveBase($tenantId) . $this->ensureLeadingSlash($path);

        $client = Http::withToken($token)
            ->withHeaders(array_merge(['Accept' => 'application/json'], $headers))
            ->timeout(30);

        $resp = $body !== null
            ? $client->send($method, $url, ['json' => $body])
            : $client->send($method, $url);

        if ($resp->status() === 401) {
            $this->cache->invalidate($tenantId);
            $token = $this->acquireToken($tenantId);
            $client = Http::withToken($token)
                ->withHeaders(array_merge(['Accept' => 'application/json'], $headers))
                ->timeout(30);
            $resp = $body !== null
                ? $client->send($method, $url, ['json' => $body])
                : $client->send($method, $url);
        }

        if (!$resp->successful()) {
            throw new \RuntimeException(
                "Graph {$method} {$path} failed: HTTP {$resp->status()} " . substr($resp->body(), 0, 1000),
            );
        }

        if (!$expectJson || $resp->body() === '') {
            return [];
        }
        $decoded = $resp->json();
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveBase(int $tenantId): string
    {
        $tenant = $this->tenants->find($tenantId);
        $base = (string) ($tenant->graph_endpoint ?? 'https://graph.microsoft.com/v1.0');
        return rtrim($base, '/');
    }

    private function ensureLeadingSlash(string $path): string
    {
        return str_starts_with($path, '/') ? $path : '/' . $path;
    }
}
