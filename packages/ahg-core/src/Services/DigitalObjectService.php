<?php

/**
 * DigitalObjectService - Service for Heratio
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



namespace AhgCore\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DigitalObjectService
{
    // Usage term IDs (taxonomy 47)
    const USAGE_MASTER = 140;
    const USAGE_REFERENCE = 141;
    const USAGE_THUMBNAIL = 142;

    // Media type term IDs (taxonomy 46)
    const MEDIA_AUDIO = 135;
    const MEDIA_IMAGE = 136;
    const MEDIA_TEXT = 137;
    const MEDIA_VIDEO = 138;
    const MEDIA_OTHER = 139;

    // Upload base directory
    /** @deprecated Use config('heratio.uploads_path') — kept as fallback only */
    const UPLOAD_DIR = '/mnt/nas/heratio/archive';

    /**
     * Get all digital objects for an entity, organized by usage type.
     * Returns: ['master' => ..., 'reference' => ..., 'thumbnail' => ..., 'all' => Collection]
     */
    public static function getForObject(int $objectId): array
    {
        $all = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->get();

        // If no direct objects, return empty
        if ($all->isEmpty()) {
            return ['master' => null, 'reference' => null, 'thumbnail' => null, 'all' => collect()];
        }

        // Find master (usage_id = 140)
        $master = $all->firstWhere('usage_id', self::USAGE_MASTER);

        // AtoM stores some uploads with usage_id=142 (thumbnail) instead of 140 (master).
        // Treat the first direct digital object as master when no usage_id=140 exists.
        if (!$master) {
            $master = $all->first();
        }

        // Find derivatives (children of master)
        $thumbnail = null;
        $reference = null;
        if ($master) {
            $derivatives = DB::table('digital_object')
                ->where('parent_id', $master->id)
                ->get();
            $thumbnail = $derivatives->firstWhere('usage_id', self::USAGE_THUMBNAIL);
            $reference = $derivatives->firstWhere('usage_id', self::USAGE_REFERENCE);
            $all = $all->merge($derivatives);
        }

        return [
            'master' => $master,
            'reference' => $reference,
            'thumbnail' => $thumbnail,
            'all' => $all,
        ];
    }

    /**
     * Build the full URL path for a digital object.
     */
    public static function getUrl($digitalObject): string
    {
        if (!$digitalObject) {
            return '';
        }

        return rtrim($digitalObject->path, '/') . '/' . $digitalObject->name;
    }

    /**
     * Get the best display image URL (reference > master for images, thumbnail for listing).
     */
    public static function getDisplayUrl(array $objects): string
    {
        if (!empty($objects['reference'])) {
            return self::getUrl($objects['reference']);
        }
        if (!empty($objects['master'])) {
            return self::getUrl($objects['master']);
        }

        return '';
    }

    /**
     * Get thumbnail URL.
     */
    public static function getThumbnailUrl(array $objects): string
    {
        if (!empty($objects['thumbnail'])) {
            return self::getUrl($objects['thumbnail']);
        }

        return self::getDisplayUrl($objects);
    }

    /**
     * Determine media type string from media_type_id.
     * Uses constants: 135=Audio, 136=Image, 137=Text, 138=Video, 139=Other
     */
    public static function getMediaType($digitalObject): string
    {
        if (!$digitalObject) {
            return 'unknown';
        }

        if ($digitalObject->media_type_id) {
            return match ((int) $digitalObject->media_type_id) {
                self::MEDIA_AUDIO => 'audio',   // 135
                self::MEDIA_IMAGE => 'image',   // 136
                self::MEDIA_TEXT  => 'text',     // 137
                self::MEDIA_VIDEO => 'video',   // 138
                self::MEDIA_OTHER => 'other',   // 139
                default => 'other',
            };
        }

        // Fallback: detect from mime_type
        $mime = $digitalObject->mime_type ?? '';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if ($mime === 'application/pdf') return 'text';

        // Fallback: detect from file extension
        $ext = strtolower(pathinfo($digitalObject->name ?? '', PATHINFO_EXTENSION));
        $extMap = [
            'mp3' => 'audio', 'm4a' => 'audio', 'wav' => 'audio', 'ogg' => 'audio', 'flac' => 'audio', 'aac' => 'audio',
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'tif' => 'image', 'tiff' => 'image', 'webp' => 'image',
            'mp4' => 'video', 'webm' => 'video', 'avi' => 'video', 'mov' => 'video', 'mkv' => 'video',
            'pdf' => 'text',
        ];

        return $extMap[$ext] ?? 'other';
    }

    /**
     * Upload a digital object for an information object.
     *
     * Creates master record + reference and thumbnail derivatives.
     * Uses GD for image resizing. Non-image files get generic derivatives.
     *
     * @param int          $objectId Information object ID
     * @param UploadedFile $file     Uploaded file
     *
     * @return int Master digital object ID
     *
     * @throws \RuntimeException on failure
     */
    public static function upload(int $objectId, UploadedFile $file): int
    {
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
        $originalName = $file->getClientOriginalName();
        $mediaTypeId = self::resolveMediaTypeId($mimeType);

        // Create upload directory
        $uploadDir = config('heratio.uploads_path', self::UPLOAD_DIR) . '/' . $objectId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Build filenames
        $extension = $file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $baseName);

        $masterFilename = 'master_' . $safeName . '.' . $extension;

        // Store master file
        $file->move($uploadDir, $masterFilename);
        $masterPath = $uploadDir . '/' . $masterFilename;

        if (!file_exists($masterPath)) {
            throw new \RuntimeException('Failed to store uploaded file.');
        }

        // Capture byte_size + checksum from the PLAINTEXT file before any
        // encrypt-on-write rewrites it. The DB columns reflect the
        // semantic content the operator uploaded, not the on-disk wrapped
        // format - matters for quota / dedup / verification.
        $byteSize = filesize($masterPath);
        $checksum = md5_file($masterPath);

        // #125 derivative encryption: when encryption_encrypt_derivatives is
        // on, encrypt the master file in place after the move. Idempotent +
        // no-op when the gate is off so the existing flow stays unchanged
        // for operators who haven't opted in. Encrypt failure is non-fatal -
        // logged and the upload continues with the file at rest as
        // plaintext (the daily bulk-apply will retry).
        try {
            (new \AhgCore\Services\EncryptionService())->encryptFile($masterPath);
        } catch (\Throwable $__e) {
            \Illuminate\Support\Facades\Log::warning('[encryption] master encrypt-on-write failed', [
                'path' => $masterPath, 'error' => $__e->getMessage(),
            ]);
        }

        // Create object table entry for digital object (class table inheritance)
        $now = now()->format('Y-m-d H:i:s');
        $doObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        // Web-relative path for DB storage
        $webPath = '/uploads/r/' . $objectId . '/';

        // Create master digital_object record
        DB::table('digital_object')->insert([
            'id' => $doObjectId,
            'object_id' => $objectId,
            'usage_id' => self::USAGE_MASTER,
            'mime_type' => $mimeType,
            'media_type_id' => $mediaTypeId,
            'name' => $masterFilename,
            'path' => $webPath,
            'byte_size' => $byteSize,
            'checksum' => $checksum,
            'checksum_type' => 'md5',
            'parent_id' => null,
        ]);

        $masterId = $doObjectId;

        // Generate derivatives
        $isImage = in_array($mediaTypeId, [self::MEDIA_IMAGE]);

        if ($isImage && extension_loaded('gd')) {
            self::generateImageDerivatives($masterId, $objectId, $masterPath, $safeName, $webPath);
        } else {
            self::generateGenericDerivatives($masterId, $objectId, $mimeType, $safeName, $webPath, $uploadDir);
        }

        return $masterId;
    }

    /**
     * Link an external URL (HTTP/HTTPS/FTP) as a digital object.
     *
     * No file is moved — the row stores the URL in `path` and the rendered name
     * in `name`. Recognised media hosts (Sketchfab/YouTube/Vimeo) are tagged as
     * MEDIA_OTHER so the read-side renders an external-link icon.
     */
    public static function linkExternalUrl(int $objectId, string $url, ?string $displayName = null): int
    {
        $url = trim($url);
        if (!preg_match('#^(https?|ftp)://#i', $url)) {
            throw new \RuntimeException('External URL must start with http://, https:// or ftp://.');
        }

        $name = $displayName ?: (basename(parse_url($url, PHP_URL_PATH) ?: '') ?: $url);
        $mimeType = self::guessMimeFromUrl($url);
        $mediaTypeId = self::resolveMediaTypeId($mimeType);

        $now = now()->format('Y-m-d H:i:s');
        $doObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        DB::table('digital_object')->insert([
            'id' => $doObjectId,
            'object_id' => $objectId,
            'usage_id' => self::USAGE_MASTER,
            'mime_type' => $mimeType,
            'media_type_id' => $mediaTypeId,
            'name' => $name,
            'path' => $url,
            'byte_size' => 0,
            'checksum' => null,
            'checksum_type' => null,
            'parent_id' => null,
        ]);

        return $doObjectId;
    }

    /**
     * Best-effort MIME guess from a URL's extension.
     */
    protected static function guessMimeFromUrl(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'tif', 'tiff' => 'image/tiff',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    /**
     * Delete a digital object and all its derivatives.
     *
     * Removes files from disk and database records.
     *
     * @param int $digitalObjectId Digital object ID
     *
     * @return bool True on success
     */
    public static function delete(int $digitalObjectId): bool
    {
        try {
            // Get master and all derivatives
            $objects = DB::table('digital_object')
                ->where('id', $digitalObjectId)
                ->orWhere('parent_id', $digitalObjectId)
                ->get();

            if ($objects->isEmpty()) {
                return false;
            }

            foreach ($objects as $obj) {
                // Try to delete file from disk
                $filePath = config('heratio.uploads_path', self::UPLOAD_DIR) . '/' . ($obj->object_id ?: '') . '/' . $obj->name;

                // Also try via path field
                if (!empty($obj->path) && !empty($obj->name)) {
                    // Path may be web-relative; try to find actual file
                    $altPath = config('heratio.uploads_path', self::UPLOAD_DIR) . '/' . ltrim($obj->path, '/') . $obj->name;
                    if (file_exists($altPath)) {
                        @unlink($altPath);
                    }
                }

                if (file_exists($filePath)) {
                    @unlink($filePath);
                }

                // Delete digital_object record
                DB::table('digital_object')->where('id', $obj->id)->delete();

                // Delete object table entry
                DB::table('object')->where('id', $obj->id)->delete();
            }

            // Clean up empty directory
            $master = $objects->firstWhere('usage_id', self::USAGE_MASTER);
            if ($master && $master->object_id) {
                $dir = config('heratio.uploads_path', self::UPLOAD_DIR) . '/' . $master->object_id;
                if (is_dir($dir) && count(scandir($dir)) <= 2) {
                    @rmdir($dir);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('DigitalObjectService::delete failed', [
                'id' => $digitalObjectId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get extended metadata from digital_object_metadata table.
     *
     * @param int $digitalObjectId Digital object ID
     *
     * @return array Metadata key-value pairs (empty if table missing or no data)
     */
    public static function getMetadata(int $digitalObjectId): array
    {
        try {
            $meta = DB::table('digital_object_metadata')
                ->where('digital_object_id', $digitalObjectId)
                ->first();

            if (!$meta) {
                return [];
            }

            return (array) $meta;
        } catch (\Exception $e) {
            // Table may not exist in some installations
            return [];
        }
    }

    /**
     * Get property values for a digital object.
     *
     * Reads displayAsCompound and digitalObjectAltText from property/property_i18n.
     *
     * @param int    $objectId Information object ID
     * @param string $culture  Culture code
     *
     * @return array ['displayAsCompound' => bool, 'altText' => string]
     */
    public static function getProperties(int $objectId, string $culture = 'en'): array
    {
        $result = [
            'displayAsCompound' => false,
            'altText' => '',
        ];

        $props = DB::table('property')
            ->where('object_id', $objectId)
            ->whereIn('name', ['displayAsCompound', 'digitalObjectAltText'])
            ->get();

        foreach ($props as $prop) {
            $value = DB::table('property_i18n')
                ->where('id', $prop->id)
                ->where('culture', $culture)
                ->value('value');

            if (null === $value) {
                $value = DB::table('property_i18n')
                    ->where('id', $prop->id)
                    ->orderBy('culture')
                    ->value('value');
            }

            if ('displayAsCompound' === $prop->name) {
                $result['displayAsCompound'] = (bool) $value;
            } elseif ('digitalObjectAltText' === $prop->name) {
                $result['altText'] = $value ?? '';
            }
        }

        return $result;
    }

    /**
     * Get a human-readable media type name from term_i18n (taxonomy 46).
     *
     * @param int|null $mediaTypeId Media type term ID
     * @param string   $culture     Culture code
     *
     * @return string Media type name or empty string
     */
    public static function getMediaTypeName(?int $mediaTypeId, string $culture = 'en'): string
    {
        if (!$mediaTypeId) {
            return '';
        }

        return DB::table('term_i18n')
            ->where('id', $mediaTypeId)
            ->where('culture', $culture)
            ->value('name') ?? '';
    }

    /**
     * Get a human-readable usage type name from term_i18n.
     *
     * @param int|null $usageId Usage term ID
     * @param string   $culture Culture code
     *
     * @return string Usage name or empty string
     */
    public static function getUsageName(?int $usageId, string $culture = 'en'): string
    {
        if (!$usageId) {
            return '';
        }

        return DB::table('term_i18n')
            ->where('id', $usageId)
            ->where('culture', $culture)
            ->value('name') ?? '';
    }

    /**
     * Format byte size for human-readable display.
     *
     * @param int|null $bytes Byte count
     *
     * @return string Formatted size (e.g. "1.5 MB")
     */
    public static function formatFileSize(?int $bytes): string
    {
        if (null === $bytes || $bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            ++$i;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get maximum upload file size from PHP configuration.
     *
     * @return int Maximum upload size in bytes
     */
    public static function getMaxUploadSize(): int
    {
        $uploadMax = self::parseIniSize(ini_get('upload_max_filesize'));
        $postMax = self::parseIniSize(ini_get('post_max_size'));

        return min($uploadMax, $postMax);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Resolve media type ID from MIME type.
     *
     * @param string $mimeType MIME type string
     *
     * @return int Media type term ID
     */
    protected static function resolveMediaTypeId(string $mimeType): int
    {
        $type = explode('/', $mimeType)[0] ?? '';

        return match ($type) {
            'image' => self::MEDIA_IMAGE,
            'audio' => self::MEDIA_AUDIO,
            'video' => self::MEDIA_VIDEO,
            'text' => self::MEDIA_TEXT,
            'application' => self::resolveApplicationMediaType($mimeType),
            default => self::MEDIA_OTHER,
        };
    }

    /**
     * Resolve media type for application/* MIME types.
     */
    protected static function resolveApplicationMediaType(string $mimeType): int
    {
        // PDFs and document types are "text" in AtoM taxonomy
        $textTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/rtf',
            'application/vnd.oasis.opendocument.text',
        ];

        if (in_array($mimeType, $textTypes)) {
            return self::MEDIA_TEXT;
        }

        return self::MEDIA_OTHER;
    }

    /**
     * Extract embedded metadata (EXIF / IPTC / XMP / document props) from a
     * master DO via ExifTool and write it to the DAM metadata tables:
     * preservation_checksum, digital_object_metadata, media_metadata,
     * and dam_iptc_metadata (when IPTC-rich).
     *
     * Requires the `exiftool` binary on PATH. If absent, logs and returns
     * so the caller's pipeline can continue.
     *
     * Idempotent: upserts by digital_object_id / object_id where a
     * uniqueness constraint exists; inserts a fresh preservation_checksum
     * row each time (append-only, as fixity verifications accrete).
     */
    public static function extractMetadataForMaster(int $masterId): void
    {
        $master = DB::table('digital_object')->where('id', $masterId)->first();
        if (!$master || !$master->object_id) {
            return;
        }

        $uploadDir = config('heratio.uploads_path', self::UPLOAD_DIR) . '/' . $master->object_id;
        $filePath = $uploadDir . '/' . $master->name;
        if (!is_file($filePath)) {
            return;
        }

        // Always record a checksum row when one is present on digital_object.
        if (!empty($master->checksum) && !empty($master->checksum_type)) {
            $existing = DB::table('preservation_checksum')
                ->where('digital_object_id', $masterId)
                ->where('algorithm', $master->checksum_type)
                ->where('checksum_value', $master->checksum)
                ->exists();
            if (!$existing) {
                DB::table('preservation_checksum')->insert([
                    'digital_object_id' => $masterId,
                    'algorithm' => $master->checksum_type,
                    'checksum_value' => $master->checksum,
                    'file_size' => $master->byte_size,
                    'generated_at' => now(),
                    'verification_status' => 'generated',
                    'created_at' => now(),
                ]);
            }
        }

        // ExifTool extraction — optional; if not installed, skip without failing.
        $binary = trim((string) @shell_exec('command -v exiftool 2>/dev/null'));
        if ($binary === '') {
            Log::info('[DigitalObjectService] exiftool not found; skipping metadata extraction for DO ' . $masterId);
            return;
        }

        $cmd = $binary . ' -j -n -api largefilesupport=1 ' . escapeshellarg($filePath) . ' 2>/dev/null';
        $json = @shell_exec($cmd);
        if (!$json) {
            return;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || empty($decoded[0])) {
            return;
        }
        $meta = $decoded[0];

        self::writeDigitalObjectMetadata($masterId, $meta, $master);
        self::writeMediaMetadata($masterId, (int) $master->object_id, $meta, $master);
        self::writeDamIptcMetadata((int) $master->object_id, $meta);
    }

    /**
     * Write the per-DO descriptive + technical metadata row.
     */
    protected static function writeDigitalObjectMetadata(int $doId, array $meta, $master): void
    {
        $fileType = self::classifyByMime((string) ($master->mime_type ?? 'application/octet-stream'));

        $row = [
            'digital_object_id' => $doId,
            'file_type' => $fileType,
            'raw_metadata' => json_encode($meta, JSON_UNESCAPED_SLASHES),
            'title' => self::pick($meta, ['Title', 'IPTC:ObjectName', 'XMP:Title']),
            'creator' => self::pick($meta, ['Creator', 'By-line', 'Artist', 'Author', 'XMP:Creator']),
            'description' => self::pick($meta, ['ImageDescription', 'Description', 'Caption-Abstract', 'XMP:Description']),
            'keywords' => self::pickKeywords($meta),
            'copyright' => self::pick($meta, ['Copyright', 'CopyrightNotice', 'Rights', 'XMP:Rights']),
            'date_created' => self::pick($meta, ['DateTimeOriginal', 'CreateDate', 'DateCreated', 'XMP:CreateDate']),
            'image_width' => self::pickInt($meta, ['ImageWidth', 'ExifImageWidth', 'PNG:ImageWidth']),
            'image_height' => self::pickInt($meta, ['ImageHeight', 'ExifImageHeight', 'PNG:ImageHeight']),
            'camera_make' => self::pick($meta, ['Make']),
            'camera_model' => self::pick($meta, ['Model']),
            'gps_latitude' => self::pickFloat($meta, ['GPSLatitude']),
            'gps_longitude' => self::pickFloat($meta, ['GPSLongitude']),
            'gps_altitude' => self::pickFloat($meta, ['GPSAltitude']),
            'page_count' => self::pickInt($meta, ['PageCount', 'PDF:PageCount', 'Pages']),
            'word_count' => self::pickInt($meta, ['Words', 'WordCount']),
            'author' => self::pick($meta, ['Author']),
            'application' => self::pick($meta, ['Software', 'CreatorTool', 'Producer']),
            'duration' => self::pickFloat($meta, ['Duration', 'MediaDuration']),
            'duration_formatted' => self::pick($meta, ['Duration#']),
            'video_codec' => self::pick($meta, ['VideoCodec', 'CompressorID', 'VideoCompressionType']),
            'audio_codec' => self::pick($meta, ['AudioCodec', 'AudioFormat']),
            'resolution' => self::buildResolution($meta),
            'frame_rate' => self::pickFloat($meta, ['VideoFrameRate', 'FrameRate']),
            'bitrate' => self::pickInt($meta, ['AvgBitrate', 'Bitrate']),
            'sample_rate' => self::pickInt($meta, ['AudioSampleRate', 'SampleRate']),
            'channels' => self::pickInt($meta, ['AudioChannels', 'Channels']),
            'artist' => self::pick($meta, ['Artist']),
            'album' => self::pick($meta, ['Album']),
            'track_number' => self::pickInt($meta, ['Track', 'TrackNumber']),
            'genre' => self::pick($meta, ['Genre']),
            'year' => self::pick($meta, ['Year']),
            'extraction_date' => now(),
            'extraction_method' => 'exiftool',
        ];
        $row = array_filter($row, fn($v) => $v !== null);

        $existing = DB::table('digital_object_metadata')->where('digital_object_id', $doId)->exists();
        if ($existing) {
            $row['updated_at'] = now();
            DB::table('digital_object_metadata')->where('digital_object_id', $doId)->update($row);
        } else {
            $row['created_at'] = now();
            DB::table('digital_object_metadata')->insert($row);
        }
    }

    /**
     * Write the A/V-leaning technical metadata row.
     */
    protected static function writeMediaMetadata(int $doId, int $ioId, array $meta, $master): void
    {
        $mime = (string) ($master->mime_type ?? 'application/octet-stream');
        $mediaClass = explode('/', $mime)[0] ?: 'other';
        $format = self::pick($meta, ['FileType']) ?: strtoupper(pathinfo($master->name, PATHINFO_EXTENSION));

        $row = [
            'digital_object_id' => $doId,
            'object_id' => $ioId,
            'media_type' => $mediaClass,
            'format' => $format,
            'file_size' => $master->byte_size,
            'duration' => self::pickFloat($meta, ['Duration', 'MediaDuration']),
            'bitrate' => self::pickInt($meta, ['AvgBitrate', 'Bitrate']),
            'audio_codec' => self::pick($meta, ['AudioCodec', 'AudioFormat']),
            'audio_sample_rate' => self::pickInt($meta, ['AudioSampleRate', 'SampleRate']),
            'audio_channels' => self::pickInt($meta, ['AudioChannels', 'Channels']),
            'audio_bits_per_sample' => self::pickInt($meta, ['AudioBitsPerSample', 'BitsPerSample']),
            'video_codec' => self::pick($meta, ['VideoCodec', 'CompressorID']),
            'video_width' => self::pickInt($meta, ['ImageWidth', 'VideoFrameWidth']),
            'video_height' => self::pickInt($meta, ['ImageHeight', 'VideoFrameHeight']),
            'video_frame_rate' => self::pickFloat($meta, ['VideoFrameRate', 'FrameRate']),
            'title' => self::pick($meta, ['Title', 'IPTC:ObjectName']),
            'artist' => self::pick($meta, ['Artist', 'Creator', 'By-line']),
            'album' => self::pick($meta, ['Album']),
            'genre' => self::pick($meta, ['Genre']),
            'year' => self::pick($meta, ['Year']),
            'copyright' => self::pick($meta, ['Copyright', 'CopyrightNotice']),
            'make' => self::pick($meta, ['Make']),
            'model' => self::pick($meta, ['Model']),
            'software' => self::pick($meta, ['Software', 'CreatorTool']),
            'gps_coordinates' => self::buildGpsCoordinates($meta),
            'raw_metadata' => json_encode($meta, JSON_UNESCAPED_SLASHES),
            'extracted_at' => now(),
        ];
        $row = array_filter($row, fn($v) => $v !== null);

        $existing = DB::table('media_metadata')->where('digital_object_id', $doId)->exists();
        if ($existing) {
            DB::table('media_metadata')->where('digital_object_id', $doId)->update($row);
        } else {
            DB::table('media_metadata')->insert($row);
        }
    }

    /**
     * Write the IPTC-rich per-IO row. Keyed on object_id (unique) — one row
     * per information_object, populated on the first DO. Subsequent DOs on
     * the same IO don't overwrite unless they carry fresh IPTC values.
     */
    protected static function writeDamIptcMetadata(int $ioId, array $meta): void
    {
        // Only write when there's IPTC-y content to store.
        $creator = self::pick($meta, ['By-line', 'Creator', 'Artist', 'XMP:Creator']);
        $headline = self::pick($meta, ['Headline', 'IPTC:Headline']);
        $caption = self::pick($meta, ['Caption-Abstract', 'Description', 'IPTC:Caption-Abstract']);
        $keywords = self::pickKeywords($meta);
        $copyright = self::pick($meta, ['CopyrightNotice', 'Copyright', 'Rights']);

        if (!$creator && !$headline && !$caption && !$keywords && !$copyright) {
            return;
        }

        $row = [
            'object_id' => $ioId,
            'creator' => $creator,
            'headline' => $headline,
            'caption' => $caption,
            'keywords' => $keywords,
            'copyright_notice' => $copyright,
            'credit_line' => self::pick($meta, ['Credit', 'CreditLine']),
            'source' => self::pick($meta, ['Source']),
            'date_created' => self::normalizeDate(self::pick($meta, ['DateTimeOriginal', 'DateCreated', 'CreateDate'])),
            'city' => self::pick($meta, ['City']),
            'state_province' => self::pick($meta, ['Province-State', 'State']),
            'country' => self::pick($meta, ['Country-PrimaryLocationName', 'Country']),
            'country_code' => self::pick($meta, ['Country-PrimaryLocationCode']),
            'sublocation' => self::pick($meta, ['Sub-location', 'Location']),
            'title' => self::pick($meta, ['ObjectName', 'Title']),
            'instructions' => self::pick($meta, ['SpecialInstructions', 'Instructions']),
            'iptc_subject_code' => self::pick($meta, ['SubjectReference', 'SubjectCode']),
            'intellectual_genre' => self::pick($meta, ['IntellectualGenre']),
            'image_width' => self::pickInt($meta, ['ImageWidth', 'ExifImageWidth']),
            'image_height' => self::pickInt($meta, ['ImageHeight', 'ExifImageHeight']),
            'resolution_x' => self::pickInt($meta, ['XResolution']),
            'resolution_y' => self::pickInt($meta, ['YResolution']),
            'resolution_unit' => self::pick($meta, ['ResolutionUnit']),
            'color_space' => self::pick($meta, ['ColorSpace']),
            'bit_depth' => self::pickInt($meta, ['BitsPerSample', 'ColorComponents']),
            'orientation' => self::pick($meta, ['Orientation']),
            'camera_make' => self::pick($meta, ['Make']),
            'camera_model' => self::pick($meta, ['Model']),
            'lens' => self::pick($meta, ['LensModel', 'Lens']),
            'focal_length' => self::pick($meta, ['FocalLength']),
            'aperture' => self::pick($meta, ['FNumber', 'ApertureValue']),
            'shutter_speed' => self::pick($meta, ['ShutterSpeed', 'ExposureTime']),
            'iso_speed' => self::pickInt($meta, ['ISO', 'ISOSpeedRatings']),
            'flash_used' => self::pickFlash($meta),
            'gps_latitude' => self::pickFloat($meta, ['GPSLatitude']),
            'gps_longitude' => self::pickFloat($meta, ['GPSLongitude']),
            'gps_altitude' => self::pickFloat($meta, ['GPSAltitude']),
        ];
        $row = array_filter($row, fn($v) => $v !== null);

        $existing = DB::table('dam_iptc_metadata')->where('object_id', $ioId)->exists();
        if ($existing) {
            $row['updated_at'] = now();
            DB::table('dam_iptc_metadata')->where('object_id', $ioId)->update($row);
        } else {
            $row['created_at'] = now();
            DB::table('dam_iptc_metadata')->insert($row);
        }
    }

    // --- extraction helpers ---

    protected static function pick(array $meta, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $meta) && $meta[$k] !== null && $meta[$k] !== '') {
                $v = is_array($meta[$k]) ? implode(', ', $meta[$k]) : (string) $meta[$k];
                return mb_substr($v, 0, 500);
            }
        }
        return null;
    }

    protected static function pickInt(array $meta, array $keys): ?int
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $meta) && is_numeric($meta[$k])) {
                return (int) $meta[$k];
            }
        }
        return null;
    }

    protected static function pickFloat(array $meta, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $meta) && is_numeric($meta[$k])) {
                return (float) $meta[$k];
            }
        }
        return null;
    }

    protected static function pickKeywords(array $meta): ?string
    {
        foreach (['Keywords', 'Subject', 'XMP:Subject'] as $k) {
            if (!empty($meta[$k])) {
                return is_array($meta[$k]) ? implode('; ', $meta[$k]) : (string) $meta[$k];
            }
        }
        return null;
    }

    protected static function pickFlash(array $meta): ?int
    {
        if (!array_key_exists('Flash', $meta)) {
            return null;
        }
        $v = $meta['Flash'];
        if (is_numeric($v)) {
            return ((int) $v & 1) ? 1 : 0;
        }
        return stripos((string) $v, 'fired') !== false ? 1 : 0;
    }

    protected static function buildResolution(array $meta): ?string
    {
        $w = self::pickInt($meta, ['ImageWidth', 'VideoFrameWidth']);
        $h = self::pickInt($meta, ['ImageHeight', 'VideoFrameHeight']);
        return ($w && $h) ? "{$w}x{$h}" : null;
    }

    protected static function buildGpsCoordinates(array $meta): ?string
    {
        $lat = self::pickFloat($meta, ['GPSLatitude']);
        $lon = self::pickFloat($meta, ['GPSLongitude']);
        return ($lat !== null && $lon !== null) ? sprintf('%.6f,%.6f', $lat, $lon) : null;
    }

    protected static function normalizeDate(?string $raw): ?string
    {
        if (!$raw) return null;
        // ExifTool emits "2026:04:24 07:27:42" — convert to ISO date.
        if (preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $raw, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    protected static function classifyByMime(string $mime): string
    {
        $class = explode('/', $mime)[0] ?: 'other';
        return match ($class) {
            'image' => 'image',
            'audio' => 'audio',
            'video' => 'video',
            'text' => 'document',
            'application' => in_array($mime, ['application/pdf', 'application/msword'], true) ? 'document' : 'other',
            default => 'other',
        };
    }

    /**
     * Public entry point for generating derivatives from a master DO that
     * already exists on disk + in the DB. Used by the scanner pipeline and
     * any other non-HTTP-upload ingest path (e.g. data-migration).
     *
     * @param int         $masterId  digital_object.id of the master
     * @param bool|null   $makeReference  null = honour session flag upstream
     * @param bool|null   $makeThumbnail
     */
    public static function generateDerivativesForMaster(int $masterId, ?bool $makeReference = true, ?bool $makeThumbnail = true): void
    {
        $master = DB::table('digital_object')->where('id', $masterId)->first();
        if (!$master || !$master->object_id) {
            return;
        }

        $uploadDir = config('heratio.uploads_path', self::UPLOAD_DIR) . '/' . $master->object_id;
        $masterPath = $uploadDir . '/' . $master->name;
        if (!is_file($masterPath)) {
            return;
        }

        $mimeType = $master->mime_type ?: 'application/octet-stream';
        $mediaTypeId = $master->media_type_id ?: self::resolveMediaTypeId($mimeType);

        $safeName = pathinfo($master->name, PATHINFO_FILENAME);
        if (str_starts_with($safeName, 'master_')) {
            $safeName = substr($safeName, 7);
        }
        $webPath = $master->path ?: ('/uploads/r/' . $master->object_id . '/');

        $isImage = in_array($mediaTypeId, [self::MEDIA_IMAGE]);

        if ($isImage && extension_loaded('gd') && ($makeReference || $makeThumbnail)) {
            $imageInfo = @getimagesize($masterPath);
            if (!$imageInfo) {
                return;
            }
            $srcImage = self::createGdImage($masterPath, $imageInfo[2]);
            if (!$srcImage) {
                return;
            }
            $srcWidth = imagesx($srcImage);
            $srcHeight = imagesy($srcImage);

            if ($makeReference) {
                self::createDerivative(
                    $srcImage, $srcWidth, $srcHeight, 480,
                    $uploadDir . '/reference_' . $safeName . '.jpg',
                    'reference_' . $safeName . '.jpg',
                    $masterId, $master->object_id, $webPath,
                    self::USAGE_REFERENCE
                );
            }
            if ($makeThumbnail) {
                self::createDerivative(
                    $srcImage, $srcWidth, $srcHeight, 100,
                    $uploadDir . '/thumbnail_' . $safeName . '.jpg',
                    'thumbnail_' . $safeName . '.jpg',
                    $masterId, $master->object_id, $webPath,
                    self::USAGE_THUMBNAIL
                );
            }
            imagedestroy($srcImage);
        } else {
            self::generateGenericDerivatives($masterId, $master->object_id, $mimeType, $safeName, $webPath, $uploadDir);
        }
    }

    /**
     * Generate image reference and thumbnail derivatives using GD.
     */
    protected static function generateImageDerivatives(
        int $masterId,
        int $objectId,
        string $masterPath,
        string $safeName,
        string $webPath
    ): void {
        $uploadDir = config('heratio.uploads_path', self::UPLOAD_DIR) . '/' . $objectId;

        // Load source image
        $imageInfo = @getimagesize($masterPath);
        if (!$imageInfo) {
            return;
        }

        $srcImage = self::createGdImage($masterPath, $imageInfo[2]);
        if (!$srcImage) {
            return;
        }

        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);

        // Generate reference image (max 480px)
        self::createDerivative(
            $srcImage, $srcWidth, $srcHeight,
            480,
            $uploadDir . '/reference_' . $safeName . '.jpg',
            'reference_' . $safeName . '.jpg',
            $masterId, $objectId, $webPath,
            self::USAGE_REFERENCE
        );

        // Generate thumbnail (max 100px)
        self::createDerivative(
            $srcImage, $srcWidth, $srcHeight,
            100,
            $uploadDir . '/thumbnail_' . $safeName . '.jpg',
            'thumbnail_' . $safeName . '.jpg',
            $masterId, $objectId, $webPath,
            self::USAGE_THUMBNAIL
        );

        imagedestroy($srcImage);
    }

    /**
     * Create a single resized derivative and its DB record.
     */
    protected static function createDerivative(
        $srcImage,
        int $srcWidth,
        int $srcHeight,
        int $maxDimension,
        string $outputPath,
        string $filename,
        int $masterId,
        int $objectId,
        string $webPath,
        int $usageId
    ): void {
        // Calculate new dimensions maintaining aspect ratio
        if ($srcWidth >= $srcHeight) {
            $newWidth = min($srcWidth, $maxDimension);
            $newHeight = (int) round($srcHeight * ($newWidth / $srcWidth));
        } else {
            $newHeight = min($srcHeight, $maxDimension);
            $newWidth = (int) round($srcWidth * ($newHeight / $srcHeight));
        }

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG sources
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);

        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight, $srcWidth, $srcHeight
        );

        // Save as JPEG
        imagejpeg($dstImage, $outputPath, 85);
        imagedestroy($dstImage);

        if (!file_exists($outputPath)) {
            return;
        }

        $byteSize = filesize($outputPath);
        $checksum = md5_file($outputPath);

        // Create object table entry
        $now = now()->format('Y-m-d H:i:s');
        $derivObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        DB::table('digital_object')->insert([
            'id' => $derivObjectId,
            'object_id' => $objectId,
            'usage_id' => $usageId,
            'mime_type' => 'image/jpeg',
            'media_type_id' => self::MEDIA_IMAGE,
            'name' => $filename,
            'path' => $webPath,
            'byte_size' => $byteSize,
            'checksum' => $checksum,
            'checksum_type' => 'md5',
            'parent_id' => $masterId,
        ]);
    }

    /**
     * Create a GD image resource from file path and image type.
     *
     * @param string $path      File path
     * @param int    $imageType IMAGETYPE_* constant
     *
     * @return \GdImage|false
     */
    protected static function createGdImage(string $path, int $imageType)
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : false,
            default => false,
        };
    }

    /**
     * Generate generic (non-image) reference and thumbnail derivatives.
     *
     * For PDFs, audio, video etc., creates placeholder derivative records
     * pointing to generic icon files rather than resized images.
     */
    protected static function generateGenericDerivatives(
        int $masterId,
        int $objectId,
        string $mimeType,
        string $safeName,
        string $webPath,
        string $uploadDir
    ): void {
        $now = now()->format('Y-m-d H:i:s');

        // Determine generic icon based on mime type
        $iconSuffix = self::getGenericIconSuffix($mimeType);

        foreach ([self::USAGE_REFERENCE => 'reference', self::USAGE_THUMBNAIL => 'thumbnail'] as $usageId => $prefix) {
            $filename = $prefix . '_' . $safeName . '_' . $iconSuffix . '.png';

            // Create a simple colored placeholder image using GD
            $size = $usageId === self::USAGE_REFERENCE ? 480 : 100;
            if (extension_loaded('gd')) {
                $img = imagecreatetruecolor($size, $size);
                $bg = imagecolorallocate($img, 240, 240, 240);
                imagefill($img, 0, 0, $bg);
                $textColor = imagecolorallocate($img, 100, 100, 100);
                $label = strtoupper($iconSuffix);
                // Center text
                $fontSize = $usageId === self::USAGE_REFERENCE ? 5 : 2;
                $textWidth = imagefontwidth($fontSize) * strlen($label);
                $textX = (int) (($size - $textWidth) / 2);
                $textY = (int) ($size / 2 - imagefontheight($fontSize) / 2);
                imagestring($img, $fontSize, $textX, $textY, $label, $textColor);
                imagepng($img, $uploadDir . '/' . $filename);
                imagedestroy($img);
            }

            $byteSize = file_exists($uploadDir . '/' . $filename) ? filesize($uploadDir . '/' . $filename) : 0;

            $derivObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitDigitalObject',
                'created_at' => $now,
                'updated_at' => $now,
                'serial_number' => 0,
            ]);

            DB::table('digital_object')->insert([
                'id' => $derivObjectId,
                'object_id' => $objectId,
                'usage_id' => $usageId,
                'mime_type' => 'image/png',
                'media_type_id' => self::MEDIA_IMAGE,
                'name' => $filename,
                'path' => $webPath,
                'byte_size' => $byteSize,
                'checksum' => $byteSize ? md5_file($uploadDir . '/' . $filename) : '',
                'checksum_type' => 'md5',
                'parent_id' => $masterId,
            ]);
        }
    }

    /**
     * Get a short suffix string for generic icon filenames.
     */
    protected static function getGenericIconSuffix(string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'pdf') => 'pdf',
            str_starts_with($mimeType, 'audio/') => 'audio',
            str_starts_with($mimeType, 'video/') => 'video',
            str_contains($mimeType, 'word') || str_contains($mimeType, 'document') => 'doc',
            str_contains($mimeType, 'spreadsheet') || str_contains($mimeType, 'excel') => 'xls',
            str_contains($mimeType, 'presentation') || str_contains($mimeType, 'powerpoint') => 'ppt',
            str_starts_with($mimeType, 'text/') => 'txt',
            default => 'file',
        };
    }

    /**
     * Parse PHP ini size value (e.g. "8M") to bytes.
     *
     * @param string $value INI size string
     *
     * @return int Size in bytes
     */
    protected static function parseIniSize(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $num = (int) $value;

        switch ($last) {
            case 'g':
                $num *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $num *= 1024 * 1024;
                break;
            case 'k':
                $num *= 1024;
                break;
        }

        return $num;
    }
}
