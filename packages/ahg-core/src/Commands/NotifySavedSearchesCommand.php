<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NotifySavedSearchesCommand — runs every saved_search whose owner has
 * notify_on_new=1 and whose notification_frequency matches the --frequency
 * argument. For each, re-executes the search via Elasticsearch (best
 * effort) and notifies the user when the result count has grown since
 * last_result_count.
 *
 * Notification path: drops a JSON file into /var/spool/workbench/notifications/
 * per the existing Workbench notification spool contract (see global
 * CLAUDE.md). Email is best-effort via the user's Mailable when configured
 * — defaults to skipping email entirely.
 *
 * Run via cron: `php artisan ahg:notify-saved-searches --frequency=daily`
 * at 08:00 UTC + `--frequency=weekly` on Monday 08:00 UTC. Cron entries
 * are registered in CronSchedulerService getDefaultSchedules().
 *
 * Phase 2 of #650 (search ecosystem gaps audit).
 */
class NotifySavedSearchesCommand extends Command
{
    protected $signature = 'ahg:notify-saved-searches
        {--frequency=daily : Filter saved_search.notification_frequency value (daily, weekly, monthly, immediate)}
        {--dry-run : Show notifications without dropping JSON or updating last_notification_at}
        {--user= : Limit to a specific user_id (debugging)}';

    protected $description = 'Re-run saved searches and notify owners when new matches appear since the previous run.';

    public function handle(): int
    {
        $freq = (string) $this->option('frequency');
        $dry = (bool) $this->option('dry-run');
        $onlyUser = $this->option('user');

        if (!Schema::hasTable('saved_search')) {
            $this->warn('saved_search table not present; skipping.');
            return self::SUCCESS;
        }

        // Filter window: don't notify the same row twice within its
        // frequency. Cutoff computed in PHP so we avoid MySQL-specific
        // INTERVAL syntax (the column compares as standard datetime).
        // Approximate windows — daily=22h (DST safety), weekly=6d, monthly=27d.
        $windowMap = [
            'immediate' => 60,                  // 1 minute
            'daily'     => 22 * 3600,           // 22 hours
            'weekly'    => 6 * 24 * 3600,       // 6 days
            'monthly'   => 27 * 24 * 3600,      // 27 days
        ];
        $cutoff = date('Y-m-d H:i:s', time() - ($windowMap[$freq] ?? $windowMap['daily']));

        $q = DB::table('saved_search')
            ->where('notify_on_new', 1)
            ->where('notification_frequency', $freq)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_notification_at')
                  ->orWhere('last_notification_at', '<', $cutoff);
            });
        if ($onlyUser) {
            $q->where('user_id', (int) $onlyUser);
        }
        $rows = $q->select('id', 'user_id', 'name', 'search_params', 'entity_type',
                          'search_url', 'last_result_count', 'last_notification_at')
            ->orderBy('id')
            ->get();

        $this->info("saved searches due (frequency={$freq}): {$rows->count()}");

        $notified = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $params = $this->decodeParams($row->search_params);
            if (!$params) {
                $this->line("  saved_search={$row->id}: invalid search_params, skipping");
                $skipped++;
                continue;
            }

            // Re-run the search via ElasticsearchService when available.
            // When ES is down or the search params are unusable, fall back
            // to skipping this row (don't bury the user in failure pings).
            $currentCount = $this->reExecuteSearch($params, (string) $row->entity_type);
            if ($currentCount === null) {
                $this->line("  saved_search={$row->id}: ES unavailable or query failed, skipping");
                $skipped++;
                continue;
            }

            $prevCount = (int) ($row->last_result_count ?? 0);
            $newMatches = max(0, $currentCount - $prevCount);

            if ($newMatches === 0) {
                // Update last_result_count even when nothing new (track decay)
                if (!$dry) {
                    DB::table('saved_search')->where('id', $row->id)
                        ->update(['last_result_count' => $currentCount]);
                }
                $this->line("  saved_search={$row->id}: no new matches (count={$currentCount})");
                continue;
            }

            // New matches present — notify
            if (!$dry) {
                $this->dropWorkbenchNotification($row, $currentCount, $newMatches);
                DB::table('saved_search')->where('id', $row->id)
                    ->update([
                        'last_notification_at' => now(),
                        'last_result_count'    => $currentCount,
                    ]);
                // Log the notification
                DB::table('saved_search_log')->insert([
                    'saved_search_id'  => $row->id,
                    'user_id'          => $row->user_id,
                    'result_count'     => $currentCount,
                    'executed_at'      => now(),
                    'user_agent'       => 'cron:notify-saved-searches',
                ]);
            }
            $notified++;
            $this->info("  saved_search={$row->id} user={$row->user_id} '{$row->name}': +{$newMatches} new (total={$currentCount})");
        }
        $this->info("notified={$notified} skipped={$skipped}" . ($dry ? ' [dry-run]' : ''));
        return self::SUCCESS;
    }

    private function decodeParams($raw): ?array
    {
        if (!$raw) return null;
        $decoded = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: null);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Re-execute the saved search. Returns the total match count or null
     * on failure. The intent is just to detect WHETHER new matches exist
     * — full result hydration is the user's job when they click through.
     */
    private function reExecuteSearch(array $params, string $entityType): ?int
    {
        try {
            // Only support the simplest case for now: a `q` text query.
            // Advanced filters (facets, date range, repository) require
            // mirroring the SearchController query builder — deferred to
            // a follow-up phase.
            $q = trim((string) ($params['q'] ?? $params['query'] ?? ''));
            if (!$q) {
                return null;
            }
            if (!class_exists(\AhgSearch\Services\ElasticsearchService::class)) {
                return null;
            }
            $es = new \AhgSearch\Services\ElasticsearchService();
            // Index conventionally is "qubit{entityType}" per existing
            // ElasticsearchService::search() callers
            $index = $this->indexFor($entityType);
            $body = [
                'query' => [
                    'multi_match' => [
                        'query'  => $q,
                        'fields' => ['*'],
                        'type'   => 'best_fields',
                    ],
                ],
                'track_total_hits' => true,
            ];
            $hits = $es->search($index, $body, 0, 1);
            // ElasticsearchService::search returns the raw ES response shape
            $total = $hits['hits']['total']['value'] ?? $hits['hits']['total'] ?? null;
            return is_numeric($total) ? (int) $total : null;
        } catch (\Throwable $exc) {
            return null;
        }
    }

    private function indexFor(string $entityType): string
    {
        return match ($entityType) {
            'actor'             => 'qubitactor',
            'repository'        => 'qubitrepository',
            'function'          => 'qubitfunction',
            'term'              => 'qubitterm',
            'accession'         => 'qubitaccession',
            default             => 'qubitinformationobject',
        };
    }

    /**
     * Drop a JSON notification into /var/spool/workbench/notifications/.
     * Fails silently when the inbox isn't present (e.g. on a test box).
     */
    private function dropWorkbenchNotification(object $row, int $currentCount, int $newMatches): void
    {
        $inboxRaw = getenv('WORKBENCH_NOTIFICATIONS_INBOX');
        $inbox = $inboxRaw ?: '/var/spool/workbench/notifications';
        if (!is_dir($inbox) || !is_writable($inbox)) {
            return;
        }
        $username = DB::table('user')->where('id', $row->user_id)->value('username')
            ?: ('user-' . $row->user_id);
        $webLink = $row->search_url ?: '/search';
        $payload = [
            'username'  => $username,
            'title'     => "{$newMatches} new result(s): " . substr((string) $row->name, 0, 100),
            'message'   => "Your saved search \"{$row->name}\" has {$newMatches} new match(es). Total now: {$currentCount}.",
            'eventType' => 'saved_search',
            'webLink'   => $webLink,
        ];
        $fname = sprintf('%s/saved-search-%d-%d.json', $inbox, $row->id, time());
        @file_put_contents($fname, json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n");
    }
}
