<?php

/**
 * AiHtrCommand - bulk handwritten-text recognition over archival objects.
 *
 * Resolves the master digital object (image or PDF) for each selected
 * information_object and runs HtrService::extractAndRecord(), which posts the
 * file to the HTR service via the AHG AI gateway's HTR proxy
 * (ai.theahg.co.za/ai/v1/htr) and persists a provenance-logged result.
 *
 * No GPU node port is contacted here - HtrService owns endpoint resolution.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\HtrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiHtrCommand extends Command
{
    protected $signature = 'ahg:ai-htr
        {--object=       : Limit to a single information_object id}
        {--repository=   : Limit to one repository_id}
        {--all           : Process every object that has a digital object}
        {--limit=100     : Max objects to process}
        {--mode=all      : HTR output format (all, json, csv, ilm, gedcom)}
        {--doc-type=auto : Document type hint passed to the HTR service}
        {--no-zones      : Reserved - skip zone segmentation (forwarded as doc-type hint)}
        {--overwrite     : Re-run HTR even when a transcript already exists}
        {--dry-run       : Resolve files and report, transcribe nothing}';

    protected $description = 'Handwritten text recognition';

    public function handle(HtrService $htr): int
    {
        $mode    = (string) $this->option('mode');
        $docType = (string) $this->option('doc-type');
        $limit   = (int) ($this->option('limit') ?: 100);
        $dryRun  = (bool) $this->option('dry-run');

        if (!$this->option('object') && !$this->option('repository') && !$this->option('all')) {
            $this->error('Specify a scope: --object=ID, --repository=ID, or --all.');
            return self::FAILURE;
        }

        // Pre-flight: confirm the HTR service is reachable via the gateway.
        if (!$dryRun) {
            $health = $htr->health();
            if ($health === null) {
                $this->error('HTR service unreachable (via gateway). Aborting.');
                return self::FAILURE;
            }
        }

        $query = DB::table('information_object as io')
            ->where('io.id', '!=', 1)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))->from('digital_object as dobj')
                    ->whereColumn('dobj.object_id', 'io.id')
                    ->whereNull('dobj.parent_id');
            })
            ->orderBy('io.id')
            ->select('io.id');

        if ($this->option('object')) {
            $query->where('io.id', (int) $this->option('object'));
        }
        if ($this->option('repository')) {
            $query->where('io.repository_id', (int) $this->option('repository'));
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows  = $query->get();
        $total = $rows->count();
        if ($total === 0) {
            $this->info('No matching objects with a digital object.');
            return self::SUCCESS;
        }

        $this->info("HTR over {$total} object(s)" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $done     = 0;
        $skipped  = 0;
        $noFile   = 0;
        $errors   = 0;

        foreach ($rows as $row) {
            $path = $this->resolveFilePath((int) $row->id);
            if ($path === null) {
                $noFile++;
                $this->line("  object {$row->id}: no resolvable image/PDF on disk");
                continue;
            }

            if (!$this->option('overwrite') && $this->hasTranscript((int) $row->id)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  object {$row->id}: would transcribe {$path}");
                $done++;
                continue;
            }

            try {
                $result = $htr->extractAndRecord($path, (int) $row->id, $docType, $mode);
                if ($result === null) {
                    $errors++;
                    $this->warn("Object {$row->id}: HTR returned no result");
                    continue;
                }
                $done++;
            } catch (Throwable $e) {
                $errors++;
                $this->warn("Object {$row->id}: " . $e->getMessage());
            }
        }

        $this->info(sprintf('Transcribed: %d, skipped: %d, no-file: %d, errors: %d', $done, $skipped, $noFile, $errors));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve the on-disk path to the master image/PDF for an object.
     * Mirrors AiController::getDigitalObjectPath() candidate resolution.
     */
    private function resolveFilePath(int $objectId): ?string
    {
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->whereNull('parent_id')
            ->orderByDesc('id')
            ->get();

        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp'];

        foreach ($digitalObjects as $do) {
            $path = $do->path ?? null;
            $name = $do->name ?? null;
            if (!$path || !$name) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                continue;
            }
            $rel = ltrim($path, '/') . $name;
            $candidates = [
                rtrim((string) config('heratio.storage_path'), '/') . '/' . $rel,
                rtrim((string) config('heratio.uploads_path'), '/') . '/' . $rel,
                '/usr/share/nginx/archive/' . $rel,
                '/usr/share/nginx/archive/uploads/' . ltrim(str_replace('/uploads/', '', $path), '/') . $name,
            ];
            foreach ($candidates as $full) {
                if (is_file($full)) {
                    return $full;
                }
            }
        }

        return null;
    }

    /** True when the object already has a stored HTR/OCR transcript. */
    private function hasTranscript(int $objectId): bool
    {
        try {
            return DB::table('iiif_ocr_text')
                ->where('object_id', $objectId)
                ->whereNotNull('full_text')
                ->where('full_text', '!=', '')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
