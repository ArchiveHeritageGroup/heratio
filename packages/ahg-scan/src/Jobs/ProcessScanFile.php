<?php

/**
 * ProcessScanFile — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Jobs;

use AhgIngest\Services\IngestService;
use AhgScan\Services\PathLayoutResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Advance one ingest_file row through the pipeline:
 *   pending → virus → meta → io → do → indexing → done
 * Each stage checkpoints on ingest_file.stage. Runs synchronously via
 * runSync() for retry/test flows.
 */
class ProcessScanFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $fileId;
    public ?int $folderId;
    public int $tries = 3;

    public function __construct(int $fileId, ?int $folderId = null)
    {
        $this->fileId = $fileId;
        $this->folderId = $folderId;
    }

    public function handle(): void
    {
        self::runSync($this->fileId, $this->folderId);
    }

    /**
     * Synchronous entry point — usable from the queue, from the CLI, and
     * from the admin "Retry" action.
     */
    public static function runSync(int $fileId, ?int $folderId = null): void
    {
        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        if (!$file) {
            return;
        }
        if (!in_array($file->status, ['pending', 'failed', 'processing'])) {
            return;
        }

        if ($folderId === null) {
            $folderId = (int) DB::table('scan_folder')
                ->where('ingest_session_id', $file->session_id)
                ->value('id');
        }
        $folder = $folderId ? DB::table('scan_folder')->where('id', $folderId)->first() : null;

        // Mark processing; increment attempts.
        DB::table('ingest_file')->where('id', $fileId)->update([
            'status' => 'processing',
            'stage' => 'virus',
            'attempts' => $file->attempts + 1,
            'error_message' => null,
        ]);

        try {
            self::stageVirus($fileId);
            self::stageMeta($fileId);
            $resolved = self::stageResolveDestination($fileId, $folder);
            self::stageIoAndDo($fileId, $resolved);
            self::stageIndexing($fileId);

            DB::table('ingest_file')->where('id', $fileId)->update([
                'status' => 'done',
                'stage' => null,
                'completed_at' => now(),
            ]);

            self::dispositionSuccess($fileId, $folder);
        } catch (DuplicateFileException $e) {
            DB::table('ingest_file')->where('id', $fileId)->update([
                'status' => 'duplicate',
                'stage' => null,
                'error_message' => $e->getMessage(),
                'resolved_io_id' => $e->ioId,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] process failed for ingest_file ' . $fileId . ': ' . $e->getMessage());
            DB::table('ingest_file')->where('id', $fileId)->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 5000),
            ]);
            self::dispositionFailure($fileId, $folder, $e->getMessage());
            throw $e;
        }
    }

    // ---------------------------------------------------------------------
    // Stages
    // ---------------------------------------------------------------------

    protected static function stageVirus(int $fileId): void
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'virus']);
        // Full ClamAV integration is P4. For now: record the attempt in
        // preservation_virus_scan if the table exists; otherwise pass.
        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        if (\Illuminate\Support\Facades\Schema::hasTable('preservation_virus_scan')) {
            try {
                DB::table('preservation_virus_scan')->insert([
                    'file_path' => $file->stored_path,
                    'scan_result' => 'not_scanned',
                    'scanned_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Schema variant — don't block ingest on metadata insert failure.
            }
        }
    }

    protected static function stageMeta(int $fileId): void
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'meta']);
        // ExifTool / JHOVE integration is P4; basic info is captured when
        // the file row is inserted by the watcher.
    }

    /**
     * Use the path-layout resolver (Style 1) to compute parent + identifier.
     * Returns a meta array suitable for IngestService::ingestFile().
     */
    protected static function stageResolveDestination(int $fileId, ?object $folder): array
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'io']);
        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        $session = DB::table('ingest_session')->where('id', $file->session_id)->first();

        if (!$folder || !$session) {
            throw new \RuntimeException('Cannot resolve destination: missing folder or session context.');
        }

        $layout = $folder->layout ?? 'path';
        if ($layout !== 'path') {
            throw new \RuntimeException("Layout '{$layout}' not yet supported (P3).");
        }

        $resolver = new PathLayoutResolver();
        $desc = $resolver->resolve($folder, $file->stored_path);
        if (!$desc) {
            throw new \RuntimeException("Path does not match layout 'path' for folder {$folder->code}: " . $file->stored_path);
        }

        // Dedupe at IO level: if the file's hash already ingested against the
        // same IO, treat as duplicate rather than create another DO.
        if (!empty($file->source_hash)) {
            $existingDo = DB::table('digital_object')
                ->where('checksum', $file->source_hash)
                ->where('checksum_type', 'sha256')
                ->first();
            if ($existingDo) {
                throw new DuplicateFileException(
                    "File already ingested as digital_object #{$existingDo->id} on IO #{$existingDo->object_id}",
                    (int) $existingDo->object_id
                );
            }
        }

        return [
            'parent_id' => $desc['parent_id'],
            'identifier' => $desc['identifier'],
            'title' => $desc['title'],
            'repository_id' => $session->repository_id ?? null,
            'source_standard' => $session->standard ?? null,
        ];
    }

    protected static function stageIoAndDo(int $fileId, array $meta): void
    {
        $file = DB::table('ingest_file')->where('id', $fileId)->first();

        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'do']);

        /** @var IngestService $ingest */
        $ingest = app(IngestService::class);
        $result = $ingest->ingestFile(
            (int) $file->session_id,
            $file->stored_path,
            $meta,
            $file->original_name
        );

        DB::table('ingest_file')->where('id', $fileId)->update([
            'resolved_io_id' => $result['io_id'],
            'resolved_do_id' => $result['do_id'],
        ]);
    }

    protected static function stageIndexing(int $fileId): void
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'indexing']);
        // ES upsert hook — handled by ahg-search via model events when present;
        // no-op here to avoid double-indexing.
    }

    // ---------------------------------------------------------------------
    // Disposition
    // ---------------------------------------------------------------------

    protected static function dispositionSuccess(int $fileId, ?object $folder): void
    {
        if (!$folder) return;
        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        $srcPath = $file->stored_path;
        if (!is_file($srcPath)) {
            return; // already moved by IngestService into uploads canonical location
        }

        switch ($folder->disposition_success) {
            case 'move':
                $archive = rtrim(config('heratio.scan.archive_path'), '/') . '/' . date('Y/m');
                if (!is_dir($archive)) { @mkdir($archive, 0775, true); }
                @rename($srcPath, $archive . '/' . basename($srcPath));
                break;
            case 'delete':
                @unlink($srcPath);
                break;
            case 'leave':
            default:
                // Leave in place; watcher dedupe-on-hash prevents re-ingest.
        }
    }

    protected static function dispositionFailure(int $fileId, ?object $folder, string $reason): void
    {
        if (!$folder || ($folder->disposition_failure ?? 'quarantine') !== 'quarantine') {
            return;
        }
        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        if (!is_file($file->stored_path)) {
            return;
        }
        $safeReason = preg_replace('/[^a-z0-9_-]+/i', '-', substr($reason, 0, 40)) ?: 'error';
        $q = rtrim(config('heratio.scan.quarantine_path'), '/') . '/' . date('Y/m') . '/' . $safeReason;
        if (!is_dir($q)) { @mkdir($q, 0775, true); }
        @rename($file->stored_path, $q . '/' . basename($file->stored_path));
    }
}

class DuplicateFileException extends \RuntimeException
{
    public function __construct(string $message, public int $ioId)
    {
        parent::__construct($message);
    }
}
