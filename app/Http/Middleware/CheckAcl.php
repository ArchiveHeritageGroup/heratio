<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use AhgCore\Services\AclService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAcl
{
    public function handle(Request $request, Closure $next, string $action = 'read'): Response
    {
        $userId = Auth::id();

        if (! AclService::hasPermission($userId, $action)) {
            if (! Auth::check()) {
                return redirect()->route('login');
            }

            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}
