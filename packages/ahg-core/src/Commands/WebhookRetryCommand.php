<?php

/**
 * Retry failed webhook deliveries whose backoff window has elapsed.
 *
 * This command used to carry its own copy of the retry query and its own HTTP
 * send. That copy referenced three columns `ahg_webhook_delivery` does not have
 * - `attempts`, `last_response` and `last_error` (the real ones are
 * `attempt_count`, `response_code` and `response_body`) - so from v1.82.1
 * (2026-05-25) every scheduled run died on
 * "Unknown column 'd.attempts'", roughly every two minutes, for months. It had
 * never worked once.
 *
 * It now delegates to AhgApi\Services\WebhookService::processRetries(), which is
 * the implementation the rest of the webhook stack already uses and which is
 * correct on all three counts. Delegating also gains two things the local copy
 * never had: the #1395 SSRF guard on the delivery URL, and redirects disabled -
 * a retry loop POSTing signed payloads to an arbitrary redirect target is
 * exactly the shape of an SSRF.
 *
 * ahg-api is a soft dependency: this no-ops with an explanation where that
 * package is absent rather than fataling in the scheduler.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class WebhookRetryCommand extends Command
{
    protected $signature = 'ahg:webhook-retry
        {--limit=50 : Max delivery rows to retry per run}
        {--max-attempts=5 : Cap retries; rows past this are left for an operator}';

    protected $description = 'Retry failed ahg_webhook_delivery rows whose backoff window has elapsed';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_webhook_delivery')) {
            $this->info('No webhook delivery table here - nothing to retry.');

            return self::SUCCESS;
        }

        if (! class_exists(\AhgApi\Services\WebhookService::class)) {
            $this->warn('ahg-api is not installed here, so there is no webhook service to retry with.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));

        $processed = app(\AhgApi\Services\WebhookService::class)->processRetries($limit, $maxAttempts);

        $this->info("retried {$processed} delivery row(s) (limit={$limit}, max_attempts={$maxAttempts})");

        return self::SUCCESS;
    }
}
