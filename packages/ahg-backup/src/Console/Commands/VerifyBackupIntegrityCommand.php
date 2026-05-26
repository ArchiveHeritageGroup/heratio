<?php

/**
 * VerifyBackupIntegrityCommand - re-verify off-site backups via SHA-256
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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Walk the `ahg_backup_replication` ledger and re-verify each remote
 * object against its recorded SHA-256.
 *
 *   php artisan backup:verify-integrity                # rows where status != 'verified'
 *   php artisan backup:verify-integrity --all          # also re-check 'verified' rows
 *   php artisan backup:verify-integrity --from=2026-05-01
 *
 * Returns non-zero exit if any row fails verification.
 *
 * Issue #671 Phase 3.
 */
class VerifyBackupIntegrityCommand extends Command
{
    protected $signature = 'backup:verify-integrity
                            {--all : Re-verify even rows already marked as verified}
                            {--from= : Only verify rows replicated on or after this date (YYYY-MM-DD)}
                            {--driver= : Restrict to one driver name}';

    protected $description = 'Verify off-site backup integrity via remote SHA-256 (#671 Phase 3).';

    public function handle(OffsiteReplicator $replicator): int
    {
        try {
            $q = DB::table('ahg_backup_replication')
                ->whereNotNull('sha256')
                ->where('sha256', '!=', '');
        } catch (\Throwable $e) {
            $this->error('ahg_backup_replication table not found: '.$e->getMessage());
            return self::FAILURE;
        }

        if (!$this->option('all')) {
            $q->where('status', '!=', 'verified');
        }
        if ($from = $this->option('from')) {
            $q->where('replicated_at', '>=', $from.' 00:00:00');
        }
        if ($driver = $this->option('driver')) {
            $q->where('driver', $driver);
        }

        $rows = $q->orderBy('replicated_at', 'asc')->get();
        if ($rows->isEmpty()) {
            $this->info('No replication rows to verify.');
            return self::SUCCESS;
        }

        $passed = 0;
        $failed = 0;
        $driverCache = [];

        foreach ($rows as $row) {
            $driverName = (string) $row->driver;
            if (!isset($driverCache[$driverName])) {
                try {
                    $driverCache[$driverName] = $replicator->driver($driverName);
                } catch (\Throwable $e) {
                    $this->error("driver '{$driverName}' could not be initialised: ".$e->getMessage());
                    $driverCache[$driverName] = null;
                }
            }
            $driver = $driverCache[$driverName];
            if ($driver === null) {
                $failed++;
                continue;
            }

            $this->line(" - VERIFY [{$driverName}] {$row->remote_path}");

            $ok = false;
            $err = null;
            try {
                $ok = $driver->verify((string) $row->remote_path, (string) $row->sha256);
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }

            if ($ok) {
                DB::table('ahg_backup_replication')->where('id', $row->id)->update([
                    'status'      => 'verified',
                    'verified_at' => date('Y-m-d H:i:s'),
                    'error'       => null,
                ]);
                $this->info('   OK  sha256 match');
                $passed++;
            } else {
                $msg = $err ?: 'SHA-256 mismatch or remote object missing';
                DB::table('ahg_backup_replication')->where('id', $row->id)->update([
                    'status' => 'failed',
                    'error'  => $msg,
                ]);
                Log::warning('[ahg-backup] integrity verification failed', [
                    'id'          => $row->id,
                    'driver'      => $driverName,
                    'remote_path' => $row->remote_path,
                    'error'       => $msg,
                ]);
                $this->error('   FAIL '.$msg);
                $failed++;
            }
        }

        $this->line('');
        $this->line("Verified: {$passed}, Failed: {$failed}");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
