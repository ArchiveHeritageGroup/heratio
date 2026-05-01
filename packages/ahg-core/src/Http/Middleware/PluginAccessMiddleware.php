<?php
/*
 * Heratio — PluginAccessMiddleware
 *
 * Issue #40 c5: gate plugin URLs server-side. If an admin has denied
 * a plugin to a user, that user gets 403 even if they type the URL.
 *
 * Apply by adding `->middleware('plugin:ahgFooPlugin')` to a route
 * group, or `Route::middleware(['plugin:ahgFooPlugin'])->group(...)`.
 *
 * AGPL-3.0-or-later — Johan Pieterse / Plain Sailing iSystems
 */

namespace AhgCore\Http\Middleware;

use AhgCore\Services\MenuService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PluginAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $pluginName): Response
    {
        // Anonymous + no plugin grants → use global enablement only.
        $userId = $request->user()?->id;

        if (!MenuService::isPluginAccessible($pluginName, $userId)) {
            abort(403, "The '{$pluginName}' plugin is not enabled for your account.");
        }

        return $next($request);
    }
}
