<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use AhgCore\Services\AclService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckAcl
{
    /**
     * Routes whose target is a description (or its digital objects / media),
     * so a per-repository rule can be checked against it (heratio#1776). On
     * any other route a repository-scoped account has only its global rights.
     */
    private const RECORD_ROUTES = ['informationobject', 'digitalobject', 'media/', 'provenance/'];

    public function handle(Request $request, Closure $next, string $action = 'read'): Response
    {
        $userId = Auth::id();

        // Users with repository rules: every record the request touches - the
        // route's record, the parent it files under, the repository it names -
        // must pass for this action. Everyone else: the global check, as before.
        if ($userId && AclService::repositoryRules($userId) !== [] && ($targets = $this->targets($request)) !== []) {
            foreach ($targets as $id) {
                if (! AclService::hasPermission($userId, $action, $id)) {
                    abort(403, 'Insufficient permissions');
                }
            }

            return $next($request);
        }

        if (! AclService::hasPermission($userId, $action)) {
            if (! Auth::check()) {
                return redirect()->route('login');
            }

            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }

    /** @return int[] object ids named by the route and the submitted form */
    private function targets(Request $request): array
    {
        $route = $request->route();
        $uri = $route ? ltrim($route->uri(), '/') : '';
        $onRecordRoute = false;
        foreach (self::RECORD_ROUTES as $p) {
            if (str_starts_with($uri, $p)) {
                $onRecordRoute = true;
                break;
            }
        }
        if (! $onRecordRoute) {
            return [];
        }

        $ids = [];
        foreach (['slug', 'id'] as $p) {
            if (($v = $route->parameter($p)) !== null) {
                $ids[] = $v;
            }
        }
        foreach (['parent_id', 'parent', 'repository_id', 'object_id', 'information_object_id'] as $f) {
            if (($v = $request->input($f)) !== null && $v !== '') {
                $ids[] = $v;
            }
        }

        $out = [];
        foreach ($ids as $v) {
            $id = ctype_digit((string) $v) ? (int) $v : (int) DB::table('slug')->where('slug', (string) $v)->value('object_id');
            // An unresolvable name is not a pass: id 0 matches no rule and fails.
            $out[] = $id;
        }

        // A top-level description files under the root (id 1), which belongs to
        // no repository; the repository it names is what decides.
        if (in_array(1, $out, true) && $request->filled('repository_id')) {
            $out = array_diff($out, [1]);
        }

        return array_values(array_unique($out));
    }
}
