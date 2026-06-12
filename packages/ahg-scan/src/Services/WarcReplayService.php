<?php

/**
 * WarcReplayService - Heratio ahg-scan
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

use Illuminate\Support\Str;

/**
 * Read-only replay of a single-page WARC 1.1 capture (ISO 28500).
 *
 * Given the path to a WARC file produced by WebArchiveCaptureService (a
 * warcinfo record followed by one response record), this service locates the
 * `response` record, parses the archived HTTP status line, the archived
 * response headers, and the archived entity body, and returns them as a plain
 * array. It never executes, fetches, or proxies any live resource: it only
 * reads bytes that were written to disk at capture time.
 *
 * Design principles:
 *   - Read-only. No database writes, no network, no ALTER. Reads the WARC file
 *     and nothing else.
 *   - Traversal-guarded. The caller resolves the WARC path; this service
 *     additionally re-verifies that the realpath sits under the configured
 *     web-archive storage root before opening it.
 *   - Resilient. Any problem (missing file, outside the root, unreadable,
 *     truncated, no response record, malformed framing) returns a structured
 *     error array. The public entry point never throws.
 *
 * Scope: this replays ONE archived document (the captured page itself). It does
 * NOT replay embedded subresources (CSS, JS, images, fonts) because the capture
 * slice records only the page document, not a crawl. Multi-resource replay is
 * noted as future work.
 */
class WarcReplayService
{
    /** Hard cap on how many bytes of a record block we will buffer in memory. */
    public const MAX_BLOCK_BYTES = 52428800; // 50 MB, mirrors the capture cap

    /** Safety bound on header bytes read while scanning a record's framing. */
    public const MAX_HEADER_BYTES = 65536; // 64 KB of WARC/HTTP headers is ample

    /**
     * Resolve and parse the response record of a WARC file.
     *
     * Returns an array shaped as one of:
     *   ['ok' => true, 'status' => int, 'reason' => string,
     *    'headers' => array<string,string>, 'body' => string,
     *    'content_type' => ?string, 'target_uri' => ?string]
     *   ['ok' => false, 'error' => string]
     *
     * @param  string|null  $warcPath  The stored warc_path from web_archive_capture.
     */
    public function replay(?string $warcPath): array
    {
        try {
            $resolved = $this->resolvePath($warcPath);
            if ($resolved === null) {
                return $this->fail('The WARC file for this capture is missing or outside the web-archive storage area.');
            }

            $record = $this->readResponseRecord($resolved);
            if ($record === null) {
                return $this->fail('No archived HTTP response could be read from the WARC file (it may be truncated or contain no response record).');
            }

            $http = $this->parseHttpMessage($record);
            if ($http === null) {
                return $this->fail('The archived HTTP response inside the WARC file could not be parsed.');
            }

            return [
                'ok' => true,
                'status' => $http['status'],
                'reason' => $http['reason'],
                'headers' => $http['headers'],
                'body' => $http['body'],
                'content_type' => $this->headerLookup($http['headers'], 'Content-Type'),
                'target_uri' => $record['target_uri'],
            ];
        } catch (\Throwable $e) {
            // Never surface an exception to the caller; degrade to a clean error.
            return $this->fail('The WARC file could not be replayed: '.$e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Path resolution + traversal guard
    // ------------------------------------------------------------------

    /**
     * Resolve the stored path, guard against traversal, and confirm the file
     * exists and is readable. Returns the canonical (realpath) path on success,
     * or null on any failure.
     *
     * The storage root is the configured web-archive directory:
     *   config('heratio.storage_path') . '/web-archive'
     * The resolved file must sit under that root once symlinks and ".." are
     * collapsed by realpath(), so a poisoned warc_path cannot escape it.
     */
    public function resolvePath(?string $warcPath): ?string
    {
        if ($warcPath === null || trim($warcPath) === '') {
            return null;
        }

        $root = $this->storageRoot();
        if ($root === null) {
            return null;
        }

        $real = @realpath($warcPath);
        if ($real === false) {
            return null;
        }

        if (! is_file($real) || ! is_readable($real)) {
            return null;
        }

        // Containment check on canonical paths. Compare against the root with a
        // trailing separator so "/store/web-archive-evil" cannot match
        // "/store/web-archive".
        $rootCanonical = rtrim($root, DIRECTORY_SEPARATOR);
        $rootWithSep = $rootCanonical.DIRECTORY_SEPARATOR;
        if ($real !== $rootCanonical && ! Str::startsWith($real, $rootWithSep)) {
            return null;
        }

        return $real;
    }

    /**
     * Canonical web-archive storage root. Mirrors WebArchiveCaptureService so the
     * two stay in lockstep. Returns the realpath of the root, or null if it does
     * not yet exist on disk.
     */
    protected function storageRoot(): ?string
    {
        $base = rtrim((string) config('heratio.storage_path'), '/').'/web-archive';
        $real = @realpath($base);

        return $real === false ? null : $real;
    }

    // ------------------------------------------------------------------
    // WARC record reading (length-framed)
    // ------------------------------------------------------------------

    /**
     * Stream the WARC file record by record and return the first `response`
     * record's block (the raw HTTP message) plus its WARC-Target-URI.
     *
     * The reader is length-framed: it reads each record's header block up to the
     * blank-line terminator, takes the declared Content-Length, then reads
     * exactly that many bytes as the record block. This is robust against bodies
     * that themselves contain "WARC/1.1" lines or blank lines. The warcinfo
     * record (and any other non-response record) is skipped by seeking past its
     * block.
     *
     * @return array{block:string,target_uri:?string}|null
     */
    protected function readResponseRecord(string $path): ?array
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }

        try {
            $guard = 0;
            while (! feof($fh)) {
                if (++$guard > 10000) {
                    return null; // pathological file; bail out cleanly
                }

                $header = $this->readRecordHeader($fh);
                if ($header === null) {
                    return null; // no more records / malformed framing
                }

                $fields = $header['fields'];
                $length = $header['content_length'];
                if ($length === null || $length < 0 || $length > self::MAX_BLOCK_BYTES) {
                    return null;
                }

                $type = Str::lower($this->headerLookup($fields, 'WARC-Type') ?? '');

                if ($type === 'response') {
                    $block = $length === 0 ? '' : (string) $this->readExactly($fh, $length);

                    return [
                        'block' => $block,
                        'target_uri' => $this->headerLookup($fields, 'WARC-Target-URI'),
                    ];
                }

                // Not the response record: skip its block plus the two-CRLF
                // record terminator, then continue scanning.
                if ($length > 0 && @fseek($fh, $length, SEEK_CUR) !== 0) {
                    return null;
                }
                $this->skipRecordTerminator($fh);
            }
        } finally {
            fclose($fh);
        }

        return null;
    }

    /**
     * Read one record's framing: the "WARC/1.1" version line, the named header
     * fields, and the blank line that ends them. Leaves the handle positioned at
     * the first byte of the record block. Returns the parsed fields and the
     * declared Content-Length, or null at clean EOF / on malformed framing.
     *
     * @return array{fields:array<string,string>,content_length:?int}|null
     */
    protected function readRecordHeader($fh): ?array
    {
        // Find the version line. Tolerate stray blank lines / record terminators
        // that precede the next record.
        $versionSeen = false;
        $read = 0;
        $fields = [];

        while (($line = fgets($fh)) !== false) {
            $read += strlen($line);
            if ($read > self::MAX_HEADER_BYTES) {
                return null;
            }
            $trimmed = rtrim($line, "\r\n");

            if (! $versionSeen) {
                if ($trimmed === '') {
                    continue; // skip terminator blank lines between records
                }
                if (Str::startsWith($trimmed, 'WARC/')) {
                    $versionSeen = true;

                    continue;
                }

                // Unexpected content before a version line: malformed.
                return null;
            }

            // In the named-field block now.
            if ($trimmed === '') {
                // Blank line ends the header block.
                return [
                    'fields' => $fields,
                    'content_length' => $this->intHeader($fields, 'Content-Length'),
                ];
            }

            $pos = strpos($trimmed, ':');
            if ($pos !== false) {
                $name = trim(substr($trimmed, 0, $pos));
                $value = trim(substr($trimmed, $pos + 1));
                if ($name !== '') {
                    $fields[$name] = $value;
                }
            }
        }

        return null; // EOF before a complete header block
    }

    /**
     * Read exactly $length bytes, looping over short reads. Returns whatever was
     * read (which may be short at EOF); the caller tolerates a truncated block.
     */
    protected function readExactly($fh, int $length): string
    {
        $buf = '';
        $remaining = $length;
        while ($remaining > 0 && ! feof($fh)) {
            $chunk = fread($fh, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buf .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $buf;
    }

    /**
     * Consume the two-CRLF terminator that follows a record block. Best-effort:
     * if the bytes are not exactly the terminator, the position is restored so
     * the next readRecordHeader can resync on the version line.
     */
    protected function skipRecordTerminator($fh): void
    {
        $pos = @ftell($fh);
        $peek = (string) fread($fh, 4);
        if ($peek === "\r\n\r\n") {
            return;
        }
        // Restore and let the header reader tolerate leading blank lines.
        if ($pos !== false) {
            @fseek($fh, $pos, SEEK_SET);
        }
    }

    // ------------------------------------------------------------------
    // HTTP message parsing (from the response record block)
    // ------------------------------------------------------------------

    /**
     * Parse the raw HTTP response message held in a response record block:
     * a status line, header lines, a blank line, then the entity body.
     *
     * @param  array{block:string,target_uri:?string}  $record
     * @return array{status:int,reason:string,headers:array<string,string>,body:string}|null
     */
    protected function parseHttpMessage(array $record): ?array
    {
        $block = $record['block'];
        if ($block === '') {
            return null;
        }

        // Split the header section from the body on the first blank line.
        // Tolerate CRLF (what the writer emits) and bare LF defensively.
        $sep = "\r\n\r\n";
        $idx = strpos($block, $sep);
        if ($idx === false) {
            $sep = "\n\n";
            $idx = strpos($block, $sep);
        }

        if ($idx === false) {
            // Headers only, no body.
            $headerPart = $block;
            $body = '';
        } else {
            $headerPart = substr($block, 0, $idx);
            $body = substr($block, $idx + strlen($sep));
        }

        $lines = preg_split("/\r\n|\n/", $headerPart);
        if (! is_array($lines) || count($lines) === 0) {
            return null;
        }

        $statusLine = array_shift($lines);
        $parsedStatus = $this->parseStatusLine((string) $statusLine);
        if ($parsedStatus === null) {
            return null;
        }

        $headers = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($name === '') {
                continue;
            }
            // Fold repeated headers onto a comma-joined value. Sensitive headers
            // (set-cookie etc.) are dropped at serve time regardless.
            if (isset($headers[$name])) {
                $headers[$name] .= ', '.$value;
            } else {
                $headers[$name] = $value;
            }
        }

        return [
            'status' => $parsedStatus['status'],
            'reason' => $parsedStatus['reason'],
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * Parse an HTTP status line, e.g. "HTTP/1.1 200 OK".
     *
     * @return array{status:int,reason:string}|null
     */
    protected function parseStatusLine(string $line): ?array
    {
        if (! preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})(?:\s+(.*))?$#', trim($line), $m)) {
            return null;
        }

        return [
            'status' => (int) $m[1],
            'reason' => isset($m[2]) ? trim($m[2]) : '',
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Case-insensitive header lookup returning the first matching value. */
    public function headerLookup(array $headers, string $name): ?string
    {
        $needle = Str::lower($name);
        foreach ($headers as $key => $value) {
            if (Str::lower((string) $key) === $needle) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function intHeader(array $headers, string $name): ?int
    {
        $v = $this->headerLookup($headers, $name);
        if ($v === null || ! ctype_digit($v)) {
            return null;
        }

        return (int) $v;
    }

    protected function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
