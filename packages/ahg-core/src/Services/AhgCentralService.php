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
 * Outbound client for the optional "AHG Central" cloud-side aggregator.
 *
 * Closes #67 (the four ahg_central_* settings now have a real consumer):
 *   - ahg_central_enabled  -> isEnabled() gates every outbound call AND the
 *                             daily heartbeat schedule
 *   - ahg_central_api_url  -> apiUrl() / endpoint() build target URLs
 *   - ahg_central_api_key  -> apiKey() carried as Authorization Bearer
 *   - ahg_central_site_id  -> siteId() included in heartbeat + sync payloads
 *
 * The cloud-side API is a forward-looking surface - if the operator turns
 * the toggle on with a URL that doesn't have a Heratio Central instance
 * behind it, the calls will return 4xx/5xx and get logged. That's fine:
 * the SETTING wiring is the contract this issue is closing, not the
 * cloud-side service availability.
 *
 * The endpoints expected on the cloud side are documented inline:
 *   GET  {api_url}/ping              -> 200 OK with { ok: true }
 *   POST {api_url}/heartbeat         -> records site is alive (siteId+version)
 *   POST {api_url}/sync/descriptions -> bulk push of description metadata
 */
class AhgCentralService
{
    public function isEnabled(): bool
    {
        return $this->setting('ahg_central_enabled', '0') === '1';
    }

    public function apiUrl(): string
    {
        return rtrim($this->setting('ahg_central_api_url', ''), '/');
    }

    public function apiKey(): string
    {
        return (string) $this->setting('ahg_central_api_key', '');
    }

    public function siteId(): string
    {
        return (string) $this->setting('ahg_central_site_id', '');
    }

    /**
     * Synchronous reachability check. Returns ['ok'=>bool, 'http'=>int, 'error'=>?string].
     * Used by the artisan ahg:central-ping command + the settings page's
     * Test-connection button.
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
     * Daily heartbeat: lets the cloud aggregator know this Heratio instance
     * is alive + which version. Caller is the scheduled
     * AhgCentralHeartbeatCommand. Skips silently when the toggle is off
     * (the schedule still fires but the body returns immediately).
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
            CURLOPT_TIMEOUT        => 10,
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
     * Read a key from ahg_settings (where the form saves them, per
     * SettingsController::ahgIntegration). Returns the default when the
     * row is missing or empty. The value column is plain text.
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
}
