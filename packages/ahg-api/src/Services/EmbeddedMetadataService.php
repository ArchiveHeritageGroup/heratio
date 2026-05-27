<?php

/**
 * EmbeddedMetadataService - assemble the REST `embedded_metadata` block
 * for a digital object across the three sidecar tables:
 *   - digital_object_metadata  (EXIF / generic exiftool extraction)
 *   - dam_iptc_metadata        (IPTC IIM + IPTC4XMPCore on the parent IO)
 *   - media_metadata           (ffprobe / EXIF audio+video, consolidated JSON)
 *
 * Heratio Issue #747. Used by both the v1 DigitalObjectApiController and the
 * v2 DescriptionController (plus the standalone /api/v2/digital-object/{id}/embedded-metadata
 * endpoint) - it never returns null fields, only the keys that actually have a
 * value. JSON blobs (raw_metadata, contributors_json, consolidated_metadata)
 * are decoded best-effort: malformed payloads degrade to empty arrays rather
 * than throwing.
 *
 * ODRL gating: when the digital object's parent information object has an
 * active odrl:use prohibition for the caller, the entire block is suppressed
 * (returns null). Caller is expected to surface the response without the
 * embedded_metadata key when null comes back.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

declare(strict_types=1);

namespace AhgApi\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmbeddedMetadataService
{
    /**
     * EXIF columns we surface from digital_object_metadata. Map = api_key => db_column.
     * Tag names follow the ExifTool / Adobe XMP camel-case conventions so an
     * integrator can copy the keys verbatim into a third-party EXIF library.
     */
    private const EXIF_COLUMNS = [
        'Title' => 'title',
        'Creator' => 'creator',
        'Description' => 'description',
        'Keywords' => 'keywords',
        'DateTimeOriginal' => 'date_created',
        'ImageWidth' => 'image_width',
        'ImageHeight' => 'image_height',
        'Make' => 'camera_make',
        'Model' => 'camera_model',
        'GPSLatitude' => 'gps_latitude',
        'GPSLongitude' => 'gps_longitude',
        'GPSAltitude' => 'gps_altitude',
        'PageCount' => 'page_count',
        'WordCount' => 'word_count',
        'Author' => 'author',
        'Application' => 'application',
        'Artist' => 'artist',
        'Album' => 'album',
        'TrackNumber' => 'track_number',
        'Genre' => 'genre',
        'Year' => 'year',
        'Duration' => 'duration_formatted',
        'VideoCodec' => 'video_codec',
        'AudioCodec' => 'audio_codec',
        'Resolution' => 'resolution',
        'FrameRate' => 'frame_rate',
        'Bitrate' => 'bitrate',
        'SampleRate' => 'sample_rate',
        'Channels' => 'channels',
    ];

    /**
     * IPTC columns we surface from dam_iptc_metadata. Keys are IPTC IIM /
     * IPTC4XMPCore canonical names; values are the database columns we
     * already populate from exiftool / dam-ingest.
     */
    private const IPTC_COLUMNS = [
        'By-line' => 'creator',
        'By-lineTitle' => 'creator_job_title',
        'Headline' => 'headline',
        'Caption-Abstract' => 'caption',
        'Keywords' => 'keywords',
        'SubjectReference' => 'iptc_subject_code',
        'IntellectualGenre' => 'intellectual_genre',
        'Genre' => 'genre',
        'Title' => 'title',
        'DateCreated' => 'date_created',
        'City' => 'city',
        'Province-State' => 'state_province',
        'Country-PrimaryLocationName' => 'country',
        'Country-PrimaryLocationCode' => 'country_code',
        'Sub-location' => 'sublocation',
        'JobID' => 'job_id',
        'SpecialInstructions' => 'instructions',
        'CreditLine' => 'credit_line',
        'Source' => 'source',
        'CopyrightNotice' => 'copyright_notice',
        'RightsUsageTerms' => 'rights_usage_terms',
        'LicenseType' => 'license_type',
        'LicenseURL' => 'license_url',
        'PersonInImage' => 'persons_shown',
        'GPSLatitude' => 'gps_latitude',
        'GPSLongitude' => 'gps_longitude',
        'GPSAltitude' => 'gps_altitude',
        'ColorSpace' => 'color_space',
        'Orientation' => 'orientation',
    ];

    /**
     * Build the embedded_metadata block for a single digital_object.
     *
     * Returns:
     *   - array{exif: array, iptc: array, xmp: array} on success
     *   - [] when no metadata exists for the digital object at all
     *   - null when ODRL gates the response
     */
    public function forDigitalObject(int $digitalObjectId, ?int $researcherId = null): ?array
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first(['id', 'object_id']);
        if (! $do) {
            return [];
        }

        if (! $this->odrlPermits((int) $do->object_id, $researcherId)) {
            return null;
        }

        $exif = $this->loadExif($digitalObjectId);
        $iptc = $this->loadIptc((int) $do->object_id);
        $xmp = $this->loadXmp($digitalObjectId);

        if (empty($exif) && empty($iptc) && empty($xmp)) {
            return [];
        }

        return [
            'exif' => $exif,
            'iptc' => $iptc,
            'xmp' => $xmp,
        ];
    }

    /**
     * Map first-digital-object lookups to embedded metadata for an entire
     * information object - used by the v2 description show endpoint when
     * `?include=embedded_metadata` is set.
     */
    public function forInformationObject(int $informationObjectId, ?int $researcherId = null): ?array
    {
        if (! $this->odrlPermits($informationObjectId, $researcherId)) {
            return null;
        }

        $do = DB::table('digital_object')
            ->where('object_id', $informationObjectId)
            ->orderByRaw('CASE WHEN usage_id = 166 THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first(['id']);

        if (! $do) {
            return [];
        }

        $exif = $this->loadExif((int) $do->id);
        $iptc = $this->loadIptc($informationObjectId);
        $xmp = $this->loadXmp((int) $do->id);

        if (empty($exif) && empty($iptc) && empty($xmp)) {
            return [];
        }

        return [
            'exif' => $exif,
            'iptc' => $iptc,
            'xmp' => $xmp,
        ];
    }

    /**
     * Pull EXIF rows from digital_object_metadata + media_metadata.
     * Both tables key off digital_object_id; if both exist, the columns
     * are merged with digital_object_metadata winning on conflict
     * (it's the canonical exiftool extraction; media_metadata is ffprobe).
     */
    private function loadExif(int $digitalObjectId): array
    {
        $exif = [];

        if ($this->hasTable('digital_object_metadata')) {
            $row = DB::table('digital_object_metadata')
                ->where('digital_object_id', $digitalObjectId)
                ->first();

            if ($row) {
                foreach (self::EXIF_COLUMNS as $apiKey => $dbCol) {
                    $val = $row->{$dbCol} ?? null;
                    if ($val !== null && $val !== '') {
                        $exif[$apiKey] = $this->stringifyScalar($val);
                    }
                }
            }
        }

        if ($this->hasTable('media_metadata')) {
            $row = DB::table('media_metadata')
                ->where('digital_object_id', $digitalObjectId)
                ->first();

            if ($row) {
                $mediaMap = [
                    'Make' => 'make',
                    'Model' => 'model',
                    'Software' => 'software',
                    'Title' => 'title',
                    'Artist' => 'artist',
                    'Album' => 'album',
                    'Genre' => 'genre',
                    'Year' => 'year',
                    'Copyright' => 'copyright',
                    'Comment' => 'comment',
                    'GPSCoordinates' => 'gps_coordinates',
                    'Format' => 'format',
                    'Duration' => 'duration',
                    'Bitrate' => 'bitrate',
                    'AudioCodec' => 'audio_codec',
                    'AudioSampleRate' => 'audio_sample_rate',
                    'AudioChannels' => 'audio_channels',
                    'VideoCodec' => 'video_codec',
                    'VideoWidth' => 'video_width',
                    'VideoHeight' => 'video_height',
                    'VideoFrameRate' => 'video_frame_rate',
                    'VideoAspectRatio' => 'video_aspect_ratio',
                ];
                foreach ($mediaMap as $apiKey => $dbCol) {
                    if (array_key_exists($apiKey, $exif)) {
                        continue;
                    }
                    $val = $row->{$dbCol} ?? null;
                    if ($val !== null && $val !== '') {
                        $exif[$apiKey] = $this->stringifyScalar($val);
                    }
                }
            }
        }

        return $exif;
    }

    /**
     * Pull IPTC rows from dam_iptc_metadata. Note this table keys on
     * object_id (information_object id) not digital_object_id, so the
     * caller passes the parent IO id.
     */
    private function loadIptc(int $informationObjectId): array
    {
        if (! $this->hasTable('dam_iptc_metadata')) {
            return [];
        }

        $row = DB::table('dam_iptc_metadata')
            ->where('object_id', $informationObjectId)
            ->first();

        if (! $row) {
            return [];
        }

        $iptc = [];
        foreach (self::IPTC_COLUMNS as $apiKey => $dbCol) {
            $val = $row->{$dbCol} ?? null;
            if ($val !== null && $val !== '') {
                $iptc[$apiKey] = $this->stringifyScalar($val);
            }
        }

        $contributors = $this->decodeJson($row->contributors_json ?? null);
        if (! empty($contributors)) {
            $iptc['Contributors'] = $contributors;
        }

        return $iptc;
    }

    /**
     * Pull XMP rows from the raw JSON sidecars. We surface a flat dc:* /
     * xmp:* / xmpRights:* projection. Both `digital_object_metadata.raw_metadata`
     * and `media_metadata.consolidated_metadata` are inspected for an `xmp`
     * sub-document; either source can win, with digital_object_metadata first.
     */
    private function loadXmp(int $digitalObjectId): array
    {
        $xmp = [];

        if ($this->hasTable('digital_object_metadata')) {
            $raw = DB::table('digital_object_metadata')
                ->where('digital_object_id', $digitalObjectId)
                ->value('raw_metadata');
            $xmp = array_merge($xmp, $this->extractXmpFromRaw($raw));
        }

        if ($this->hasTable('media_metadata')) {
            $consolidated = DB::table('media_metadata')
                ->where('digital_object_id', $digitalObjectId)
                ->value('consolidated_metadata');
            foreach ($this->extractXmpFromRaw($consolidated) as $k => $v) {
                if (! array_key_exists($k, $xmp)) {
                    $xmp[$k] = $v;
                }
            }
        }

        return $xmp;
    }

    /**
     * Walk a decoded raw_metadata payload and lift any namespaced keys
     * (dc:*, xmp:*, xmpRights:*, photoshop:*, Iptc4xmpCore:*) into a flat
     * array. We also accept an explicit `xmp` sub-object as a shorthand.
     */
    private function extractXmpFromRaw($raw): array
    {
        $decoded = $this->decodeJson($raw);
        if (empty($decoded)) {
            return [];
        }

        $out = [];

        if (isset($decoded['xmp']) && is_array($decoded['xmp'])) {
            foreach ($decoded['xmp'] as $k => $v) {
                $out[(string) $k] = $this->stringifyScalar($v);
            }
        }

        $nsPrefixes = ['dc:', 'xmp:', 'xmpRights:', 'xmpMM:', 'photoshop:', 'Iptc4xmpCore:', 'Iptc4xmpExt:'];
        foreach ($decoded as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            foreach ($nsPrefixes as $prefix) {
                if (str_starts_with($k, $prefix)) {
                    $out[$k] = $this->stringifyScalar($v);
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * ODRL gate. Allow when:
     *   - the OdrlService is not installed (no research package), or
     *   - the service reports the action is permitted for the caller.
     * We check the `odrl:use` action against the information_object target.
     */
    private function odrlPermits(int $informationObjectId, ?int $researcherId): bool
    {
        $serviceClass = '\\AhgResearch\\Services\\OdrlService';
        if (! class_exists($serviceClass)) {
            return true;
        }

        try {
            /** @var object $svc */
            $svc = app($serviceClass);
            if (! method_exists($svc, 'isPermitted')) {
                return true;
            }

            return (bool) $svc->isPermitted('informationobject', $informationObjectId, $researcherId, 'odrl:use');
        } catch (\Throwable $e) {
            // Fail-open: the audit log will catch the trace and we don't want
            // a missing research_rights_policy table to break the read API.
            return true;
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Best-effort JSON decode. Returns [] for null / empty / malformed
     * payloads. Accepts both string and already-decoded array input.
     */
    private function decodeJson($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Coerce database scalars to API-shape values. Numeric strings are kept
     * as strings (GPS, ISO speed) to preserve precision; arrays/objects pass
     * through; booleans render as 0/1 strings to stay JSON-stable.
     */
    private function stringifyScalar($value)
    {
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
