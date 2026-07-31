<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use AhgCore\Services\AclService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            // A logged-out visitor is sent to login (matching the 'auth'
            // middleware) and returned to the page after signing in, rather than
            // shown a bare 403. JSON/API callers still get a 401.
            if ($request->expectsJson()) {
                abort(401, 'Unauthenticated');
            }

            return redirect()->guest(
                \Illuminate\Support\Facades\Route::has('login') ? route('login') : '/login'
            );
        }

        if (! AclService::canAdmin(Auth::id())) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}
