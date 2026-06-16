<?php

/**
 * UrlGuard - SSRF guard for user-supplied URLs fetched server-side.
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

namespace AhgCore\Support;

class UrlGuard
{
    /**
     * Assert that a user-supplied URL is safe to fetch from the server.
     *
     * Rejects anything that is not http/https (blocks file://, gopher://,
     * ftp://, php://, etc.) and any host that resolves to a private, loopback,
     * link-local or otherwise reserved address - the classic SSRF targets,
     * including the cloud metadata endpoint 169.254.169.254.
     *
     * Aborts the request (422) on rejection; returns the URL unchanged on pass.
     *
     * Note: this validates at check time. A determined attacker could still
     * attempt DNS rebinding between this check and the actual fetch (TOCTOU).
     * For the admin-gated import features this guards, the resolve-and-reject
     * check is a proportionate mitigation; pin the resolved IP for the fetch
     * if these endpoints ever become unauthenticated.
     */
    public static function assertPublicHttpUrl(string $url): string
    {
        $reason = self::rejectionReason($url);
        if ($reason !== null) {
            abort(422, $reason);
        }

        return $url;
    }

    /**
     * Non-throwing variant for callers that return their own error shape
     * (JSON envelope, redirect-with-error, etc.). True = safe to fetch.
     */
    public static function isAllowed(string $url): bool
    {
        return self::rejectionReason($url) === null;
    }

    /**
     * Core check. Returns a human-readable rejection reason, or null if the
     * URL is safe to fetch server-side.
     */
    private static function rejectionReason(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return 'Invalid URL.';
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'Only http and https URLs are allowed.';
        }

        $host = $parts['host'];
        $ips = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            foreach (@dns_get_record($host, DNS_A + DNS_AAAA) ?: [] as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
            if (empty($ips)) {
                $ips = @gethostbynamel($host) ?: [];
            }
        }

        if (empty($ips)) {
            return 'Could not resolve URL host.';
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'URL host is not allowed (private or reserved address).';
            }
        }

        return null;
    }
}
