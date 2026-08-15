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
        $database = $this->option('database') ?: config('database.connections.'.config('database.default').'.database');
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
        //
        // Credentials come from the application's own DB config, NOT from socket
        // auth. The previous version passed no credentials at all, which works
        // when an operator runs this as root - root has socket auth - but the
        // SCHEDULER runs it as www-data, which holds no MySQL grant. So every
        // scheduled backup died with
        // "Access denied for user 'www-data'@'localhost' (using password: NO)"
        // and no scheduled backup had ever been written.
        //
        // The password goes through MYSQL_PWD rather than --password so it is not
        // exposed in the process list to every user on the box.
        $db = config('database.connections.'.config('database.default'), []);
        $cmd = 'mysqldump';
        if (! empty($db['unix_socket'])) {
            $cmd .= ' --socket='.escapeshellarg((string) $db['unix_socket']);
        } else {
            $cmd .= ' --host='.escapeshellarg((string) ($db['host'] ?? '127.0.0.1'));
            $cmd .= ' --port='.escapeshellarg((string) ($db['port'] ?? 3306));
        }
        if (! empty($db['username'])) {
            $cmd .= ' --user='.escapeshellarg((string) $db['username']);
        }
        $cmd .= sprintf(' --single-transaction --quick --triggers --routines --events %s', escapeshellarg((string) $database));

        $envPrefix = ! empty($db['password'])
            ? 'MYSQL_PWD='.escapeshellarg((string) $db['password']).' '
            : '';
        if ($gzip) {
            $cmd .= ' | gzip --best';
        }
        $cmd .= ' > '.escapeshellarg($file).' 2>/tmp/heratio-backup.err';
        $cmd = $envPrefix.$cmd;

        if ($this->option('dry-run')) {
            // NEVER print the credential. --dry-run is the one path a human reads
            // and copies into a ticket or a chat window, and MYSQL_PWD carries the
            // live database password.
            $shown = $envPrefix !== ''
                ? preg_replace("/^MYSQL_PWD='.*?' /", "MYSQL_PWD='[redacted]' ", $cmd)
                : $cmd;
            $this->info("would run: {$shown}");

            return self::SUCCESS;
        }

        $t0 = microtime(true);
        $rc = 0;
        system($cmd, $rc);
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
