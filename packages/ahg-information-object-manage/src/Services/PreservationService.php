<?php

declare(strict_types=1);

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
     * Get AIPs linked to an information object.
     *
     * AIP records are linked to information objects via the aip.part_of field
     * or via the relation table. We check both paths.
     */
    public function getAipsForObject(int $objectId): Collection
    {
        try {
            // AIP table uses part_of to reference the information object
            // or object_id if available. Check both.
            $aips = DB::table('aip')
                ->where('part_of', $objectId)
                ->orderByDesc('created_at')
                ->get();

            if ($aips->isEmpty()) {
                // Fallback: try via digital_object -> information_object linkage
                $aips = DB::table('aip')
                    ->join('digital_object as do', function ($j) use ($objectId) {
                        $j->on('do.id', '=', DB::raw('aip.id'))
                            ->where('do.object_id', $objectId);
                    })
                    ->select('aip.*')
                    ->orderByDesc('aip.created_at')
                    ->get();
            }

            return $aips;
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
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
