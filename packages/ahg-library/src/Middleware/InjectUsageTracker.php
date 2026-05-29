<?php

/**
 * InjectUsageTracker - global response middleware that injects the
 * usage-tracker.js bundle + a <meta name="library-item-id"> tag into any
 * library-item show page response.
 *
 * Lets the COUNTER R5 per-event instrumentation run without editing the
 * locked layout templates.
 *
 * Issue: heratio#766
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgLibrary\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InjectUsageTracker
{
    /** Cookie carrying the anonymised per-session usage token. */
    private const SESSION_COOKIE = 'lib_usage_sid';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        try {
            if (!$this->shouldInject($request, $response)) {
                return $response;
            }

            $libraryItemId = $this->resolveLibraryItemId($request);
            $isOpac = $this->isOpacPage($request);

            // Inject on item pages (item-bound beacons) OR on the OPAC search
            // page (search-event capture). Nothing else.
            if (!$libraryItemId && !$isOpac) return $response;

            $content = (string) $response->getContent();
            if (!str_contains($content, '</head>')) return $response;
            if (str_contains($content, 'usage-tracker.js')) return $response; // already injected

            $inject = '';
            if ($libraryItemId) {
                $inject .= '<meta name="library-item-id" content="' . (int) $libraryItemId . '">';
            }
            if ($isOpac) {
                $inject .= '<meta name="library-usage-search" content="1">';
            }
            $inject .= '<script src="/vendor/ahg-library/js/usage-tracker.js" defer></script>';

            $content = str_replace('</head>', $inject . '</head>', $content);
            $response->setContent($content);

            // Drop an anonymised, http-only session token so UsageEventController
            // can de-duplicate unique-item / search metrics per browser session.
            $this->ensureSessionCookie($request, $response);
        } catch (Throwable) {
            // Never let instrumentation break a page.
        }

        return $response;
    }

    /**
     * The OPAC search surface lives at /opac (and /opac?q=...), which is not
     * under the library/ prefix, so it needs its own match for search capture.
     */
    private function isOpacPage(Request $request): bool
    {
        if (!$request->isMethod('GET')) return false;
        $path = trim($request->path(), '/');
        return $path === 'opac' || str_starts_with($path, 'opac?');
    }

    /**
     * Set the lib_usage_sid cookie once per browser if it is missing. Value is
     * a random opaque token (hashed again server-side before storage).
     */
    private function ensureSessionCookie(Request $request, Response $response): void
    {
        if ($request->cookie(self::SESSION_COOKIE)) {
            return;
        }
        $token = Str::random(40);
        // 1-year, http-only, lax. Not a tracking identifier across sites - only
        // used to group COUNTER unique-item / search events within this site.
        $response->headers->setCookie(new Cookie(
            self::SESSION_COOKIE,
            $token,
            now()->addYear()->getTimestamp(),
            '/',
            null,
            $request->isSecure(),
            true,        // httpOnly
            false,
            Cookie::SAMESITE_LAX
        ));
    }

    private function shouldInject(Request $request, Response $response): bool
    {
        if (!$response->headers->has('Content-Type')) return false;
        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) return false;
        if ($response->getStatusCode() >= 300) return false;

        $path = $request->path();
        // Inject on library show pages only - keeps the beacon traffic relevant.
        foreach (['library/', 'glam/browse'] as $prefix) {
            if (str_starts_with($path, $prefix)) return true;
        }
        // Also inject on the slug-based IO show pages, where the meta-resolver
        // checks the slug against library_item via information_object.
        return $request->isMethod('GET') && substr_count($path, '/') === 0;
    }

    /**
     * Resolve a library_item_id from the request. Three paths:
     *  - library/{id} URL: take id directly
     *  - library show route bind: read from request->route attributes
     *  - slug-based /{slug} resolve: lookup information_object by slug then library_item by io_id
     */
    private function resolveLibraryItemId(Request $request): ?int
    {
        $route = $request->route();
        if ($route) {
            foreach (['id', 'libraryItem', 'library_item_id', 'libraryItemId'] as $k) {
                $v = $route->parameter($k);
                if (is_numeric($v) && (int) $v > 0) return (int) $v;
            }
        }

        // Slug path: try DB lookup. Cached lightly so repeated injections on
        // hot pages don't hit MySQL every request.
        $path = trim($request->path(), '/');
        if ($path === '' || str_contains($path, '/')) return null;
        if (!preg_match('/^[a-z0-9][a-z0-9-]+$/i', $path)) return null;

        try {
            $cacheKey = 'usage_tracker_slug:' . $path;
            return cache()->remember($cacheKey, 300, function () use ($path) {
                $row = DB::table('library_item')
                    ->join('information_object', 'information_object.id', '=', 'library_item.information_object_id')
                    ->where('information_object.slug', $path)
                    ->select('library_item.id')
                    ->first();
                return $row ? (int) $row->id : null;
            });
        } catch (Throwable) {
            return null;
        }
    }
}
