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
        if (! $apiKeyId) {
            return $next($request); // Session auth - no rate limiting
        }

        $apiKey = DB::table('ahg_api_key')->where('id', $apiKeyId)->first();
        $rateLimit = $apiKey->rate_limit ?? 1000;
        // #1566 - optional daily cap; 0, NULL or no column means none.
        $dailyLimit = (int) ($apiKey->daily_limit ?? 0);

        $windowStart = now()->startOfHour();

        if ($dailyLimit > 0) {
            $today = (int) DB::table('ahg_api_rate_limit')
                ->where('api_key_id', $apiKeyId)
                ->where('window_start', '>=', now()->startOfDay())
                ->sum('request_count');
            if ($today >= $dailyLimit) {
                return $this->tooMany("This key's daily limit of {$dailyLimit} requests is used up; it resets at midnight.", now()->startOfDay()->addDay());
            }
        }

        $row = DB::table('ahg_api_rate_limit')
            ->where('api_key_id', $apiKeyId)
            ->where('window_start', $windowStart)
            ->first();

        if ($row && $row->request_count >= $rateLimit) {
            return $this->tooMany("Rate limit of {$rateLimit} requests per hour exceeded.", $windowStart->copy()->addHour());
        }

        // One atomic INSERT ... ON DUPLICATE KEY UPDATE. updateOrInsert checked
        // then inserted, so two requests opening a window together both
        // inserted and the second failed on unique_key_window with a 500.
        DB::table('ahg_api_rate_limit')->upsert(
            [['api_key_id' => $apiKeyId, 'window_start' => $windowStart, 'request_count' => 1]],
            ['api_key_id', 'window_start'],
            ['request_count' => DB::raw('request_count + 1')]
        );

        $response = $next($request);

        $remaining = max(0, $rateLimit - (($row->request_count ?? 0) + 1));
        $response->headers->set('X-RateLimit-Limit', $rateLimit);
        $response->headers->set('X-RateLimit-Remaining', $remaining);

        return $response;
    }

    /** 429 with Retry-After: whole seconds until $resetsAt, at least one. */
    protected function tooMany(string $message, \Carbon\CarbonInterface $resetsAt)
    {
        return response()->json([
            'success' => false,
            'error' => 'Too Many Requests',
            'message' => $message,
        ], 429)->header('Retry-After', (string) max(1, $resetsAt->getTimestamp() - time()));
    }
}
