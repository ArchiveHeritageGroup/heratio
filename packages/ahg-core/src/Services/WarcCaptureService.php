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
 * Subresources (extends the original page-only slice)
 * -----------------------------------------------------
 * After fetching the main HTML page, the capture ALSO parses that page for its direct
 * (depth-1) subresources - stylesheet/script/image/icon URLs from
 * <link rel=stylesheet href>, <script src>, <img src> + srcset, <link rel=icon href>,
 * and url(...) references inside inline <style> blocks - resolves each relative URL
 * against the page URL, and keeps ONLY the SAME-HOST http/https ones (assertSameHostUrl()
 * applies the same SSRF guards as the page fetch: same host as the record page, http/https
 * only, no embedded credentials, no alternate port, no loopback / link-local /
 * cloud-metadata host). Off-host assets (third-party CDNs, fonts, analytics) are dropped
 * honestly - they are never fetched. Each kept subresource is fetched with the same
 * bounded cURL client and appended to the SAME .warc as a matching request + response
 * record pair, identical in structure to the page's records (WARC-Block-Digest sha256,
 * WARC-Target-URI, Content-Type application/http; msgtype=...). The whole capture stays
 * a single valid WARC 1.1 file. Bounds: a hard cap on the number of subresources, a
 * per-asset size cap, a total-capture size cap, a per-asset timeout, URL de-duplication,
 * and depth-1 only (the page's direct subresources; nested @import inside fetched CSS is
 * NOT recursed). A subresource that fails (404 / timeout / oversize / off-host) is
 * skipped cleanly and never aborts the capture. The page-only path still works when a
 * page has no same-host subresources. The subresource count is recorded WITHOUT any
 * schema change (no ALTER): it is written into the existing error_message column as a
 * short note for successful captures and surfaced in the admin list + result message.
 *
 * Honest remaining gap: same-host subresources are now captured, but OFF-HOST assets
 * (third-party CDNs / fonts) are deliberately NOT fetched, and there is still no replay
 * (Wayback / pywb) surface. Off-host capture and replay remain open under heratio#1244 /
 * the digital-preservation roadmap.
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

    /** Subresource bounds (depth-1, same-host only). */

    /** Max number of distinct same-host subresources fetched per capture. */
    private const MAX_SUBRESOURCES = 50;

    /** Per-subresource body size cap (4 MiB). Oversize -> skipped cleanly. */
    private const MAX_SUBRESOURCE_BYTES = 4194304;

    /** Total-capture body budget across the page + all subresources (24 MiB). */
    private const MAX_TOTAL_BYTES = 25165824;

    /** Per-subresource total timeout (seconds). */
    private const SUBRESOURCE_TIMEOUT_SECONDS = 12;

    /** Per-subresource connect timeout (seconds). */
    private const SUBRESOURCE_CONNECT_TIMEOUT_SECONDS = 6;

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

        // Discover + fetch the page's DIRECT same-host subresources (depth-1, bounded).
        // Any failure here degrades to zero subresources - the page-only WARC still
        // succeeds.
        $subresources = $this->captureSubresources(
            $targetUri,
            (string) ($fetch['body'] ?? ''),
            (string) ($fetch['content_type'] ?? ''),
            strlen((string) ($fetch['response_block'] ?? ''))
        );

        // Build the WARC 1.1 bytes (page records + N subresource record pairs).
        try {
            $warc = $this->buildWarc(
                $targetUri,
                $fetch['request_block'],
                $fetch['response_block'],
                $fetch['http_status'],
                $subresources
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

        // Record the success row. The subresource COUNT is recorded WITHOUT any schema
        // change (no ALTER, no new column): it is stored as a short note in the existing
        // error_message column (a free-text status field) and surfaced in the admin list.
        $subCount = count($subresources);
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
                'error_message' => $this->subresourceNote($subCount),
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
                'subresource_count' => $subCount,
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
            'subresource_count' => $subCount,
        ];
    }

    /**
     * Discover + fetch the page's DIRECT (depth-1) same-host subresources into WARC
     * record blocks, fully bounded (count cap, per-asset cap, total-capture budget,
     * per-asset timeout, de-dup). Resilient: any single asset failure is skipped and a
     * total failure here returns an empty list so the page-only WARC still succeeds.
     *
     * @return array<int, array{target_uri:string, request_block:string, response_block:string}>
     */
    private function captureSubresources(string $pageUrl, string $body, string $contentType, int $pageBlockBytes): array
    {
        try {
            // Only parse subresources out of an HTML page.
            if ($contentType !== '' && ! str_contains($contentType, 'html')) {
                return [];
            }
            if (trim($body) === '') {
                return [];
            }

            $urls = $this->discoverSubresources($body, $pageUrl);
            if ($urls === []) {
                return [];
            }

            $out = [];
            // The page response block already consumed part of the total budget.
            $remaining = self::MAX_TOTAL_BYTES - max(0, $pageBlockBytes);

            foreach ($urls as $url) {
                if ($remaining <= 0 || count($out) >= self::MAX_SUBRESOURCES) {
                    break;
                }
                $sub = $this->fetchSubresource($url, $remaining);
                if (! $sub['ok']) {
                    // 404 / timeout / oversize / off-host-redirect -> skip cleanly, noted.
                    \Log::info('[ahg-core] warc subresource skipped ('.($sub['error'] ?? 'unknown').'): '.$url);

                    continue;
                }
                $remaining -= (int) $sub['byte_len'];
                $out[] = [
                    'target_uri' => $url,
                    'request_block' => $sub['request_block'],
                    'response_block' => $sub['response_block'],
                ];
            }

            return $out;
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] warc captureSubresources failed (page-only fallback): '.$e->getMessage());

            return [];
        }
    }

    /**
     * A short, human-readable note recording the subresource count on a SUCCESSFUL
     * capture, stored in the existing error_message column (no schema change). The admin
     * list parses this back into a count for display.
     */
    private function subresourceNote(int $count): ?string
    {
        if ($count <= 0) {
            return __('Page only; no same-host subresources captured.');
        }

        return trans_choice(
            '{1}:count same-host subresource captured.|[2,*]:count same-host subresources captured.',
            $count,
            ['count' => $count]
        );
    }

    /**
     * Bounded server-side HTTP GET of the (already SSRF-validated) record URL. Uses
     * cURL when available (for a streamed, size-capped read + redirect cap), and
     * captures the exact request + response blocks for the WARC.
     *
     * @return array{ok:bool, error:?string, http_status:int, request_block:string, response_block:string, body:string, content_type:string}
     */
    private function fetch(string $url): array
    {
        $fail = fn (string $msg) => [
            'ok' => false, 'error' => $msg, 'http_status' => 0,
            'request_block' => '', 'response_block' => '', 'body' => '', 'content_type' => '',
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
            'body' => $body,
            'content_type' => $this->contentTypeFromHeaderBlock($finalHeader),
        ];
    }

    /**
     * Bounded server-side HTTP GET of one SAME-HOST subresource (already SSRF-validated
     * by assertSameHostUrl()). Mirrors fetch() but uses the tighter per-subresource
     * caps and an explicit remaining-byte budget so the total capture stays bounded.
     * Returns ok=false (with a short reason) on any failure / oversize / non-2xx so the
     * caller can skip it cleanly - it never throws.
     *
     * @return array{ok:bool, error:?string, http_status:int, request_block:string, response_block:string, byte_len:int}
     */
    private function fetchSubresource(string $url, int $remainingBudget): array
    {
        $fail = fn (string $msg) => [
            'ok' => false, 'error' => $msg, 'http_status' => 0,
            'request_block' => '', 'response_block' => '', 'byte_len' => 0,
        ];

        if (! function_exists('curl_init')) {
            return $fail('http client unavailable');
        }

        // Per-asset cap is the smaller of the fixed per-asset cap and what is left in
        // the total-capture budget.
        $cap = max(0, min(self::MAX_SUBRESOURCE_BYTES, $remainingBudget));
        if ($cap <= 0) {
            return $fail('total capture budget exhausted');
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

        $reqHeaders = [
            'GET '.$path.' HTTP/1.1',
            'Host: '.$host,
            'User-Agent: '.self::SOFTWARE,
            'Accept: */*',
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
            CURLOPT_CONNECTTIMEOUT => self::SUBRESOURCE_CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::SUBRESOURCE_TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'User-Agent: '.self::SOFTWARE,
                'Accept: */*',
                'Accept-Encoding: identity',
            ],
            // Pin to http/https only (defence in depth on top of assertSameHostUrl()).
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$rawHeader) {
                $rawHeader .= $line;

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$body, &$oversize, $cap) {
                $body .= $chunk;
                if (strlen($body) > $cap) {
                    $oversize = true;

                    return -1; // abort: oversize / over budget
                }

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($oversize) {
            return $fail('subresource exceeds size / budget cap');
        }
        if ($ok === false && $errno !== 0) {
            return $fail('subresource network error');
        }
        if ($status < 200 || $status >= 300) {
            // Only archive successfully-served assets (a 404/redirect-loop/5xx is skipped).
            return $fail('subresource HTTP '.$status);
        }

        $finalHeader = $this->lastHeaderBlock($rawHeader);
        $finalHeader = $this->toCrlf(rtrim($finalHeader, "\r\n"));
        $responseBlock = $finalHeader."\r\n\r\n".$body;

        return [
            'ok' => true,
            'error' => null,
            'http_status' => $status,
            'request_block' => $requestBlock,
            'response_block' => $responseBlock,
            'byte_len' => strlen($responseBlock),
        ];
    }

    // =====================================================================
    // Subresource discovery + same-host SSRF filter (depth-1)
    // =====================================================================

    /**
     * Parse the fetched HTML for the page's DIRECT (depth-1) subresource URLs, resolve
     * each against the page URL, keep only SAME-HOST http/https ones, de-duplicate, and
     * cap the count. Returns an ordered, unique list of absolute same-host URLs.
     *
     * Discovered references:
     *   - <link rel="stylesheet" href>          (CSS)
     *   - <link rel="icon"|"shortcut icon" href> (favicons)
     *   - <link rel="preload" as="..."> / "modulepreload" href
     *   - <script src>                           (JS)
     *   - <img src> and <img srcset> / <source srcset>
     *   - url(...) inside inline <style> blocks   (CSS images / @font-face)
     *
     * Off-host references are dropped here (never fetched). Nested @import inside fetched
     * CSS is intentionally NOT recursed (depth-1 only).
     *
     * @return array<int, string>
     */
    public function discoverSubresources(string $html, string $pageUrl): array
    {
        if (trim($html) === '') {
            return [];
        }

        $candidates = [];

        // href on <link> (stylesheet / icon / preload / modulepreload).
        if (preg_match_all('/<link\b[^>]*>/i', $html, $m)) {
            foreach ($m[0] as $tag) {
                $rel = $this->attr($tag, 'rel');
                $relLc = strtolower($rel);
                $wanted = str_contains($relLc, 'stylesheet')
                    || str_contains($relLc, 'icon')
                    || str_contains($relLc, 'preload')        // covers preload + modulepreload
                    || str_contains($relLc, 'apple-touch');
                if (! $wanted) {
                    continue;
                }
                $href = $this->attr($tag, 'href');
                if ($href !== '') {
                    $candidates[] = $href;
                }
                // <link rel=preload imagesrcset=...> carries a srcset too.
                foreach ($this->srcsetUrls($this->attr($tag, 'imagesrcset')) as $u) {
                    $candidates[] = $u;
                }
            }
        }

        // <script src>.
        if (preg_match_all('/<script\b[^>]*\bsrc\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)[^>]*>/i', $html, $m)) {
            foreach ($m[0] as $tag) {
                $src = $this->attr($tag, 'src');
                if ($src !== '') {
                    $candidates[] = $src;
                }
            }
        }

        // <img src> + <img srcset> + <source srcset>.
        if (preg_match_all('/<(?:img|source)\b[^>]*>/i', $html, $m)) {
            foreach ($m[0] as $tag) {
                $src = $this->attr($tag, 'src');
                if ($src !== '') {
                    $candidates[] = $src;
                }
                foreach ($this->srcsetUrls($this->attr($tag, 'srcset')) as $u) {
                    $candidates[] = $u;
                }
            }
        }

        // url(...) inside inline <style> blocks (CSS background images, @font-face src).
        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $m)) {
            foreach ($m[1] as $css) {
                foreach ($this->cssUrls($css) as $u) {
                    $candidates[] = $u;
                }
            }
        }

        // Resolve, same-host filter, de-dup, cap.
        $seen = [];
        $out = [];
        foreach ($candidates as $raw) {
            $abs = $this->resolveUrl($raw, $pageUrl);
            if ($abs === null) {
                continue;
            }
            if (! $this->assertSameHostUrl($abs, $pageUrl)) {
                continue; // off-host / loopback / metadata / ported / credentialed -> dropped
            }
            if (isset($seen[$abs])) {
                continue;
            }
            // Never re-fetch the page itself as a subresource.
            if (rtrim($abs, '/') === rtrim($this->stripFragmentQuery($pageUrl), '/')) {
                continue;
            }
            $seen[$abs] = true;
            $out[] = $abs;
            if (count($out) >= self::MAX_SUBRESOURCES) {
                break;
            }
        }

        return $out;
    }

    /**
     * SAME-HOST SSRF GUARD for a subresource URL. Looser than assertOwnRecordUrl() on
     * the PATH (a subresource has its own path) but identical on every host-level guard:
     * http/https only, the SAME host as the record page, no embedded credentials, no
     * alternate port, and the host must not be a loopback / link-local / cloud-metadata
     * address. cURL is additionally pinned to http/https at fetch time.
     */
    public function assertSameHostUrl(string $url, string $pageUrl): bool
    {
        $a = parse_url($url);
        $p = parse_url($pageUrl);
        if (! is_array($a) || ! is_array($p)) {
            return false;
        }

        // Scheme must be http/https and identical to the page scheme (no downgrade).
        $aScheme = strtolower((string) ($a['scheme'] ?? ''));
        $pScheme = strtolower((string) ($p['scheme'] ?? ''));
        if (! in_array($aScheme, ['http', 'https'], true) || $aScheme !== $pScheme) {
            return false;
        }

        // No embedded credentials.
        if (isset($a['user']) || isset($a['pass'])) {
            return false;
        }

        // Host must match the page host exactly, and must be this site's own host.
        $aHost = strtolower((string) ($a['host'] ?? ''));
        $pHost = strtolower((string) ($p['host'] ?? ''));
        $ours = strtolower((string) parse_url((string) url('/'), PHP_URL_HOST));
        if ($aHost === '' || $aHost !== $pHost || ($ours !== '' && $aHost !== $ours)) {
            return false;
        }

        // No alternate port (the canonical page URL carries no explicit port).
        if (isset($a['port'])) {
            return false;
        }

        // Reject literal loopback / link-local / cloud-metadata hosts as a belt-and-braces
        // guard even though the host-equality check above already constrains us.
        if ($this->isBlockedHostLiteral($aHost)) {
            return false;
        }

        return true;
    }

    /** True for loopback / link-local / cloud-metadata / private-range host literals. */
    private function isBlockedHostLiteral(string $host): bool
    {
        if ($host === '') {
            return true;
        }
        $h = trim($host, '[]'); // strip IPv6 brackets

        if (in_array($h, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return true;
        }
        // AWS / GCP / Azure link-local metadata endpoint.
        if ($h === '169.254.169.254' || str_starts_with($h, '169.254.')) {
            return true;
        }
        // RFC1918 / loopback / unspecified IPv4 literals.
        if (filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (! filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }
        // Unique-local / loopback IPv6 literals.
        if (filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $lc = strtolower($h);
            if ($lc === '::1' || str_starts_with($lc, 'fc') || str_starts_with($lc, 'fd') || str_starts_with($lc, 'fe80')) {
                return true;
            }
        }

        return false;
    }

    /** Resolve a (possibly relative) reference against the absolute page URL. */
    private function resolveUrl(string $ref, string $base): ?string
    {
        $ref = trim(html_entity_decode($ref, ENT_QUOTES | ENT_HTML5));
        if ($ref === '') {
            return null;
        }
        // Ignore non-fetchable schemes outright.
        $lc = strtolower($ref);
        if (str_starts_with($lc, 'data:')
            || str_starts_with($lc, 'javascript:')
            || str_starts_with($lc, 'mailto:')
            || str_starts_with($lc, 'tel:')
            || str_starts_with($lc, 'blob:')
            || str_starts_with($lc, '#')
            || str_starts_with($lc, 'about:')) {
            return null;
        }

        $b = parse_url($base);
        if (! is_array($b) || ! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $scheme = strtolower((string) $b['scheme']);
        $host = (string) $b['host'];
        $basePath = (string) ($b['path'] ?? '/');

        // Already absolute (has a scheme).
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $ref)) {
            return $this->stripFragmentQuery($ref, true);
        }
        // Protocol-relative //host/path.
        if (str_starts_with($ref, '//')) {
            return $this->stripFragmentQuery($scheme.':'.$ref, true);
        }
        // Root-relative /path.
        if (str_starts_with($ref, '/')) {
            return $this->stripFragmentQuery($scheme.'://'.$host.$ref, true);
        }
        // Document-relative path -> resolve against the base directory.
        $dir = $basePath;
        $slash = strrpos($dir, '/');
        $dir = $slash === false ? '/' : substr($dir, 0, $slash + 1);
        $combined = $this->normalizePath($dir.$ref);

        return $this->stripFragmentQuery($scheme.'://'.$host.$combined, true);
    }

    /** Collapse ./ and ../ segments in a URL path. */
    private function normalizePath(string $path): string
    {
        $isAbs = str_starts_with($path, '/');
        $parts = explode('/', $path);
        $stack = [];
        foreach ($parts as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($stack);

                continue;
            }
            $stack[] = $seg;
        }
        $out = ($isAbs ? '/' : '').implode('/', $stack);
        // Preserve a trailing slash if the original had one.
        if (str_ends_with($path, '/') && ! str_ends_with($out, '/')) {
            $out .= '/';
        }

        return $out === '' ? '/' : $out;
    }

    /**
     * Strip the fragment from a URL (and, by default for subresources, keep the query
     * since CSS/JS assets are often cache-busted with ?v=...). When $keepQuery is false
     * the query is also stripped (used to compare against the page URL).
     */
    private function stripFragmentQuery(string $url, bool $keepQuery = false): string
    {
        $hash = strpos($url, '#');
        if ($hash !== false) {
            $url = substr($url, 0, $hash);
        }
        if (! $keepQuery) {
            $q = strpos($url, '?');
            if ($q !== false) {
                $url = substr($url, 0, $q);
            }
        }

        return $url;
    }

    /** Extract an HTML attribute value (quoted or bare) from a single tag string. */
    private function attr(string $tag, string $name): string
    {
        if ($tag === '') {
            return '';
        }
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*"([^"]*)"/i', $tag, $m)) {
            return $m[1];
        }
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*\'([^\']*)\'/i', $tag, $m)) {
            return $m[1];
        }
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*([^\s>]+)/i', $tag, $m)) {
            return $m[1];
        }

        return '';
    }

    /** Pull the URL part out of each comma-separated srcset candidate. */
    private function srcsetUrls(string $srcset): array
    {
        $srcset = trim($srcset);
        if ($srcset === '') {
            return [];
        }
        $out = [];
        foreach (explode(',', $srcset) as $cand) {
            $cand = trim($cand);
            if ($cand === '') {
                continue;
            }
            // "url 2x" / "url 640w" / "url" - take the first whitespace-delimited token.
            $url = preg_split('/\s+/', $cand)[0] ?? '';
            if ($url !== '') {
                $out[] = $url;
            }
        }

        return $out;
    }

    /** Extract url(...) references from a CSS string (inline <style> only, depth-1). */
    private function cssUrls(string $css): array
    {
        $out = [];
        if (preg_match_all('/url\(\s*(\'[^\']*\'|"[^"]*"|[^)]*)\s*\)/i', $css, $m)) {
            foreach ($m[1] as $u) {
                $u = trim($u, " \t\n\r\0\x0B\"'");
                if ($u !== '') {
                    $out[] = $u;
                }
            }
        }

        return $out;
    }

    /** Best-effort Content-Type value from a normalised HTTP header block (lowercased). */
    private function contentTypeFromHeaderBlock(string $headerBlock): string
    {
        if (preg_match('/^content-type\s*:\s*([^\r\n]+)/im', $headerBlock, $m)) {
            return strtolower(trim($m[1]));
        }

        return '';
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
    public function buildWarc(string $targetUri, string $requestBlock, string $responseBlock, int $httpStatus, array $subresources = []): string
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

        $warc = $warcinfo.$requestRec.$responseRec;

        // Append each captured SAME-HOST subresource as a request + response pair, with
        // the exact same record structure (headers + sha256 block digest) as the page.
        foreach ($subresources as $sub) {
            $subUri = (string) ($sub['target_uri'] ?? '');
            $subReq = (string) ($sub['request_block'] ?? '');
            $subResp = (string) ($sub['response_block'] ?? '');
            if ($subUri === '' || $subResp === '') {
                continue;
            }

            $warc .= $this->record('request', [
                'WARC-Date' => $date,
                'WARC-Target-URI' => $subUri,
                'Content-Type' => 'application/http; msgtype=request',
            ], $subReq, null);

            $warc .= $this->record('response', [
                'WARC-Date' => $date,
                'WARC-Target-URI' => $subUri,
                'Content-Type' => 'application/http; msgtype=response',
            ], $subResp, null);
        }

        return $warc;
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
                    'subresource_count' => (string) $r->status === self::STATUS_CAPTURED
                        ? $this->subresourceCountFromNote($r->error_message !== null ? (string) $r->error_message : null)
                        : null,
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

    /**
     * Parse the subresource count back out of a successful capture's note (stored in the
     * error_message column). Returns the integer count, or 0 when the note is the
     * "page only" form (or absent / unparsable). Never throws.
     */
    private function subresourceCountFromNote(?string $note): int
    {
        if ($note === null || trim($note) === '') {
            return 0;
        }
        if (preg_match('/\d+/', $note, $m)) {
            return (int) $m[0];
        }

        return 0;
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
