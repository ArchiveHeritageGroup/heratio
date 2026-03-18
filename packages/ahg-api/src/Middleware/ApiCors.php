<?php

namespace AhgApi\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiCors
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, X-API-Key, X-REST-API-Key, Authorization, Accept')
                ->header('Access-Control-Max-Age', 86400);
        }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-API-Key, X-REST-API-Key, Authorization, Accept');

        return $response;
    }
}
