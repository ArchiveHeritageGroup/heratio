<?php

/**
 * ServicesCheckCommand — probe every dependent service Heratio relies on
 * and report up/down. Designed for cron + alerting:
 *
 *   - exit 0 if everything that's *enabled* is reachable
 *   - exit 1 if any enabled service fails
 *   - --json for machine consumption
 *   - --alert to send a flash to ahg_alert_log when anything is down
 *
 * Probes (each is best-effort — missing config is treated as "skipped",
 * never as a failure):
 *   MySQL                — heratio default connection ping
 *   Elasticsearch        — GET /
 *   Qdrant               — GET /readyz (vector search)
 *   Ollama (embedding)   — GET /api/tags  (semantic_embedding_url)
 *   Ollama (image LLM)   — GET /api/tags  (voice_local_llm_url)
 *   TripoSR              — GET /health    (when triposr_enabled=1)
 *   IIIF (Cantaloupe)    — GET /iiif/3    (when iiif_server_url set)
 *   AI condition service — GET /health    (when ai_condition_service_url set)
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgSettings\Commands;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ServicesCheckCommand extends Command
{
    protected $signature = 'ahg:services-check
        {--json : Emit machine-readable JSON instead of a table}
        {--alert : Insert a row into ahg_alert_log for each unhealthy service}
        {--timeout=5 : HTTP probe timeout (seconds)}';

    protected $description = 'Check all system services and report up/down';

    public function handle(): int
    {
        $timeout = max(1, (int) $this->option('timeout'));
        $checks = [
            $this->probeMysql(),
            $this->probeHttp('elasticsearch', config('services.elasticsearch.host', env('ELASTICSEARCH_HOST', 'http://localhost:9200')), '/', $timeout),
            $this->probeHttp('qdrant',        AhgSettingsService::get('semantic_qdrant_url', 'http://localhost:6333'), '/readyz', $timeout),
            $this->probeHttp('ollama_embed',  AhgSettingsService::get('semantic_embedding_url'), '/api/tags', $timeout),
            $this->probeHttp('ollama_image',  AhgSettingsService::get('voice_local_llm_url'),    '/api/tags', $timeout),
            $this->probeTriposr($timeout),
            $this->probeHttp('iiif',          AhgSettingsService::get('iiif_server_url'),        '/iiif/3', $timeout),
            $this->probeHttp('ai_condition',  AhgSettingsService::get('ai_condition_service_url'), '/health', $timeout),
        ];

        $checks = array_values(array_filter($checks));

        if ($this->option('alert')) $this->logAlerts($checks);

        $unhealthy = array_filter($checks, fn ($c) => $c['status'] === 'down');

        if ($this->option('json')) {
            $this->line(json_encode([
                'checked_at' => now()->toIso8601String(),
                'unhealthy_count' => count($unhealthy),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->renderTable($checks);
        }

        return empty($unhealthy) ? self::SUCCESS : self::FAILURE;
    }

    protected function probeMysql(): array
    {
        $t0 = microtime(true);
        try {
            DB::connection()->getPdo()->query('SELECT 1')->fetchAll();
            return $this->ok('mysql', config('database.default'), $t0);
        } catch (\Throwable $e) {
            return $this->down('mysql', config('database.default'), $e->getMessage(), $t0);
        }
    }

    protected function probeHttp(string $name, ?string $url, string $path, int $timeout): ?array
    {
        if (! $url) return $this->skipped($name, 'not configured');
        $endpoint = rtrim($url, '/') . $path;
        $t0 = microtime(true);
        try {
            $resp = Http::timeout($timeout)->get($endpoint);
            return $resp->successful()
                ? $this->ok($name, $endpoint, $t0, ['http_code' => $resp->status()])
                : $this->down($name, $endpoint, 'HTTP ' . $resp->status(), $t0);
        } catch (\Throwable $e) {
            return $this->down($name, $endpoint, $e->getMessage(), $t0);
        }
    }

    protected function probeTriposr(int $timeout): ?array
    {
        if (! AhgSettingsService::getBool('triposr_enabled', false)) {
            return $this->skipped('triposr', 'disabled in settings');
        }
        $mode = AhgSettingsService::get('triposr_mode', 'local');
        $url = $mode === 'remote'
            ? AhgSettingsService::get('triposr_remote_url')
            : AhgSettingsService::get('triposr_api_url', 'http://127.0.0.1:5050');
        return $this->probeHttp('triposr', $url, '/health', $timeout);
    }

    protected function ok(string $name, string $target, float $t0, array $extra = []): array
    {
        return ['service' => $name, 'status' => 'up', 'target' => $target,
                'latency_ms' => $this->ms($t0), 'detail' => null] + $extra;
    }

    protected function down(string $name, string $target, string $reason, float $t0): array
    {
        return ['service' => $name, 'status' => 'down', 'target' => $target,
                'latency_ms' => $this->ms($t0), 'detail' => $reason];
    }

    protected function skipped(string $name, string $why): array
    {
        return ['service' => $name, 'status' => 'skipped', 'target' => '—', 'latency_ms' => null, 'detail' => $why];
    }

    protected function ms(float $t0): int
    {
        return (int) round((microtime(true) - $t0) * 1000);
    }

    protected function logAlerts(array $checks): void
    {
        if (! Schema::hasTable('ahg_alert_log')) return;
        $down = array_filter($checks, fn ($c) => $c['status'] === 'down');
        foreach ($down as $c) {
            DB::table('ahg_alert_log')->insert([
                'severity' => 'error',
                'source' => 'services-check',
                'message' => "{$c['service']} unreachable: {$c['detail']}",
                'context' => json_encode(['target' => $c['target'], 'latency_ms' => $c['latency_ms']]),
                'created_at' => now(),
            ]);
        }
    }

    protected function renderTable(array $checks): void
    {
        $this->info(sprintf('=== services-check %s ===', now()->toDateTimeString()));
        foreach ($checks as $c) {
            $tag = match ($c['status']) {
                'up'      => '<info>UP     </info>',
                'down'    => '<error>DOWN   </error>',
                'skipped' => '<comment>SKIP   </comment>',
            };
            $latency = $c['latency_ms'] !== null ? sprintf('%4dms', $c['latency_ms']) : '   --';
            $detail = $c['detail'] ? "  ({$c['detail']})" : '';
            $this->line(sprintf('  %s %-16s %s  %s%s', $tag, $c['service'], $latency, $c['target'], $detail));
        }
        $down = count(array_filter($checks, fn ($c) => $c['status'] === 'down'));
        if ($down > 0) $this->error("{$down} service(s) unhealthy");
        else $this->info('all enabled services healthy');
    }
}
