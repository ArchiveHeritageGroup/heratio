<?php

/**
 * BagItService — Phase 3.1.
 *
 * Builds RFC-8493-compliant BagIt v1.0 packages from one or more digital_object
 * rows belonging to an information_object subtree, and validates existing bags.
 *
 * A built bag has the structure:
 *
 *     <bag-name>/
 *         bagit.txt                  -- BagIt-Version: 1.0 + Tag-File-Character-Encoding: UTF-8
 *         bag-info.txt               -- Source-Organization, External-Identifier, Bagging-Date,
 *                                       Bag-Size, Payload-Oxum, Heratio-Information-Object-Id, etc.
 *         manifest-<alg>.txt         -- payload checksums (one row per file under data/)
 *         tagmanifest-<alg>.txt      -- checksums of bagit.txt + bag-info.txt + manifest-<alg>.txt
 *         data/                      -- the payload tree, mirroring digital_object.path
 *             ...
 *
 * Then optionally zipped to $export_path.
 *
 * Persistence:
 *   - rm_record_disposal_class is unaffected.
 *   - preservation_package is the package row (status, format=bagit, manifest_algorithm, etc).
 *   - preservation_package_object is one row per file (with relative_path + checksum_value).
 *   - preservation_package_event captures every lifecycle PREMIS event.
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
use ZipArchive;

class BagItService
{
    public const VERSION = '1.0';
    public const TAG_ENCODING = 'UTF-8';

    public function __construct(protected PreservationService $preservation)
    {
    }

    /**
     * Build a bag from a single information_object's digital_object subtree.
     *
     * @param int   $ioId   Information object that anchors the bag (its descendants' digital files become payload).
     * @param array $opts   {
     *   @var string|null  $algorithm           sha256 (default), sha512, md5
     *   @var string|null  $name                bag name; defaults to "heratio-bag-<ioId>-<date>"
     *   @var string|null  $description
     *   @var string|null  $source_organization
     *   @var string|null  $originator
     *   @var bool         $include_descendants Whether to include child IOs' digital objects (default true)
     *   @var bool         $zip                 Zip the bag into $export_path (default true)
     *   @var string|null  $bag_root            Override base path for staging the bag dir
     * }
     * @param string|null $createdBy   Username/email recorded as creator + agent in events
     * @return array{package_id:int, status:string, bag_path:string, export_path:?string, payload_files:int, total_size:int}
     */
    public function buildPackage(int $ioId, array $opts = [], ?string $createdBy = null): array
    {
        $algorithm = $this->validateAlgorithm($opts['algorithm'] ?? 'sha256');
        $includeDescendants = (bool) ($opts['include_descendants'] ?? true);
        $shouldZip = (bool) ($opts['zip'] ?? true);
        $bagName = (string) ($opts['name'] ?? ('heratio-bag-' . $ioId . '-' . Carbon::now()->format('Ymd-His')));
        $bagName = $this->safeName($bagName);

        $bagRoot = rtrim(
            (string) ($opts['bag_root'] ?? config('heratio.storage_path', storage_path('app')) . '/preservation/bags'),
            '/'
        );
        if (! is_dir($bagRoot) && ! @mkdir($bagRoot, 0775, true) && ! is_dir($bagRoot)) {
            throw new \RuntimeException('Bag root not writable: ' . $bagRoot);
        }
        $bagPath = $bagRoot . '/' . $bagName;
        if (is_dir($bagPath)) {
            throw new \RuntimeException('Bag directory already exists: ' . $bagPath);
        }

        $payload = $this->collectPayload($ioId, $includeDescendants);

        // 1. Persist preservation_package (status=building) so we have an id for object rows + events.
        $packageId = (int) DB::table('preservation_package')->insertGetId([
            'uuid'                  => (string) Str::uuid(),
            'name'                  => $bagName,
            'description'           => $opts['description'] ?? null,
            'package_type'          => 'AIP',
            'package_format'        => 'bagit',
            'bagit_version'         => self::VERSION,
            'manifest_algorithm'    => $algorithm,
            'status'                => 'building',
            'object_count'          => 0,
            'total_size'            => 0,
            'originator'            => $opts['originator'] ?? ($opts['source_organization'] ?? null),
            'submission_agreement'  => $opts['submission_agreement'] ?? null,
            'information_object_id' => $ioId,
            'source_path'           => $bagPath,
            'export_path'           => null,
            'created_by'            => $createdBy,
            'metadata'              => json_encode([
                'opts' => array_diff_key($opts, ['bag_root' => true]),
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $this->logPackageEvent($packageId, 'creation', 'bag build started: ' . $bagName, 'success', $createdBy);

        try {
            // 2. Materialise the bag directory.
            mkdir($bagPath . '/data', 0775, true);
            $manifestLines    = [];
            $copiedFiles      = 0;
            $totalSize        = 0;

            foreach ($payload as $row) {
                $absoluteSource = $this->resolveAbsolutePath($row->path, $row->name);
                if (! $absoluteSource || ! is_file($absoluteSource)) {
                    $this->logPackageEvent($packageId, 'fixityCheck',
                        'payload file missing on disk for digital_object #' . $row->id . ': ' . ($row->path ?? '') . ($row->name ?? ''),
                        'failure', $createdBy);
                    continue;
                }

                // Place under data/<repo>/<hash-prefix>/<file>  — preserves provenance from the storage tree.
                $relativeInBag = $this->payloadRelativePath($row);
                $destInBag     = $bagPath . '/data/' . $relativeInBag;
                $destDir       = dirname($destInBag);
                if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
                    throw new \RuntimeException('Could not create payload subdir: ' . $destDir);
                }
                if (! @copy($absoluteSource, $destInBag)) {
                    throw new \RuntimeException('Could not copy ' . $absoluteSource . ' → ' . $destInBag);
                }

                $checksum = hash_file($algorithm, $destInBag);
                if ($checksum === false) {
                    throw new \RuntimeException('Checksum failed for ' . $destInBag);
                }
                $size = filesize($destInBag) ?: 0;
                $totalSize += $size;
                $copiedFiles++;

                $manifestLines[] = $checksum . '  data/' . $relativeInBag;

                DB::table('preservation_package_object')->insert([
                    'package_id'         => $packageId,
                    'digital_object_id'  => (int) $row->id,
                    'relative_path'      => 'data/' . $relativeInBag,
                    'file_name'          => basename($destInBag),
                    'file_size'          => $size,
                    'checksum_algorithm' => $algorithm,
                    'checksum_value'     => $checksum,
                    'mime_type'          => $row->mime_type ?? null,
                    'object_role'        => 'payload',
                    'sequence'           => $copiedFiles,
                ]);
            }

            // 3. Tag files (bagit.txt, bag-info.txt, manifest).
            $manifestFile = "manifest-{$algorithm}.txt";
            file_put_contents($bagPath . '/' . $manifestFile, implode("\n", $manifestLines) . "\n");

            file_put_contents($bagPath . '/bagit.txt',
                "BagIt-Version: " . self::VERSION . "\n" .
                "Tag-File-Character-Encoding: " . self::TAG_ENCODING . "\n"
            );

            $payloadOxum = $totalSize . '.' . $copiedFiles;
            $bagInfo = [
                'Source-Organization'           => $opts['source_organization'] ?? 'Heratio',
                'Organization-Address'          => $opts['organization_address'] ?? null,
                'Contact-Name'                  => $createdBy,
                'External-Identifier'           => 'heratio:io:' . $ioId,
                'External-Description'          => $opts['description'] ?? null,
                'Bagging-Date'                  => Carbon::now()->toDateString(),
                'Bag-Size'                      => $this->humanSize($totalSize),
                'Payload-Oxum'                  => $payloadOxum,
                'Bag-Software-Agent'            => 'Heratio BagItService (AGPL-3.0)',
                'Heratio-Information-Object-Id' => (string) $ioId,
                'Heratio-Package-Uuid'          => DB::table('preservation_package')->where('id', $packageId)->value('uuid'),
            ];
            file_put_contents($bagPath . '/bag-info.txt', $this->renderBagInfo($bagInfo));

            // 4. Tag manifest — checksums of the tag files written above.
            $tagManifestLines = [];
            foreach (['bagit.txt', 'bag-info.txt', $manifestFile] as $tagFile) {
                $tagManifestLines[] = hash_file($algorithm, $bagPath . '/' . $tagFile) . '  ' . $tagFile;
            }
            $tagManifestFile = "tagmanifest-{$algorithm}.txt";
            file_put_contents($bagPath . '/' . $tagManifestFile, implode("\n", $tagManifestLines) . "\n");

            foreach (['bagit.txt' => 'tag', 'bag-info.txt' => 'tag', $manifestFile => 'manifest', $tagManifestFile => 'tagmanifest'] as $tf => $role) {
                DB::table('preservation_package_object')->insert([
                    'package_id'         => $packageId,
                    'digital_object_id'  => 0,
                    'relative_path'      => $tf,
                    'file_name'          => $tf,
                    'file_size'          => filesize($bagPath . '/' . $tf) ?: 0,
                    'checksum_algorithm' => $algorithm,
                    'checksum_value'     => hash_file($algorithm, $bagPath . '/' . $tf),
                    'object_role'        => $role,
                    'sequence'           => 0,
                ]);
            }

            // 5. Optional zip export.
            $exportPath = null;
            if ($shouldZip) {
                $exportPath = $bagPath . '.zip';
                $this->zipBag($bagPath, $exportPath);
                $packageChecksum = hash_file($algorithm, $exportPath);
            } else {
                $packageChecksum = null;
            }

            DB::table('preservation_package')->where('id', $packageId)->update([
                'status'           => 'built',
                'object_count'     => $copiedFiles,
                'total_size'       => $totalSize,
                'export_path'      => $exportPath,
                'package_checksum' => $packageChecksum,
                'built_at'         => Carbon::now(),
            ]);

            $this->logPackageEvent($packageId, 'capture', "bagged {$copiedFiles} files, {$totalSize} bytes, oxum={$payloadOxum}", 'success', $createdBy);
            if ($exportPath) {
                $this->logPackageEvent($packageId, 'replication', 'zip exported to ' . $exportPath, 'success', $createdBy);
            }

            return [
                'package_id'    => $packageId,
                'status'        => 'built',
                'bag_path'      => $bagPath,
                'export_path'   => $exportPath,
                'payload_files' => $copiedFiles,
                'total_size'    => $totalSize,
            ];
        } catch (Throwable $e) {
            DB::table('preservation_package')->where('id', $packageId)->update([
                'status' => 'failed',
            ]);
            $this->logPackageEvent($packageId, 'creation', 'bag build failed: ' . mb_substr($e->getMessage(), 0, 1000), 'failure', $createdBy);
            Log::error('preservation: BagIt build failed', ['io_id' => $ioId, 'package_id' => $packageId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Re-verify every payload + tag file checksum on disk against preservation_package_object rows.
     *
     * @return array{package_id:int, total:int, ok:int, mismatched:int, missing:int, errors:array}
     */
    public function validatePackage(int $packageId): array
    {
        $package = DB::table('preservation_package')->where('id', $packageId)->first();
        if (! $package) {
            throw new \RuntimeException('Package not found: ' . $packageId);
        }
        $bagPath = (string) $package->source_path;
        if (! is_dir($bagPath)) {
            throw new \RuntimeException('Bag directory missing: ' . $bagPath);
        }

        $algorithm = $package->manifest_algorithm ?: 'sha256';
        $rows = DB::table('preservation_package_object')->where('package_id', $packageId)->get();

        $ok = $mismatched = $missing = 0;
        $errors = [];
        foreach ($rows as $row) {
            $abs = $bagPath . '/' . $row->relative_path;
            if (! is_file($abs)) {
                $missing++;
                $errors[] = ['file' => $row->relative_path, 'reason' => 'missing'];
                continue;
            }
            $sum = hash_file($algorithm, $abs);
            if ($sum !== $row->checksum_value) {
                $mismatched++;
                $errors[] = ['file' => $row->relative_path, 'reason' => 'checksum_mismatch', 'expected' => $row->checksum_value, 'actual' => $sum];
                continue;
            }
            $ok++;
        }

        $total      = $rows->count();
        $allClean   = ($mismatched + $missing) === 0;
        $endStatus  = $allClean ? 'success' : 'failure';

        DB::table('preservation_package')->where('id', $packageId)->update([
            'status'       => $allClean ? 'validated' : ($package->status === 'built' ? 'corrupted' : $package->status),
            'validated_at' => Carbon::now(),
        ]);

        $this->logPackageEvent(
            $packageId,
            'fixityCheck',
            "validate: ok={$ok} mismatched={$mismatched} missing={$missing}",
            $endStatus,
            null
        );

        return [
            'package_id' => $packageId,
            'total'      => $total,
            'ok'         => $ok,
            'mismatched' => $mismatched,
            'missing'    => $missing,
            'errors'     => $errors,
        ];
    }

    /* -------------------------------------------------------------------- */
    /*  Internals                                                            */
    /* -------------------------------------------------------------------- */

    /**
     * Walk the IO subtree (using lft/rgt nested-set) and collect every digital_object row.
     *
     * @return array<int, object>  digital_object rows (id, path, name, mime_type, byte_size, object_id)
     */
    protected function collectPayload(int $ioId, bool $includeDescendants): array
    {
        if ($includeDescendants) {
            $root = DB::table('information_object')->where('id', $ioId)->select('lft', 'rgt')->first();
            if (! $root) {
                return [];
            }
            $ioIds = DB::table('information_object')
                ->whereBetween('lft', [(int) $root->lft, (int) $root->rgt])
                ->pluck('id')
                ->all();
        } else {
            $ioIds = [$ioId];
        }
        if (empty($ioIds)) {
            return [];
        }
        return DB::table('digital_object')
            ->whereIn('object_id', $ioIds)
            ->select('id', 'path', 'name', 'mime_type', 'byte_size', 'object_id')
            ->orderBy('id')
            ->get()
            ->all();
    }

    protected function resolveAbsolutePath(?string $path, ?string $name): ?string
    {
        if ($path === null || $name === null) {
            return null;
        }
        $rel = ltrim($path, '/') . $name;
        $candidates = [
            rtrim((string) config('heratio.uploads_path', config('heratio.storage_path') . '/uploads'), '/') . '/' . $rel,
            '/usr/share/nginx/heratio/uploads/' . $rel,
        ];
        // path columns sometimes look like /uploads/r/<hash>/file — try public/uploads as well
        if (str_starts_with($path, '/uploads/')) {
            $candidates[] = '/usr/share/nginx/heratio/public' . $path . $name;
        }
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }
        return null;
    }

    protected function payloadRelativePath(object $row): string
    {
        // Preserve a stable, traceable path. Use object_id + filename — avoids collisions if
        // a fonds has multiple files with the same name in different sub-records.
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $row->name) ?: 'file';
        return "io-{$row->object_id}/do-{$row->id}/{$safeName}";
    }

    protected function zipBag(string $bagPath, string $exportPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($exportPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not open zip for write: ' . $exportPath);
        }
        $bagName = basename($bagPath);
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($bagPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $fileinfo) {
            $absolute = $fileinfo->getPathname();
            $relative = $bagName . '/' . ltrim(substr($absolute, strlen($bagPath)), '/');
            if ($fileinfo->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($absolute, $relative);
            }
        }
        $zip->close();
    }

    protected function renderBagInfo(array $values): string
    {
        $out = '';
        foreach ($values as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            // RFC 8493 line folding: keep simple — values are short.
            $out .= $k . ': ' . str_replace(["\r", "\n"], ' ', (string) $v) . "\n";
        }
        return $out;
    }

    protected function validateAlgorithm(string $alg): string
    {
        $alg = strtolower($alg);
        if (! in_array($alg, ['sha256', 'sha512', 'sha1', 'md5'], true)) {
            throw new \InvalidArgumentException('Unsupported algorithm: ' . $alg);
        }
        return $alg;
    }

    protected function safeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?: 'bag';
        return mb_substr($name, 0, 100);
    }

    protected function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        $count = $bytes < 1024 ? (int) $bytes : round($size, 2);
        return $count . ' ' . $units[$i];
    }

    protected function logPackageEvent(int $packageId, string $type, string $detail, string $outcome, ?string $createdBy): void
    {
        try {
            DB::table('preservation_package_event')->insert([
                'package_id'    => $packageId,
                'event_type'    => $type,
                'event_detail'  => $detail,
                'event_outcome' => $outcome,
                'agent_type'    => 'system',
                'agent_value'   => 'heratio-bagit',
                'created_by'    => $createdBy,
            ]);
        } catch (Throwable $e) {
            Log::warning('preservation: package event log failed', ['error' => $e->getMessage()]);
        }
    }
}
