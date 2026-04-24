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
            $resolved = self::stageResolveDestination($fileId, $folder);
            self::stageIoAndDo($fileId, $resolved);
            self::stageMeta($fileId);
            self::stageSectorRouting($fileId);
            self::stageDeriving($fileId);
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

        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        $session = DB::table('ingest_session')->where('id', $file->session_id)->first();

        // Honour the session's process_virus_scan flag.
        if (!$session || !(int) ($session->process_virus_scan ?? 0)) {
            return;
        }

        // Prefer clamscan (standalone, runs as PHP user so it can read
        // staged files in the PHP-user-owned staging dir). Fall back to
        // clamdscan (daemon — faster but needs the daemon's user to have
        // traverse permission on the staging path).
        $clamsc = trim((string) @shell_exec('command -v clamscan 2>/dev/null'));
        $clamd = trim((string) @shell_exec('command -v clamdscan 2>/dev/null'));
        $binary = $clamsc ?: $clamd;
        if ($binary === '') {
            Log::warning('[ahg-scan] virus scan requested but clamscan/clamdscan not found; ingest continuing without scan for ingest_file ' . $fileId);
            return;
        }

        $escaped = escapeshellarg($file->stored_path);
        // --no-summary keeps output short; clamdscan exit code: 0=clean, 1=infected, 2=error.
        $cmd = $binary . ' --no-summary --infected ' . $escaped . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode === 1) {
            $threat = '';
            foreach ($output as $line) {
                if (preg_match('/:\s*(.+)\s+FOUND$/', $line, $m)) {
                    $threat = trim($m[1]);
                    break;
                }
            }
            throw new \RuntimeException('Virus scan detected threat: ' . ($threat ?: 'unknown') . ' (exit ' . $exitCode . ')');
        }
        if ($exitCode !== 0) {
            // ClamAV error — not a clean PASS, but also not a known infection. Fail safe: abort.
            throw new \RuntimeException('Virus scanner error (exit ' . $exitCode . '): ' . substr(implode("\n", $output), 0, 500));
        }
        // Exit 0 = clean; proceed. preservation_virus_scan recording is P4 scope
        // (needs digital_object_id which doesn't exist until stageIoAndDo runs).
    }

    /**
     * Extract embedded metadata (EXIF / IPTC / XMP / document props) into
     * preservation_checksum + digital_object_metadata + media_metadata +
     * dam_iptc_metadata. Runs after IO/DO creation so the target tables
     * have their FK targets in place. ExifTool is optional — absence is
     * logged, never fatal.
     */
    protected static function stageMeta(int $fileId): void
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'meta']);

        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        if (!$file || !$file->resolved_do_id) {
            return;
        }

        try {
            \AhgCore\Services\DigitalObjectService::extractMetadataForMaster((int) $file->resolved_do_id);
        } catch (\Throwable $e) {
            // Non-fatal — metadata extraction failure shouldn't roll back a
            // successful ingest. Log and continue.
            Log::warning('[ahg-scan] metadata extraction failed for DO ' . $file->resolved_do_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Compute destination + descriptive metadata for this file.
     *
     * Priority: sidecar XML > path-layout > session fallback.
     *
     * Returns a meta array suitable for IngestService::ingestFile().
     */
    protected static function stageResolveDestination(int $fileId, ?object $folder): array
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'io']);
        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        $session = DB::table('ingest_session')->where('id', $file->session_id)->first();

        if (!$session) {
            throw new \RuntimeException('Cannot resolve destination: missing session context.');
        }

        // Dedupe at hash level: if the file's hash already ingested, treat as duplicate.
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

        $meta = [];

        // 1a. XML sidecar on disk (watched-folder flat-sidecar layout, or Scan API sidecar upload).
        if (!empty($file->sidecar_path) && is_file($file->sidecar_path)) {
            $parser = new \AhgScan\Services\SidecarParser();
            try {
                $parsed = $parser->parse($file->sidecar_path);
                $meta = $parser->toIngestMeta($parsed, $session);
                DB::table('ingest_file')->where('id', $fileId)->update([
                    'sidecar_json' => json_encode($parsed, JSON_UNESCAPED_SLASHES),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[ahg-scan] sidecar parse failed for ingest_file ' . $fileId . ': ' . $e->getMessage());
                // Fall through to inline metadata + path-layout.
            }
        }

        // 1b. Inline metadata JSON (Scan API's `metadata` form field) — already
        // stashed on ingest_file.sidecar_json when no XML sidecar was uploaded.
        // Lower priority than a real sidecar but higher than path-layout.
        if (empty($meta['parent_id']) && !empty($file->sidecar_json)) {
            $inline = json_decode($file->sidecar_json, true);
            if (is_array($inline) && empty($inline['_warnings'])) {
                // Heuristic: treat as a flat meta dict if it has no nested
                // sidecar envelope markers. Parser output has keys like
                // sector/rights/digital_object — inline metadata is flat.
                if (!isset($inline['rights']) && !isset($inline['digital_object'])) {
                    foreach (['parent_id', 'identifier', 'title', 'scope_and_content',
                              'level_of_description_id', 'repository_id', 'source_standard',
                              'merge'] as $k) {
                        if (isset($inline[$k]) && !isset($meta[$k])) {
                            $meta[$k] = $inline[$k];
                        }
                    }
                }
            }
        }

        // 2. Path-layout (works for Style 1 folders and as fallback when sidecar gave incomplete info)
        if (empty($meta['parent_id']) || empty($meta['identifier'])) {
            if ($folder && ($folder->layout ?? 'path') === 'path') {
                $resolver = new PathLayoutResolver();
                $desc = $resolver->resolve($folder, $file->stored_path);
                if ($desc) {
                    $meta['parent_id'] = $meta['parent_id'] ?? $desc['parent_id'];
                    $meta['identifier'] = $meta['identifier'] ?? $desc['identifier'];
                    $meta['title'] = $meta['title'] ?? $desc['title'];
                }
            }
        }

        // 3. Session fallback for parent/repo/standard
        $meta['parent_id'] = $meta['parent_id'] ?? $session->parent_id ?? null;
        $meta['repository_id'] = $meta['repository_id'] ?? $session->repository_id ?? null;
        $meta['source_standard'] = $meta['source_standard'] ?? $session->standard ?? null;

        if (empty($meta['parent_id'])) {
            $layout = $folder->layout ?? 'path';
            throw new \RuntimeException("Cannot resolve parent destination for file (layout={$layout}, sidecar=" . ($file->sidecar_path ? 'yes' : 'no') . '): ' . $file->stored_path);
        }

        return $meta;
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

    /**
     * Write sector-specific metadata (library/gallery/museum) + DAM
     * augmentation based on the parsed sidecar. No-op for archive sector or
     * when no sidecar was supplied. Warnings from the router are appended
     * to ingest_file.error_message as soft warnings (pipeline continues).
     */
    protected static function stageSectorRouting(int $fileId): void
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'sector-route']);

        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        if (!$file || !$file->resolved_io_id || !$file->resolved_do_id) {
            return;
        }
        $session = DB::table('ingest_session')->where('id', $file->session_id)->first();
        if (!$session) { return; }

        $parsed = null;
        if (!empty($file->sidecar_json)) {
            $decoded = json_decode($file->sidecar_json, true);
            if (is_array($decoded) && (isset($decoded['sector_profile']) || isset($decoded['rights']) || isset($decoded['dam_augmentation']))) {
                $parsed = $decoded;
            }
        }

        try {
            $router = new \AhgScan\Services\SectorRoutingService();
            $warnings = $router->route(
                (int) $file->resolved_io_id,
                (int) $file->resolved_do_id,
                $parsed,
                $session
            );
            if (!empty($warnings)) {
                // Append to any existing error_message (kept as "soft warnings" — the pipeline succeeds).
                DB::table('ingest_file')->where('id', $fileId)->update([
                    'error_message' => trim(($file->error_message ? $file->error_message . "\n" : '') . "[sector warnings]\n" . implode("\n", $warnings)),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] sector routing failed for ingest_file ' . $fileId . ': ' . $e->getMessage());
            // Non-fatal — the base IO/DO are already created.
        }
    }

    protected static function stageDeriving(int $fileId): void
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'deriving']);

        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        if (!$file || !$file->resolved_do_id) {
            return;
        }
        $session = DB::table('ingest_session')->where('id', $file->session_id)->first();
        if (!$session) {
            return;
        }

        \AhgCore\Services\DigitalObjectService::generateDerivativesForMaster(
            (int) $file->resolved_do_id,
            (bool) ($session->derivative_reference ?? 1),
            (bool) ($session->derivative_thumbnails ?? 1)
        );
    }

    protected static function stageIndexing(int $fileId): void
    {
        DB::table('ingest_file')->where('id', $fileId)->update(['stage' => 'indexing']);

        $file = DB::table('ingest_file')->where('id', $fileId)->first();
        if (!$file || !$file->resolved_io_id) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Artisan::call('ahg:es-reindex', [
                '--index' => 'informationobject',
                '--id' => (int) $file->resolved_io_id,
            ]);
        } catch (\Throwable $e) {
            // Non-fatal — ES unavailable just means the record is searchable
            // after the next scheduled reindex. Log and continue.
            Log::info('[ahg-scan] ES index skipped for IO ' . $file->resolved_io_id . ': ' . $e->getMessage());
        }
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

        // Also move the paired sidecar (if one exists and we own it) — but only
        // after the LAST file sharing that sidecar has been processed. For
        // simplicity, move it together with the current file on success; if
        // other siblings still need it, they'll be resolved to 'duplicate' by
        // hash match on next pass because their IO already exists.
        $siblings = !empty($file->sidecar_path) && is_file($file->sidecar_path)
            ? [$file->sidecar_path]
            : [];

        switch ($folder->disposition_success) {
            case 'move':
                $archive = rtrim(config('heratio.scan.archive_path'), '/') . '/' . date('Y/m');
                if (!is_dir($archive)) { @mkdir($archive, 0775, true); }
                @rename($srcPath, $archive . '/' . basename($srcPath));
                foreach ($siblings as $sidecar) {
                    @rename($sidecar, $archive . '/' . basename($sidecar));
                }
                break;
            case 'delete':
                @unlink($srcPath);
                foreach ($siblings as $sidecar) { @unlink($sidecar); }
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
