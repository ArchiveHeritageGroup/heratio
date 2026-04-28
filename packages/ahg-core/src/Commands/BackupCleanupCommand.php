<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupCleanupCommand extends Command
{
    protected $signature = 'ahg:backup-cleanup
        {--days= : Override retention window (default reads ahg_settings.backup_retention_days, fallback 30)}
        {--dry-run : Show what would be removed without deleting}';

    protected $description = 'Remove old backups past retention';

    public function handle(): int
    {
        $backupsDir = rtrim((string) config('heratio.backups_path', base_path('backups')), '/');
        if (! is_dir($backupsDir)) {
            $this->info("[backup-cleanup] {$backupsDir} does not exist — nothing to clean.");
            return self::SUCCESS;
        }

        $days = $this->option('days');
        if ($days === null && Schema::hasTable('ahg_settings')) {
            $days = DB::table('ahg_settings')->where('setting_key', 'backup_retention_days')->value('setting_value');
        }
        $days = max(1, (int) ($days ?: 30));
        $cutoff = time() - ($days * 86400);
        $dry = (bool) $this->option('dry-run');

        $removed = 0; $bytes = 0; $kept = 0;
        foreach (new \DirectoryIterator($backupsDir) as $entry) {
            if ($entry->isDot() || ! $entry->isFile()) continue;
            // Only sweep recognisable backup artefacts; never touch unknown files.
            $name = $entry->getFilename();
            if (! preg_match('/\.(sql|sql\.gz|tar|tar\.gz|zip|dump|bak)$/i', $name)) {
                continue;
            }
            if ($entry->getMTime() >= $cutoff) {
                $kept++;
                continue;
            }
            $size = $entry->getSize();
            if ($dry) {
                $this->line("  would remove: {$entry->getPathname()} (" . number_format($size) . " bytes, " . date('Y-m-d', $entry->getMTime()) . ")");
            } else {
                @unlink($entry->getPathname());
            }
            $removed++; $bytes += $size;
        }

        $this->info("[backup-cleanup] dir={$backupsDir} keep_days={$days} kept={$kept} removed={$removed} bytes_freed=" . number_format($bytes) . ($dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
