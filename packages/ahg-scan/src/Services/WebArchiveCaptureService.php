<?php

/**
 * WebArchiveCaptureService - Heratio ahg-scan
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
 *
 * @copyright Plain Sailing Information Systems
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Single-page web capture to a WARC 1.1 file (ISO 28500).
 *
 * This is the first slice of the larger web-archiving track. It captures ONE
 * URL (the page itself), not a crawl: no link following, no embedded-resource
 * harvesting, and no replay. The output is a standards-shaped WARC 1.1 file
 * containing a warcinfo record plus a single response record that wraps the
 * raw HTTP status line, response headers and body.
 *
 * The service is deliberately resilient: every fetch is wrapped in try/catch
 * and a failure is recorded as a 'failed' row rather than thrown, so the admin
 * surface and the console command never depend on the remote host being up.
 */
class WebArchiveCaptureService
{
    /** Hard cap on the stored response body. Oversize captures are skipped. */
    public const MAX_BYTES = 52428800; // 50 MB

    /** Fetch timeout in seconds. */
    public const TIMEOUT_SECONDS = 30;

    /** Bounded redirect follow count. */
    public const MAX_REDIRECTS = 5;

    /**
     * Capture a single URL to a WARC file and record a web_archive_capture row.
     *
     * Never throws: any error (validation, network, filesystem, size cap) is
     * captured into the returned row with status 'failed'. Returns the inserted
     * row id, or null only if even the failure row could not be written.
     */
    public function capture(string $url, ?int $userId = null): ?int
    {
        $url = trim($url);
        $now = now();

        // Pre-flight validation. A bad URL is a 'failed' row, not an exception.
        if (! $this->isValidHttpUrl($url)) {
            return $this->recordFailure($url, $userId, 'Invalid or unsupported URL (only http/https are accepted).');
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent(),
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->maxRedirects(self::MAX_REDIRECTS)
                ->withOptions(['allow_redirects' => ['max' => self::MAX_REDIRECTS]])
                ->get($url);
        } catch (\Throwable $e) {
            return $this->recordFailure($url, $userId, 'Fetch failed: '.$e->getMessage());
        }

        $body = (string) $response->body();
        $byteSize = strlen($body);

        if ($byteSize > self::MAX_BYTES) {
            return $this->recordFailure(
                $url,
                $userId,
                'Response body of '.number_format($byteSize).' bytes exceeds the '
                    .number_format(self::MAX_BYTES).' byte (50 MB) cap; capture skipped.',
                $response->status(),
                $this->headerValue($response->headers(), 'Content-Type')
            );
        }

        $httpStatus = $response->status();
        $contentType = $this->headerValue($response->headers(), 'Content-Type');
        $title = $this->extractTitle($body, $contentType) ?: $url;

        // Build the WARC file content.
        try {
            $warc = $this->buildWarc($url, $response, $body, $now);
            $warcPath = $this->writeWarc($url, $warc, $now);
        } catch (\Throwable $e) {
            return $this->recordFailure($url, $userId, 'WARC write failed: '.$e->getMessage(), $httpStatus, $contentType);
        }

        return $this->insertRow([
            'url' => Str::limit($url, 2040, ''),
            'title' => $title !== null ? Str::limit($title, 1020, '') : null,
            'status' => 'captured',
            'http_status' => $httpStatus,
            'content_type' => $contentType !== null ? Str::limit($contentType, 250, '') : null,
            'warc_path' => $warcPath,
            'byte_size' => $byteSize,
            'captured_by' => $userId,
            'captured_at' => $now,
            'error' => null,
            'created_at' => $now,
        ]);
    }

    // ------------------------------------------------------------------
    // WARC 1.1 (ISO 28500) record assembly
    // ------------------------------------------------------------------

    /**
     * Assemble a complete WARC 1.1 file body: a warcinfo record followed by a
     * single response record.
     *
     * Record framing rules (ISO 28500 / WARC 1.1):
     *   - Each record = a "WARC/1.1" version line, named header fields, a blank
     *     line (CRLF), the record block, then two CRLF terminators.
     *   - Every record carries WARC-Record-ID (a urn:uuid), WARC-Date and
     *     Content-Length.
     *   - The response record block is the verbatim HTTP response: the status
     *     line, the response headers, a blank line, and the entity body. Its
     *     Content-Type is "application/http; msgtype=response".
     */
    public function buildWarc(string $url, $response, string $body, $now): string
    {
        $warcDate = $this->warcDate($now);

        // --- response record block: raw HTTP message ---
        $httpVersion = $this->httpVersion($response);
        $statusLine = 'HTTP/'.$httpVersion.' '.$response->status().' '.$this->reasonPhrase($response->status());
        $rawHttpHeaders = $this->rawHeaders($response->headers());
        $httpBlock = $statusLine."\r\n".$rawHttpHeaders."\r\n\r\n".$body;

        $responseId = $this->urnUuid();

        // --- warcinfo record block ---
        $infoBlock = $this->warcinfoBlock();
        $warcinfoId = $this->urnUuid();

        $warcinfo = $this->record([
            'WARC-Type' => 'warcinfo',
            'WARC-Date' => $warcDate,
            'WARC-Record-ID' => $warcinfoId,
            'WARC-Filename' => $this->warcFilename($url, $now),
            'Content-Type' => 'application/warc-fields',
        ], $infoBlock);

        $responseRecord = $this->record([
            'WARC-Type' => 'response',
            'WARC-Target-URI' => $url,
            'WARC-Date' => $warcDate,
            'WARC-Record-ID' => $responseId,
            'WARC-Concurrent-To' => $warcinfoId,
            'WARC-IP-Address' => '',
            'Content-Type' => 'application/http; msgtype=response',
        ], $httpBlock);

        return $warcinfo.$responseRecord;
    }

    /**
     * Frame a single WARC record: version line, headers, blank line, block,
     * then the two-CRLF record terminator. Content-Length is computed from the
     * block. Empty-valued headers are dropped (e.g. an unknown IP address).
     */
    protected function record(array $headers, string $block): string
    {
        $headers = array_filter($headers, static fn ($v) => $v !== null && $v !== '');
        $headers['Content-Length'] = (string) strlen($block);

        $lines = 'WARC/1.1'."\r\n";
        foreach ($headers as $name => $value) {
            $lines .= $name.': '.$value."\r\n";
        }
        $lines .= "\r\n"; // end of named fields

        return $lines.$block."\r\n\r\n";
    }

    /**
     * The warcinfo block: application/warc-fields naming the generating tool.
     */
    protected function warcinfoBlock(): string
    {
        $appName = config('app.name', 'Heratio');
        $appUrl = rtrim((string) config('app.url', ''), '/');

        $fields = [
            'software' => $appName.' ahg-scan WebArchiveCaptureService',
            'format' => 'WARC file version 1.1',
            'conformsTo' => 'http://iipc.github.io/warc-specifications/specifications/warc-format/warc-1.1/',
            'description' => 'Single-page web capture (no crawl, no replay).',
        ];
        if ($appUrl !== '') {
            $fields['isPartOf'] = $appUrl;
        }

        $out = '';
        foreach ($fields as $k => $v) {
            $out .= $k.': '.$v."\r\n";
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Filesystem
    // ------------------------------------------------------------------

    /**
     * Resolve the web-archive storage directory under the configured Heratio
     * storage path. Never hardcoded; created on demand (guarded).
     */
    public function storageDir($now = null): string
    {
        $now = $now ?: now();
        $base = rtrim((string) config('heratio.storage_path'), '/').'/web-archive';
        // Partition by year/month so the directory does not grow unbounded.
        return $base.'/'.$now->format('Y').'/'.$now->format('m');
    }

    protected function writeWarc(string $url, string $warc, $now): string
    {
        $dir = $this->storageDir($now);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Could not create web-archive directory: '.$dir);
            }
        }

        $path = $dir.'/'.$this->warcFilename($url, $now);
        if (@file_put_contents($path, $warc) === false) {
            throw new \RuntimeException('Could not write WARC file: '.$path);
        }

        return $path;
    }

    protected function warcFilename(string $url, $now): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'capture';
        $slug = Str::slug($host) ?: 'capture';

        return $slug.'-'.$now->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.warc';
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    protected function recordFailure(
        string $url,
        ?int $userId,
        string $error,
        ?int $httpStatus = null,
        ?string $contentType = null
    ): ?int {
        $now = now();

        return $this->insertRow([
            'url' => Str::limit($url, 2040, ''),
            'title' => null,
            'status' => 'failed',
            'http_status' => $httpStatus,
            'content_type' => $contentType !== null ? Str::limit($contentType, 250, '') : null,
            'warc_path' => null,
            'byte_size' => null,
            'captured_by' => $userId,
            'captured_at' => null,
            'error' => Str::limit($error, 2040, ''),
            'created_at' => $now,
        ]);
    }

    protected function insertRow(array $row): ?int
    {
        try {
            if (! Schema::hasTable('web_archive_capture')) {
                return null;
            }

            return (int) DB::table('web_archive_capture')->insertGetId($row);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function isValidHttpUrl(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    protected function userAgent(): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $suffix = $appUrl !== '' ? ' (+'.$appUrl.')' : '';

        return 'Heratio-WebArchive/1.0 (+web-archiving single-page capture)'.$suffix;
    }

    /** WARC-Date is a UTC W3C-ISO8601 timestamp; WARC 1.1 allows sub-second. */
    protected function warcDate($now): string
    {
        return $now->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    protected function urnUuid(): string
    {
        return 'urn:uuid:'.(string) Str::uuid();
    }

    /**
     * Rebuild the raw HTTP header block from the parsed header map. Each value
     * may be an array (repeated header); each instance is emitted on its own
     * line, as the original wire format would have carried it.
     */
    protected function rawHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $values) {
            foreach ((array) $values as $value) {
                $lines[] = $name.': '.$value;
            }
        }

        return implode("\r\n", $lines);
    }

    protected function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $values) {
            if (Str::lower($key) === Str::lower($name)) {
                $first = is_array($values) ? ($values[0] ?? null) : $values;

                return $first !== null ? (string) $first : null;
            }
        }

        return null;
    }

    protected function httpVersion($response): string
    {
        try {
            $proto = $response->toPsrResponse()->getProtocolVersion();

            return $proto !== '' ? $proto : '1.1';
        } catch (\Throwable $e) {
            return '1.1';
        }
    }

    /**
     * Extract a human title from an HTML body for the list view. Best-effort,
     * never fatal; non-HTML responses simply have no title.
     */
    protected function extractTitle(string $body, ?string $contentType): ?string
    {
        if ($contentType !== null && stripos($contentType, 'html') === false) {
            return null;
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $title !== '' ? $title : null;
        }

        return null;
    }

    /** Minimal reason-phrase map for the reconstructed HTTP status line. */
    protected function reasonPhrase(int $status): string
    {
        $map = [
            200 => 'OK', 201 => 'Created', 204 => 'No Content',
            301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other',
            304 => 'Not Modified', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
            400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
            404 => 'Not Found', 410 => 'Gone', 429 => 'Too Many Requests',
            500 => 'Internal Server Error', 502 => 'Bad Gateway',
            503 => 'Service Unavailable', 504 => 'Gateway Timeout',
        ];

        return $map[$status] ?? '';
    }
}
