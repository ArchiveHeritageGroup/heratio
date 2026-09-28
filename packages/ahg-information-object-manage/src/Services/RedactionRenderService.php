<?php

namespace AhgInformationObjectManage\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
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

    /** Region statuses that are in force for viewers. */
    public const LIVE_STATUSES = ['applied', 'reviewed', 'pending'];

    /**
     * Generate (or reuse) a redacted file for the given IO. Returns the
     * absolute path to the redacted file, or null when no master file
     * exists / no regions are on file. Idempotent: a second call with the
     * same regions returns the cached path without re-rendering.
     *
     * $doId picks one of the record's images (heratio#1503); null means the
     * default master, which is what a single-image record always had.
     */
    public function render(int $ioId, ?int $doId = null): ?string
    {
        $master = $doId === null ? $this->defaultMaster($ioId) : $this->masterFor($ioId, $doId);
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
            // regions_hash covers the REGIONS, not the output format, so a row
            // written before the transcode landed still points at a perfectly
            // valid .tiff derivative and would be served forever. Re-render
            // when the stored extension is not the one we would write now.
            $wantExt = self::derivativeExtension((string) $master->name, $fileType);
            if (strtolower(pathinfo($cached->redacted_path, PATHINFO_EXTENSION)) === $wantExt) {
                return $cached->redacted_path;
            }
        }

        // Otherwise render fresh.
        $cacheDir = rtrim(config('heratio.uploads_path', '/mnt/nas/heratio'), '/') . '/redaction-cache/' . $ioId;
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $ext = self::derivativeExtension((string) $master->name, $fileType);
        // The master id is in the name because two images of one record can
        // carry identical regions, and would otherwise share a file.
        $outputPath = $cacheDir . '/' . $master->id . '-' . substr($hash, 0, 16) . '.' . $ext;

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

    /**
     * The master a region with no digital_object_id belongs to, and the one a
     * single-image viewer shows.
     */
    public function defaultMaster(int $ioId): ?object
    {
        // An IO can have multiple parent_id IS NULL rows (e.g. a PDF master AND
        // a JPG preview that was uploaded as a separate "master"). The PDF that
        // the redactions reference might not be the row MySQL returns first
        // without an ORDER BY - that intermittently swapped which file we
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

        // Prefer a real master (usage 140) over an older parentless derivative
        // row, which is what the editor always picked. The two used to disagree
        // until a region was saved.
        return DB::table('digital_object')
            ->where('object_id', $ioId)
            ->whereNull('parent_id')
            ->orderByRaw('usage_id = 140 DESC')
            ->orderBy('id')
            ->select('id', 'name', 'path', 'mime_type')
            ->first();
    }

    /**
     * Every image of the record a region can be drawn on: its own parentless
     * masters, then objects attached through information_object_digital_object
     * (#1447), whose object_id is NULL. Each carries a `label` for pickers.
     *
     * @return Collection<int,object>
     */
    public function mastersFor(int $ioId): Collection
    {
        $own = DB::table('digital_object')
            ->where('object_id', $ioId)
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get(['id', 'name', 'path', 'mime_type', 'name as label']);

        $attached = collect();
        if (\AhgCore\Services\AttachedDigitalObjectService::available()) {
            $attached = DB::table(\AhgCore\Services\AttachedDigitalObjectService::TABLE . ' as l')
                ->join('digital_object as d', 'd.id', '=', 'l.digital_object_id')
                ->where('l.information_object_id', $ioId)
                ->orderBy('l.sort_order')
                ->orderBy('l.id')
                ->get(['d.id', 'd.name', 'd.path', 'd.mime_type', DB::raw('COALESCE(l.caption, d.name) as label')]);
        }

        return $own->concat($attached)->unique('id')->values();
    }

    /** One image of the record, or null when $doId is not one of them. */
    public function masterFor(int $ioId, int $doId): ?object
    {
        return $this->mastersFor($ioId)->firstWhere('id', $doId);
    }

    /**
     * The image a page is displaying, as a redaction target: that image when it
     * is one of the record's masters, otherwise the default master. The show
     * page picks its master its own way (DigitalObjectService::getForObject),
     * which can land on a row with a parent in legacy data.
     */
    public function masterOrDefault(int $ioId, ?int $doId): ?object
    {
        return ($doId ? $this->masterFor($ioId, $doId) : null) ?? $this->defaultMaster($ioId);
    }

    /**
     * The regions drawn on one image of a record - the single definition every
     * reader uses, so the editor, the renderer, the asset endpoint and the show
     * page cannot disagree about which regions cover which file.
     *
     * A region belongs to the image in its digital_object_id. Rows with none
     * predate per-image binding, when the editor only ever drew on a primary
     * master, so they count against EVERY primary master of the record - never
     * against an attached object. Where a record has two primaries that can
     * over-redact the sibling, which is the safe direction: tying them to one
     * guessed master would leak whenever a page shows the other. Saving in the
     * editor binds them to the image on screen.
     */
    public function regionsQuery(int $ioId, int $masterId, bool $liveOnly = true): Builder
    {
        $isPrimary = DB::table('digital_object')
            ->where('id', $masterId)
            ->where('object_id', $ioId)
            ->exists();

        return DB::table('privacy_visual_redaction')
            ->where('object_id', $ioId)
            ->when($liveOnly, fn ($q) => $q->whereIn('status', self::LIVE_STATUSES))
            ->where(function ($q) use ($masterId, $isPrimary) {
                $q->where('digital_object_id', $masterId);
                if ($isPrimary) {
                    $q->orWhereNull('digital_object_id');
                }
            });
    }

    /**
     * Whether the signed-in viewer sees originals rather than redactions.
     * Administrators only, the same rule every redaction reader applies.
     */
    public static function viewerCanBypass(): bool
    {
        $u = auth()->check() ? auth()->user() : null;
        if (!$u) {
            return false;
        }

        return method_exists($u, 'isAdministrator') ? (bool) $u->isAdministrator() : (bool) ($u->is_admin ?? false);
    }

    /**
     * Whether a Cantaloupe identifier names a file whose pixels are covered by
     * live regions: a redacted master, or any derivative of one (the viewers
     * deep-zoom the reference copy). Cantaloupe tiles the ORIGINAL, so these
     * must not be served to anyone who cannot bypass redaction (GHSA-wpfv-ccw6-g9jg).
     *
     * An identifier that is not a digital object at all is not redacted.
     */
    public function isRedactedIdentifier(string $identifier): bool
    {
        // uploads_SL_r_SL_837_SL_x.jpg[;page] -> /uploads/r/837/ + x.jpg
        $decoded = str_replace('_SL_', '/', preg_replace('/;\d+$/', '', rawurldecode($identifier)));
        $name = basename($decoded);
        $dir = trim(dirname($decoded), '/');
        if ($name === '' || $dir === '' || $dir === '.') {
            return false;
        }

        // Upload paths are content-addressed, so one file can back several
        // records - redacted on one, not on another. The pixels are the same,
        // so the file is refused when ANY record using it has it redacted.
        $rows = DB::table('digital_object')
            ->where('name', $name)
            ->whereIn('path', ['/' . $dir . '/', $dir . '/', '/' . $dir, $dir])
            ->get(['id', 'object_id', 'parent_id']);

        foreach ($rows as $row) {
            $master = $row->parent_id
                ? DB::table('digital_object')->where('id', $row->parent_id)->first(['id', 'object_id'])
                : $row;
            if (!$master) {
                continue;
            }

            // The record: the primary's object_id, or the link table for an
            // attached object (#1447), whose object_id is NULL.
            $ioIds = $master->object_id
                ? [(int) $master->object_id]
                : (\AhgCore\Services\AttachedDigitalObjectService::available()
                    ? DB::table(\AhgCore\Services\AttachedDigitalObjectService::TABLE)
                        ->where('digital_object_id', $master->id)->pluck('information_object_id')->map('intval')->all()
                    : []);

            foreach ($ioIds as $ioId) {
                if ($this->regionsQuery($ioId, (int) $master->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Ids of the record's images that carry live regions. */
    public function redactedMasterIds(int $ioId): array
    {
        return $this->mastersFor($ioId)
            ->filter(fn ($m) => $this->regionsQuery($ioId, (int) $m->id)->exists())
            ->pluck('id')->map('intval')->values()->all();
    }

    private function resolveAbsolutePath(object $master): ?string
    {
        // Heratio stores files under config('heratio.uploads_path') and the
        // web-facing path field starts with /uploads/r/ - strip that prefix
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
        // Heratio's real on-disk layout is <uploads_path>/r/<hash>/, i.e. the
        // '/r/' segment IS part of the filesystem path - so strip only the
        // leading 'uploads/' (keep 'r/'). The canonical strip above drops '/r/'
        // and only matches the flatter AtoM legacy layout.
        $uploadsOnly = str_starts_with($rawPath, 'uploads/')
            ? substr($rawPath, strlen('uploads/'))
            : $rawPath;
        $candidates = [
            $uploads . '/' . $uploadsOnly . $master->name,        // Heratio: <uploads>/r/<hash>/
            $uploads . '/' . $stripped . $master->name,           // canonical (AtoM legacy, no /r/)
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
        // Through regionsQuery(), the same scope the show page and the asset
        // endpoint use. Filtering by digital_object_id alone once dropped every
        // region when the master picked here was a sibling of the one they were
        // bound to; resolving the master through defaultMaster(), which prefers
        // the referenced one, is what keeps that from coming back.
        $rows = $this->regionsQuery($ioId, $masterId)
            ->orderBy('page_number')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            // Through the shared reader: this read 'top'/'left' with no
            // fallback, so a row stored as x/y burned the mask at (0,0) at the
            // right size - into a derivative file, where it stays.
            $rect   = \AhgInformationObjectManage\Services\PrivacyService::redactionRect($r->coordinates ?? '{}');
            $top    = $rect['top'];
            $left   = $rect['left'];
            $width  = $rect['width'];
            $height = $rect['height'];
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
        // Stable ordering - same regions in same order produce same hash.
        return hash('sha256', json_encode($regions));
    }

    /**
     * Image formats a browser will actually render.
     *
     * Everything else - TIFF, JP2 and friends - is transcoded to JPEG for the
     * derivative. The redaction path cannot go through Cantaloupe, because
     * Cantaloupe tiles the ORIGINAL file and would happily serve tiles of the
     * very content being hidden, so it also gives up Cantaloupe's transcode.
     * Without this the burnt-in derivative kept the master's extension: a
     * redacted TIFF was written as TIFF and served as image/tiff, which no
     * browser renders, so Mirador showed nothing at all. Fail-safe, but only
     * by accident, and archival TIFF is exactly what the deep-zoom viewers are
     * for.
     */
    private const WEB_RENDERABLE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const MIME_BY_EXT = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];

    /**
     * The extension the derivative is written with - which is also what the
     * Python redactor picks its output format from.
     */
    public static function derivativeExtension(string $sourceName, string $fileType): string
    {
        $ext = strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));

        if ($fileType === 'pdf') {
            return 'pdf';
        }

        return in_array($ext, self::WEB_RENDERABLE, true) ? $ext : 'jpg';
    }

    /**
     * Content type for a rendered derivative, read off the derivative itself.
     *
     * The caller must NOT reuse the master's mime type: after a transcode the
     * two disagree, and the browser believes the header.
     */
    public static function mimeForPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::MIME_BY_EXT[$ext] ?? 'application/octet-stream';
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
