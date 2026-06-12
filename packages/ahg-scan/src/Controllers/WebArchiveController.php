<?php

/**
 * WebArchiveController - Heratio ahg-scan
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

namespace AhgScan\Controllers;

use AhgScan\Services\WarcReplayService;
use AhgScan\Services\WebArchiveCaptureService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin surface for single-page web archiving (WARC 1.1).
 *
 * Every action is empty-state safe: if the table is not yet installed, the
 * pages render an informative notice rather than throwing a 500.
 */
class WebArchiveController extends Controller
{
    public function __construct(
        protected WebArchiveCaptureService $service,
        protected WarcReplayService $replayService
    ) {
    }

    /** List captures + the submit-URL form. */
    public function index()
    {
        $installed = $this->installed();

        $captures = collect();
        if ($installed) {
            $captures = DB::table('web_archive_capture')
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        }

        return view('ahg-scan::admin.web-archive.index', [
            'installed' => $installed,
            'captures' => $captures,
            'storageHint' => rtrim((string) config('heratio.storage_path'), '/').'/web-archive',
        ]);
    }

    /** Handle the submit-URL form: capture, then redirect back with a notice. */
    public function store(Request $request)
    {
        if (! $this->installed()) {
            return redirect()->route('web-archive.index')
                ->with('error', 'The web-archive store is not installed yet. Reload this page to trigger auto-install.');
        }

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url'],
        ]);

        $id = $this->service->capture($validated['url'], optional($request->user())->id);

        if ($id === null) {
            return redirect()->route('web-archive.index')
                ->with('error', 'Capture could not be recorded.');
        }

        $row = DB::table('web_archive_capture')->find($id);
        if ($row && $row->status === 'captured') {
            return redirect()->route('web-archive.show', $id)
                ->with('notice', 'Captured to WARC.');
        }

        return redirect()->route('web-archive.show', $id)
            ->with('error', 'Capture recorded as failed: '.($row->error ?? 'unknown error'));
    }

    /** Per-capture detail: row metadata + parsed WARC headers + download link. */
    public function show($id)
    {
        if (! $this->installed()) {
            return redirect()->route('web-archive.index')
                ->with('error', 'The web-archive store is not installed yet.');
        }

        $capture = DB::table('web_archive_capture')->find((int) $id);
        if ($capture === null) {
            return redirect()->route('web-archive.index')
                ->with('error', 'Capture not found.');
        }

        $warcHeaders = [];
        $warcExists = false;
        if ($capture->warc_path && is_file($capture->warc_path) && is_readable($capture->warc_path)) {
            $warcExists = true;
            $warcHeaders = $this->parseWarcHeaders($capture->warc_path);
        }

        return view('ahg-scan::admin.web-archive.show', [
            'capture' => $capture,
            'warcHeaders' => $warcHeaders,
            'warcExists' => $warcExists,
        ]);
    }

    /**
     * Replay a captured snapshot back from its stored WARC file.
     *
     * This is a SINGLE-DOCUMENT replay: it serves the archived page document
     * exactly as it was captured, with the original Content-Type, but it never
     * fetches, executes, or proxies any live subresource (CSS, JS, images,
     * trackers). For HTML it injects a fixed "archived snapshot" banner and a
     * restrictive Content-Security-Policy so the page cannot reach out to the
     * live web. Non-HTML payloads are offered as a download alongside a small
     * metadata page. Multi-resource replay is future work.
     *
     * Never 500s: a missing or corrupt WARC degrades to a clean notice page.
     */
    public function replay($id)
    {
        if (! $this->installed()) {
            return redirect()->route('web-archive.index')
                ->with('error', 'The web-archive store is not installed yet.');
        }

        $capture = DB::table('web_archive_capture')->find((int) $id);
        if ($capture === null) {
            return redirect()->route('web-archive.index')
                ->with('error', 'Capture not found.');
        }

        if (($capture->status ?? null) !== 'captured' || ! $capture->warc_path) {
            return $this->replayUnavailable($capture, 'This capture has no archived WARC file to replay.');
        }

        $result = $this->replayService->replay($capture->warc_path);
        if (! ($result['ok'] ?? false)) {
            return $this->replayUnavailable($capture, $result['error'] ?? 'The archived snapshot could not be replayed.');
        }

        $contentType = $result['content_type'] ?: ($capture->content_type ?: 'application/octet-stream');
        $isHtml = stripos((string) $contentType, 'html') !== false;

        if ($isHtml) {
            return $this->serveArchivedHtml($capture, $result, (string) $contentType);
        }

        return $this->serveArchivedNonHtml($capture, $result, (string) $contentType);
    }

    /** Stream the WARC file as a download. */
    public function download($id)
    {
        if (! $this->installed()) {
            abort(404);
        }

        $capture = DB::table('web_archive_capture')->find((int) $id);
        if ($capture === null || ! $capture->warc_path || ! is_file($capture->warc_path)) {
            abort(404);
        }

        return response()->download($capture->warc_path, basename($capture->warc_path), [
            'Content-Type' => 'application/warc',
        ]);
    }

    // ------------------------------------------------------------------
    // Replay serving
    // ------------------------------------------------------------------

    /**
     * Serve an archived HTML snapshot. The fixed banner is injected at the top
     * of the document and a restrictive CSP is set so the page cannot load any
     * live subresource (scripts, trackers, frames). The archived body is served
     * verbatim apart from the prepended banner.
     */
    protected function serveArchivedHtml($capture, array $result, string $contentType): Response
    {
        $banner = $this->bannerHtml($capture);
        $body = (string) ($result['body'] ?? '');
        $html = $this->injectBanner($body, $banner);

        $charset = $this->charsetFromContentType($contentType);
        $serveType = 'text/html'.($charset !== null ? '; charset='.$charset : '; charset=UTF-8');

        $response = response($html, 200);
        $this->applySafeHeaders($response, $serveType);

        return $response;
    }

    /**
     * Serve a non-HTML archived payload. To avoid handing the browser an
     * unframed live-looking document, the body is offered as a download
     * (Content-Disposition: attachment) and a small HTML metadata page is shown
     * with a direct download link and the snapshot banner.
     *
     * The actual bytes are streamed when ?raw=1 is present (the download link),
     * carrying the original Content-Type and an attachment disposition.
     */
    protected function serveArchivedNonHtml($capture, array $result, string $contentType): Response
    {
        if (request()->query('raw') === '1') {
            $body = (string) ($result['body'] ?? '');
            $filename = $this->downloadFilename($capture, $contentType);

            $response = response($body, 200);
            $this->applySafeHeaders($response, $contentType);
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

            return $response;
        }

        $html = view('ahg-scan::admin.web-archive.replay-binary', [
            'capture' => $capture,
            'contentType' => $contentType,
            'targetUri' => $result['target_uri'] ?? $capture->url,
            'byteSize' => isset($result['body']) ? strlen((string) $result['body']) : null,
            'rawUrl' => route('web-archive.replay', $capture->id).'?raw=1',
        ])->render();

        $response = response($html, 200);
        $this->applySafeHeaders($response, 'text/html; charset=UTF-8');

        return $response;
    }

    /**
     * Render the "snapshot unavailable" notice (missing / corrupt WARC). Always
     * a clean 200 HTML page, never a 500.
     */
    protected function replayUnavailable($capture, string $message): Response
    {
        $html = view('ahg-scan::admin.web-archive.replay-unavailable', [
            'capture' => $capture,
            'message' => $message,
        ])->render();

        $response = response($html, 200);
        $this->applySafeHeaders($response, 'text/html; charset=UTF-8');

        return $response;
    }

    /**
     * Apply the safe-serving headers used for every replayed response:
     *   - A restrictive Content-Security-Policy that blocks every live network
     *     fetch (default-src 'none'), allows only inline styles for the banner,
     *     and forbids framing.
     *   - X-Frame-Options DENY + nosniff + a no-referrer policy.
     *   - X-Robots-Tag noindex so a replayed snapshot is never search-indexed.
     * These ensure a replayed page cannot reach the live web or load trackers.
     */
    protected function applySafeHeaders($response, string $contentType): void
    {
        $csp = implode('; ', [
            "default-src 'none'",
            "img-src 'self' data:",
            "style-src 'unsafe-inline'",
            "font-src 'self' data:",
            "form-action 'none'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
        ]);

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Build the fixed archived-snapshot banner markup. Self-contained inline
     * styles only (no external CSS), so it renders under the strict CSP.
     */
    protected function bannerHtml($capture): string
    {
        $when = $this->capturedWhen($capture);
        $url = htmlspecialchars((string) $capture->url, ENT_QUOTES, 'UTF-8');
        $whenEsc = htmlspecialchars($when, ENT_QUOTES, 'UTF-8');

        $style = 'all:initial;display:block;box-sizing:border-box;position:sticky;top:0;left:0;'
            .'width:100%;z-index:2147483647;background:#5a1f1f;color:#fff;'
            .'font:13px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;'
            .'padding:8px 14px;border-bottom:2px solid #2d0f0f;text-align:left;';
        $linkStyle = 'color:#ffd9d9;text-decoration:underline;word-break:break-all;';

        return '<div role="alert" style="'.$style.'">'
            .'<strong>ARCHIVED SNAPSHOT</strong> &middot; captured '.$whenEsc
            .' from <a href="'.$url.'" rel="noopener noreferrer nofollow" style="'.$linkStyle.'">'.$url.'</a>'
            .' &middot; this is a stored copy, not the live site. Links and embedded resources are not replayed.'
            .'</div>';
    }

    /**
     * Inject the banner immediately after <body> if present, otherwise after
     * the first <html...> tag, otherwise at the very top of the document.
     */
    protected function injectBanner(string $html, string $banner): string
    {
        if (preg_match('/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);

            return substr($html, 0, $pos).$banner.substr($html, $pos);
        }

        if (preg_match('/<html\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);

            return substr($html, 0, $pos).$banner.substr($html, $pos);
        }

        return $banner.$html;
    }

    protected function capturedWhen($capture): string
    {
        $raw = $capture->captured_at ?? $capture->created_at ?? null;
        if ($raw === null || $raw === '') {
            return 'an unknown date';
        }
        try {
            return \Illuminate\Support\Carbon::parse($raw)->toDayDateTimeString();
        } catch (\Throwable $e) {
            return (string) $raw;
        }
    }

    protected function charsetFromContentType(string $contentType): ?string
    {
        if (preg_match('/charset=([\w\-]+)/i', $contentType, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function downloadFilename($capture, string $contentType): string
    {
        $host = parse_url((string) $capture->url, PHP_URL_HOST) ?: 'snapshot';
        $base = Str::slug($host).'-'.(int) $capture->id;
        $ext = $this->extensionForType($contentType);

        return $base.($ext !== null ? '.'.$ext : '');
    }

    protected function extensionForType(string $contentType): ?string
    {
        $type = Str::lower(trim(Str::before($contentType, ';')));
        $map = [
            'application/pdf' => 'pdf',
            'application/json' => 'json',
            'text/plain' => 'txt',
            'text/css' => 'css',
            'text/csv' => 'csv',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
        ];

        return $map[$type] ?? null;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function installed(): bool
    {
        try {
            return Schema::hasTable('web_archive_capture');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Read just the named WARC header fields from each record's framing block
     * (the lines before the first blank line of each record). Bounded read so a
     * large WARC does not load fully into memory; for a single-response WARC the
     * relevant headers are always near the top.
     */
    protected function parseWarcHeaders(string $path): array
    {
        $records = [];
        try {
            $fh = @fopen($path, 'rb');
            if ($fh === false) {
                return [];
            }

            $current = null;
            $inHeaders = false;
            $maxLines = 4000; // safety bound
            $read = 0;

            while (($line = fgets($fh)) !== false && $read < $maxLines) {
                $read++;
                $line = rtrim($line, "\r\n");

                if ($line === 'WARC/1.1') {
                    if ($current !== null) {
                        $records[] = $current;
                    }
                    $current = [];
                    $inHeaders = true;

                    continue;
                }

                if ($current === null) {
                    continue;
                }

                if ($inHeaders) {
                    if ($line === '') {
                        // end of this record's header block; skip the block body
                        $inHeaders = false;

                        continue;
                    }
                    $pos = strpos($line, ':');
                    if ($pos !== false) {
                        $name = trim(substr($line, 0, $pos));
                        $value = trim(substr($line, $pos + 1));
                        $current[$name] = $value;
                    }
                }
            }

            if ($current !== null && ! empty($current)) {
                $records[] = $current;
            }

            fclose($fh);
        } catch (\Throwable $e) {
            return $records;
        }

        return $records;
    }
}
