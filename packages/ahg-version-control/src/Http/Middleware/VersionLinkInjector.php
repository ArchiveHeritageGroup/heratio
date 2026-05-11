<?php

/**
 * VersionLinkInjector - server-side HTML response filter that adds a
 * "Version history (N)" banner to IO / actor / sector show pages.
 *
 * Mirrors the PSIS ViewLinkInjector listener. Avoids touching locked show
 * blades: the banner is injected post-render via a regex pass on the
 * response body. The injection is silent on failure (no anchor matched,
 * non-HTML response, entity unknown, no captured versions).
 *
 * Activation conditions:
 *   - GET request, not XHR
 *   - Response Content-Type starts with text/html
 *   - URL resolves to an IO / actor / repository / donor / rightsholder by slug
 *   - The entity has at least one row in *_version
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgVersionControl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class VersionLinkInjector
{
    /** Object_type values (display_object_config.object_type) that map to IO. */
    private const IO_TYPES = ['archive', 'photo', 'dam', 'library', 'gallery', 'museum'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (!$this->isInjectionCandidate($request, $response)) {
                return $response;
            }

            $entity = $this->resolveEntityFromRequest($request);
            if ($entity === null) {
                return $response;
            }

            [$entityType, $entityId] = $entity;

            $count = $this->countVersions($entityType, $entityId);
            if ($count === 0) {
                return $response;
            }

            $banner = $this->buildBanner($entityType, $entityId, $count);
            $body = (string) $response->getContent();
            $modified = $this->inject($body, $banner);
            if ($modified !== null) {
                $response->setContent($modified);
            }
        } catch (\Throwable $e) {
            // Never break a show-page render on injection failure.
        }

        return $response;
    }

    private function isInjectionCandidate(Request $request, Response $response): bool
    {
        if ($request->method() !== 'GET' || $request->ajax() || $request->wantsJson()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && !str_starts_with($contentType, 'text/html')) {
            return false;
        }

        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }

    /**
     * Reverse-lookup the entity from the request URL. Heratio's IO show is
     * `/{slug}` (catch-all) and actor / repository shows are nested under
     * /actor/{slug}, /repository/{slug}. We try slug-based lookup first,
     * then fall back to numeric id in route parameters.
     *
     * @return array{0:string,1:int}|null  ['information_object'|'actor', id]
     */
    private function resolveEntityFromRequest(Request $request): ?array
    {
        $path = trim($request->path(), '/');
        if ($path === '' || str_starts_with($path, 'admin/') || str_starts_with($path, 'api/')) {
            return null;
        }

        // Single-segment URL → likely an IO slug via catch-all.
        if (!str_contains($path, '/')) {
            $entity = $this->resolveBySlug($path);
            if ($entity !== null) {
                return $entity;
            }
        }

        // /actor/{slug}, /repository/{slug}, /donor/{slug}, /rightsholder/{slug}
        $segments = explode('/', $path);
        if (count($segments) === 2 && in_array($segments[0], ['actor', 'repository', 'donor', 'rightsholder'], true)) {
            $entity = $this->resolveBySlug($segments[1]);
            if ($entity !== null) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:int}|null
     */
    private function resolveBySlug(string $slug): ?array
    {
        if ($slug === '' || strlen($slug) > 255) {
            return null;
        }

        $row = DB::table('slug')->where('slug', $slug)->select('object_id')->first();
        if (!$row || empty($row->object_id)) {
            return null;
        }

        $objectId = (int) $row->object_id;

        $isIo = DB::table('information_object')->where('id', $objectId)->exists();
        if ($isIo) {
            return ['information_object', $objectId];
        }

        $isActor = DB::table('actor')->where('id', $objectId)->exists();
        if ($isActor) {
            return ['actor', $objectId];
        }

        return null;
    }

    private function countVersions(string $entityType, int $entityId): int
    {
        $table = $entityType === 'actor' ? 'actor_version' : 'information_object_version';
        $fk    = $entityType === 'actor' ? 'actor_id'      : 'information_object_id';

        if (!Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->where($fk, $entityId)->count();
    }

    private function buildBanner(string $entityType, int $entityId, int $count): string
    {
        $url = url("/version-control/{$entityType}/{$entityId}");
        $label = sprintf(__('Version history (%d)'), $count);

        $urlAttr   = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $labelText = htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<div class="alert alert-info py-1 px-2 mb-2 d-inline-block ahg-vc-banner" style="font-size:.9rem;">'
            . '<i class="fas fa-history me-1"></i><a href="' . $urlAttr . '">' . $labelText . '</a>'
            . '</div>';
    }

    /**
     * Insert the banner just after the first matching anchor in the
     * response body. Bails (returns null) if no anchor is found.
     */
    private function inject(string $body, string $banner): ?string
    {
        $patterns = [
            '#(<div[^>]+id=["\']main-column["\'][^>]*>)#i',
            '#(<main[^>]*>)#i',
            '#(<section[^>]+class=["\'][^"\']*\bcontent\b[^"\']*["\'][^>]*>)#i',
            '#(<div[^>]+class=["\'][^"\']*\bpage-content\b[^"\']*["\'][^>]*>)#i',
        ];

        foreach ($patterns as $pattern) {
            $count = 0;
            $replaced = preg_replace($pattern, '$1' . $banner, $body, 1, $count);
            if ($count > 0 && is_string($replaced)) {
                return $replaced;
            }
        }

        return null;
    }
}
