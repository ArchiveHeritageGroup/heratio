<?php

/**
 * PublishPackageService - platform-ready publish packages (heratio#1761, GCIS 6.8.2).
 *
 * From an approved, public record that has video, audio or an image, staff
 * make one zip per chosen platform: the media sized to that platform's preset
 * (fitted to the aspect with a blurred background, never cropped), caption.txt
 * cut to the platform's caption length, and a thumbnail. Every package is a
 * row in media_publish_package, the distribution log: made, downloaded, and
 * "posted" once a person has posted it by hand and filled in the URL and date.
 *
 * Source: always the access copy (the reference MP4, which already carries the
 * video watermark when the record has one; the reference image or audio), never
 * the master, so a watermark carries over and an original never leaves.
 *
 * ROADMAP - direct posting. Posting straight to the platforms goes through the
 * YouTube Data API, the TikTok Content Posting API, the Meta Graph API
 * (Facebook, Instagram Reels), the X API and the WhatsApp Business Platform.
 * Each needs the institution's own accounts, an app registered with that
 * platform and, for most, the platform's app review before it may post. None
 * of that exists here, so nothing is posted by this code. The log is built so
 * a poster can fill the same rows later: set posted_via = 'api', external_id
 * and posted_url, and the status 'posted'.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgMediaStreaming\Services;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DigitalObjectService as DOS;
use AhgCore\Services\DisclosureGate;
use AhgCore\Services\MediaProtection;
use AhgCore\Support\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublishPackageService
{
    public const TABLE = 'media_publish_package';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_MADE = 'made';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DOWNLOADED = 'downloaded';

    public const STATUS_POSTED = 'posted';

    /** Default labels; the ahg_dropdown 'publish_status' rows win when present. */
    public const STATUS_LABELS = [
        self::STATUS_QUEUED => 'Queued',
        self::STATUS_MADE => 'Made',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_DOWNLOADED => 'Downloaded',
        self::STATUS_POSTED => 'Posted',
    ];

    /**
     * Platform presets, seeded into ahg_dropdown 'publish_platform' (metadata
     * holds everything but the label); the rows are what the code reads, so an
     * administrator can change a limit there when a platform changes it.
     *
     * Every preset: H.264 video + AAC audio in an MP4, yuv420p, faststart.
     * max_duration in seconds and max_bytes in bytes; null = no limit we apply.
     * caption_max in characters. image_as: what an image record becomes on that
     * platform - 'image' (a fitted JPG) or 'video' (a still clip of
     * still_seconds), for platforms that take only video.
     *
     * Limits as known in mid-2026, from the platforms' own help pages; the ones
     * marked UNSURE could not be confirmed and should be checked before a bid
     * relies on them:
     *  - YouTube: 12 h / 256 GB is for verified accounts; an unverified
     *    account is held to 15 minutes. Description 5000 characters.
     *  - TikTok: UNSURE. In-app uploads up to 10 minutes; the web uploader
     *    has allowed longer (up to 60 minutes) for some accounts. Caption
     *    limit has been 2200 and was reported raised to 4000 - 2200 kept to be
     *    safe. Size: UNSURE, 4 GB is the Content Posting API figure.
     *  - Reels (Instagram / Facebook): UNSURE. Instagram allows Reels up to
     *    3 minutes since January 2025; Facebook Reels have been 90 seconds.
     *    1 GB size is UNSURE. Caption 2200 (Instagram).
     *  - X: 2 minutes 20 seconds and 512 MB for accounts without a paid tier;
     *    post text 280 characters (X counts any link as 23; the cut here counts
     *    the URL's real length, which is the safe side).
     *  - Facebook feed video: 240 minutes and 10 GB; post text 63,206 characters.
     *  - WhatsApp: 16 MB per video and 3 minutes, as the brief asks; 16 MB is
     *    taken as 16,000,000 bytes to stay under it whichever way it is read.
     *    Caption 1024 characters is the WhatsApp Business Platform media
     *    caption limit; UNSURE for the consumer app.
     *  - GozaTV: UNSURE. No public technical specification was found; the
     *    preset follows the GCIS brief (16:9 1080p) and must be confirmed with
     *    GCIS. No duration, size or caption limit is applied beyond 5000.
     */
    public const DEFAULT_PRESETS = [
        'youtube' => ['label' => 'YouTube', 'aspect' => '16:9', 'width' => 1920, 'height' => 1080, 'max_duration' => 43200,
            'max_bytes' => 274877906944, 'video_kbps' => 8000, 'codec' => 'h264/aac mp4', 'caption_max' => 5000, 'image_as' => 'video', 'still_seconds' => 10],
        'tiktok' => ['label' => 'TikTok', 'aspect' => '9:16', 'width' => 1080, 'height' => 1920, 'max_duration' => 600,
            'max_bytes' => 4294967296, 'video_kbps' => 6000, 'codec' => 'h264/aac mp4', 'caption_max' => 2200, 'image_as' => 'video', 'still_seconds' => 10],
        'reels' => ['label' => 'Reels (Instagram / Facebook)', 'aspect' => '9:16', 'width' => 1080, 'height' => 1920, 'max_duration' => 180,
            'max_bytes' => 1073741824, 'video_kbps' => 6000, 'codec' => 'h264/aac mp4', 'caption_max' => 2200, 'image_as' => 'video', 'still_seconds' => 10],
        'x' => ['label' => 'X', 'aspect' => '16:9', 'width' => 1920, 'height' => 1080, 'max_duration' => 140,
            'max_bytes' => 536870912, 'video_kbps' => 6000, 'codec' => 'h264/aac mp4', 'caption_max' => 280, 'image_as' => 'image', 'still_seconds' => 10],
        'facebook' => ['label' => 'Facebook (16:9)', 'aspect' => '16:9', 'width' => 1920, 'height' => 1080, 'max_duration' => 14400,
            'max_bytes' => 10737418240, 'video_kbps' => 8000, 'codec' => 'h264/aac mp4', 'caption_max' => 63206, 'image_as' => 'image', 'still_seconds' => 10],
        'facebook_square' => ['label' => 'Facebook (1:1)', 'aspect' => '1:1', 'width' => 1080, 'height' => 1080, 'max_duration' => 14400,
            'max_bytes' => 10737418240, 'video_kbps' => 6000, 'codec' => 'h264/aac mp4', 'caption_max' => 63206, 'image_as' => 'image', 'still_seconds' => 10],
        'whatsapp' => ['label' => 'WhatsApp', 'aspect' => '16:9', 'width' => 1280, 'height' => 720, 'max_duration' => 180,
            'max_bytes' => 16000000, 'video_kbps' => 2500, 'codec' => 'h264/aac mp4', 'caption_max' => 1024, 'image_as' => 'image', 'still_seconds' => 10],
        'gozatv' => ['label' => 'GozaTV', 'aspect' => '16:9', 'width' => 1920, 'height' => 1080, 'max_duration' => null,
            'max_bytes' => null, 'video_kbps' => 8000, 'codec' => 'h264/aac mp4', 'caption_max' => 5000, 'image_as' => 'video', 'still_seconds' => 10],
    ];

    /** Audio bitrate in every package, kbit/s. */
    private const AUDIO_KBPS = 128;

    // ---------------------------------------------------------------
    // Presets
    // ---------------------------------------------------------------

    /** @return array<string, array> code => preset (label included), active rows in sort order. */
    public function presets(): array
    {
        $out = [];
        if (Schema::hasTable('ahg_dropdown')) {
            foreach (DB::table('ahg_dropdown')->where('taxonomy', 'publish_platform')->where('is_active', 1)->orderBy('sort_order')->get() as $r) {
                $meta = json_decode((string) $r->metadata, true) ?: [];
                $out[$r->code] = array_merge(self::DEFAULT_PRESETS[$r->code] ?? [], $meta, ['label' => $r->label]);
            }
        }

        return $out ?: self::DEFAULT_PRESETS;
    }

    public function statusLabels(): array
    {
        $labels = self::STATUS_LABELS;
        if (Schema::hasTable('ahg_dropdown')) {
            foreach (DB::table('ahg_dropdown')->where('taxonomy', 'publish_status')->get(['code', 'label']) as $r) {
                $labels[$r->code] = $r->label;
            }
        }

        return $labels;
    }

    // ---------------------------------------------------------------
    // Eligibility: platforms are public, so the record must be
    // ---------------------------------------------------------------

    /**
     * Why record $ioId may not go to a platform; [] when it may. The same rules
     * as public display: DisclosureGate (published, ICIP/TK, ODRL, embargo,
     * classification, tenant), PII redaction regions, FileScreeningGate, and
     * release clearance on audio/video. Checked as for an anonymous visitor -
     * the staff member's own bypass does not count, because a platform is public.
     *
     * @return string[]
     */
    public function refusals(int $ioId): array
    {
        $why = [];
        $gate = new DisclosureGate; // fresh: no memo from an earlier check in this request
        if (! $gate->allows($ioId)) {
            $why[] = __('The record is not public: it is a draft, or it is embargoed, restricted or classified.');
        }
        if ($gate->hasRedactions($ioId)) {
            $why[] = __('The record has redaction regions; its access copy is not the redacted rendition.');
        }
        if (class_exists(\AhgPrivacy\Services\FileScreeningGate::class) && app(\AhgPrivacy\Services\FileScreeningGate::class)->withholds($ioId)) {
            $why[] = __('The record\'s files are waiting for personal-information screening.');
        }
        $src = $this->source($ioId);
        if (! $src) {
            $why[] = __('The record has no video, audio or image access copy.');
        } elseif (MediaProtection::awaitsClearance($src['master'])) {
            $why[] = __('The media has not been cleared for release.');
        }

        return $why;
    }

    /**
     * The access copy of the record's first video, audio or image master.
     *
     * @return array{type: string, master: object, file: string, do_id: int, still: ?string}|null
     */
    public function source(int $ioId): ?array
    {
        $masters = DB::table('digital_object')->where('object_id', $ioId)->whereNull('parent_id')->orderBy('id')->get();
        foreach ($masters as $m) {
            $mime = strtolower((string) $m->mime_type);
            $type = str_starts_with($mime, 'video/') || (int) $m->media_type_id === DOS::MEDIA_VIDEO ? 'video'
                : (str_starts_with($mime, 'audio/') || (int) $m->media_type_id === DOS::MEDIA_AUDIO ? 'audio'
                : (str_starts_with($mime, 'image/') || (int) $m->media_type_id === DOS::MEDIA_IMAGE ? 'image' : null));
            if (! $type) {
                continue;
            }
            $kids = DB::table('digital_object')->where('parent_id', $m->id)->get();
            $ref = $kids->first(function ($k) use ($type) {
                $km = strtolower((string) $k->mime_type);

                return (int) $k->usage_id === DOS::USAGE_REFERENCE
                    && ($type === 'video' ? $km === 'video/mp4' : str_starts_with($km, $type.'/'));
            });
            $file = $ref ? DOS::resolveDiskPath($ref) : null;
            if (! $file || ! is_file($file)) {
                continue; // no access copy on disk; the master is never used instead
            }
            $still = null;
            if ($type === 'audio') {
                $thumb = $kids->first(fn ($k) => (int) $k->usage_id === DOS::USAGE_THUMBNAIL);
                $still = $thumb ? DOS::resolveDiskPath($thumb) : null;
                $still = $still && is_file($still) ? $still : null;
            }

            return ['type' => $type, 'master' => $m, 'file' => $file, 'do_id' => (int) $ref->id, 'still' => $still];
        }

        return null;
    }

    // ---------------------------------------------------------------
    // Caption
    // ---------------------------------------------------------------

    /** Title, short description, credit line and the public URL. */
    public function captionParts(int $ioId): array
    {
        $i18n = DB::table('information_object_i18n')->where('id', $ioId)
            ->orderByRaw("culture = 'en' DESC")->first(['title', 'scope_and_content']);
        $slug = DB::table('slug')->where('object_id', $ioId)->value('slug');
        $desc = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($i18n->scope_and_content ?? ''))));
        $desc = trim(preg_replace('/(^|\s)[#>*_`]+|[*_`]+(\s|$)/u', '$1$2', $desc));
        if (mb_strlen($desc) > 500) {
            $desc = $this->cut($desc, 500);
        }

        return [
            'title' => trim((string) ($i18n->title ?? '')) ?: __('Untitled'),
            'description' => $desc,
            'credit' => $this->creditLine($ioId),
            'url' => $slug ? url('/'.$slug) : url('/'),
        ];
    }

    private function creditLine(int $ioId): string
    {
        $set = trim((string) AhgSettingsService::get('publish_credit_line', ''));
        if ($set !== '') {
            return $set;
        }
        $repo = DB::table('information_object')->where('id', $ioId)->value('repository_id');
        $name = $repo ? trim((string) DB::table('actor_i18n')->where('id', $repo)->orderByRaw("culture = 'en' DESC")->value('authorized_form_of_name')) : '';

        return __('Courtesy of :name', ['name' => $name !== '' ? $name : (string) config('app.name', 'Heratio')]);
    }

    /**
     * The caption cut to $max characters. The credit line and the URL are kept
     * whole; the description is shortened first, then the title.
     */
    public function caption(array $parts, int $max): string
    {
        $tail = $parts['credit']."\n".$parts['url'];
        $join = fn ($t, $d) => implode("\n\n", array_filter([$t, $d, $tail], fn ($s) => $s !== ''));
        $text = $join($parts['title'], $parts['description']);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $room = $max - mb_strlen($join($parts['title'], ''));
        if ($parts['description'] !== '' && $room > 2 + 20) {
            return $join($parts['title'], $this->cut($parts['description'], $room - 2));
        }
        $room = $max - mb_strlen($tail) - 2;
        if ($room > 10) {
            return $join($this->cut($parts['title'], $room), '');
        }

        return mb_substr($text, 0, $max); // ponytail: a limit shorter than the URL itself; hard cut
    }

    /** Shorten to at most $max characters at a word boundary, ending in an ellipsis. */
    private function cut(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        $s = mb_substr($s, 0, max(1, $max - 3));
        $sp = mb_strrpos($s, ' ');
        if ($sp !== false && $sp > $max / 2) {
            $s = mb_substr($s, 0, $sp);
        }

        return rtrim($s, " ,.;:-").'...';
    }

    // ---------------------------------------------------------------
    // Making the packages
    // ---------------------------------------------------------------

    public static function packagesDir(): string
    {
        // Under storage, never in the shared uploads tree.
        return rtrim((string) (config('heratio.publish_packages_path') ?: storage_path('app/publish-packages')), '/');
    }

    /** Clips longer than this (seconds) are made by a queued job. */
    public static function queueSeconds(): int
    {
        return max(0, (int) (AhgSettingsService::get('publish_queue_seconds', '60') ?: 60));
    }

    public function duration(string $file): ?float
    {
        $out = trim((string) shell_exec('ffprobe -v error -show_entries format=duration -of csv=p=0 '.escapeshellarg($file).' 2>/dev/null'));

        return is_numeric($out) ? (float) $out : null;
    }

    /**
     * Log one queued row per platform. The caller checks refusals() first;
     * this checks again so a row is never made for a record that may not go out.
     *
     * @param  string[]  $platforms
     * @return int[] the new row ids
     */
    public function request(int $ioId, array $platforms, bool $trim, ?int $userId): array
    {
        if ($this->refusals($ioId) !== []) {
            throw new \RuntimeException('This record may not be published to a platform.');
        }
        $presets = $this->presets();
        $src = $this->source($ioId);
        $dur = $src['type'] === 'image' ? null : $this->duration($src['file']);
        $ids = [];
        foreach (array_unique($platforms) as $p) {
            if (! isset($presets[$p])) {
                continue;
            }
            $ids[] = (int) DB::table(self::TABLE)->insertGetId([
                'information_object_id' => $ioId, 'digital_object_id' => $src['do_id'], 'platform' => $p,
                'status' => self::STATUS_QUEUED, 'trimmed' => $trim ? 1 : 0, 'source_duration' => $dur,
                'made_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        AuditLog::captureSecondaryMutation($ioId, 'information_object', 'publish_package_requested', [
            'platforms' => array_values(array_intersect(array_unique($platforms), array_keys($presets))),
            'trim' => $trim, 'package_ids' => $ids, 'source_digital_object_id' => $src['do_id'],
        ]);

        return $ids;
    }

    /** Make the zip for one logged row. Never throws; a failure is written to the row. */
    public function build(int $packageId): object
    {
        $row = DB::table(self::TABLE)->where('id', $packageId)->first();
        if (! $row || ! in_array($row->status, [self::STATUS_QUEUED, self::STATUS_FAILED], true)) {
            return $row;
        }
        $work = sys_get_temp_dir().'/ahg-publish-'.$packageId.'-'.bin2hex(random_bytes(4));
        try {
            // Check again at build time: a queued job may run after the record changed.
            if ($why = $this->refusals((int) $row->information_object_id)) {
                throw new \RuntimeException(implode(' ', $why));
            }
            $preset = $this->presets()[$row->platform] ?? throw new \RuntimeException('Unknown platform preset: '.$row->platform);
            $src = $this->source((int) $row->information_object_id);
            @mkdir($work, 0770, true);

            $asImage = $src['type'] === 'image' && ($preset['image_as'] ?? 'video') === 'image';
            $media = $work.'/'.($asImage ? 'image.jpg' : 'video.mp4');
            $outDur = null;
            if ($asImage) {
                $this->ffmpeg(sprintf('-i %s -filter_complex %s -map "[v]" -frames:v 1 -q:v 2 %s',
                    escapeshellarg($src['file']), escapeshellarg($this->fitGraph($preset)), escapeshellarg($media)));
            } else {
                $outDur = $this->encodeVideo($src, $preset, (bool) $row->trimmed, $row->source_duration !== null ? (float) $row->source_duration : null, $media);
            }
            if (! is_file($media) || filesize($media) === 0) {
                throw new \RuntimeException('ffmpeg made no output.');
            }

            $thumb = $work.'/thumbnail.jpg';
            $this->ffmpeg($asImage
                ? sprintf('-i %s -vf %s -frames:v 1 -q:v 3 %s', escapeshellarg($media), escapeshellarg('scale=640:-2'), escapeshellarg($thumb))
                : sprintf('-ss %s -i %s -frames:v 1 -q:v 3 %s', min(1, max(0, ($outDur ?? 0) / 2)), escapeshellarg($media), escapeshellarg($thumb)));

            $caption = $this->caption($this->captionParts((int) $row->information_object_id), (int) $preset['caption_max']);
            file_put_contents($work.'/caption.txt', $caption."\n");

            $dir = self::packagesDir().'/'.$row->information_object_id;
            if (! is_dir($dir) && ! @mkdir($dir, 0770, true)) {
                throw new \RuntimeException('Cannot create the package folder.');
            }
            $zipPath = $dir.'/'.$row->platform.'-'.$packageId.'-'.now()->format('Ymd-His').'.zip';
            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot write the zip.');
            }
            $zip->addFile($media, basename($media));
            $zip->setCompressionName(basename($media), \ZipArchive::CM_STORE); // already compressed
            $zip->addFile($work.'/caption.txt', 'caption.txt');
            if (is_file($thumb)) {
                $zip->addFile($thumb, 'thumbnail.jpg');
            }
            $zip->close();

            DB::table(self::TABLE)->where('id', $packageId)->update([
                'status' => self::STATUS_MADE, 'package_path' => $zipPath, 'caption' => $caption, 'error' => null,
                'width' => (int) $preset['width'], 'height' => (int) $preset['height'], 'output_duration' => $outDur,
                'byte_size' => filesize($media), 'made_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table(self::TABLE)->where('id', $packageId)->update([
                'status' => self::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now(),
            ]);
        } finally {
            shell_exec('rm -rf '.escapeshellarg($work));
        }
        $row = DB::table(self::TABLE)->where('id', $packageId)->first();
        AuditLog::captureSecondaryMutation((int) $row->information_object_id, 'information_object',
            $row->status === self::STATUS_MADE ? 'publish_package_made' : 'publish_package_failed',
            ['package_id' => $packageId, 'platform' => $row->platform, 'error' => $row->error]);

        return $row;
    }

    /**
     * Fit [0:v] into the preset canvas without cropping: the picture scaled to
     * fit inside, centred on a blurred, filled copy of itself. Ends in [v].
     */
    private function fitGraph(array $p): string
    {
        [$w, $h] = [(int) $p['width'], (int) $p['height']];

        return "[0:v]split=2[bg][fg];"
            ."[bg]scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},boxblur=20:2[bgb];"
            ."[fg]scale={$w}:{$h}:force_original_aspect_ratio=decrease:force_divisible_by=2[fgs];"
            ."[bgb][fgs]overlay=(W-w)/2:(H-h)/2,setsar=1,format=yuv420p[v]";
    }

    /** Encode the video (or audio over a still, or a still image clip). Returns the output duration. */
    private function encodeVideo(array $src, array $p, bool $trim, ?float $dur, string $out): ?float
    {
        $max = $p['max_duration'] !== null ? (float) $p['max_duration'] : null;
        if ($src['type'] === 'image') {
            $dur = (float) ($p['still_seconds'] ?? 10);
        }
        if ($max !== null && $dur !== null && $dur > $max + 0.05) {
            if (! $trim) {
                throw new \RuntimeException(sprintf('The clip is %d s long; %s takes at most %d s. Choose "trim" to cut it.', (int) ceil($dur), $p['label'], (int) $max));
            }
            $dur = $max;
        }

        // Bitrate: the preset's, lowered when the size limit needs it.
        $vk = (int) $p['video_kbps'];
        if ($p['max_bytes'] !== null && $dur) {
            $total = (int) floor($p['max_bytes'] * 8 * 0.92 / $dur / 1000); // 8% for the container and rate swing
            $vk = min($vk, $total - self::AUDIO_KBPS);
            if ($vk < 150) {
                throw new \RuntimeException(sprintf('%s allows %d MB; a clip of %d s cannot fit at a watchable quality.', $p['label'], (int) ($p['max_bytes'] / 1000000), (int) $dur));
            }
        }

        $limit = $dur !== null && ($trim && $max !== null) ? ' -t '.$max : '';
        if ($src['type'] === 'video') {
            $in = '-i '.escapeshellarg($src['file']).$limit;
            $map = '-map "[v]" -map "0:a:0?"';
        } elseif ($src['type'] === 'audio') {
            $still = $src['still']
                ? '-loop 1 -i '.escapeshellarg($src['still'])
                : sprintf('-f lavfi -i color=c=0x202020:s=%dx%d', $p['width'], $p['height']);
            $in = $still.' -i '.escapeshellarg($src['file']).$limit;
            $map = '-map "[v]" -map 1:a:0 -shortest';
        } else {
            $in = '-loop 1 -i '.escapeshellarg($src['file']).' -f lavfi -i anullsrc=r=44100:cl=stereo -t '.$dur;
            $map = '-map "[v]" -map 1:a:0 -shortest';
        }

        foreach ([1.0, 0.7] as $factor) {
            $k = (int) floor($vk * $factor);
            $this->ffmpeg(sprintf('%s -filter_complex %s %s -c:v libx264 -preset veryfast -profile:v high -pix_fmt yuv420p -r 30'
                .' -b:v %dk -maxrate %dk -bufsize %dk -c:a aac -b:a %dk -ar 44100 -movflags +faststart %s',
                $in, escapeshellarg($this->fitGraph($p)), $map, $k, $k, $k * 2, self::AUDIO_KBPS, escapeshellarg($out)));
            if (! is_file($out) || $p['max_bytes'] === null || filesize($out) <= $p['max_bytes']) {
                break;
            }
        }
        if (is_file($out) && $p['max_bytes'] !== null && filesize($out) > $p['max_bytes']) {
            throw new \RuntimeException(sprintf('The %s copy came out larger than its %d-byte limit.', $p['label'], $p['max_bytes']));
        }

        return is_file($out) ? $this->duration($out) : null;
    }

    private function ffmpeg(string $args): void
    {
        shell_exec('ffmpeg -v error -y -nostdin '.$args.' 2>/dev/null');
    }

    // ---------------------------------------------------------------
    // The distribution log
    // ---------------------------------------------------------------

    public function find(int $id): ?object
    {
        return Schema::hasTable(self::TABLE) ? DB::table(self::TABLE)->where('id', $id)->first() : null;
    }

    public function markDownloaded(int $id, ?int $userId): void
    {
        $row = $this->find($id);
        if (! $row) {
            return;
        }
        $upd = ['download_count' => DB::raw('download_count + 1'), 'downloaded_by' => $userId, 'downloaded_at' => now(), 'updated_at' => now()];
        if ($row->status === self::STATUS_MADE) {
            $upd['status'] = self::STATUS_DOWNLOADED;
        }
        DB::table(self::TABLE)->where('id', $id)->update($upd);
        AuditLog::captureSecondaryMutation((int) $row->information_object_id, 'information_object', 'publish_package_downloaded',
            ['package_id' => $id, 'platform' => $row->platform]);
    }

    /** Staff posted the package by hand: record where and when. */
    public function markPosted(int $id, string $url, string $date, ?int $userId, string $via = 'manual', ?string $externalId = null): void
    {
        $row = $this->find($id);
        if (! $row || ! in_array($row->status, [self::STATUS_MADE, self::STATUS_DOWNLOADED, self::STATUS_POSTED], true)) {
            throw new \InvalidArgumentException('Only a made package can be marked posted.');
        }
        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => self::STATUS_POSTED, 'posted_url' => $url, 'posted_at' => $date, 'posted_by' => $userId,
            'posted_via' => $via, 'external_id' => $externalId, 'updated_at' => now(),
        ]);
        AuditLog::captureSecondaryMutation((int) $row->information_object_id, 'information_object', 'publish_package_posted', [
            'package_id' => $id, 'platform' => $row->platform,
            'before' => ['status' => $row->status, 'posted_url' => $row->posted_url],
            'after' => ['status' => self::STATUS_POSTED, 'posted_url' => $url, 'posted_at' => $date, 'posted_via' => $via],
        ]);
    }

    /** Log rows, newest first, with the record title and slug and the maker's name. */
    public function log(?int $ioId = null, int $limit = 200, ?string $status = null)
    {
        if (! Schema::hasTable(self::TABLE)) {
            return collect();
        }
        $q = DB::table(self::TABLE.' as p')
            ->leftJoin('information_object_i18n as i', fn ($j) => $j->on('i.id', '=', 'p.information_object_id')->where('i.culture', '=', 'en'))
            ->leftJoin('slug as s', 's.object_id', '=', 'p.information_object_id')
            ->leftJoin('user as u', 'u.id', '=', 'p.made_by')
            ->select('p.*', 'i.title', 's.slug', 'u.username as made_by_name')
            ->orderByDesc('p.id')->limit($limit);
        if ($ioId) {
            $q->where('p.information_object_id', $ioId);
        }
        if ($status) {
            $q->where('p.status', $status);
        }

        return $q->get();
    }
}
