<?php

/**
 * OaisLifecycleService — Phase 3.4 (OAIS SIP / AIP / DIP formalisation).
 *
 * Turns Heratio's `preservation_package` table into a proper OAIS Information
 * Package state machine:
 *
 *   SIP (Submission Information Package)
 *      └── promote → AIP (Archival Information Package)
 *                       └── derive → DIP (Dissemination Information Package)
 *
 * Lineage is recorded in `preservation_package.parent_package_id`.
 * Every transition writes a `preservation_package_event` row (PREMIS-shaped).
 *
 * - createSip(): receive a producer's submission. Records the package as
 *   package_type='sip', status='received'. No checksums computed yet — the
 *   incoming bag may already be a BagIt or just a flat directory.
 *
 * - promoteSipToAip(): the curatorial step. Calls BagItService::buildPackage
 *   on the originating information_object (SIP's source), creating a new
 *   AIP package row with parent_package_id pointing back at the SIP and
 *   status='built'. The SIP is left untouched as the historical record of
 *   what arrived (per OAIS).
 *
 * - createDipFromAip(): per-request package for a consumer. May filter the
 *   AIP's files (e.g. only PDFs), apply watermarking, etc. Writes a fresh
 *   package_type='dip' row with parent_package_id=AIP id.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgPreservation\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OaisLifecycleService
{
    public function __construct(
        protected BagItService $bagit,
        protected PreservationService $preservation
    ) {
    }

    /* ====================================================================
     *  SIP — Submission Information Package
     * ==================================================================== */

    /**
     * Record the receipt of a SIP from a producer.
     *
     * @param int   $ioId  Information object that the SIP corresponds to (the producer's source record).
     * @param array $opts  {
     *   @var string|null $name
     *   @var string|null $description
     *   @var string|null $originator       -- the producer's name
     *   @var string|null $submission_agreement
     *   @var string|null $source_path      -- where the incoming files live (e.g. /sftp/.../inbox/...)
     *   @var int|null    $object_count
     *   @var int|null    $total_size
     * }
     * @return int new preservation_package.id (status=received)
     */
    public function createSip(int $ioId, array $opts = [], ?string $createdBy = null): int
    {
        $packageId = (int) DB::table('preservation_package')->insertGetId([
            'uuid'                  => (string) Str::uuid(),
            'name'                  => (string) ($opts['name'] ?? ('sip-io-' . $ioId . '-' . Carbon::now()->format('Ymd-His'))),
            'description'           => $opts['description'] ?? null,
            'package_type'          => 'sip',
            'package_format'        => 'bagit', // even pre-bagit submissions get tagged as the canonical format
            'status'                => 'received',
            'object_count'          => (int) ($opts['object_count'] ?? 0),
            'total_size'            => (int) ($opts['total_size'] ?? 0),
            'originator'            => $opts['originator'] ?? null,
            'submission_agreement'  => $opts['submission_agreement'] ?? null,
            'information_object_id' => $ioId,
            'source_path'           => $opts['source_path'] ?? null,
            'created_by'            => $createdBy,
            'metadata'              => json_encode(['oais_step' => 'sip_received'], JSON_UNESCAPED_SLASHES),
        ]);

        $this->logEvent($packageId, 'capture', 'SIP received from ' . ($opts['originator'] ?? 'unknown producer'), 'success', $createdBy);

        return $packageId;
    }

    /* ====================================================================
     *  SIP → AIP promotion
     * ==================================================================== */

    /**
     * Promote a SIP to an AIP by building a BagIt around its source IO.
     *
     * The SIP row is preserved as the historical record of what arrived
     * (OAIS principle). A new AIP row is created with parent_package_id
     * pointing at the SIP, and the BagIt is built from the live IO.
     *
     * @param int    $sipId       preservation_package.id of the SIP
     * @param array  $bagOpts     options forwarded to BagItService::buildPackage
     *                            (algorithm, include_descendants, zip, etc.)
     * @param string|null $createdBy
     * @return array{sip_id:int, aip_id:int, bag_path:string, payload_files:int, total_size:int}
     */
    public function promoteSipToAip(int $sipId, array $bagOpts = [], ?string $createdBy = null): array
    {
        $sip = DB::table('preservation_package')->where('id', $sipId)->first();
        if (! $sip) {
            throw new \RuntimeException('SIP not found: ' . $sipId);
        }
        if ($sip->package_type !== 'sip') {
            throw new \RuntimeException('Package #' . $sipId . ' is not a SIP (package_type=' . $sip->package_type . ')');
        }
        if (empty($sip->information_object_id)) {
            throw new \RuntimeException('SIP #' . $sipId . ' has no information_object_id — cannot build AIP');
        }
        if ($sip->status === 'promoted') {
            throw new \RuntimeException('SIP #' . $sipId . ' is already promoted');
        }

        $this->logEvent($sipId, 'normalisation', 'Promoting SIP to AIP', 'success', $createdBy);

        // Override the bag name to make the AIP discoverable from the SIP.
        $bagOpts['name']        = (string) ($bagOpts['name'] ?? ('aip-io-' . $sip->information_object_id . '-' . Carbon::now()->format('Ymd-His')));
        $bagOpts['description'] = (string) ($bagOpts['description'] ?? ('AIP promoted from SIP #' . $sipId));
        $bagOpts['source_organization'] = $bagOpts['source_organization'] ?? $sip->originator;

        $built = $this->bagit->buildPackage((int) $sip->information_object_id, $bagOpts, $createdBy);
        $aipId = (int) $built['package_id'];

        // The BagItService writes package_type='AIP' uppercase — normalise to lowercase to match the SIP/DIP convention.
        DB::table('preservation_package')->where('id', $aipId)->update([
            'package_type'      => 'aip',
            'parent_package_id' => $sipId,
        ]);

        DB::table('preservation_package')->where('id', $sipId)->update([
            'status'      => 'promoted',
            'metadata'    => json_encode([
                'oais_step'      => 'sip_promoted',
                'promoted_to_aip' => $aipId,
                'promoted_at'    => Carbon::now()->toDateTimeString(),
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $this->logEvent($aipId, 'capture', 'AIP built from SIP #' . $sipId, 'success', $createdBy);
        $this->logEvent($sipId, 'normalisation', 'SIP promoted to AIP #' . $aipId, 'success', $createdBy);

        return [
            'sip_id'        => $sipId,
            'aip_id'        => $aipId,
            'bag_path'      => $built['bag_path'],
            'payload_files' => $built['payload_files'],
            'total_size'    => $built['total_size'],
        ];
    }

    /**
     * Convenience: build an AIP directly from an information_object without
     * going through a SIP. Used by the RM transfer-to-archives executor (P3.6)
     * — there's no producer "submission", the IO is already in the catalogue.
     *
     * @return array{aip_id:int, bag_path:string, payload_files:int, total_size:int}
     */
    public function createAipFromIO(int $ioId, array $bagOpts = [], ?string $createdBy = null): array
    {
        $bagOpts['name']        = (string) ($bagOpts['name'] ?? ('aip-io-' . $ioId . '-' . Carbon::now()->format('Ymd-His')));
        $bagOpts['description'] = (string) ($bagOpts['description'] ?? ('AIP for information object #' . $ioId));

        $built = $this->bagit->buildPackage($ioId, $bagOpts, $createdBy);
        $aipId = (int) $built['package_id'];

        DB::table('preservation_package')->where('id', $aipId)->update([
            'package_type' => 'aip',
        ]);

        $this->logEvent($aipId, 'capture', 'AIP built directly from IO #' . $ioId . ' (no SIP)', 'success', $createdBy);

        return [
            'aip_id'        => $aipId,
            'bag_path'      => $built['bag_path'],
            'payload_files' => $built['payload_files'],
            'total_size'    => $built['total_size'],
        ];
    }

    /* ====================================================================
     *  AIP → DIP derivation
     * ==================================================================== */

    /**
     * Create a DIP from an AIP for delivery to a consumer.
     *
     * @param int   $aipId   preservation_package.id of the source AIP
     * @param array $opts    {
     *   @var string|null $name
     *   @var string|null $description
     *   @var array|null  $include_paths    -- relative_path values to include (subset filter)
     *   @var array|null  $include_mime     -- mime_type prefixes to include (e.g. ['application/pdf', 'image/'])
     *   @var bool        $zip              -- default true
     * }
     * @return array{dip_id:int, aip_id:int, file_count:int, total_size:int, export_path:?string}
     */
    public function createDipFromAip(int $aipId, array $opts = [], ?string $createdBy = null): array
    {
        $aip = DB::table('preservation_package')->where('id', $aipId)->first();
        if (! $aip) {
            throw new \RuntimeException('AIP not found: ' . $aipId);
        }
        if ($aip->package_type !== 'aip') {
            throw new \RuntimeException('Package #' . $aipId . ' is not an AIP (package_type=' . $aip->package_type . ')');
        }
        if (! $aip->source_path || ! is_dir($aip->source_path)) {
            throw new \RuntimeException('AIP #' . $aipId . ' bag directory missing: ' . ($aip->source_path ?? '(none)'));
        }

        $algorithm = $aip->manifest_algorithm ?: 'sha256';
        $includePaths = isset($opts['include_paths']) && is_array($opts['include_paths']) ? array_flip($opts['include_paths']) : null;
        $includeMime  = isset($opts['include_mime']) && is_array($opts['include_mime']) ? $opts['include_mime'] : null;

        // Source files come from the AIP's payload list.
        $payload = DB::table('preservation_package_object')
            ->where('package_id', $aipId)
            ->where('object_role', 'payload')
            ->orderBy('id')
            ->get();

        // Apply filter.
        $selected = $payload->filter(function ($row) use ($includePaths, $includeMime) {
            if ($includePaths !== null && ! isset($includePaths[$row->relative_path])) {
                return false;
            }
            if ($includeMime !== null) {
                $matched = false;
                foreach ($includeMime as $prefix) {
                    if ($row->mime_type !== null && str_starts_with((string) $row->mime_type, (string) $prefix)) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    return false;
                }
            }
            return true;
        });

        if ($selected->isEmpty()) {
            throw new \RuntimeException('DIP filter selected zero payload files from AIP #' . $aipId);
        }

        $dipName = (string) ($opts['name'] ?? ('dip-aip-' . $aipId . '-' . Carbon::now()->format('Ymd-His')));
        $dipName = preg_replace('/[^A-Za-z0-9._-]/', '-', $dipName);
        $bagRoot = rtrim((string) (config('heratio.storage_path', storage_path('app')) . '/preservation/bags'), '/');
        $dipPath = $bagRoot . '/' . $dipName;
        if (is_dir($dipPath)) {
            throw new \RuntimeException('DIP directory already exists: ' . $dipPath);
        }
        @mkdir($dipPath . '/data', 0775, true);

        $dipId = (int) DB::table('preservation_package')->insertGetId([
            'uuid'                  => (string) Str::uuid(),
            'name'                  => $dipName,
            'description'           => $opts['description'] ?? ('DIP derived from AIP #' . $aipId),
            'package_type'          => 'dip',
            'package_format'        => 'bagit',
            'bagit_version'         => BagItService::VERSION,
            'manifest_algorithm'    => $algorithm,
            'status'                => 'building',
            'object_count'          => 0,
            'total_size'            => 0,
            'parent_package_id'     => $aipId,
            'information_object_id' => $aip->information_object_id,
            'source_path'           => $dipPath,
            'created_by'            => $createdBy,
            'metadata'              => json_encode([
                'oais_step'      => 'dip_built',
                'derived_from_aip' => $aipId,
                'filter'         => array_intersect_key($opts, ['include_paths' => 1, 'include_mime' => 1]),
            ], JSON_UNESCAPED_SLASHES),
        ]);

        try {
            $manifestLines = [];
            $totalSize = 0;
            $count = 0;
            foreach ($selected as $row) {
                $absSrc = rtrim($aip->source_path, '/') . '/' . $row->relative_path;
                if (! is_file($absSrc)) {
                    continue;
                }
                // Place under data/<original-relative-without-leading-data>
                $dipRel = preg_replace('/^data\//', '', $row->relative_path);
                $dest   = $dipPath . '/data/' . $dipRel;
                @mkdir(dirname($dest), 0775, true);
                if (! @copy($absSrc, $dest)) {
                    throw new \RuntimeException('DIP copy failed: ' . $absSrc . ' → ' . $dest);
                }
                $sum = hash_file($algorithm, $dest);
                $size = filesize($dest) ?: 0;
                $totalSize += $size;
                $count++;
                $manifestLines[] = $sum . '  data/' . $dipRel;

                DB::table('preservation_package_object')->insert([
                    'package_id'         => $dipId,
                    'digital_object_id'  => $row->digital_object_id,
                    'relative_path'      => 'data/' . $dipRel,
                    'file_name'          => basename($dest),
                    'file_size'          => $size,
                    'checksum_algorithm' => $algorithm,
                    'checksum_value'     => $sum,
                    'mime_type'          => $row->mime_type,
                    'object_role'        => 'payload',
                    'sequence'           => $count,
                ]);
            }

            // Tag files (DIP is a fully-formed bag too).
            $manifestFile = "manifest-{$algorithm}.txt";
            file_put_contents($dipPath . '/' . $manifestFile, implode("\n", $manifestLines) . "\n");
            file_put_contents($dipPath . '/bagit.txt',
                "BagIt-Version: " . BagItService::VERSION . "\n" .
                "Tag-File-Character-Encoding: UTF-8\n"
            );
            $bagInfo = sprintf(
                "Source-Organization: %s\nExternal-Identifier: heratio:dip:%d\nBagging-Date: %s\nBag-Size: %d B\nPayload-Oxum: %d.%d\nDerived-From-AIP: %d\n",
                'Heratio',
                $dipId,
                Carbon::now()->toDateString(),
                $totalSize,
                $totalSize,
                $count,
                $aipId
            );
            file_put_contents($dipPath . '/bag-info.txt', $bagInfo);

            $tagManifestLines = [];
            foreach (['bagit.txt', 'bag-info.txt', $manifestFile] as $tf) {
                $tagManifestLines[] = hash_file($algorithm, $dipPath . '/' . $tf) . '  ' . $tf;
            }
            $tagManifestFile = "tagmanifest-{$algorithm}.txt";
            file_put_contents($dipPath . '/' . $tagManifestFile, implode("\n", $tagManifestLines) . "\n");

            // Optional zip.
            $exportPath = null;
            $shouldZip  = (bool) ($opts['zip'] ?? true);
            if ($shouldZip) {
                $exportPath = $dipPath . '.zip';
                $this->zipDir($dipPath, $exportPath);
            }

            DB::table('preservation_package')->where('id', $dipId)->update([
                'status'        => 'built',
                'object_count'  => $count,
                'total_size'    => $totalSize,
                'export_path'   => $exportPath,
                'package_checksum' => $exportPath ? hash_file($algorithm, $exportPath) : null,
                'built_at'      => Carbon::now(),
            ]);

            $this->logEvent($dipId, 'capture', "DIP built from AIP #{$aipId}: {$count} files, {$totalSize} bytes", 'success', $createdBy);
            $this->logEvent($aipId, 'replication', "DIP #{$dipId} derived for delivery", 'success', $createdBy);

            return [
                'dip_id'      => $dipId,
                'aip_id'      => $aipId,
                'file_count'  => $count,
                'total_size'  => $totalSize,
                'export_path' => $exportPath,
            ];
        } catch (Throwable $e) {
            DB::table('preservation_package')->where('id', $dipId)->update(['status' => 'failed']);
            $this->logEvent($dipId, 'capture', 'DIP build failed: ' . mb_substr($e->getMessage(), 0, 1000), 'failure', $createdBy);
            Log::error('preservation: DIP build failed', ['dip_id' => $dipId, 'aip_id' => $aipId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /* -------------------------------------------------------------------- */

    public function getLineage(int $packageId): array
    {
        $chain = [];
        $current = $packageId;
        while ($current) {
            $row = DB::table('preservation_package')
                ->where('id', $current)
                ->select('id', 'package_type', 'name', 'status', 'parent_package_id', 'created_at', 'built_at')
                ->first();
            if (! $row) {
                break;
            }
            $chain[] = $row;
            $current = $row->parent_package_id;
        }

        // Children (descendants) of the original package.
        $descendants = DB::table('preservation_package')
            ->where('parent_package_id', $packageId)
            ->select('id', 'package_type', 'name', 'status', 'created_at', 'built_at')
            ->orderBy('id')
            ->get()
            ->all();

        return [
            'package_id'   => $packageId,
            'ancestors'    => array_reverse($chain), // root first
            'descendants'  => $descendants,
        ];
    }

    protected function zipDir(string $sourceDir, string $exportPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($exportPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not open zip: ' . $exportPath);
        }
        $base = basename($sourceDir);
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $f) {
            $abs = $f->getPathname();
            $rel = $base . '/' . ltrim(substr($abs, strlen($sourceDir)), '/');
            if ($f->isDir()) {
                $zip->addEmptyDir($rel);
            } else {
                $zip->addFile($abs, $rel);
            }
        }
        $zip->close();
    }

    protected function logEvent(int $packageId, string $type, string $detail, string $outcome, ?string $createdBy): void
    {
        try {
            DB::table('preservation_package_event')->insert([
                'package_id'   => $packageId,
                'event_type'   => $type,
                'event_detail' => $detail,
                'event_outcome'=> $outcome,
                'agent_type'   => 'system',
                'agent_value'  => 'heratio-oais',
                'created_by'   => $createdBy,
            ]);
        } catch (Throwable $e) {
            Log::warning('preservation: package event log failed', ['error' => $e->getMessage()]);
        }
    }
}
