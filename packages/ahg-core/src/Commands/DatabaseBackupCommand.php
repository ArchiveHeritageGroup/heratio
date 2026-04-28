<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'ahg:backup
        {--database= : Override DB name (default: config(database.connections.{default}.database))}
        {--gzip : gzip the dump (recommended)}
        {--dry-run : Print plan without executing}';

    protected $description = 'Dump the heratio MySQL database to the configured backups_path';

    public function handle(): int
    {
        $database = $this->option('database') ?: config('database.connections.' . config('database.default') . '.database');
        $backupsDir = rtrim((string) config('heratio.backups_path', base_path('backups')), '/');
        if (! is_dir($backupsDir)) {
            if (! @mkdir($backupsDir, 0775, true)) {
                $this->error("cannot create {$backupsDir}");
                return self::FAILURE;
            }
        }

        $stamp = date('Y-m-d_His');
        $gzip = (bool) $this->option('gzip');
        $ext = $gzip ? '.sql.gz' : '.sql';
        $file = "{$backupsDir}/heratio-{$database}-{$stamp}{$ext}";

        // mysqldump with --single-transaction for InnoDB consistency without long locks.
        // Credentials come from MySQL socket auth (no password on CLI).
        $cmd = sprintf('mysqldump --single-transaction --quick --triggers --routines --events %s', escapeshellarg((string) $database));
        if ($gzip) $cmd .= ' | gzip --best';
        $cmd .= ' > ' . escapeshellarg($file) . ' 2>/tmp/heratio-backup.err';

        if ($this->option('dry-run')) {
            $this->info("would run: {$cmd}");
            return self::SUCCESS;
        }

        $t0 = microtime(true);
        $rc = 0; system($cmd, $rc);
        $elapsed = round(microtime(true) - $t0, 1);

        if ($rc !== 0) {
            $err = is_readable('/tmp/heratio-backup.err') ? trim((string) file_get_contents('/tmp/heratio-backup.err')) : '';
            $this->error("mysqldump exited {$rc}: {$err}");
            return self::FAILURE;
        }
        $size = is_file($file) ? filesize($file) : 0;
        $this->info(sprintf('ok %s (%s bytes, %.1f s)', $file, number_format($size), $elapsed));
        return self::SUCCESS;
    }
}
