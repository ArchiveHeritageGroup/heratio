<?php

/**
 * ScanProcessCommand — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Console;

use AhgScan\Jobs\ProcessScanFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run the processing pipeline synchronously for a single file or all
 * pending files in a folder. Useful for sites without a queue worker,
 * for testing, and for the admin "Retry" action.
 */
class ScanProcessCommand extends Command
{
    protected $signature = 'ahg:scan-process
        {--file= : ingest_file.id to process}
        {--folder= : scan_folder.code — process all pending rows in its session}
        {--limit=50 : Max files per run}';

    protected $description = 'Process pending scan files through the ingest pipeline (synchronous)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        if ($fileId = $this->option('file')) {
            return $this->processOne((int) $fileId);
        }

        if ($folderCode = $this->option('folder')) {
            $folder = DB::table('scan_folder')->where('code', $folderCode)->first();
            if (!$folder) {
                $this->error("No scan_folder with code '{$folderCode}'.");
                return self::FAILURE;
            }
            $ids = DB::table('ingest_file')
                ->where('session_id', $folder->ingest_session_id)
                ->whereIn('status', ['pending', 'failed'])
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id');
        } else {
            $ids = DB::table('ingest_file as f')
                ->join('ingest_session as s', 'f.session_id', '=', 's.id')
                ->where('s.session_kind', '!=', 'wizard')
                ->whereIn('f.status', ['pending', 'failed'])
                ->orderBy('f.id')
                ->limit($limit)
                ->pluck('f.id');
        }

        if ($ids->isEmpty()) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            $this->processOne((int) $id);
        }

        return self::SUCCESS;
    }

    protected function processOne(int $fileId): int
    {
        $this->line("Processing ingest_file #{$fileId}...");
        try {
            ProcessScanFile::runSync($fileId);
            $row = DB::table('ingest_file')->where('id', $fileId)->first();
            $this->info("  → status={$row->status}" . ($row->resolved_io_id ? ", io={$row->resolved_io_id}, do={$row->resolved_do_id}" : ''));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("  ✗ " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
