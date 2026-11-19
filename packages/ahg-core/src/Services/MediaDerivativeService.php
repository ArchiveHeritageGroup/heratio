<?php

/**
 * MediaDerivativeService - Heratio core (P7)
 *
 * Generates format-appropriate derivatives for audio / video / 3D masters.
 * Complements DigitalObjectService::generateDerivativesForMaster(), which
 * handles still-image formats.
 *
 *   Audio → playback copy (MP3 or AAC, bitrate from settings) + waveform PNG
 *   Video → playback MP4 (H.264 / H.265 / AV1 at the configured height, never
 *           upscaled) + poster frame, and optionally an HLS ladder of
 *           renditions in hls_<name>/ (heratio#1752-#1755)
 *
 * The profile comes from the media settings group (media_video_codec,
 * media_video_height, media_audio_codec, media_audio_bitrate,
 * media_hls_enabled, media_rendition_ladder); see profile(). The defaults
 * reproduce the original fixed 480p H.264 / 128 kbps MP3 behaviour.
 *   3D    → delegates to AhgThreeDModel\Services\ThreeDThumbnailService
 *
 * Derivatives are stored as digital_object rows with parent_id = master and
 * usage_id set to match the existing taxonomy (reference = 141, thumbnail
 * = 142). Files land next to the master in {uploads}/<io_id>/.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MediaDerivativeService
{
    /**
     * Dispatch derivative generation based on the master's mime type /
     * extension. Returns the number of derivatives successfully created.
     */
    public static function generateForMaster(int $masterId): int
    {
        $master = DB::table('digital_object')->where('id', $masterId)->first();
        if (! $master || ! $master->object_id) {
            return 0;
        }

        $uploadDir = config('heratio.uploads_path', DigitalObjectService::UPLOAD_DIR).'/'.$master->object_id;
        $masterPath = $uploadDir.'/'.$master->name;
        if (! is_file($masterPath)) {
            // Other layouts (uploads/r/<id>/, imported hashed folders such as
            // r/null/9/0/2/...): find the file from its stored path and write the
            // derivatives beside it, so these videos get their playable MP4 too.
            $resolved = DigitalObjectService::resolveDiskPath($master);
            if (! $resolved || ! is_file($resolved)) {
                return 0;
            }
            $masterPath = $resolved;
            $uploadDir = dirname($resolved);
        }

        $mime = (string) ($master->mime_type ?? '');
        $ext = strtolower(pathinfo($master->name, PATHINFO_EXTENSION));

        // 3D is delegated to the existing package.
        if (self::is3D($mime, $ext)) {
            try {
                if (class_exists(\AhgThreeDModel\Services\ThreeDThumbnailService::class)) {
                    $svc = app(\AhgThreeDModel\Services\ThreeDThumbnailService::class);

                    return $svc->createDerivatives((int) $masterId) ? 1 : 0;
                }
            } catch (\Throwable $e) {
                Log::info('[MediaDerivativeService] 3D thumbnail failed: '.$e->getMessage());
            }

            return 0;
        }

        $ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            Log::info('[MediaDerivativeService] ffmpeg not installed; skipping audio/video derivatives for DO '.$masterId);

            return 0;
        }

        $isAudio = self::isAudio($mime, $ext);
        // heratio#1756: a WMA (and any audio-only ASF/MP4/MKV) is detected as
        // video/x-ms-asf or similar. With no real picture it is audio: take the
        // audio route and record the master as audio, so the player and the
        // fallback list treat it as one.
        if (str_starts_with($mime, 'video/') && self::hasVideoStream($masterPath) === false) {
            $isAudio = true;
            self::retypeMaster($master, $mime === 'video/x-ms-asf' || in_array($ext, ['wma', 'asf'], true)
                ? 'audio/x-ms-wma'
                : (str_starts_with($mime, 'video/') ? 'audio/'.substr($mime, 6) : $mime), DigitalObjectService::MEDIA_AUDIO);
        }

        if ($isAudio) {
            return self::generateAudioDerivatives($ffmpeg, $masterId, $master, $masterPath, $uploadDir);
        }
        if (self::isVideo($mime, $ext)) {
            // heratio#1746: an MXF arrives as application/mxf, which the upload
            // records as "other" - so the record page showed no player at all.
            if (! str_starts_with($mime, 'video/') && (int) ($master->media_type_id ?? 0) !== DigitalObjectService::MEDIA_VIDEO) {
                self::retypeMaster($master, $mime, DigitalObjectService::MEDIA_VIDEO);
            }

            return self::generateVideoDerivatives($ffmpeg, $masterId, $master, $masterPath, $uploadDir);
        }
        if (self::isTiff($mime, $ext)) {
            return self::generateTiffDerivatives($masterId, $master, $masterPath, $uploadDir);
        }

        return 0;
    }

    /**
     * Derivatives for a TIFF master. A TIFF is NOT renderable by browsers, so the
     * old behaviour (only a pyramidal `.ptif` placed in the reference slot) left a
     * TIFF blank on any install without a running Cantaloupe image server - the
     * record page's inline "reference image" and the IIIF viewer's documented
     * "fall back to the JPEG reference" both had nothing to show.
     *
     * Fix: generate a flat, browser-viewable JPEG **reference (141)** and JPEG
     * **thumbnail (142)** from the first frame, so a TIFF renders on ANY install.
     * The pyramidal TIFF is still produced as a FILE on disk for a Cantaloupe
     * deep-zoom delegate where one exists, but it is no longer the displayed
     * reference (the IIIF identifier resolves to the master file, not this slot).
     * All ImageMagick calls are best-effort; a failure of any one never aborts the
     * others. Requires ImageMagick `convert`.
     */
    protected static function generateTiffDerivatives(int $masterId, object $master, string $masterPath, string $uploadDir): int
    {
        $convert = trim((string) @shell_exec('command -v convert 2>/dev/null'));
        if ($convert === '') {
            Log::info('[MediaDerivativeService] ImageMagick convert not installed; skipping TIFF derivatives for DO '.$masterId);

            return 0;
        }

        $baseName = pathinfo($master->name, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $baseName);
        if (str_starts_with($safe, 'master_')) {
            $safe = substr($safe, 7);
        }

        // First frame of a (possibly multi-page) TIFF. The [0] suffix is parsed by
        // ImageMagick itself, so escapeshellarg-quoting the whole token is safe.
        $src = escapeshellarg($masterPath.'[0]');
        $created = 0;

        // 1. Browser-viewable JPEG REFERENCE (usage 141) - the inline display image
        //    and the no-Cantaloupe fallback. Downscaled only (never upscaled).
        $ref = $uploadDir.'/reference_'.$safe.'.jpg';
        @shell_exec(sprintf(
            '%s %s -auto-orient -resize 3000x3000\> -quality 85 -colorspace sRGB %s 2>/dev/null',
            escapeshellcmd($convert), $src, escapeshellarg($ref)
        ));
        if (is_file($ref) && filesize($ref) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'reference_'.$safe.'.jpg',
                $master->path, 'image/jpeg', DigitalObjectService::MEDIA_IMAGE, DigitalObjectService::USAGE_REFERENCE, $ref);
            $created++;
        }

        // 2. JPEG THUMBNAIL (usage 142) for browse / result lists.
        $thumb = $uploadDir.'/thumbnail_'.$safe.'.jpg';
        @shell_exec(sprintf(
            '%s %s -auto-orient -thumbnail 400x400\> -quality 82 -colorspace sRGB %s 2>/dev/null',
            escapeshellcmd($convert), $src, escapeshellarg($thumb)
        ));
        if (is_file($thumb) && filesize($thumb) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'thumbnail_'.$safe.'.jpg',
                $master->path, 'image/jpeg', DigitalObjectService::MEDIA_IMAGE, DigitalObjectService::USAGE_THUMBNAIL, $thumb);
            $created++;
        }

        // 3. Pyramidal TIFF for Cantaloupe deep-zoom (performance optimisation
        //    where an image server is present). Kept as a FILE only - NOT the
        //    displayed reference, and not registered as a derivative row.
        $ptif = $uploadDir.'/pyramid_'.$safe.'.ptif';
        @shell_exec(sprintf(
            '%s %s -define tiff:tile-geometry=256x256 -compress jpeg -quality 85 ptif:%s 2>/dev/null',
            escapeshellcmd($convert), escapeshellarg($masterPath), escapeshellarg($ptif)
        ));

        return $created;
    }

    // ---------------------------------------------------------------
    // Audio
    // ---------------------------------------------------------------

    protected static function generateAudioDerivatives(string $ffmpeg, int $masterId, object $master, string $masterPath, string $uploadDir): int
    {
        $baseName = pathinfo($master->name, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $baseName);
        if (str_starts_with($safe, 'master_')) {
            $safe = substr($safe, 7);
        }

        $created = 0;

        // 1. Playback copy (codec + bitrate from the profile; MP3 128k by default).
        //    -vn drops cover art, which an MP3/M4A cannot carry as a stream.
        $p = self::profile();
        [$encoder, $outExt, $outMime] = self::AUDIO_CODECS[$p['audio_codec']];
        $mp3 = $uploadDir.'/reference_'.$safe.'.'.$outExt;
        $cmd = sprintf('%s -y -i %s -vn -codec:a %s -b:a %dk %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), $encoder, $p['audio_bitrate'], escapeshellarg($mp3));
        @shell_exec($cmd);
        if (is_file($mp3) && filesize($mp3) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'reference_'.$safe.'.'.$outExt,
                $master->path, $outMime, DigitalObjectService::MEDIA_AUDIO, DigitalObjectService::USAGE_REFERENCE, $mp3);
            $created++;
        }

        // 2. Waveform PNG thumbnail
        $png = $uploadDir.'/thumbnail_'.$safe.'.png';
        $cmd = sprintf('%s -y -i %s -filter_complex "showwavespic=s=640x120:colors=#555555" -frames:v 1 %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), escapeshellarg($png));
        @shell_exec($cmd);
        if (is_file($png) && filesize($png) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'thumbnail_'.$safe.'.png',
                $master->path, 'image/png', DigitalObjectService::MEDIA_IMAGE, DigitalObjectService::USAGE_THUMBNAIL, $png);
            $created++;
        }

        return $created;
    }

    // ---------------------------------------------------------------
    // Video
    // ---------------------------------------------------------------

    protected static function generateVideoDerivatives(string $ffmpeg, int $masterId, object $master, string $masterPath, string $uploadDir): int
    {
        $baseName = pathinfo($master->name, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $baseName);
        if (str_starts_with($safe, 'master_')) {
            $safe = substr($safe, 7);
        }

        $created = 0;

        // 1. Playback MP4 from the profile (480p H.264 by default). The height
        //    is a ceiling: a smaller source keeps its own size (never upscaled).
        $p = self::profile();
        $mp4 = $uploadDir.'/reference_'.$safe.'.mp4';
        $cmd = sprintf('%s -y -i %s -vf %s -c:v %s %s -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), escapeshellarg(self::scaleFilter($p['video_height'])),
            $p['video_encoder'], self::ENCODER_ARGS[$p['video_encoder']], escapeshellarg($mp4));
        @shell_exec($cmd);
        if (is_file($mp4) && filesize($mp4) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'reference_'.$safe.'.mp4',
                $master->path, 'video/mp4', DigitalObjectService::MEDIA_VIDEO, DigitalObjectService::USAGE_REFERENCE, $mp4);
            $created++;
            // 1b. HLS ladder beside it (off by default). Files only: the folder
            //     is judged by the /uploads gate as this reference copy.
            if ($p['hls']) {
                self::generateHls($ffmpeg, $masterPath, $uploadDir.'/hls_'.$safe, $p['ladder']);
            }
        }

        // 2. Poster frame (JPG thumbnail taken 1s in)
        $jpg = $uploadDir.'/thumbnail_'.$safe.'.jpg';
        $cmd = sprintf('%s -y -ss 00:00:01 -i %s -frames:v 1 -q:v 3 %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), escapeshellarg($jpg));
        @shell_exec($cmd);
        if (is_file($jpg) && filesize($jpg) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'thumbnail_'.$safe.'.jpg',
                $master->path, 'image/jpeg', DigitalObjectService::MEDIA_IMAGE, DigitalObjectService::USAGE_THUMBNAIL, $jpg);
            $created++;
        }

        return $created;
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    protected static function isAudio(string $mime, string $ext): bool
    {
        if (str_starts_with($mime, 'audio/')) {
            return true;
        }

        return in_array($ext, ['wav', 'flac', 'aac', 'ogg', 'mp3', 'm4a', 'aif', 'aiff', 'bwf',
            'wma', 'opus', 'amr', 'awb', 'alac', 'caf', 'au', 'ac3', 'ra', 'mka'], true);
    }

    protected static function isVideo(string $mime, string $ext): bool
    {
        if (str_starts_with($mime, 'video/') || $mime === 'application/mxf') {
            return true;
        }

        return in_array($ext, ['mov', 'mp4', 'mkv', 'webm', 'avi', 'mxf', 'dv', 'mts', 'm2ts',
            'wmv', 'asf', 'flv', '3gp', 'm4v', 'mpg', 'mpeg', 'ts', 'vob', 'ogv'], true);
    }

    /**
     * Media type id for an audio/video MIME type the generic resolver files as
     * "other" (MXF arrives as application/mxf), else null. heratio#1746: the
     * upload path calls this so an MXF gets its proxy job at upload time.
     */
    public static function avMediaTypeId(?string $mime): ?int
    {
        return in_array((string) $mime, ['application/mxf', 'application/x-mxf'], true) ? DigitalObjectService::MEDIA_VIDEO : null;
    }

    /**
     * True when the file has a real picture (cover art does not count), false
     * when ffprobe reads it and finds none, null when it cannot tell.
     */
    public static function hasVideoStream(string $path): ?bool
    {
        $out = trim((string) @shell_exec(sprintf('ffprobe -v error -show_entries stream=codec_type:stream_disposition=attached_pic -of csv=p=0 %s 2>/dev/null',
            escapeshellarg($path))));
        if ($out === '') {
            return null;
        }
        foreach (preg_split('/\R/', $out) as $line) {
            if (str_starts_with($line, 'video') && ! str_ends_with(trim($line), ',1')) {
                return true;
            }
        }

        return false;
    }

    /** Frame height of the first video stream, 0 when unknown. */
    public static function videoHeight(string $path): int
    {
        return (int) trim((string) @shell_exec(sprintf('ffprobe -v error -select_streams v:0 -show_entries stream=height -of csv=p=0 %s 2>/dev/null',
            escapeshellarg($path))));
    }

    private static function hasAudioStream(string $path): bool
    {
        return trim((string) @shell_exec(sprintf('ffprobe -v error -select_streams a -show_entries stream=index -of csv=p=0 %s 2>/dev/null',
            escapeshellarg($path)))) !== '';
    }

    /** Record the master as what the file really is (its type decides which player the record page shows). */
    private static function retypeMaster(object $master, string $mime, int $mediaTypeId): void
    {
        DB::table('digital_object')->where('id', $master->id)->update(['mime_type' => $mime, 'media_type_id' => $mediaTypeId]);
        $master->mime_type = $mime;
        $master->media_type_id = $mediaTypeId;
    }

    // ---------------------------------------------------------------
    // Playback profile, renditions and HLS (heratio#1752-#1755)
    // ---------------------------------------------------------------

    /** Standard ladder rungs (frame height => target video kbps for the H.264 HLS variants). */
    public const LADDER = [480 => 1400, 720 => 2800, 1080 => 5000, 1440 => 8000, 2160 => 14000];

    /** Playback video codec => ffmpeg encoders that can make it, in order of preference. */
    public const VIDEO_CODECS = ['h264' => ['libx264'], 'h265' => ['libx265'], 'av1' => ['libsvtav1', 'libaom-av1']];

    public const ENCODER_ARGS = [
        'libx264' => '-preset fast -crf 23',
        'libx265' => '-preset fast -crf 28 -tag:v hvc1',
        'libsvtav1' => '-preset 8 -crf 35',
        'libaom-av1' => '-cpu-used 8 -row-mt 1 -crf 35 -b:v 0',
    ];

    /** Playback audio codec => [encoder, extension, MIME type]. */
    public const AUDIO_CODECS = ['mp3' => ['libmp3lame', 'mp3', 'audio/mpeg'], 'aac' => ['aac', 'm4a', 'audio/mp4']];

    private static ?array $encoders = null;

    /** Encoder names this host's ffmpeg was built with. */
    public static function encoders(): array
    {
        if (self::$encoders === null) {
            preg_match_all('/^\s*[VAS][.A-Z]{5}\s+(\S+)/m', (string) @shell_exec('ffmpeg -hide_banner -encoders 2>/dev/null'), $m);
            self::$encoders = $m[1] ?? [];
        }

        return self::$encoders;
    }

    /** Playback video codecs this host can produce: codec => encoder. */
    public static function videoCodecs(): array
    {
        $out = [];
        foreach (self::VIDEO_CODECS as $codec => $candidates) {
            foreach ($candidates as $enc) {
                if (in_array($enc, self::encoders(), true)) {
                    $out[$codec] = $enc;
                    break;
                }
            }
        }

        return $out;
    }

    /** Playback audio codecs this host can produce. */
    public static function audioCodecs(): array
    {
        return array_keys(array_filter(self::AUDIO_CODECS, fn ($c) => in_array($c[0], self::encoders(), true)));
    }

    /**
     * The playback profile from the media settings. Anything unset, unknown or
     * not available on this host falls back to the original fixed profile.
     */
    public static function profile(): array
    {
        $codecs = self::videoCodecs();
        $codec = (string) AhgSettingsService::get('media_video_codec', 'h264');
        if (! isset($codecs[$codec])) {
            $codec = 'h264';
        }
        $audio = (string) AhgSettingsService::get('media_audio_codec', 'mp3');
        if (! isset(self::AUDIO_CODECS[$audio]) || ! in_array($audio, self::audioCodecs(), true)) {
            $audio = 'mp3';
        }

        return [
            'video_codec' => $codec,
            'video_encoder' => $codecs[$codec] ?? 'libx264',
            'video_height' => max(144, min(4320, AhgSettingsService::getInt('media_video_height', 480) ?: 480)),
            'audio_codec' => $audio,
            'audio_bitrate' => max(32, min(512, AhgSettingsService::getInt('media_audio_bitrate', 128) ?: 128)),
            'hls' => AhgSettingsService::getBool('media_hls_enabled', false),
            'ladder' => self::parseLadder((string) AhgSettingsService::get('media_rendition_ladder', '480,720,1080')),
        ];
    }

    /** "480, 720,1080" => [480, 720, 1080]; only the standard rungs. */
    public static function parseLadder(string $value): array
    {
        $h = array_values(array_intersect(array_keys(self::LADDER), array_map('intval', preg_split('/[\s,;]+/', $value))));

        return $h ?: [480];
    }

    /**
     * The rungs to make for a source of this height: never above the source.
     * A source smaller than every rung gets one variant at its own height.
     */
    public static function ladderFor(int $sourceHeight, array $ladder): array
    {
        if ($sourceHeight <= 0) {
            return [min($ladder)];
        }
        $fit = array_values(array_filter($ladder, fn ($h) => $h <= $sourceHeight));

        return $fit ?: [$sourceHeight - ($sourceHeight % 2)];
    }

    /** Scale to at most $height (even), keeping the aspect ratio; never upscale. */
    public static function scaleFilter(int $height): string
    {
        return "scale=-2:'trunc(min({$height},ih)/2)*2'";
    }

    /**
     * Package the master as HLS: one variant per rung (H.264 + AAC, which
     * every HLS player decodes), segments and playlists in $dir, and one
     * master.m3u8 listing them. Returns the master playlist path or null.
     */
    public static function generateHls(string $ffmpeg, string $masterPath, string $dir, array $ladder): ?string
    {
        $rungs = self::ladderFor(self::videoHeight($masterPath), $ladder);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            return null;
        }
        array_map('unlink', glob($dir.'/*') ?: []);
        $audio = self::hasAudioStream($masterPath);

        $n = count($rungs);
        $filter = '[0:v]split='.$n.implode('', array_map(fn ($i) => "[s$i]", range(0, $n - 1)));
        $maps = [];
        $streams = [];
        foreach ($rungs as $i => $h) {
            $kbps = self::LADDER[$h] ?? (int) max(400, round($h * 2.9));
            $filter .= ";[s$i]".self::scaleFilter($h)."[v$i]";
            $maps[] = sprintf('-map [v%d] -c:v:%d libx264 -b:v:%d %dk -maxrate:v:%d %dk -bufsize:v:%d %dk',
                $i, $i, $i, $kbps, $i, (int) ($kbps * 1.07), $i, (int) ($kbps * 1.5));
            if ($audio) {
                $maps[] = '-map 0:a:0';
            }
            $streams[] = "v:$i".($audio ? ",a:$i" : '').",name:$h";
        }

        $cmd = sprintf('%s -y -i %s -filter_complex %s %s %s -preset fast -pix_fmt yuv420p -force_key_frames %s -sc_threshold 0'
            .' -f hls -hls_time 6 -hls_playlist_type vod -hls_segment_filename %s -master_pl_name master.m3u8 -var_stream_map %s %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), escapeshellarg($filter), implode(' ', $maps),
            $audio ? '-c:a aac -b:a 128k -ac 2' : '', escapeshellarg('expr:gte(t,n_forced*6)'),
            escapeshellarg($dir.'/v%v_%03d.ts'), escapeshellarg(implode(' ', $streams)), escapeshellarg($dir.'/v%v.m3u8'));
        @shell_exec($cmd);

        return is_file($dir.'/master.m3u8') ? $dir.'/master.m3u8' : null;
    }

    // ---------------------------------------------------------------
    // Player lookups (theme::components.media-player)
    // ---------------------------------------------------------------

    /** Audio MIME types no mainstream browser plays: the record page leads with the playback copy instead. */
    public const BROWSER_UNPLAYABLE_AUDIO = ['audio/amr', 'audio/amr-wb', 'audio/3gpp', 'audio/x-ms-wma', 'audio/aiff', 'audio/x-aiff',
        'audio/basic', 'audio/x-au', 'audio/ac3', 'audio/x-pn-realaudio', 'audio/x-realaudio', 'audio/x-caf'];

    /**
     * What the player can use besides the URL it was given: the HLS master
     * playlist made for this file, and the playback copy beside a master.
     * Only plain /uploads/ URLs are looked up - a rerouted (redacted or
     * watermarked) URL gets nothing extra.
     *
     * @return array{hls: ?string, fallback: ?array{0: string, 1: string}}
     */
    public static function playbackAlternates(string $url, string $type): array
    {
        $out = ['hls' => null, 'fallback' => null];
        if (! str_starts_with($url, '/uploads/') || str_contains($url, '..')) {
            return $out;
        }
        $dir = dirname($url).'/';
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo(basename($url), PATHINFO_FILENAME));
        $safe = preg_replace('/^(master_|reference_)/', '', $safe);
        $exists = fn (string $path, string $name) => DigitalObjectService::resolveDiskPath((object) ['path' => $path, 'name' => $name]) !== null;

        if ($type === 'video' && $exists($dir.'hls_'.$safe.'/', 'master.m3u8')) {
            $out['hls'] = $dir.'hls_'.$safe.'/master.m3u8';
        }
        if (! str_starts_with(basename($url), 'reference_')) {
            $copies = $type === 'video' ? ['mp4' => 'video/mp4'] : array_column(array_map(fn ($c) => [$c[1], $c[2]], self::AUDIO_CODECS), 1, 0);
            foreach ($copies as $ext => $mime) {
                if ($exists($dir, 'reference_'.$safe.'.'.$ext)) {
                    $out['fallback'] = [$dir.'reference_'.$safe.'.'.$ext, $mime];
                    break;
                }
            }
        }

        return $out;
    }

    protected static function is3D(string $mime, string $ext): bool
    {
        if (str_starts_with($mime, 'model/')) {
            return true;
        }

        return in_array($ext, ['glb', 'gltf', 'obj', 'ply', 'stl', 'usdz', 'fbx'], true);
    }

    protected static function isTiff(string $mime, string $ext): bool
    {
        if ($mime === 'image/tiff' || $mime === 'image/tif') {
            return true;
        }

        return in_array($ext, ['tif', 'tiff'], true);
    }

    protected static function insertDerivative(int $parentId, int $ioId, string $filename, ?string $webPath, string $mime, int $mediaTypeId, int $usageId, string $diskPath): void
    {
        $byteSize = filesize($diskPath) ?: null;
        $checksum = hash_file('sha256', $diskPath);
        $now = now()->format('Y-m-d H:i:s');

        $doId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        DB::table('digital_object')->insert([
            'id' => $doId,
            'object_id' => $ioId,
            'usage_id' => $usageId,
            'mime_type' => $mime,
            'media_type_id' => $mediaTypeId,
            'name' => $filename,
            'path' => $webPath ?: ('/uploads/r/'.$ioId.'/'),
            'byte_size' => $byteSize,
            'checksum' => $checksum,
            'checksum_type' => 'sha256',
            'parent_id' => $parentId,
        ]);
    }
}
