<?php

/**
 * PronomIdentificationService — Phase 3.2.
 *
 * Pure-PHP file-format identification driven by the PRONOM registry vocabulary
 * (PUIDs like fmt/14 for PDF 1.0, fmt/353 for TIFF 6.0, x-fmt/263 for ZIP).
 *
 * Identification strategy, in order of confidence (highest first):
 *
 *   1. Magic-bytes match against the {@see MAGIC_SIGNATURES} table.
 *   2. Extension match against the {@see EXTENSION_MAP} table.
 *   3. MIME-type match against the same table (any row whose mime_type matches).
 *   4. Fallback to "application/octet-stream" / fmt/0 (unknown).
 *
 * Persists into:
 *   - preservation_format          (the PUID registry; bootstrapped on first call)
 *   - preservation_object_format   (per-digital-object identification result)
 *
 * Risk levels per PRONOM-style classifications:
 *   - low      — preservation-quality formats (PDF/A, TIFF uncompressed, FLAC, WAV, JP2, EML)
 *   - medium   — widely supported but not preservation-grade (PDF, JPEG, MP3, MP4, DOCX)
 *   - high     — proprietary or version-bound (DOC, XLS, PPT, RAR)
 *   - unknown  — could not be identified
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgPreservation\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PronomIdentificationService
{
    /**
     * Magic-byte signatures (offset 0 unless stated). First match wins, so order
     * specific subtypes before generic containers.
     *
     * @var array<int, array{puid:string, name:string, version:?string, ext:?string, mime:?string,
     *                       offset:int, hex:string, risk:string, is_preservation:bool}>
     */
    public const MAGIC_SIGNATURES = [
        // PDF/A is just PDF with extra metadata — we identify both as PDF and let DROID handle PDF/A later
        ['puid' => 'fmt/14',   'name' => 'Acrobat PDF 1.0',          'version' => '1.0', 'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d312e30', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/15',   'name' => 'Acrobat PDF 1.1',          'version' => '1.1', 'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d312e31', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/16',   'name' => 'Acrobat PDF 1.2',          'version' => '1.2', 'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d312e32', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/17',   'name' => 'Acrobat PDF 1.3',          'version' => '1.3', 'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d312e33', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/18',   'name' => 'Acrobat PDF 1.4',          'version' => '1.4', 'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d312e34', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/19',   'name' => 'Acrobat PDF 1.5',          'version' => '1.5', 'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d312e35', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/20',   'name' => 'Acrobat PDF 1.6',          'version' => '1.6', 'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d312e36', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/276',  'name' => 'Acrobat PDF 1.7',          'version' => '1.7', 'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d312e37', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/95',   'name' => 'PDF/A-1',                  'version' => '1',   'ext' => 'pdf',  'mime' => 'application/pdf',                'offset' => 0, 'hex' => '255044462d',         'risk' => 'low',    'is_preservation' => true],

        // Image — TIFF (LE/BE)
        ['puid' => 'fmt/353',  'name' => 'TIFF (little-endian)',     'version' => '6.0', 'ext' => 'tiff', 'mime' => 'image/tiff',                     'offset' => 0, 'hex' => '49492a00',           'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'fmt/353',  'name' => 'TIFF (big-endian)',        'version' => '6.0', 'ext' => 'tiff', 'mime' => 'image/tiff',                     'offset' => 0, 'hex' => '4d4d002a',           'risk' => 'low',    'is_preservation' => true],

        // Image — JPEG family
        ['puid' => 'fmt/43',   'name' => 'JPEG File Interchange',    'version' => '1.01','ext' => 'jpg',  'mime' => 'image/jpeg',                     'offset' => 0, 'hex' => 'ffd8ff',             'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'x-fmt/391','name' => 'JPEG 2000',                'version' => null,  'ext' => 'jp2',  'mime' => 'image/jp2',                      'offset' => 0, 'hex' => '0000000c6a5020200d0a870a', 'risk' => 'low', 'is_preservation' => true],
        ['puid' => 'fmt/11',   'name' => 'PNG',                      'version' => null,  'ext' => 'png',  'mime' => 'image/png',                      'offset' => 0, 'hex' => '89504e470d0a1a0a',   'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'fmt/3',    'name' => 'GIF 87a',                  'version' => '87a', 'ext' => 'gif',  'mime' => 'image/gif',                      'offset' => 0, 'hex' => '474946383761',       'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/4',    'name' => 'GIF 89a',                  'version' => '89a', 'ext' => 'gif',  'mime' => 'image/gif',                      'offset' => 0, 'hex' => '474946383961',       'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/41',   'name' => 'BMP',                      'version' => null,  'ext' => 'bmp',  'mime' => 'image/bmp',                      'offset' => 0, 'hex' => '424d',               'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/119',  'name' => 'SVG',                      'version' => null,  'ext' => 'svg',  'mime' => 'image/svg+xml',                  'offset' => 0, 'hex' => '3c737667',           'risk' => 'low',    'is_preservation' => true],

        // Audio
        ['puid' => 'fmt/141',  'name' => 'WAVE',                     'version' => null,  'ext' => 'wav',  'mime' => 'audio/wav',                      'offset' => 0, 'hex' => '52494646',           'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'fmt/134',  'name' => 'MP3',                      'version' => null,  'ext' => 'mp3',  'mime' => 'audio/mpeg',                     'offset' => 0, 'hex' => '494433',             'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/279',  'name' => 'FLAC',                     'version' => null,  'ext' => 'flac', 'mime' => 'audio/flac',                     'offset' => 0, 'hex' => '664c6143',           'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'fmt/203',  'name' => 'Ogg Vorbis',               'version' => null,  'ext' => 'ogg',  'mime' => 'audio/ogg',                      'offset' => 0, 'hex' => '4f676753',           'risk' => 'medium', 'is_preservation' => false],

        // Video
        ['puid' => 'fmt/199',  'name' => 'MPEG-4 / MP4',             'version' => null,  'ext' => 'mp4',  'mime' => 'video/mp4',                      'offset' => 4, 'hex' => '6674797069736f6d',   'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/199',  'name' => 'MPEG-4 / MP4 (mp42)',      'version' => null,  'ext' => 'mp4',  'mime' => 'video/mp4',                      'offset' => 4, 'hex' => '667479706d703432',   'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/5',    'name' => 'AVI',                      'version' => null,  'ext' => 'avi',  'mime' => 'video/x-msvideo',                'offset' => 0, 'hex' => '52494646',           'risk' => 'high',   'is_preservation' => false],
        ['puid' => 'x-fmt/384','name' => 'Matroska',                 'version' => null,  'ext' => 'mkv',  'mime' => 'video/x-matroska',               'offset' => 0, 'hex' => '1a45dfa3',           'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/569',  'name' => 'QuickTime / MOV',          'version' => null,  'ext' => 'mov',  'mime' => 'video/quicktime',                'offset' => 4, 'hex' => '6674797071742020',   'risk' => 'medium', 'is_preservation' => false],

        // Office / OOXML — all start with PK\x03\x04 (zip), but more specific subtypes first.
        ['puid' => 'fmt/412',  'name' => 'Microsoft Word DOCX',      'version' => '2007','ext' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'offset' => 0, 'hex' => '504b0304', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/214',  'name' => 'Microsoft Excel XLSX',     'version' => '2007','ext' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',     'offset' => 0, 'hex' => '504b0304', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/215',  'name' => 'Microsoft PowerPoint PPTX','version' => '2007','ext' => 'pptx', 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'offset' => 0, 'hex' => '504b0304', 'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/40',   'name' => 'Microsoft Word DOC',       'version' => null,  'ext' => 'doc',  'mime' => 'application/msword',             'offset' => 0, 'hex' => 'd0cf11e0a1b11ae1',  'risk' => 'high',   'is_preservation' => false],
        ['puid' => 'fmt/61',   'name' => 'Microsoft Excel XLS',      'version' => null,  'ext' => 'xls',  'mime' => 'application/vnd.ms-excel',       'offset' => 0, 'hex' => 'd0cf11e0a1b11ae1',  'risk' => 'high',   'is_preservation' => false],
        ['puid' => 'fmt/126',  'name' => 'Microsoft PowerPoint PPT', 'version' => null,  'ext' => 'ppt',  'mime' => 'application/vnd.ms-powerpoint',  'offset' => 0, 'hex' => 'd0cf11e0a1b11ae1',  'risk' => 'high',   'is_preservation' => false],

        // Archives
        ['puid' => 'x-fmt/263','name' => 'ZIP',                      'version' => null,  'ext' => 'zip',  'mime' => 'application/zip',                'offset' => 0, 'hex' => '504b0304',           'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'x-fmt/265','name' => 'TAR',                      'version' => null,  'ext' => 'tar',  'mime' => 'application/x-tar',              'offset' => 257, 'hex' => '7573746172',       'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'fmt/484',  'name' => '7-Zip',                    'version' => null,  'ext' => '7z',   'mime' => 'application/x-7z-compressed',    'offset' => 0, 'hex' => '377abcaf271c',       'risk' => 'medium', 'is_preservation' => false],
        ['puid' => 'x-fmt/264','name' => 'RAR',                      'version' => null,  'ext' => 'rar',  'mime' => 'application/vnd.rar',            'offset' => 0, 'hex' => '526172211a07',       'risk' => 'high',   'is_preservation' => false],

        // Email
        ['puid' => 'fmt/278',  'name' => 'EML',                      'version' => null,  'ext' => 'eml',  'mime' => 'message/rfc822',                 'offset' => 0, 'hex' => '52656365697665643a',  'risk' => 'low',    'is_preservation' => true],
    ];

    /**
     * Extension/MIME fallback when magic-bytes don't match.
     *
     * @var array<int, array{puid:string, name:string, version:?string, ext:string, mime:string, risk:string, is_preservation:bool}>
     */
    public const EXTENSION_MAP = [
        ['puid' => 'fmt/101',  'name' => 'XML',                      'version' => '1.0', 'ext' => 'xml',  'mime' => 'application/xml',                'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'fmt/96',   'name' => 'HTML',                     'version' => null,  'ext' => 'html', 'mime' => 'text/html',                      'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'x-fmt/18', 'name' => 'CSV',                      'version' => null,  'ext' => 'csv',  'mime' => 'text/csv',                       'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'x-fmt/111','name' => 'Plain text',               'version' => null,  'ext' => 'txt',  'mime' => 'text/plain',                     'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'fmt/817',  'name' => 'JSON',                     'version' => null,  'ext' => 'json', 'mime' => 'application/json',               'risk' => 'low',    'is_preservation' => true],
        ['puid' => 'fmt/0',    'name' => 'Unknown / generic binary', 'version' => null,  'ext' => '',     'mime' => 'application/octet-stream',       'risk' => 'unknown','is_preservation' => false],
    ];

    /**
     * Identify a single file by path.
     *
     * @return array{puid:string, name:string, version:?string, mime:string, ext:?string,
     *               confidence:string, basis:string, risk:string, is_preservation:bool}
     */
    public function identifyFile(string $filePath): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            return $this->unknownResult('file missing or unreadable');
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // 1. Magic-bytes match (highest confidence) with ambiguous-zip disambiguation.
        $sample = $this->readSample($filePath, 512);
        if ($sample !== null) {
            foreach (self::MAGIC_SIGNATURES as $sig) {
                if ($this->matchesSignature($sample, $sig['offset'], $sig['hex'])) {
                    // ZIP-magic ambiguity: PK\x03\x04 is shared by ZIP + OOXML (DOCX/XLSX/PPTX) + JAR + many others.
                    // Resolve by file extension. Without an extension we cannot tell from the wire bytes alone
                    // (which is why DROID inspects [Content_Types].xml inside the zip — a future enhancement).
                    if (in_array($sig['puid'], ['fmt/412', 'fmt/214', 'fmt/215', 'x-fmt/263'], true)) {
                        $resolved = $this->disambiguateZipFamily($ext);
                        if ($resolved !== null) {
                            return [
                                'puid'             => $resolved['puid'],
                                'name'             => $resolved['name'],
                                'version'          => $resolved['version'] ?? null,
                                'mime'             => $resolved['mime'],
                                'ext'              => $ext ?: ($resolved['ext'] ?? null),
                                'confidence'       => $ext ? 'high' : 'medium',
                                'basis'            => sprintf('zip magic + extension match (.%s)', $ext ?: 'none'),
                                'risk'             => $resolved['risk'],
                                'is_preservation'  => (bool) $resolved['is_preservation'],
                            ];
                        }
                        // No extension — return generic ZIP.
                        $generic = $this->findRow(self::MAGIC_SIGNATURES, fn($r) => $r['puid'] === 'x-fmt/263');
                        return [
                            'puid'             => $generic['puid'],
                            'name'             => $generic['name'],
                            'version'          => $generic['version'] ?? null,
                            'mime'             => $generic['mime'],
                            'ext'              => $ext ?: null,
                            'confidence'       => 'medium',
                            'basis'            => 'zip magic, no extension to disambiguate OOXML/JAR/EPUB',
                            'risk'             => $generic['risk'],
                            'is_preservation'  => (bool) $generic['is_preservation'],
                        ];
                    }

                    // d0cf11e0... is shared by DOC/XLS/PPT (Compound Document). Same disambiguation by extension.
                    if (in_array($sig['puid'], ['fmt/40', 'fmt/61', 'fmt/126'], true)) {
                        $resolved = $this->disambiguateCompoundDoc($ext);
                        if ($resolved !== null) {
                            return [
                                'puid'             => $resolved['puid'],
                                'name'             => $resolved['name'],
                                'version'          => $resolved['version'] ?? null,
                                'mime'             => $resolved['mime'],
                                'ext'              => $ext,
                                'confidence'       => 'high',
                                'basis'            => sprintf('compound-doc magic + extension match (.%s)', $ext),
                                'risk'             => $resolved['risk'],
                                'is_preservation'  => (bool) $resolved['is_preservation'],
                            ];
                        }
                    }

                    return [
                        'puid'             => $sig['puid'],
                        'name'             => $sig['name'],
                        'version'          => $sig['version'] ?? null,
                        'mime'             => $sig['mime'] ?? 'application/octet-stream',
                        'ext'              => $sig['ext'] ?? null,
                        'confidence'       => 'high',
                        'basis'            => sprintf('magic-bytes match at offset %d (%s)', $sig['offset'], $sig['hex']),
                        'risk'             => $sig['risk'],
                        'is_preservation'  => (bool) $sig['is_preservation'],
                    ];
                }
            }
        }

        // 2. Extension match.
        if ($ext !== '') {
            // Check signatures' extension field first (so we get the richer name).
            foreach (self::MAGIC_SIGNATURES as $sig) {
                if (($sig['ext'] ?? '') === $ext) {
                    return [
                        'puid'             => $sig['puid'],
                        'name'             => $sig['name'],
                        'version'          => $sig['version'] ?? null,
                        'mime'             => $sig['mime'] ?? 'application/octet-stream',
                        'ext'              => $ext,
                        'confidence'       => 'medium',
                        'basis'            => 'extension match (.' . $ext . ')',
                        'risk'             => $sig['risk'],
                        'is_preservation'  => (bool) $sig['is_preservation'],
                    ];
                }
            }
            foreach (self::EXTENSION_MAP as $row) {
                if ($row['ext'] === $ext) {
                    return [
                        'puid'             => $row['puid'],
                        'name'             => $row['name'],
                        'version'          => $row['version'] ?? null,
                        'mime'             => $row['mime'],
                        'ext'              => $ext,
                        'confidence'       => 'medium',
                        'basis'            => 'extension match (.' . $ext . ')',
                        'risk'             => $row['risk'],
                        'is_preservation'  => (bool) $row['is_preservation'],
                    ];
                }
            }
        }

        // 3. PHP fileinfo MIME guess (lower confidence).
        $mime = function_exists('mime_content_type') ? @mime_content_type($filePath) : false;
        if (is_string($mime) && $mime !== '') {
            return [
                'puid'             => 'fmt/0',
                'name'             => 'Unknown — best-guess MIME',
                'version'          => null,
                'mime'             => $mime,
                'ext'              => $ext ?: null,
                'confidence'       => 'low',
                'basis'            => 'fileinfo mime guess (' . $mime . ')',
                'risk'             => 'unknown',
                'is_preservation'  => false,
            ];
        }

        return $this->unknownResult('no signature, no extension, no fileinfo match');
    }

    /**
     * Identify a digital_object by id, persist the result, and bump
     * preservation_format.
     *
     * @return array  identification result + persistence ids
     */
    public function identifyDigitalObject(int $digitalObjectId): array
    {
        $do = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->select('id', 'path', 'name', 'mime_type', 'object_id')
            ->first();

        if (! $do) {
            throw new \RuntimeException('digital_object not found: ' . $digitalObjectId);
        }

        $absolute = $this->resolveAbsolutePath($do->path, $do->name);
        $result = $absolute
            ? $this->identifyFile($absolute)
            : $this->unknownResult('digital_object on-disk path not resolvable');

        $formatId = $this->upsertFormat($result);

        DB::table('preservation_object_format')->insert([
            'digital_object_id'    => $digitalObjectId,
            'format_id'            => $formatId,
            'puid'                 => $result['puid'],
            'mime_type'            => $result['mime'],
            'format_name'          => $result['name'],
            'format_version'       => $result['version'],
            'identification_tool'  => 'heratio-pronom',
            'identification_date'  => Carbon::now(),
            'confidence'           => $result['confidence'],
            'basis'                => $result['basis'],
        ]);

        return array_merge($result, [
            'digital_object_id' => $digitalObjectId,
            'format_id'         => $formatId,
        ]);
    }

    /**
     * Bulk-classify digital_objects that have no preservation_object_format row yet.
     *
     * @return array{identified:int, skipped:int, failed:int}
     */
    public function batchIdentify(int $limit = 1000): array
    {
        $alreadyIdentified = DB::table('preservation_object_format')->pluck('digital_object_id')->all();
        $candidates = DB::table('digital_object')
            ->whereNotIn('id', $alreadyIdentified ?: [0])
            ->whereNotNull('path')
            ->limit($limit)
            ->pluck('id');

        $identified = 0; $skipped = 0; $failed = 0;
        foreach ($candidates as $id) {
            try {
                $r = $this->identifyDigitalObject((int) $id);
                if ($r['puid'] !== 'fmt/0') {
                    $identified++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('preservation: PRONOM identify failed', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }
        return ['identified' => $identified, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * Format risk-distribution stats for the dashboard.
     *
     * @return array<int, object>  rows: risk_level, count
     */
    public function riskDistribution(): array
    {
        return DB::table('preservation_object_format as pof')
            ->leftJoin('preservation_format as pf', 'pf.id', '=', 'pof.format_id')
            ->selectRaw('COALESCE(pf.risk_level, "unknown") as risk_level, COUNT(*) as count')
            ->groupBy('risk_level')
            ->get()
            ->all();
    }

    /* -------------------------------------------------------------------- */

    protected function readSample(string $filePath, int $bytes = 512): ?string
    {
        $fh = @fopen($filePath, 'rb');
        if (! $fh) {
            return null;
        }
        $sample = fread($fh, $bytes);
        fclose($fh);
        return $sample === false ? null : $sample;
    }

    protected function matchesSignature(string $sample, int $offset, string $hex): bool
    {
        $bin = @hex2bin($hex);
        if ($bin === false) {
            return false;
        }
        if (strlen($sample) < $offset + strlen($bin)) {
            return false;
        }
        return substr($sample, $offset, strlen($bin)) === $bin;
    }

    protected function upsertFormat(array $r): int
    {
        $existing = DB::table('preservation_format')
            ->where('puid', $r['puid'])
            ->where('format_version', $r['version'])
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }
        return (int) DB::table('preservation_format')->insertGetId([
            'puid'                  => $r['puid'],
            'mime_type'             => $r['mime'],
            'format_name'           => $r['name'],
            'format_version'        => $r['version'],
            'extension'             => $r['ext'] ?? null,
            'risk_level'            => $r['risk'],
            'is_preservation_format'=> $r['is_preservation'] ? 1 : 0,
            'preservation_action'   => $r['is_preservation'] ? 'retain' : 'monitor',
        ]);
    }

    protected function resolveAbsolutePath(?string $path, ?string $name): ?string
    {
        if ($path === null || $name === null) {
            return null;
        }
        $rel = ltrim($path, '/') . $name;
        $candidates = [
            rtrim((string) config('heratio.uploads_path', config('heratio.storage_path') . '/uploads'), '/') . '/' . $rel,
            '/usr/share/nginx/heratio/uploads/' . $rel,
        ];
        if (str_starts_with($path, '/uploads/')) {
            $candidates[] = '/usr/share/nginx/heratio/public' . $path . $name;
        }
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }
        return null;
    }

    protected function unknownResult(string $reason): array
    {
        return [
            'puid'             => 'fmt/0',
            'name'             => 'Unknown',
            'version'          => null,
            'mime'             => 'application/octet-stream',
            'ext'              => null,
            'confidence'       => 'low',
            'basis'            => $reason,
            'risk'             => 'unknown',
            'is_preservation'  => false,
        ];
    }

    /**
     * Resolve PK\x03\x04 magic by extension: docx/xlsx/pptx/zip/jar/epub etc.
     */
    protected function disambiguateZipFamily(string $ext): ?array
    {
        return match ($ext) {
            'docx' => ['puid' => 'fmt/412', 'name' => 'Microsoft Word DOCX',     'version' => '2007', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'risk' => 'medium', 'is_preservation' => false, 'ext' => 'docx'],
            'xlsx' => ['puid' => 'fmt/214', 'name' => 'Microsoft Excel XLSX',    'version' => '2007', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',     'risk' => 'medium', 'is_preservation' => false, 'ext' => 'xlsx'],
            'pptx' => ['puid' => 'fmt/215', 'name' => 'Microsoft PowerPoint PPTX','version' => '2007', 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'risk' => 'medium', 'is_preservation' => false, 'ext' => 'pptx'],
            'odt'  => ['puid' => 'fmt/291', 'name' => 'OpenDocument Text',       'version' => null,  'mime' => 'application/vnd.oasis.opendocument.text',       'risk' => 'low',    'is_preservation' => true,  'ext' => 'odt'],
            'ods'  => ['puid' => 'fmt/295', 'name' => 'OpenDocument Spreadsheet','version' => null,  'mime' => 'application/vnd.oasis.opendocument.spreadsheet','risk' => 'low',    'is_preservation' => true,  'ext' => 'ods'],
            'odp'  => ['puid' => 'fmt/293', 'name' => 'OpenDocument Presentation','version' => null, 'mime' => 'application/vnd.oasis.opendocument.presentation','risk' => 'low',    'is_preservation' => true,  'ext' => 'odp'],
            'epub' => ['puid' => 'fmt/483', 'name' => 'EPUB',                    'version' => null,  'mime' => 'application/epub+zip',                          'risk' => 'low',    'is_preservation' => true,  'ext' => 'epub'],
            'jar'  => ['puid' => 'x-fmt/418','name' => 'Java Archive',           'version' => null,  'mime' => 'application/java-archive',                      'risk' => 'medium', 'is_preservation' => false, 'ext' => 'jar'],
            'zip'  => ['puid' => 'x-fmt/263','name' => 'ZIP',                    'version' => null,  'mime' => 'application/zip',                               'risk' => 'medium', 'is_preservation' => false, 'ext' => 'zip'],
            default => null,
        };
    }

    /**
     * Resolve compound-document magic (D0 CF 11 E0) by extension: doc/xls/ppt/msg.
     */
    protected function disambiguateCompoundDoc(string $ext): ?array
    {
        return match ($ext) {
            'doc' => ['puid' => 'fmt/40',  'name' => 'Microsoft Word DOC',       'version' => null, 'mime' => 'application/msword',            'risk' => 'high',   'is_preservation' => false],
            'xls' => ['puid' => 'fmt/61',  'name' => 'Microsoft Excel XLS',      'version' => null, 'mime' => 'application/vnd.ms-excel',      'risk' => 'high',   'is_preservation' => false],
            'ppt' => ['puid' => 'fmt/126', 'name' => 'Microsoft PowerPoint PPT', 'version' => null, 'mime' => 'application/vnd.ms-powerpoint', 'risk' => 'high',   'is_preservation' => false],
            'msg' => ['puid' => 'x-fmt/430','name' => 'Outlook MSG',             'version' => null, 'mime' => 'application/vnd.ms-outlook',    'risk' => 'medium', 'is_preservation' => false],
            default => null,
        };
    }

    protected function findRow(array $rows, callable $predicate): ?array
    {
        foreach ($rows as $r) {
            if ($predicate($r)) {
                return $r;
            }
        }
        return null;
    }
}
