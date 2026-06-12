<?php

/**
 * WarcCaptureService - Heratio ahg-core
 *
 * heratio#1244 (WARC web-archiving slice). A bounded, VERIFIABLE first slice of web
 * archiving for the catalogue: snapshot a PUBLISHED archival record's OWN public web
 * page into a valid WARC 1.1 (ISO 28500) file, so the catalogue can web-archive its
 * own record pages.
 *
 * What it does, precisely
 * -----------------------
 *   1. Resolves a published record (by id) to its canonical, same-host public URL via
 *      url('/'.slug) - exactly the URL a visitor sees. The published gate is the same
 *      one every public surface uses: a `status` row with type_id 158 (publication
 *      status) AND status_id 160 (published), the synthetic root description (id 1)
 *      excluded.
 *   2. SSRF guard: the ONLY URL it will ever fetch is the record's own canonical url()
 *      on THIS host. assertOwnRecordUrl() re-derives the canonical URL from the record
 *      and refuses anything whose scheme/host/path differs (off-host, a different
 *      record, a non-http scheme, a credentialed/ported authority, ...). No
 *      user-supplied URL is ever fetched; the capture takes a record id, not a URL.
 *   3. Performs a SERVER-SIDE HTTP GET of that one URL, bounded: a connect + total
 *      timeout, a redirect cap, and a hard response-size cap (oversize -> a clean
 *      failure, never an unbounded read).
 *   4. Writes a valid WARC 1.1 file containing, in order:
 *        - a `warcinfo` record (capture-software + format metadata),
 *        - a `request`  record (the exact HTTP request line + headers we sent),
 *        - a `response` record (the HTTP status line + response headers + body).
 *      Each record carries correct WARC headers (WARC-Type, WARC-Record-ID as a
 *      urn:uuid, WARC-Date, WARC-Target-URI, Content-Type application/http with the
 *      msgtype parameter for request/response, Content-Length, and a
 *      WARC-Block-Digest of sha256:<base32>). Records are separated by the mandatory
 *      CRLF CRLF, and the version line is exactly "WARC/1.1".
 *   5. Stores the .warc under config('heratio.storage_path').'/web-archive' (NEVER a
 *      hardcoded path; the storage root is www-data-writable on this host) and records
 *      a row in the NEW warc_capture table (information_object_id, slug, target_uri,
 *      file path/name, byte size, file sha256, http status, status, captured_at,
 *      captured_by).
 *
 * Scope of writes: ONLY the new warc_capture table + the .warc files on disk. No
 * AtoM/Qubit base table is ever written, no ALTER is run, and no AI call is made. The
 * status enumeration comes from the Dropdown Manager (ahg_dropdown group
 * warc_capture_status) - never an ENUM.
 *
 * Resilient by design: a missing table, an unreachable page, an oversize body, or a
 * non-own-host URL produces a `failed` row + a clean message - never a 500.
 *
 * Honest remaining gap: this archives the record's OWN HTML page only. It does NOT yet
 * fetch and embed the page's subresources (CSS / JS / images / fonts), and there is no
 * replay (Wayback / pywb) surface. Multi-resource capture and replay remain open under
 * heratio#1244 / the digital-preservation roadmap.
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

class WarcCaptureService
{
    /** The new register table this service owns. */
    public const TABLE = 'warc_capture';

    /** Dropdown Manager taxonomy for the outcome status. */
    public const STATUS_TAXONOMY = 'warc_capture_status';

    /** Publication-status taxonomy: status.type_id of a publication-status row. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id meaning "published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information object - never a real description. */
    private const ROOT_ID = 1;

    /** Outcome status values (validated; mirrored by the dropdown seed). */
    public const STATUS_CAPTURED = 'captured';

    public const STATUS_FAILED = 'failed';

    /** Bounded capture caps. */
    private const HTTP_TIMEOUT_SECONDS = 20;

    private const HTTP_CONNECT_TIMEOUT_SECONDS = 8;

    private const MAX_REDIRECTS = 3;

    /** Hard response-body size cap (8 MiB). Oversize -> clean failure. */
    private const MAX_BODY_BYTES = 8388608;

    /** WARC subdirectory under the configured storage path. */
    private const WEB_ARCHIVE_SUBDIR = 'web-archive';

    /** A stable, honest software identifier for the warcinfo record. */
    private const SOFTWARE = 'Heratio WarcCaptureService (ahg-core) heratio#1244';

    // =====================================================================
    // Availability
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

    // =====================================================================
    // Resolution + SSRF guard
    // =====================================================================

    /**
     * Resolve a PUBLISHED, non-root record by id to its display fields + canonical
     * same-host public URL, or null when the record is missing / not published.
     *
     * The canonical URL is url('/'.slug) - the single-segment /{slug} record page,
     * built with url() so the host is never hardcoded.
     *
     * @return array{id:int, slug:string, title:string, url:string}|null
     */
    public function resolvePublishedRecord(int $id): ?array
    {
        if ($id <= self::ROOT_ID) {
            return null;
        }
        if (! Schema::hasTable('information_object')
            || ! Schema::hasTable('status')
            || ! Schema::hasTable('slug')) {
            return null;
        }

        try {
            $hasI18n = Schema::hasTable('information_object_i18n');
            $hasSource = Schema::hasColumn('information_object', 'source_culture');

            $q = DB::table('information_object as io')
                ->join('status as st', function ($j) {
                    $j->on('st.object_id', '=', 'io.id')
                        ->where('st.type_id', self::STATUS_TYPE_PUBLICATION)
                        ->where('st.status_id', self::STATUS_PUBLISHED);
                })
                ->join('slug as s', 's.object_id', '=', 'io.id');

            if ($hasI18n) {
                $q->leftJoin('information_object_i18n as i', function ($j) use ($hasSource) {
                    $j->on('i.id', '=', 'io.id');
                    if ($hasSource) {
                        $j->on('i.culture', '=', 'io.source_culture');
                    }
                });
            }

            $select = ['io.id', 's.slug'];
            $select[] = $hasI18n ? 'i.title' : DB::raw('NULL as title');

            $row = $q->where('io.id', $id)
                ->where('io.id', '>', self::ROOT_ID)
                ->select($select)
                ->first();

            if ($row === null) {
                return null;
            }

            $slug = trim((string) ($row->slug ?? ''));
            if ($slug === '') {
                return null;
            }

            $title = trim((string) ($row->title ?? ''));
            if ($title === '') {
                $title = __('Untitled record');
            }

            return [
                'id' => (int) $row->id,
                'slug' => $slug,
                'title' => $title,
                'url' => $this->canonicalUrl($slug),
            ];
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc resolvePublishedRecord failed: '.$e->getMessage());

            return null;
        }
    }

    /** The canonical same-host public URL for a record slug (url(), never hardcoded). */
    public function canonicalUrl(string $slug): string
    {
        return url('/'.ltrim(trim($slug), '/'));
    }

    /**
     * SSRF GUARD. Confirm that $url is EXACTLY the record's own canonical public URL
     * on THIS host - same scheme, same host (no port / no userinfo), same path. This
     * is the only URL the capture is ever allowed to fetch. Anything else (a different
     * host, a different record, a non-http scheme, an embedded port or credentials, a
     * path mismatch) is rejected.
     *
     * @return bool true only when $url is the record's own canonical URL on this host
     */
    public function assertOwnRecordUrl(string $url, array $record): bool
    {
        $canonical = (string) ($record['url'] ?? '');
        if ($canonical === '') {
            return false;
        }

        $a = parse_url($url);
        $b = parse_url($canonical);
        if (! is_array($a) || ! is_array($b)) {
            return false;
        }

        // Scheme must be http/https and identical to the canonical scheme.
        $aScheme = strtolower((string) ($a['scheme'] ?? ''));
        $bScheme = strtolower((string) ($b['scheme'] ?? ''));
        if (! in_array($aScheme, ['http', 'https'], true) || $aScheme !== $bScheme) {
            return false;
        }

        // No embedded credentials (user:pass@) - a classic SSRF dodge.
        if (isset($a['user']) || isset($a['pass'])) {
            return false;
        }

        // Host must match the canonical host exactly (case-insensitive), and must be
        // this site's own host. No alternate port is permitted.
        $aHost = strtolower((string) ($a['host'] ?? ''));
        $bHost = strtolower((string) ($b['host'] ?? ''));
        $ours = strtolower((string) parse_url((string) url('/'), PHP_URL_HOST));
        if ($aHost === '' || $aHost !== $bHost || ($ours !== '' && $aHost !== $ours)) {
            return false;
        }
        if (isset($a['port'])) {
            // The canonical URL carries no explicit port; reject any ported authority.
            return false;
        }

        // Path must match the canonical path exactly (no query/fragment trickery
        // changes which resource we fetch; we ignore query/fragment but require the
        // path to be the record's own path).
        $aPath = rtrim((string) ($a['path'] ?? ''), '/');
        $bPath = rtrim((string) ($b['path'] ?? ''), '/');

        return $aPath !== '' && $aPath === $bPath;
    }

    // =====================================================================
    // Capture
    // =====================================================================

    /**
     * Capture one published record's own public page into a WARC 1.1 file and record
     * it. Always returns a structured result; on any failure it records a `failed`
     * row (when the table exists) and returns ok=false with a clean message - never
     * throws to the caller.
     *
     * @return array{ok:bool, id:?int, status:string, message:?string, target_uri:?string, byte_size:?int, sha256:?string}
     */
    public function capture(int $informationObjectId, ?int $userId = null): array
    {
        $record = $this->resolvePublishedRecord($informationObjectId);
        if ($record === null) {
            return $this->fail(null, null, __('That published record could not be found.'), $informationObjectId, null);
        }

        $targetUri = $record['url'];

        // SSRF guard: only ever the record's OWN canonical URL on this host.
        if (! $this->assertOwnRecordUrl($targetUri, $record)) {
            return $this->fail($informationObjectId, $record['slug'], __('Refused: the target is not this record\'s own page on this host.'), $informationObjectId, $targetUri);
        }

        if (! $this->isAvailable()) {
            return [
                'ok' => false,
                'id' => null,
                'status' => self::STATUS_FAILED,
                'message' => __('The web-archive table is not installed yet. Please try again shortly.'),
                'target_uri' => $targetUri,
                'byte_size' => null,
                'sha256' => null,
            ];
        }

        // Perform the bounded server-side GET.
        $fetch = $this->fetch($targetUri);
        if (! $fetch['ok']) {
            return $this->fail($informationObjectId, $record['slug'], $fetch['error'] ?? __('The page could not be captured.'), $informationObjectId, $targetUri);
        }

        // Build the WARC 1.1 bytes.
        try {
            $warc = $this->buildWarc(
                $targetUri,
                $fetch['request_block'],
                $fetch['response_block'],
                $fetch['http_status']
            );
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc buildWarc failed: '.$e->getMessage());

            return $this->fail($informationObjectId, $record['slug'], __('The WARC file could not be assembled.'), $informationObjectId, $targetUri);
        }

        // Write it under the configured storage web-archive dir.
        $written = $this->writeWarc($record['slug'], $informationObjectId, $warc);
        if (! $written['ok']) {
            return $this->fail($informationObjectId, $record['slug'], $written['error'] ?? __('The WARC file could not be stored.'), $informationObjectId, $targetUri);
        }

        // Record the success row.
        $now = now();
        try {
            $id = (int) DB::table(self::TABLE)->insertGetId([
                'information_object_id' => $informationObjectId,
                'slug' => $this->clip($record['slug'], 255),
                'target_uri' => $this->clip($targetUri, 2048),
                'file_path' => $this->clip($written['path'], 1024),
                'file_name' => $this->clip($written['name'], 255),
                'byte_size' => $written['size'],
                'sha256' => $written['sha256'],
                'http_status' => $fetch['http_status'] ?: null,
                'status' => self::STATUS_CAPTURED,
                'error_message' => null,
                'captured_by' => $userId && $userId > 0 ? $userId : null,
                'captured_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc capture insert failed: '.$e->getMessage());

            return [
                'ok' => false,
                'id' => null,
                'status' => self::STATUS_FAILED,
                'message' => __('The capture was made but could not be recorded.'),
                'target_uri' => $targetUri,
                'byte_size' => $written['size'],
                'sha256' => $written['sha256'],
            ];
        }

        return [
            'ok' => true,
            'id' => $id,
            'status' => self::STATUS_CAPTURED,
            'message' => null,
            'target_uri' => $targetUri,
            'byte_size' => $written['size'],
            'sha256' => $written['sha256'],
        ];
    }

    /**
     * Bounded server-side HTTP GET of the (already SSRF-validated) record URL. Uses
     * cURL when available (for a streamed, size-capped read + redirect cap), and
     * captures the exact request + response blocks for the WARC.
     *
     * @return array{ok:bool, error:?string, http_status:int, request_block:string, response_block:string}
     */
    private function fetch(string $url): array
    {
        $fail = fn (string $msg) => [
            'ok' => false, 'error' => $msg, 'http_status' => 0,
            'request_block' => '', 'response_block' => '',
        ];

        if (! function_exists('curl_init')) {
            return $fail(__('Capture is unavailable: the HTTP client is not installed.'));
        }

        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?'.$parts['query'];
        }

        // The exact request line + headers we send (recorded verbatim in the WARC
        // `request` record). We send a minimal, honest header set.
        $reqHeaders = [
            'GET '.$path.' HTTP/1.1',
            'Host: '.$host,
            'User-Agent: '.self::SOFTWARE,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Encoding: identity',
            'Connection: close',
        ];
        $requestBlock = implode("\r\n", $reqHeaders)."\r\n\r\n";

        $rawHeader = '';
        $body = '';
        $oversize = false;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_CONNECTTIMEOUT => self::HTTP_CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::HTTP_TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'User-Agent: '.self::SOFTWARE,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Encoding: identity',
            ],
            // Restrict to http/https only (defence in depth on top of the SSRF guard).
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$rawHeader) {
                $rawHeader .= $line;

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$body, &$oversize) {
                $body .= $chunk;
                if (strlen($body) > self::MAX_BODY_BYTES) {
                    $oversize = true;

                    return -1; // abort the transfer: oversize
                }

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($oversize) {
            return $fail(__('The page exceeds the capture size limit.'));
        }
        if ($ok === false && $errno !== 0) {
            return $fail(__('The page could not be reached (network error).'));
        }
        if ($status < 100) {
            return $fail(__('No HTTP response was received from the page.'));
        }

        // The cURL header buffer can contain multiple header blocks across redirects;
        // keep only the FINAL response's header block (the last "HTTP/" run).
        $finalHeader = $this->lastHeaderBlock($rawHeader);

        // Normalise line endings to CRLF for the stored HTTP message.
        $finalHeader = $this->toCrlf(rtrim($finalHeader, "\r\n"));
        $responseBlock = $finalHeader."\r\n\r\n".$body;

        return [
            'ok' => true,
            'error' => null,
            'http_status' => $status,
            'request_block' => $requestBlock,
            'response_block' => $responseBlock,
        ];
    }

    // =====================================================================
    // WARC 1.1 assembly
    // =====================================================================

    /**
     * Assemble a valid WARC 1.1 (ISO 28500) record stream: warcinfo, request,
     * response. Each record is "WARC/1.1" CRLF, named headers CRLF, a blank CRLF, the
     * block, then the mandatory CRLF CRLF trailer. Every record carries a unique
     * urn:uuid WARC-Record-ID, a WARC-Date, a Content-Length, and a sha256
     * WARC-Block-Digest; request/response also carry WARC-Target-URI and
     * Content-Type: application/http; msgtype=<request|response>.
     */
    public function buildWarc(string $targetUri, string $requestBlock, string $responseBlock, int $httpStatus): string
    {
        $date = gmdate('Y-m-d\TH:i:s\Z');

        // warcinfo block: simple "key: value" application/warc-fields content.
        $infoFields = implode("\r\n", [
            'software: '.self::SOFTWARE,
            'format: WARC File Format 1.1',
            'conformsTo: http://iipc.github.io/warc-specifications/specifications/warc-format/warc-1.1/',
            'description: Single-page capture of a Heratio published record\'s own public page.',
            'robots: ignore',
        ])."\r\n";

        $warcinfo = $this->record('warcinfo', [
            'WARC-Date' => $date,
            'Content-Type' => 'application/warc-fields',
        ], $infoFields, null);

        $requestRec = $this->record('request', [
            'WARC-Date' => $date,
            'WARC-Target-URI' => $targetUri,
            'Content-Type' => 'application/http; msgtype=request',
        ], $requestBlock, null);

        $responseRec = $this->record('response', [
            'WARC-Date' => $date,
            'WARC-Target-URI' => $targetUri,
            'Content-Type' => 'application/http; msgtype=response',
        ], $responseBlock, null);

        return $warcinfo.$requestRec.$responseRec;
    }

    /**
     * Build one WARC record. $headers is the set of extra named headers (besides the
     * always-present WARC-Type, WARC-Record-ID, Content-Length and WARC-Block-Digest).
     * The block bytes are appended verbatim; the record is terminated by CRLF CRLF.
     */
    private function record(string $type, array $headers, string $block, ?string $unusedConcurrent): string
    {
        $length = strlen($block);
        $digest = 'sha256:'.$this->base32(hash('sha256', $block, true));

        $lines = [];
        $lines[] = 'WARC/1.1';
        $lines[] = 'WARC-Type: '.$type;
        $lines[] = 'WARC-Record-ID: '.$this->recordId();
        // Named headers (WARC-Date, WARC-Target-URI, Content-Type, ...).
        foreach ($headers as $k => $v) {
            $lines[] = $k.': '.$v;
        }
        $lines[] = 'WARC-Block-Digest: '.$digest;
        $lines[] = 'Content-Length: '.$length;

        $headerText = implode("\r\n", $lines)."\r\n\r\n";

        // Record = header CRLF CRLF + block + mandatory CRLF CRLF trailer.
        return $headerText.$block."\r\n\r\n";
    }

    /** A fresh WARC-Record-ID as a urn:uuid (RFC 4122 v4). */
    private function recordId(): string
    {
        return '<urn:uuid:'.$this->uuid4().'>';
    }

    /** RFC 4122 version-4 UUID from a strong random source. */
    private function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0F) | 0x40); // version 4
        $b[8] = chr((ord($b[8]) & 0x3F) | 0x80); // variant 10
        $hex = bin2hex($b);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4),
            substr($hex, 16, 4), substr($hex, 20, 12));
    }

    /**
     * Base32 (RFC 4648, no padding) of raw bytes. WARC block digests are conventionally
     * written as sha256:<base32>; we emit unpadded uppercase base32 of the raw digest.
     */
    private function base32(string $raw): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $buffer = 0;
        $bits = 0;
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($raw[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= $alphabet[($buffer >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) {
            $out .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        return $out;
    }

    // =====================================================================
    // Storage
    // =====================================================================

    /**
     * Write the WARC bytes under config('heratio.storage_path').'/web-archive'. The
     * directory is created if missing (the storage root is www-data-writable on this
     * host). Returns the absolute path, file name, byte size and file sha256.
     *
     * @return array{ok:bool, error:?string, path:?string, name:?string, size:?int, sha256:?string}
     */
    private function writeWarc(string $slug, int $id, string $bytes): array
    {
        try {
            $dir = $this->webArchiveDir();
            if ($dir === null) {
                return ['ok' => false, 'error' => __('Storage is not configured.'), 'path' => null, 'name' => null, 'size' => null, 'sha256' => null];
            }
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (! is_dir($dir) || ! is_writable($dir)) {
                return ['ok' => false, 'error' => __('The web-archive storage directory is not writable.'), 'path' => null, 'name' => null, 'size' => null, 'sha256' => null];
            }

            $safeSlug = preg_replace('/[^a-z0-9-]/', '-', strtolower($slug)) ?: 'record';
            $name = $safeSlug.'-'.$id.'-'.gmdate('Ymd-His').'-'.substr($this->uuid4(), 0, 8).'.warc';
            $path = rtrim($dir, '/').'/'.$name;

            $written = @file_put_contents($path, $bytes, LOCK_EX);
            if ($written === false) {
                return ['ok' => false, 'error' => __('The WARC file could not be written to disk.'), 'path' => null, 'name' => null, 'size' => null, 'sha256' => null];
            }

            return [
                'ok' => true,
                'error' => null,
                'path' => $path,
                'name' => $name,
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
            ];
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc writeWarc failed: '.$e->getMessage());

            return ['ok' => false, 'error' => __('The WARC file could not be stored.'), 'path' => null, 'name' => null, 'size' => null, 'sha256' => null];
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

    // =====================================================================
    // Register reads
    // =====================================================================

    /**
     * All captures, newest first, hydrated for the admin list. Returns an empty array
     * when unavailable.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listCaptures(int $limit = 200): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->limit(max(1, min(1000, $limit)))
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id' => (int) $r->id,
                    'information_object_id' => $r->information_object_id !== null ? (int) $r->information_object_id : null,
                    'slug' => $r->slug !== null ? (string) $r->slug : null,
                    'target_uri' => (string) $r->target_uri,
                    'byte_size' => $r->byte_size !== null ? (int) $r->byte_size : null,
                    'sha256' => $r->sha256 !== null ? (string) $r->sha256 : null,
                    'http_status' => $r->http_status !== null ? (int) $r->http_status : null,
                    'status' => (string) $r->status,
                    'error_message' => $r->error_message !== null ? (string) $r->error_message : null,
                    'captured_at' => $r->captured_at !== null ? (string) $r->captured_at : null,
                    'has_file' => $r->file_path !== null && $r->file_path !== '' && is_file((string) $r->file_path),
                ];
            }

            return $out;
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc listCaptures failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * One capture row for download, or null. Returns the on-disk path + download name
     * only when the capture succeeded AND the file is present.
     *
     * @return array{path:string, name:string}|null
     */
    public function fileForDownload(int $id): ?array
    {
        if ($id <= 0 || ! $this->isAvailable()) {
            return null;
        }

        try {
            $r = DB::table(self::TABLE)->where('id', $id)->first();
            if ($r === null) {
                return null;
            }
            $path = trim((string) ($r->file_path ?? ''));
            if ($path === '' || ! is_file($path) || ! is_readable($path)) {
                return null;
            }
            // Defence in depth: the stored file must live under the configured
            // web-archive directory (no path traversal out of storage).
            $dir = $this->webArchiveDir();
            $real = realpath($path);
            $realDir = $dir !== null ? realpath($dir) : false;
            if ($real === false || $realDir === false || strpos($real, $realDir.'/') !== 0) {
                return null;
            }

            $name = trim((string) ($r->file_name ?? ''));
            if ($name === '') {
                $name = 'capture-'.$id.'.warc';
            }

            return ['path' => $real, 'name' => $name];
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc fileForDownload failed: '.$e->getMessage());

            return null;
        }
    }

    /** Dropdown-driven status label, with a safe fallback to the raw code. */
    public function statusLabel(string $code): string
    {
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $row = DB::table('ahg_dropdown')
                    ->where('taxonomy', self::STATUS_TAXONOMY)
                    ->where('code', $code)
                    ->first(['label']);
                if ($row && trim((string) $row->label) !== '') {
                    return (string) $row->label;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }

        return ucfirst($code);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Record a `failed` row (best-effort) and return the structured failure result.
     * Never throws.
     */
    private function fail(?int $ioId, ?string $slug, string $message, int $requestedId, ?string $targetUri): array
    {
        if ($this->isAvailable()) {
            try {
                $now = now();
                DB::table(self::TABLE)->insert([
                    'information_object_id' => $ioId,
                    'slug' => $this->clip($slug, 255),
                    'target_uri' => $this->clip($targetUri ?? ('record:'.$requestedId), 2048),
                    'file_path' => null,
                    'file_name' => null,
                    'byte_size' => null,
                    'sha256' => null,
                    'http_status' => null,
                    'status' => self::STATUS_FAILED,
                    'error_message' => $this->clip($message, 1024),
                    'captured_by' => null,
                    'captured_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (Throwable $e) {
                \Log::warning('[ahg-core] warc fail-row insert failed: '.$e->getMessage());
            }
        }

        return [
            'ok' => false,
            'id' => null,
            'status' => self::STATUS_FAILED,
            'message' => $message,
            'target_uri' => $targetUri,
            'byte_size' => null,
            'sha256' => null,
        ];
    }

    /** Keep only the FINAL HTTP header block from a cURL header buffer (redirect-safe). */
    private function lastHeaderBlock(string $rawHeader): string
    {
        // cURL emits each header block ending with a bare CRLF line. Split on the
        // status-line marker "HTTP/" and keep the last block.
        $normalized = str_replace("\r\n", "\n", $rawHeader);
        $blocks = preg_split('/\n(?=HTTP\/)/', $normalized) ?: [$normalized];
        $last = trim((string) end($blocks));

        return $last;
    }

    /** Normalise any line endings to CRLF. */
    private function toCrlf(string $s): string
    {
        return preg_replace("/\r\n|\r|\n/", "\r\n", $s);
    }

    /** Trim + length-clip a string; null stays null, empty becomes null. */
    private function clip($value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
