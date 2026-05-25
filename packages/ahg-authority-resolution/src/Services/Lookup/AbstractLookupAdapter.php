<?php

/**
 * AbstractLookupAdapter - Heratio
 *
 * Shared plumbing for every concrete external-source adapter (Task 6).
 * Subclasses only need to implement `source()`, `supports()` and the
 * inner `fetchFromSource()` HTTP call - this base class handles the
 * enabled toggle, rate limiting, cache lookup/write, and consistent
 * error handling.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class AbstractLookupAdapter implements LookupAdapterInterface
{
    /**
     * Per-process rate-limit ledger. Source name -> list of unix timestamps
     * of recent calls (seconds). Best-effort: resets when php-fpm worker
     * restarts. For stricter limits use Redis / DB-backed sliding window.
     *
     * @var array<string, list<int>>
     */
    private static array $callLog = [];

    abstract public function source(): string;

    abstract public function supports(string $entityType): bool;

    /**
     * Subclasses implement the actual HTTP call + parse. Return the same
     * candidate shape documented in LookupAdapterInterface. Throwing is
     * fine - the base class catches and returns [].
     *
     * @return list<array<string,mixed>>
     */
    abstract protected function fetchFromSource(string $query, string $entityType, int $limit): array;

    /**
     * Public search entry point. Enabled check -> cache lookup -> rate
     * limit -> fetch -> cache write.
     *
     * @return list<array<string,mixed>>
     */
    public function search(string $query, string $entityType, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '' || ! $this->supports($entityType)) {
            return [];
        }

        if (! $this->isEnabled()) {
            return [];
        }

        $cached = $this->readCache($query, $entityType);
        if ($cached !== null) {
            return array_slice($cached, 0, $limit);
        }

        if (! $this->withinRateLimit()) {
            Log::info('AuthRes lookup: rate limited', ['source' => $this->source(), 'query' => $query]);

            return [];
        }

        try {
            $this->recordCall();
            $results = $this->fetchFromSource($query, $entityType, $limit);
        } catch (\Throwable $e) {
            Log::warning('AuthRes lookup: adapter threw', [
                'source' => $this->source(),
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            $results = [];
        }

        // Cache even an empty result - prevents repeated 404 storms.
        $this->writeCache($query, $entityType, $results);

        return array_slice($results, 0, $limit);
    }

    // ---- Settings helpers ----------------------------------------------

    protected function settingValue(string $key, $default = null)
    {
        try {
            $val = DB::table('ahg_settings')
                ->where('setting_key', $key)
                ->value('setting_value');

            return $val === null ? $default : $val;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    protected function isEnabled(): bool
    {
        return (int) $this->settingValue('lookup.'.$this->source().'.enabled', 0) === 1;
    }

    protected function rateLimit(): int
    {
        return max(1, (int) $this->settingValue('lookup.'.$this->source().'.rate_limit', 60));
    }

    protected function cacheTtl(): int
    {
        return max(60, (int) $this->settingValue('lookup.'.$this->source().'.cache_ttl', 604800));
    }

    protected function licenceNote(): ?string
    {
        $v = $this->settingValue('lookup.'.$this->source().'.license_note');

        return is_string($v) && trim($v) !== '' ? $v : null;
    }

    protected function licenceUrl(): ?string
    {
        $v = $this->settingValue('lookup.'.$this->source().'.license_url');

        return is_string($v) && trim($v) !== '' ? $v : null;
    }

    protected function httpTimeout(): int
    {
        return max(2, (int) $this->settingValue('lookup.http_timeout', 8));
    }

    // ---- Rate limit ----------------------------------------------------

    private function withinRateLimit(): bool
    {
        $src = $this->source();
        $now = time();
        $window = 60;
        $limit = $this->rateLimit();

        $log = self::$callLog[$src] ?? [];
        $log = array_values(array_filter($log, fn ($ts) => $ts >= $now - $window));
        self::$callLog[$src] = $log;

        return count($log) < $limit;
    }

    private function recordCall(): void
    {
        $src = $this->source();
        self::$callLog[$src] ??= [];
        self::$callLog[$src][] = time();
    }

    public function lastCallAt(): ?int
    {
        $log = self::$callLog[$this->source()] ?? [];

        return empty($log) ? null : max($log);
    }

    // ---- Cache (ahg_authority_lookup_cache) ----------------------------

    private function readCache(string $query, string $entityType): ?array
    {
        try {
            $row = DB::table('ahg_authority_lookup_cache')
                ->where('source', $this->source())
                ->where('entity_type', $entityType)
                ->where('query_text', $query)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
        if (! $row) {
            return null;
        }
        $age = time() - strtotime((string) $row->retrieved_at);
        if ($age > (int) $row->ttl_seconds) {
            return null;
        }
        $decoded = json_decode((string) $row->payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(string $query, string $entityType, array $results): void
    {
        try {
            DB::table('ahg_authority_lookup_cache')->updateOrInsert(
                [
                    'source' => $this->source(),
                    'entity_type' => $entityType,
                    'query_text' => $query,
                ],
                [
                    'payload' => json_encode($results, JSON_UNESCAPED_UNICODE),
                    'license_note' => $this->licenceNote(),
                    'retrieved_at' => now(),
                    'ttl_seconds' => $this->cacheTtl(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('AuthRes lookup: cache write failed', [
                'source' => $this->source(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function nowIso(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
