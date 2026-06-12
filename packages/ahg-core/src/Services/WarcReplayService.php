<?php

/**
 * WarcReplayService - Heratio ahg-core
 *
 * heratio#1244 (WARC REPLAY slice). A bounded, read-only Wayback / pywb-style replay of a
 * previously-captured WARC file. Given a `warc_capture` row written by WarcCaptureService,
 * this service opens the stored .warc, parses it back into its constituent records, and
 * lets the catalogue render the archived page (and its same-host subresources) ENTIRELY
 * from the WARC - never from the live site.
 *
 * What it does, precisely
 * -----------------------
 *   1. Resolves a warc_capture row to its on-disk .warc path, with the SAME defence-in-depth
 *      as WarcCaptureService::fileForDownload(): the file must exist, be readable, and live
 *      UNDER config('heratio.storage_path').'/web-archive' (realpath prefix check - no path
 *      traversal out of storage).
 *   2. PARSES the WARC 1.1 byte stream that WarcCaptureService wrote. The writer emits each
 *      record as exactly:
 *          "WARC/1.1" CRLF
 *          <named WARC headers, one per line> CRLF
 *          CRLF                      (blank line ends the WARC header block)
 *          <block of Content-Length bytes>
 *          CRLF CRLF                 (mandatory record trailer)
 *      The parser splits on the "WARC/1.1" version line at the start of each record, reads
 *      the named headers up to the blank line, then takes EXACTLY Content-Length bytes as the
 *      record block (length-delimited - it never guesses at the trailer, so a block that
 *      itself contains "WARC/1.1" or CRLF CRLF parses correctly). For each WARC-Type: response
 *      record it then parses the inner application/http block (an HTTP status line + HTTP
 *      headers + CRLF CRLF + body) into {status, content-type, headers, body}, keyed by the
 *      record's WARC-Target-URI.
 *   3. Builds a URI -> response map. The MAIN page is the response whose WARC-Target-URI equals
 *      warc_capture.target_uri (with a tolerant fallback to the first text/html response if the
 *      exact URI is not present). Every other response is an addressable archived subresource.
 *
 * Bounded by design: a hard cap on the WARC file size opened for replay, a cap on the number
 * of records parsed, and a cap on the size of any single response block. An oversize / missing /
 * corrupt / empty WARC yields a clean null (the controller renders "snapshot unavailable"),
 * never a 500. The parse is iterative over an in-memory string read once under the size cap -
 * it does not load absurd sizes unguarded.
 *
 * Scope of writes: NONE. This service is strictly read-only over the .warc file and the
 * warc_capture row. It runs no AI call, performs NO live HTTP fetch (replay serves ONLY from
 * the WARC - that is the whole point), executes no ALTER, and owns no new table.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WarcReplayService
{
    /** The register table written by WarcCaptureService (read-only here). */
    public const TABLE = 'warc_capture';

    /** WARC subdirectory under the configured storage path (mirrors the capture service). */
    private const WEB_ARCHIVE_SUBDIR = 'web-archive';

    /** The exact version line the capture writer emits at the start of every record. */
    private const VERSION_LINE = 'WARC/1.1';

    /** Hard cap on the WARC file size opened for replay (32 MiB). Oversize -> clean null. */
    private const MAX_WARC_BYTES = 33554432;

    /** Cap on the number of WARC records parsed from one file (defence in depth). */
    private const MAX_RECORDS = 5000;

    /** Cap on a single response block kept in the map (8 MiB). Oversize block -> skipped. */
    private const MAX_RESPONSE_BLOCK_BYTES = 8388608;

    // =====================================================================
    // Availability + row resolution (read-only)
    // =====================================================================

    /** True only when the register table exists - the feature is installed. */
    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Read one capture row (read-only). Returns the row as an array, or null when the
     * table is absent / the row is missing.
     *
     * @return array{id:int, information_object_id:?int, slug:?string, target_uri:string, file_path:?string, file_name:?string, status:string, captured_at:?string}|null
     */
    public function captureRow(int $id): ?array
    {
        if ($id <= 0 || ! $this->isAvailable()) {
            return null;
        }

        try {
            $r = DB::table(self::TABLE)->where('id', $id)->first();
            if ($r === null) {
                return null;
            }

            return [
                'id' => (int) $r->id,
                'information_object_id' => $r->information_object_id !== null ? (int) $r->information_object_id : null,
                'slug' => $r->slug !== null ? (string) $r->slug : null,
                'target_uri' => (string) ($r->target_uri ?? ''),
                'file_path' => $r->file_path !== null ? (string) $r->file_path : null,
                'file_name' => $r->file_name !== null ? (string) $r->file_name : null,
                'status' => (string) ($r->status ?? ''),
                'captured_at' => $r->captured_at !== null ? (string) $r->captured_at : null,
            ];
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc replay captureRow failed: '.$e->getMessage());

            return null;
        }
    }

    /** The absolute web-archive storage directory (config-driven, never hardcoded). */
    public function webArchiveDir(): ?string
    {
        $base = (string) config('heratio.storage_path', '');
        $base = rtrim(trim($base), '/');
        if ($base === '') {
            return null;
        }

        return $base.'/'.self::WEB_ARCHIVE_SUBDIR;
    }

    /**
     * Resolve a capture row's .warc to a safe, real on-disk path that lives UNDER the
     * configured web-archive directory. Returns null on any failure (missing / unreadable /
     * outside storage). Read-only.
     */
    public function safeWarcPath(array $row): ?string
    {
        $path = trim((string) ($row['file_path'] ?? ''));
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $dir = $this->webArchiveDir();
        $real = realpath($path);
        $realDir = $dir !== null ? realpath($dir) : false;
        if ($real === false || $realDir === false || strpos($real, $realDir.'/') !== 0) {
            return null;
        }

        return $real;
    }

    // =====================================================================
    // Replay model (parse the WARC into a URI -> response map)
    // =====================================================================

    /**
     * Open + parse the capture's WARC into a replay model:
     *   [
     *     'main_uri'  => <the page URI>,
     *     'main'      => {status, content_type, headers, body} | null,
     *     'resources' => [ uri => {status, content_type, headers, body}, ... ],
     *   ]
     * Returns null when the WARC is missing / oversize / corrupt / has no usable response -
     * the controller turns null into a clean "snapshot unavailable" message (never a 500).
     *
     * @return array{main_uri:string, main:?array, resources:array<string,array>}|null
     */
    public function buildModel(array $row): ?array
    {
        try {
            $path = $this->safeWarcPath($row);
            if ($path === null) {
                return null;
            }

            $size = @filesize($path);
            if ($size === false || $size <= 0 || $size > self::MAX_WARC_BYTES) {
                // Empty, unreadable, or oversize -> unavailable (bounded; we never read absurd sizes).
                return null;
            }

            $bytes = @file_get_contents($path, false, null, 0, self::MAX_WARC_BYTES);
            if ($bytes === false || $bytes === '') {
                return null;
            }

            $responses = $this->parseResponses($bytes);
            if ($responses === []) {
                return null;
            }

            $targetUri = $this->normalizeUri((string) ($row['target_uri'] ?? ''));

            // Pick the main page: the response whose URI matches the capture target_uri.
            $main = null;
            $mainUri = $targetUri;
            if ($targetUri !== '' && isset($responses[$targetUri])) {
                $main = $responses[$targetUri];
            } else {
                // Tolerant fallback: the first text/html response in the WARC.
                foreach ($responses as $uri => $resp) {
                    if (str_contains((string) ($resp['content_type'] ?? ''), 'html')) {
                        $main = $resp;
                        $mainUri = $uri;
                        break;
                    }
                }
            }

            if ($main === null) {
                // No HTML main page found at all -> nothing meaningful to replay.
                return null;
            }

            return [
                'main_uri' => $mainUri,
                'main' => $main,
                'resources' => $responses,
            ];
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc replay buildModel failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Look up ONE archived subresource by its URI in the capture's WARC. Returns
     * {status, content_type, headers, body} or null when the URI is not present in this
     * WARC. Replay serves ONLY from the WARC: a miss here is a 404 at the controller, never
     * a live fetch. Read-only.
     *
     * @return array{status:int, content_type:string, headers:array<string,string>, body:string}|null
     */
    public function findResource(array $row, string $uri): ?array
    {
        $uri = $this->normalizeUri($uri);
        if ($uri === '') {
            return null;
        }
        $model = $this->buildModel($row);
        if ($model === null) {
            return null;
        }
        $resources = $model['resources'] ?? [];

        return $resources[$uri] ?? null;
    }

    // =====================================================================
    // WARC 1.1 parsing (length-delimited, byte-exact to the capture writer)
    // =====================================================================

    /**
     * Parse the WARC byte stream into a URI -> response map. Only WARC-Type: response
     * records are kept (warcinfo + request records are skipped). Each value is the parsed
     * inner HTTP message: {status, content_type, headers, body}. Iterative + bounded: at
     * most MAX_RECORDS records, response blocks over MAX_RESPONSE_BLOCK_BYTES are skipped.
     *
     * Parse strategy (matches WarcCaptureService::record() exactly):
     *   - Find the next "WARC/1.1" version line at a record boundary.
     *   - Read the WARC header lines up to the first blank line (CRLF CRLF).
     *   - From those headers take Content-Length and read EXACTLY that many block bytes
     *     immediately after the blank line (length-delimited - robust to any byte content
     *     inside the block, including embedded "WARC/1.1" or CRLF CRLF).
     *   - Advance past the block + the mandatory CRLF CRLF trailer to the next record.
     *
     * @return array<string, array{status:int, content_type:string, headers:array<string,string>, body:string}>
     */
    public function parseResponses(string $warc): array
    {
        $out = [];
        if ($warc === '') {
            return $out;
        }

        $len = strlen($warc);
        $pos = 0;
        $records = 0;
        $marker = self::VERSION_LINE;
        $markerLen = strlen($marker);

        while ($pos < $len && $records < self::MAX_RECORDS) {
            // Locate the next version line that begins a record.
            $vp = strpos($warc, $marker, $pos);
            if ($vp === false) {
                break;
            }
            // The version line must sit at a record boundary: position 0, or immediately
            // after a CRLF (LF). Otherwise it is incidental bytes inside a prior block we
            // already skipped past via Content-Length - keep scanning.
            if ($vp !== 0 && $warc[$vp - 1] !== "\n") {
                $pos = $vp + $markerLen;

                continue;
            }

            $records++;

            // The WARC header block ends at the first blank line (CRLF CRLF).
            $headerStart = $vp;
            $blankAt = strpos($warc, "\r\n\r\n", $headerStart);
            if ($blankAt === false) {
                break; // malformed tail
            }
            $headerText = substr($warc, $headerStart, $blankAt - $headerStart);
            $blockStart = $blankAt + 4; // past CRLF CRLF

            $headers = $this->parseWarcHeaders($headerText);
            $contentLength = isset($headers['content-length']) ? (int) $headers['content-length'] : -1;

            if ($contentLength < 0 || $blockStart + $contentLength > $len) {
                // No usable Content-Length, or it overruns the buffer -> stop parsing cleanly.
                break;
            }

            $block = substr($warc, $blockStart, $contentLength);

            // Advance position past the block + the mandatory CRLF CRLF record trailer.
            $next = $blockStart + $contentLength;
            if (substr($warc, $next, 4) === "\r\n\r\n") {
                $next += 4;
            }
            $pos = $next;

            $type = strtolower((string) ($headers['warc-type'] ?? ''));
            if ($type !== 'response') {
                continue; // warcinfo / request / metadata -> not replayable content
            }

            $uri = $this->normalizeUri((string) ($headers['warc-target-uri'] ?? ''));
            if ($uri === '') {
                continue;
            }
            if (strlen($block) > self::MAX_RESPONSE_BLOCK_BYTES) {
                continue; // bounded: skip an oversize response block
            }

            $http = $this->parseHttpResponse($block);
            if ($http === null) {
                continue;
            }

            // First write wins for a given URI (the page/asset was captured once).
            if (! isset($out[$uri])) {
                $out[$uri] = $http;
            }
        }

        return $out;
    }

    /**
     * Parse a WARC named-header block ("Key: Value" lines) into a lowercased-key map.
     *
     * @return array<string,string>
     */
    private function parseWarcHeaders(string $headerText): array
    {
        $out = [];
        $lines = preg_split('/\r\n|\n/', $headerText) ?: [];
        foreach ($lines as $line) {
            if ($line === '' || $line === self::VERSION_LINE) {
                continue;
            }
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $key = strtolower(trim(substr($line, 0, $colon)));
            $val = trim(substr($line, $colon + 1));
            if ($key !== '') {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    /**
     * Parse an inner application/http response block (HTTP status line + headers + CRLF CRLF
     * + body) into {status, content_type, headers, body}. Returns null when the block has no
     * recognisable HTTP status line.
     *
     * @return array{status:int, content_type:string, headers:array<string,string>, body:string}|null
     */
    private function parseHttpResponse(string $block): ?array
    {
        if ($block === '') {
            return null;
        }

        // Split the HTTP header block from the body at the first blank line.
        $sep = strpos($block, "\r\n\r\n");
        $nlSep = strpos($block, "\n\n");
        if ($sep !== false && ($nlSep === false || $sep <= $nlSep)) {
            $headerPart = substr($block, 0, $sep);
            $body = substr($block, $sep + 4);
        } elseif ($nlSep !== false) {
            $headerPart = substr($block, 0, $nlSep);
            $body = substr($block, $nlSep + 2);
        } else {
            // Headers only, no body separator.
            $headerPart = $block;
            $body = '';
        }

        $lines = preg_split('/\r\n|\n/', $headerPart) ?: [];
        if ($lines === []) {
            return null;
        }

        $statusLine = (string) array_shift($lines);
        if (! preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', trim($statusLine), $m)) {
            return null;
        }
        $status = (int) $m[1];

        $headers = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $key = strtolower(trim(substr($line, 0, $colon)));
            $val = trim(substr($line, $colon + 1));
            if ($key !== '') {
                $headers[$key] = $val;
            }
        }

        $contentType = (string) ($headers['content-type'] ?? '');

        return [
            'status' => $status,
            'content_type' => $contentType,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * Canonicalise a URI for map keying + lookup: trim, drop a fragment, and normalise a
     * trailing slash (so "/x" and "/x/" key the same). The query string is preserved (assets
     * are often cache-busted with ?v=...), matching how the capture service keyed them.
     */
    public function normalizeUri(string $uri): string
    {
        $uri = trim($uri);
        if ($uri === '') {
            return '';
        }
        $hash = strpos($uri, '#');
        if ($hash !== false) {
            $uri = substr($uri, 0, $hash);
        }
        // Normalise a trailing slash on a path that has no query (keep the root "/").
        if (! str_contains($uri, '?')) {
            $trimmed = rtrim($uri, '/');
            // Keep at least scheme://host (don't collapse "https://host" to "https:/").
            if ($trimmed !== '' && ! preg_match('#^https?://[^/]*$#i', $uri)) {
                $uri = $trimmed === '' ? '/' : $trimmed;
            }
        }

        return $uri;
    }
}
