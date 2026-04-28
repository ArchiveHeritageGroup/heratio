<?php

/**
 * TriposrPreloadCommand — warm up the TripoSR worker so first request isn't a cold start.
 *
 * Hits the /preload endpoint (server-side keeps weights in VRAM) once it's
 * reachable. Useful as a service-startup hook or scheduled keep-alive.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Ahg3dModel\Commands;

use AhgSettings\Services\AhgSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TriposrPreloadCommand extends Command
{
    protected $signature = 'ahg:triposr-preload {--retries=3}';

    protected $description = 'Preload TripoSR model into memory (warm-up call to /preload)';

    public function handle(): int
    {
        if (! AhgSettingsService::getBool('triposr_enabled', false)) {
            $this->warn('TripoSR is disabled in settings; nothing to preload.');
            return self::SUCCESS;
        }

        $mode = AhgSettingsService::get('triposr_mode', 'local');
        $url = $mode === 'remote'
            ? AhgSettingsService::get('triposr_remote_url', '')
            : AhgSettingsService::get('triposr_api_url', 'http://127.0.0.1:5050');
        $apiKey = $mode === 'remote' ? AhgSettingsService::get('triposr_remote_api_key', '') : null;
        if (! $url) { $this->error('TripoSR URL not configured'); return self::FAILURE; }

        $endpoint = rtrim($url, '/') . '/preload';
        $retries = max(1, (int) $this->option('retries'));

        for ($i = 1; $i <= $retries; $i++) {
            try {
                $req = Http::timeout(60);
                if ($apiKey) $req = $req->withHeaders(['X-API-Key' => $apiKey]);
                $resp = $req->post($endpoint);
                if ($resp->ok()) {
                    $body = $resp->json() ?: [];
                    $this->info("preloaded ({$endpoint}): " . ($body['status'] ?? 'ok'));
                    return self::SUCCESS;
                }
                $this->warn("attempt {$i}/{$retries}: HTTP {$resp->status()}");
            } catch (\Throwable $e) {
                $this->warn("attempt {$i}/{$retries}: " . $e->getMessage());
            }
            if ($i < $retries) sleep(min(5 * $i, 15));
        }
        $this->error("preload failed after {$retries} attempts");
        return self::FAILURE;
    }
}
