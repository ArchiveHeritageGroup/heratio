<?php

/**
 * TriposrHealthCommand — ping the configured TripoSR API and report status.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Ahg3dModel\Commands;

use AhgSettings\Services\AhgSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TriposrHealthCommand extends Command
{
    protected $signature = 'ahg:triposr-health {--json}';

    protected $description = 'Check TripoSR API health';

    public function handle(): int
    {
        $mode = AhgSettingsService::get('triposr_mode', 'local');
        $url = $mode === 'remote'
            ? AhgSettingsService::get('triposr_remote_url', '')
            : AhgSettingsService::get('triposr_api_url', 'http://127.0.0.1:5050');
        $apiKey = $mode === 'remote' ? AhgSettingsService::get('triposr_remote_api_key', '') : null;
        $enabled = AhgSettingsService::getBool('triposr_enabled', false);
        $timeout = max(1, (int) AhgSettingsService::get('triposr_timeout', 10));

        $report = [
            'enabled' => $enabled,
            'mode' => $mode,
            'url' => $url,
            'reachable' => false,
            'status_code' => null,
            'latency_ms' => null,
            'error' => null,
        ];

        if (! $url) {
            $report['error'] = 'no URL configured';
            return $this->emit($report);
        }

        $health = rtrim($url, '/') . '/health';
        $request = Http::timeout(min($timeout, 10));
        if ($apiKey) $request = $request->withHeaders(['X-API-Key' => $apiKey]);
        try {
            $start = microtime(true);
            $resp = $request->get($health);
            $report['latency_ms'] = (int) round((microtime(true) - $start) * 1000);
            $report['status_code'] = $resp->status();
            $report['reachable'] = $resp->ok();
            if (! $resp->ok()) $report['error'] = 'HTTP ' . $resp->status();
        } catch (\Throwable $e) {
            $report['error'] = $e->getMessage();
        }

        return $this->emit($report);
    }

    protected function emit(array $r): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($r, JSON_PRETTY_PRINT));
        } else {
            $this->info('=== TripoSR health ===');
            foreach ($r as $k => $v) $this->line(sprintf('  %-12s %s', $k, is_bool($v) ? ($v ? 'true' : 'false') : (string) $v));
        }
        return ($r['reachable'] && $r['enabled']) ? self::SUCCESS : self::FAILURE;
    }
}
