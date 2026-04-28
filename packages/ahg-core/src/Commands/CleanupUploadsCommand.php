<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class CleanupUploadsCommand extends Command
{
    protected $signature = 'ahg:cleanup-uploads
        {--days=7 : Remove temp files older than N days}
        {--dry-run : Report what would be removed without unlinking}';

    protected $description = 'Remove temp upload files';

    public function handle(): int
    {
        $tmpDir = rtrim((string) config('heratio.uploads_path', base_path('uploads')), '/') . '/tmp';
        if (! is_dir($tmpDir)) {
            $this->info("[cleanup-uploads] {$tmpDir} does not exist — nothing to clean.");
            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $cutoff = time() - ($days * 86400);
        $dry = (bool) $this->option('dry-run');

        $removed = 0; $bytes = 0; $checked = 0;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $entry) {
            $checked++;
            if (! $entry->isFile()) continue;
            if ($entry->getMTime() >= $cutoff) continue;
            $size = $entry->getSize();
            if ($dry) {
                $this->line("  would remove: {$entry->getPathname()} (" . number_format($size) . " bytes)");
            } else {
                @unlink($entry->getPathname());
            }
            $removed++; $bytes += $size;
        }

        // Best-effort: prune now-empty subdirectories.
        if (! $dry) {
            $iter2 = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iter2 as $entry) {
                if ($entry->isDir() && ! (new \FilesystemIterator($entry->getPathname()))->valid()) {
                    @rmdir($entry->getPathname());
                }
            }
        }

        $this->info("[cleanup-uploads] dir={$tmpDir} cutoff_days={$days} checked={$checked} removed={$removed} bytes_freed=" . number_format($bytes) . ($dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
