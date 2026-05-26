<?php

/**
 * RestoreTableCommand - granular restore for a single table or filtered subset
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

namespace AhgBackup\Console\Commands;

use AhgBackup\Services\GranularRestoreService;
use Illuminate\Console\Command;

/**
 * Restore one table (optionally filtered with --where) from a full
 * mysqldump backup, applying rows via INSERT ... ON DUPLICATE KEY
 * UPDATE inside a single transaction.
 *
 *   php artisan backup:restore-table information_object_i18n /path/to/backup.sql.gz --where="id=42"
 *   php artisan backup:restore-table actor_i18n              /path/to/backup.sql.gz
 *
 * Issue #671 Phase 4.
 */
class RestoreTableCommand extends Command
{
    protected $signature = 'backup:restore-table
                            {table : Table name to restore}
                            {backup : Absolute path to .sql.gz full backup}
                            {--where= : Optional MySQL-syntax WHERE clause (no trailing semicolon)}
                            {--yes : Skip the interactive confirmation prompt}';

    protected $description = 'Restore one table (or a filtered subset) from a full backup (#671 Phase 4).';

    public function handle(GranularRestoreService $service): int
    {
        $table = (string) $this->argument('table');
        $backup = (string) $this->argument('backup');
        $where = $this->option('where');

        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            $this->error("Refusing unsafe table name: {$table}");
            return self::FAILURE;
        }
        if (!is_file($backup)) {
            $this->error("Backup file not found: {$backup}");
            return self::FAILURE;
        }

        $this->warn('Granular restore can break referential integrity. Verify in dev first.');
        $this->line("  table  : {$table}");
        $this->line("  backup : {$backup}");
        $this->line('  where  : '.($where ?: '(none, all rows)'));

        if (!$this->option('yes') && !$this->confirm('Proceed with restore?', false)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        try {
            $result = $service->restoreTable($table, $backup, $where);
        } catch (\Throwable $e) {
            $this->error('Restore failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Restore complete - %d row(s) applied to %s.', $result['statements'], $table));
        if ($result['statements'] === 0) {
            $this->warn('No matching rows found in backup. Confirm table name and WHERE clause.');
        }
        return self::SUCCESS;
    }
}
