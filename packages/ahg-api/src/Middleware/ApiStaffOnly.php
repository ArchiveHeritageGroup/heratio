<?php

/**
 * ApiStaffOnly - admits only administrators and editors, by session or API key.
 * Use after api.auth, which resolves the caller into api_user_id.
 *
 * For surfaces that cannot apply record-level disclosure rules, such as raw
 * SPARQL over the RiC triple store: the store is not filtered by publication,
 * embargo, classification or ICIP protocol, so it is not for the public.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgApi\Middleware;

use AhgCore\Services\AclService;
use Closure;
use Illuminate\Http\Request;

class ApiStaffOnly
{
    public function handle(Request $request, Closure $next)
    {
        $id = (int) $request->attributes->get('api_user_id');
        $user = $id ? (object) ['id' => $id] : null;
        if (! $user || ! (AclService::isAdministrator($user) || AclService::isEditor($user))) {
            return response()->json(['error' => 'Forbidden', 'message' => 'This endpoint is for administrators and editors only.'], 403);
        }

        return $next($request);
    }
}
