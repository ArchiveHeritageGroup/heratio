<?php
/**
 * Heratio - auto-provenance for digital objects (issue #1201).
 *
 * Bridges a `digital_object` row to a digitisation-provenance record:
 * resolves the on-disk master, decides whether it is a master worth signing,
 * builds the ProvenanceRecordService::record() input, and records it -
 * best-effort, idempotent, and silent when signing is unavailable. Shared by
 * the ingest-time listener (forward-compat hook on the Eloquent model) and the
 * `ahg:c2pa-provenance-backfill` command (the authoritative path, since every
 * live upload path inserts the row via raw DB::table()->insert() and fires no
 * Eloquent/domain event).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The "ingest -> provenance" glue. Distinct from ProvenanceRecordService
 * (which owns the signing + ahg_c2pa_provenance table) and from C2paService
 * (the low-level engine): this class only knows how to turn a digital object
 * into the record() input and how to avoid double-recording.
 */
final class DigitalObjectProvenanceService
{
    /** digital_object.usage_id for a master file (taxonomy 47). */
    private const USAGE_MASTER = 140;

    public function __construct(private ProvenanceRecordService $service)
    {
    }

    /**
     * Best-effort: record digitisation provenance for a single digital object
     * (by id) when it is a master worth signing and has no provenance record
     * yet. Returns the new ahg_c2pa_provenance row id, or null when nothing was
     * recorded (not a master / already recorded / tables absent / any error).
     *
     * NEVER throws - the upload/ingest caller must not be affected.
     */
    public function recordForDigitalObject(int $digitalObjectId, ?string $reason = null): ?int
    {
        try {
            if ($digitalObjectId <= 0
                || !Schema::hasTable('digital_object')
                || !Schema::hasTable('ahg_c2pa_provenance')
            ) {
                return null;
            }

            $do = DB::table('digital_object')
                ->where('id', $digitalObjectId)
                ->first(['id', 'object_id', 'usage_id', 'mime_type', 'name', 'path', 'parent_id']);
            if ($do === null || empty($do->object_id)) {
                return null;
            }

            if (!$this->isSignableMaster($do)) {
                return null;
            }

            // Idempotency: skip when this digital object already has a record.
            $alreadyRecorded = DB::table('ahg_c2pa_provenance')
                ->where('digital_object_id', $digitalObjectId)
                ->exists();
            if ($alreadyRecorded) {
                return null;
            }

            $assetPath = $this->resolveAssetPath($do);

            return $this->service->record((int) $do->object_id, [
                'digital_object_id' => (int) $do->id,
                'captured_by'       => null,
                'capture_software'  => 'Heratio ingest',
                'notes'             => $reason ?? 'Auto-recorded on digital-object ingest',
                'asset_path'        => $assetPath,
                'heratio_version'   => $this->heratioVersion(),
            ]);
        } catch (Throwable $e) {
            Log::warning('c2pa: auto-provenance for digital object failed', [
                'digital_object_id' => $digitalObjectId,
                'err'               => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Public master-file resolver for the "download with content credentials"
     * path (issue #1201). Loads the digital_object row by id and resolves its
     * on-disk master, reusing the SAME candidate logic the ingest / record /
     * backfill paths use, so the download path agrees with the rest of the
     * package on where masters live. Returns null when the row is absent, the
     * path is an external URL, or the file cannot be read. Never throws.
     *
     * @return array{path:string, name:string, mime:?string}|null
     */
    public function resolveMasterForDownload(int $digitalObjectId): ?array
    {
        try {
            if ($digitalObjectId <= 0 || !Schema::hasTable('digital_object')) {
                return null;
            }
            $do = DB::table('digital_object')
                ->where('id', $digitalObjectId)
                ->first(['id', 'object_id', 'usage_id', 'mime_type', 'name', 'path', 'parent_id']);
            if ($do === null) {
                return null;
            }

            // External URL links have no local file to stream.
            $rawPath = (string) ($do->path ?? '');
            if (preg_match('#^(https?|ftp)://#i', $rawPath)) {
                return null;
            }

            $resolved = $this->resolveAssetPath($do);
            if ($resolved === null || !is_readable($resolved)) {
                return null;
            }

            $name = (string) ($do->name ?? '');
            if ($name === '') {
                $name = basename($resolved);
            }

            return [
                'path' => $resolved,
                'name' => $name,
                'mime' => isset($do->mime_type) && (string) $do->mime_type !== '' ? (string) $do->mime_type : null,
            ];
        } catch (Throwable $e) {
            Log::warning('c2pa: resolveMasterForDownload failed', [
                'digital_object_id' => $digitalObjectId,
                'err'               => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * A digital object is a signable master when it is a top-level master row
     * (usage_id=140, or a row with no parent so AtoM "thumbnail-as-master"
     * uploads still qualify) and is NOT an external URL link. We sign masters
     * of any format: embeddable formats (JPEG/PNG/TIFF/MP4) gain a native JUMBF
     * embed when c2patool is present; all others get a signed sidecar + DB
     * record, which is still authoritative inside Heratio.
     */
    private function isSignableMaster(object $do): bool
    {
        // Skip derivatives (reference/thumbnail) - they hang off a parent.
        if (!empty($do->parent_id)) {
            return false;
        }
        $usage = isset($do->usage_id) ? (int) $do->usage_id : null;
        // Master usage, or a parentless row whose usage we cannot read.
        if ($usage !== null && $usage !== self::USAGE_MASTER) {
            // Parentless non-master usage is unusual; treat as master so we
            // don't silently skip a genuine top-level asset.
            // (Derivatives were already excluded by the parent_id check above.)
            $usage = self::USAGE_MASTER;
        }
        if ($usage !== self::USAGE_MASTER) {
            return false;
        }
        // Skip externally-linked URLs (path holds the URL, file is not local).
        $path = (string) ($do->path ?? '');
        if (preg_match('#^(https?|ftp)://#i', $path)) {
            return false;
        }
        return true;
    }

    /**
     * Resolve a digital object's master file on disk. Mirrors
     * ProvenanceController::resolveAssetPath() / C2paEmbedCommand::resolveMaster()
     * so the ingest path agrees with the live record + backfill paths on where
     * masters live. Returns null when the file cannot be found (the record is
     * still created and signed against a synthetic asset hash).
     */
    private function resolveAssetPath(object $do): ?string
    {
        $base = function_exists('config') ? (string) config('heratio.uploads_path', '') : '';
        $path = (string) ($do->path ?? '');
        $name = (string) ($do->name ?? '');
        $candidates = array_filter([
            $base !== '' ? rtrim($base, '/') . '/' . ltrim($path . $name, '/') : null,
            $base !== '' ? rtrim($base, '/') . '/' . ltrim($path, '/') : null,
            $base !== '' ? rtrim($base, '/') . '/' . $name : null,
            $path . $name,
            $path,
        ]);
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function heratioVersion(): string
    {
        if (function_exists('base_path')) {
            $path = base_path('version.json');
            if (is_readable($path)) {
                $data = json_decode((string) file_get_contents($path), true);
                if (is_array($data) && isset($data['version'])) {
                    return (string) $data['version'];
                }
            }
        }
        return 'unknown';
    }
}
