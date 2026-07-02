<?php

/**
 * SsrfGuard - Service for Heratio
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

/**
 * #1395(C) — one shared SSRF guard for every outbound fetch. Mirrors the
 * already-correct HarvestClient logic and closes the systemic gaps: hosts are
 * resolved (A + AAAA) and EVERY resolved IP is checked against private /
 * reserved / loopback / link-local ranges (not just IP literals), cloud-metadata
 * endpoints are blocked by name, and numeric-integer host bypasses are
 * normalised. Callers must additionally disable redirect-following (a 30x to a
 * private IP would otherwise re-open the hole) — use safeHttpOptions().
 *
 * Fail-closed: a URL that cannot be proven safe is rejected.
 */
class SsrfGuard
{
    private const BLOCKED_HOSTS = [
        '169.254.169.254', 'metadata.google.internal', 'metadata.internal', 'metadata',
    ];

    /** True if the URL is safe to fetch (no exception). */
    public function isSafeUrl(string $url): bool
    {
        try {
            $this->assertSafeUrl($url);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Throw \RuntimeException unless $url is a public http/https endpoint whose
     * every resolved IP is a public address.
     */
    public function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new \RuntimeException('URL has no resolvable host.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Only http/https URLs are permitted.');
        }

        $host = strtolower($parts['host']);
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new \RuntimeException('Blocked host: '.$host);
        }

        foreach ($this->resolveIps($host) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException('URL resolves to a private/reserved IP: '.$ip);
            }
        }
    }

    /**
     * Guzzle/Http options that must accompany a guarded fetch: never follow
     * redirects (a 30x could rebind to a private IP after the check).
     *
     * @return array<string,mixed>
     */
    public function safeHttpOptions(): array
    {
        return ['allow_redirects' => false];
    }

    /**
     * Resolve a host to the set of IPs curl could connect to. Handles IP
     * literals, integer/decimal hosts (a documented bypass), and A + AAAA
     * records. Throws if nothing resolves (fail-closed).
     *
     * @return string[]
     */
    private function resolveIps(string $host): array
    {
        // Literal IP (v4 or v6)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        // Decimal-integer host (e.g. http://2130706433/ == 127.0.0.1) — a
        // classic private-range bypass. Normalise and check the literal.
        if (ctype_digit($host)) {
            $ip = long2ip((int) $host);
            if ($ip !== false) {
                return [$ip];
            }
            throw new \RuntimeException('Unresolvable numeric host.');
        }

        $ips = @gethostbynamel($host) ?: [];
        foreach ((@dns_get_record($host, DNS_AAAA) ?: []) as $rec) {
            if (! empty($rec['ipv6'])) {
                $ips[] = $rec['ipv6'];
            }
        }

        if (empty($ips)) {
            throw new \RuntimeException('Host does not resolve: '.$host);
        }

        return $ips;
    }
}
