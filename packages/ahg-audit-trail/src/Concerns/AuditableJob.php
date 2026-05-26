<?php

/**
 * AuditableJob - drop-in trait that emits audit rows for any queued job's
 * start + end. Issue #676 Phase 6.
 *
 * Usage:
 *
 *   class MyJob implements ShouldQueue
 *   {
 *       use \AhgAuditTrail\Concerns\AuditableJob;
 *
 *       public function handle(): void
 *       {
 *           $this->auditJobStart();
 *           try {
 *               // ... real work ...
 *               $this->auditJobEnd('success');
 *           } catch (\Throwable $e) {
 *               $this->auditJobEnd('failed', ['error' => $e->getMessage()]);
 *               throw $e;
 *           }
 *       }
 *   }
 *
 * For full automatic instrumentation without touching `handle()`, register
 * `AhgAuditTrail\Concerns\AuditableJob::globalMiddleware()` as the queue
 * worker's default middleware via `Queue::resolved()` or a service-provider
 * hook (see audit-trail-phase-6.md). The middleware form composes with the
 * rate-limit + uniqueness middleware shipped in issue #672 Phase 2.
 *
 * Captures: job class, payload sha256 hash, queue name, attempts, exit status.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

declare(strict_types=1);

namespace AhgAuditTrail\Concerns;

use AhgAuditTrail\Services\AuditLogger;

trait AuditableJob
{
    /** @var string|null uuid generated at start, surfaced to the end-row metadata */
    protected ?string $auditJobRunUuid = null;

    /** @var float|null microtime at start, used to compute duration */
    protected ?float $auditJobStartedAt = null;

    /**
     * Emit a `job.start` row.
     */
    public function auditJobStart(array $extra = []): ?int
    {
        $this->auditJobRunUuid = $this->generateJobRunUuid();
        $this->auditJobStartedAt = microtime(true);

        return $this->auditJobLogger()->logAction(
            action: 'job.start',
            entityType: 'queued_job',
            entityId: null,
            metadata: array_merge([
                'run_uuid'    => $this->auditJobRunUuid,
                'job'         => static::class,
                'queue'       => $this->resolveQueueName(),
                'connection'  => $this->resolveConnectionName(),
                'attempts'    => $this->resolveAttempts(),
                'payload_sha' => $this->resolvePayloadSha(),
                'host'        => gethostname() ?: null,
                'pid'         => function_exists('getmypid') ? getmypid() : null,
                'started_at'  => date('c'),
            ], $extra),
            entitySlug: static::class,
        );
    }

    /**
     * Emit a `job.end` row. `$status` is one of `success`, `failed`, or
     * `released` (back to the queue for retry). Duration is captured in
     * milliseconds.
     */
    public function auditJobEnd(string $status, array $extra = []): ?int
    {
        $durationMs = null;
        if ($this->auditJobStartedAt !== null) {
            $durationMs = (int) round((microtime(true) - $this->auditJobStartedAt) * 1000);
        }

        return $this->auditJobLogger()->logAction(
            action: 'job.end',
            entityType: 'queued_job',
            entityId: null,
            metadata: array_merge([
                'run_uuid'    => $this->auditJobRunUuid,
                'job'         => static::class,
                'queue'       => $this->resolveQueueName(),
                'connection'  => $this->resolveConnectionName(),
                'attempts'    => $this->resolveAttempts(),
                'status'      => $status,
                'duration_ms' => $durationMs,
                'host'        => gethostname() ?: null,
                'pid'         => function_exists('getmypid') ? getmypid() : null,
                'ended_at'    => date('c'),
            ], $extra),
            entitySlug: static::class,
        );
    }

    /**
     * Convenience "before" hook for callers that register `before()` /
     * `after()` middleware-style closures via the queue worker. Some
     * applications register a global queue middleware that calls
     * $job->resolved()->before(fn() => $job->auditJobStart()).
     */
    public function before(): void
    {
        $this->auditJobStart();
    }

    /**
     * Convenience "after" hook. If the consumer wants to distinguish success
     * from failure, call auditJobEnd() directly from the job's `handle()` /
     * `failed()` methods - this hook reports `success` because Laravel only
     * fires the queue's `after()` callbacks on the success path.
     */
    public function after(): void
    {
        $this->auditJobEnd('success');
    }

    /**
     * Standard Laravel "failed" hook. If the consuming job already defines
     * its own `failed(\Throwable $e)` method, this method is shadowed; the
     * trait still emits the start row before the throw, so the absence of a
     * matching end row is itself a useful signal.
     */
    public function failed(?\Throwable $e = null): void
    {
        $this->auditJobEnd('failed', $e === null ? [] : [
            'error' => substr($e->getMessage(), 0, 1000),
            'exception' => get_class($e),
        ]);
    }

    private function auditJobLogger(): AuditLogger
    {
        try {
            if (function_exists('app')) {
                $app = app();
                if ($app !== null && $app->bound(AuditLogger::class)) {
                    return $app->make(AuditLogger::class);
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return new AuditLogger();
    }

    private function resolveQueueName(): ?string
    {
        if (property_exists($this, 'queue') && is_string($this->queue) && $this->queue !== '') {
            return $this->queue;
        }
        if (method_exists($this, 'queue')) {
            try {
                $q = $this->queue();
                if (is_string($q) && $q !== '') {
                    return $q;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return null;
    }

    private function resolveConnectionName(): ?string
    {
        if (property_exists($this, 'connection') && is_string($this->connection) && $this->connection !== '') {
            return $this->connection;
        }
        return null;
    }

    private function resolveAttempts(): ?int
    {
        if (method_exists($this, 'attempts')) {
            try {
                $a = $this->attempts();
                if (is_int($a) || is_numeric($a)) {
                    return (int) $a;
                }
            } catch (\Throwable $e) {
                // ignore - attempts() only works when the job is dispatched
                // via the queue worker (i.e. has a Queue\Job instance bound)
            }
        }
        return null;
    }

    /**
     * Hash the serialised payload so the audit row carries a fingerprint of
     * the job without leaking sensitive arguments. SHA-256 hex.
     */
    private function resolvePayloadSha(): ?string
    {
        try {
            $vars = get_object_vars($this);
            // Drop trait-managed bookkeeping so the same job + payload always
            // produces the same hash regardless of when we sample it.
            unset($vars['auditJobRunUuid'], $vars['auditJobStartedAt']);
            $serial = @serialize($vars);
            if (!is_string($serial) || $serial === '') {
                return null;
            }
            return hash('sha256', $serial);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function generateJobRunUuid(): string
    {
        try {
            if (class_exists('Illuminate\Support\Str')) {
                return (string) \Illuminate\Support\Str::uuid();
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return bin2hex(random_bytes(16));
    }
}
