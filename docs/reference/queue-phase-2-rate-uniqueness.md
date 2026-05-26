# Queue Phase 2 - Rate Limiting and Job Uniqueness

> Heratio reference document. Issue #672 Phase 2. Companion to `docs/queue-worker-deployment.md` (Phase 1 - supervisord + systemd worker daemons).

This document describes the opt-in tooling that Phase 2 adds for Heratio's queued jobs. The Phase 1 worker daemon is unchanged; nothing here is forced on existing Job classes. Adoption is per-Job, per-callsite, and additive.

## What ships in Phase 2

| Path | Purpose |
|---|---|
| `app/Jobs/Middleware/RateLimited.php` | Throttle a queued job to N attempts per minute; release back to queue on overflow. |
| `app/Jobs/Middleware/EnsureUnique.php` | Atomic cache lock around `handle()`; swallow concurrent duplicates silently. |
| `app/Jobs/Concerns/UniqueDispatchable.php` | `dispatchUnique(...)` helper that dedupes dispatches at the call site over a TTL window. |
| `app/Jobs/Concerns/RateLimitedJob.php` | One-line trait that wires `RateLimited` into a Job's `middleware()`. |
| `config/queue.php` - `rate_limits` array | Named per-minute caps the middleware can look up by string key. |

## Quick decision guide

| You want to... | Use |
|---|---|
| Cap how often a Job actually executes (e.g. respect an upstream API quota). | `RateLimited` middleware (via `RateLimitedJob` trait or hand-instantiated). |
| Stop two workers from running the same logical job at the same time. | `EnsureUnique` middleware. |
| Stop the same job from being queued twice at the dispatcher (debounce). | `UniqueDispatchable::dispatchUnique(...)`. |

`RateLimited` retries (release back to queue, run later). `EnsureUnique` and `UniqueDispatchable` swallow duplicates - they assume the second call is a bug or a redundant fan-out, not a retry case.

## Cache driver requirement (READ THIS BEFORE PROD)

All three tools depend on the application cache:

- `RateLimited` uses `Illuminate\Cache\RateLimiter`, which counts hits in the cache.
- `EnsureUnique` and `UniqueDispatchable` use `Cache::lock(...)`, which requires an atomic store.

Supported atomic stores: **redis**, **memcached**, **dynamodb**, **database**. The `file` and `array` drivers do NOT support locks and will throw `LockTimeoutException` or `BadMethodCallException`.

On a host running multiple worker processes the cache MUST be shared across them (redis or database). A per-process `file` cache will silently underreport limits and let duplicates through.

Current Heratio default: `CACHE_STORE=database` (set in `config/cache.php`). If your deployment uses `file`, switch to `database` or `redis` before adopting any Phase 2 tool. The `cache` and `cache_locks` tables are part of the Laravel default migrations and should already exist; if not, run `php artisan cache:table` + `php artisan migrate`.

## Pattern 1 - rate-limited job via trait

The most common case: an AI inference job that must not exceed the upstream gateway's per-minute throughput.

```php
namespace App\Jobs;

use App\Jobs\Concerns\RateLimitedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class HtrExtractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use RateLimitedJob;

    public static int $rateLimit = 10; // 10 jobs per minute

    public function handle(): void { /* ... */ }
}
```

The trait reads `static::class` as the rate-limit bucket. Two `HtrExtractJob` instances share one counter; a separate Job class gets its own bucket.

## Pattern 2 - rate-limited job via explicit middleware

If you need a richer key (per-tenant, per-resource) or want to compose with other middleware:

```php
public function middleware(): array
{
    return [
        new \App\Jobs\Middleware\RateLimited('htr_extract:tenant-'.$this->tenantId, 10),
    ];
}
```

If `queue.rate_limits.htr_extract:tenant-7` is set in config the explicit `10` is overridden; otherwise `10` wins. (Exact-match lookup - the tenant suffix means the config rarely overrides per-tenant keys, which is usually what you want.)

## Pattern 3 - unique job execution

A reindex job that takes 5 minutes; you don't want a second worker re-running it if a duplicate slips into the queue.

```php
public function middleware(): array
{
    return [
        new \App\Jobs\Middleware\EnsureUnique('reindex-io-'.$this->informationObjectId, 600),
    ];
}
```

While one worker holds the lock, any other worker that picks up a duplicate copy returns immediately. The original execution finishes, the lock is released, and a NEW dispatch (e.g. a later edit) is free to run.

## Pattern 4 - unique dispatch debounce

A controller that may be called repeatedly in rapid succession (e.g. webhook receiver, autosave). You want the first call to schedule the job and subsequent calls within the window to be dropped before they hit the queue.

```php
// Old:
ReindexJob::dispatch($informationObjectId);

// New:
ReindexJob::dispatchUnique($informationObjectId);
```

The trait on the Job class:

```php
use App\Jobs\Concerns\UniqueDispatchable;

class ReindexJob implements ShouldQueue
{
    use Dispatchable, /* ... */;
    use UniqueDispatchable;

    public static int $dispatchUniqueTtl = 60; // override the 60s default
}
```

Returns the `PendingDispatch` on success, `null` if the call was deduped. `EnsureUnique` and `UniqueDispatchable` compose cleanly - use `dispatchUnique` to debounce the dispatcher AND `EnsureUnique` to defend the worker against any duplicate that gets through (different cache keys, separate concerns).

## Configuration knobs

`config/queue.php` ships these defaults; every value is overridable via env:

| Key | Env var | Default |
|---|---|---|
| `htr_extract` | `QUEUE_RATE_LIMIT_HTR_EXTRACT` | 10 |
| `llm_complete` | `QUEUE_RATE_LIMIT_LLM_COMPLETE` | 60 |
| `ner_extract` | `QUEUE_RATE_LIMIT_NER_EXTRACT` | 30 |
| `summarize` | `QUEUE_RATE_LIMIT_SUMMARIZE` | 30 |
| `translate` | `QUEUE_RATE_LIMIT_TRANSLATE` | 30 |
| `email_send` | `QUEUE_RATE_LIMIT_EMAIL_SEND` | 100 |
| `sms_send` | `QUEUE_RATE_LIMIT_SMS_SEND` | 30 |
| `webhook_dispatch` | `QUEUE_RATE_LIMIT_WEBHOOK` | 60 |
| `es_reindex` | `QUEUE_RATE_LIMIT_ES_REINDEX` | 120 |
| `thumbnail_gen` | `QUEUE_RATE_LIMIT_THUMBNAIL` | 60 |

Tune to match the actual capacity of the host. The Phase 1 worker count (`numprocs=2` in the supervisord conf) determines parallel throughput; the rate limit is the per-minute ceiling that the workers must collectively respect.

## Observability

All three tools log to the default Laravel logger at info level:

- `queue.rate_limited.release` - job hit the cap, released back to queue with `retry_after_seconds`.
- `queue.unique.duplicate_swallowed` - second worker found the lock held, returned without running.
- `queue.dispatch.duplicate_dropped` - `dispatchUnique` call deduped before dispatch.

Grep `/var/log/heratio-queue-worker-*.log` (path from Phase 1 supervisord conf) for these strings to confirm the tools are firing.

## Retrofitting existing Jobs

Most Heratio Jobs live inside locked packages (`packages/ahg-information-object-manage/`, `packages/ahg-ingest/`, `packages/ahg-ai-services/`, etc. - see `.locked-paths`). Phase 2 deliberately does NOT edit any existing Job. The opt-in helpers are available now; per-package retrofits open as separate GitHub issues as the corresponding lock is lifted for unrelated work. Suggested first candidates (track via issue links from #672):

- `ahg-ai-services` HTR / NER / Summarize / Translate Jobs - high value for `RateLimited`.
- `ahg-search` reindex Jobs - high value for `EnsureUnique` + `dispatchUnique`.
- `ahg-export` finding-aid Job - candidate for `dispatchUnique` debounce.

Each retrofit is a 3-5 line trait/middleware addition plus a test; no functional behaviour change otherwise.

## Out of scope (Phase 3 candidates)

- Per-tenant queue names + per-tenant worker pools.
- Failed-job dashboard data layer + UI.
- Admin UI for tuning the `rate_limits` config without code edits.
- Per-Job uniqueness key registration (so `php artisan queue:unique:list` can dump every active lock).

Tracked under #672 alongside Phase 1 + Phase 2 line items.
