<?php

/**
 * RestoreToPointInTimeCommand - PITR orchestrator (full restore + binlog replay)
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

use AhgBackup\Services\BinaryLogArchiver;
use Illuminate\Console\Command;

/**
 * Point-in-time recovery.
 *
 *   php artisan backup:pitr "2026-05-25 14:30:00"
 *   php artisan backup:pitr "2026-05-25 14:30:00" --dry-run
 *   php artisan backup:pitr "2026-05-25 14:30:00" --binlog-dir=/path/to/binlogs
 *   php artisan backup:pitr "2026-05-25 14:30:00" --skip-full     # only replay binlogs (assume DB already restored)
 *
 * Workflow:
 *   1. Find the most recent `ahg_backup_run` row with `dumped_at` <= target.
 *   2. Restore that full backup with `gunzip | mysql` (unless --skip-full).
 *   3. Replay every archived binlog from that run's recorded
 *      `binlog_file` forward, stopping at `--stop-datetime=<target>`.
 *
 * Operator preconditions:
 *   - `log_bin = ON` and `binlog_format = ROW` at the time the dump ran
 *   - `mysqlbinlog` on PATH
 *   - The target DB user has sufficient privileges to apply DDL+DML
 *
 * This command is DESTRUCTIVE. Always test in a sandbox DB first.
 *
 * Issue #671 Phase 4.
 */
class RestoreToPointInTimeCommand extends Command
{
    protected $signature = 'backup:pitr
                            {target : Target wall-clock time (Y-m-d H:i:s)}
                            {--dry-run : Print the plan but do not modify the database}
                            {--binlog-dir= : Override the binlog archive directory}
                            {--skip-full : Skip the full-restore step (binlog replay only)}
                            {--database= : Target database name (default: connection default)}';

    protected $description = 'Restore the database to a point in time using a full backup + binlog replay (#671 Phase 4).';

    public function handle(BinaryLogArchiver $archiver): int
    {
        $target = (string) $this->argument('target');
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $target)) {
            $this->error("Invalid target time '{$target}'. Expected format: YYYY-MM-DD HH:MM:SS");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipFull = (bool) $this->option('skip-full');
        $database = (string) ($this->option('database') ?: config('database.connections.mysql.database'));

        // 1. Locate the full backup to restore from.
        $run = $archiver->findRunForPitr($target);
        if (!$run) {
            $this->error(
                "No backup run found with dumped_at <= {$target} AND log_bin_enabled=1. ".
                'Confirm `log_bin=ON` was active for at least one full backup before the target time.'
            );
            return self::FAILURE;
        }

        $this->info('PITR plan:');
        $this->line("  target time        : {$target}");
        $this->line("  full backup        : {$run->backup_filename}");
        $this->line("  backup taken at    : {$run->dumped_at}");
        $this->line("  starting binlog    : {$run->binlog_file}");
        $this->line("  starting position  : {$run->binlog_pos}");
        $this->line("  binlog_format      : {$run->binlog_format}");
        $this->line("  target database    : {$database}");

        if ($run->binlog_format !== 'ROW') {
            $this->warn("WARNING: binlog_format at dump time was '{$run->binlog_format}'. PITR is only reliable with ROW.");
        }

        // 2. Collect binlogs to replay.
        $binlogDir = (string) ($this->option('binlog-dir') ?: $archiver->defaultBinlogDir());
        $binlogs = $archiver->binlogsFromCheckpoint((string) $run->binlog_file);
        if (empty($binlogs)) {
            $this->warn("No archived binary logs found from {$run->binlog_file} onward in ledger. Falling back to file system scan of {$binlogDir}.");
            $binlogs = $this->scanBinlogDir($binlogDir, (string) $run->binlog_file);
        }
        if (empty($binlogs)) {
            $this->error('No binary log files available for replay. Cannot perform PITR.');
            return self::FAILURE;
        }

        $this->line('  binlogs to replay  : '.count($binlogs));
        foreach ($binlogs as $b) {
            $this->line('    - '.$b);
        }

        try {
            $archiver->assertMysqlBinlogAvailable();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run - no changes applied.');
            return self::SUCCESS;
        }

        // 3. Restore the full backup (unless skipped).
        if (!$skipFull) {
            $this->info('Restoring full backup...');
            $rc = $this->restoreFullBackup((string) $run->backup_path, $database);
            if ($rc !== 0) {
                $this->error("Full restore failed (exit {$rc}).");
                return self::FAILURE;
            }
            $this->info('Full backup restored.');
        } else {
            $this->warn('--skip-full given; assuming the database is already at the post-restore state.');
        }

        // 4. Replay binlogs up to target.
        $this->info('Replaying binary logs up to '.$target.' ...');
        $rc = $this->replayBinlogs(
            $binlogs,
            (string) $run->binlog_file,
            (int) $run->binlog_pos,
            $target,
            $database
        );
        if ($rc !== 0) {
            $this->error("Binlog replay failed (exit {$rc}).");
            return self::FAILURE;
        }

        $this->info('PITR completed successfully.');
        $this->warn('Verify application data before clearing application caches and re-enabling write traffic.');
        return self::SUCCESS;
    }

    /**
     * Filesystem fallback when the binlog ledger has no entries
     * (e.g. first-time PITR before the hourly archive command has
     * ever run, but operator manually copied binlogs in place).
     */
    private function scanBinlogDir(string $dir, string $fromFile): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $entries = scandir($dir) ?: [];
        sort($entries);
        $out = [];
        $started = false;
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') { continue; }
            if (!preg_match('/\.\d+$/', $name)) { continue; }
            if (!$started && strcmp($name, $fromFile) >= 0) {
                $started = true;
            }
            if ($started) {
                $out[] = rtrim($dir, '/').'/'.$name;
            }
        }
        return $out;
    }

    private function restoreFullBackup(string $backupPath, string $database): int
    {
        $dbConfig = config('database.connections.mysql');
        $dbHost = $dbConfig['host'] ?? '127.0.0.1';
        $dbPort = $dbConfig['port'] ?? '3306';
        $dbUser = $dbConfig['username'] ?? 'root';
        $dbPass = $dbConfig['password'] ?? '';
        $dbSocket = $dbConfig['unix_socket'] ?? '';

        $reader = str_ends_with($backupPath, '.gz')
            ? 'gunzip -c '.escapeshellarg($backupPath)
            : 'cat '.escapeshellarg($backupPath);

        $cmd = $reader.' | mysql';
        if ($dbSocket) {
            $cmd .= ' --socket='.escapeshellarg($dbSocket);
        } else {
            $cmd .= ' --host='.escapeshellarg($dbHost);
            $cmd .= ' --port='.escapeshellarg($dbPort);
        }
        $cmd .= ' --user='.escapeshellarg($dbUser);
        if ($dbPass) {
            $cmd .= ' --password='.escapeshellarg($dbPass);
        }
        $cmd .= ' '.escapeshellarg($database).' 2>&1';

        $rc = 0;
        $out = [];
        exec($cmd, $out, $rc);
        if ($rc !== 0) {
            foreach ($out as $l) { $this->line('    '.$l); }
        }
        return $rc;
    }

    /**
     * Pipe `mysqlbinlog` over every file in $files into the live
     * server, with --stop-datetime bounding the replay and
     * --start-position applied only to the first file.
     */
    private function replayBinlogs(array $files, string $firstFile, int $startPos, string $target, string $database): int
    {
        $dbConfig = config('database.connections.mysql');
        $dbHost = $dbConfig['host'] ?? '127.0.0.1';
        $dbPort = $dbConfig['port'] ?? '3306';
        $dbUser = $dbConfig['username'] ?? 'root';
        $dbPass = $dbConfig['password'] ?? '';
        $dbSocket = $dbConfig['unix_socket'] ?? '';

        $mysqlCmd = 'mysql';
        if ($dbSocket) {
            $mysqlCmd .= ' --socket='.escapeshellarg($dbSocket);
        } else {
            $mysqlCmd .= ' --host='.escapeshellarg($dbHost);
            $mysqlCmd .= ' --port='.escapeshellarg($dbPort);
        }
        $mysqlCmd .= ' --user='.escapeshellarg($dbUser);
        if ($dbPass) {
            $mysqlCmd .= ' --password='.escapeshellarg($dbPass);
        }
        $mysqlCmd .= ' '.escapeshellarg($database);

        foreach ($files as $idx => $file) {
            $this->line('  replay: '.basename($file));
            $args = '--database='.escapeshellarg($database);
            $args .= ' --stop-datetime='.escapeshellarg($target);
            if ($idx === 0 && basename($file) === $firstFile && $startPos > 0) {
                $args .= ' --start-position='.escapeshellarg((string) $startPos);
            }
            $cmd = 'mysqlbinlog '.$args.' '.escapeshellarg($file).' | '.$mysqlCmd.' 2>&1';
            $rc = 0;
            $out = [];
            exec($cmd, $out, $rc);
            if ($rc !== 0) {
                foreach ($out as $l) { $this->line('    '.$l); }
                return $rc;
            }
        }
        return 0;
    }
}
