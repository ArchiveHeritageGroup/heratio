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
                ->orderByDesc('created_at')
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
