<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use AhgCore\Services\AclService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Default deny for repository-scoped accounts (heratio#1776).
 *
 * Many modules guard their staff pages and even their saves with "signed in"
 * alone, so a scoped account - whose password may be public, like a course's
 * training login - could open staff forms everywhere and, where a module never
 * checks a permission, write. Rather than chase each module, a scoped account
 * may use only:
 *  - what a visitor could use without signing in;
 *  - its own work: descriptions, their digital objects, media, rights and
 *    provenance (each still checked per record by the acl: middleware);
 *  - a few personal pages and form helpers (autocomplete lookups).
 * Every other signed-in page is refused, forms included. Users who are not
 * repository-scoped never reach the checks below.
 */
class ScopedAccountGuard
{
    /** Signed-in route prefixes a scoped account may use. */
    private const ALLOWED = [
        'informationobject', 'digitalobject', 'media/', 'rights/', 'provenance/',
        '{slug}/object/', '{slug}/ext-rights',
        'logout', 'user/profile', 'user/password', 'user/passwordEdit', 'user/passwordReset',
        'clipboard', 'favorites', 'help', 'glam', 'search',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $userId = Auth::id();
        if (! $userId || ! AclService::isRepositoryScoped((int) $userId)) {
            return $next($request);
        }
        $route = $request->route();
        if (! $route) {
            return $next($request);
        }

        $uri = ltrim($route->uri(), '/');
        foreach (self::ALLOWED as $p) {
            if (str_starts_with($uri, $p)) {
                return $next($request);
            }
        }
        if ($request->isMethodSafe() && stripos($uri, 'autocomplete') !== false) {
            return $next($request);
        }

        // Open to visitors anyway: allowed.
        $signedInOnly = false;
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && (str_starts_with($m, 'auth') || str_starts_with($m, 'acl') || $m === 'admin'
                || str_contains($m, 'Authenticate') || str_contains($m, 'RequireAdmin') || str_contains($m, 'RequireAuth'))) {
                $signedInOnly = true;
                break;
            }
        }
        if (! $signedInOnly) {
            return $next($request);
        }

        abort(403, __('This account is limited to its repositories and cannot use this page.'));
    }
}
