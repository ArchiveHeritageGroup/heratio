<?php

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
        $master = $all->firstWhere('usage_id', 140);

        // Find derivatives (children of master)
        $thumbnail = null;
        $reference = null;
        if ($master) {
            $derivatives = DB::table('digital_object')
                ->where('parent_id', $master->id)
                ->get();
            $thumbnail = $derivatives->firstWhere('usage_id', 142);
            $reference = $derivatives->firstWhere('usage_id', 141);
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
     * 136=Image, 137=Audio, 138=Video, 139=Text, 140=Other
     */
    public static function getMediaType($digitalObject): string
    {
        if (!$digitalObject) {
            return 'unknown';
        }

        return match ((int) $digitalObject->media_type_id) {
            136 => 'image',
            137 => 'audio',
            138 => 'video',
            139 => 'text',
            default => 'other',
        };
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
        $uploadDir = self::UPLOAD_DIR . '/' . $objectId;
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

        $byteSize = filesize($masterPath);
        $checksum = md5_file($masterPath);

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
                $filePath = self::UPLOAD_DIR . '/' . ($obj->object_id ?: '') . '/' . $obj->name;

                // Also try via path field
                if (!empty($obj->path) && !empty($obj->name)) {
                    // Path may be web-relative; try to find actual file
                    $altPath = self::UPLOAD_DIR . '/' . ltrim($obj->path, '/') . $obj->name;
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
                $dir = self::UPLOAD_DIR . '/' . $master->object_id;
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
     * Generate image reference and thumbnail derivatives using GD.
     */
    protected static function generateImageDerivatives(
        int $masterId,
        int $objectId,
        string $masterPath,
        string $safeName,
        string $webPath
    ): void {
        $uploadDir = self::UPLOAD_DIR . '/' . $objectId;

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
