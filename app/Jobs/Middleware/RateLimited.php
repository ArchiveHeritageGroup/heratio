<?php

/*
 * Copyright (c) 2026 Johan Pieterse / Plain Sailing Information Systems.
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * RateLimited job middleware.
 *
 * Issue #672 Phase 2. Throttles a job to N attempts per minute keyed by a
 * stable string. If the limit is exceeded the job is released back to the
 * queue with the limiter's recommended retry delay so it tries again later
 * instead of failing.
 *
 * Existing Job classes opt in by returning an instance from `middleware()`:
 *
 *     public function middleware(): array
 *     {
 *         return [new \App\Jobs\Middleware\RateLimited('htr_extract', 10)];
 *     }
 *
 * The $key can also reference a name from config('queue.rate_limits') -
 * the middleware looks the value up and falls back to the explicit
 * $maxAttempts argument when no config entry exists.
 *
 * Cache driver requirement: the underlying Illuminate\Cache\RateLimiter
 * is backed by the application cache store. file / array drivers work for
 * single-process dev but production hosts must use redis or database for
 * the counter to be shared across worker processes.
 */
class RateLimited
{
    public function __construct(
        protected string $key,
        protected int $maxAttempts = 60,
    ) {
    }

    /**
     * Process the queued job.
     *
     * @param  mixed    $job
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($job, Closure $next)
    {
        /** @var RateLimiter $limiter */
        $limiter = Container::getInstance()->make(RateLimiter::class);

        // Allow a named limit from config/queue.php#rate_limits to override
        // the explicit $maxAttempts (e.g. tune in config without redeploy).
        $configured = config('queue.rate_limits.'.$this->key);
        $max = is_int($configured) ? $configured : $this->maxAttempts;

        if ($limiter->tooManyAttempts($this->key, $max)) {
            $retryAfter = $limiter->availableIn($this->key);

            Log::info('queue.rate_limited.release', [
                'job' => get_class($job),
                'key' => $this->key,
                'max_per_minute' => $max,
                'retry_after_seconds' => $retryAfter,
            ]);

            // Release the job back to the queue; the worker will pick it up
            // after $retryAfter seconds. The limiter's decay is 60 seconds.
            $job->release($retryAfter);

            return;
        }

        $limiter->hit($this->key, 60);

        return $next($job);
    }
}
