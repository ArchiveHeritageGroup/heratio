<?php

/**
 * BackupService - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 */

namespace AhgBackup\Services;

use AhgBackup\Services\BinaryLogArchiver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Shared backup engine. Extracted from BackupController::create so the SAME logic
 * runs from (a) a queued job dispatched by the web UI - so a large uploads tar no
 * longer blows the php-fpm request timeout and returns a non-JSON error - and
 * (b) the `backup:run` artisan command for cron / manual headless backups.
 *
 * `run()` performs a mysqldump + per-component tar and returns a structured
 * result (files, errors, metrics); the caller decides how to notify.
 */
class BackupService
{
    /**
     * @param  array<int,string>  $components  any of: database, uploads, plugins, framework
     * @param  callable|null  $log  optional fn(string $line) for progress (CLI)
     * @return array{files:array,errors:array,timestamp:string,duration_ms:int,total_bytes:int}
     */
    public function run(array $components, ?callable $log = null): array
    {
        $say = $log ?? static function () {};
        $startedAt = microtime(true);
        $backupPath = $this->backupPath();
        if (! File::isDirectory($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $timestamp = date('Y-m-d_His');
        $files = [];
        $errors = [];

        if (in_array('database', $components, true)) {
            $say('Dumping database...');
            $this->backupDatabase($backupPath, $timestamp, $files, $errors);
        }
        if (in_array('uploads', $components, true)) {
            $say('Archiving uploads (content)...');
            $this->backupTar('uploads', config('heratio.uploads_path'), $backupPath, $timestamp, $files, $errors, true);
        }
        if (in_array('plugins', $components, true)) {
            $say('Archiving plugins...');
            $this->backupTar('plugins', base_path('packages'), $backupPath, $timestamp, $files, $errors, false, 'packages');
        }
        if (in_array('framework', $components, true)) {
            $say('Archiving framework...');
            $this->backupFramework($backupPath, $timestamp, $files, $errors);
        }

        $this->enforceRetention($backupPath);

        $totalBytes = 0;
        foreach ($files as $f) {
            $p = $backupPath.'/'.($f['filename'] ?? '');
            if (File::exists($p)) {
                $totalBytes += File::size($p);
            }
        }

        return [
            'files' => $files,
            'errors' => $errors,
            'timestamp' => $timestamp,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'total_bytes' => $totalBytes,
        ];
    }

    private function backupDatabase(string $backupPath, string $timestamp, array &$files, array &$errors): void
    {
        $db = config('database.connections.'.config('database.default'));
        $name = $db['database'] ?? 'archive';
        $filename = "database_{$name}_{$timestamp}.sql.gz";
        $filepath = $backupPath.'/'.$filename;

        $cmd = 'mysqldump';
        if (! empty($db['unix_socket'])) {
            $cmd .= ' --socket='.escapeshellarg($db['unix_socket']);
        } else {
            $cmd .= ' --host='.escapeshellarg($db['host'] ?? '127.0.0.1');
            $cmd .= ' --port='.escapeshellarg((string) ($db['port'] ?? '3306'));
        }
        $cmd .= ' --user='.escapeshellarg($db['username'] ?? 'root');
        if (! empty($db['password'])) {
            $cmd .= ' --password='.escapeshellarg($db['password']);
        }
        $cmd .= ' --single-transaction --routines --triggers --events';
        $cmd .= ' '.escapeshellarg($name);
        $cmd .= ' 2>/tmp/heratio-backup-db.err | gzip > '.escapeshellarg($filepath);

        exec($cmd, $output, $rc);

        if ($rc === 0 && File::exists($filepath) && File::size($filepath) > 0) {
            $files[] = ['component' => 'database', 'filename' => $filename, 'size' => $this->human(File::size($filepath))];
            try {
                app(BinaryLogArchiver::class)->recordDumpCoordinates($filename, $filepath, $name, 'Captured by BackupService::run()');
            } catch (\Throwable $e) {
                Log::warning('[ahg-backup] PITR coordinate record failed', ['error' => $e->getMessage()]);
            }
        } else {
            if (File::exists($filepath)) {
                File::delete($filepath);
            }
            $err = @file_get_contents('/tmp/heratio-backup-db.err') ?: 'mysqldump exit code '.$rc;
            $errors[] = 'Database backup failed: '.trim($err);
        }
    }

    /** Generic single-directory tar.gz component (uploads, plugins). */
    private function backupTar(string $component, ?string $path, string $backupPath, string $timestamp, array &$files, array &$errors, bool $needsDir, ?string $literalDir = null): void
    {
        $filename = "{$component}_{$timestamp}.tar.gz";
        $filepath = $backupPath.'/'.$filename;

        if (! $path || (! File::isDirectory($path))) {
            $errors[] = ucfirst($component).' backup failed: directory not found'.($path ? " ({$path})" : '');

            return;
        }

        if ($literalDir) {
            $cmd = 'tar -czf '.escapeshellarg($filepath).' -C '.escapeshellarg(base_path()).' '.escapeshellarg($literalDir).' 2>&1';
        } else {
            $cmd = 'tar -czf '.escapeshellarg($filepath).' -C '.escapeshellarg(dirname($path)).' '.escapeshellarg(basename($path)).' 2>&1';
        }
        exec($cmd, $output, $rc);

        // GNU tar returns 1 for "file changed as we read it" (harmless for a live
        // uploads dir) - treat a non-empty archive as success even on rc=1.
        if (($rc === 0 || $rc === 1) && File::exists($filepath) && File::size($filepath) > 0) {
            $files[] = ['component' => $component, 'filename' => $filename, 'size' => $this->human(File::size($filepath))];
        } else {
            if (File::exists($filepath)) {
                File::delete($filepath);
            }
            $errors[] = ucfirst($component).' backup failed: tar exit code '.$rc;
        }
    }

    private function backupFramework(string $backupPath, string $timestamp, array &$files, array &$errors): void
    {
        $filename = "framework_{$timestamp}.tar.gz";
        $filepath = $backupPath.'/'.$filename;
        $excludes = '--exclude=vendor --exclude=node_modules --exclude=storage/logs --exclude=.git --exclude=packages';
        $cmd = 'tar -czf '.escapeshellarg($filepath).' '.$excludes.' -C '.escapeshellarg(dirname(base_path())).' '.escapeshellarg(basename(base_path())).' 2>&1';
        exec($cmd, $output, $rc);
        if (($rc === 0 || $rc === 1) && File::exists($filepath) && File::size($filepath) > 0) {
            $files[] = ['component' => 'framework', 'filename' => $filename, 'size' => $this->human(File::size($filepath))];
        } else {
            if (File::exists($filepath)) {
                File::delete($filepath);
            }
            $errors[] = 'Framework backup failed: tar exit code '.$rc;
        }
    }

    public function backupPath(): string
    {
        // Match BackupController::getBackupPath() exactly so the CLI/queued
        // backups land in the same directory the web UI lists + prunes.
        $path = \AhgCore\Services\AhgSettingsService::get('backup_path', config('heratio.backups_path'))
            ?: rtrim((string) config('heratio.storage_path', '/mnt/nas/heratio'), '/').'/backups';

        return rtrim($path, '/');
    }

    private function enforceRetention(string $backupPath): void
    {
        try {
            $max = (int) (\AhgCore\Services\AhgSettingsService::get('backup_max_backups', 10) ?: 10);
            if ($max < 1) {
                return;
            }
            // Group by run timestamp; keep the newest $max runs.
            $runs = [];
            foreach (glob($backupPath.'/*_*.{sql.gz,tar.gz}', GLOB_BRACE) ?: [] as $f) {
                if (preg_match('/(\d{4}-\d{2}-\d{2}_\d{6})/', basename($f), $m)) {
                    $runs[$m[1]][] = $f;
                }
            }
            krsort($runs);
            $i = 0;
            foreach ($runs as $ts => $group) {
                if ($i++ < $max) {
                    continue;
                }
                foreach ($group as $f) {
                    @unlink($f);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-backup] retention prune failed', ['error' => $e->getMessage()]);
        }
    }

    private function human(int $bytes, int $decimals = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), $decimals).' '.$units[$i];
    }
}
