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
                $jobId = null;
                try {
                    $count = $this->scanOne($folder, $jobId);
                    if ($count > 0) {
                        $this->info("[{$folder->code}] enqueued {$count} new file(s).");
                    }
                    $folders->touchScanned($folder->id);
                    if ($jobId) {
                        $this->closeJob($folder, (int) $jobId);
                    }
                } catch (\Throwable $e) {
                    $this->error("[{$folder->code}] " . $e->getMessage());
                    if ($jobId) {
                        DB::table('ingest_job')->where('id', $jobId)->update([
                            'status' => 'failed',
                            'error_log' => json_encode(['message' => $e->getMessage()]),
                            'completed_at' => now(),
                        ]);
                    }
                }
            }

            if (!$once) {
                sleep($interval);
            }
        } while (!$once);

        return self::SUCCESS;
    }

    protected function scanOne(object $folder, ?int &$jobId = null): int
    {
        if (!is_dir($folder->path)) {
            throw new \RuntimeException("Watched path is not a directory: {$folder->path}");
        }

        $minQuiet = max(1, (int) $folder->min_quiet_seconds);
        $now = time();
        $enqueued = 0;
        $jobId = null;

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

            // Lazily open an ingest_job for this pass on the first enqueued file.
            if ($jobId === null) {
                $jobId = DB::table('ingest_job')->insertGetId([
                    'session_id' => $folder->ingest_session_id,
                    'status' => 'running',
                    'started_at' => now(),
                    'created_at' => now(),
                ]);
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

    /**
     * Close an ingest_job opened for this watcher pass, writing summary
     * counts from the ingest_file rows the pass produced.
     */
    protected function closeJob(object $folder, int $jobId): void
    {
        $job = DB::table('ingest_job')->where('id', $jobId)->first();
        if (!$job) { return; }

        $files = DB::table('ingest_file')
            ->where('session_id', $folder->ingest_session_id)
            ->where('created_at', '>=', $job->started_at)
            ->get();

        $total = $files->count();
        $processed = $files->whereIn('status', ['done', 'duplicate'])->count();
        $createdDos = $files->whereNotNull('resolved_do_id')->count();
        $createdIos = $files->whereNotNull('resolved_io_id')->unique('resolved_io_id')->count();
        $errors = $files->where('status', 'failed')->count();

        DB::table('ingest_job')->where('id', $jobId)->update([
            'status' => $errors > 0 ? 'completed_with_errors' : 'completed',
            'total_rows' => $total,
            'processed_rows' => $processed,
            'created_records' => $createdIos,
            'created_dos' => $createdDos,
            'error_count' => $errors,
            'completed_at' => now(),
        ]);
    }
}
