<?php

/**
 * K bartRemoteService - automated remote KBART feed scheduler
 *
 * Fetches one or more remote KBART TSV feeds on a schedule, parses them
 * via KbartService, and upserts the resulting records into the library.
 *
 * Copyright (C) 2026 Johan Pieterse
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Manages automated KBART feed subscriptions: fetch, parse, and store.
 *
 * Credentials / feed definitions live in library_kbart_feed (this service
 * creates the table lazily on first run).  Feed metadata (last_fetch_at,
 * last_fetch_status, last_row_count) is updated after each fetch so the
 * admin UI can display a health dashboard.  Guarded by
 * library_kbart_auto_import_enabled so operators can silence the schedule
 * without deleting the subscriptions.
 *
 * Process:
 *   1. Check library_kbart_auto_import_enabled flag → no-op when off
 *   2. Load all active subscriptions (url not blank, active = 1)
 *   3. For each: HTTP GET → raw TSV → KbartService::parseKbartRowsFromString
 *   4. writeImportBatch for the raw TSV
 *   5. Upsert metadata in library_kbart_feed (last_fetch_at, last_fetch_status,
 *      last_row_count, last_error)
 */
class KbartRemoteService
{
    /** Cache key for per-feed lock to prevent concurrent fetches. */
    private const LOCK_TTL_SECONDS = 300;

    /** Feed metadata cache key prefix. */
    private const CACHE_PREFIX = 'kbart_remote_feed:';

    public function __construct(
        private KbartService $kbart
    ) {}

    /**
     * Return true when the auto-import master switch is on.
     */
    public function isAutoImportEnabled(): bool
    {
        return (bool) \AhgCore\Services\SettingHelper::get(
            'library_kbart_auto_import_enabled',
            false
        );
    }

    /**
     * Lazily create the library_kbart_feed table if it does not exist.
     */
    public function ensureFeedTable(): void
    {
        if (! Schema::hasTable('library_kbart_feed')) {
            DB::statement("
                CREATE TABLE IF NOT EXISTS `library_kbart_feed` (
                    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'human-readable feed label',
                    `url` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'remote KBART TSV URL',
                    `vendor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'vendor/platform name',
                    `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'internal notes',
                    `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = include in scheduled runs',
                    `last_fetch_at` datetime DEFAULT NULL COMMENT 'ISO datetime of most recent fetch',
                    `last_fetch_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'success|fail|skipped',
                    `last_row_count` int unsigned DEFAULT 0 COMMENT 'rows written in most recent fetch',
                    `last_error` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `idx_url` (`url`(255)),
                    KEY `idx_active` (`active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /**
     * Fetch all active remote feeds and commit each to the library catalogue.
     *
     * Returns an array summary per feed: [feed_id, name, url, status, row_count, error].
     *
     * @return array<int, array{feed_id:int, name:string, url:string, status:string, row_count:int, error:string}>
     */
    public function fetchAllActiveFeeds(): array
    {
        if (! $this->isAutoImportEnabled()) {
            Log::info('KbartRemoteService: auto-import disabled — skipping fetch run.');
            return [];
        }

        $this->ensureFeedTable();
        $feeds = DB::table('library_kbart_feed')
            ->where('active', 1)
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->orderBy('id')
            ->get(['id', 'name', 'url']);

        $results = [];
        foreach ($feeds as $feed) {
            $results[] = $this->fetchSingleFeed((int) $feed->id, $feed->name, $feed->url);
        }

        return $results;
    }

    /**
     * Fetch and commit a single named feed, updating its last-run metadata.
     *
     * @param int    $feedId
     * @param string $name
     * @param string $url
     * @param string $rawTsv Fallback TSV passed directly (test / --once mode)
     * @return array{feed_id:int, name:string, url:string, status:string, row_count:int, error:string}
     */
    public function fetchSingleFeed(int $feedId, string $name, string $url, ?string $rawTsv = null): array
    {
        $lockKey = 'kbart_remote:' . $feedId;

        if (Cache::has($lockKey)) {
            Log::warning("KbartRemoteService: feed #{$feedId} ({$name}) is already being fetched — skipping.");
            return [
                'feed_id' => $feedId,
                'name' => $name,
                'url' => $url,
                'status' => 'skipped',
                'row_count' => 0,
                'error' => 'Concurrent fetch in progress.',
            ];
        }

        Cache::put($lockKey, true, self::LOCK_TTL_SECONDS);

        try {
            $raw = $rawTsv ?? $this->downloadFeed($url);

            if ($raw === null) {
                return $this->recordFailure($feedId, 'Download failed or empty response.');
            }

            $count = $this->kbart->writeImportBatch($raw);

            DB::table('library_kbart_feed')
                ->where('id', $feedId)
                ->update([
                    'last_fetch_at'     => now(),
                    'last_fetch_status' => 'success',
                    'last_row_count'    => (int) $count,
                    'last_error'       => null,
                ]);

            Log::info("KbartRemoteService: fetched feed #{$feedId} ({$name}) — {$count} rows written.");

            return [
                'feed_id' => $feedId,
                'name' => $name,
                'url' => $url,
                'status' => 'success',
                'row_count' => (int) $count,
                'error' => '',
            ];

        } catch (\Throwable $e) {
            Log::error("KbartRemoteService: fetch failed for feed #{$feedId} ({$name}) — {$e->getMessage()}");
            return $this->recordFailure($feedId, $e->getMessage(), $url);

        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * List all feed subscriptions with last-run metadata.
     *
     * @return \Illuminate\Support\Collection
     */
    public function listFeeds(): \Illuminate\Support\Collection
    {
        $this->ensureFeedTable();

        return DB::table('library_kbart_feed')
            ->orderBy('active', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Store a new or updated feed subscription.
     *
     * @param int|null $id          Existing feed id (null → insert)
     * @param array    $data        ['name', 'url', 'vendor', 'notes', 'active']
     * @return int Feed id (inserted or updated)
     */
    public function saveFeed(?int $id, array $data): int
    {
        $this->ensureFeedTable();

        $row = [
            'name'    => trim($data['name'] ?? ''),
            'url'     => trim($data['url'] ?? ''),
            'vendor'  => trim($data['vendor'] ?? '') ?: null,
            'notes'   => trim($data['notes'] ?? '') ?: null,
            'active'  => ! empty($data['active']) ? 1 : 0,
        ];

        if ($id) {
            DB::table('library_kbart_feed')->where('id', $id)->update($row);
            return $id;
        }

        $row['created_at'] = now();
        $row['updated_at'] = now();
        return (int) DB::table('library_kbart_feed')->insertGetId($row);
    }

    /**
     * Delete a feed subscription and its last-run metadata.
     *
     * @param int $id
     * @return bool True when deleted
     */
    public function deleteFeed(int $id): bool
    {
        $this->ensureFeedTable();
        return (bool) DB::table('library_kbart_feed')->where('id', $id)->delete();
    }

    /**
     * Toggle the active flag on a feed.
     *
     * @param int $id
     * @return bool New active state
     */
    public function toggleFeed(int $id): bool
    {
        $this->ensureFeedTable();
        $feed = DB::table('library_kbart_feed')->where('id', $id)->first(['active']);
        if (! $feed) {
            return false;
        }
        $new = $feed->active ? 0 : 1;
        DB::table('library_kbart_feed')->where('id', $id)->update(['active' => $new]);
        return (bool) $new;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Download raw TSV content from a remote URL.
     *
     * @param string $url
     * @return string|null Raw TSV text, or null on failure
     */
    private function downloadFeed(string $url): ?string
    {
        try {
            $response = Http::timeout(60)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'Heratio-' . config('app.version', '1.0') . ' (KBART-Feed-Fetcher)',
                    'Accept'     => 'text/tab-separated-values, text/plain, */*',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning("KbartRemoteService: HTTP {$response->status()} from {$url}");
                return null;
            }

            $body = trim($response->body());
            return $body !== '' ? $body : null;

        } catch (\Throwable $e) {
            Log::error("KbartRemoteService: fetch exception for {$url} — {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Record a fetch failure in the feed metadata table.
     *
     * @param int    $feedId
     * @param string $error
     * @param string $url
     * @return array
     */
    private function recordFailure(int $feedId, string $error, string $url = ''): array
    {
        DB::table('library_kbart_feed')
            ->where('id', $feedId)
            ->update([
                'last_fetch_at'     => now(),
                'last_fetch_status' => 'fail',
                'last_row_count'    => 0,
                'last_error'       => mb_substr($error, 0, 1000),
            ]);

        return [
            'feed_id' => $feedId,
            'name'    => DB::table('library_kbart_feed')->where('id', $feedId)->value('name') ?: (string) $feedId,
            'url'     => $url,
            'status'  => 'fail',
            'row_count' => 0,
            'error'   => $error,
        ];
    }
}
