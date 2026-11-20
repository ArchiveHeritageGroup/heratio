<?php

/**
 * MediaRegenerateService - Heratio ahg-core (heratio#1771 follow-up)
 *
 * Makes an audio/video master's playback copies again from scratch, so a
 * change to the media settings (video watermark, encrypted HLS, codec,
 * ladder) reaches files that were converted before it. Per master:
 *
 *   - the old HLS folder (hls_<name>/: segments and playlists) and the
 *     master's HLS keys are removed;
 *   - MediaDerivativeService::generateForMaster() makes the reference copy,
 *     the poster or waveform and (when on) the HLS renditions again, encrypted
 *     with fresh keys when media_hls_encrypt is on. The files keep their
 *     names, so they are written over in place and their rows brought up to
 *     date;
 *   - reference and thumbnail rows left over from earlier runs (duplicates,
 *     or an older codec's copy) are removed, with their files when those sit
 *     beside the master.
 *
 * The master file is never written. Nothing is done when SharedUploadsGuard
 * refuses the master or any derivative path (another system's catalogue).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MediaRegenerateService
{
    public const KINDS = [DigitalObjectService::USAGE_REFERENCE, DigitalObjectService::USAGE_THUMBNAIL];

    /**
     * Master ids to regenerate: the given masters, the masters of the given
     * records, and with $allVideo every video master. Only audio/video masters.
     *
     * @return int[]
     */
    public static function targets(array $ids = [], array $records = [], bool $allVideo = false): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $records = array_values(array_filter(array_map('intval', $records)));
        if ($ids === [] && $records === [] && ! $allVideo) {
            return [];
        }
        $av = fn ($q) => $q->where('mime_type', 'like', 'video/%')->orWhere('mime_type', 'like', 'audio/%')
            ->orWhere('mime_type', 'application/mxf')
            ->orWhereIn('media_type_id', [DigitalObjectService::MEDIA_VIDEO, DigitalObjectService::MEDIA_AUDIO]);

        return DB::table('digital_object')->whereNull('parent_id')->where($av)
            ->where(function ($q) use ($ids, $records, $allVideo) {
                if ($ids !== []) {
                    $q->orWhereIn('id', $ids);
                }
                if ($records !== []) {
                    $q->orWhereIn('object_id', $records);
                }
                if ($allVideo) {
                    $q->orWhere(fn ($w) => $w->where('mime_type', 'like', 'video/%')->orWhere('mime_type', 'application/mxf')
                        ->orWhere('media_type_id', DigitalObjectService::MEDIA_VIDEO));
                }
            })
            ->orderBy('id')->pluck('id')->map('intval')->all();
    }

    /**
     * Regenerate one master. With $dryRun nothing is changed: the result says
     * what would be done.
     *
     * @return array{id: int, status: string, detail: string}
     */
    public static function regenerate(int $masterId, bool $dryRun = false): array
    {
        $out = fn (string $status, string $detail) => ['id' => $masterId, 'status' => $status, 'detail' => $detail];
        $master = DB::table('digital_object')->where('id', $masterId)->first();
        if (! $master || $master->parent_id) {
            return $out('skipped', 'not a master digital object');
        }
        if (! MediaProtection::isAudioVideo($master)) {
            return $out('skipped', 'not audio or video');
        }
        $masterPath = MediaDerivativeService::masterPath($master);
        if ($masterPath === null) {
            return $out('skipped', 'master file not found');
        }
        $dir = dirname($masterPath);
        $safe = MediaDerivativeService::safeName((string) $master->name);
        $hlsDir = $dir.'/hls_'.$safe;

        $rows = DB::table('digital_object')->where('parent_id', $masterId)->whereIn('usage_id', self::KINDS)->get();
        $planned = [];
        foreach (['mp4', 'mp3', 'm4a'] as $ext) {
            $planned[] = $dir.'/reference_'.$safe.'.'.$ext;
        }
        $planned[] = $dir.'/thumbnail_'.$safe.'.jpg';
        $planned[] = $dir.'/thumbnail_'.$safe.'.png';
        $planned[] = $hlsDir.'/master.m3u8';
        foreach ($rows as $r) {
            if ($p = DigitalObjectService::resolveDiskPath($r)) {
                $planned[] = $p;
            }
        }

        // Never write the master: a derivative name that is the master's own name is refused.
        $realMaster = realpath($masterPath) ?: $masterPath;
        foreach ($planned as $p) {
            if ((realpath($p) ?: $p) === $realMaster) {
                return self::log($out('refused', 'a derivative would be written over the master: '.basename($p)));
            }
        }
        foreach (array_merge([$masterPath], $planned) as $p) {
            if ($why = SharedUploadsGuard::refuse($p)) {
                return self::log($out('refused', $why));
            }
        }

        if ($dryRun) {
            return $out('would-regenerate', sprintf('%d derivative row(s), HLS %s, watermark %s, encryption %s', $rows->count(),
                is_dir($hlsDir) ? 'present' : 'absent', MediaProtection::watermarkEnabled() ? 'on' : 'off',
                MediaProtection::encryptionEnabled() ? 'on' : 'off'));
        }

        // Old HLS segments, playlists and keys go first: whatever is made now replaces them.
        self::removeDir($hlsDir);
        MediaProtection::dropKeys($masterId);
        @rmdir(MediaProtection::keysDir().'/'.$masterId);

        MediaDerivativeService::startTracking();
        try {
            $made = MediaDerivativeService::generateForMaster($masterId);
        } catch (\Throwable $e) {
            return self::log($out('failed', $e->getMessage()));
        }
        $written = MediaDerivativeService::written();
        if ($made === 0 || $written === []) {
            return self::log($out('failed', 'no playback copy could be made (see the ffmpeg log)'));
        }

        // Rows the run did not write are leftovers: duplicates, or an older profile's copy.
        $keptNames = DB::table('digital_object')->whereIn('id', $written)->pluck('name')->all();
        $writtenKinds = DB::table('digital_object')->whereIn('id', $written)->pluck('usage_id')->map('intval')->all();
        $stale = DB::table('digital_object')->where('parent_id', $masterId)->whereIn('usage_id', $writtenKinds)
            ->whereNotIn('id', $written)->get();
        $removed = 0;
        foreach ($stale as $r) {
            $file = DigitalObjectService::resolveDiskPath($r);
            if ($file && ! in_array($r->name, $keptNames, true) && dirname(realpath($file) ?: $file) === (realpath($dir) ?: $dir)
                && (realpath($file) ?: $file) !== $realMaster && SharedUploadsGuard::refuse($file) === null) {
                @unlink($file);
            }
            DB::table('digital_object')->where('id', $r->id)->delete();
            DB::table('object')->where('id', $r->id)->delete();
            $removed++;
        }

        $hls = is_file($hlsDir.'/master.m3u8');
        $encrypted = $hls && str_contains((string) @file_get_contents((string) (glob($hlsDir.'/v*.m3u8')[0] ?? '')), '#EXT-X-KEY');

        return self::log($out('regenerated', sprintf('%d derivative(s) written, %d stale row(s) removed, HLS %s', count($written), $removed,
            $hls ? ($encrypted ? 'encrypted' : 'plain') : 'none')));
    }

    private static function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        array_map('unlink', array_filter(glob($dir.'/*') ?: [], 'is_file'));
        @rmdir($dir);
    }

    private static function log(array $result): array
    {
        $level = in_array($result['status'], ['refused', 'failed'], true) ? 'warning' : 'info';
        Log::$level('[media-regenerate] master '.$result['id'].': '.$result['status'].' - '.$result['detail']);

        return $result;
    }
}
