<?php

/**
 * RecordDoiView middleware.
 *
 * Issue #654 Phase 3. Sits in the global stack and fires a DoiViewed event
 * whenever an information-object show route returns a 2xx response and the
 * matched record has an active DOI. The matching IO is resolved by slug
 * from the request path so this works against the slug catch-all in
 * ahg-information-object-manage without touching that locked package.
 *
 * The DOI lookup is a single indexed query against ahg_doi; if it misses
 * the middleware is a no-op. Failures are swallowed so a Datacite outage
 * cannot ever 500 a show page.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Http\Middleware;

use AhgDoiManage\Events\DoiViewed;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordDoiView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! $request->isMethod('GET')) {
                return $response;
            }
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                return $response;
            }

            $slug = $this->extractSlug($request->path());
            if ($slug === null) {
                return $response;
            }

            if (! Schema::hasTable('ahg_doi') || ! Schema::hasTable('slug')) {
                return $response;
            }

            $row = DB::table('ahg_doi as d')
                ->join('slug as s', 's.object_id', '=', 'd.information_object_id')
                ->where('s.slug', $slug)
                ->whereIn('d.status', ['minted', 'active'])
                ->select('d.doi', 'd.information_object_id')
                ->first();

            if (! $row || empty($row->doi)) {
                return $response;
            }

            event(new DoiViewed(
                doi: (string) $row->doi,
                informationObjectId: (int) $row->information_object_id,
                url: $request->fullUrl(),
                userAgent: substr((string) $request->userAgent(), 0, 200),
            ));
        } catch (Throwable $e) {
            // Best-effort - never block the response.
        }

        return $response;
    }

    /**
     * Pull the candidate slug off a path. Anything with a /known/ admin
     * prefix is skipped to mirror the slug catch-all's exclusion list.
     */
    protected function extractSlug(string $path): ?string
    {
        $path = trim($path, '/');
        if ($path === '') {
            return null;
        }
        if (str_contains($path, '/')) {
            // Show URLs are top-level ({slug}); anything deeper is not.
            return null;
        }
        $blocked = [
            'admin', 'ingest', 'research', 'glam', 'api', 'login', 'logout',
            'register', 'home', 'dashboard', 'reports', 'help', 'settings',
        ];
        if (in_array(strtolower($path), $blocked, true)) {
            return null;
        }

        return $path;
    }
}
