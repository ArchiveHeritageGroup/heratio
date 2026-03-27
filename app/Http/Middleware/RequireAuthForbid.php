<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Like RequireAuth but returns 403 instead of redirecting to login.
 * Matches AtoM behavior where certain routes show "forbidden" to anonymous users
 * rather than redirecting to the login page.
 */
class RequireAuthForbid
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}
