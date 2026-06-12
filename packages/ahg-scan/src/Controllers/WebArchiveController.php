<?php

/**
 * WebArchiveController - Heratio ahg-scan
 *
 * heratio#1244 - the SINGLE web-archive admin surface at /admin/web-archive. It offers
 * BOTH capture modes over ONE list, ONE detail, ONE download, ONE replay, and ONE asset
 * route, and it owns the live web-archive.* route names + URIs:
 *
 *   GET  /admin/web-archive                list captures + both capture forms
 *   POST /admin/web-archive                archive a submitted URL          (url mode)
 *   POST /admin/web-archive/capture        snapshot a published record's page (record mode)
 *   GET  /admin/web-archive/{id}           per-capture detail + WARC record headers
 *   GET  /admin/web-archive/{id}/replay    render the captured page FROM the WARC
 *   GET  /admin/web-archive/{id}/asset     serve ONE archived subresource FROM the WARC
 *   GET  /admin/web-archive/{id}/download  stream the stored .warc (application/warc)
 *
 * This controller is a thin SURFACE: all the heavy lifting lives in the reusable engine
 * services in ahg-core (the base package ahg-scan depends on):
 *   - AhgCore\Services\WarcCaptureService - the capture engine for BOTH modes
 *       (SSRF-guarded record-page capture with same-host subresources, and SSRF-guarded
 *        operator-submitted-URL capture), writing the single warc_capture table + the
 *        .warc files on disk under the configured storage path.
 *   - AhgCore\Services\WarcReplayService  - the well-tested length-delimited WARC 1.1
 *        parser + URI -> response model used by replay + asset.
 *
 * Replay is strictly read-only and serves ONLY from the stored WARC - it NEVER performs a
 * live fetch. A missing / corrupt / empty WARC degrades to a clean "snapshot unavailable"
 * page, never a 500. Off-host assets are not captured, so they do not replay - that gap
 * keeps #1244 open.
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

use AhgCore\Services\WarcCaptureService;
use AhgCore\Services\WarcReplayService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single admin surface for web archiving (WARC 1.1). Every action is empty-state safe:
 * if the warc_capture table is not yet installed, the pages render an informative notice
 * rather than throwing a 500.
 */
class WebArchiveController extends Controller
{
    public function __construct(
        protected WarcCaptureService $capture,
        protected WarcReplayService $replay
    ) {
    }

    /** List captures + both capture forms (archive a URL, and capture a record page). */
    public function index()
    {
        return view('ahg-scan::admin.web-archive.index', [
            'available' => $this->capture->isAvailable(),
            'captures' => $this->capture->listCaptures(),
            'storageHint' => rtrim((string) config('heratio.storage_path'), '/').'/web-archive',
        ]);
    }

    /**
     * Mode (a): archive an operator-submitted general URL. Keeps the original http/https
     * URL validation and adds the engine's public-host SSRF guard (never loosened); the
     * engine fetches the page and writes a mode = url row + .warc.
     */
    public function store(Request $request)
    {
        if (! $this->capture->isAvailable()) {
            return redirect()->route('web-archive.index')
                ->with('error', __('The web-archive store is not installed yet. Reload this page to trigger auto-install.'));
        }

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url'],
        ]);

        $result = $this->capture->captureUrl($validated['url'], optional($request->user())->id);

        if (! empty($result['ok'])) {
            return redirect()->route('web-archive.show', $result['id'])
                ->with('notice', __('Captured to WARC.').' ('.number_format((int) ($result['byte_size'] ?? 0)).' '.__('bytes').')');
        }

        if (! empty($result['id'])) {
            return redirect()->route('web-archive.show', $result['id'])
                ->with('error', __('Capture recorded as failed:').' '.($result['message'] ?? __('unknown error')));
        }

        return redirect()->route('web-archive.index')
            ->with('error', $result['message'] ?? __('Capture could not be recorded.'));
    }

    /**
     * Mode (b): snapshot a PUBLISHED record's own public page (by record id). The engine
     * derives the record's own canonical url(), SSRF-validates it (same host, own record),
     * and captures the page plus its same-host subresources into a mode = record row.
     */
    public function captureRecord(Request $request)
    {
        if (! $this->capture->isAvailable()) {
            return redirect()->route('web-archive.index')
                ->with('error', __('The web-archive store is not installed yet. Reload this page to trigger auto-install.'));
        }

        $validated = $request->validate([
            'information_object_id' => ['required', 'integer', 'min:2'],
        ]);

        $result = $this->capture->capture((int) $validated['information_object_id'], Auth::id());

        if (! empty($result['ok'])) {
            $subCount = (int) ($result['subresource_count'] ?? 0);
            $subNote = $subCount > 0
                ? ' '.trans_choice(
                    '{1}+ :count same-host subresource.|[2,*]+ :count same-host subresources.',
                    $subCount,
                    ['count' => $subCount]
                )
                : ' '.__('(page only; no same-host subresources).');

            return redirect()->route('web-archive.show', $result['id'])
                ->with('notice', __('Page captured to a WARC file.').' ('.number_format((int) ($result['byte_size'] ?? 0)).' '.__('bytes').')'.$subNote);
        }

        if (! empty($result['id'])) {
            return redirect()->route('web-archive.show', $result['id'])
                ->with('error', __('Capture recorded as failed:').' '.($result['message'] ?? __('unknown error')));
        }

        return redirect()->route('web-archive.index')
            ->with('error', $result['message'] ?? __('The page could not be captured.'));
    }

    /** Per-capture detail: row metadata + parsed WARC headers + replay/download links. */
    public function show($id)
    {
        if (! $this->capture->isAvailable()) {
            return redirect()->route('web-archive.index')
                ->with('error', __('The web-archive store is not installed yet.'));
        }

        $capture = DB::table(WarcCaptureService::TABLE)->find((int) $id);
        if ($capture === null) {
            return redirect()->route('web-archive.index')
                ->with('error', __('Capture not found.'));
        }

        $warcHeaders = [];
        $warcExists = false;
        if ($capture->file_path && is_file($capture->file_path) && is_readable($capture->file_path)) {
            $warcExists = true;
            $warcHeaders = $this->parseWarcHeaders($capture->file_path);
        }

        return view('ahg-scan::admin.web-archive.show', [
            'capture' => $capture,
            'warcHeaders' => $warcHeaders,
            'warcExists' => $warcExists,
        ]);
    }

    /**
     * Replay the captured MAIN page FROM the stored WARC (never the live site). Delegates
     * the parse to the ahg-core engine: WarcReplayService builds the URI -> response model,
     * and the archived HTML is served with its same-host subresource URLs rewritten to the
     * asset route, an "Archived snapshot" banner prepended, and a restrictive
     * Content-Security-Policy so the page cannot beacon out to the live site. A missing /
     * corrupt / empty WARC degrades to a clean notice page, never a 500.
     */
    public function replay($id)
    {
        $row = $this->replay->captureRow((int) $id);
        if ($row === null) {
            return $this->snapshotUnavailable(__('That capture could not be found.'));
        }

        $model = $this->replay->buildModel($row);
        if ($model === null || empty($model['main'])) {
            return $this->snapshotUnavailable(
                __('This snapshot is unavailable: the archived WARC is missing, empty, or could not be read.')
            );
        }

        $main = $model['main'];
        $body = (string) ($main['body'] ?? '');
        $contentType = strtolower((string) ($main['content_type'] ?? ''));

        // Only HTML is rewritten + framed; anything else is served verbatim from the WARC.
        if (! str_contains($contentType, 'html')) {
            $resp = response($body, 200);
            $resp->headers->set('Content-Type', $main['content_type'] ?: 'application/octet-stream');
            $this->applyReplayCsp($resp);

            return $resp;
        }

        $rewritten = $this->rewriteHtml(
            $body,
            (string) ($model['main_uri'] ?? ($row['target_uri'] ?? '')),
            (int) $id,
            array_keys($model['resources'] ?? [])
        );

        $framed = $this->bannerHtml($row).$rewritten;

        $resp = response($framed, 200);
        $resp->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $resp->headers->set('X-Content-Type-Options', 'nosniff');
        $this->applyReplayCsp($resp);

        return $resp;
    }

    /**
     * Serve ONE archived subresource FROM the WARC by its captured URI (the `u` query
     * param). 404 when the URI is not in this WARC - replay serves ONLY from the WARC and
     * NEVER falls through to a live fetch.
     */
    public function asset($id, Request $request)
    {
        $uri = (string) $request->query('u', '');
        if (trim($uri) === '') {
            abort(404);
        }

        $row = $this->replay->captureRow((int) $id);
        if ($row === null) {
            abort(404);
        }

        $resource = $this->replay->findResource($row, $uri);
        if ($resource === null) {
            // Archived-only: the URI is not in the WARC. No live fallback. 404.
            abort(404);
        }

        $resp = response((string) ($resource['body'] ?? ''), 200);
        $ct = (string) ($resource['content_type'] ?? '');
        $resp->headers->set('Content-Type', $ct !== '' ? $ct : 'application/octet-stream');
        $resp->headers->set('X-Content-Type-Options', 'nosniff');
        $this->applyReplayCsp($resp);

        return $resp;
    }

    /** Stream the stored .warc file as a download (Content-Type application/warc). */
    public function download($id)
    {
        $file = $this->capture->fileForDownload((int) $id);
        if ($file === null) {
            abort(404);
        }

        return response()->download($file['path'], $file['name'], [
            'Content-Type' => 'application/warc',
        ]);
    }

    // ------------------------------------------------------------------
    // Replay rendering helpers (mirror the ahg-core engine's replay output)
    // ------------------------------------------------------------------

    /**
     * A clean "snapshot unavailable" page (HTTP 200, not a 500). Rendered inline so it
     * never depends on the WARC having parsed.
     */
    protected function snapshotUnavailable(string $message): Response
    {
        $backUrl = e(route('web-archive.index'));
        $msg = e($message);
        $title = e(__('Archived snapshot unavailable'));
        $back = e(__('Back to web archive'));
        $html = <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f8f9fa;color:#212529}
.wrap{max-width:640px;margin:8vh auto;padding:2rem;background:#fff;border:1px solid #dee2e6;border-radius:.5rem;text-align:center}
.ic{font-size:2.5rem;color:#adb5bd}.t{font-size:1.25rem;font-weight:600;margin:.5rem 0}
.m{color:#6c757d;margin:.75rem 0 1.5rem}.b{display:inline-block;padding:.5rem 1rem;background:#0d6efd;color:#fff;border-radius:.375rem;text-decoration:none}</style>
</head><body><div class="wrap"><div class="ic">&#9888;</div>
<div class="t">{$title}</div><div class="m">{$msg}</div>
<a class="b" href="{$backUrl}">{$back}</a></div></body></html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** The "Archived snapshot - captured <when>" banner prepended to a replayed page. */
    protected function bannerHtml(array $row): string
    {
        $when = trim((string) ($row['captured_at'] ?? ''));
        $whenLabel = $when !== ''
            ? __('Archived snapshot - captured :when', ['when' => $when])
            : __('Archived snapshot');
        $note = __('This page is served entirely from the stored WARC file, not the live site. Links and off-host assets may not work.');
        $download = route('web-archive.download', ['id' => $row['id']]);
        $back = route('web-archive.index');

        $whenLabel = e($whenLabel);
        $note = e($note);
        $download = e($download);
        $back = e($back);
        $dlLabel = e(__('Download WARC'));
        $backLabel = e(__('Web archive'));

        return <<<HTML
<div style="position:sticky;top:0;z-index:2147483647;background:#664d03;color:#fff;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;font-size:13px;line-height:1.4;padding:8px 14px;border-bottom:2px solid #ffc107;box-shadow:0 1px 4px rgba(0,0,0,.3)">
<strong style="background:#ffc107;color:#000;padding:1px 6px;border-radius:3px;margin-right:8px">&#128190; {$whenLabel}</strong>
<span style="opacity:.95">{$note}</span>
<span style="float:right">
<a href="{$download}" style="color:#ffe69c;text-decoration:underline;margin-left:12px">{$dlLabel}</a>
<a href="{$back}" style="color:#ffe69c;text-decoration:underline;margin-left:12px">{$backLabel}</a>
</span></div>
HTML;
    }

    /**
     * Apply a restrictive Content-Security-Policy to a replayed response so archived
     * content cannot beacon out to the live site: everything is pinned to 'self' (this
     * app's own origin, where the replay + asset routes live), inline + data URIs are
     * allowed for the archived markup's own inline styles/scripts, but no off-origin
     * connect / frame / form-action is permitted.
     */
    protected function applyReplayCsp($response): void
    {
        $csp = implode('; ', [
            "default-src 'self'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "script-src 'self' 'unsafe-inline'",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "form-action 'self'",
            "base-uri 'none'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Rewrite a captured page's same-host subresource URLs so they point at the asset route
     * (so the page renders entirely from the WARC, not the live site). Rewrites <link href>,
     * <script src>, <img src>, <source src>, <img/source srcset>, and url(...) inside inline
     * <style> blocks. Only URLs that resolve to a captured resource (present in
     * $capturedUris) are rewritten; everything else is left as-is (off-host / uncaptured
     * assets honestly do not load - the documented gap). The original <base href> is
     * neutralised so relative URLs we did not rewrite do not resolve against the live host.
     */
    protected function rewriteHtml(string $html, string $pageUri, int $id, array $capturedUris): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $captured = [];
        foreach ($capturedUris as $u) {
            $captured[$this->replay->normalizeUri((string) $u)] = true;
        }

        $assetBase = route('web-archive.asset', ['id' => $id]);
        $toAsset = function (string $absUri) use ($assetBase): string {
            return $assetBase.'?u='.rawurlencode($absUri);
        };

        $rewriteRef = function (string $ref) use ($pageUri, $captured, $toAsset): ?string {
            $abs = $this->resolveAgainst($ref, $pageUri);
            if ($abs === null) {
                return null;
            }
            $norm = $this->replay->normalizeUri($abs);
            if ($norm === '' || ! isset($captured[$norm])) {
                return null;
            }

            return $toAsset($norm);
        };

        // Neutralise any <base href> so un-rewritten relative URLs don't hit the live host.
        $html = preg_replace('/<base\b[^>]*\bhref\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)[^>]*>/i', '<!-- base neutralised by replay -->', $html);

        $html = $this->rewriteAttr($html, 'link', 'href', $rewriteRef);
        $html = $this->rewriteAttr($html, 'script', 'src', $rewriteRef);
        $html = $this->rewriteAttr($html, 'img', 'src', $rewriteRef);
        $html = $this->rewriteAttr($html, 'source', 'src', $rewriteRef);

        $html = $this->rewriteSrcset($html, $rewriteRef);

        $html = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function ($m) use ($rewriteRef) {
            return str_replace($m[1], $this->rewriteCssUrls($m[1], $rewriteRef), $m[0]);
        }, $html);

        return $html;
    }

    /**
     * Rewrite one attribute (e.g. src / href) on all occurrences of a tag, replacing the
     * value with the asset-route URL when $rewriteRef maps it to a captured resource.
     */
    protected function rewriteAttr(string $html, string $tag, string $attr, callable $rewriteRef): string
    {
        $pattern = '/<'.$tag.'\b[^>]*>/i';

        return preg_replace_callback($pattern, function ($m) use ($attr, $rewriteRef) {
            $full = $m[0];
            $valPattern = '/(\b'.$attr.'\s*=\s*)("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';

            return preg_replace_callback($valPattern, function ($vm) use ($rewriteRef) {
                $prefix = $vm[1];
                $raw = $vm[3] ?? ($vm[4] ?? ($vm[5] ?? ''));
                $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
                $new = $rewriteRef($decoded);
                if ($new === null) {
                    return $vm[0];
                }

                return $prefix.'"'.htmlspecialchars($new, ENT_QUOTES).'"';
            }, $full);
        }, $html) ?? $html;
    }

    /** Rewrite each captured URL inside any srcset="" attribute on <img>/<source>. */
    protected function rewriteSrcset(string $html, callable $rewriteRef): string
    {
        $pattern = '/<(?:img|source)\b[^>]*>/i';

        return preg_replace_callback($pattern, function ($m) use ($rewriteRef) {
            return preg_replace_callback('/(\bsrcset\s*=\s*)("([^"]*)"|\'([^\']*)\')/i', function ($sm) use ($rewriteRef) {
                $prefix = $sm[1];
                $set = $sm[3] ?? ($sm[4] ?? '');
                $parts = [];
                foreach (explode(',', $set) as $cand) {
                    $cand = trim($cand);
                    if ($cand === '') {
                        continue;
                    }
                    $tokens = preg_split('/\s+/', $cand);
                    $url = (string) ($tokens[0] ?? '');
                    $descriptor = count($tokens) > 1 ? ' '.implode(' ', array_slice($tokens, 1)) : '';
                    $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
                    $new = $rewriteRef($decoded);
                    $parts[] = ($new !== null ? $new : $url).$descriptor;
                }

                return $prefix.'"'.htmlspecialchars(implode(', ', $parts), ENT_QUOTES).'"';
            }, $m[0]) ?? $m[0];
        }, $html) ?? $html;
    }

    /** Rewrite url(...) references inside a CSS string to the asset route where captured. */
    protected function rewriteCssUrls(string $css, callable $rewriteRef): string
    {
        return preg_replace_callback('/url\(\s*(\'[^\']*\'|"[^"]*"|[^)]*)\s*\)/i', function ($m) use ($rewriteRef) {
            $raw = trim($m[1], " \t\n\r\0\x0B\"'");
            if ($raw === '') {
                return $m[0];
            }
            $new = $rewriteRef(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
            if ($new === null) {
                return $m[0];
            }

            return 'url("'.$new.'")';
        }, $css) ?? $css;
    }

    /**
     * Resolve a (possibly relative) reference against the captured page URI into an
     * absolute URL, mirroring the capture engine's resolution so the rewritten key matches
     * a captured WARC-Target-URI. Returns null for non-fetchable schemes.
     */
    protected function resolveAgainst(string $ref, string $base): ?string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        $lc = strtolower($ref);
        if (str_starts_with($lc, 'data:')
            || str_starts_with($lc, 'javascript:')
            || str_starts_with($lc, 'mailto:')
            || str_starts_with($lc, 'tel:')
            || str_starts_with($lc, 'blob:')
            || str_starts_with($lc, 'about:')
            || str_starts_with($ref, '#')) {
            return null;
        }

        $b = parse_url($base);
        if (! is_array($b) || ! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $scheme = strtolower((string) $b['scheme']);
        $host = (string) $b['host'];
        $basePath = (string) ($b['path'] ?? '/');

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $ref)) {
            return $ref;
        }
        if (str_starts_with($ref, '//')) {
            return $scheme.':'.$ref;
        }
        if (str_starts_with($ref, '/')) {
            return $scheme.'://'.$host.$ref;
        }
        $dir = $basePath;
        $slash = strrpos($dir, '/');
        $dir = $slash === false ? '/' : substr($dir, 0, $slash + 1);

        return $scheme.'://'.$host.$this->normalizePath($dir.$ref);
    }

    /** Collapse ./ and ../ segments in a URL path (mirrors the capture engine). */
    protected function normalizePath(string $path): string
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
        if (str_ends_with($path, '/') && ! str_ends_with($out, '/')) {
            $out .= '/';
        }

        return $out === '' ? '/' : $out;
    }

    // ------------------------------------------------------------------
    // Detail helpers
    // ------------------------------------------------------------------

    /**
     * Read just the named WARC header fields from each record's framing block (the lines
     * before the first blank line of each record). Bounded read so a large WARC does not
     * load fully into memory.
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
