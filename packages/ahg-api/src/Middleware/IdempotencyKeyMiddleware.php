<?php

/**
 * IdempotencyKeyMiddleware
 *
 * Honours the Idempotency-Key header on non-idempotent POSTs:
 *   - First call: process normally, cache the response keyed by
 *     (user_id, key, route) for 24h.
 *   - Replay with same key + same request body: return cached response.
 *   - Replay with same key + different body: 409 Conflict.
 *
 * Bypass: missing header = normal pass-through (header is optional).
 *
 * Issue #652 Phase 1.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IdempotencyKeyMiddleware
{
    /** 24-hour replay window. */
    protected const TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next)
    {
        // Only intercept non-idempotent verbs.
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return $next($request);
        }

        // Safety: enforce client-side max key length per draft RFC.
        if (strlen($key) > 64) {
            return response()->json([
                'success' => false,
                'error' => 'Bad Request',
                'message' => 'Idempotency-Key must be <= 64 characters.',
                'timestamp' => now()->toIso8601String(),
            ], 400);
        }

        // If table missing (fresh DB), let the request through.
        if (! Schema::hasTable('ahg_api_idempotency_key')) {
            return $next($request);
        }

        $userId = (int) ($request->attributes->get('api_user_id') ?? 0);
        $route = $request->path();
        $requestHash = hash('sha256', (string) $request->getContent());

        $existing = DB::table('ahg_api_idempotency_key')
            ->where('user_id', $userId)
            ->where('idem_key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            // Same payload? replay the cached response.
            if ($existing->request_hash === $requestHash && $existing->route === $route) {
                $headers = $this->decodeHeaders($existing->response_headers);
                $headers['X-Idempotent-Replay'] = 'true';

                return response(
                    (string) $existing->response_body,
                    (int) $existing->response_status,
                    $headers
                );
            }

            // Same key, different request: protocol error.
            return response()->json([
                'success' => false,
                'error' => 'Conflict',
                'message' => 'Idempotency-Key has already been used with a different request body or route.',
                'timestamp' => now()->toIso8601String(),
            ], 409);
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Only cache 2xx responses to avoid replaying transient errors.
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            try {
                DB::table('ahg_api_idempotency_key')->insert([
                    'idem_key' => $key,
                    'user_id' => $userId,
                    'route' => substr($route, 0, 255),
                    'request_hash' => $requestHash,
                    'response_status' => $status,
                    'response_body' => $response->getContent(),
                    'response_headers' => json_encode($this->headerList($response)),
                    'expires_at' => now()->addSeconds(self::TTL_SECONDS),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Duplicate-key races are fine — first writer wins; ignore.
            }
        }

        return $response;
    }

    /**
     * Flatten response headers into a plain array (drop Set-Cookie, Date).
     */
    protected function headerList($response): array
    {
        $out = [];
        foreach ($response->headers->all() as $name => $values) {
            $lower = strtolower($name);
            if (in_array($lower, ['set-cookie', 'date'], true)) {
                continue;
            }
            $out[$name] = is_array($values) ? implode(', ', $values) : (string) $values;
        }

        return $out;
    }

    protected function decodeHeaders(?string $raw): array
    {
        if (! $raw) {
            return [];
        }
        $h = json_decode($raw, true);

        return is_array($h) ? $h : [];
    }
}
