<?php

/**
 * AhgCentralService - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Outbound client for the AHG Central cloud service (heratio#127 / #67).
 *
 * AHG Central (central.theahg.co.za) is the fleet registry + heartbeat +
 * error-monitoring service. This class is its client side. Onboarding is
 * zero-touch: a fresh install carries the fleet enrolment key in .env
 * (AHG_CENTRAL_API_KEY, seeded by bin/install -> config heratio.central),
 * derives its site_id from the hostname, and auto-enrols on its first
 * heartbeat - no operator registration step.
 *
 * Settings (ahg_settings, seeded by AhgCoreServiceProvider):
 *   - ahg_central_enabled        -> isEnabled() gates every outbound call
 *   - ahg_central_error_sync     -> errorSyncEnabled() gates error-sync only
 *   - ahg_central_api_url        -> apiUrl()   (falls back to config default)
 *   - ahg_central_api_key        -> apiKey()   (falls back to the .env key)
 *   - ahg_central_site_id        -> siteId()   (falls back to the hostname)
 *   - ahg_central_last_error_id  -> the error-sync watermark
 *
 * Central is advisory and best-effort - it must never gate local
 * functionality. Every method here fails soft.
 *
 * Endpoints on the cloud side ({api_url} = .../api/v1):
 *   GET  {api_url}/ping       -> liveness
 *   POST {api_url}/heartbeat  -> records this install alive + auto-enrols it
 *   POST {api_url}/errors     -> ingests a batch of redacted ahg_error_log rows
 */
class AhgCentralService
{
    /** Per-run cap on rows pulled from ahg_error_log into one POST. */
    private const ERROR_BATCH_MAX = 500;

    public function isEnabled(): bool
    {
        return $this->setting('ahg_central_enabled', '0') === '1';
    }

    /**
     * Error-sync is a *separate* opt-in on top of isEnabled(): error logs can
     * carry stack traces / PII, so shipping them off-box needs its own
     * explicit consent. Default off.
     */
    public function errorSyncEnabled(): bool
    {
        return $this->setting('ahg_central_error_sync', '0') === '1';
    }

    public function apiUrl(): string
    {
        $url = rtrim($this->setting('ahg_central_api_url', ''), '/');
        if ($url !== '') {
            return $url;
        }

        // Deploy default - config/heratio.php <- AHG_CENTRAL_API_URL.
        return rtrim((string) config('heratio.central.api_url', ''), '/');
    }

    public function apiKey(): string
    {
        $key = (string) $this->setting('ahg_central_api_key', '');
        if ($key !== '') {
            return $key;
        }

        // Deploy default - the shared fleet enrolment key from .env.
        return (string) config('heratio.central.api_key', '');
    }

    /**
     * Stable per-install identifier. Operator-set value wins; otherwise it is
     * auto-derived from the machine hostname so a fresh install registers
     * itself without anyone filling in the settings form.
     */
    public function siteId(): string
    {
        $id = (string) $this->setting('ahg_central_site_id', '');

        return $id !== '' ? $id : $this->defaultSiteId();
    }

    /** 'heratio-' + sanitised hostname - the auto-derived site_id. */
    public function defaultSiteId(): string
    {
        $host = strtolower((string) (gethostname() ?: 'unknown'));
        $host = preg_replace('/[^a-z0-9._-]/', '-', $host) ?: 'unknown';

        return 'heratio-' . $host;
    }

    /**
     * Synchronous reachability check. Returns ['ok'=>bool, 'http'=>int, ...].
     * Used by `ahg:central-ping` + the settings page Test-connection button.
     */
    public function ping(): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'http' => 0, 'error' => 'ahg_central_enabled is off'];
        }
        if ($this->apiUrl() === '') {
            return ['ok' => false, 'http' => 0, 'error' => 'ahg_central_api_url is empty'];
        }

        return $this->request('GET', '/ping');
    }

    /**
     * Daily heartbeat: tells Central this install is alive + on which version.
     * An unknown site_id presenting the fleet key auto-enrols on the Central
     * side - this is the zero-touch registration path. No-ops when disabled.
     */
    public function heartbeat(): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'http' => 0, 'error' => 'ahg_central_enabled is off'];
        }
        if ($this->apiUrl() === '' || $this->siteId() === '') {
            return ['ok' => false, 'http' => 0, 'error' => 'apiUrl or siteId is empty'];
        }

        $version = '';
        try {
            $version = (string) (json_decode((string) @file_get_contents(base_path('version.json')), true)['version'] ?? '');
        } catch (\Throwable $e) { /* version best-effort */ }

        return $this->request('POST', '/heartbeat', [
            'site_id'   => $this->siteId(),
            'version'   => $version,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Push the current open ahg_error_log rows to Central for fleet-wide
     * error visibility.
     *
     * Open errors only (resolved_at IS NULL) and a *full replace*: each run
     * sends the site's current open set and Central stores exactly that, so
     * resolving an error at source removes it from the fleet view on the next
     * run. Every text field is redacted (maskPii + URL query-string stripping)
     * before it leaves the building, and the PII-heavy columns (trace,
     * client_ip, user_agent, user_id, request_id) are never sent at all.
     *
     * Best-effort - returns a result array, never throws. Caller is the
     * scheduled AhgCentralSyncErrorsCommand.
     *
     * @return array{ok:bool,sent:int,error?:string,http?:int}
     */
    public function syncErrors(int $batch = 500): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'sent' => 0, 'error' => 'ahg_central_enabled is off'];
        }
        if (!$this->errorSyncEnabled()) {
            return ['ok' => false, 'sent' => 0, 'error' => 'ahg_central_error_sync is off'];
        }
        if ($this->apiUrl() === '' || $this->siteId() === '') {
            return ['ok' => false, 'sent' => 0, 'error' => 'apiUrl or siteId is empty'];
        }

        $cap = max(1, min($batch, self::ERROR_BATCH_MAX));

        try {
            // Open errors only - rows not yet resolved (resolved_at IS NULL),
            // most recent first.
            $rows = DB::table('ahg_error_log')
                ->whereNull('resolved_at')
                ->orderBy('id', 'desc')
                ->limit($cap)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[ahg-central] ahg_error_log read failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'sent' => 0, 'error' => 'ahg_error_log read failed'];
        }

        $payload = [];
        foreach ($rows as $r) {
            $payload[] = [
                'occurred_at'     => (string) ($r->created_at ?? ''),
                'level'           => (string) ($r->level ?? ''),
                'status_code'     => isset($r->status_code) && $r->status_code !== null ? (int) $r->status_code : null,
                'message'         => $this->redact((string) ($r->message ?? '')),
                'exception_class' => (string) ($r->exception_class ?? ''),
                'file'            => (string) ($r->file ?? ''),
                'line'            => isset($r->line) && $r->line !== null ? (int) $r->line : null,
                'url'             => $this->redact($this->stripQuery((string) ($r->url ?? ''))),
                'http_method'     => (string) ($r->http_method ?? ''),
                'hostname'        => (string) ($r->hostname ?? ''),
                // Stable dedup key on the Central side - the origin row id.
                'fingerprint'     => (string) $r->id,
            ];
        }

        // replace=true: Central drops this site's existing rows and stores
        // exactly the open set posted here. An empty set therefore clears the
        // site, which is correct when every error has been resolved.
        $result = $this->request('POST', '/errors', ['errors' => $payload, 'replace' => true]);
        if (!empty($result['ok'])) {
            return [
                'ok'   => true,
                'sent' => count($payload),
                'http' => (int) ($result['http'] ?? 0),
            ];
        }

        return [
            'ok'    => false,
            'sent'  => 0,
            'http'  => (int) ($result['http'] ?? 0),
            'error' => 'POST /errors returned non-2xx',
        ];
    }

    /**
     * Mask PII (emails + 9+-digit number runs) before a value leaves the
     * building. Reuses AhgAiServices\Services\GuardrailService::maskPii() when
     * that package is present; falls back to an equivalent inline pass so
     * redaction never silently no-ops.
     */
    private function redact(string $text): string
    {
        if ($text === '') {
            return '';
        }

        try {
            $guardrail = app(\AhgAiServices\Services\GuardrailService::class);
            [$masked] = $guardrail->maskPii($text);

            return (string) $masked;
        } catch (\Throwable $e) {
            // ahg-ai-services absent - inline equivalent of maskPii().
            $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[REDACTED:email]', $text) ?? $text;
            $text = preg_replace_callback(
                '/\+?\d[\d\s().-]{6,}\d/u',
                fn ($m) => strlen(preg_replace('/\D/', '', $m[0])) >= 9 ? '[REDACTED:number]' : $m[0],
                $text
            ) ?? $text;

            return $text;
        }
    }

    /** Drop the query string from a URL so tokens in ?params never ship. */
    private function stripQuery(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $q = strpos($url, '?');

        return $q === false ? $url : substr($url, 0, $q);
    }

    private function request(string $method, string $path, ?array $jsonBody = null): array
    {
        $url = $this->apiUrl() . $path;

        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->apiKey(),
            'X-Heratio-Site-Id: ' . $this->siteId(),
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CUSTOMREQUEST  => $method,
        ];
        if ($jsonBody !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody);
            $headers[] = 'Content-Type: application/json';
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Log::warning('[ahg-central] curl error', ['url' => $url, 'method' => $method, 'error' => $err]);

            return ['ok' => false, 'http' => 0, 'error' => $err];
        }

        $ok = $httpCode >= 200 && $httpCode < 300;
        if (!$ok) {
            Log::info('[ahg-central] non-2xx response', ['url' => $url, 'method' => $method, 'http' => $httpCode]);
        }

        return ['ok' => $ok, 'http' => $httpCode, 'response' => substr((string) $response, 0, 1024)];
    }

    /**
     * Read a key from ahg_settings (where the form saves them). Returns the
     * default when the row is missing or empty. The value column is plain text.
     */
    private function setting(string $key, string $default = ''): string
    {
        try {
            $val = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');

            return ($val === null || $val === '') ? $default : (string) $val;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /** Persist a key back to ahg_settings (used for the error-sync watermark). */
    private function putSetting(string $key, string $value): void
    {
        try {
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        } catch (\Throwable $e) {
            Log::warning('[ahg-central] could not persist ' . $key, ['error' => $e->getMessage()]);
        }
    }
}
