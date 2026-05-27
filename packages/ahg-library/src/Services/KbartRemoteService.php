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
                    `refresh_frequency` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'daily' COMMENT 'hourly | daily | weekly | monthly | cron expression',
                    `last_fetch_at` datetime DEFAULT NULL COMMENT 'ISO datetime of most recent fetch',
                    `last_fetch_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'success|fail|skipped',
                    `last_row_count` int unsigned DEFAULT 0 COMMENT 'rows written in most recent fetch',
                    `fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'sha256 of last fetched TSV body',
                    `last_error` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `last_diff` json DEFAULT NULL COMMENT 'snapshot of last successful import for diff detection',
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
        $columns = ['id', 'name', 'url'];
        if (Schema::hasColumn('library_kbart_feed', 'refresh_frequency')) $columns[] = 'refresh_frequency';
        if (Schema::hasColumn('library_kbart_feed', 'last_fetch_at'))     $columns[] = 'last_fetch_at';

        $feeds = DB::table('library_kbart_feed')
            ->where('active', 1)
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->orderBy('id')
            ->get($columns);

        $results = [];
        foreach ($feeds as $feed) {
            if (!$this->feedIsDue($feed)) {
                $results[] = [
                    'feed_id' => (int) $feed->id, 'name' => $feed->name, 'url' => $feed->url,
                    'status' => 'skipped', 'row_count' => 0,
                    'error' => 'Not yet due per refresh_frequency.',
                ];
                continue;
            }
            $results[] = $this->fetchSingleFeed((int) $feed->id, $feed->name, $feed->url);
        }

        return $results;
    }

    /**
     * Decide whether a feed is due for refresh based on its refresh_frequency
     * column and last_fetch_at. Frequencies supported:
     *   hourly | daily (default) | weekly | monthly | <cron expression>
     * For ad-hoc cron expressions, only the minute/hour/day-of-month/day-of-week
     * are evaluated for an "interval" approximation (we don't run the full
     * Cron parser - the scheduler-side cron runs us frequently anyway).
     */
    private function feedIsDue($feed): bool
    {
        $frequency = $feed->refresh_frequency ?? 'daily';
        if ($frequency === '' || $frequency === null) $frequency = 'daily';

        $last = $feed->last_fetch_at ?? null;
        if (!$last) return true; // never fetched - always due

        try {
            $lastTs = strtotime($last);
            if ($lastTs === false) return true;
        } catch (\Throwable) {
            return true;
        }

        $minInterval = match (strtolower($frequency)) {
            'hourly'   => 55 * 60,        // 55 min so a 5-min cron skew doesn't double-fire
            'daily'    => 23 * 3600,
            'weekly'   => 7 * 86400 - 1800,
            'monthly'  => 30 * 86400 - 1800,
            default    => 23 * 3600,
        };

        return (time() - $lastTs) >= $minInterval;
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
        $startMs = microtime(true);

        try {
            $raw = $rawTsv ?? $this->downloadFeed($url);

            if ($raw === null) {
                $this->logImport($feedId, 'fail', 0, 0, 0, 0, null, 'Download failed or empty response.', (int) round((microtime(true) - $startMs) * 1000));
                $this->notify('failure', $feedId, $name, 'Download failed or empty response.');
                return $this->recordFailure($feedId, 'Download failed or empty response.');
            }

            $fingerprint = hash('sha256', $raw);
            $diff = $this->computeDiff($feedId, $raw);

            // Short-circuit when nothing changed: still log + update metadata
            // but skip the writeImportBatch round-trip.
            $prevFingerprint = DB::table('library_kbart_feed')->where('id', $feedId)->value('fingerprint');
            if ($prevFingerprint === $fingerprint) {
                DB::table('library_kbart_feed')->where('id', $feedId)->update([
                    'last_fetch_at'     => now(),
                    'last_fetch_status' => 'skipped',
                    'last_error'       => null,
                ]);
                $this->logImport($feedId, 'skipped', 0, 0, 0, 0, $fingerprint, 'No change since last fetch.', (int) round((microtime(true) - $startMs) * 1000));
                return [
                    'feed_id' => $feedId, 'name' => $name, 'url' => $url,
                    'status' => 'skipped', 'row_count' => 0, 'error' => '',
                ];
            }

            $count = $this->kbart->writeImportBatch($raw);

            DB::table('library_kbart_feed')
                ->where('id', $feedId)
                ->update([
                    'last_fetch_at'     => now(),
                    'last_fetch_status' => 'success',
                    'last_row_count'    => (int) $count,
                    'fingerprint'      => $fingerprint,
                    'last_diff'        => json_encode($diff, JSON_UNESCAPED_UNICODE),
                    'last_error'       => null,
                ]);

            $this->logImport(
                $feedId, 'success', (int) $count,
                (int) $diff['added'], (int) $diff['removed'], (int) $diff['changed'],
                $fingerprint, null, (int) round((microtime(true) - $startMs) * 1000),
                $diff['sample'] ?? []
            );

            // Notify if there are added or removed titles - silent on identical refresh.
            if (($diff['added'] + $diff['removed']) > 0) {
                $this->notify('changes', $feedId, $name, sprintf(
                    'KBART: %s added %d new titles, removed %d titles.',
                    $name, $diff['added'], $diff['removed']
                ));
            }

            Log::info("KbartRemoteService: fetched feed #{$feedId} ({$name}) — {$count} rows written, +{$diff['added']} / -{$diff['removed']}.");

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
            $this->logImport($feedId, 'fail', 0, 0, 0, 0, null, $e->getMessage(), (int) round((microtime(true) - $startMs) * 1000));
            $this->notify('failure', $feedId, $name, $e->getMessage());
            return $this->recordFailure($feedId, $e->getMessage(), $url);

        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Compute add/remove/change counts for the new TSV body vs the previous diff stored on the feed row.
     * Identifiers used: title_id when present, else online_identifier, else print_identifier, else title.
     * Returns ['added','removed','changed','sample' => [[op,id,title], ...]].
     */
    private function computeDiff(int $feedId, string $raw): array
    {
        $newIds = $this->extractIdentifiers($raw);

        $prevSnapshot = DB::table('library_kbart_feed')
            ->where('id', $feedId)
            ->value('last_diff');
        $prevDecoded = $prevSnapshot ? json_decode($prevSnapshot, true) : null;
        $prevIds = (is_array($prevDecoded) && isset($prevDecoded['identifiers'])) ? $prevDecoded['identifiers'] : [];

        $addedKeys   = array_diff(array_keys($newIds), array_keys($prevIds));
        $removedKeys = array_diff(array_keys($prevIds), array_keys($newIds));
        $changedKeys = [];
        foreach ($newIds as $k => $title) {
            if (isset($prevIds[$k]) && $prevIds[$k] !== $title) $changedKeys[] = $k;
        }

        $sample = [];
        foreach (array_slice($addedKeys, 0, 5) as $k)   $sample[] = ['op' => 'add',    'id' => $k, 'title' => $newIds[$k]];
        foreach (array_slice($removedKeys, 0, 5) as $k) $sample[] = ['op' => 'remove', 'id' => $k, 'title' => $prevIds[$k] ?? ''];
        foreach (array_slice($changedKeys, 0, 5) as $k) $sample[] = ['op' => 'change', 'id' => $k, 'title' => $newIds[$k]];

        return [
            'added'       => count($addedKeys),
            'removed'     => count($removedKeys),
            'changed'     => count($changedKeys),
            'identifiers' => $newIds, // stored back into last_diff for next-fetch comparison
            'sample'      => $sample,
        ];
    }

    /**
     * Extract a {identifier -> title} map from a KBART TSV string.
     */
    private function extractIdentifiers(string $raw): array
    {
        $lines = preg_split('/\r?\n/', $raw);
        if (!$lines || count($lines) < 2) return [];

        $header = str_getcsv((string) $lines[0], "\t");
        $idx = array_flip(array_map('strtolower', $header));
        $titleCol = $idx['publication_title'] ?? 0;
        $titleIdCol = $idx['title_id'] ?? null;
        $onlineCol = $idx['online_identifier'] ?? null;
        $printCol = $idx['print_identifier'] ?? null;

        $out = [];
        for ($i = 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (trim($line) === '') continue;
            $cols = str_getcsv($line, "\t");
            $title = $cols[$titleCol] ?? '';
            $key = null;
            if ($titleIdCol !== null && !empty($cols[$titleIdCol])) $key = 'tid:' . $cols[$titleIdCol];
            elseif ($onlineCol !== null && !empty($cols[$onlineCol])) $key = 'oid:' . $cols[$onlineCol];
            elseif ($printCol  !== null && !empty($cols[$printCol]))  $key = 'pid:' . $cols[$printCol];
            else                                                       $key = 'tit:' . md5($title);
            $out[$key] = $title;
        }
        return $out;
    }

    /**
     * Write one row to library_kbart_import_log.
     */
    private function logImport(int $feedId, string $status, int $rowCount, int $added, int $removed, int $changed, ?string $fingerprint, ?string $error, int $elapsedMs, array $diffSample = []): void
    {
        try {
            if (!Schema::hasTable('library_kbart_import_log')) return;
            DB::table('library_kbart_import_log')->insert([
                'feed_id'     => $feedId,
                'status'      => $status,
                'row_count'   => $rowCount,
                'added'       => $added,
                'removed'     => $removed,
                'changed'     => $changed,
                'fingerprint' => $fingerprint,
                'error'       => $error,
                'diff_sample' => json_encode($diffSample, JSON_UNESCAPED_UNICODE),
                'elapsed_ms'  => $elapsedMs,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('KbartRemoteService: import-log insert failed: ' . $e->getMessage());
        }
    }

    /**
     * Surface a KBART event to the bell-style notification bus.
     */
    private function notify(string $kind, int $feedId, string $name, string $message): void
    {
        try {
            if (!Schema::hasTable('ahg_notification')) return;
            DB::table('ahg_notification')->insert([
                'recipient_role' => 'librarian',
                'subject'        => '[KBART] ' . $name . ' (' . $kind . ')',
                'body'           => $message,
                'metadata'       => json_encode(['feed_id' => $feedId, 'kind' => $kind], JSON_UNESCAPED_UNICODE),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('KbartRemoteService: notification insert failed: ' . $e->getMessage());
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
