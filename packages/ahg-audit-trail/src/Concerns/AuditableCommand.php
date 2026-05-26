<?php

/**
 * AuditableCommand - drop-in trait that emits audit rows for any Artisan
 * command's start + end. Issue #676 Phase 6.
 *
 * Usage:
 *
 *   class MyCommand extends \Illuminate\Console\Command
 *   {
 *       use \AhgAuditTrail\Concerns\AuditableCommand;
 *
 *       protected $signature = 'app:do-thing {target}';
 *
 *       public function handle(): int
 *       {
 *           $this->auditCommandStart();
 *           try {
 *               // ... real work ...
 *               return self::SUCCESS;
 *           } finally {
 *               $this->auditCommandEnd($this->lastExitCode ?? self::SUCCESS);
 *           }
 *       }
 *   }
 *
 * Both calls write to `ahg_audit_log` via AuditLogger so they ride the same
 * hash chain + Ed25519 signature as web-side audit rows. The trait stores the
 * start timestamp + uuid on the consuming command so the `_end` row can
 * reference the matching start row.
 *
 * Composes cleanly with the rate-limit + uniqueness middleware shipped in
 * issue #672 Phase 2: the middleware sits in front of the HTTP layer; CLI
 * commands run outside that path, so this trait is the audit surface for
 * the CLI half.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

declare(strict_types=1);

namespace AhgAuditTrail\Concerns;

use AhgAuditTrail\Services\AuditLogger;

trait AuditableCommand
{
    /** @var string|null uuid generated at start, surfaced to the end-row metadata */
    protected ?string $auditCommandRunUuid = null;

    /** @var float|null microtime at start, used to compute duration */
    protected ?float $auditCommandStartedAt = null;

    /**
     * Emit a `cli.command_start` row. Safe to call multiple times - subsequent
     * calls overwrite the run uuid, which is the correct behaviour for a
     * command that legitimately restarts mid-run.
     */
    protected function auditCommandStart(array $extra = []): ?int
    {
        $this->auditCommandRunUuid = $this->generateRunUuid();
        $this->auditCommandStartedAt = microtime(true);

        return $this->auditLogger()->logAction(
            action: 'cli.command_start',
            entityType: 'cli_command',
            entityId: null,
            metadata: array_merge([
                'run_uuid' => $this->auditCommandRunUuid,
                'command'  => $this->resolveCommandName(),
                'arguments' => $this->safeArguments(),
                'options'   => $this->safeOptions(),
                'host'      => gethostname() ?: null,
                'pid'       => function_exists('getmypid') ? getmypid() : null,
                'started_at' => date('c'),
            ], $extra),
            entitySlug: $this->resolveCommandName(),
        );
    }

    /**
     * Emit a `cli.command_end` row. The exit code is the canonical signal:
     * 0 success, non-zero failure. Duration is captured in milliseconds.
     */
    protected function auditCommandEnd(int $exitCode, array $extra = []): ?int
    {
        $durationMs = null;
        if ($this->auditCommandStartedAt !== null) {
            $durationMs = (int) round((microtime(true) - $this->auditCommandStartedAt) * 1000);
        }

        return $this->auditLogger()->logAction(
            action: 'cli.command_end',
            entityType: 'cli_command',
            entityId: null,
            metadata: array_merge([
                'run_uuid'   => $this->auditCommandRunUuid,
                'command'    => $this->resolveCommandName(),
                'exit_code'  => $exitCode,
                'duration_ms' => $durationMs,
                'host'       => gethostname() ?: null,
                'pid'        => function_exists('getmypid') ? getmypid() : null,
                'ended_at'   => date('c'),
            ], $extra),
            entitySlug: $this->resolveCommandName(),
        );
    }

    /**
     * Resolve a shared AuditLogger instance. Falls back to a fresh one when
     * the container is not yet booted (early-boot test harness).
     */
    private function auditLogger(): AuditLogger
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

    private function resolveCommandName(): string
    {
        // Illuminate\Console\Command exposes ->getName(); when the trait is
        // mixed into something exotic without a getName(), fall back to the
        // class name so we never emit a NULL command identifier.
        if (method_exists($this, 'getName')) {
            $name = $this->getName();
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }
        return static::class;
    }

    /**
     * Returns the command's arguments WITHOUT the implicit `command` key
     * (which is always present in Symfony Console's argument list and only
     * adds noise). Returns array<string,mixed>.
     */
    private function safeArguments(): array
    {
        if (!method_exists($this, 'arguments')) {
            return [];
        }
        try {
            $args = $this->arguments();
            if (is_array($args)) {
                unset($args['command']);
                return $args;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return [];
    }

    /**
     * Returns the command's options sans Symfony's standard verbosity /
     * help / version / quiet noise (those are not interesting in an audit
     * row and just bloat the JSON).
     */
    private function safeOptions(): array
    {
        if (!method_exists($this, 'options')) {
            return [];
        }
        try {
            $opts = $this->options();
            if (is_array($opts)) {
                foreach (['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'] as $std) {
                    unset($opts[$std]);
                }
                return $opts;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return [];
    }

    private function generateRunUuid(): string
    {
        try {
            if (class_exists('Illuminate\Support\Str')) {
                return (string) \Illuminate\Support\Str::uuid();
            }
        } catch (\Throwable $e) {
            // fall through
        }
        // last-resort RFC-4122-ish identifier; not cryptographically perfect
        // but only used to correlate start + end rows for the same run.
        return bin2hex(random_bytes(16));
    }
}
