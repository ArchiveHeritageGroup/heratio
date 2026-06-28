<?php

/**
 * BackupController - Controller for Heratio
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

namespace AhgBackup\Controllers;

use AhgBackup\Mail\BackupCompletedMail;
use AhgBackup\Mail\BackupFailedMail;
use AhgBackup\Services\BinaryLogArchiver;
use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BackupController extends Controller
{
    /**
     * Get the configured backup path.
     */
    private function getBackupPath(): string
    {
        return AhgSettingsService::get('backup_path', config('heratio.backups_path'));
    }

    /**
     * List existing backup files in the backup directory.
     */
    private function listBackups(): array
    {
        $path = $this->getBackupPath();

        if (! File::isDirectory($path)) {
            return [];
        }

        $files = collect(File::files($path))
            ->filter(function ($file) {
                return preg_match('/\.(gz|tar\.gz|sql\.gz|zip)$/i', $file->getFilename());
            })
            ->map(function ($file) {
                $name = $file->getFilename();

                // Determine type from filename
                $type = 'unknown';
                if (str_contains($name, 'full-backup')) {
                    $type = 'full';
                } elseif (str_contains($name, 'database') || str_contains($name, '.sql.gz')) {
                    $type = 'database';
                } elseif (str_contains($name, 'uploads')) {
                    $type = 'uploads';
                } elseif (str_contains($name, 'plugins')) {
                    $type = 'plugins';
                } elseif (str_contains($name, 'framework')) {
                    $type = 'framework';
                }

                // Determine components from filename
                $components = [];
                if ($type === 'full' || str_contains($name, 'database') || str_ends_with($name, '.sql.gz')) {
                    $components[] = 'database';
                }
                if ($type === 'full' || str_contains($name, 'uploads')) {
                    $components[] = 'uploads';
                }
                if ($type === 'full' || str_contains($name, 'plugins')) {
                    $components[] = 'plugins';
                }
                if ($type === 'full' || str_contains($name, 'framework')) {
                    $components[] = 'framework';
                }
                if (empty($components)) {
                    $components[] = 'database';
                }

                return [
                    'id' => md5($name),
                    'filename' => $name,
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'size_human' => $this->humanFileSize($file->getSize()),
                    'date' => date('Y-m-d H:i:s', $file->getMTime()),
                    'timestamp' => $file->getMTime(),
                    'type' => $type,
                    'components' => $components,
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();

        return $files;
    }

    /**
     * Format bytes to human-readable size.
     */
    private function humanFileSize(int $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        $factor = min($factor, count($units) - 1);

        return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Backup dashboard.
     */
    public function index()
    {
        $dbConfig = config('database.connections.mysql');
        $backupPath = $this->getBackupPath();
        $backups = $this->listBackups();

        $totalSize = array_sum(array_column($backups, 'size'));

        // Settings
        $maxBackups = AhgSettingsService::getInt('backup_max_backups', 10);
        $retentionDays = AhgSettingsService::getInt('backup_retention_days', 30);
        $notificationEmail = AhgSettingsService::get('backup_notification_email', '');

        // Scheduled backups
        $schedules = [];
        $scheduleData = AhgSettingsService::getGroup('backup_schedule');
        if (! empty($scheduleData)) {
            foreach ($scheduleData as $key => $value) {
                if (str_starts_with($key, 'backup_schedule_')) {
                    $decoded = json_decode($value, true);
                    if ($decoded) {
                        $schedules[] = $decoded;
                    }
                }
            }
        }

        // Test DB connection
        $dbConnected = false;
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {
            // Connection failed
        }

        return view('ahg-backup::index', [
            'dbConfig' => $dbConfig,
            'dbConnected' => $dbConnected,
            'backupPath' => $backupPath,
            'backups' => $backups,
            'backupCount' => count($backups),
            'totalSize' => $this->humanFileSize($totalSize),
            'maxBackups' => $maxBackups,
            'retentionDays' => $retentionDays,
            'notificationEmail' => $notificationEmail,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Create a new backup (AJAX).
     */
    public function create(Request $request)
    {
        $request->validate([
            'components' => 'required|array|min:1',
            'components.*' => 'in:database,uploads,plugins,framework',
        ]);

        $startedAt = microtime(true);
        $components = $request->input('components', []);
        $backupPath = $this->getBackupPath();

        // Ensure backup directory exists
        if (! File::isDirectory($backupPath)) {
            try {
                File::makeDirectory($backupPath, 0755, true);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create backup directory: '.$e->getMessage(),
                ], 500);
            }
        }

        $timestamp = date('Y-m-d_His');
        $createdFiles = [];
        $errors = [];

        // Database backup
        if (in_array('database', $components)) {
            $dbConfig = config('database.connections.mysql');
            $dbHost = $dbConfig['host'] ?? '127.0.0.1';
            $dbPort = $dbConfig['port'] ?? '3306';
            $dbName = $dbConfig['database'] ?? 'archive';
            $dbUser = $dbConfig['username'] ?? 'root';
            $dbPass = $dbConfig['password'] ?? '';
            $dbSocket = $dbConfig['unix_socket'] ?? '';

            $filename = "database_{$dbName}_{$timestamp}.sql.gz";
            $filepath = $backupPath.'/'.$filename;

            // Build mysqldump command
            $cmd = 'mysqldump';
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
            $cmd .= ' --single-transaction --routines --triggers --events';
            $cmd .= ' '.escapeshellarg($dbName);
            $cmd .= ' 2>&1 | gzip > '.escapeshellarg($filepath);

            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && File::exists($filepath) && File::size($filepath) > 0) {
                $createdFiles[] = [
                    'component' => 'database',
                    'filename' => $filename,
                    'size' => $this->humanFileSize(File::size($filepath)),
                ];

                // #671 Phase 4: record the binary-log coordinates at
                // dump time so the PITR command knows where to start
                // replay from. Failure here MUST NOT break the
                // backup response - PITR will surface the gap.
                try {
                    app(BinaryLogArchiver::class)->recordDumpCoordinates(
                        $filename,
                        $filepath,
                        $dbName,
                        'Captured by BackupController::create()'
                    );
                } catch (\Throwable $e) {
                    Log::warning('[ahg-backup] failed to record dump coordinates for PITR', [
                        'filename' => $filename,
                        'error'    => $e->getMessage(),
                    ]);
                }
            } else {
                // Clean up failed file
                if (File::exists($filepath)) {
                    File::delete($filepath);
                }
                $errorMsg = ! empty($output) ? implode("\n", $output) : 'mysqldump failed with exit code '.$returnCode;
                $errors[] = "Database backup failed: {$errorMsg}";
            }
        }

        // Uploads backup
        if (in_array('uploads', $components)) {
            $uploadsPath = config('heratio.uploads_path');
            $filename = "uploads_{$timestamp}.tar.gz";
            $filepath = $backupPath.'/'.$filename;

            if (File::isDirectory($uploadsPath)) {
                $cmd = 'tar -czf '.escapeshellarg($filepath).' -C '.escapeshellarg(dirname($uploadsPath)).' '.escapeshellarg(basename($uploadsPath)).' 2>&1';
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0 && File::exists($filepath)) {
                    $createdFiles[] = [
                        'component' => 'uploads',
                        'filename' => $filename,
                        'size' => $this->humanFileSize(File::size($filepath)),
                    ];
                } else {
                    if (File::exists($filepath)) {
                        File::delete($filepath);
                    }
                    $errors[] = 'Uploads backup failed: tar returned exit code '.$returnCode;
                }
            } else {
                $errors[] = "Uploads directory not found: {$uploadsPath}";
            }
        }

        // Plugins backup
        if (in_array('plugins', $components)) {
            $pluginsPath = base_path('packages');
            $filename = "plugins_{$timestamp}.tar.gz";
            $filepath = $backupPath.'/'.$filename;

            if (File::isDirectory($pluginsPath)) {
                $cmd = 'tar -czf '.escapeshellarg($filepath).' -C '.escapeshellarg(base_path()).' packages 2>&1';
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0 && File::exists($filepath)) {
                    $createdFiles[] = [
                        'component' => 'plugins',
                        'filename' => $filename,
                        'size' => $this->humanFileSize(File::size($filepath)),
                    ];
                } else {
                    if (File::exists($filepath)) {
                        File::delete($filepath);
                    }
                    $errors[] = 'Plugins backup failed: tar returned exit code '.$returnCode;
                }
            } else {
                $errors[] = 'Packages directory not found';
            }
        }

        // Framework backup
        if (in_array('framework', $components)) {
            $filename = "framework_{$timestamp}.tar.gz";
            $filepath = $backupPath.'/'.$filename;

            $excludes = '--exclude=vendor --exclude=node_modules --exclude=storage/logs --exclude=.git --exclude=packages';
            $cmd = 'tar -czf '.escapeshellarg($filepath).' '.$excludes.' -C '.escapeshellarg(dirname(base_path())).' '.escapeshellarg(basename(base_path())).' 2>&1';
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && File::exists($filepath)) {
                $createdFiles[] = [
                    'component' => 'framework',
                    'filename' => $filename,
                    'size' => $this->humanFileSize(File::size($filepath)),
                ];
            } else {
                if (File::exists($filepath)) {
                    File::delete($filepath);
                }
                $errors[] = 'Framework backup failed: tar returned exit code '.$returnCode;
            }
        }

        // Enforce max backups limit
        $this->enforceRetention();

        // Compute summary metrics for notification payloads.
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $totalSize = 0;
        foreach ($createdFiles as $f) {
            // $createdFiles entries have a 'size' human string; recompute bytes
            // from the on-disk file for accuracy.
            $candidate = $backupPath.'/'.($f['filename'] ?? '');
            if (File::exists($candidate)) {
                $totalSize += File::size($candidate);
            }
        }
        $completedAt = now()->toIso8601String();

        // Wire up notifications (Phase 2 of #671).
        if (! empty($createdFiles) && empty($errors)) {
            $payload = [
                'id' => 'run-'.$timestamp,
                'components' => $components,
                'files' => $createdFiles,
                'size_bytes' => $totalSize,
                'size_human' => $this->humanFileSize($totalSize),
                'duration_ms' => $durationMs,
                'status' => 'success',
                'warnings' => [],
                'completed_at' => $completedAt,
            ];
            $this->notifyBackupSuccess($payload);

            return response()->json([
                'success' => true,
                'message' => 'Backup completed successfully.',
                'files' => $createdFiles,
            ]);
        } elseif (! empty($createdFiles) && ! empty($errors)) {
            $payload = [
                'id' => 'run-'.$timestamp,
                'components' => $components,
                'files' => $createdFiles,
                'size_bytes' => $totalSize,
                'size_human' => $this->humanFileSize($totalSize),
                'duration_ms' => $durationMs,
                'status' => 'success_with_warnings',
                'warnings' => $errors,
                'completed_at' => $completedAt,
            ];
            $this->notifyBackupSuccess($payload);

            return response()->json([
                'success' => true,
                'message' => 'Backup completed with warnings.',
                'files' => $createdFiles,
                'errors' => $errors,
            ]);
        } else {
            $payload = [
                'id' => 'failed-'.$timestamp,
                'components' => $components,
                'partial_files' => $createdFiles,
                'errors' => $errors,
                'duration_ms' => $durationMs,
                'status' => 'failed',
                'completed_at' => $completedAt,
            ];
            $this->notifyBackupFailure($payload);

            return response()->json([
                'success' => false,
                'message' => 'Backup failed.',
                'errors' => $errors,
            ], 500);
        }
    }

    /**
     * Send the success-path email + workbench notification.
     * Wrapped end-to-end in try/catch so notification failure never
     * breaks the backup response.
     */
    private function notifyBackupSuccess(array $payload): void
    {
        if (! AhgSettingsService::getBool('backup_notify_on_success', true)) {
            return;
        }

        $email = AhgSettingsService::get('backup_notification_email')
            ?: config('mail.from.address');
        if (! empty($email)) {
            try {
                Mail::to($email)->queue(new BackupCompletedMail($payload));
            } catch (\Throwable $e) {
                Log::warning('[ahg-backup] mail send failed (success path)', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $username = AhgSettingsService::get('backup_notify_workbench_username', 'admin');
        $title = $payload['status'] === 'success_with_warnings'
            ? 'Heratio backup completed with warnings'
            : 'Heratio backup completed';
        $message = sprintf(
            '%s components backed up (%s, %s).',
            implode('+', $payload['components'] ?? []),
            $payload['size_human'] ?? '?',
            isset($payload['duration_ms'])
                ? number_format($payload['duration_ms'] / 1000, 1).'s'
                : '?'
        );
        $eventType = $payload['status'] === 'success_with_warnings' ? 'warning' : 'success';
        $this->dispatchWorkbenchNotification($username, $title, $message, $eventType);
    }

    /**
     * Send the failure-path email + workbench notification.
     */
    private function notifyBackupFailure(array $payload): void
    {
        if (! AhgSettingsService::getBool('backup_notify_on_failure', true)) {
            return;
        }

        $email = AhgSettingsService::get('backup_notification_email')
            ?: config('mail.from.address');
        if (! empty($email)) {
            try {
                Mail::to($email)->queue(new BackupFailedMail($payload));
            } catch (\Throwable $e) {
                Log::warning('[ahg-backup] mail send failed (failure path)', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $username = AhgSettingsService::get('backup_notify_workbench_username', 'admin');
        $firstError = $payload['errors'][0] ?? 'No further detail available.';
        $title = 'Heratio backup FAILED';
        $message = sprintf(
            'Backup of %s did not complete: %s',
            implode('+', $payload['components'] ?? []),
            \Illuminate\Support\Str::limit($firstError, 240)
        );
        $this->dispatchWorkbenchNotification($username, $title, $message, 'error');
    }

    /**
     * Drop a JSON file into the Workbench notification inbox so it
     * surfaces in Johan's bell + toast + chime on ai.theahg.co.za.
     *
     * Fails silently (log + skip) if the inbox directory does not
     * exist on this host — must NOT crash the surrounding backup
     * action.
     */
    private function dispatchWorkbenchNotification(string $username, string $title, string $message, string $eventType): void
    {
        $inbox = '/var/spool/workbench/notifications';

        if (! is_dir($inbox) || ! is_writable($inbox)) {
            Log::info('[ahg-backup] workbench notification skipped (inbox unavailable)', [
                'inbox' => $inbox,
                'title' => $title,
            ]);

            return;
        }

        $payload = [
            'username' => $username,
            'title' => $title,
            'message' => $message,
            'eventType' => $eventType,
            'webLink' => url('/admin/backup'),
        ];

        $filename = $inbox.'/'.uniqid('heratio-backup-', true).'.json';
        try {
            $bytes = @file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT));
            if ($bytes === false) {
                Log::warning('[ahg-backup] workbench notification write failed', [
                    'filename' => $filename,
                    'title' => $title,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-backup] workbench notification write threw', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enforce max backups and retention days.
     */
    private function enforceRetention(): void
    {
        $backups = $this->listBackups();
        $maxBackups = AhgSettingsService::getInt('backup_max_backups', 10);
        $retentionDays = AhgSettingsService::getInt('backup_retention_days', 30);
        $cutoff = time() - ($retentionDays * 86400);

        // Remove old backups beyond retention
        foreach ($backups as $backup) {
            if ($backup['timestamp'] < $cutoff) {
                File::delete($backup['path']);
            }
        }

        // Remove excess backups beyond max count
        $backups = $this->listBackups();
        if (count($backups) > $maxBackups) {
            $toRemove = array_slice($backups, $maxBackups);
            foreach ($toRemove as $backup) {
                File::delete($backup['path']);
            }
        }
    }

    /**
     * Backup settings form.
     */
    public function settings()
    {
        $settings = [
            'backup_path' => AhgSettingsService::get('backup_path', config('heratio.backups_path')),
            'backup_max_backups' => AhgSettingsService::getInt('backup_max_backups', 10),
            'backup_retention_days' => AhgSettingsService::getInt('backup_retention_days', 30),
            'backup_notification_email' => AhgSettingsService::get('backup_notification_email', ''),
            'backup_notify_workbench_username' => AhgSettingsService::get('backup_notify_workbench_username', 'admin'),
            'backup_notify_on_success' => AhgSettingsService::getBool('backup_notify_on_success', true),
            'backup_notify_on_failure' => AhgSettingsService::getBool('backup_notify_on_failure', true),
        ];

        return view('ahg-backup::settings', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save backup settings.
     */
    /**
     * Backup dumps are the entire DB (all PII) and restore overwrites it +
     * untars over the codebase — administrators only, NOT the editor-inclusive
     * `admin`/canAdmin gate the route group uses (#1383).
     */
    private function requireAdministrator(): void
    {
        if (! AclService::isAdministrator()) {
            abort(403, 'Backup download / restore / delete is restricted to administrators.');
        }
    }

    public function saveSettings(Request $request)
    {
        $this->requireAdministrator();
        $request->validate([
            'backup_path' => 'required|string|max:500',
            'backup_max_backups' => 'required|integer|min:1|max:999',
            'backup_retention_days' => 'required|integer|min:1|max:3650',
            'backup_notification_email' => 'nullable|email|max:255',
            'backup_notify_workbench_username' => 'nullable|string|max:64',
            'backup_notify_on_success' => 'nullable|boolean',
            'backup_notify_on_failure' => 'nullable|boolean',
        ]);

        // #1383: never let backups land in a web-served directory — repointing
        // backup_path into /uploads or public/ would expose full-PII DB dumps to anon.
        $newPath = rtrim((string) $request->input('backup_path'), '/');
        foreach (array_filter([rtrim((string) config('heratio.uploads_path'), '/'), rtrim(public_path(), '/')]) as $webRoot) {
            if ($newPath === $webRoot || str_starts_with($newPath.'/', $webRoot.'/') || str_contains($newPath, '/uploads/')) {
                return back()->withInput()->with('error', 'Backup path may not be inside a web-served directory (/uploads or public/).');
            }
        }

        AhgSettingsService::set('backup_path', $request->input('backup_path'), 'backup');
        AhgSettingsService::set('backup_max_backups', $request->input('backup_max_backups'), 'backup');
        AhgSettingsService::set('backup_retention_days', $request->input('backup_retention_days'), 'backup');
        AhgSettingsService::set('backup_notification_email', $request->input('backup_notification_email', ''), 'backup');
        AhgSettingsService::set('backup_notify_workbench_username', $request->input('backup_notify_workbench_username', 'admin'), 'backup');
        AhgSettingsService::set('backup_notify_on_success', $request->boolean('backup_notify_on_success') ? '1' : '0', 'backup');
        AhgSettingsService::set('backup_notify_on_failure', $request->boolean('backup_notify_on_failure') ? '1' : '0', 'backup');

        AhgSettingsService::clearCache();

        return redirect()->route('backup.settings')->with('success', 'Backup settings saved successfully.');
    }

    /**
     * Restore page.
     */
    public function restore()
    {
        $this->requireAdministrator();
        $dbConfig = config('database.connections.mysql');
        $backupPath = $this->getBackupPath();
        $backups = $this->listBackups();
        $totalSize = array_sum(array_column($backups, 'size'));

        $maxBackups = AhgSettingsService::getInt('backup_max_backups', 10);
        $retentionDays = AhgSettingsService::getInt('backup_retention_days', 30);
        $notificationEmail = AhgSettingsService::get('backup_notification_email', '');

        $schedules = [];
        $scheduleData = AhgSettingsService::getGroup('backup_schedule');
        if (! empty($scheduleData)) {
            foreach ($scheduleData as $key => $value) {
                if (str_starts_with($key, 'backup_schedule_')) {
                    $decoded = json_decode($value, true);
                    if ($decoded) {
                        $schedules[] = (object) $decoded;
                    }
                }
            }
        }

        $dbConnected = false;
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {
        }

        return view('ahg-backup::restore', [
            'dbConfig' => $dbConfig,
            'dbConnected' => $dbConnected,
            'backupPath' => $backupPath,
            'backups' => $backups,
            'backupCount' => count($backups),
            'totalSize' => $this->humanFileSize($totalSize),
            'maxBackups' => $maxBackups,
            'retentionDays' => $retentionDays,
            'notificationEmail' => $notificationEmail,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Perform restore (AJAX).
     */
    public function doRestore(Request $request)
    {
        $this->requireAdministrator();
        $request->validate([
            'backup_id' => 'required|string',
            'components' => 'required|array|min:1',
            'components.*' => 'in:database,uploads,plugins,framework',
        ]);

        // #1383: server-side typed confirmation. A restore is irreversible and
        // overwrites live data, so it must not proceed on the client-side JS
        // confirm alone — the POST must carry the exact phrase the operator
        // typed. Absent/mismatched => 422, no restore performed.
        if ((string) $request->input('confirm_phrase') !== 'RESTORE') {
            return response()->json([
                'success' => false,
                'message' => 'Restore not confirmed. Type RESTORE in the confirmation box to proceed.',
            ], 422);
        }

        $backupId = $request->input('backup_id');
        $components = $request->input('components', []);
        $backups = $this->listBackups();

        // Find the backup by ID
        $backup = collect($backups)->firstWhere('id', $backupId);
        if (! $backup) {
            return response()->json([
                'success' => false,
                'message' => 'Backup file not found.',
            ], 404);
        }

        $restored = [];
        $errors = [];

        // Database restore
        if (in_array('database', $components) && in_array('database', $backup['components'])) {
            $filePath = $backup['path'];

            // Only restore .sql.gz files directly
            if (str_ends_with($filePath, '.sql.gz')) {
                $dbConfig = config('database.connections.mysql');
                $dbHost = $dbConfig['host'] ?? '127.0.0.1';
                $dbPort = $dbConfig['port'] ?? '3306';
                $dbName = $dbConfig['database'] ?? 'archive';
                $dbUser = $dbConfig['username'] ?? 'root';
                $dbPass = $dbConfig['password'] ?? '';
                $dbSocket = $dbConfig['unix_socket'] ?? '';

                $cmd = 'gunzip -c '.escapeshellarg($filePath).' | mysql';
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
                $cmd .= ' '.escapeshellarg($dbName).' 2>&1';

                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    $restored[] = 'database';
                } else {
                    $errorMsg = ! empty($output) ? implode("\n", $output) : 'mysql import failed with exit code '.$returnCode;
                    $errors[] = "Database restore failed: {$errorMsg}";
                }
            } else {
                $errors[] = 'Selected backup is not a database backup (.sql.gz).';
            }
        }

        // Uploads restore
        if (in_array('uploads', $components) && in_array('uploads', $backup['components'])) {
            $filePath = $backup['path'];
            if (str_contains($backup['filename'], 'uploads') && str_ends_with($filePath, '.tar.gz')) {
                $uploadsPath = config('heratio.uploads_path');
                $cmd = 'tar -xzf '.escapeshellarg($filePath).' -C '.escapeshellarg(dirname($uploadsPath)).' 2>&1';
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    $restored[] = 'uploads';
                } else {
                    $errors[] = 'Uploads restore failed: tar returned exit code '.$returnCode;
                }
            } else {
                $errors[] = 'Selected backup does not contain uploads data.';
            }
        }

        // Plugins restore
        if (in_array('plugins', $components) && in_array('plugins', $backup['components'])) {
            $filePath = $backup['path'];
            if (str_contains($backup['filename'], 'plugins') && str_ends_with($filePath, '.tar.gz')) {
                $cmd = 'tar -xzf '.escapeshellarg($filePath).' -C '.escapeshellarg(base_path()).' 2>&1';
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    $restored[] = 'plugins';
                } else {
                    $errors[] = 'Plugins restore failed: tar returned exit code '.$returnCode;
                }
            } else {
                $errors[] = 'Selected backup does not contain plugins data.';
            }
        }

        // Framework restore
        if (in_array('framework', $components) && in_array('framework', $backup['components'])) {
            $filePath = $backup['path'];
            if (str_contains($backup['filename'], 'framework') && str_ends_with($filePath, '.tar.gz')) {
                $cmd = 'tar -xzf '.escapeshellarg($filePath).' -C '.escapeshellarg(dirname(base_path())).' 2>&1';
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    $restored[] = 'framework';
                } else {
                    $errors[] = 'Framework restore failed: tar returned exit code '.$returnCode;
                }
            } else {
                $errors[] = 'Selected backup does not contain framework data.';
            }
        }

        if (! empty($restored) && empty($errors)) {
            return response()->json([
                'success' => true,
                'message' => 'Restore completed successfully.',
                'restored' => $restored,
            ]);
        } elseif (! empty($restored) && ! empty($errors)) {
            return response()->json([
                'success' => true,
                'message' => 'Restore completed with warnings.',
                'restored' => $restored,
                'errors' => $errors,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Restore failed.',
                'errors' => $errors,
            ], 500);
        }
    }

    /**
     * Download a backup file.
     */
    public function download(string $id)
    {
        $this->requireAdministrator();
        $backups = $this->listBackups();
        $backup = collect($backups)->firstWhere('id', $id);

        if (! $backup || ! File::exists($backup['path'])) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($backup['path'], $backup['filename']);
    }

    /**
     * Delete a backup file (AJAX).
     */
    public function destroy(string $id)
    {
        $this->requireAdministrator();
        $backups = $this->listBackups();
        $backup = collect($backups)->firstWhere('id', $id);

        if (! $backup || ! File::exists($backup['path'])) {
            return response()->json([
                'success' => false,
                'message' => 'Backup file not found.',
            ], 404);
        }

        try {
            File::delete($backup['path']);

            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup: '.$e->getMessage(),
            ], 500);
        }
    }
}
