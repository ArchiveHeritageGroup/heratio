<?php

/**
 * BinaryLogArchiver - capture MySQL binary-log coordinates and archive rotated logs
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

namespace AhgBackup\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Point-in-time recovery support (#671 Phase 4).
 *
 * Two responsibilities, both bounded:
 *
 *   1. `recordDumpCoordinates()` - called by the backup pipeline right
 *      after a successful mysqldump. Reads `SHOW MASTER STATUS` and
 *      `@@binlog_format` / `@@log_bin`, then inserts one row into
 *      `ahg_backup_run` keyed on the dump filename. PITR uses this to
 *      know which binlog file + position to replay from.
 *
 *   2. `archiveRotatedLogs()` - called hourly by the
 *      `backup:archive-binlogs` artisan command. Issues
 *      `FLUSH BINARY LOGS` (forces MySQL to close the active log and
 *      start a new one), then copies every closed binlog file out of
 *      MySQL's datadir into `storage/backups/binlogs/` and records one
 *      row in `ahg_backup_binlog` per file. The Phase 3 off-site
 *      replicator then picks those files up on its next sweep.
 *
 * Preconditions (operator-enabled, NOT enabled by this service):
 *   - `log_bin = ON`
 *   - `binlog_format = ROW`
 *   - `binlog_row_image = FULL` (recommended)
 *
 * If `log_bin` is OFF the service still records the dump-time state
 * (so PITR knows the run is non-replayable) but `archiveRotatedLogs()`
 * becomes a no-op with a loud warning.
 */
class BinaryLogArchiver
{
    /**
     * Default destination subdirectory under the configured backups
     * root. Kept inside the backups dir so the off-site replicator
     * already covers it without further wiring.
     */
    public const BINLOG_SUBDIR = 'binlogs';

    /**
     * Look up the current binary-log position and persist it against
     * the supplied backup filename. Returns the inserted row id (or
     * the existing row's id when the filename is already on file).
     *
     * @param string $backupFilename  Just the basename of the dump file.
     * @param string $backupPath      Absolute path to the dump file on disk.
     * @param string|null $dbName     Logical DB name being dumped.
     * @param string|null $notes      Free-text annotation.
     */
    public function recordDumpCoordinates(
        string $backupFilename,
        string $backupPath,
        ?string $dbName = null,
        ?string $notes = null
    ): int {
        $dbName = $dbName ?: (string) config('database.connections.mysql.database', 'heratio');

        $coords = $this->fetchMasterStatus();
        $format = $this->fetchBinlogFormat();
        $logBin = $this->fetchLogBinEnabled();

        $row = [
            'backup_path'     => $backupPath,
            'backup_filename' => $backupFilename,
            'dumped_at'       => date('Y-m-d H:i:s'),
            'db_name'         => $dbName,
            'binlog_file'     => $coords['file'],
            'binlog_pos'      => $coords['pos'],
            'gtid_executed'   => $coords['gtid'],
            'binlog_format'   => $format,
            'log_bin_enabled' => $logBin ? 1 : 0,
            'notes'           => $notes,
        ];

        $existing = DB::table('ahg_backup_run')
            ->where('backup_filename', $backupFilename)
            ->first();
        if ($existing) {
            DB::table('ahg_backup_run')->where('id', $existing->id)->update($row);
            return (int) $existing->id;
        }
        return (int) DB::table('ahg_backup_run')->insertGetId($row);
    }

    /**
     * Flush + copy all closed binlog files out of the MySQL datadir.
     * Returns an array of archived basenames.
     *
     * @param string|null $destDir  Override the destination directory.
     */
    public function archiveRotatedLogs(?string $destDir = null): array
    {
        if (!$this->fetchLogBinEnabled()) {
            Log::warning('[ahg-backup] binlog archive skipped - log_bin is OFF on this MySQL server.');
            return [];
        }

        $destDir = $destDir ?: $this->defaultBinlogDir();
        if (!File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0750, true);
        }

        // Force MySQL to close the active binlog so we have a stable
        // set of fully-written files to copy. Ignored gracefully on
        // permission errors - we still archive whatever closed files
        // are present.
        try {
            DB::statement('FLUSH BINARY LOGS');
        } catch (\Throwable $e) {
            Log::warning('[ahg-backup] FLUSH BINARY LOGS failed; carrying on with existing closed files.', [
                'error' => $e->getMessage(),
            ]);
        }

        $files = $this->listBinaryLogs();
        if (empty($files)) {
            return [];
        }

        // The currently-active file is the LAST one (highest sequence)
        // returned by SHOW BINARY LOGS. Don't archive it - it can grow
        // mid-copy. Everything before it is closed.
        array_pop($files);

        $archived = [];
        $datadir = $this->fetchDatadir();
        foreach ($files as $entry) {
            $filename = $entry['name'];
            $src = rtrim($datadir, '/').'/'.$filename;
            $dst = rtrim($destDir, '/').'/'.$filename;

            if (file_exists($dst)) {
                // Already archived in a previous run. Idempotent.
                continue;
            }
            if (!is_readable($src)) {
                Log::warning('[ahg-backup] binlog not readable - skipping', ['src' => $src]);
                continue;
            }
            if (!@copy($src, $dst)) {
                Log::warning('[ahg-backup] binlog copy failed', ['src' => $src, 'dst' => $dst]);
                continue;
            }

            $size = (int) @filesize($dst);
            $sha  = (string) @hash_file('sha256', $dst);

            try {
                $existing = DB::table('ahg_backup_binlog')
                    ->where('binlog_file', $filename)
                    ->first();
                $row = [
                    'binlog_file'  => $filename,
                    'archive_path' => $dst,
                    'size_bytes'   => $size,
                    'sha256'       => $sha ?: null,
                    'archived_at'  => date('Y-m-d H:i:s'),
                ];
                if ($existing) {
                    DB::table('ahg_backup_binlog')->where('id', $existing->id)->update($row);
                } else {
                    DB::table('ahg_backup_binlog')->insert($row);
                }
            } catch (\Throwable $e) {
                Log::warning('[ahg-backup] binlog ledger write failed', [
                    'file'  => $filename,
                    'error' => $e->getMessage(),
                ]);
            }

            $archived[] = $filename;
        }

        return $archived;
    }

    /**
     * Default archive directory: <backups>/binlogs.
     */
    public function defaultBinlogDir(): string
    {
        $root = (string) config('heratio.backups_path', storage_path('backups'));
        return rtrim($root, '/').'/'.self::BINLOG_SUBDIR;
    }

    /**
     * Find the closest run in `ahg_backup_run` whose dumped_at is
     * <= the supplied target time. Returns the row or null if none.
     *
     * @param string $targetTime  Y-m-d H:i:s in server-local time.
     */
    public function findRunForPitr(string $targetTime): ?object
    {
        return DB::table('ahg_backup_run')
            ->where('dumped_at', '<=', $targetTime)
            ->where('log_bin_enabled', 1)
            ->orderByDesc('dumped_at')
            ->first();
    }

    /**
     * Return the archived binlog files relevant for a PITR replay
     * starting from `$startingFile` (inclusive). Caller passes the
     * result of `findRunForPitr()`->binlog_file.
     *
     * Returns a sorted list of absolute paths.
     */
    public function binlogsFromCheckpoint(string $startingFile): array
    {
        $rows = DB::table('ahg_backup_binlog')
            ->orderBy('binlog_file')
            ->get();

        $started = false;
        $out = [];
        foreach ($rows as $r) {
            if (!$started && strcmp($r->binlog_file, $startingFile) >= 0) {
                $started = true;
            }
            if ($started) {
                if (is_file($r->archive_path)) {
                    $out[] = $r->archive_path;
                }
            }
        }
        return $out;
    }

    /**
     * SHOW MASTER STATUS -> ['file' => ..., 'pos' => ..., 'gtid' => ...].
     */
    private function fetchMasterStatus(): array
    {
        try {
            $row = DB::selectOne('SHOW MASTER STATUS');
        } catch (\Throwable $e) {
            // MySQL 8.4+ renamed SHOW MASTER STATUS to SHOW BINARY LOG STATUS.
            try {
                $row = DB::selectOne('SHOW BINARY LOG STATUS');
            } catch (\Throwable $e2) {
                return ['file' => null, 'pos' => null, 'gtid' => null];
            }
        }
        if (!$row) {
            return ['file' => null, 'pos' => null, 'gtid' => null];
        }
        $arr = (array) $row;
        return [
            'file' => $arr['File'] ?? $arr['file'] ?? null,
            'pos'  => isset($arr['Position']) ? (int) $arr['Position'] : null,
            'gtid' => $arr['Executed_Gtid_Set'] ?? $arr['executed_gtid_set'] ?? null,
        ];
    }

    private function fetchBinlogFormat(): ?string
    {
        try {
            $row = DB::selectOne("SHOW VARIABLES LIKE 'binlog_format'");
            return $row ? (string) ($row->Value ?? $row->value ?? '') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchLogBinEnabled(): bool
    {
        try {
            $row = DB::selectOne("SHOW VARIABLES LIKE 'log_bin'");
            $val = $row->Value ?? $row->value ?? '';
            return strtoupper((string) $val) === 'ON';
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function fetchDatadir(): string
    {
        try {
            $row = DB::selectOne("SHOW VARIABLES LIKE 'datadir'");
            return (string) ($row->Value ?? $row->value ?? '/var/lib/mysql');
        } catch (\Throwable $e) {
            return '/var/lib/mysql';
        }
    }

    /**
     * SHOW BINARY LOGS returns rows of [Log_name, File_size, ...].
     * MySQL emits them in ascending sequence order.
     *
     * @return array<int, array{name: string, size: int}>
     */
    private function listBinaryLogs(): array
    {
        try {
            $rows = DB::select('SHOW BINARY LOGS');
        } catch (\Throwable $e) {
            Log::warning('[ahg-backup] SHOW BINARY LOGS failed', ['error' => $e->getMessage()]);
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $arr = (array) $r;
            $name = $arr['Log_name'] ?? $arr['log_name'] ?? null;
            if ($name === null) {
                continue;
            }
            $out[] = [
                'name' => (string) $name,
                'size' => (int) ($arr['File_size'] ?? $arr['file_size'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Raise if mysqlbinlog isn't on PATH. Used by the PITR command.
     */
    public function assertMysqlBinlogAvailable(): void
    {
        $rc = 0;
        $out = [];
        @exec('command -v mysqlbinlog', $out, $rc);
        if ($rc !== 0 || empty($out)) {
            throw new RuntimeException(
                'BinaryLogArchiver: `mysqlbinlog` binary is not on PATH. Install mysql-client to enable PITR replay.'
            );
        }
    }
}
