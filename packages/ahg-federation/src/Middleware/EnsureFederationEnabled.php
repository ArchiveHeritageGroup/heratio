<?php

namespace AhgFederation\Middleware;

use Closure;
use AhgFederation\Services\FederationService;
use Illuminate\Http\Request;

/**
 * EnsureFederationEnabled
 *
 * Middleware that prevents access to federation routes when federation_enabled = false.
 * This wires the existing admin setting so the feature can be toggled from the settings UI.
 */
class EnsureFederationEnabled
{
    protected FederationService $service;

    public function __construct(FederationService $service)
    {
        $this->service = $service;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->service->isEnabled()) {
            // Feature is disabled — present 404 to avoid exposing plugin UI.
            abort(404);
        }

        return $next($request);
    }
}
