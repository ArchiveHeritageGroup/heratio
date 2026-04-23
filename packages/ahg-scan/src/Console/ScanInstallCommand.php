<?php

/**
 * ScanInstallCommand — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Apply the ahg-scan schema + dropdown seed, idempotently.
 *
 * Runs column-by-column checks against INFORMATION_SCHEMA so it's safe
 * to re-run and doesn't trip over MySQL's prepared-statement buffering
 * the way a raw install.sql with SET/PREPARE/EXECUTE blocks would.
 */
class ScanInstallCommand extends Command
{
    protected $signature = 'ahg:scan-install';
    protected $description = 'Apply ahg-scan schema + dropdown seed (idempotent)';

    public function handle(): int
    {
        $this->installSchema();
        $this->installDropdowns();
        return self::SUCCESS;
    }

    // ---------------------------------------------------------------------
    // Schema
    // ---------------------------------------------------------------------

    protected function installSchema(): void
    {
        $this->line('Schema:');

        $this->addColumn('ingest_session', 'session_kind',
            "ALTER TABLE ingest_session ADD COLUMN session_kind VARCHAR(32) NOT NULL DEFAULT 'wizard' AFTER entity_type, ADD KEY ix_session_kind (session_kind)");
        $this->addColumn('ingest_session', 'auto_commit',
            "ALTER TABLE ingest_session ADD COLUMN auto_commit TINYINT(1) NOT NULL DEFAULT 0 AFTER session_kind");
        $this->addColumn('ingest_session', 'source_ref',
            "ALTER TABLE ingest_session ADD COLUMN source_ref VARCHAR(255) NULL AFTER auto_commit");

        $this->addColumn('ingest_file', 'status',
            "ALTER TABLE ingest_file ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending' AFTER extracted_path, ADD KEY ix_ingest_file_status (status)");
        $this->addColumn('ingest_file', 'stage',
            "ALTER TABLE ingest_file ADD COLUMN stage VARCHAR(32) NULL AFTER status");
        $this->addColumn('ingest_file', 'source_hash',
            "ALTER TABLE ingest_file ADD COLUMN source_hash CHAR(64) NULL AFTER stage, ADD KEY ix_ingest_file_hash (source_hash)");
        $this->addColumn('ingest_file', 'error_message',
            "ALTER TABLE ingest_file ADD COLUMN error_message TEXT NULL AFTER source_hash");
        $this->addColumn('ingest_file', 'attempts',
            "ALTER TABLE ingest_file ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER error_message");
        $this->addColumn('ingest_file', 'resolved_io_id',
            "ALTER TABLE ingest_file ADD COLUMN resolved_io_id INT NULL AFTER attempts, ADD KEY ix_ingest_file_io (resolved_io_id)");
        $this->addColumn('ingest_file', 'resolved_do_id',
            "ALTER TABLE ingest_file ADD COLUMN resolved_do_id INT NULL AFTER resolved_io_id");
        $this->addColumn('ingest_file', 'completed_at',
            "ALTER TABLE ingest_file ADD COLUMN completed_at DATETIME NULL AFTER resolved_do_id");

        $this->createTable('scan_folder', <<<SQL
CREATE TABLE `scan_folder` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `layout` VARCHAR(32) NOT NULL DEFAULT 'path',
  `ingest_session_id` INT NOT NULL,
  `disposition_success` VARCHAR(32) NOT NULL DEFAULT 'move',
  `disposition_failure` VARCHAR(32) NOT NULL DEFAULT 'quarantine',
  `min_quiet_seconds` INT NOT NULL DEFAULT 10,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `last_scanned_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scan_folder_code` (`code`),
  KEY `ix_scan_folder_enabled` (`enabled`),
  KEY `ix_scan_folder_session` (`ingest_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
    }

    protected function addColumn(string $table, string $column, string $alterSql): void
    {
        if (!Schema::hasTable($table)) {
            $this->warn("  skip {$table}.{$column} — table missing");
            return;
        }
        if (Schema::hasColumn($table, $column)) {
            $this->line("  {$table}.{$column} exists");
            return;
        }
        try {
            DB::statement($alterSql);
            $this->info("  + {$table}.{$column} added");
        } catch (\Throwable $e) {
            $this->error("  ! {$table}.{$column}: " . $e->getMessage());
        }
    }

    protected function createTable(string $table, string $createSql): void
    {
        if (Schema::hasTable($table)) {
            $this->line("  {$table} exists");
            return;
        }
        try {
            DB::statement($createSql);
            $this->info("  + {$table} created");
        } catch (\Throwable $e) {
            $this->error("  ! {$table}: " . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------
    // Dropdowns
    // ---------------------------------------------------------------------

    protected function installDropdowns(): void
    {
        $this->line('Dropdowns:');

        if (!Schema::hasTable('ahg_dropdown')) {
            $this->warn('  skip — ahg_dropdown table missing');
            return;
        }

        $groups = [
            ['taxonomy' => 'ingest_session_kind', 'label' => 'Ingest Session Kind', 'section' => 'ingest', 'rows' => [
                ['wizard', 'Wizard (interactive)', '#0d6efd', 'hat-wizard', 10],
                ['watched_folder', 'Watched folder', '#198754', 'folder-open', 20],
                ['scan_api', 'Scan API', '#6f42c1', 'plug', 30],
            ]],
            ['taxonomy' => 'ingest_file_status', 'label' => 'Ingest File Status', 'section' => 'ingest', 'rows' => [
                ['pending', 'Pending', '#6c757d', 'clock', 10],
                ['processing', 'Processing', '#0d6efd', 'spinner', 20],
                ['done', 'Done', '#198754', 'check-circle', 30],
                ['failed', 'Failed', '#dc3545', 'exclamation-triangle', 40],
                ['duplicate', 'Duplicate (already ingested)', '#ffc107', 'clone', 50],
                ['quarantined', 'Quarantined', '#fd7e14', 'shield-virus', 60],
            ]],
            ['taxonomy' => 'ingest_file_stage', 'label' => 'Ingest File Stage', 'section' => 'ingest', 'rows' => [
                ['virus', 'Virus scan', null, null, 10],
                ['meta', 'Extract metadata', null, null, 20],
                ['io', 'Resolve / create IO', null, null, 30],
                ['do', 'Create digital object', null, null, 40],
                ['deriving', 'Generating derivatives', null, null, 50],
                ['indexing', 'Indexing', null, null, 60],
            ]],
            ['taxonomy' => 'scan_folder_layout', 'label' => 'Scan Folder Layout', 'section' => 'scan', 'rows' => [
                ['path', 'Path as destination', null, null, 10],
                ['flat-sidecar', 'Flat files with XML sidecar', null, null, 20],
            ]],
            ['taxonomy' => 'scan_disposition', 'label' => 'Scan Disposition', 'section' => 'scan', 'rows' => [
                ['move', 'Move to archive folder', null, null, 10],
                ['quarantine', 'Move to quarantine folder', null, null, 20],
                ['leave', 'Leave in place', null, null, 30],
                ['delete', 'Delete (not recommended)', null, null, 40],
            ]],
        ];

        $inserted = 0;
        foreach ($groups as $g) {
            foreach ($g['rows'] as [$code, $label, $color, $icon, $order]) {
                $exists = DB::table('ahg_dropdown')
                    ->where('taxonomy', $g['taxonomy'])
                    ->where('code', $code)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('ahg_dropdown')->insert([
                    'taxonomy' => $g['taxonomy'],
                    'taxonomy_label' => $g['label'],
                    'taxonomy_section' => $g['section'],
                    'code' => $code,
                    'label' => $label,
                    'color' => $color,
                    'icon' => $icon,
                    'sort_order' => $order,
                    'is_active' => 1,
                    'created_at' => now(),
                ]);
                $inserted++;
            }
        }

        $this->info("  {$inserted} dropdown row(s) inserted");
    }
}
