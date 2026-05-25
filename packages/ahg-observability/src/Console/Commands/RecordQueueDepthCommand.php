<?php

/**
 * observability:record-queue-depth - sample Queue::size() per connection.
 *
 * Scheduled every minute (via AhgObservabilityServiceProvider) so the
 * heratio_queue_depth gauge reflects current backlog without us having to
 * intercept enqueue/dequeue events (which would mean wiring into every
 * driver's job lifecycle).
 *
 * The list of (connection, queue) pairs to sample comes from
 * config('observability.queues'). Falls back to a single
 * (default, default) sample so a fresh install gets something useful.
 *
 * Failure mode: a single unhealthy connection logs a warning and we keep
 * going; we never want one broken queue driver to stop scraping the rest.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgObservability\Console\Commands;

use AhgObservability\Services\MetricsRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class RecordQueueDepthCommand extends Command
{
    protected $signature = 'observability:record-queue-depth';

    protected $description = 'Sample queue depth for each configured connection/queue and set the heratio_queue_depth gauge.';

    public function __construct(private readonly MetricsRegistry $metrics)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $pairs = (array) config('observability.queues', []);
        if (empty($pairs)) {
            // Avoid silent no-op - make this discoverable in scheduler logs.
            $pairs = [['connection' => null, 'queue' => 'default']];
        }

        $gauge = $this->metrics->gauge(
            'queue_depth',
            'Number of jobs currently waiting on the named queue',
            ['connection', 'queue']
        );

        $sampled = 0;
        foreach ($pairs as $pair) {
            $connection = $pair['connection'] ?? null;
            $queue = (string) ($pair['queue'] ?? 'default');
            $connectionName = (string) ($connection ?: config('queue.default'));

            try {
                $size = (int) Queue::connection($connection)->size($queue);
                $gauge->set($size, [$connectionName, $queue]);
                $sampled++;
                $this->line(sprintf('  %s/%s = %d', $connectionName, $queue, $size));
            } catch (\Throwable $e) {
                // One bad driver shouldn't sink the rest.
                $this->warn(sprintf('  %s/%s skipped (%s)', $connectionName, $queue, $e->getMessage()));
            }
        }

        $this->info(sprintf('Sampled %d queue(s).', $sampled));

        return self::SUCCESS;
    }
}
