<?php

/**
 * RegisterDataciteEventJob - queued submission of one ahg_datacite_event row.
 *
 * Issue #654 Phase 3. Throttled via the #672 Phase 2 RateLimited middleware
 * keyed on 'datacite_events' (default cap 30/min - the named limit lives in
 * config/queue.php so an operator can tune without redeploying). When the
 * limit is reached the job is released back to the queue with the limiter's
 * recommended retry-after delay rather than failing, so a burst of view or
 * download events stalls at the limit rather than spamming DataCite.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Jobs;

use AhgDoiManage\Services\DataciteEventsService;
use App\Jobs\Middleware\RateLimited;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegisterDataciteEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(public int $eventId)
    {
    }

    public function middleware(): array
    {
        return [new RateLimited('datacite_events', 30)];
    }

    public function handle(DataciteEventsService $svc): void
    {
        $svc->submit($this->eventId);
    }
}
