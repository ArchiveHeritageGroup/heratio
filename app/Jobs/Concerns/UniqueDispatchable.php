<?php

/*
 * Copyright (c) 2026 Johan Pieterse / Plain Sailing Information Systems.
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * UniqueDispatchable trait.
 *
 * Issue #672 Phase 2. Additive helper that dedupes job dispatches at the
 * call site without touching the Job class itself. Callers replace
 *
 *     ReindexJob::dispatch($informationObjectId);
 *
 * with
 *
 *     ReindexJob::dispatchUnique($informationObjectId);
 *
 * to get a 60 second dedupe window. Subsequent dispatches of the same Job
 * class with the same serialized arguments inside that window are dropped
 * (logged at info level) and never reach the queue.
 *
 * The dedupe key is `dispatch.unique.{class}.{sha1(serialize(args))}`.
 *
 * Cache driver requirement: Cache::lock() requires redis, memcached,
 * dynamodb, or database. The file and array drivers will throw.
 *
 * Override the window per Job class by setting:
 *
 *     public static int $dispatchUniqueTtl = 300;
 */
trait UniqueDispatchable
{
    /**
     * Dispatch the job, dropping duplicate dispatches within the TTL window.
     *
     * @param  mixed  ...$args  Constructor arguments forwarded to dispatch().
     * @return mixed The PendingDispatch from Bus, or null if the call was deduped.
     */
    public static function dispatchUnique(...$args)
    {
        $ttl = (int) (static::$dispatchUniqueTtl ?? 60);
        $key = 'dispatch.unique.'.static::class.'.'.sha1(serialize($args));

        $lock = Cache::lock($key, $ttl);

        if (! $lock->get()) {
            Log::info('queue.dispatch.duplicate_dropped', [
                'job' => static::class,
                'key' => $key,
                'ttl_seconds' => $ttl,
            ]);

            return null;
        }

        // Lock is intentionally NOT released here - its lifetime IS the
        // dedupe window. It will expire automatically after $ttl seconds.

        return static::dispatch(...$args);
    }
}
