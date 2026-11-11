<?php

/**
 * SearchHostAuth - adds the search server's credentials and TLS settings to every
 * HTTP request Heratio sends to it, and to nothing else.
 *
 * Heratio talks to OpenSearch / Elasticsearch over plain HTTP from many places
 * (Http::get("{$host}/..."), about 20 files). Rather than give each call site a
 * client, one Guzzle middleware matches requests on the configured search host
 * and port, and adds Basic auth plus the CA / verify option. Requests to any other
 * host (the AI gateway, Qdrant, federation peers) are untouched, so the
 * credentials never leave for another server.
 *
 * Config: services.elasticsearch.{host, username, password, ca, verify}.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgSearch\Support;

use Psr\Http\Message\RequestInterface;

class SearchHostAuth
{
    /** True when the config asks for anything beyond a plain, open server. */
    public static function needed(array $cfg): bool
    {
        return ! empty($cfg['username']) || ! empty($cfg['ca']) || ! self::verify($cfg);
    }

    /** Guzzle middleware for Http::globalMiddleware(). */
    public static function middleware(array $cfg): callable
    {
        $target = self::endpoint((string) ($cfg['host'] ?? ''));

        return function (callable $handler) use ($cfg, $target) {
            return function (RequestInterface $request, array $options) use ($handler, $cfg, $target) {
                if ($target !== null && self::endpoint((string) $request->getUri()) === $target) {
                    if (! empty($cfg['username'])) {
                        $request = $request->withHeader('Authorization',
                            'Basic '.base64_encode($cfg['username'].':'.($cfg['password'] ?? '')));
                    }
                    $options['verify'] = ! empty($cfg['ca']) ? (string) $cfg['ca'] : self::verify($cfg);
                }

                return $handler($request, $options);
            };
        };
    }

    /** "scheme://host:port" with default ports filled in, or null if unparseable. */
    public static function endpoint(string $url): ?string
    {
        $p = parse_url($url);
        if (empty($p['host'])) {
            return null;
        }
        $scheme = strtolower($p['scheme'] ?? 'http');
        $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $scheme.'://'.strtolower($p['host']).':'.$port;
    }

    private static function verify(array $cfg): bool
    {
        return filter_var($cfg['verify'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
