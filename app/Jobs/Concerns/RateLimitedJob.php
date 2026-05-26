<?php

/*
 * Copyright (c) 2026 Johan Pieterse / Plain Sailing Information Systems.
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\RateLimited;

/**
 * RateLimitedJob trait.
 *
 * Issue #672 Phase 2. Saves boilerplate on Job classes that want a single
 * default RateLimited middleware keyed by the job class name. Opt in:
 *
 *     class HtrExtractJob implements ShouldQueue
 *     {
 *         use \App\Jobs\Concerns\RateLimitedJob;
 *
 *         public static int $rateLimit = 10; // attempts per minute
 *
 *         public function handle(): void { ... }
 *     }
 *
 * Override `middleware()` in the Job to add more middleware alongside
 * the rate limiter - call `parent::middleware()` or compose manually.
 *
 * The key passed to RateLimited is static::class, so a single Job class
 * shares its bucket across all dispatches. For per-tenant or per-resource
 * limiting, instantiate RateLimited directly in your own middleware()
 * with a richer key (e.g. static::class.':'.$this->tenantId).
 *
 * The class name key also doubles as a config('queue.rate_limits') lookup
 * candidate, but resolution is by exact string match so a fully qualified
 * class name is unlikely to collide with a config short-name entry.
 */
trait RateLimitedJob
{
    /**
     * Default middleware stack for queued execution.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $max = (int) (static::$rateLimit ?? 60);

        return [new RateLimited(static::class, $max)];
    }
}
