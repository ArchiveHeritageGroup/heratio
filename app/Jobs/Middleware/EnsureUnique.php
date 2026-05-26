<?php

/*
 * Copyright (c) 2026 Johan Pieterse / Plain Sailing Information Systems.
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * EnsureUnique job middleware.
 *
 * Issue #672 Phase 2. Wraps job execution in an atomic cache lock so that
 * concurrent workers cannot run the same logical job twice in parallel.
 *
 * If the lock cannot be acquired the duplicate is SWALLOWED silently
 * (logged at info level) - this middleware is for catching duplicate
 * dispatches, not for retry coordination. If you need "wait and retry"
 * semantics use RateLimited instead.
 *
 * Existing Job classes opt in by returning an instance from `middleware()`:
 *
 *     public function middleware(): array
 *     {
 *         return [new \App\Jobs\Middleware\EnsureUnique('reindex-io-'.$this->id, 300)];
 *     }
 *
 * Cache driver requirement: Cache::lock() requires an atomic store -
 * redis, memcached, dynamodb, or database. The file and array drivers
 * do NOT support locks and will throw. Production hosts must run on
 * redis or database (matches the queue connection default).
 */
class EnsureUnique
{
    public function __construct(
        protected string $lockKey,
        protected int $ttl = 60,
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
        $lock = Cache::lock('queue.unique.'.$this->lockKey, $this->ttl);

        if (! $lock->get()) {
            Log::info('queue.unique.duplicate_swallowed', [
                'job' => get_class($job),
                'lock_key' => $this->lockKey,
                'ttl_seconds' => $this->ttl,
            ]);

            // Do NOT release. Duplicate dispatch is the bug - let the in-
            // flight execution proceed and drop the redundant copy.
            return;
        }

        try {
            return $next($job);
        } finally {
            // Use forceRelease() because the worker holding the lock may not
            // be the same PHP process across release() calls under some
            // queue drivers.
            $lock->forceRelease();
        }
    }
}
