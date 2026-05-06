<?php

/**
 * MetadataExtractionService - Service for Heratio
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



namespace AhgMetadataExtraction\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Metadata Extraction Service.
 *
 * Extracts embedded metadata from digital objects using exiftool, ffprobe,
 * and native PHP functions. Supports images (EXIF/IPTC/XMP), PDFs, video, and audio.
 *
 * Ported from ahgMetadataExtractionPlugin / ahgUniversalMetadataExtractor.
 */
class MetadataExtractionService
{
    /** MIME type to category mapping */
    private const MIME_CATEGORIES = [
        'image/jpeg'      => 'image',
        'image/png'       => 'image',
        'image/tiff'      => 'image',
        'image/webp'      => 'image',
        'image/gif'       => 'image',
        'image/bmp'       => 'image',
        'application/pdf' => 'pdf',
        'video/mp4'       => 'video',
        'video/webm'      => 'video',
        'video/ogg'       => 'video',
        'video/quicktime' => 'video',
        'video/x-msvideo' => 'video',
        'video/x-matroska'=> 'video',
        'audio/mpeg'      => 'audio',
        'audio/mp3'       => 'audio',
        'audio/wav'       => 'audio',
        'audio/ogg'       => 'audio',
        'audio/flac'      => 'audio',
        'audio/aac'       => 'audio',
        'audio/x-m4a'     => 'audio',
    ];

    /** Extension to category fallback */
    private const EXT_CATEGORIES = [
        'jpg'  => 'image', 'jpeg' => 'image', 'png' => 'image',
        'tif'  => 'image', 'tiff' => 'image', 'webp' => 'image',
        'gif'  => 'image', 'bmp'  => 'image',
        'pdf'  => 'pdf',
        'mp4'  => 'video', 'webm' => 'video', 'ogv' => 'video',
        'mov'  => 'video', 'avi'  => 'video', 'mkv' => 'video',
        'mp3'  => 'audio', 'wav'  => 'audio', 'ogg' => 'audio',
        'flac' => 'audio', 'aac'  => 'audio', 'm4a' => 'audio',
    ];

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Extract all available metadata from a file.
     *
     * Returns an associative array of metadata found, delegating to the
     * appropriate extractor(s) based on file type.
     */
    public function extract(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $mimeType = $this->detectMimeType($filePath);
        $category = $this->determineCategory($filePath, $mimeType);

        $metadata = [
            'file' => [
                'path'      => $filePath,
                'name'      => basename($filePath),
                'size'      => filesize($filePath),
                'mime_type' => $mimeType,
                'category'  => $category,
                'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
                'modified'  => date('Y-m-d H:i:s', filemtime($filePath)),
            ],
        ];

        switch ($category) {
            case 'image':
                $metadata['exif'] = $this->extractExif($filePath);
                $metadata['iptc'] = $this->extractIptc($filePath);
                $metadata['xmp']  = $this->extractXmp($filePath);
                if (!empty($metadata['exif'])) {
                    $metadata['gps'] = $this->extractGpsFromExif($metadata['exif']);
                }
                break;

            case 'pdf':
                $metadata['pdf'] = $this->extractPdfInfo($filePath);
                break;

            case 'video':
                $metadata['video'] = $this->extractVideoInfo($filePath);
                break;

            case 'audio':
                $metadata['audio'] = $this->extractAudioInfo($filePath);
                break;
        }

        // If exiftool is available, always merge its comprehensive output
        if ($this->isExifToolAvailable()) {
            $metadata['exiftool'] = $this->runExifTool($filePath);
        }

        return $metadata;
    }

    /**
     * Extract EXIF from images using PHP exif_read_data().
     */
    public function extractExif(string $filePath): array
    {
        if (!function_exists('exif_read_data')) {
            Log::warning('MetadataExtraction: PHP EXIF extension not available');
            return [];
        }

        $supportedTypes = [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM];
        $imageType = @exif_imagetype($filePath);

        if (!in_array($imageType, $supportedTypes)) {
            return [];
        }

        try {
            $exif = @exif_read_data($filePath, 'ANY_TAG', true);

            if (!$exif || !is_array($exif)) {
                return [];
            }

            // Flatten nested sections, sanitise binary data
            $flat = [];
            foreach ($exif as $section => $data) {
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                            $value = '[Binary Data]';
                        }
                        $flat[$key] = $value;
                    }
                } else {
                    $flat[$section] = $data;
                }
            }

            return $flat;
        } catch (\Throwable $e) {
            Log::warning("MetadataExtraction: EXIF read failed: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Extract IPTC data from images.
     */
    public function extractIptc(string $filePath): array
    {
        $info = [];
        if (!@getimagesize($filePath, $info)) {
            return [];
        }

        if (!isset($info['APP13'])) {
            return [];
        }

        $iptcRaw = @iptcparse($info['APP13']);
        if (!$iptcRaw || !is_array($iptcRaw)) {
            return [];
        }

        $fieldMap = [
            '2#005' => 'object_name',
            '2#010' => 'urgency',
            '2#015' => 'category',
            '2#020' => 'supplemental_category',
            '2#025' => 'keywords',
            '2#040' => 'special_instructions',
            '2#055' => 'date_created',
            '2#060' => 'time_created',
            '2#062' => 'digital_creation_date',
            '2#063' => 'digital_creation_time',
            '2#065' => 'originating_program',
            '2#070' => 'program_version',
            '2#080' => 'byline',
            '2#085' => 'byline_title',
            '2#090' => 'city',
            '2#092' => 'sub_location',
            '2#095' => 'province_state',
            '2#100' => 'country_code',
            '2#101' => 'country',
            '2#103' => 'original_transmission_reference',
            '2#105' => 'headline',
            '2#110' => 'credit',
            '2#115' => 'source',
            '2#116' => 'copyright',
            '2#118' => 'contact',
            '2#120' => 'caption',
            '2#122' => 'writer',
        ];

        $parsed = [];
        foreach ($iptcRaw as $code => $values) {
            $fieldName = $fieldMap[$code] ?? $code;
            if (count($values) === 1) {
                $parsed[$fieldName] = $this->cleanString($values[0]);
            } else {
                $parsed[$fieldName] = array_map([$this, 'cleanString'], $values);
            }
        }

        return $parsed;
    }

    /**
     * Extract XMP data via reading the XML packet from the file,
     * or via exiftool if available.
     */
    public function extractXmp(string $filePath): array
    {
        // Try native XMP parsing first
        $content = @file_get_contents($filePath, false, null, 0, 1024 * 512); // first 512 KB
        if (!$content) {
            return [];
        }

        $start = strpos($content, '<x:xmpmeta');
        if ($start === false) {
            $start = strpos($content, '<?xpacket begin');
        }
        if ($start === false) {
            return [];
        }

        $end = strpos($content, '</x:xmpmeta>', $start);
        if ($end === false) {
            $end = strpos($content, '<?xpacket end', $start);
        }
        if ($end === false) {
            return [];
        }

        $xmpData = substr($content, $start, $end - $start + 15);

        return $this->parseXmpXml($xmpData);
    }

    /**
     * Extract PDF info using pdfinfo command.
     *
     * Returns: Title, Author, Keywords, Creator, Producer, Pages, Page size.
     */
    public function extractPdfInfo(string $filePath): array
    {
        $result = [];

        // Try pdfinfo first
        if ($this->isCommandAvailable('pdfinfo')) {
            $command = sprintf('pdfinfo %s 2>&1', escapeshellarg($filePath));
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                foreach ($output as $line) {
                    if (preg_match('/^([^:]+):\s*(.+)$/', $line, $m)) {
                        $key = trim($m[1]);
                        $val = trim($m[2]);
                        $result[strtolower(str_replace(' ', '_', $key))] = $val;
                    }
                }
            }
        }

        // Fallback: manual header parsing
        if (empty($result)) {
            $result = $this->extractPdfHeaderManual($filePath);
        }

        // Normalise key names
        $normalised = [];
        $keyMap = [
            'title'           => 'title',
            'author'          => 'author',
            'subject'         => 'subject',
            'keywords'        => 'keywords',
            'creator'         => 'creator',
            'producer'        => 'producer',
            'creationdate'    => 'creation_date',
            'creation_date'   => 'creation_date',
            'moddate'         => 'modification_date',
            'mod_date'        => 'modification_date',
            'pages'           => 'pages',
            'page_size'       => 'page_size',
            'file_size'       => 'file_size',
            'pdf_version'     => 'pdf_version',
            'encrypted'       => 'encrypted',
            'page_rot'        => 'page_rotation',
            'tagged'          => 'tagged',
            'optimized'       => 'optimized',
        ];

        foreach ($result as $k => $v) {
            $normKey = $keyMap[strtolower($k)] ?? strtolower(str_replace(' ', '_', $k));
            $normalised[$normKey] = $v;
        }

        return $normalised;
    }

    /**
     * Extract video information using ffprobe.
     *
     * Returns: duration, codec, resolution, frame rate, bitrate.
     */
    public function extractVideoInfo(string $filePath): array
    {
        if (!$this->isFfprobeAvailable()) {
            Log::warning('MetadataExtraction: ffprobe not available');
            return [];
        }

        $probe = $this->runFfprobe($filePath);
        if (empty($probe)) {
            return [];
        }

        $result = [];

        // Format-level info
        $format = $probe['format'] ?? [];
        $result['duration']    = isset($format['duration']) ? round((float) $format['duration'], 2) : null;
        $result['bitrate']     = isset($format['bit_rate']) ? (int) $format['bit_rate'] : null;
        $result['format_name'] = $format['format_name'] ?? null;
        $result['format_long'] = $format['format_long_name'] ?? null;
        $result['size']        = isset($format['size']) ? (int) $format['size'] : null;

        // Format tags
        $tags = $format['tags'] ?? [];
        $result['title']         = $tags['title'] ?? $tags['TITLE'] ?? null;
        $result['artist']        = $tags['artist'] ?? $tags['ARTIST'] ?? null;
        $result['creation_time'] = $tags['creation_time'] ?? null;
        $result['encoder']       = $tags['encoder'] ?? $tags['ENCODER'] ?? null;

        // Video stream info
        $streams = $probe['streams'] ?? [];
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $result['video_codec']      = $stream['codec_name'] ?? null;
                $result['video_codec_long'] = $stream['codec_long_name'] ?? null;
                $result['width']            = $stream['width'] ?? null;
                $result['height']           = $stream['height'] ?? null;
                $result['resolution']       = isset($stream['width'], $stream['height'])
                    ? $stream['width'] . 'x' . $stream['height']
                    : null;
                $result['frame_rate']       = $this->parseFrameRate($stream['r_frame_rate'] ?? null);
                $result['pixel_format']     = $stream['pix_fmt'] ?? null;
                $result['video_bitrate']    = isset($stream['bit_rate']) ? (int) $stream['bit_rate'] : null;
                break;
            }
        }

        // Audio stream info within the video
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                $result['audio_codec']       = $stream['codec_name'] ?? null;
                $result['audio_sample_rate'] = isset($stream['sample_rate']) ? (int) $stream['sample_rate'] : null;
                $result['audio_channels']    = $stream['channels'] ?? null;
                $result['audio_bitrate']     = isset($stream['bit_rate']) ? (int) $stream['bit_rate'] : null;
                break;
            }
        }

        return array_filter($result, fn ($v) => $v !== null);
    }

    /**
     * Extract audio information using ffprobe.
     *
     * Returns: duration, bitrate, sample rate, channels, codec.
     */
    public function extractAudioInfo(string $filePath): array
    {
        if (!$this->isFfprobeAvailable()) {
            Log::warning('MetadataExtraction: ffprobe not available');
            return [];
        }

        $probe = $this->runFfprobe($filePath);
        if (empty($probe)) {
            return [];
        }

        $result = [];

        // Format-level info
        $format = $probe['format'] ?? [];
        $result['duration']    = isset($format['duration']) ? round((float) $format['duration'], 2) : null;
        $result['bitrate']     = isset($format['bit_rate']) ? (int) $format['bit_rate'] : null;
        $result['format_name'] = $format['format_name'] ?? null;
        $result['format_long'] = $format['format_long_name'] ?? null;
        $result['size']        = isset($format['size']) ? (int) $format['size'] : null;

        // Tags (ID3, Vorbis, etc.)
        $tags = $format['tags'] ?? [];
        $result['title']   = $tags['title'] ?? $tags['TITLE'] ?? null;
        $result['artist']  = $tags['artist'] ?? $tags['ARTIST'] ?? null;
        $result['album']   = $tags['album'] ?? $tags['ALBUM'] ?? null;
        $result['genre']   = $tags['genre'] ?? $tags['GENRE'] ?? null;
        $result['date']    = $tags['date'] ?? $tags['DATE'] ?? null;
        $result['track']   = $tags['track'] ?? $tags['TRACKNUMBER'] ?? null;
        $result['comment'] = $tags['comment'] ?? $tags['COMMENT'] ?? null;
        $result['encoder'] = $tags['encoder'] ?? $tags['ENCODER'] ?? null;

        // Audio stream info
        $streams = $probe['streams'] ?? [];
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                $result['codec']         = $stream['codec_name'] ?? null;
                $result['codec_long']    = $stream['codec_long_name'] ?? null;
                $result['sample_rate']   = isset($stream['sample_rate']) ? (int) $stream['sample_rate'] : null;
                $result['channels']      = $stream['channels'] ?? null;
                $result['channel_layout'] = $stream['channel_layout'] ?? null;
                $result['bits_per_sample'] = $stream['bits_per_sample'] ?? null;
                $result['stream_bitrate']  = isset($stream['bit_rate']) ? (int) $stream['bit_rate'] : null;
                break;
            }
        }

        return array_filter($result, fn ($v) => $v !== null);
    }

    /**
     * Check if exiftool command is available on the system.
     */
    public function isExifToolAvailable(): bool
    {
        return $this->isCommandAvailable('exiftool');
    }

    /**
     * Check if ffprobe command is available on the system.
     */
    public function isFfprobeAvailable(): bool
    {
        return $this->isCommandAvailable('ffprobe');
    }

    /**
     * Get the version string of exiftool.
     */
    public function getExifToolVersion(): ?string
    {
        if (!$this->isExifToolAvailable()) {
            return null;
        }
        $output = [];
        exec('exiftool -ver 2>&1', $output);
        return $output[0] ?? null;
    }

    /**
     * Get the version string of ffprobe.
     */
    public function getFfprobeVersion(): ?string
    {
        if (!$this->isFfprobeAvailable()) {
            return null;
        }
        $output = [];
        exec('ffprobe -version 2>&1', $output);
        $line = $output[0] ?? '';
        if (preg_match('/ffprobe version (\S+)/', $line, $m)) {
            return $m[1];
        }
        return $line ?: null;
    }

    /**
     * Check if pdfinfo command is available.
     */
    public function isPdfinfoAvailable(): bool
    {
        return $this->isCommandAvailable('pdfinfo');
    }

    // ------------------------------------------------------------------
    // Digital-object helpers (database operations)
    // ------------------------------------------------------------------

    /**
     * Extract metadata for a digital object by ID and store results in property table.
     *
     * @return array The extracted metadata
     */
    public function extractFromDigitalObject(int $digitalObjectId, string $culture = 'en'): array
    {
        $digitalObject = DB::table('digital_object')->where('id', $digitalObjectId)->first();

        if (!$digitalObject) {
            throw new \RuntimeException("Digital object {$digitalObjectId} not found");
        }

        $uploadsPath = config('heratio.uploads_path');
        $filePath = rtrim($uploadsPath, '/') . '/' . ltrim($digitalObject->path, '/');

        if (!file_exists($filePath)) {
            // Try web dir fallback
            $filePath = public_path($digitalObject->path);
        }

        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found for digital object {$digitalObjectId}: {$digitalObject->path}");
        }

        $metadata = $this->extract($filePath);

        // Flatten the nested metadata into key/value pairs for storage
        $flat = $this->flattenMetadata($metadata);

        // Delete old metadata for this object
        $this->deleteMetadata($digitalObjectId);

        // Save new metadata
        foreach ($flat as $key => $value) {
            $this->saveMetadataProperty($digitalObjectId, $key, (string) $value, $culture);
        }

        return $metadata;
    }

    /**
     * Get stored metadata for a digital object.
     */
    public function getMetadata(int $digitalObjectId): \Illuminate\Support\Collection
    {
        return DB::table('property')
            ->where('object_id', $digitalObjectId)
            ->where('scope', 'metadata_extraction')
            ->orderBy('name')
            ->get();
    }

    /**
     * Delete all extracted metadata properties for a digital object.
     */
    public function deleteMetadata(int $digitalObjectId): void
    {
        $properties = DB::table('property')
            ->where('object_id', $digitalObjectId)
            ->where('scope', 'metadata_extraction')
            ->get();

        foreach ($properties as $property) {
            DB::table('property_i18n')->where('id', $property->id)->delete();
            DB::table('property')->where('id', $property->id)->delete();
            DB::table('object')->where('id', $property->id)->delete();
        }
    }

    /**
     * Batch extract metadata for digital objects that have no extraction yet.
     *
     * @return array{processed: int, errors: int, remaining: int}
     */
    public function batchExtract(int $limit = 50, string $culture = 'en'): array
    {
        $digitalObjects = DB::table('digital_object')
            ->whereNotNull('path')
            ->whereNotIn('id', function ($query) {
                $query->select('object_id')
                    ->from('property')
                    ->where('scope', 'metadata_extraction')
                    ->distinct();
            })
            ->limit($limit)
            ->get();

        $processed = 0;
        $errors = 0;

        foreach ($digitalObjects as $obj) {
            try {
                $this->extractFromDigitalObject($obj->id, $culture);
                $processed++;
            } catch (\Throwable $e) {
                Log::warning("MetadataExtraction batch: failed for DO {$obj->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $remaining = DB::table('digital_object')
            ->whereNotNull('path')
            ->whereNotIn('id', function ($query) {
                $query->select('object_id')
                    ->from('property')
                    ->where('scope', 'metadata_extraction')
                    ->distinct();
            })
            ->count();

        return compact('processed', 'errors', 'remaining');
    }

    /**
     * Get statistics for the metadata extraction dashboard.
     */
    public function getStatistics(): array
    {
        $totalDigitalObjects = DB::table('digital_object')->whereNotNull('path')->count();

        $objectsWithMetadata = DB::table('property')
            ->where('scope', 'metadata_extraction')
            ->distinct('object_id')
            ->count('object_id');

        $totalMetadataFields = DB::table('property')
            ->where('scope', 'metadata_extraction')
            ->count();

        $mimeTypeBreakdown = DB::table('digital_object')
            ->select('mime_type', DB::raw('count(*) as count'))
            ->whereNotNull('mime_type')
            ->groupBy('mime_type')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'total_digital_objects'  => $totalDigitalObjects,
            'objects_with_metadata'  => $objectsWithMetadata,
            'total_metadata_fields'  => $totalMetadataFields,
            'mime_type_breakdown'    => $mimeTypeBreakdown,
        ];
    }

    // ------------------------------------------------------------------
    // Normalisation + per-sector apply (consumes map_*_<sector> settings)
    // ------------------------------------------------------------------

    /** Normalised-dict keys that the per-sector mapping addresses. */
    public const NORMALISED_FIELDS = ['title', 'creator', 'date', 'description', 'copyright', 'keywords', 'technical', 'gps'];

    /**
     * AtoM-legacy key → real Heratio column on the per-sector typed table.
     * The values stored in ahg_settings.map_<field>_<sector> are AtoM field
     * labels (productionPerson, copyrightNotice, etc.). For DAM most of them
     * land directly in dam_iptc_metadata. For IO/Museum many of them are
     * relations (subject access points, name access points) which this
     * first pass does NOT handle - they're enumerated in the return value
     * with a 'relation' marker so the caller knows to skip them.
     */
    private const SECTOR_COLUMN_RESOLVER = [
        'dam' => [
            'creator'         => ['type' => 'column', 'table' => 'dam_iptc_metadata', 'column' => 'creator'],
            'caption'         => ['type' => 'column', 'table' => 'dam_iptc_metadata', 'column' => 'caption'],
            'keywords'        => ['type' => 'column', 'table' => 'dam_iptc_metadata', 'column' => 'keywords'],
            'dateCreated'     => ['type' => 'column', 'table' => 'dam_iptc_metadata', 'column' => 'date_created'],
            'date_created'    => ['type' => 'column', 'table' => 'dam_iptc_metadata', 'column' => 'date_created'],
            'copyrightNotice' => ['type' => 'column', 'table' => 'dam_iptc_metadata', 'column' => 'copyright_notice'],
            'gpsLocation'     => ['type' => 'gps_components', 'table' => 'dam_iptc_metadata'],
            'technicalInfo'   => ['type' => 'skip', 'reason' => 'no dedicated column on dam_iptc_metadata; raw values land in property table via extractFromDigitalObject'],
            'title'           => ['type' => 'i18n', 'table' => 'information_object_i18n', 'column' => 'title'],
        ],
        'isad' => [
            'title'                => ['type' => 'i18n', 'table' => 'information_object_i18n', 'column' => 'title'],
            'scopeAndContent'      => ['type' => 'i18n', 'table' => 'information_object_i18n', 'column' => 'scope_and_content'],
            'accessConditions'     => ['type' => 'i18n', 'table' => 'information_object_i18n', 'column' => 'access_conditions'],
            'physicalCharacteristics' => ['type' => 'i18n', 'table' => 'information_object_i18n', 'column' => 'physical_characteristics'],
            // Phase 3 (#86): match-only-skip strategy. We split EXIF strings
            // on common separators (semicolon, comma) and look up each token
            // by exact match against term_i18n / actor_i18n. Found = relation
            // inserted. Not found = surfaced in skipped[] with the unmatched
            // token list, NEVER auto-created (would contaminate authority
            // files with arbitrary EXIF artist names).
            'nameAccessPoints'     => ['type' => 'actor_relation',  'relation_type_id' => 161],
            'subjectAccessPoints'  => ['type' => 'term_relation',   'taxonomy_id' => 35],
            'placeAccessPoints'    => ['type' => 'term_relation',   'taxonomy_id' => 42],
            // Phase 4 (#86): dedupe-then-insert. If the IO already has a
            // creation event we skip; otherwise insert with the parsed date
            // (ISO YYYY-MM-DD or partial). type_id=111 = 'Creation'.
            'creationEvent'        => ['type' => 'creation_event',  'type_id' => 111],
        ],
        'museum' => [
            'title'                  => ['type' => 'i18n', 'table' => 'information_object_i18n', 'column' => 'title'],
            'briefDescription'       => ['type' => 'i18n', 'table' => 'information_object_i18n', 'column' => 'scope_and_content'],
            'productionDate'         => ['type' => 'column', 'table' => 'museum_metadata', 'column' => 'creation_date_earliest'],
            'productionPerson'       => ['type' => 'column', 'table' => 'museum_metadata', 'column' => 'creator_identity'],
            'rightsNotes'            => ['type' => 'column', 'table' => 'museum_metadata', 'column' => 'rights_remarks'],
            'objectCategory'         => ['type' => 'column', 'table' => 'museum_metadata', 'column' => 'object_category'],
            'technicalDescription'   => ['type' => 'column', 'table' => 'museum_metadata', 'column' => 'techniques'],
            'fieldCollectionPlace'   => ['type' => 'column', 'table' => 'museum_metadata', 'column' => 'discovery_place'],
        ],
        'library' => [
            'title' => ['type' => 'i18n', 'table' => 'information_object_i18n', 'column' => 'title'],
        ],
    ];

    /**
     * Resolve a free-form information_object.source_standard value to one of
     * the four sector keys we support: 'dam', 'museum', 'library', 'isad'.
     * 'isad' is the catch-all default (anything ISAD/DACS/empty).
     */
    public function resolveSector(?string $sourceStandard): string
    {
        $s = strtolower(trim((string) $sourceStandard));
        if ($s === 'dam') return 'dam';
        if ($s === 'library') return 'library';
        if (str_contains($s, 'cco') || str_contains($s, 'museum')) return 'museum';
        return 'isad';
    }

    /**
     * Extract metadata from a file and flatten to a normalised dict suitable
     * for per-sector apply. Returns one of {title, creator, date, description,
     * copyright, keywords, technical, gps} keys (any subset). Honours the
     * meta_extract_iptc / meta_extract_xmp / meta_extract_gps toggles - if a
     * channel is disabled in settings, its values do NOT appear in the dict.
     *
     * Source priority for each field is XMP > IPTC > EXIF > exiftool catch-all,
     * with first-non-empty winning. EXIF + exiftool are always available; IPTC
     * + XMP are gated by the toggles.
     */
    public function extractNormalised(string $filePath): array
    {
        $raw = $this->extract($filePath);
        $exif = $raw['exif'] ?? [];
        $iptc = $raw['iptc'] ?? [];
        $xmp  = $raw['xmp']  ?? [];
        $pdf  = $raw['pdf']  ?? [];
        $exiftool = $raw['exiftool'] ?? [];

        // Toggles: default values seeded in ahg_settings (false for IPTC/XMP/GPS,
        // i.e. opt-in). Operators flip them on per-instance.
        $iptcEnabled = $this->settingBool('meta_extract_iptc', false);
        $xmpEnabled  = $this->settingBool('meta_extract_xmp', false);
        $gpsEnabled  = $this->settingBool('meta_extract_gps', false);

        if (!$iptcEnabled) $iptc = [];
        if (!$xmpEnabled)  $xmp  = [];

        $first = function (...$values) {
            foreach ($values as $v) {
                if (is_array($v)) {
                    foreach ($v as $vv) if ($vv !== null && $vv !== '') return is_array($vv) ? implode('; ', $vv) : (string) $vv;
                    continue;
                }
                if ($v !== null && $v !== '') return (string) $v;
            }
            return null;
        };

        $normalised = [
            'title'       => $first(
                $xmp['title'] ?? null,
                $iptc['object_name'] ?? null,
                $iptc['headline'] ?? null,
                $pdf['title'] ?? null,
                $exif['ImageDescription'] ?? null,
                $exiftool['XMP-dc:Title'] ?? $exiftool['IPTC:ObjectName'] ?? null
            ),
            'creator'     => $first(
                is_array($xmp['creator'] ?? null) ? $xmp['creator'] : ($xmp['creator'] ?? null),
                $iptc['byline'] ?? null,
                $exif['Artist'] ?? null,
                $pdf['author'] ?? null,
                $exiftool['XMP-dc:Creator'] ?? $exiftool['EXIF:Artist'] ?? $exiftool['IPTC:By-line'] ?? null
            ),
            'date'        => $first(
                $exif['DateTimeOriginal'] ?? null,
                $xmp['date_time_original'] ?? $xmp['create_date'] ?? null,
                $iptc['date_created'] ?? null,
                $pdf['creation_date'] ?? null,
                $exif['DateTime'] ?? null,
                $exiftool['EXIF:DateTimeOriginal'] ?? $exiftool['XMP-xmp:CreateDate'] ?? null
            ),
            'description' => $first(
                $xmp['description'] ?? null,
                $iptc['caption'] ?? null,
                $pdf['subject'] ?? null,
                $exif['UserComment'] ?? null,
                $exiftool['XMP-dc:Description'] ?? $exiftool['IPTC:Caption-Abstract'] ?? null
            ),
            'copyright'   => $first(
                $xmp['rights'] ?? null,
                $iptc['copyright'] ?? null,
                $exif['Copyright'] ?? null,
                $exiftool['XMP-dc:Rights'] ?? $exiftool['EXIF:Copyright'] ?? null
            ),
            'keywords'    => $first(
                is_array($xmp['keywords'] ?? null) ? implode(', ', $xmp['keywords']) : null,
                is_array($iptc['keywords'] ?? null) ? implode(', ', $iptc['keywords']) : ($iptc['keywords'] ?? null),
                $pdf['keywords'] ?? null,
                $exiftool['XMP-dc:Subject'] ?? $exiftool['IPTC:Keywords'] ?? null
            ),
            'technical'   => $first(
                $exif['Make'] ?? null,
                $exif['Model'] ?? null,
                $exiftool['EXIF:Make'] ?? $exiftool['EXIF:Model'] ?? null
            ),
        ];

        if ($gpsEnabled && !empty($raw['gps'])) {
            $normalised['gps'] = $raw['gps']; // {latitude, longitude, decimal[, altitude]}
        }

        // Strip nulls so the apply step's overwrite checks don't see false positives.
        return array_filter($normalised, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Resolve the per-sector mapping from ahg_settings.map_<field>_<sector>.
     * Returns ['title' => 'title', 'creator' => 'creator', ...] keyed by
     * normalised-field name with values being the AtoM-legacy field labels
     * the operator chose on /admin/ahgSettings/metadata. Falls back to the
     * shipped defaults when an operator hasn't customised.
     */
    public function getMappingForSector(string $sector): array
    {
        $sector = strtolower($sector);
        $keys = [];
        foreach (self::NORMALISED_FIELDS as $f) {
            $keys[$f] = "map_{$f}_{$sector}";
        }
        $keys['gps'] = "map_gps_{$sector}";

        $rows = DB::table('ahg_settings')
            ->whereIn('setting_key', array_values($keys))
            ->pluck('setting_value', 'setting_key')
            ->all();

        $out = [];
        foreach ($keys as $field => $key) {
            $v = $rows[$key] ?? null;
            if ($v !== null && $v !== '') $out[$field] = $v;
        }
        return $out;
    }

    /**
     * Apply a normalised metadata dict to a sector's typed columns.
     *
     * Returns ['written' => [field=>column], 'skipped' => [field=>reason]]
     * so the caller can audit-trail what landed where. Honours
     * meta_overwrite_existing: when false, only writes columns that are
     * currently NULL or empty.
     *
     * First-pass scope: handles 'column' (direct DB column), 'i18n' (the
     * source-culture row of information_object_i18n), and 'gps_components'
     * (split decimal lat/lon to city/state/country isn't really possible -
     * we store the decimal in dam_iptc_metadata.sublocation as the closest
     * fit since there's no dedicated coords column). Skips 'relation' /
     * 'event' targets - those need term/actor/event inserts that are out of
     * scope for the first pass and are reported in 'skipped' so #86 can
     * track follow-up work.
     */
    public function applyToSector(int $objectId, string $sector, array $normalised, array $options = []): array
    {
        $sector = strtolower($sector);
        $resolver = self::SECTOR_COLUMN_RESOLVER[$sector] ?? self::SECTOR_COLUMN_RESOLVER['isad'];
        $mapping  = $this->getMappingForSector($sector);
        $overwrite = $options['overwrite'] ?? $this->settingBool('meta_overwrite_existing', false);
        $culture   = $options['culture'] ?? app()->getLocale();

        $written = [];
        $skipped = [];

        foreach ($normalised as $field => $value) {
            $atomKey = $mapping[$field] ?? null;
            if (!$atomKey) {
                $skipped[$field] = 'no mapping configured for sector';
                continue;
            }
            $rule = $resolver[$atomKey] ?? null;
            if (!$rule) {
                $skipped[$field] = "atom-key '{$atomKey}' not resolvable for sector '{$sector}'";
                continue;
            }
            if (in_array($rule['type'], ['skip'], true)) {
                $skipped[$field] = $rule['reason'] ?? "type={$rule['type']}";
                continue;
            }

            $strValue = is_array($value) ? json_encode($value) : (string) $value;
            $strValue = trim($strValue);
            if ($strValue === '') continue;
            // Truncate to a reasonable length so a stray giant XMP blob can't
            // overflow varchar columns and break the upload transaction.
            if (mb_strlen($strValue) > 4000) $strValue = mb_substr($strValue, 0, 4000);

            try {
                if ($rule['type'] === 'column') {
                    $row = DB::table($rule['table'])->where('object_id', $objectId)->first();
                    if (!$row) {
                        $skipped[$field] = "no {$rule['table']} row for object_id={$objectId}";
                        continue;
                    }
                    $current = $row->{$rule['column']} ?? null;
                    if (!$overwrite && $current !== null && $current !== '') {
                        $skipped[$field] = "column has value, overwrite=false";
                        continue;
                    }
                    DB::table($rule['table'])
                        ->where('object_id', $objectId)
                        ->update([$rule['column'] => $strValue]);
                    $written[$field] = "{$rule['table']}.{$rule['column']}";
                } elseif ($rule['type'] === 'i18n') {
                    $row = DB::table($rule['table'])
                        ->where('id', $objectId)->where('culture', $culture)->first();
                    if (!$row) {
                        $skipped[$field] = "no {$rule['table']} row for id={$objectId}/{$culture}";
                        continue;
                    }
                    $current = $row->{$rule['column']} ?? null;
                    if (!$overwrite && $current !== null && $current !== '') {
                        $skipped[$field] = "i18n column has value, overwrite=false";
                        continue;
                    }
                    DB::table($rule['table'])
                        ->where('id', $objectId)->where('culture', $culture)
                        ->update([$rule['column'] => $strValue]);
                    $written[$field] = "{$rule['table']}.{$rule['column']}";
                } elseif ($rule['type'] === 'gps_components') {
                    if (is_array($value) && isset($value['decimal'])) {
                        DB::table($rule['table'])
                            ->where('object_id', $objectId)
                            ->update(['sublocation' => $value['decimal']]);
                        $written[$field] = "{$rule['table']}.sublocation (decimal lat,lon)";
                    } else {
                        $skipped[$field] = 'gps payload missing decimal component';
                    }
                } elseif ($rule['type'] === 'term_relation') {
                    $tokens = $this->splitMultiValue($strValue);
                    [$matched, $unmatched] = $this->matchTermsByName($tokens, $rule['taxonomy_id'], $culture);
                    $insertedIds = [];
                    foreach ($matched as $termId) {
                        if (!$this->relationExists($termId, $objectId, null)) {
                            $insertedIds[] = $this->insertRelation($termId, $objectId, null, $culture);
                        }
                    }
                    if (!empty($insertedIds)) {
                        $written[$field] = sprintf(
                            'relation x %d (subject=term[taxonomy=%d], object=io)',
                            count($insertedIds), $rule['taxonomy_id']
                        );
                    }
                    if (!empty($unmatched)) {
                        $skipped[$field] = sprintf(
                            'unmatched in taxonomy %d (match-only policy, no auto-create): %s',
                            $rule['taxonomy_id'],
                            implode(', ', array_slice($unmatched, 0, 5)) . (count($unmatched) > 5 ? ', …' : '')
                        );
                    }
                } elseif ($rule['type'] === 'actor_relation') {
                    $tokens = $this->splitMultiValue($strValue);
                    [$matched, $unmatched] = $this->matchActorsByName($tokens, $culture);
                    $insertedIds = [];
                    foreach ($matched as $actorId) {
                        if (!$this->relationExists($actorId, $objectId, $rule['relation_type_id'])) {
                            $insertedIds[] = $this->insertRelation($actorId, $objectId, $rule['relation_type_id'], $culture);
                        }
                    }
                    if (!empty($insertedIds)) {
                        $written[$field] = sprintf(
                            'relation x %d (subject=actor, object=io, type=%d)',
                            count($insertedIds), $rule['relation_type_id']
                        );
                    }
                    if (!empty($unmatched)) {
                        $skipped[$field] = sprintf(
                            'unmatched actor names (match-only policy, no auto-create): %s',
                            implode(', ', array_slice($unmatched, 0, 5)) . (count($unmatched) > 5 ? ', …' : '')
                        );
                    }
                } elseif ($rule['type'] === 'creation_event') {
                    $existing = DB::table('event')
                        ->where('object_id', $objectId)
                        ->where('type_id', $rule['type_id'])
                        ->exists();
                    if ($existing) {
                        $skipped[$field] = 'creation event already exists for this IO (dedupe policy)';
                    } else {
                        $isoDate = $this->normaliseIsoDate($strValue);
                        if ($isoDate === null) {
                            $skipped[$field] = "could not parse date '{$strValue}' to ISO YYYY-MM-DD";
                        } else {
                            $eventId = $this->insertEvent($objectId, $rule['type_id'], $isoDate, $culture);
                            $written[$field] = "event id={$eventId} (type=creation, start_date={$isoDate})";
                        }
                    }
                }
            } catch (\Throwable $e) {
                $skipped[$field] = 'write failed: ' . $e->getMessage();
            }
        }

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * Split a multi-value EXIF string (semicolon / comma separated) into an
     * array of trimmed non-empty tokens. Used for keywords / artist lists
     * where EXIF shoves multiple values into one string.
     */
    private function splitMultiValue(string $value): array
    {
        $parts = preg_split('/\s*[;,]\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(array_map('trim', $parts ?: []), fn ($v) => $v !== ''));
    }

    /**
     * Look up term ids by exact (case-insensitive) name match within a
     * taxonomy. Returns [matched_term_ids, unmatched_input_strings].
     * Match-only policy: no auto-create. The audit row carries the unmatched
     * list so an operator can decide later whether to add them as authority
     * file entries.
     */
    private function matchTermsByName(array $names, int $taxonomyId, string $culture): array
    {
        $matched = [];
        $unmatched = [];
        foreach ($names as $n) {
            $id = (int) DB::table('term as t')
                ->join('term_i18n as ti', 'ti.id', '=', 't.id')
                ->where('t.taxonomy_id', $taxonomyId)
                ->whereRaw('LOWER(ti.name) = LOWER(?)', [$n])
                ->whereIn('ti.culture', [$culture, 'en'])
                ->orderByRaw('FIELD(ti.culture, ?, ?)', [$culture, 'en'])
                ->value('t.id');
            if ($id) {
                $matched[$n] = $id;
            } else {
                $unmatched[] = $n;
            }
        }
        return [array_values(array_unique($matched)), $unmatched];
    }

    /**
     * Look up actor ids by exact (case-insensitive) authorized_form_of_name
     * match. Returns [matched_actor_ids, unmatched_input_strings]. Same
     * match-only policy as matchTermsByName: no auto-create.
     */
    private function matchActorsByName(array $names, string $culture): array
    {
        $matched = [];
        $unmatched = [];
        foreach ($names as $n) {
            $id = (int) DB::table('actor as a')
                ->join('actor_i18n as ai', 'ai.id', '=', 'a.id')
                ->whereRaw('LOWER(ai.authorized_form_of_name) = LOWER(?)', [$n])
                ->whereIn('ai.culture', [$culture, 'en'])
                ->orderByRaw('FIELD(ai.culture, ?, ?)', [$culture, 'en'])
                ->value('a.id');
            if ($id) {
                $matched[$n] = $id;
            } else {
                $unmatched[] = $n;
            }
        }
        return [array_values(array_unique($matched)), $unmatched];
    }

    /** Whether a relation row already exists for (subject, object[, type]). */
    private function relationExists(int $subjectId, int $objectId, ?int $typeId): bool
    {
        $q = DB::table('relation')->where('subject_id', $subjectId)->where('object_id', $objectId);
        if ($typeId !== null) {
            $q->where('type_id', $typeId);
        }
        return $q->exists();
    }

    /**
     * Insert a relation row honouring AtoM's CTI shape (object row first,
     * relation row references the same id). Returns the new relation id.
     */
    private function insertRelation(int $subjectId, int $objectId, ?int $typeId, string $culture): int
    {
        $relId = DB::table('object')->insertGetId([
            'class_name' => 'QubitRelation',
            'created_at' => now(),
            'updated_at' => now(),
            'serial_number' => 0,
        ]);
        DB::table('relation')->insert([
            'id' => $relId,
            'subject_id' => $subjectId,
            'object_id'  => $objectId,
            'type_id'    => $typeId,
            'source_culture' => $culture,
        ]);
        return $relId;
    }

    /**
     * Insert an event row honouring AtoM's CTI shape.
     */
    private function insertEvent(int $objectId, int $typeId, string $isoDate, string $culture): int
    {
        $eventId = DB::table('object')->insertGetId([
            'class_name' => 'QubitEvent',
            'created_at' => now(),
            'updated_at' => now(),
            'serial_number' => 0,
        ]);
        DB::table('event')->insert([
            'id' => $eventId,
            'object_id' => $objectId,
            'type_id' => $typeId,
            'start_date' => $isoDate,
            'source_culture' => $culture,
        ]);
        return $eventId;
    }

    /**
     * Best-effort parse of a free-form date to ISO YYYY-MM-DD. Handles EXIF
     * 'YYYY:MM:DD HH:MM:SS' (most common), ISO 'YYYY-MM-DD', and 'YYYY' (year
     * only - returns YYYY-01-01). Returns null if the input is unparseable
     * so the caller can surface a skipped reason instead of inserting bad
     * data.
     */
    private function normaliseIsoDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        // EXIF DateTime: 2021:02:05 15:04:54
        if (preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $value, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        // ISO date or ISO datetime: 2021-02-05 / 2021-02-05T15:04:54
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        // Year only
        if (preg_match('/^(\d{4})$/', $value, $m)) {
            return "{$m[1]}-01-01";
        }
        // strtotime fallback
        $t = @strtotime($value);
        if ($t !== false && $t > 0) {
            return date('Y-m-d', $t);
        }
        return null;
    }

    /**
     * Single entry point for upload-path callers: extract from the file at
     * $filePath, resolve the sector from the IO's source_standard, and apply
     * to the per-sector typed columns. No-op if meta_extract_on_upload is
     * disabled. Returns the apply() result so the caller can audit-trail.
     */
    public function extractAndApplyOnUpload(int $objectId, string $filePath, array $options = []): array
    {
        if (!$this->settingBool('meta_extract_on_upload', true)) {
            return ['written' => [], 'skipped' => ['_disabled' => 'meta_extract_on_upload=false']];
        }
        if (!file_exists($filePath)) {
            return ['written' => [], 'skipped' => ['_disabled' => "file not found: {$filePath}"]];
        }
        $sourceStandard = (string) DB::table('information_object')
            ->where('id', $objectId)
            ->value('source_standard');
        $sector = $this->resolveSector($sourceStandard);
        $normalised = $this->extractNormalised($filePath);
        if (empty($normalised)) {
            return ['written' => [], 'skipped' => ['_no_metadata' => 'no metadata extracted from file']];
        }
        $result = $this->applyToSector($objectId, $sector, $normalised, $options);
        $result['sector'] = $sector;
        $result['extracted_fields'] = array_keys($normalised);
        return $result;
    }

    /**
     * Read a boolean-shaped ahg_settings row. Honours 'true'/'false'/'1'/'0'.
     */
    private function settingBool(string $key, bool $default): bool
    {
        $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
        if ($v === null) return $default;
        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Run exiftool and return parsed JSON output.
     *
     * Execute: exiftool -json -a -G1 filepath
     */
    private function runExifTool(string $filePath): array
    {
        $command = sprintf(
            'exiftool -json -a -G1 %s 2>&1',
            escapeshellarg($filePath)
        );

        $output = [];
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning("MetadataExtraction: exiftool failed (code {$returnCode}): " . implode("\n", $output));
            return [];
        }

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('MetadataExtraction: Failed to parse exiftool JSON: ' . json_last_error_msg());
            return [];
        }

        return $data[0] ?? [];
    }

    /**
     * Run ffprobe and return parsed JSON output.
     *
     * Execute: ffprobe -v quiet -print_format json -show_format -show_streams filepath
     */
    private function runFfprobe(string $filePath): array
    {
        $command = sprintf(
            'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellarg($filePath)
        );

        $output = [];
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning("MetadataExtraction: ffprobe failed (code {$returnCode})");
            return [];
        }

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('MetadataExtraction: Failed to parse ffprobe JSON: ' . json_last_error_msg());
            return [];
        }

        return $data;
    }

    /**
     * Parse XMP XML content into an associative array.
     */
    private function parseXmpXml(string $xmpData): array
    {
        $parsed = [];

        // Dublin Core
        if (preg_match('/<dc:title[^>]*>.*?<rdf:Alt[^>]*>.*?<rdf:li[^>]*>([^<]+)/s', $xmpData, $m)) {
            $parsed['title'] = $this->cleanString($m[1]);
        }
        if (preg_match('/<dc:description[^>]*>.*?<rdf:Alt[^>]*>.*?<rdf:li[^>]*>([^<]+)/s', $xmpData, $m)) {
            $parsed['description'] = $this->cleanString($m[1]);
        }
        if (preg_match_all('/<dc:creator[^>]*>.*?<rdf:Seq[^>]*>(.*?)<\/rdf:Seq>/s', $xmpData, $matches)) {
            preg_match_all('/<rdf:li[^>]*>([^<]+)<\/rdf:li>/', $matches[1][0] ?? '', $creators);
            if (!empty($creators[1])) {
                $parsed['creator'] = array_map([$this, 'cleanString'], $creators[1]);
            }
        }
        if (preg_match_all('/<dc:subject[^>]*>.*?<rdf:Bag[^>]*>(.*?)<\/rdf:Bag>/s', $xmpData, $matches)) {
            preg_match_all('/<rdf:li[^>]*>([^<]+)<\/rdf:li>/', $matches[1][0] ?? '', $keywords);
            if (!empty($keywords[1])) {
                $parsed['keywords'] = array_map([$this, 'cleanString'], $keywords[1]);
            }
        }
        if (preg_match('/<dc:rights[^>]*>.*?<rdf:Alt[^>]*>.*?<rdf:li[^>]*>([^<]+)/s', $xmpData, $m)) {
            $parsed['rights'] = $this->cleanString($m[1]);
        }

        // Photoshop / IPTC Core
        foreach (['AuthorsPosition', 'City', 'State', 'Country'] as $field) {
            if (preg_match("/<photoshop:{$field}>([^<]+)/s", $xmpData, $m)) {
                $parsed[strtolower($field)] = $this->cleanString($m[1]);
            }
        }

        // XMP Basic
        if (preg_match('/<xmp:CreateDate>([^<]+)/s', $xmpData, $m)) {
            $parsed['create_date'] = $this->cleanString($m[1]);
        }
        if (preg_match('/<xmp:ModifyDate>([^<]+)/s', $xmpData, $m)) {
            $parsed['modify_date'] = $this->cleanString($m[1]);
        }
        if (preg_match('/<xmp:CreatorTool>([^<]+)/s', $xmpData, $m)) {
            $parsed['creator_tool'] = $this->cleanString($m[1]);
        }

        // EXIF in XMP
        if (preg_match('/<exif:DateTimeOriginal>([^<]+)/s', $xmpData, $m)) {
            $parsed['date_time_original'] = $this->cleanString($m[1]);
        }

        return $parsed;
    }

    /**
     * Extract GPS coordinates from EXIF data.
     */
    private function extractGpsFromExif(array $exif): ?array
    {
        if (!isset($exif['GPSLatitude'], $exif['GPSLongitude'])) {
            return null;
        }

        $lat = $this->gpsToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N');
        $lon = $this->gpsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E');

        if ($lat === null || $lon === null) {
            return null;
        }

        $gps = [
            'latitude'  => $lat,
            'longitude' => $lon,
            'decimal'   => sprintf('%.6f, %.6f', $lat, $lon),
        ];

        if (isset($exif['GPSAltitude'])) {
            $alt = $this->parseRational($exif['GPSAltitude']);
            if ($alt !== null) {
                $ref = $exif['GPSAltitudeRef'] ?? 0;
                $gps['altitude'] = $ref == 1 ? -$alt : $alt;
            }
        }

        return $gps;
    }

    /**
     * Convert GPS DMS array to decimal degrees.
     */
    private function gpsToDecimal($coordinate, string $ref): ?float
    {
        if (!is_array($coordinate) || count($coordinate) !== 3) {
            return null;
        }

        $degrees = $this->parseRational($coordinate[0]);
        $minutes = $this->parseRational($coordinate[1]);
        $seconds = $this->parseRational($coordinate[2]);

        if ($degrees === null || $minutes === null || $seconds === null) {
            return null;
        }

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if ($ref === 'S' || $ref === 'W') {
            $decimal = -$decimal;
        }

        return $decimal;
    }

    /**
     * Parse an EXIF rational number (e.g. "1/200").
     */
    private function parseRational($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = explode('/', $value);
            if ((float) $den != 0) {
                return (float) $num / (float) $den;
            }
        }

        return null;
    }

    /**
     * Manual PDF header parsing fallback.
     */
    private function extractPdfHeaderManual(string $filePath): array
    {
        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return [];
        }

        $header = fread($handle, 4096);
        fclose($handle);

        $result = [];

        // PDF version
        if (preg_match('/^%PDF-(\d+\.\d+)/', $header, $m)) {
            $result['pdf_version'] = $m[1];
        }

        // Metadata from Info dictionary (very basic parsing)
        $content = @file_get_contents($filePath);
        if ($content) {
            foreach (['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer'] as $field) {
                if (preg_match("/{$field}\s*\(([^)]+)\)/", $content, $m)) {
                    $result[strtolower($field)] = $this->cleanString($m[1]);
                } elseif (preg_match("/{$field}\s*<([^>]+)>/", $content, $m)) {
                    $decoded = $this->decodePdfHex($m[1]);
                    if ($decoded) {
                        $result[strtolower($field)] = $decoded;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Decode PDF hex-encoded string.
     */
    private function decodePdfHex(string $hex): ?string
    {
        $hex = str_replace(' ', '', $hex);
        $decoded = @hex2bin($hex);
        if ($decoded === false) {
            return null;
        }
        // Strip BOM and nulls (UTF-16)
        $decoded = str_replace("\x00", '', $decoded);
        $decoded = preg_replace('/[\x00-\x1f]/', '', $decoded);
        return trim($decoded) ?: null;
    }

    /**
     * Parse frame rate string from ffprobe (e.g. "24000/1001" or "30/1").
     */
    private function parseFrameRate(?string $rate): ?float
    {
        if (!$rate) {
            return null;
        }
        if (str_contains($rate, '/')) {
            [$num, $den] = explode('/', $rate);
            if ((float) $den != 0) {
                return round((float) $num / (float) $den, 3);
            }
        }
        return is_numeric($rate) ? (float) $rate : null;
    }

    /**
     * Flatten a nested metadata array into dot-notation key/value pairs.
     */
    private function flattenMetadata(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}:{$key}" : $key;

            if (is_array($value)) {
                // Check if sequential array (list)
                if (array_is_list($value)) {
                    $flat[$fullKey] = json_encode($value);
                } else {
                    $flat = array_merge($flat, $this->flattenMetadata($value, $fullKey));
                }
            } elseif ($value !== null && $value !== '') {
                $flat[$fullKey] = (string) $value;
            }
        }

        return $flat;
    }

    /**
     * Save a single metadata property into the AtoM property table.
     */
    private function saveMetadataProperty(int $objectId, string $name, string $value, string $culture = 'en'): void
    {
        // Create object entry
        $propertyObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitProperty',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // AtoM `property` has no created_at/updated_at — timestamps live on `object`.
        DB::table('property')->insert([
            'id'             => $propertyObjectId,
            'object_id'      => $objectId,
            'name'           => $name,
            'scope'          => 'metadata_extraction',
            'source_culture' => $culture,
        ]);

        // Create i18n entry
        DB::table('property_i18n')->insert([
            'id'      => $propertyObjectId,
            'culture' => $culture,
            'value'   => $value,
        ]);
    }

    /**
     * Detect MIME type of a file.
     */
    private function detectMimeType(string $filePath): string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($filePath);
            if ($mime) {
                return $mime;
            }
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mime) {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Determine file category from MIME type or extension.
     */
    private function determineCategory(string $filePath, string $mimeType): ?string
    {
        if (isset(self::MIME_CATEGORIES[$mimeType])) {
            return self::MIME_CATEGORIES[$mimeType];
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return self::EXT_CATEGORIES[$ext] ?? null;
    }

    /**
     * Check if a system command is available.
     */
    private function isCommandAvailable(string $command): bool
    {
        $result = shell_exec("which {$command} 2>/dev/null");
        return !empty(trim($result ?? ''));
    }

    /**
     * Clean a string: fix encoding, trim whitespace.
     */
    private function cleanString($value): string
    {
        if (!is_string($value)) {
            return (string) $value;
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        return trim($value);
    }
}
