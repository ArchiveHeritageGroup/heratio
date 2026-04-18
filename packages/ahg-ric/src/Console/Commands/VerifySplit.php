<?php

/**
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace AhgRic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end smoke test for the RiC split. Exercises every public endpoint
 * via config('ric.api_url') (external service) or app.url (in-process). On
 * external mode, uses config('ric.service_key') via X-API-Key.
 *
 * Write tests create-and-delete a throwaway Place so the DB state is
 * restored after a successful run. If a write fails midway, the Place may
 * linger — grep `name="ric:verify-split smoke test"` to find and delete.
 *
 * Usage:
 *   php artisan ric:verify-split               # uses env config
 *   php artisan ric:verify-split --no-writes  # reads only (safe in prod)
 *   php artisan ric:verify-split --base=https://ric.theahg.co.za/api/ric/v1 \
 *                                 --key=xxxx    # override env for ad-hoc tests
 */
class VerifySplit extends Command
{
    protected $signature = 'ric:verify-split
                            {--base= : override base URL (default: config ric.api_url or app.url + /api/ric/v1)}
                            {--key= : override API key (default: config ric.service_key)}
                            {--no-writes : skip the create/delete tests}';

    protected $description = 'Smoke-test every RiC API endpoint and report pass/fail.';

    private string $base;
    private ?string $key;
    private array $results = [];

    public function handle(): int
    {
        $this->base = rtrim(
            $this->option('base')
                ?: (config('ric.api_url') ?: rtrim(config('app.url'), '/') . '/api/ric/v1'),
            '/'
        );
        $this->key = $this->option('key') ?: config('ric.service_key');

        $this->info("Base URL: {$this->base}");
        $this->info('Mode: ' . ($this->key ? 'external (X-API-Key)' : 'in-process (session-less — reads only unless RIC_SERVICE_API_KEY is set)'));
        $this->line('');

        // READS
        $this->check('health', 'GET', '/health');
        $this->check('vocabulary', 'GET', '/vocabulary');
        $this->check('vocabulary/ric_place_type', 'GET', '/vocabulary/ric_place_type', fn($b) => !empty($b['items'] ?? []));
        $this->check('autocomplete?q=egypt', 'GET', '/autocomplete?q=egypt&limit=3', fn($b) => is_array($b));
        $this->check('places (list)', 'GET', '/places?per_page=3', fn($b) => is_array($b));
        $this->check('places/flat', 'GET', '/places/flat', fn($b) => isset($b['items']));
        $this->check('relations (list)', 'GET', '/relations?per_page=3', fn($b) => isset($b['data']));
        $this->check('relation-types', 'GET', '/relation-types', fn($b) => isset($b['items']));

        // Known-id probes — best-effort. If the server has no Place id 912150 these will fail gracefully.
        $this->check('hierarchy/912150', 'GET', '/hierarchy/912150');
        $this->check('entities/912150/info', 'GET', '/entities/912150/info');
        $this->check('relations-for/912150', 'GET', '/relations-for/912150', fn($b) => isset($b['outgoing']));
        $this->check('graph?uri=/place/912150', 'GET', '/graph?uri=/place/912150&depth=1', fn($b) => !empty($b['openric:nodes'] ?? []));

        // WRITES — create + delete a Place.
        if (!$this->option('no-writes')) {
            if (!$this->key) {
                $this->warn('Skipping writes: no API key. Pass --key=… or set RIC_SERVICE_API_KEY.');
            } else {
                $label = 'ric:verify-split smoke test ' . now()->toIso8601String();
                $created = $this->call_api('POST', '/places', ['name' => $label, 'latitude' => 0, 'longitude' => 0]);
                $placeId = $created['body']['id'] ?? null;
                $this->record('POST /places', $created['status'], $placeId !== null);
                if ($placeId) {
                    // Patch it.
                    $patched = $this->call_api('PATCH', "/places/{$placeId}", ['name' => $label . ' (patched)']);
                    $this->record("PATCH /places/{$placeId}", $patched['status'], ($patched['body']['success'] ?? false) === true);
                    // Delete it.
                    $deleted = $this->call_api('DELETE', "/places/{$placeId}");
                    $this->record("DELETE /places/{$placeId}", $deleted['status'], ($deleted['body']['success'] ?? false) === true);
                }
            }
        }

        // Report
        $this->line('');
        $this->line(str_pad('endpoint', 45) . str_pad('status', 10) . 'ok?');
        $this->line(str_repeat('-', 65));
        $pass = 0; $fail = 0;
        foreach ($this->results as $r) {
            $this->line(
                str_pad(substr($r['label'], 0, 44), 45) .
                str_pad((string) $r['status'], 10) .
                ($r['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>')
            );
            $r['ok'] ? $pass++ : $fail++;
        }
        $this->line('');
        $this->line("Result: <fg=green>{$pass} pass</>, <fg=red>{$fail} fail</>.");
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $label, string $method, string $path, ?callable $bodyOk = null): void
    {
        $resp = $this->call_api($method, $path);
        $ok = $resp['status'] >= 200 && $resp['status'] < 300 && ($bodyOk === null || $bodyOk($resp['body']));
        $this->record("{$method} {$path}", $resp['status'], $ok);
    }

    private function record(string $label, int $status, bool $ok): void
    {
        $this->results[] = compact('label', 'status', 'ok');
    }

    private function call_api(string $method, string $path, array $body = []): array
    {
        try {
            $client = Http::timeout(10)->acceptJson();
            if ($this->key) $client = $client->withHeaders(['X-API-Key' => $this->key]);

            $url = $this->base . $path;
            $resp = match (strtolower($method)) {
                'get' => $client->get($url),
                'post' => $client->post($url, $body),
                'patch' => $client->asJson()->patch($url, $body),
                'delete' => $client->delete($url),
            };
            return ['status' => $resp->status(), 'body' => $resp->json() ?? []];
        } catch (\Throwable $e) {
            return ['status' => 0, 'body' => ['error' => $e->getMessage()]];
        }
    }
}
