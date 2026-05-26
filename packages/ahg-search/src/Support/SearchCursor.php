<?php

/**
 * SearchCursor - Bidirectional cursor codec for Elasticsearch search_after.
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

namespace AhgSearch\Support;

/**
 * Opaque cursor codec for Elasticsearch `search_after` / `search_before` paging.
 *
 * Internally a cursor is a JSON array of sort values (whatever the index sort
 * clause produced for the boundary hit) plus a one-bit direction flag and a
 * tiny version number for future-proofing. The whole thing is base64url-encoded
 * so callers only ever see an opaque string.
 *
 * Forward paging:  ?cursor=<token-of-last-hit>
 * Backward paging: ?cursor=<token-of-first-hit>&dir=prev
 *
 * Cursors with an unknown version are rejected (decode returns null) so the
 * caller falls back to first-page semantics.
 *
 * Search ecosystem issue #650 - Phase 3.
 */
final class SearchCursor
{
    public const VERSION = 1;

    public const DIR_NEXT = 'n';

    public const DIR_PREV = 'p';

    /**
     * Encode an array of ES sort values into an opaque token.
     *
     * @param  array<int, mixed>  $sortValues  The `sort` array on the boundary hit.
     * @param  string  $direction  self::DIR_NEXT for `search_after`, DIR_PREV for `search_before`.
     */
    public static function encode(array $sortValues, string $direction = self::DIR_NEXT): string
    {
        $payload = [
            'v' => self::VERSION,
            'd' => $direction === self::DIR_PREV ? self::DIR_PREV : self::DIR_NEXT,
            's' => array_values($sortValues),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
    }

    /**
     * Decode an opaque cursor token. Returns null on any decode failure - the
     * caller treats that as "no cursor" and falls back to first-page semantics.
     *
     * @return array{sort: array<int, mixed>, direction: string}|null
     */
    public static function decode(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        // base64url -> base64
        $b64 = strtr($token, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $raw = base64_decode($b64, true);
        if ($raw === false) {
            return null;
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return null;
        }

        if (($payload['v'] ?? null) !== self::VERSION) {
            return null;
        }

        $sort = $payload['s'] ?? null;
        if (! is_array($sort) || empty($sort)) {
            return null;
        }

        $dir = $payload['d'] ?? self::DIR_NEXT;
        if ($dir !== self::DIR_NEXT && $dir !== self::DIR_PREV) {
            $dir = self::DIR_NEXT;
        }

        return ['sort' => array_values($sort), 'direction' => $dir];
    }
}
