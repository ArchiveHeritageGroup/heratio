<?php

/**
 * MediaProtection - Heratio ahg-core (heratio#1771)
 *
 * Protection for audio and video beyond the publication / restriction gates:
 *
 *  1. Video watermark. When media_video_watermark_enabled is on and the
 *     record carries a watermark under the same rule the image watermark uses
 *     (WatermarkService::getWatermarkConfig: security classification, then the
 *     record's own setting, then the default), the playback MP4 and every HLS
 *     rendition get the watermark burnt in with an ffmpeg overlay (the
 *     watermark image) or drawtext (the configured text or the site name).
 *     The master file is never changed. While a record's video is watermarked,
 *     its original goes only to those MasterAccess releases originals to, so
 *     the clean file is not one URL away.
 *
 *  2. Encrypted HLS. When media_hls_encrypt is on, HLS segments are encrypted
 *     with AES-128 (ffmpeg -hls_key_info_file), one fresh random key and IV per
 *     rendition, made again every time the renditions are made. Keys live under
 *     heratio.media_keys_path, outside the web root, and are served only by the
 *     /media-streaming/hls-key route, which runs mayPlay() below.
 *     This is encryption in transit with access-controlled keys. It is NOT DRM:
 *     a viewer who is allowed the key can save the decrypted stream. Widevine,
 *     FairPlay and PlayReady need a licence service and are not implemented.
 *
 *  3. Cleared for release. An audio/video master can be held (pending or
 *     withheld) until a person clears it; media_clearance_required makes every
 *     audio/video master wait for clearance. Uncleared media plays only for
 *     staff. Clearing records who and when (media_release_clearance) and
 *     writes an audit entry (security_audit_log).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use AhgCore\Support\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MediaProtection
{
    public const TABLE = 'media_release_clearance';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_WITHHELD = 'withheld';

    /** Video watermark positions; anything else (including the image-only "repeat") falls back to bottom-right. */
    public const POSITIONS = ['top-left', 'top-center', 'top-right', 'center', 'bottom-left', 'bottom-center', 'bottom-right'];

    /** Extensions of audio/video files and HLS parts - the only paths the /uploads check below looks at. */
    private const AV_EXT = ['mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mxf', 'dv', 'mts', 'm2ts', 'wmv', 'asf', 'flv', '3gp',
        'mpg', 'mpeg', 'ts', 'vob', 'ogv', 'm3u8', 'mp3', 'm4a', 'wav', 'flac', 'aac', 'ogg', 'oga', 'opus', 'aif', 'aiff',
        'bwf', 'wma', 'amr', 'awb', 'caf', 'au', 'ac3', 'ra', 'mka'];

    // ---------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------

    public static function watermarkEnabled(): bool
    {
        return AhgSettingsService::getBool('media_video_watermark_enabled', false);
    }

    public static function encryptionEnabled(): bool
    {
        return AhgSettingsService::getBool('media_hls_encrypt', false);
    }

    public static function clearanceRequired(): bool
    {
        return AhgSettingsService::getBool('media_clearance_required', false);
    }

    // ---------------------------------------------------------------
    // 1. Video watermark
    // ---------------------------------------------------------------

    /**
     * The watermark to burn into record $ioId's video renditions, or null.
     *
     * @return array{kind: string, image?: string, text?: string, position: string, opacity: float}|null
     */
    public static function videoWatermark(int $ioId): ?array
    {
        if ($ioId <= 1 || ! self::watermarkEnabled() || ! class_exists(\AhgMediaProcessing\Services\WatermarkService::class)) {
            return null;
        }
        try {
            $cfg = app(\AhgMediaProcessing\Services\WatermarkService::class)->getWatermarkConfig($ioId);
        } catch (\Throwable) {
            return null;
        }
        if (! $cfg) {
            return null;
        }

        $position = strtolower(str_replace([' ', '_'], '-', trim((string) AhgSettingsService::get('media_video_watermark_position', 'bottom-right'))));
        $out = [
            'position' => in_array($position, self::POSITIONS, true) ? $position : 'bottom-right',
            'opacity' => max(0.05, min(1.0, (float) (AhgSettingsService::get('media_video_watermark_opacity', '0.4') ?: 0.4))),
        ];

        if (AhgSettingsService::get('media_video_watermark_type', 'image') !== 'text') {
            foreach ([public_path(ltrim((string) ($cfg['image'] ?? ''), '/')), (string) ($cfg['image'] ?? '')] as $file) {
                if ($file !== '' && is_file($file)) {
                    return $out + ['kind' => 'image', 'image' => $file];
                }
            }
        }
        $text = trim((string) AhgSettingsService::get('media_video_watermark_text', ''));

        return $out + ['kind' => 'text', 'text' => $text !== '' ? $text : self::siteName()];
    }

    /**
     * An ffmpeg filter graph that takes [0:v] (and [1:v], the watermark image,
     * when 'inputs' is used) and ends in [$out]. The watermark is laid on the
     * full-size frame, before any scaling, so every rendition carries it at the
     * same proportion. Delete the 'cleanup' files after the ffmpeg run.
     *
     * @return array{inputs: string, graph: string, cleanup: string[]}
     */
    public static function overlayGraph(array $wm, int $sourceHeight, string $out = 'wm'): array
    {
        $op = rtrim(rtrim(sprintf('%.2f', $wm['opacity']), '0'), '.');
        if ($wm['kind'] === 'image') {
            $h = max(16, (int) round(($sourceHeight > 0 ? $sourceHeight : 480) / 8));
            [$x, $y] = self::place($wm['position'], 'W', 'H', 'w', 'h');

            return [
                'inputs' => '-i '.escapeshellarg($wm['image']),
                'graph' => "[1:v]scale=-1:{$h},format=rgba,colorchannelmixer=aa={$op}[wmimg];[0:v][wmimg]overlay=x={$x}:y={$y}[{$out}]",
                'cleanup' => [],
            ];
        }

        // The text goes through a file with expansion off, so no character in a
        // site name can break out of the filter or be read as a %{} expression.
        $file = sys_get_temp_dir().'/ahg-wm-'.bin2hex(random_bytes(6)).'.txt';
        file_put_contents($file, $wm['text']);
        [$x, $y] = self::place($wm['position'], 'w', 'h', 'tw', 'th');

        return [
            'inputs' => '',
            'graph' => "[0:v]drawtext=textfile='{$file}':expansion=none:fontsize=h/16:fontcolor=white@{$op}"
                .":borderw=2:bordercolor=black@{$op}:x={$x}:y={$y}[{$out}]",
            'cleanup' => [$file],
        ];
    }

    /** x/y expressions for a position; $W/$H = frame, $w/$h = the watermark. */
    private static function place(string $position, string $W, string $H, string $w, string $h): array
    {
        $m = "{$W}*0.03";
        $x = str_ends_with($position, 'left') ? $m : (str_ends_with($position, 'right') ? "{$W}-{$w}-{$m}" : "({$W}-{$w})/2");
        $my = "{$H}*0.03";
        $y = str_starts_with($position, 'top') ? $my : (str_starts_with($position, 'bottom') ? "{$H}-{$h}-{$my}" : "({$H}-{$h})/2");

        return [$x, $y];
    }

    private static function siteName(): string
    {
        try {
            $id = DB::table('setting')->where('name', 'siteTitle')->value('id');
            $name = $id ? trim((string) DB::table('setting_i18n')->where('id', $id)->where('culture', 'en')->value('value')) : '';
            if ($name !== '') {
                return $name;
            }
        } catch (\Throwable) {
            // fall through
        }

        return (string) config('app.name', 'Heratio');
    }

    // ---------------------------------------------------------------
    // 2. HLS keys (AES-128)
    // ---------------------------------------------------------------

    public static function keysDir(): string
    {
        return rtrim((string) (config('heratio.media_keys_path') ?: storage_path('app/media-keys')), '/');
    }

    public static function keyPath(int $masterId, int $rendition): string
    {
        return self::keysDir().'/'.$masterId.'/'.$rendition.'.key';
    }

    /** The URI written into the playlist; root-relative so it works on every host name. */
    public static function keyUri(int $masterId, int $rendition): string
    {
        return '/media-streaming/hls-key/'.$masterId.'/'.$rendition;
    }

    /** Drop every key of a master; called before its renditions are made again (rotation). */
    public static function dropKeys(int $masterId): void
    {
        $dir = self::keysDir().'/'.$masterId;
        array_map('unlink', glob($dir.'/*') ?: []);
    }

    /**
     * A fresh random key and IV for one rendition, and the ffmpeg key info
     * file that names them (URI, key file, IV). Returns the key info path,
     * which the caller deletes after ffmpeg has run, or null on failure.
     */
    public static function newKeyInfo(int $masterId, int $rendition): ?string
    {
        $dir = self::keysDir().'/'.$masterId;
        if (! is_dir($dir) && ! @mkdir($dir, 0750, true)) {
            return null;
        }
        $key = self::keyPath($masterId, $rendition);
        if (@file_put_contents($key, random_bytes(16)) !== 16) {
            return null;
        }
        @chmod($key, 0640);
        $info = $dir.'/'.$rendition.'.keyinfo';
        file_put_contents($info, self::keyUri($masterId, $rendition)."\n".$key."\n".bin2hex(random_bytes(16))."\n");

        return $info;
    }

    // ---------------------------------------------------------------
    // Access: one rule for the stream route and the key route
    // ---------------------------------------------------------------

    /** Administrators, editors and contributors: they already see drafts, and uncleared media. */
    public static function isStaff(?object $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user !== null && (AclService::isAdministrator($user) || AclService::isEditor($user) || AclService::isContributor($user));
    }

    /** The master a digital object belongs to (itself when it is one). */
    public static function masterOf(?object $do): ?object
    {
        if (! $do) {
            return null;
        }
        if ($do->parent_id) {
            return DB::table('digital_object')->where('id', $do->parent_id)->first();
        }

        return $do;
    }

    /** The records a master belongs to: its own, or the link table for an attached object. */
    public static function recordIds(object $master): array
    {
        if ($master->object_id) {
            return [(int) $master->object_id];
        }

        return AttachedDigitalObjectService::available()
            ? DB::table(AttachedDigitalObjectService::TABLE)->where('digital_object_id', $master->id)->pluck('information_object_id')->map('intval')->all()
            : [];
    }

    public static function isAudioVideo(object $master): bool
    {
        $mime = strtolower((string) ($master->mime_type ?? ''));

        return str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/') || $mime === 'application/mxf'
            || in_array((int) ($master->media_type_id ?? 0), [DigitalObjectService::MEDIA_VIDEO, DigitalObjectService::MEDIA_AUDIO], true);
    }

    /**
     * May the current user play this master's media? $original: the bytes
     * asked for are the master file itself (the stream route on a master),
     * rather than an access rendition (playback copy, HLS, HLS key).
     *
     * The same gates as the /uploads file check (publication, ICIP/TK, ODRL
     * prohibition, embargo, security classification and tenant through
     * DisclosureGate; PII screening through FileScreeningGate), plus the ODRL
     * engine's own answer for this user, "Protect originals", the video
     * watermark (an original under a watermark is for those originals are
     * released to) and release clearance. Staff pass, as they do there.
     */
    public static function mayPlay(object $master, bool $original = false, ?object $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (self::isStaff($user)) {
            return true;
        }
        if (self::awaitsClearance($master)) {
            return false;
        }

        $ioIds = self::recordIds($master);
        if ($ioIds !== []) {
            $gate = app(DisclosureGate::class);
            $screening = class_exists(\AhgPrivacy\Services\FileScreeningGate::class) ? app(\AhgPrivacy\Services\FileScreeningGate::class) : null;
            $open = array_filter($ioIds, fn ($id) => $gate->allows($id) && ! ($screening && $screening->withholds($id)));
            if ($open === []) {
                return false;
            }
        }

        if (class_exists(\AhgResearch\Services\OdrlService::class)) {
            try {
                if (! app(\AhgResearch\Services\OdrlService::class)->isDigitalObjectPermitted((int) $master->id, 'use')) {
                    return false;
                }
            } catch (\Throwable) {
                return false; // fail closed: the policy engine is there but could not answer
            }
        }

        if ($original && $ioIds !== []) {
            $io = $ioIds[0];
            if (MasterAccess::previewOnly($io, $master)) {
                return false;
            }
            if (self::videoWatermark($io) !== null && ! MasterAccess::mayFetch($io, $user)) {
                return false;
            }
        }

        return true;
    }

    // ---------------------------------------------------------------
    // 3. Cleared for release
    // ---------------------------------------------------------------

    public static function clearance(int $masterId): ?object
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        return DB::table(self::TABLE)->where('digital_object_id', $masterId)->first();
    }

    /**
     * Is this audio/video master waiting for clearance? Held when it has a
     * clearance row that is not 'cleared', or when clearance is required and
     * it has never been cleared. Other media types are not affected.
     */
    public static function awaitsClearance(object $master): bool
    {
        if (! self::isAudioVideo($master) || ! Schema::hasTable(self::TABLE)) {
            return false;
        }
        $row = self::clearance((int) $master->id);
        if ($row) {
            return $row->status !== self::STATUS_CLEARED;
        }

        return self::clearanceRequired();
    }

    /**
     * Clear a master for release, or hold it back, and audit who, when and why.
     */
    public static function setClearance(int $masterId, bool $cleared, ?string $note = null): void
    {
        $master = DB::table('digital_object')->where('id', $masterId)->first();
        if (! $master || $master->parent_id) {
            throw new \InvalidArgumentException('Clearance is recorded on a master digital object.');
        }
        $old = self::clearance($masterId);
        $now = now();
        $row = [
            'object_id' => $master->object_id,
            'status' => $cleared ? self::STATUS_CLEARED : self::STATUS_WITHHELD,
            'note' => $note,
            'cleared_by' => $cleared ? auth()->id() : null,
            'cleared_at' => $cleared ? $now : null,
            'updated_by' => auth()->id(),
            'updated_at' => $now,
        ];
        if ($old) {
            DB::table(self::TABLE)->where('id', $old->id)->update($row);
        } else {
            DB::table(self::TABLE)->insert($row + ['digital_object_id' => $masterId, 'created_at' => $now]);
        }

        AuditLog::captureSecondaryMutation($masterId, 'digital_object', $cleared ? 'media_cleared_for_release' : 'media_release_withheld', [
            'record_id' => $master->object_id,
            'before' => $old ? ['status' => $old->status] : null,
            'after' => ['status' => $row['status']],
            'note' => $note,
        ]);
    }

    // ---------------------------------------------------------------
    // /uploads gate and player
    // ---------------------------------------------------------------

    /**
     * The master behind an /uploads path, and whether the path is the master
     * file itself. HLS parts (hls_<name>/...) belong to the master they were
     * made from, as in RedactionRenderService.
     *
     * @return array{master: object, original: bool}|null
     */
    public static function resolvePath(string $path): ?array
    {
        $path = '/'.ltrim(rawurldecode((string) strtok($path, '?')), '/');
        if (str_contains($path, '..') || ! Schema::hasTable('digital_object')) {
            return null;
        }
        $name = basename($path);
        $dir = dirname($path);
        $hls = null;
        if (preg_match('#^(.+)/hls_([^/]+)$#', $dir, $m)) {
            [$dir, $hls] = [$m[1], $m[2]];
        }
        $dir = rtrim($dir, '/').'/';

        $q = DB::table('digital_object')->whereIn('path', [$dir, ltrim($dir, '/')]);
        $hls === null
            ? $q->where('name', $name)
            : $q->where(fn ($w) => $w->where('name', 'reference_'.$hls.'.mp4')->orWhere('name', 'like', 'master_'.addcslashes($hls, '\\%_').'.%'));
        $row = $q->orderByRaw('parent_id IS NULL')->first();
        $master = self::masterOf($row);

        return $master ? ['master' => $master, 'original' => $hls === null && ! $row->parent_id] : null;
    }

    /**
     * For the /uploads file check: refuse this audio/video file? Yes when its
     * master awaits clearance, or when it is the original of a watermarked
     * video and the viewer is not released originals. Cheap for everything
     * else: other extensions return at once.
     */
    public static function refusesPath(string $path): bool
    {
        $ext = strtolower(pathinfo((string) strtok($path, '?'), PATHINFO_EXTENSION));
        if ($path === '' || ! in_array($ext, self::AV_EXT, true) || self::isStaff()) {
            return false;
        }
        $hit = self::resolvePath($path);
        if (! $hit) {
            return false;
        }
        if (self::awaitsClearance($hit['master'])) {
            return true;
        }
        if ($hit['original']) {
            foreach (self::recordIds($hit['master']) as $io) {
                if (self::videoWatermark($io) !== null && ! MasterAccess::mayFetch($io)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * What the media player needs: whether this viewer sees the media at all
     * (clearance), whether the master must be left out of the sources
     * (watermarked video), and for staff the clearance state to show.
     *
     * @return array{master: ?object, withheld: bool, hideOriginal: bool, staff: bool, clearance: ?object, needsClearance: bool}
     */
    public static function playerState(string $src): array
    {
        $out = ['master' => null, 'withheld' => false, 'hideOriginal' => false, 'staff' => false, 'clearance' => null, 'needsClearance' => false];
        if (! str_starts_with($src, '/uploads/')) {
            return $out;
        }
        try {
            $hit = self::resolvePath($src);
            if (! $hit || ! self::isAudioVideo($hit['master'])) {
                return $out;
            }
            $m = $hit['master'];
            $out['master'] = $m;
            $out['staff'] = self::isStaff();
            $out['clearance'] = self::clearance((int) $m->id);
            $out['needsClearance'] = self::awaitsClearance($m);
            $out['withheld'] = $out['needsClearance'] && ! $out['staff'];
            if (! $out['staff']) {
                foreach (self::recordIds($m) as $io) {
                    if (self::videoWatermark($io) !== null && ! MasterAccess::mayFetch($io)) {
                        $out['hideOriginal'] = true;
                    }
                }
            }
        } catch (\Throwable) {
            // Never let the protection lookup break the page; the gates still apply.
        }

        return $out;
    }
}
