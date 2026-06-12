<?php

/**
 * InjectOembedLink - Heratio ahg-core
 *
 * Adds the oEmbed autodiscovery <link rel="alternate" type="application/json+oembed">
 * into the <head> of HTML record pages, so an oEmbed consumer that scrapes a pasted
 * record URL discovers the provider endpoint at /oembed?url={thisPage} without any
 * edit to the (locked / shared) theme head/layout. This is the documented response-
 * middleware cousin of the View::composer markup-injection pattern, and the direct
 * sibling of InjectOpenSearchLink.
 *
 * DB-FREE BY DESIGN: the href is built from the CURRENT REQUEST URL only
 * ($request->fullUrl()) - there is NO database query in this middleware. It does
 * not resolve the record, check publication status, or read any setting. The
 * /oembed endpoint itself does the (guarded, read-only) resolution when a consumer
 * actually calls it; an autodiscovery link on a non-record page is harmless (the
 * endpoint simply returns a clean 404 for a non-record url).
 *
 * Best-effort and non-destructive: it only touches successful text/html GET
 * responses, only when a </head> exists and the oEmbed link is not already present,
 * and it wraps everything in a guard so it can NEVER break a page render. Only a
 * single <link> element is added immediately before </head>; the body, scripts and
 * all other markup are untouched.
 *
 * Registered exactly like InjectOpenSearchLink / InjectSplatViewer: pushed onto the
 * `web` middleware group from AhgCoreServiceProvider::boot() inside app->booted().
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Middleware;

use Closure;
use Illuminate\Http\Request;
use Throwable;

class InjectOembedLink
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if (! $this->eligible($request, $response)) {
                return $response;
            }

            $html = (string) $response->getContent();
            if ($html === ''
                || stripos($html, '</head>') === false
                || stripos($html, 'application/json+oembed') !== false) {
                return $response;
            }

            // DB-FREE: the discovery href is /oembed?url={current page URL}, built
            // purely from the request. No record lookup, no setting read.
            $pageUrl  = $request->fullUrl();
            $endpoint = url('/oembed').'?url='.rawurlencode($pageUrl);
            $href     = htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8');
            $title    = htmlspecialchars('oEmbed', ENT_QUOTES, 'UTF-8');

            $link = '<link rel="alternate" type="application/json+oembed" href="'
                .$href.'" title="'.$title.'">';

            $pos  = stripos($html, '</head>');
            $html = substr($html, 0, $pos).$link."\n".substr($html, $pos);

            $response->setContent($html);
        } catch (Throwable $e) {
            // never break the page
        }

        return $response;
    }

    /**
     * Only successful text/html GET responses on a SINGLE-segment path (the shape
     * of an archival-record show URL) are candidates. Admin / multi-segment pages
     * are skipped - they are never embeddable records, so an oEmbed link there
     * would be noise. This keeps the link to record-shaped pages without any DB
     * query (the path shape is read from the request alone).
     */
    private function eligible(Request $request, $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }
        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() !== 200) {
            return false;
        }
        $ct = (string) $response->headers->get('Content-Type', '');
        if ($ct !== '' && ! str_contains($ct, 'text/html')) {
            return false;
        }
        if (! method_exists($response, 'getContent') || ! method_exists($response, 'setContent')) {
            return false;
        }

        // Single-segment, slug-shaped path only (mirrors the /{slug} record route
        // constraint). Multi-segment / dotted / prefixed paths are not records.
        $path = trim((string) $request->path(), '/');
        if ($path === '' || str_contains($path, '/')) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*$/', strtolower($path));
    }
}
