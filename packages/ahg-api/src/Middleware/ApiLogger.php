<?php

namespace AhgApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiLogger
{
    /**
     * Log every API request to ahg_api_log.
     */
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) ((microtime(true) - $start) * 1000);

        try {
            DB::table('ahg_api_log')->insert([
                'api_key_id' => $request->attributes->get('api_key_id'),
                'user_id' => $request->attributes->get('api_user_id'),
                'method' => $request->method(),
                'endpoint' => substr($request->path(), 0, 255),
                'status_code' => $response->getStatusCode(),
                'request_body' => in_array($request->method(), ['POST', 'PUT', 'PATCH'])
                    ? substr($request->getContent(), 0, 65535) : null,
                'response_size' => strlen($response->getContent()),
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging failure should not break the API response
        }

        return $response;
    }
}
