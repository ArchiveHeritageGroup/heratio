<?php

/**
 * BackupController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use AhgCore\Services\AhgSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Get the configured backup path.
     */
    private function getBackupPath(): string
    {
        return AhgSettingsService::get('backup_path', config('backup.path', '/mnt/nas/heratio/backups'));
    }

    /**
     * List existing backup files in the backup directory.
     */
    private function listBackups(): array
    {
        $path = $this->getBackupPath();

        if (!File::isDirectory($path)) {
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
        if (!empty($scheduleData)) {
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

        $components = $request->input('components', []);
        $backupPath = $this->getBackupPath();

        // Ensure backup directory exists
        if (!File::isDirectory($backupPath)) {
            try {
                File::makeDirectory($backupPath, 0755, true);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create backup directory: ' . $e->getMessage(),
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
            $filepath = $backupPath . '/' . $filename;

            // Build mysqldump command
            $cmd = 'mysqldump';
            if ($dbSocket) {
                $cmd .= ' --socket=' . escapeshellarg($dbSocket);
            } else {
                $cmd .= ' --host=' . escapeshellarg($dbHost);
                $cmd .= ' --port=' . escapeshellarg($dbPort);
            }
            $cmd .= ' --user=' . escapeshellarg($dbUser);
            if ($dbPass) {
                $cmd .= ' --password=' . escapeshellarg($dbPass);
            }
            $cmd .= ' --single-transaction --routines --triggers --events';
            $cmd .= ' ' . escapeshellarg($dbName);
            $cmd .= ' 2>&1 | gzip > ' . escapeshellarg($filepath);

            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && File::exists($filepath) && File::size($filepath) > 0) {
                $createdFiles[] = [
                    'component' => 'database',
                    'filename' => $filename,
                    'size' => $this->humanFileSize(File::size($filepath)),
                ];
            } else {
                // Clean up failed file
                if (File::exists($filepath)) {
                    File::delete($filepath);
                }
                $errorMsg = !empty($output) ? implode("\n", $output) : 'mysqldump failed with exit code ' . $returnCode;
                $errors[] = "Database backup failed: {$errorMsg}";
            }
        }

        // Uploads backup
        if (in_array('uploads', $components)) {
            $uploadsPath = config('app.uploads_path', '/mnt/nas/heratio/archive');
            $filename = "uploads_{$timestamp}.tar.gz";
            $filepath = $backupPath . '/' . $filename;

            if (File::isDirectory($uploadsPath)) {
                $cmd = 'tar -czf ' . escapeshellarg($filepath) . ' -C ' . escapeshellarg(dirname($uploadsPath)) . ' ' . escapeshellarg(basename($uploadsPath)) . ' 2>&1';
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
                    $errors[] = 'Uploads backup failed: tar returned exit code ' . $returnCode;
                }
            } else {
                $errors[] = "Uploads directory not found: {$uploadsPath}";
            }
        }

        // Plugins backup
        if (in_array('plugins', $components)) {
            $pluginsPath = base_path('packages');
            $filename = "plugins_{$timestamp}.tar.gz";
            $filepath = $backupPath . '/' . $filename;

            if (File::isDirectory($pluginsPath)) {
                $cmd = 'tar -czf ' . escapeshellarg($filepath) . ' -C ' . escapeshellarg(base_path()) . ' packages 2>&1';
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
                    $errors[] = 'Plugins backup failed: tar returned exit code ' . $returnCode;
                }
            } else {
                $errors[] = 'Packages directory not found';
            }
        }

        // Framework backup
        if (in_array('framework', $components)) {
            $filename = "framework_{$timestamp}.tar.gz";
            $filepath = $backupPath . '/' . $filename;

            $excludes = '--exclude=vendor --exclude=node_modules --exclude=storage/logs --exclude=.git --exclude=packages';
            $cmd = 'tar -czf ' . escapeshellarg($filepath) . ' ' . $excludes . ' -C ' . escapeshellarg(dirname(base_path())) . ' ' . escapeshellarg(basename(base_path())) . ' 2>&1';
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
                $errors[] = 'Framework backup failed: tar returned exit code ' . $returnCode;
            }
        }

        // Enforce max backups limit
        $this->enforceRetention();

        // Send notification if configured
        $email = AhgSettingsService::get('backup_notification_email');
        if ($email && !empty($createdFiles)) {
            // Notification would be sent here in production
        }

        if (!empty($createdFiles) && empty($errors)) {
            return response()->json([
                'success' => true,
                'message' => 'Backup completed successfully.',
                'files' => $createdFiles,
            ]);
        } elseif (!empty($createdFiles) && !empty($errors)) {
            return response()->json([
                'success' => true,
                'message' => 'Backup completed with warnings.',
                'files' => $createdFiles,
                'errors' => $errors,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Backup failed.',
                'errors' => $errors,
            ], 500);
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
            'backup_path' => AhgSettingsService::get('backup_path', '/mnt/nas/heratio/backups'),
            'backup_max_backups' => AhgSettingsService::getInt('backup_max_backups', 10),
            'backup_retention_days' => AhgSettingsService::getInt('backup_retention_days', 30),
            'backup_notification_email' => AhgSettingsService::get('backup_notification_email', ''),
        ];

        return view('ahg-backup::settings', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save backup settings.
     */
    public function saveSettings(Request $request)
    {
        $request->validate([
            'backup_path' => 'required|string|max:500',
            'backup_max_backups' => 'required|integer|min:1|max:999',
            'backup_retention_days' => 'required|integer|min:1|max:3650',
            'backup_notification_email' => 'nullable|email|max:255',
        ]);

        AhgSettingsService::set('backup_path', $request->input('backup_path'), 'backup');
        AhgSettingsService::set('backup_max_backups', $request->input('backup_max_backups'), 'backup');
        AhgSettingsService::set('backup_retention_days', $request->input('backup_retention_days'), 'backup');
        AhgSettingsService::set('backup_notification_email', $request->input('backup_notification_email', ''), 'backup');

        AhgSettingsService::clearCache();

        return redirect()->route('backup.settings')->with('success', 'Backup settings saved successfully.');
    }

    /**
     * Restore page.
     */
    public function restore()
    {
        $backups = $this->listBackups();

        return view('ahg-backup::restore', [
            'backups' => $backups,
        ]);
    }

    /**
     * Perform restore (AJAX).
     */
    public function doRestore(Request $request)
    {
        $request->validate([
            'backup_id' => 'required|string',
            'components' => 'required|array|min:1',
            'components.*' => 'in:database,uploads,plugins,framework',
        ]);

        $backupId = $request->input('backup_id');
        $components = $request->input('components', []);
        $backups = $this->listBackups();

        // Find the backup by ID
        $backup = collect($backups)->firstWhere('id', $backupId);
        if (!$backup) {
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

                $cmd = 'gunzip -c ' . escapeshellarg($filePath) . ' | mysql';
                if ($dbSocket) {
                    $cmd .= ' --socket=' . escapeshellarg($dbSocket);
                } else {
                    $cmd .= ' --host=' . escapeshellarg($dbHost);
                    $cmd .= ' --port=' . escapeshellarg($dbPort);
                }
                $cmd .= ' --user=' . escapeshellarg($dbUser);
                if ($dbPass) {
                    $cmd .= ' --password=' . escapeshellarg($dbPass);
                }
                $cmd .= ' ' . escapeshellarg($dbName) . ' 2>&1';

                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    $restored[] = 'database';
                } else {
                    $errorMsg = !empty($output) ? implode("\n", $output) : 'mysql import failed with exit code ' . $returnCode;
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
                $uploadsPath = config('app.uploads_path', '/mnt/nas/heratio/archive');
                $cmd = 'tar -xzf ' . escapeshellarg($filePath) . ' -C ' . escapeshellarg(dirname($uploadsPath)) . ' 2>&1';
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    $restored[] = 'uploads';
                } else {
                    $errors[] = 'Uploads restore failed: tar returned exit code ' . $returnCode;
                }
            } else {
                $errors[] = 'Selected backup does not contain uploads data.';
            }
        }

        // Plugins restore
        if (in_array('plugins', $components) && in_array('plugins', $backup['components'])) {
            $filePath = $backup['path'];
            if (str_contains($backup['filename'], 'plugins') && str_ends_with($filePath, '.tar.gz')) {
                $cmd = 'tar -xzf ' . escapeshellarg($filePath) . ' -C ' . escapeshellarg(base_path()) . ' 2>&1';
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    $restored[] = 'plugins';
                } else {
                    $errors[] = 'Plugins restore failed: tar returned exit code ' . $returnCode;
                }
            } else {
                $errors[] = 'Selected backup does not contain plugins data.';
            }
        }

        // Framework restore
        if (in_array('framework', $components) && in_array('framework', $backup['components'])) {
            $filePath = $backup['path'];
            if (str_contains($backup['filename'], 'framework') && str_ends_with($filePath, '.tar.gz')) {
                $cmd = 'tar -xzf ' . escapeshellarg($filePath) . ' -C ' . escapeshellarg(dirname(base_path())) . ' 2>&1';
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    $restored[] = 'framework';
                } else {
                    $errors[] = 'Framework restore failed: tar returned exit code ' . $returnCode;
                }
            } else {
                $errors[] = 'Selected backup does not contain framework data.';
            }
        }

        if (!empty($restored) && empty($errors)) {
            return response()->json([
                'success' => true,
                'message' => 'Restore completed successfully.',
                'restored' => $restored,
            ]);
        } elseif (!empty($restored) && !empty($errors)) {
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
        $backups = $this->listBackups();
        $backup = collect($backups)->firstWhere('id', $id);

        if (!$backup || !File::exists($backup['path'])) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($backup['path'], $backup['filename']);
    }

    /**
     * Delete a backup file (AJAX).
     */
    public function destroy(string $id)
    {
        $backups = $this->listBackups();
        $backup = collect($backups)->firstWhere('id', $id);

        if (!$backup || !File::exists($backup['path'])) {
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
                'message' => 'Failed to delete backup: ' . $e->getMessage(),
            ], 500);
        }
    }
}
