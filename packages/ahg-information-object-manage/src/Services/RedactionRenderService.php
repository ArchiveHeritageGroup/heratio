<?php

namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Server-side renderer for visual redactions.
 *
 * Reads privacy_visual_redaction rows for an IO + the master digital_object,
 * shells out to the bundled Python redactors (PyMuPDF for PDFs, Pillow for
 * raster images), writes the redacted file to a cache directory, and records
 * a privacy_redaction_cache row. Non-admin viewers are then served from this
 * cache (see PrivacyController::redactedAsset).
 *
 * Cache key: sha256(sorted region payloads + applied/pending status). Same
 * regions in the same order produce the same hash, so re-renders are
 * detected at the cache layer and skipped.
 */
class RedactionRenderService
{
    private const PYTHON_DIR = __DIR__ . '/../../python';

    /**
     * Generate (or reuse) a redacted file for the given IO. Returns the
     * absolute path to the redacted file, or null when no master file
     * exists / no regions are on file. Idempotent: a second call with the
     * same regions returns the cached path without re-rendering.
     */
    public function render(int $ioId): ?string
    {
        $master = $this->getMaster($ioId);
        if (!$master || empty($master->path) || empty($master->name)) {
            return null;
        }
        $sourcePath = $this->resolveAbsolutePath($master);
        if (!$sourcePath || !file_exists($sourcePath)) {
            Log::warning('[redaction] master file missing on disk', ['io_id' => $ioId, 'path' => $sourcePath]);
            return null;
        }

        $regions = $this->loadRegions($ioId, (int) $master->id);
        if (empty($regions)) {
            return null;
        }

        $hash = $this->regionsHash($regions);
        $fileType = $this->fileTypeFor($master);

        // Cache hit?
        $cached = DB::table('privacy_redaction_cache')
            ->where('object_id', $ioId)
            ->where('digital_object_id', $master->id)
            ->where('regions_hash', $hash)
            ->first();
        if ($cached && file_exists($cached->redacted_path)) {
            return $cached->redacted_path;
        }

        // Otherwise render fresh.
        $cacheDir = rtrim(config('heratio.uploads_path', '/mnt/nas/heratio'), '/') . '/redaction-cache/' . $ioId;
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $ext = pathinfo($master->name, PATHINFO_EXTENSION);
        $outputPath = $cacheDir . '/' . substr($hash, 0, 16) . '.' . $ext;

        $ok = $fileType === 'pdf'
            ? $this->renderPdf($sourcePath, $outputPath, $regions)
            : $this->renderImage($sourcePath, $outputPath, $regions);

        if (!$ok || !file_exists($outputPath)) {
            return null;
        }

        // Bust any stale cache rows for this DO + drop the new one in.
        DB::table('privacy_redaction_cache')
            ->where('object_id', $ioId)
            ->where('digital_object_id', $master->id)
            ->delete();
        DB::table('privacy_redaction_cache')->insert([
            'object_id'         => $ioId,
            'digital_object_id' => $master->id,
            'original_path'     => $sourcePath,
            'redacted_path'     => $outputPath,
            'file_type'         => $fileType,
            'regions_hash'      => $hash,
            'region_count'      => count($regions),
            'file_size'         => filesize($outputPath),
            'generated_at'      => now(),
        ]);

        return $outputPath;
    }

    /** Invalidate cache rows for an IO so the next view re-renders. */
    public function invalidate(int $ioId): void
    {
        $rows = DB::table('privacy_redaction_cache')->where('object_id', $ioId)->get();
        foreach ($rows as $r) {
            if (!empty($r->redacted_path) && file_exists($r->redacted_path)) {
                @unlink($r->redacted_path);
            }
        }
        DB::table('privacy_redaction_cache')->where('object_id', $ioId)->delete();
    }

    private function getMaster(int $ioId): ?object
    {
        // An IO can have multiple parent_id IS NULL rows (e.g. a PDF master AND
        // a JPG preview that was uploaded as a separate "master"). The PDF that
        // the redactions reference might not be the row MySQL returns first
        // without an ORDER BY — that intermittently swapped which file we
        // looked at and the redactions silently disappeared. Prefer the master
        // referenced by the redaction rows; otherwise fall back to oldest by id.
        $referenced = DB::table('digital_object as d')
            ->join('privacy_visual_redaction as r', 'r.digital_object_id', '=', 'd.id')
            ->where('d.object_id', $ioId)
            ->whereNull('d.parent_id')
            ->whereIn('r.status', ['applied', 'reviewed', 'pending'])
            ->select('d.id', 'd.name', 'd.path', 'd.mime_type')
            ->orderBy('d.id')
            ->first();
        if ($referenced) return $referenced;

        return DB::table('digital_object')
            ->where('object_id', $ioId)
            ->whereNull('parent_id')
            ->orderBy('id')
            ->select('id', 'name', 'path', 'mime_type')
            ->first();
    }

    private function resolveAbsolutePath(object $master): ?string
    {
        // Heratio stores files under config('heratio.uploads_path') and the
        // web-facing path field starts with /uploads/r/ — strip that prefix
        // when computing the filesystem path. AtoM legacy data sometimes
        // uses /uploads/ without the /r/ segment.
        $uploads = rtrim(config('heratio.uploads_path', '/mnt/nas/heratio/archive'), '/');
        $rawPath = ltrim((string) $master->path, '/');
        $stripped = $rawPath;
        foreach (['uploads/r/', 'uploads/'] as $prefix) {
            if (str_starts_with($stripped, $prefix)) {
                $stripped = substr($stripped, strlen($prefix));
                break;
            }
        }
        $candidates = [
            $uploads . '/' . $stripped . $master->name,           // canonical
            $uploads . '/' . $rawPath . $master->name,            // belt-and-braces
            rtrim((string) $master->path, '/') . '/' . $master->name, // raw (works if path is already absolute)
            $uploads . '/' . $master->name,                       // last-resort flat
        ];
        foreach ($candidates as $c) {
            if ($c && file_exists($c)) return $c;
        }
        return null;
    }

    private function loadRegions(int $ioId, int $masterId): array
    {
        // Filter by object_id only — the show page's redaction banner / asset
        // reroute also use object_id, so the renderer must agree with them.
        // Filtering by digital_object_id used to silently drop the regions when
        // getMaster() returned a different (sibling) master row.
        $rows = DB::table('privacy_visual_redaction')
            ->where('object_id', $ioId)
            ->whereIn('status', ['applied', 'reviewed', 'pending'])
            ->orderBy('page_number')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $coords = json_decode((string) ($r->coordinates ?? '{}'), true) ?: [];
            $top    = (float) ($coords['top']    ?? 0);
            $left   = (float) ($coords['left']   ?? 0);
            $width  = (float) ($coords['width']  ?? 0);
            $height = (float) ($coords['height'] ?? 0);
            if ($width <= 0 || $height <= 0) continue; // skip zero-sized
            $out[] = [
                'page'       => (int) ($r->page_number ?: 1),
                'x'          => $left,
                'y'          => $top,
                'width'      => $width,
                'height'     => $height,
                'normalized' => (int) ($r->normalized ?? 0) === 1,
                'color'      => $r->color ?: '#000000',
            ];
        }
        return $out;
    }

    private function regionsHash(array $regions): string
    {
        // Stable ordering — same regions in same order produce same hash.
        return hash('sha256', json_encode($regions));
    }

    private function fileTypeFor(object $master): string
    {
        $mime = strtolower((string) ($master->mime_type ?? ''));
        if ($mime === 'application/pdf') return 'pdf';
        if (str_starts_with($mime, 'image/')) return 'image';
        // Fall back to extension.
        $ext = strtolower(pathinfo((string) $master->name, PATHINFO_EXTENSION));
        if ($ext === 'pdf') return 'pdf';
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'gif', 'webp'], true)) return 'image';
        return 'unsupported';
    }

    private function renderPdf(string $in, string $out, array $regions): bool
    {
        $script = self::PYTHON_DIR . '/pdf_redactor.py';
        // PSIS's pdf_redactor.py accepts coordinate-based regions via
        // PdfRedactor::redact_pdf_by_coordinates. We invoke it via a small
        // Python -c stub so we don't have to add a CLI flag upstream.
        $py = sprintf(
            "import sys, json; sys.path.insert(0, %s); from pdf_redactor import PdfRedactor; "
            . "r = PdfRedactor(); res = r.redact_pdf_regions(%s, %s, json.loads(sys.stdin.read())); "
            . "print(json.dumps(res))",
            var_export(self::PYTHON_DIR, true),
            var_export($in, true),
            var_export($out, true)
        );
        $regionsJson = json_encode($regions);
        return $this->runPython(['python3', '-c', $py], $regionsJson, 'pdf', $in);
    }

    private function renderImage(string $in, string $out, array $regions): bool
    {
        $script = self::PYTHON_DIR . '/image_redactor.py';
        // image_redactor.py has a CLI: input output regions_json
        $regionsJson = json_encode($regions);
        return $this->runPython(['python3', $script, $in, $out, $regionsJson], null, 'image', $in);
    }

    private function runPython(array $cmd, ?string $stdin, string $kind, string $sourceFile): bool
    {
        try {
            $proc = new Process($cmd);
            $proc->setTimeout(120);
            if ($stdin !== null) $proc->setInput($stdin);
            $proc->run();
            if (!$proc->isSuccessful()) {
                Log::warning('[redaction] python failed', [
                    'kind'   => $kind,
                    'src'    => $sourceFile,
                    'stderr' => $proc->getErrorOutput(),
                    'stdout' => $proc->getOutput(),
                    'code'   => $proc->getExitCode(),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('[redaction] python exception', ['err' => $e->getMessage(), 'kind' => $kind]);
            return false;
        }
    }
}
