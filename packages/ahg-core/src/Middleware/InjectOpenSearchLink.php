<?php

/**
 * InjectOpenSearchLink - Heratio ahg-core
 *
 * Adds the OpenSearch autodiscovery <link rel="search"> into the <head> of HTML
 * responses, so a browser (or an aggregator that scrapes the home page) discovers
 * the catalogue's search provider at /opensearch.xml without any edit to the
 * (locked / shared) theme head/layout. This is the documented response-middleware
 * cousin of the View::composer pattern for injecting markup into locked callers.
 *
 * Best-effort and non-destructive: it only touches successful text/html GET
 * responses, only when a </head> exists and the link is not already present, and
 * it wraps everything in a guard so it can NEVER break a page render. It does not
 * alter the body, scripts, or any other markup - a single <link> element is added
 * immediately before </head>.
 *
 * Registered exactly like InjectSplatViewer: pushed onto the `web` middleware
 * group from AhgCoreServiceProvider::boot() inside app->booted() (so it runs after
 * the HTTP kernel syncs its web group; ahg-core boots early).
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

class InjectOpenSearchLink
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
                || stripos($html, 'application/opensearchdescription+xml') !== false) {
                return $response;
            }

            $title = htmlspecialchars($this->title(), ENT_QUOTES, 'UTF-8');
            $href  = htmlspecialchars(url('/opensearch.xml'), ENT_QUOTES, 'UTF-8');
            $link  = '<link rel="search" type="application/opensearchdescription+xml" href="'
                .$href.'" title="'.$title.'">';

            // Insert immediately before the first </head>. str_ireplace with a
            // count limit keeps it to the first occurrence only.
            $pos = stripos($html, '</head>');
            $html = substr($html, 0, $pos).$link."\n".substr($html, $pos);

            $response->setContent($html);
        } catch (Throwable $e) {
            // never break the page
        }

        return $response;
    }

    /** Only successful, text/html GET responses are candidates. */
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
        // Only standard renderable responses carry getContent() usefully.
        return method_exists($response, 'getContent') && method_exists($response, 'setContent');
    }

    /** The autodiscovery link title (the institution / site name, guarded). */
    private function title(): string
    {
        try {
            $setting = \Illuminate\Support\Facades\DB::table('setting')
                ->where('name', 'siteTitle')->first();
            if ($setting) {
                $i18n = \Illuminate\Support\Facades\DB::table('setting_i18n')
                    ->where('id', $setting->id)->where('culture', 'en')->first();
                $value = trim((string) ($i18n->value ?? ''));
                if ($value !== '') {
                    return $value.' catalogue';
                }
            }
        } catch (Throwable $e) {
            // fall through
        }

        return 'Catalogue search';
    }
}
