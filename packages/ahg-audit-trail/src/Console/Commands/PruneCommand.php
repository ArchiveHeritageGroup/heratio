<?php

/**
 * audit:prune — delete audit-log rows older than the configured retention.
 *
 * Reads ahg_settings.audit_retention_days (compliance group, default 365).
 * Prunes security_audit_log + every other audit-shaped table with a
 * created_at column. Skips tables that don't exist (defensive — different
 * Heratio installs have different module sets).
 *
 * Daily schedule registered in AhgAuditTrailServiceProvider::boot().
 * Manual run from /admin/ahgSettings/compliance via the "Run prune now"
 * button (POST /admin/audit-trail/prune).
 *
 * A retention value of 0 (or empty) disables pruning — existing rows stay
 * forever. Useful for compliance regimes that mandate permanent retention.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuditTrail\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneCommand extends Command
{
    protected $signature = 'audit:prune {--dry-run : Report what would be pruned without deleting} {--days= : Override retention days for this run}';
    protected $description = 'Delete audit-log rows older than ahg_settings.audit_retention_days';

    /**
     * Audit-shaped tables that share the {created_at} retention semantics.
     * Each entry is [table, column-with-timestamp]. Tables that don't exist
     * on this install are silently skipped at runtime.
     */
    private const TABLES = [
        ['security_audit_log',           'created_at'],
        ['access_audit_log',             'created_at'],
        ['audit_log',                    'created_at'],
        ['ahg_audit_log',                'created_at'],
        ['ahg_audit_access',             'created_at'],
        ['ahg_audit_authentication',     'created_at'],
        ['ahg_encryption_audit',         'created_at'],
        ['cdpa_audit_log',               'created_at'],
        ['embargo_audit',                'created_at'],
        ['heritage_audit_log',           'created_at'],
        ['ipsas_audit_log',              'created_at'],
        ['naz_audit_log',                'created_at'],
        ['nmmz_audit_log',               'created_at'],
        ['object_3d_audit_log',          'created_at'],
        ['openric_audit_log',            'created_at'],
        ['privacy_audit_log',            'created_at'],
        ['research_researcher_audit',    'archived_at'],
        ['spectrum_audit_log',           'created_at'],
        ['display_mode_audit',           'created_at'],
        ['atom_extension_audit',         'created_at'],
        ['atom_isbn_lookup_audit',       'created_at'],
        ['atom_landing_page_audit_log',  'created_at'],
        ['atom_plugin_audit',            'created_at'],
    ];

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? $this->retentionDays());
        if ($days <= 0) {
            $this->info('Retention disabled (audit_retention_days <= 0). Nothing to prune.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->format('Y-m-d H:i:s');
        $dry = (bool) $this->option('dry-run');
        $this->line(($dry ? '[DRY RUN] ' : '') . "Pruning rows older than {$cutoff} (retention {$days} days)");

        $totalRows = 0;
        $totalTables = 0;
        foreach (self::TABLES as [$table, $col]) {
            if (!Schema::hasTable($table)) continue;
            // Different installs / migrations have different audit-table
            // column shapes (some use created_at, some timestamp, some have
            // the column on a sister table). Skip silently when absent so
            // a missing column on one table doesn't abort the whole prune.
            if (!Schema::hasColumn($table, $col)) continue;
            $count = DB::table($table)->where($col, '<', $cutoff)->count();
            if ($count === 0) continue;
            $totalTables++;
            $totalRows += $count;
            if ($dry) {
                $this->line(sprintf('  %-30s %d row(s) would be deleted', $table, $count));
            } else {
                $deleted = DB::table($table)->where($col, '<', $cutoff)->delete();
                $this->line(sprintf('  %-30s %d row(s) deleted', $table, $deleted));
            }
        }

        // Stamp the last-prune timestamp + outcome in ahg_settings so the
        // compliance UI can surface it.
        if (!$dry) {
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => 'audit_last_pruned_at'],
                ['setting_value' => now()->format('Y-m-d H:i:s'), 'setting_group' => 'compliance', 'setting_type' => 'string', 'updated_at' => now()]
            );
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => 'audit_last_pruned_rows'],
                ['setting_value' => (string) $totalRows, 'setting_group' => 'compliance', 'setting_type' => 'integer', 'updated_at' => now()]
            );
        }

        $this->info(sprintf('%s%d row(s) across %d table(s) %s.',
            $dry ? '[DRY RUN] ' : '',
            $totalRows,
            $totalTables,
            $dry ? 'would be removed' : 'removed'));
        return self::SUCCESS;
    }

    private function retentionDays(): int
    {
        return (int) (DB::table('ahg_settings')
            ->where('setting_key', 'audit_retention_days')
            ->value('setting_value') ?: 365);
    }
}
