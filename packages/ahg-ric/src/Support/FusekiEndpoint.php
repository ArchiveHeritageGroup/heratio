<?php

/**
 * FusekiEndpoint - where the RiC triple store is, and whether it answers.
 *
 * Shared by the RiC dashboard's sync readiness check and the
 * ahg:ric:fuseki-sync-default command, so both resolve the endpoint and
 * ping it the same way. Bound in the container so tests can swap the ping.
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

namespace AhgRic\Support;

use AhgCore\Services\SecretCrypto;
use Illuminate\Support\Facades\DB;

class FusekiEndpoint
{
    /**
     * Resolve the Fuseki base URL, dataset and credentials.
     *
     * .env-backed config (RIC_FUSEKI_*) wins; when it is empty, fall back to
     * the RiC settings (group 'fuseki', fuseki_endpoint = full dataset URL
     * http://host:port/<dataset>), which the rest of the package reads (#1421).
     *
     * @return array{url: ?string, dataset: ?string, user: ?string, pass: ?string}
     */
    public function resolve(): array
    {
        $url = config('ahg-ric.fuseki.url');
        $dataset = config('ahg-ric.fuseki.dataset');
        $user = config('ahg-ric.fuseki.user');
        $pass = config('ahg-ric.fuseki.pass');

        if (empty($url) || empty($dataset)) {
            $settings = $this->settings();
            $endpoint = trim((string) ($settings['fuseki_endpoint'] ?? ''));
            if ($endpoint !== '') {
                $parts = parse_url($endpoint);
                if (empty($url) && ! empty($parts['host'])) {
                    $url = ($parts['scheme'] ?? 'http').'://'.$parts['host']
                        .(isset($parts['port']) ? ':'.$parts['port'] : '');
                }
                if (empty($dataset)) {
                    $dataset = trim((string) ($parts['path'] ?? ''), '/') ?: $dataset;
                }
            }
            if (empty($user)) {
                $user = $settings['fuseki_username'] ?? $user;
            }
            if (empty($pass) && ! empty($settings['fuseki_password'])) {
                $pass = SecretCrypto::reveal($settings['fuseki_password']);
            }
        }

        return ['url' => $url, 'dataset' => $dataset, 'user' => $user, 'pass' => $pass];
    }

    /**
     * Best-effort Fuseki ping - 1-second timeout, accept 200 or 401.
     */
    public function reachable(string $baseUrl): bool
    {
        $ch = curl_init(rtrim($baseUrl, '/').'/$/ping');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 1,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return in_array($code, [200, 401], true);
    }

    /** @return array<string, string> */
    private function settings(): array
    {
        try {
            return DB::table('ahg_settings')
                ->where('setting_group', 'fuseki')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
