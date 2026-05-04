<?php

/**
 * PreservationService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Preservation Service
 *
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgPreservationPlugin/lib/PreservationService.php
 * and /usr/share/nginx/archive/atom-ahg-plugins/ahgPreservationPlugin/modules/preservation/actions/actions.class.php
 *
 * Provides access to AIP packages and PREMIS objects for information objects.
 *
 * DB tables:
 *   aip: id, type_id, uuid, filename, size_on_disk, digital_object_count, created_at, part_of
 *   premis_object: id, information_object_id, puid, filename, last_modified, date_ingested, size, mime_type
 */
class PreservationService
{
    /**
     * Get preservation packages linked to an information object.
     *
     * Modern AHG packages live in `preservation_package` and link to IOs via
     * `preservation_package_object` -> `digital_object` -> `information_object`.
     * Legacy AtoM AIPs live in the `aip` table linked via `aip.part_of` or
     * `digital_object.id`. We merge both stores so the index view sees all
     * packages regardless of which pipeline created them. Column shape is
     * normalised to `{ id, package_type, name, status, total_size, size_on_disk,
     * created_at }` so the index blade's `package_type` filter works.
     */
    public function getAipsForObject(int $objectId): Collection
    {
        $combined = collect();

        // Modern AHG packages via preservation_package_object -> digital_object
        try {
            $modern = DB::table('preservation_package as p')
                ->join('preservation_package_object as ppo', 'ppo.package_id', '=', 'p.id')
                ->join('digital_object as do', 'do.id', '=', 'ppo.digital_object_id')
                ->where('do.object_id', $objectId)
                ->select(
                    'p.id', 'p.uuid', 'p.name', 'p.package_type', 'p.status',
                    'p.total_size', 'p.created_at', 'p.export_path'
                )
                ->distinct()
                ->orderByDesc('p.created_at')
                ->get()
                ->map(function ($r) {
                    // Normalise package_type to uppercase so the view's
                    // ->where('package_type', 'DIP') filter is case-insensitive.
                    $r->package_type = strtoupper((string) $r->package_type);
                    // Map to size_on_disk for the view's $aips->sum('size_on_disk').
                    $r->size_on_disk = (int) ($r->total_size ?? 0);
                    return $r;
                });
            $combined = $combined->concat($modern);
        } catch (\Illuminate\Database\QueryException $e) {
            // preservation_package may not exist on legacy installs; fall through.
        }

        // Legacy AtoM AIPs via aip.part_of (and digital_object fallback)
        try {
            $legacy = DB::table('aip')
                ->where('part_of', $objectId)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($r) {
                    // Legacy aip rows are AIPs by definition; tag them so the
                    // view's package_type filter assigns them to the AIP bucket.
                    if (!isset($r->package_type)) $r->package_type = 'AIP';
                    if (!isset($r->size_on_disk) && isset($r->size)) $r->size_on_disk = (int) $r->size;
                    return $r;
                });
            $combined = $combined->concat($legacy);
        } catch (\Illuminate\Database\QueryException $e) {
            // aip table not present on Heratio-only installs.
        }

        return $combined->sortByDesc('created_at')->values();
    }

    /**
     * Load a single preservation_package row with normalised columns
     * (size_on_disk + uppercased package_type) so the view shapes are
     * consistent with whatever getAipsForObject returns.
     */
    public function getPackage(int $packageId): ?object
    {
        try {
            $p = DB::table('preservation_package')->where('id', $packageId)->first();
            if (!$p) return null;
            $p->package_type = strtoupper((string) $p->package_type);
            $p->size_on_disk = (int) ($p->total_size ?? 0);
            return $p;
        } catch (\Illuminate\Database\QueryException $e) {
            return null;
        }
    }

    /**
     * List files in a preservation_package as preservation_package_object
     * rows joined to digital_object for friendly display data.
     */
    public function getPackageFiles(int $packageId): Collection
    {
        try {
            return DB::table('preservation_package_object as ppo')
                ->leftJoin('digital_object as do', 'do.id', '=', 'ppo.digital_object_id')
                ->where('ppo.package_id', $packageId)
                ->select(
                    'ppo.id', 'ppo.relative_path', 'ppo.file_name', 'ppo.file_size',
                    'ppo.mime_type', 'ppo.checksum_algorithm', 'ppo.checksum_value',
                    'ppo.object_role', 'ppo.sequence', 'ppo.added_at',
                    'do.id as digital_object_id', 'do.path as do_path'
                )
                ->orderBy('ppo.sequence')
                ->orderBy('ppo.id')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Audit-trail events for a preservation_package, newest first.
     */
    public function getPackageEvents(int $packageId): Collection
    {
        try {
            return DB::table('preservation_package_event')
                ->where('package_id', $packageId)
                ->orderByDesc('event_datetime')
                ->orderByDesc('id')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Update mutable fields on a preservation_package. Restricts to a
     * whitelist (name / description / status) so caller cannot poke at
     * uuid / package_type / checksum / object_count.
     */
    public function updatePackage(int $packageId, array $data): bool
    {
        try {
            $update = [];
            foreach (['name', 'description', 'status'] as $f) {
                if (array_key_exists($f, $data)) {
                    $v = $data[$f];
                    if (is_string($v)) $v = trim($v);
                    $update[$f] = $v === '' ? null : $v;
                }
            }
            if (empty($update)) return false;
            $update['updated_at'] = now();
            return DB::table('preservation_package')->where('id', $packageId)->update($update) > 0;
        } catch (\Illuminate\Database\QueryException $e) {
            return false;
        }
    }

    /**
     * BagIt-export an existing preservation_package row.
     *
     * Reads the linked preservation_package_object rows + their digital_object
     * source files, copies them into a BagIt 1.0 layout under
     *   storage_path('app/preservation/<uuid>/')
     * with bagit.txt + bag-info.txt + manifest-sha256.txt + tagmanifest-sha256.txt,
     * zips the bag to .../<uuid>.zip, then updates the package row's status to
     * 'exported' and stamps export_path + package_checksum + exported_at.
     *
     * Self-contained (does not depend on ahg-ingest's OaisPackagerService) so
     * the existing package row is exported in place rather than rebuilt.
     *
     * @return array{ok:bool, message:string, export_path?:string, checksum?:string}
     */
    public function exportPackage(int $packageId): array
    {
        $pkg = $this->getPackage($packageId);
        if (!$pkg) {
            return ['ok' => false, 'message' => 'Package not found.'];
        }
        if (($pkg->status ?? '') === 'exported' && !empty($pkg->export_path) && is_file($pkg->export_path)) {
            return ['ok' => true, 'message' => 'Already exported.', 'export_path' => $pkg->export_path];
        }

        $files = $this->getPackageFiles($packageId);
        if ($files->isEmpty()) {
            return ['ok' => false, 'message' => 'Package has no linked files to export.'];
        }

        $exportRoot = storage_path('app/preservation');
        if (!is_dir($exportRoot)) @mkdir($exportRoot, 0775, true);

        $workDir = $exportRoot . '/' . $pkg->uuid;
        $dataDir = $workDir . '/data';
        if (is_dir($workDir)) {
            $this->rmTree($workDir);
        }
        if (!@mkdir($dataDir, 0775, true)) {
            return ['ok' => false, 'message' => 'Cannot create work directory: ' . $workDir];
        }

        $manifestLines = [];
        $totalSize = 0;
        foreach ($files as $f) {
            $src = $this->resolveSourcePath($f);
            if (!$src || !is_file($src)) {
                Log::warning('[preservation] missing source file for package export', [
                    'package_id' => $packageId, 'file' => $f->relative_path,
                ]);
                continue;
            }
            $rel = ltrim((string) ($f->relative_path ?? $f->file_name), '/');
            // Force every file under data/ inside the bag.
            if (!str_starts_with($rel, 'data/')) {
                $rel = 'data/' . $rel;
            }
            $dest = $workDir . '/' . $rel;
            $destDir = dirname($dest);
            if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
            if (!@copy($src, $dest)) {
                continue;
            }
            $sha = hash_file('sha256', $dest) ?: '';
            $manifestLines[] = $sha . '  ' . $rel;
            $totalSize += filesize($dest) ?: 0;
        }

        if (empty($manifestLines)) {
            $this->rmTree($workDir);
            return ['ok' => false, 'message' => 'No source files could be located on disk for this package.'];
        }

        // BagIt declaration + bag-info + payload manifest + tag manifest.
        file_put_contents($workDir . '/bagit.txt',
            "BagIt-Version: 1.0\nTag-File-Character-Encoding: UTF-8\n");
        $bagInfo  = "Source-Organization: Heratio\n";
        $bagInfo .= "Bagging-Date: " . now()->toDateString() . "\n";
        $bagInfo .= "External-Identifier: " . $pkg->uuid . "\n";
        $bagInfo .= "External-Description: " . str_replace(["\r", "\n"], ' ', (string) $pkg->name) . "\n";
        $bagInfo .= "Package-Type: " . strtoupper((string) $pkg->package_type) . "\n";
        $bagInfo .= "Bag-Size: " . $totalSize . " bytes\n";
        $bagInfo .= "Payload-Oxum: {$totalSize}." . count($manifestLines) . "\n";
        file_put_contents($workDir . '/bag-info.txt', $bagInfo);

        file_put_contents($workDir . '/manifest-sha256.txt', implode("\n", $manifestLines) . "\n");
        $tagManifest = [];
        foreach (['bagit.txt', 'bag-info.txt', 'manifest-sha256.txt'] as $tag) {
            $tagPath = $workDir . '/' . $tag;
            if (is_file($tagPath)) {
                $tagManifest[] = hash_file('sha256', $tagPath) . '  ' . $tag;
            }
        }
        file_put_contents($workDir . '/tagmanifest-sha256.txt', implode("\n", $tagManifest) . "\n");

        $exportPath = $exportRoot . '/' . $pkg->uuid . '.zip';
        if (file_exists($exportPath)) @unlink($exportPath);
        $zip = new \ZipArchive();
        if ($zip->open($exportPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'message' => 'Cannot open zip for writing: ' . $exportPath];
        }
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($workDir, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($rii as $item) {
            if ($item->isDir()) continue;
            $absolute = $item->getPathname();
            $relativeInZip = $pkg->uuid . '/' . substr($absolute, strlen($workDir) + 1);
            $zip->addFile($absolute, $relativeInZip);
        }
        $zip->close();
        $checksum = hash_file('sha256', $exportPath) ?: '';

        DB::table('preservation_package')->where('id', $packageId)->update([
            'status'           => 'exported',
            'export_path'      => $exportPath,
            'source_path'      => $workDir,
            'package_checksum' => $checksum,
            'object_count'     => count($manifestLines),
            'total_size'       => $totalSize,
            'updated_at'       => now(),
        ]);

        try {
            DB::table('preservation_package_event')->insert([
                'package_id'     => $packageId,
                'event_type'     => 'export',
                'event_outcome'  => 'success',
                'event_detail'   => 'BagIt export written to ' . $exportPath . ' (' . count($manifestLines) . ' files, ' . $totalSize . ' bytes, sha256 ' . substr($checksum, 0, 12) . '...).',
                'event_datetime' => now(),
                'agent_type'     => 'system',
                'agent_value'    => 'PreservationService::exportPackage',
            ]);
        } catch (\Throwable $e) { /* event table optional */ }

        return [
            'ok'           => true,
            'message'      => 'Exported ' . count($manifestLines) . ' file(s) into ' . basename($exportPath) . '.',
            'export_path'  => $exportPath,
            'checksum'     => $checksum,
        ];
    }

    /**
     * Resolve a preservation_package_object row's source file on disk.
     * Tries the linked digital_object's path first, falls back to the
     * heratio uploads root when path is relative.
     */
    private function resolveSourcePath(object $row): ?string
    {
        if (!empty($row->do_path) && !empty($row->file_name)) {
            $candidate = rtrim((string) $row->do_path, '/') . '/' . $row->file_name;
            if (is_file($candidate)) return $candidate;
        }
        // Fallback: search the configured uploads path for the file_name.
        try {
            $uploads = config('heratio.uploads_path', config('heratio.storage_path'));
            if (is_string($uploads) && $uploads !== '' && !empty($row->file_name)) {
                $globbed = glob($uploads . '/**/' . $row->file_name);
                if (!empty($globbed) && is_file($globbed[0])) return $globbed[0];
            }
        } catch (\Throwable $e) {}
        return null;
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $item) {
            if ($item->isDir()) @rmdir($item->getPathname());
            else @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * Get PREMIS objects for an information object.
     */
    public function getPremisObjects(int $objectId): Collection
    {
        try {
            return DB::table('premis_object')
                ->where('information_object_id', $objectId)
                ->orderByDesc('date_ingested')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get a single AIP with full details.
     */
    public function getAipDetails(int $aipId): ?object
    {
        try {
            $aip = DB::table('aip')
                ->where('id', $aipId)
                ->first();

            if (!$aip) {
                return null;
            }

            // Resolve type name if type_id exists
            if ($aip->type_id) {
                $aip->type_name = DB::table('term_i18n')
                    ->where('id', $aip->type_id)
                    ->where('culture', app()->getLocale())
                    ->value('name');
            }

            // Get related PREMIS objects if this AIP is linked to an IO
            if ($aip->part_of) {
                $aip->premis_objects = DB::table('premis_object')
                    ->where('information_object_id', $aip->part_of)
                    ->get();
            }

            return $aip;
        } catch (\Illuminate\Database\QueryException $e) {
            return null;
        }
    }
}
