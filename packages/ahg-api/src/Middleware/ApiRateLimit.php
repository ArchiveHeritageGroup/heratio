<?php

namespace AhgApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiRateLimit
{
    /**
     * Per-key rate limiting using the ahg_api_rate_limit table.
     * Session-authenticated requests are not rate-limited.
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKeyId = $request->attributes->get('api_key_id');
        if (!$apiKeyId) {
            return $next($request); // Session auth — no rate limiting
        }

        $apiKey = DB::table('ahg_api_key')->where('id', $apiKeyId)->first();
        $rateLimit = $apiKey->rate_limit ?? 1000;

        $windowStart = now()->startOfHour();

        $row = DB::table('ahg_api_rate_limit')
            ->where('api_key_id', $apiKeyId)
            ->where('window_start', $windowStart)
            ->first();

        if ($row && $row->request_count >= $rateLimit) {
            $retryAfter = now()->endOfHour()->diffInSeconds(now());
            return response()->json([
                'success' => false,
                'error' => 'Too Many Requests',
                'message' => "Rate limit of {$rateLimit} requests per hour exceeded.",
            ], 429)->header('Retry-After', $retryAfter);
        }

        DB::table('ahg_api_rate_limit')->updateOrInsert(
            ['api_key_id' => $apiKeyId, 'window_start' => $windowStart],
            ['request_count' => DB::raw('request_count + 1')]
        );

        $response = $next($request);

        $remaining = max(0, $rateLimit - (($row->request_count ?? 0) + 1));
        $response->headers->set('X-RateLimit-Limit', $rateLimit);
        $response->headers->set('X-RateLimit-Remaining', $remaining);

        return $response;
    }
}
