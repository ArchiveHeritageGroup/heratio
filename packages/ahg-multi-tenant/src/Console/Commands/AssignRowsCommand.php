<?php

/**
 * multi-tenant:assign-rows - bulk-assign tenant_id on existing rows.
 *
 * Phase 1 of issue #651. Designed for the migration path when a
 * single-tenant install adds the second tenant and needs to bind the
 * historical rows to a specific tenant.
 *
 * Usage:
 *
 *   php artisan multi-tenant:assign-rows information_object 1
 *   php artisan multi-tenant:assign-rows digital_object 2 --where="created_at < '2026-01-01'"
 *   php artisan multi-tenant:assign-rows audit_log 1 --dry-run
 *
 * Safety:
 *   - Refuses to run if the target table has no `tenant_id` column unless
 *     --force is passed (idiot-proofing - prevents a typo'd table name
 *     from silently failing).
 *   - The {tenant} argument may be the integer id OR the code of a row
 *     in ahg_tenant. Code is preferred in scripted invocations because
 *     ids drift across installs.
 *   - Updates are wrapped in a transaction. Failure rolls back.
 *   - On success, a single ahg_audit_log row is emitted with action
 *     'multi_tenant.assign_rows' and metadata {table, tenant_id, where,
 *     rows_affected}. AuditLogger from #676 - if it's not bound yet
 *     (early-boot / partial install) we no-op the audit step rather
 *     than abort.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Console\Commands;

use AhgMultiTenant\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AssignRowsCommand extends Command
{
    protected $signature = 'multi-tenant:assign-rows
        {table : Target table name (e.g. information_object, digital_object)}
        {tenant : Tenant id or code (must exist in ahg_tenant)}
        {--where= : SQL fragment for filtering rows (without "WHERE")}
        {--dry-run : Show row count without writing}
        {--force : Allow running against a table that has no tenant_id column}';

    protected $description = 'Bulk-assign tenant_id on rows of an existing table (Phase 1 of issue #651).';

    public function handle(): int
    {
        $table = (string) $this->argument('table');
        $tenantArg = (string) $this->argument('tenant');
        $where = (string) ($this->option('where') ?? '');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($table === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $this->error('Invalid table name. Use [a-zA-Z0-9_]+ only.');
            return self::FAILURE;
        }

        if (! Schema::hasTable($table)) {
            $this->error("Table {$table} does not exist.");
            return self::FAILURE;
        }

        $hasTenantCol = Schema::hasColumn($table, 'tenant_id');
        if (! $hasTenantCol && ! $force) {
            $this->error("Table {$table} has no tenant_id column. Re-run with --force only if you really mean it (you almost certainly do not).");
            return self::FAILURE;
        }

        $tenant = $this->resolveTenant($tenantArg);
        if (! $tenant) {
            $this->error("Tenant '{$tenantArg}' not found in ahg_tenant (tried id then code).");
            return self::FAILURE;
        }
        $tenantId = (int) $tenant->id;

        // Build the row count probe. We use the same WHERE on the probe
        // and the UPDATE so the dry-run number always matches the wet
        // count (assuming no concurrent writes between the two queries -
        // single-tenant migration windows always satisfy that).
        $query = DB::table($table);
        if ($where !== '') {
            try {
                $query->whereRaw($where);
            } catch (Throwable $e) {
                $this->error('Invalid --where fragment: '.$e->getMessage());
                return self::FAILURE;
            }
        }

        try {
            $count = (int) $query->count();
        } catch (Throwable $e) {
            $this->error('Row count probe failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Target: %s   Tenant: %s (id=%d)   Where: %s   Matched rows: %d',
            $table,
            $tenant->code ?? '?',
            $tenantId,
            $where === '' ? '(all rows)' : $where,
            $count,
        ));

        if ($dryRun) {
            $this->warn('[dry-run] No rows written. Drop --dry-run to apply.');
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->warn('Nothing to do - 0 rows match.');
            return self::SUCCESS;
        }

        if (! $hasTenantCol) {
            $this->warn('--force in effect: table has no tenant_id column. Skipping the UPDATE; audit row will note the skip.');
            $this->writeAuditRow($table, $tenantId, $where, 0, skipped: true);
            return self::SUCCESS;
        }

        $affected = 0;
        try {
            DB::beginTransaction();

            $upd = DB::table($table);
            if ($where !== '') {
                $upd->whereRaw($where);
            }
            $affected = (int) $upd->update(['tenant_id' => $tenantId]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('UPDATE failed (rolled back): '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info("Updated rows: {$affected}");
        $this->writeAuditRow($table, $tenantId, $where, $affected, skipped: false);
        return self::SUCCESS;
    }

    /**
     * Look up the tenant - try id first (when arg is numeric), then code.
     */
    private function resolveTenant(string $arg)
    {
        if (! Schema::hasTable('ahg_tenant')) {
            return null;
        }
        if (ctype_digit($arg)) {
            $byId = Tenant::query()->where('id', (int) $arg)->first();
            if ($byId) {
                return $byId;
            }
        }
        return Tenant::query()->where('code', $arg)->first();
    }

    /**
     * Emit an ahg_audit_log row describing the bulk assignment.
     *
     * We resolve AuditLogger via the container at runtime so this command
     * keeps working in environments where ahg-audit-trail is not yet
     * booted (fresh install, CI smoke checks). When the logger class
     * is unreachable we just skip the audit step rather than fail.
     */
    private function writeAuditRow(string $table, int $tenantId, string $where, int $rows, bool $skipped): void
    {
        try {
            if (! class_exists(\AhgAuditTrail\Services\AuditLogger::class)) {
                return;
            }
            $logger = app()->make(\AhgAuditTrail\Services\AuditLogger::class);
            if (method_exists($logger, 'withTenant')) {
                $logger = $logger->withTenant($tenantId);
            }
            $logger->logAction(
                action: 'multi_tenant.assign_rows',
                entityType: $table,
                entityId: null,
                metadata: [
                    'table' => $table,
                    'tenant_id' => $tenantId,
                    'where' => $where === '' ? null : $where,
                    'rows_affected' => $rows,
                    'skipped' => $skipped,
                    'source' => 'multi-tenant:assign-rows',
                ],
            );
        } catch (Throwable $e) {
            // Never let audit break the migration step. The UPDATE
            // already committed; we just lose the breadcrumb.
        }
    }
}
