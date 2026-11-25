<?php

/**
 * AhgAuditTrailServiceProvider - Service provider for AHG Audit Trail
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

namespace AhgAuditTrail\Providers;

use AhgAuditTrail\Services\AuditService;
use AhgAuditTrail\Services\ChainedAuditWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgAuditTrailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditService::class, function ($app) {
            return new AuditService;
        });

        // Issue #676 Phase 5 - the chain writer is a singleton so the
        // resolved Signer + warn-once state stick around for the lifetime of
        // the request / worker process. AuditLogger falls back to a fresh
        // instance if this binding is not present (early boot, tests).
        $this->app->singleton(ChainedAuditWriter::class, function ($app) {
            return new ChainedAuditWriter();
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-audit-trail');


        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgAuditTrail\Console\Commands\PruneCommand::class,
                \AhgAuditTrail\Console\Commands\VerifyChainCommand::class,
                \AhgAuditTrail\Console\Commands\ReportCommand::class,
            ]);

            // Schedule a daily prune. Honours ahg_settings.audit_retention_days
            // (compliance group) - set to 0 to disable. The command itself
            // is a no-op when retention is disabled.
            $this->app->afterResolving(\Illuminate\Console\Scheduling\Schedule::class, function (\Illuminate\Console\Scheduling\Schedule $schedule) {
                $schedule->command('audit:prune')->dailyAt('03:30')->withoutOverlapping();
            });
        }

        // Issue #676 Phase 5 - apply chain-column ALTER TABLE + append-only
        // triggers on first boot after upgrade. We keep BOTH the probe and
        // the install attempt inside one outer try/catch so a missing table
        // (fresh install before the rest of the bootstrap finishes) can't
        // fault the whole provider - mirrors reference_ci_schema_hastable.md.
        // Not while the app is down: the demo reset drops these triggers to swap the
        // tables in, and a boot during that window (scheduler, worker, a leaked web
        // request) put them straight back - "1435 Trigger in wrong schema" killed the
        // swap on 22 Oct and 11 Nov 2026. The first boot after `artisan up` installs
        // anything still missing.
        try {
            if (! $this->app->isDownForMaintenance() && Schema::hasTable('ahg_audit_log')) {
                if (!Schema::hasColumn('ahg_audit_log', 'seq')) {
                    $this->runSqlFile(__DIR__.'/../../database/install-chain.sql');
                }
                // Create only the triggers that are missing, never drop one that exists, and
                // after a failure wait an hour. Where the app's DB user may not create triggers
                // (binlog on, not SUPER) the old drop-then-create on every request removed a
                // working trigger and queued every request on the table's metadata lock
                // (ANC, 25 Nov 2026).
                $missing = array_values(array_filter(
                    ['ahg_audit_log_no_update_chained', 'ahg_audit_log_no_delete_chained'],
                    fn (string $t) => ! $this->triggerExists($t)
                ));
                if ($missing && ! Cache::has('ahg_audit_trigger_install_failed')) {
                    try {
                        $this->runSqlFileUnprepared(__DIR__.'/../../database/install-trigger.sql', $missing);
                    } catch (\Throwable $e) {
                        Cache::put('ahg_audit_trigger_install_failed', true, 3600);
                        Log::warning('ahg-audit-trail: could not create '.implode(', ', $missing).': '.$e->getMessage()
                            .' - create them as a MySQL user allowed to (SUPER, or log_bin_trust_function_creators=1 when binlog is on)'
                            .' from packages/ahg-audit-trail/database/install-trigger.sql. Next try in an hour.');
                    }
                }
                // Issue #676 Phase 6 - tenant_id column + composite index for
                // tenant-scoped reports. Same schema-probe pattern so a fresh
                // install / partial bootstrap can not fault the provider.
                if (!Schema::hasColumn('ahg_audit_log', 'tenant_id')) {
                    $this->runSqlFile(__DIR__.'/../../database/install-tenant.sql');
                }
            }
        } catch (\Throwable $e) {
            // Boot must never abort. Operator can replay manually with
            // `mysql heratio < packages/ahg-audit-trail/database/install-chain.sql`.
        }
    }

    /**
     * Run an install file as a sequence of prepared statements split on `;`.
     * Mirrors the helper in AhgAiComplianceServiceProvider so behaviour /
     * comment-stripping stays consistent between siblings.
     */
    private function runSqlFile(string $path): void
    {
        $sql = (string) file_get_contents($path);

        // Strip line comments before splitting so stray semicolons inside
        // them do not fracture statements.
        $lines = preg_split('/\r?\n/', $sql) ?: [];
        $stripped = '';
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $stripped .= $line."\n";
        }

        foreach (array_filter(array_map('trim', explode(';', $stripped))) as $stmt) {
            if ($stmt !== '') {
                DB::statement($stmt);
            }
        }
    }

    /**
     * Triggers contain multi-statement BEGIN/END blocks that PDO::prepare()
     * rejects. Issue the whole file via unprepared() but still split into
     * single statements (CREATE TRIGGER ... END; / DROP TRIGGER ...) so each
     * trigger lands as one server-side statement.
     *
     * Only the CREATE TRIGGER statements for $only run. The file's DROPs are
     * skipped: the named triggers are missing anyway, and dropping one that
     * exists is how a working trigger got lost when the CREATE then failed.
     *
     * @param  string[]  $only  trigger names to create
     */
    private function runSqlFileUnprepared(string $path, array $only): void
    {
        $sql = (string) file_get_contents($path);
        $lines = preg_split('/\r?\n/', $sql) ?: [];
        $stripped = '';
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $stripped .= $line."\n";
        }

        // Split on semicolons that end either a DROP statement (terminal `;`
        // on its own line is fine) or that immediately follow `END`. Anything
        // else (the `IF ... THEN ... END IF;` inside the trigger body, the
        // `SIGNAL ... SET MESSAGE_TEXT;`) must remain attached to the parent
        // CREATE TRIGGER block.
        $parts = preg_split('/;\s*\n(?=(DROP\s+TRIGGER|CREATE\s+TRIGGER|$))/i', $stripped) ?: [];
        foreach ($parts as $stmt) {
            $stmt = trim(rtrim($stmt, ";\n\r\t "));
            if (! preg_match('/^CREATE\s+TRIGGER\s+`?(\w+)`?/i', $stmt, $m) || ! in_array($m[1], $only, true)) {
                continue;
            }
            DB::unprepared($stmt);
        }
    }

    private function triggerExists(string $name): bool
    {
        try {
            $rows = DB::select(
                'SELECT 1 FROM information_schema.TRIGGERS '.
                'WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ? LIMIT 1',
                [$name]
            );
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
