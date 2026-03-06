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
            return redirect()->route('login');
        }

        if (! AclService::canAdmin(Auth::id())) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}
