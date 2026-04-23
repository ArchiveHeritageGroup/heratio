<?php

/**
 * ScanWatchCommand — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Console;

use AhgScan\Services\WatchedFolderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Polls enabled scan_folder rows, enqueues new files for ingest.
 *
 * Run continuously under supervisord:
 *   php artisan ahg:scan-watch --interval=15
 *
 * Or one-shot (ideal from cron):
 *   php artisan ahg:scan-watch --once
 */
class ScanWatchCommand extends Command
{
    protected $signature = 'ahg:scan-watch
        {--once : Run a single pass then exit}
        {--interval=30 : Seconds between passes when not --once}
        {--folder= : Process only this scan_folder code}';

    protected $description = 'Watch scan folders and enqueue new files for ingest';

    public function handle(WatchedFolderService $folders): int
    {
        $interval = max(5, (int) $this->option('interval'));
        $once = (bool) $this->option('once');
        $onlyCode = $this->option('folder');

        do {
            $list = $folders->enabledFolders();
            if ($onlyCode) {
                $list = array_values(array_filter($list, fn($f) => $f->code === $onlyCode));
            }

            foreach ($list as $folder) {
                try {
                    $count = $this->scanOne($folder);
                    if ($count > 0) {
                        $this->info("[{$folder->code}] enqueued {$count} new file(s).");
                    }
                    $folders->touchScanned($folder->id);
                } catch (\Throwable $e) {
                    $this->error("[{$folder->code}] " . $e->getMessage());
                }
            }

            if (!$once) {
                sleep($interval);
            }
        } while (!$once);

        return self::SUCCESS;
    }

    protected function scanOne(object $folder): int
    {
        if (!is_dir($folder->path)) {
            throw new \RuntimeException("Watched path is not a directory: {$folder->path}");
        }

        $minQuiet = max(1, (int) $folder->min_quiet_seconds);
        $now = time();
        $enqueued = 0;

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder->path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($rii as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) {
                continue;
            }

            $full = $fileInfo->getPathname();

            // Skip dotfiles, lock files, the folder's own disposition subdirs
            $relative = substr($full, strlen(rtrim($folder->path, '/')) + 1);
            if (preg_match('#(^|/)\.#', $relative)) {
                continue;
            }
            if (str_ends_with($full, '.lock') || str_ends_with($full, '.part') || str_ends_with($full, '.tmp')) {
                continue;
            }

            // Quiet-period check: file must be idle for min_quiet_seconds
            if (($now - $fileInfo->getMTime()) < $minQuiet) {
                continue;
            }

            // Dedupe: skip if already staged for this session
            $hash = @hash_file('sha256', $full);
            if (!$hash) {
                continue;
            }
            $already = DB::table('ingest_file')
                ->where('session_id', $folder->ingest_session_id)
                ->where('source_hash', $hash)
                ->exists();
            if ($already) {
                continue;
            }

            $fileId = DB::table('ingest_file')->insertGetId([
                'session_id' => $folder->ingest_session_id,
                'file_type' => $this->classifyFile($full),
                'original_name' => $fileInfo->getFilename(),
                'stored_path' => $full,
                'file_size' => $fileInfo->getSize(),
                'mime_type' => function_exists('mime_content_type') ? (@mime_content_type($full) ?: null) : null,
                'status' => 'pending',
                'stage' => null,
                'source_hash' => $hash,
                'attempts' => 0,
                'created_at' => now(),
            ]);

            \AhgScan\Jobs\ProcessScanFile::dispatch($fileId, $folder->id);
            $enqueued++;
        }

        return $enqueued;
    }

    protected function classifyFile(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'xml' => 'sidecar',
            'csv', 'tsv' => 'csv',
            default => 'digital_object',
        };
    }
}
