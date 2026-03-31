<?php

/**
 * MetadataExtractionController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgMetadataExtraction\Controllers;

use AhgMetadataExtraction\Services\MetadataExtractionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Metadata Extraction Controller.
 *
 * Provides a dashboard for viewing tool availability, browsing digital objects,
 * extracting metadata, and managing extracted metadata properties.
 *
 * Ported from ahgMetadataExtractionPlugin (metadataExtractionActions).
 */
class MetadataExtractionController extends Controller
{
    public function __construct(
        private readonly MetadataExtractionService $service
    ) {}

    /**
     * Dashboard: tool availability, statistics, list of digital objects with extraction status.
     */
    public function index(Request $request)
    {
        $culture = app()->getLocale();

        // Tool availability
        $exifToolAvailable = $this->service->isExifToolAvailable();
        $ffprobeAvailable  = $this->service->isFfprobeAvailable();
        $pdfinfoAvailable  = $this->service->isPdfinfoAvailable();

        // Versions
        $exifToolVersion = $this->service->getExifToolVersion();
        $ffprobeVersion  = $this->service->getFfprobeVersion();

        // Statistics
        $stats = $this->service->getStatistics();

        // Filter parameters
        $filterMimeType  = $request->get('mime_type', '');
        $filterExtracted = $request->get('extracted', '');
        $page            = max(1, (int) $request->get('page', 1));
        $limit           = 25;
        $offset          = ($page - 1) * $limit;

        // Build digital objects query
        $query = DB::table('digital_object as do')
            ->join('information_object as io', 'do.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->select(
                'do.id',
                'do.name',
                'do.path',
                'do.mime_type',
                'do.byte_size',
                'do.information_object_id',
                'ioi.title as record_title'
            )
            ->whereNotNull('do.path');

        if (!empty($filterMimeType)) {
            $query->where('do.mime_type', 'LIKE', $filterMimeType . '%');
        }

        $totalCount = $query->count();

        $digitalObjects = $query
            ->orderBy('do.id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Attach metadata count per object
        foreach ($digitalObjects as $obj) {
            $obj->metadata_count = DB::table('property')
                ->where('object_id', $obj->id)
                ->where('scope', 'metadata_extraction')
                ->count();
        }

        // Post-query filter for extracted status
        if ($filterExtracted === 'yes') {
            $digitalObjects = $digitalObjects->filter(fn ($obj) => $obj->metadata_count > 0)->values();
        } elseif ($filterExtracted === 'no') {
            $digitalObjects = $digitalObjects->filter(fn ($obj) => $obj->metadata_count == 0)->values();
        }

        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $limit) : 1;

        // Available MIME types for filter dropdown
        $mimeTypes = DB::table('digital_object')
            ->select('mime_type')
            ->distinct()
            ->whereNotNull('mime_type')
            ->orderBy('mime_type')
            ->pluck('mime_type')
            ->toArray();

        return view('ahg-metadata-extraction::index', compact(
            'exifToolAvailable',
            'ffprobeAvailable',
            'pdfinfoAvailable',
            'exifToolVersion',
            'ffprobeVersion',
            'stats',
            'digitalObjects',
            'totalCount',
            'totalPages',
            'page',
            'limit',
            'filterMimeType',
            'filterExtracted',
            'mimeTypes',
        ));
    }

    /**
     * View metadata for a specific digital object.
     */
    public function view(int $id)
    {
        $culture = app()->getLocale();

        $digitalObject = DB::table('digital_object as do')
            ->join('information_object as io', 'do.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->where('do.id', $id)
            ->select(
                'do.id',
                'do.name',
                'do.path',
                'do.mime_type',
                'do.byte_size',
                'do.information_object_id',
                'ioi.title as record_title',
                'io.slug'
            )
            ->first();

        if (!$digitalObject) {
            abort(404, 'Digital object not found');
        }

        $metadata = $this->service->getMetadata($id);

        // Group metadata by category prefix (e.g. "EXIF:ImageWidth" -> group "EXIF")
        $groupedMetadata = [];
        foreach ($metadata as $meta) {
            $parts     = explode(':', $meta->name, 2);
            $group     = count($parts) > 1 ? $parts[0] : 'General';
            $fieldName = count($parts) > 1 ? $parts[1] : $meta->name;

            if (!isset($groupedMetadata[$group])) {
                $groupedMetadata[$group] = [];
            }

            // Retrieve i18n value
            $i18nValue = DB::table('property_i18n')
                ->where('id', $meta->id)
                ->value('value');

            $groupedMetadata[$group][] = (object) [
                'name'      => $fieldName,
                'full_name' => $meta->name,
                'value'     => $i18nValue ?? $meta->name,
            ];
        }

        ksort($groupedMetadata);

        return view('ahg-metadata-extraction::view', compact(
            'digitalObject',
            'metadata',
            'groupedMetadata',
        ));
    }

    /**
     * Extract metadata from a digital object (AJAX).
     */
    public function extract(Request $request, int $id)
    {
        try {
            $metadata = $this->service->extractFromDigitalObject($id, app()->getLocale());

            $flat = [];
            array_walk_recursive($metadata, function ($v, $k) use (&$flat) {
                if (!is_array($v)) {
                    $flat[$k] = $v;
                }
            });

            $count = DB::table('property')
                ->where('object_id', $id)
                ->where('scope', 'metadata_extraction')
                ->count();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Extracted {$count} metadata fields",
                    'count'   => $count,
                ]);
            }

            return redirect()->route('metadata-extraction.view', $id)
                ->with('success', "Extracted {$count} metadata fields.");
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return redirect()->route('metadata-extraction.index')
                ->with('error', 'Extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete metadata for a digital object (AJAX).
     */
    public function delete(Request $request, int $id)
    {
        $this->service->deleteMetadata($id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Metadata deleted',
            ]);
        }

        return redirect()->route('metadata-extraction.view', $id)
            ->with('success', 'Metadata deleted.');
    }

    /**
     * Batch extract metadata for objects without existing extraction.
     */
    public function batchExtract()
    {
        if (!$this->service->isExifToolAvailable()) {
            return redirect()->route('metadata-extraction.index')
                ->with('error', 'ExifTool is not installed on this system');
        }

        $result = $this->service->batchExtract(50, app()->getLocale());

        if ($result['remaining'] > 0) {
            $msg = "Processed {$result['processed']} files ({$result['errors']} errors). "
                 . "{$result['remaining']} remaining - run again to continue.";
        } else {
            $msg = "Batch extraction complete. Processed {$result['processed']} files ({$result['errors']} errors).";
        }

        return redirect()->route('metadata-extraction.index')
            ->with('success', $msg);
    }

    /**
     * Legacy extract: ID passed in request body instead of URL.
     */
    public function extractLegacy(Request $request)
    {
        $id = (int) $request->input('id');
        abort_unless($id, 400, 'Missing id parameter.');

        return $this->extract($request, $id);
    }

    /**
     * Legacy delete: ID passed in request body instead of URL.
     */
    public function deleteLegacy(Request $request)
    {
        $id = (int) $request->input('id');
        abort_unless($id, 400, 'Missing id parameter.');

        return $this->delete($request, $id);
    }

    /**
     * Status page: tool versions and extraction statistics.
     */
    public function status()
    {
        $exifToolAvailable = $this->service->isExifToolAvailable();
        $ffprobeAvailable  = $this->service->isFfprobeAvailable();
        $pdfinfoAvailable  = $this->service->isPdfinfoAvailable();

        $exifToolVersion = $this->service->getExifToolVersion();
        $ffprobeVersion  = $this->service->getFfprobeVersion();

        $stats = $this->service->getStatistics();

        $supportedTypes = [
            'image/jpeg', 'image/png', 'image/tiff', 'image/gif', 'image/bmp', 'image/webp',
            'application/pdf', 'video/mp4', 'video/x-msvideo', 'audio/mpeg',
        ];

        return view('ahg-metadata-extraction::status', compact(
            'exifToolAvailable',
            'ffprobeAvailable',
            'pdfinfoAvailable',
            'exifToolVersion',
            'ffprobeVersion',
            'stats',
            'supportedTypes',
        ));
    }
}
