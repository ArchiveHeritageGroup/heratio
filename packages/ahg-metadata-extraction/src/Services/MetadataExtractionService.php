<?php

/**
 * MetadataExtractionService - Service for Heratio
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
