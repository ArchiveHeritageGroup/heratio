<?php

/**
 * MonitorCommand - heratio#1742: built-in checks with alerts, so a site needs
 * no separate monitoring stack to hear that it is down, full or stuck.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\SystemAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Runs from the scheduler (every 5 minutes), outside the web server, so it
 * still reports when the website itself has stopped answering. Each check
 * alerts once when it fails, once when it recovers, and daily while it stays
 * failed (SystemAlerts).
 *
 * ponytail: a machine that is off or has lost its network cannot report
 * itself; an outside ping (any uptime service on /up) covers that.
 */
class MonitorCommand extends Command
{
    protected $signature = 'ahg:monitor {--dry-run : Show the checks, send nothing}';

    protected $description = 'Check the website, disk space, database, search engine and job queue; alert on problems';

    public function handle(): int
    {
        $results = [
            'web' => $this->web(),
            'database' => $this->database(),
            'search' => $this->search(),
            'queue' => $this->queue(),
        ] + $this->disks();

        $bad = 0;
        foreach ($results as $check => [$ok, $detail]) {
            $this->line(sprintf('%-40s %s  %s', $check, $ok ? 'ok  ' : 'FAIL', $detail));
            $bad += $ok ? 0 : 1;
            if (! $this->option('dry-run')) {
                SystemAlerts::report($check, $ok, $detail, \Illuminate\Support\Facades\Route::has('settings.index') ? route('settings.index') : null);
            }
        }

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0: bool, 1: string} */
    private function web(): array
    {
        $url = rtrim((string) (AhgSettingsService::get('monitoring_url', '') ?: config('app.url')), '/').'/up';
        try {
            $r = Http::timeout(15)->withoutVerifying()->get($url);

            return [$r->status() === 200, $url.' answered '.$r->status()];
        } catch (\Throwable $e) {
            return [false, $url.' did not answer: '.mb_substr($e->getMessage(), 0, 200)];
        }
    }

    private function database(): array
    {
        try {
            $t = microtime(true);
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $t) * 1000);

            return [$ms < 5000, "answered in {$ms} ms"];
        } catch (\Throwable $e) {
            return [false, mb_substr($e->getMessage(), 0, 200)];
        }
    }

    private function search(): array
    {
        $host = rtrim((string) config('services.elasticsearch.host', 'http://localhost:9200'), '/');
        try {
            $status = (string) Http::timeout(10)->get($host.'/_cluster/health')->json('status');

            return [in_array($status, ['green', 'yellow'], true), "cluster status {$status}"];
        } catch (\Throwable $e) {
            return [false, $host.' did not answer'];
        }
    }

    private function queue(): array
    {
        if (config('queue.default') !== 'database' || ! Schema::hasTable('jobs')) {
            return [true, 'not a database queue'];
        }
        $mins = max(5, AhgSettingsService::getInt('monitoring_queue_max_wait_minutes', 30));
        $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
        $waiting = $oldest ? (int) floor((time() - (int) $oldest) / 60) : 0;
        $count = DB::table('jobs')->whereNull('reserved_at')->count();

        return [$waiting < $mins, $count." waiting, oldest {$waiting} min (alert at {$mins})"];
    }

    /** @return array<string, array{0: bool, 1: string}> */
    private function disks(): array
    {
        $min = max(1, AhgSettingsService::getInt('monitoring_disk_min_free_percent', 10));
        $out = [];
        $seen = [];
        foreach (array_filter([storage_path(), config('heratio.storage_path'), config('heratio.uploads_path'), config('heratio.backups_path')]) as $path) {
            $path = (string) $path;
            if (! is_dir($path)) {
                continue;
            }
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if (! $total || $free === false) {
                continue;
            }
            $device = @stat($path)['dev'] ?? $path;
            if (isset($seen[$device])) {
                continue; // one alert per volume
            }
            $seen[$device] = true;
            $pct = round($free / $total * 100, 1);
            $out['disk:'.$path] = [$pct >= $min, sprintf('%s%% free (%s GB of %s GB; alert below %d%%)', $pct, round($free / 1e9, 1), round($total / 1e9, 1), $min)];
        }

        return $out;
    }
}
