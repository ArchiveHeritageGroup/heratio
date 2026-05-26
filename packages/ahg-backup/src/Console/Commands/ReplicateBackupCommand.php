<?php

/**
 * ReplicateBackupCommand - push local backups to the off-site driver
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

use AhgBackup\Services\OffsiteReplicator;
use AhgCore\Services\AhgSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Daily-cron candidate. Lists local backup files, skips anything
 * already recorded as replicated for the active driver, and pushes the
 * rest. Returns non-zero exit if any push failed.
 *
 *   php artisan backup:replicate
 *   php artisan backup:replicate --driver=s3
 *   php artisan backup:replicate --force                 # ignore ledger, re-push everything
 *   php artisan backup:replicate --no-encryption         # one-off skip of GPG even if configured
 *
 * Issue #671 Phase 3.
 */
class ReplicateBackupCommand extends Command
{
    protected $signature = 'backup:replicate
                            {--driver= : Override the configured off-site driver (s3|rsync|localfs)}
                            {--force : Re-push files even if the ledger says they are already replicated}
                            {--no-encryption : Push as-is even if backup_encryption_passphrase is set}';

    protected $description = 'Replicate local backup archives off-site via the configured driver (#671 Phase 3).';

    public function handle(OffsiteReplicator $replicator): int
    {
        $driverName = (string) ($this->option('driver') ?: config('backup.offsite.driver', 'localfs'));

        try {
            $driver = $replicator->driver($driverName);
        } catch (\Throwable $e) {
            $this->error('Failed to initialise driver ['.$driverName.']: '.$e->getMessage());
            return self::FAILURE;
        }

        if ($driverName === 'localfs') {
            $this->warn('Using localfs off-site driver - this is TEST ONLY and provides no DR protection.');
        }

        $passphrase = $replicator->encryptionPassphrase();
        $encryptionWanted = $passphrase !== null && !$this->option('no-encryption');
        if (!$encryptionWanted && $passphrase === null) {
            $this->warn(
                'No backup_encryption_passphrase set - off-site copies will be UNENCRYPTED. '.
                'Set ahg_setting.backup_encryption_passphrase (group=backup) to enable AES256 GPG.'
            );
        }

        $backupPath = AhgSettingsService::get('backup_path', config('heratio.backups_path'));
        if (!is_dir($backupPath)) {
            $this->error("Local backup directory not found: {$backupPath}");
            return self::FAILURE;
        }

        $files = collect(File::files($backupPath))
            ->filter(fn ($f) => preg_match('/\.(gz|tar\.gz|sql\.gz|zip)$/i', $f->getFilename()))
            ->sortBy(fn ($f) => $f->getMTime())
            ->values();

        if ($files->isEmpty()) {
            $this->info('No local backup files to replicate.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $pushed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $file) {
            $local = $file->getPathname();

            if (!$force && $this->alreadyReplicated($local, $driverName)) {
                $this->line(" - SKIP {$local} (already replicated, driver={$driverName})");
                $skipped++;
                continue;
            }

            $this->line(" - PUSH {$local} -> driver={$driverName}");

            $payload = $local;
            $tempEncrypted = null;
            try {
                if ($encryptionWanted) {
                    $tempEncrypted = $replicator->encryptIfConfigured($local);
                    if ($tempEncrypted !== null) {
                        $payload = $tempEncrypted;
                    }
                }
                $result = $driver->push($payload);
                $this->upsertLedger($local, $driverName, $result, $tempEncrypted !== null, null);
                $this->info("   OK  {$result['remote_path']} ({$result['size_bytes']} bytes, sha256={$result['sha256']})");
                $pushed++;
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $this->error('   FAIL '.$msg);
                Log::error('[ahg-backup] replicate push failed', [
                    'local'   => $local,
                    'driver'  => $driverName,
                    'error'   => $msg,
                ]);
                $this->upsertLedger($local, $driverName, [
                    'remote_path' => '',
                    'size_bytes'  => (int) @filesize($local),
                    'sha256'      => (string) @hash_file('sha256', $local),
                ], $tempEncrypted !== null, $msg);
                $failed++;
            } finally {
                if ($tempEncrypted !== null && is_file($tempEncrypted)) {
                    @unlink($tempEncrypted);
                }
            }
        }

        $this->line('');
        $this->line("Pushed: {$pushed}, Skipped: {$skipped}, Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function alreadyReplicated(string $localPath, string $driver): bool
    {
        try {
            $row = DB::table('ahg_backup_replication')
                ->where('local_path', $localPath)
                ->where('driver', $driver)
                ->whereIn('status', ['replicated', 'verified'])
                ->first();
            return $row !== null;
        } catch (\Throwable $e) {
            // Table missing during early boot - treat as not replicated
            // so the push attempt itself surfaces the bootstrap gap.
            return false;
        }
    }

    /**
     * @param array{remote_path: string, size_bytes: int, sha256: string} $result
     */
    private function upsertLedger(string $local, string $driver, array $result, bool $encrypted, ?string $error): void
    {
        try {
            $existing = DB::table('ahg_backup_replication')
                ->where('local_path', $local)
                ->where('driver', $driver)
                ->first();

            $row = [
                'local_path'    => $local,
                'remote_path'   => $result['remote_path'],
                'driver'        => $driver,
                'size_bytes'    => $result['size_bytes'],
                'sha256'        => $result['sha256'] ?: null,
                'encrypted'     => $encrypted ? 1 : 0,
                'replicated_at' => date('Y-m-d H:i:s'),
                'status'        => $error === null ? 'replicated' : 'failed',
                'error'         => $error,
            ];
            if ($existing) {
                DB::table('ahg_backup_replication')->where('id', $existing->id)->update($row);
            } else {
                DB::table('ahg_backup_replication')->insert($row);
            }
        } catch (\Throwable $e) {
            Log::error('[ahg-backup] failed to write replication ledger', [
                'local'  => $local,
                'driver' => $driver,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
