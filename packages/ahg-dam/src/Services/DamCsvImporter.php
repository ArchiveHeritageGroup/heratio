<?php

/**
 * DamCsvImporter - DAM (Dublin Core/IPTC) CSV importer for Heratio.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgDam\Services;

use AhgCore\Services\Import\SectorCsvImporter;
use Illuminate\Support\Facades\DB;

/**
 * DAM sector CSV importer following Dublin Core/IPTC standards.
 *
 * Migrated from AtoM's DamCsvImportCommand in ahgDataMigrationPlugin.
 * Maps CSV columns to Dublin Core/IPTC fields and creates information_object records
 * with events for creation dates and photographer/creator actors.
 * Handles GPS coordinates and EXIF camera metadata.
 * Saves DAM-specific metadata to dam_metadata table.
 */
class DamCsvImporter extends SectorCsvImporter
{
    // Note type for GPS coordinates
    const NOTE_TYPE_GENERAL = 127;

    public function getSectorName(): string
    {
        return 'dam';
    }

    public function getStandard(): string
    {
        return 'Dublin Core/IPTC';
    }

    public function getColumnMap(): array
    {
        return [
            'legacy_id'            => 'legacyId',
            'parent_id'            => 'parentId',
            'asset_id'             => 'identifier',
            'file_name'            => 'filename',
            'name'                 => 'filename',
            'alternative_title'    => 'alternativeTitle',
            'alt_title'            => 'alternativeTitle',
            'author'               => 'creator',
            'photographer'         => 'creator',
            'artist'               => 'creator',
            'date_created'         => 'dateCreated',
            'creation_date'        => 'dateCreated',
            'date'                 => 'dateCreated',
            'date_captured'        => 'dateCaptured',
            'capture_date'         => 'dateCaptured',
            'date_taken'           => 'dateCaptured',
            'date_modified'        => 'dateModified',
            'modified_date'        => 'dateModified',
            'scope_and_content'    => 'description',
            'summary'              => 'description',
            'file_format'          => 'format',
            'format_mime_type'     => 'formatMimeType',
            'mime_type'            => 'formatMimeType',
            'file_size'            => 'fileSize',
            'size'                 => 'fileSize',
            'image_size'           => 'dimensions',
            'length'               => 'duration',
            'media_type'           => 'type',
            'copyright'            => 'rights',
            'rights_holder'        => 'rightsHolder',
            'copyright_holder'     => 'rightsHolder',
            'usage_terms'          => 'usageTerms',
            'credit_line'          => 'credit',
            'tags'                 => 'keywords',
            'gps_latitude'         => 'gpsLatitude',
            'latitude'             => 'gpsLatitude',
            'gps_longitude'        => 'gpsLongitude',
            'longitude'            => 'gpsLongitude',
            'gps_altitude'         => 'gpsAltitude',
            'altitude'             => 'gpsAltitude',
            'location_created'     => 'locationCreated',
            'location'             => 'locationCreated',
            'location_shown'       => 'locationShown',
            'camera_model'         => 'cameraModel',
            'model'                => 'cameraModel',
            'camera_make'          => 'cameraMake',
            'make'                 => 'cameraMake',
            'focal_length'         => 'focalLength',
            'exposure_time'        => 'exposureTime',
            'shutter_speed'        => 'exposureTime',
            'f_stop'               => 'aperture',
            'iso_speed'            => 'iso',
            'color_space'          => 'colorSpace',
            'dpi'                  => 'resolution',
            'subjects'             => 'subjectAccessPoints',
            'places'               => 'placeAccessPoints',
            'names'                => 'nameAccessPoints',
            'digital_object_path'  => 'digitalObjectPath',
            'file_path'            => 'digitalObjectPath',
            'path'                 => 'digitalObjectPath',
            'digital_object_uri'   => 'digitalObjectURI',
            'url'                  => 'digitalObjectURI',
        ];
    }

    public function getRequiredColumns(): array
    {
        return ['identifier', 'title'];
    }

    protected function getI18nFieldMap(array $data): array
    {
        return array_filter([
            'title'             => $data['title'] ?? $data['filename'] ?? null,
            'extent_and_medium' => $this->formatExtent($data),
            'scope_and_content' => $data['description'] ?? $data['caption'] ?? null,
            'access_conditions' => $data['rights'] ?? $data['license'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
    }

    protected function formatExtent(array $data): ?string
    {
        $parts = [];

        if (!empty($data['format'])) {
            $parts[] = $data['format'];
        }

        if (!empty($data['formatMimeType'])) {
            $parts[] = '(' . $data['formatMimeType'] . ')';
        }

        if (!empty($data['fileSize'])) {
            $parts[] = $this->formatFileSize($data['fileSize']);
        }

        if (!empty($data['dimensions'])) {
            $parts[] = $data['dimensions'];
        }

        if (!empty($data['resolution'])) {
            $parts[] = $data['resolution'];
        }

        if (!empty($data['duration'])) {
            $parts[] = 'Duration: ' . $data['duration'];
        }

        return !empty($parts) ? implode('; ', $parts) : null;
    }

    protected function formatFileSize($bytes): string
    {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    protected function createEvents(int $objectId, array $data): void
    {
        $creator = $data['creator'] ?? null;
        $dateCreated = $data['dateCreated'] ?? $data['dateCaptured'] ?? null;

        if (empty($creator) && empty($dateCreated)) {
            return;
        }

        $actorId = null;
        if (!empty($creator)) {
            $actorId = $this->findOrCreateActor($creator);
        }

        $this->createEvent(
            $objectId,
            self::EVENT_TYPE_CREATION,
            $dateCreated,
            null,
            null,
            $actorId
        );

        // GPS coordinate location as place access point or note
        $this->createLocationAccessPoint($objectId, $data);
    }

    protected function createLocationAccessPoint(int $objectId, array $data): void
    {
        $location = $data['locationCreated'] ?? $data['locationShown'] ?? null;
        $lat = $data['gpsLatitude'] ?? null;
        $lon = $data['gpsLongitude'] ?? null;

        if (!empty($location)) {
            $this->createTermRelations($objectId, $location, self::TAXONOMY_PLACE);
        } elseif (!empty($lat) && !empty($lon)) {
            // Store GPS as a note
            $noteObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitNote',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('note')->insert([
                'id' => $noteObjectId,
                'object_id' => $objectId,
                'type_id' => self::NOTE_TYPE_GENERAL,
                'scope' => 'QubitInformationObject',
                'source_culture' => $this->culture,
            ]);

            DB::table('note_i18n')->insert([
                'id' => $noteObjectId,
                'culture' => $this->culture,
                'content' => sprintf('GPS Coordinates: %s, %s', $lat, $lon),
            ]);
        }
    }

    protected function saveSectorMetadata(int $objectId, array $data): void
    {
        $metadata = array_filter([
            'filename'    => $data['filename'] ?? null,
            'format'      => $data['format'] ?? null,
            'mime_type'   => $data['formatMimeType'] ?? null,
            'file_size'   => $data['fileSize'] ?? null,
            'dimensions'  => $data['dimensions'] ?? null,
            'resolution'  => $data['resolution'] ?? null,
            'color_space' => $data['colorSpace'] ?? null,
            'rights'      => $data['rights'] ?? null,
            'license'     => $data['license'] ?? null,
            'gps_latitude'  => $data['gpsLatitude'] ?? null,
            'gps_longitude' => $data['gpsLongitude'] ?? null,
            'camera_model'  => $data['cameraModel'] ?? null,
            'camera_make'   => $data['cameraMake'] ?? null,
            'caption'       => $data['caption'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if (!empty($metadata)) {
            $this->upsertMetadata('dam_metadata', 'information_object_id', $objectId, $metadata);
        }
    }
}
