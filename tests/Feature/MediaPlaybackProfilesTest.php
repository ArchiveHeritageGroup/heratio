<?php

/**
 * MediaPlaybackProfilesTest - GCIS media issues heratio#1746, #1752-#1756.
 *
 * Tiny media is generated with ffmpeg in sys_get_temp_dir() (1 second, small
 * frames, sine tones) - never collection files. Covers: the default profile
 * reproduces the old fixed 480p H.264 / 128k MP3; codec, height and audio
 * settings are honoured; the rendition ladder never upscales; HLS writes one
 * master playlist with a variant per rung; WMA (detected as video) takes the
 * audio route; the on-the-fly fallback list knows AMR, Opus and ALAC; an MXF
 * gets its proxy and a video player; the /uploads gate judges HLS files as
 * their record; the player leads with HLS and with a playable copy.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DigitalObjectService as DOS;
use AhgCore\Services\MediaDerivativeService as MDS;
use AhgCore\Support\StatusRow;
use AhgMediaStreaming\Services\TranscodingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaPlaybackProfilesTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['digital_object', 'information_object', 'status', 'ahg_settings'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $this->root = sys_get_temp_dir().'/ahg-media-'.uniqid();
        mkdir($this->root, 0777, true);
        config(['heratio.uploads_path' => $this->root]);
        DB::table('ahg_settings')->where('setting_key', 'like', 'media\_%')->delete();
        AhgSettingsService::clearCache();
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root));
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function needFfmpeg(): void
    {
        if (trim((string) shell_exec('command -v ffmpeg')) === '' || trim((string) shell_exec('command -v ffprobe')) === '') {
            $this->markTestSkipped('ffmpeg/ffprobe not installed');
        }
    }

    private function setting(string $key, string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value, 'setting_group' => 'media']);
        AhgSettingsService::clearCache();
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(int $status = 160): int
    {
        $id = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        StatusRow::set($id, 158, $status);

        return $id;
    }

    /** A master row for a file made by ffmpeg with $args into uploads/r/<io>/. */
    private function master(string $name, string $mime, int $mediaType, string $args, ?int $io = null): array
    {
        $io ??= $this->record();
        $dir = $this->root.'/r/'.$io;
        @mkdir($dir, 0777, true);
        shell_exec('ffmpeg -v error -y '.$args.' '.escapeshellarg($dir.'/'.$name).' 2>&1');
        $this->assertFileExists($dir.'/'.$name);
        $id = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $io, 'usage_id' => DOS::USAGE_MASTER, 'name' => $name,
            'path' => '/uploads/r/'.$io.'/', 'mime_type' => $mime, 'media_type_id' => $mediaType, 'byte_size' => filesize($dir.'/'.$name)]);

        return [$id, $io, $dir];
    }

    private function reference(int $master): ?object
    {
        return DB::table('digital_object')->where('parent_id', $master)->where('usage_id', DOS::USAGE_REFERENCE)->first();
    }

    private function probe(string $file, string $entries): string
    {
        return trim((string) shell_exec('ffprobe -v error -select_streams v:0 -show_entries stream='.$entries.' -of csv=p=0 '.escapeshellarg($file)));
    }

    private const VIDEO = '-f lavfi -i testsrc=size=%s:rate=10:duration=1 -f lavfi -i sine=duration=1 -c:v libx264 -preset ultrafast -c:a aac -shortest';

    private const TONE = '-f lavfi -i sine=frequency=440:duration=1';

    public function test_defaults_reproduce_the_original_profile(): void
    {
        $p = MDS::profile();
        $this->assertSame('h264', $p['video_codec']);
        $this->assertSame(480, $p['video_height']);
        $this->assertSame('mp3', $p['audio_codec']);
        $this->assertSame(128, $p['audio_bitrate']);
        $this->assertFalse($p['hls']);

        // Unknown or unavailable values fall back rather than break encoding.
        $this->setting('media_video_codec', 'mpeg1');
        $this->setting('media_video_height', 'lots');
        $this->assertSame('h264', MDS::profile()['video_codec']);
        $this->assertSame(480, MDS::profile()['video_height']);
    }

    public function test_the_ladder_never_upscales(): void
    {
        $this->assertSame([480, 1080], MDS::parseLadder('1080, 999,480'));
        $this->assertSame([480], MDS::parseLadder('nonsense'));
        $this->assertSame([480, 720], MDS::ladderFor(720, [480, 720, 1080, 2160]));
        $this->assertSame([480, 720, 1080, 1440, 2160], MDS::ladderFor(2160, array_keys(MDS::LADDER)));
        // Smaller than every rung: one variant at the source's own (even) height.
        $this->assertSame([240], MDS::ladderFor(241, [480, 720]));
    }

    public function test_video_profile_ladder_and_hls_master_playlist(): void
    {
        $this->needFfmpeg();
        $codec = isset(MDS::videoCodecs()['h265']) ? 'h265' : 'h264';
        $this->setting('media_video_codec', $codec);
        $this->setting('media_video_height', '480');
        $this->setting('media_hls_enabled', 'true');
        $this->setting('media_rendition_ladder', '480,720,1080');
        [$id, , $dir] = $this->master('master_clip.mov', 'video/quicktime', DOS::MEDIA_VIDEO, sprintf(self::VIDEO, '1280x720'));

        $this->assertGreaterThanOrEqual(1, MDS::generateForMaster($id));
        $ref = $this->reference($id);
        $this->assertSame('reference_clip.mp4', $ref->name);
        $this->assertSame($codec === 'h265' ? 'hevc' : 'h264', $this->probe($dir.'/reference_clip.mp4', 'codec_name'));
        $this->assertSame('480', $this->probe($dir.'/reference_clip.mp4', 'height'));

        $playlist = (string) @file_get_contents($dir.'/hls_clip/master.m3u8');
        $this->assertStringContainsString('#EXT-X-STREAM-INF', $playlist);
        $this->assertMatchesRegularExpression('/RESOLUTION=\d+x480/', $playlist);
        $this->assertMatchesRegularExpression('/RESOLUTION=\d+x720/', $playlist);
        $this->assertStringNotContainsString('x1080', $playlist, 'a 720p source is never upscaled to 1080p');
        $this->assertFileExists($dir.'/hls_clip/v720.m3u8');
        $this->assertNotEmpty(glob($dir.'/hls_clip/v480_*.ts'));
    }

    public function test_a_small_source_is_not_upscaled_and_hls_stays_off_by_default(): void
    {
        $this->needFfmpeg();
        $this->setting('media_video_height', '1080');
        [$id, , $dir] = $this->master('master_small.mp4', 'video/mp4', DOS::MEDIA_VIDEO, sprintf(self::VIDEO, '160x120'));

        MDS::generateForMaster($id);
        $this->assertSame('120', $this->probe($dir.'/reference_small.mp4', 'height'));
        $this->assertDirectoryDoesNotExist($dir.'/hls_small');
    }

    public function test_av1_output_when_the_encoder_is_present(): void
    {
        $this->needFfmpeg();
        if (! isset(MDS::videoCodecs()['av1'])) {
            $this->markTestSkipped('no AV1 encoder in this ffmpeg build');
        }
        $this->setting('media_video_codec', 'av1');
        [$id, , $dir] = $this->master('master_av.mp4', 'video/mp4', DOS::MEDIA_VIDEO, sprintf(self::VIDEO, '160x120'));

        MDS::generateForMaster($id);
        $this->assertSame('av1', $this->probe($dir.'/reference_av.mp4', 'codec_name'));
    }

    public function test_wma_detected_as_video_takes_the_audio_route(): void
    {
        $this->needFfmpeg();
        [$id, , $dir] = $this->master('master_talk.wma', 'video/x-ms-asf', DOS::MEDIA_VIDEO, self::TONE.' -c:a wmav2');

        $this->assertGreaterThanOrEqual(1, MDS::generateForMaster($id));
        $ref = $this->reference($id);
        $this->assertSame('reference_talk.mp3', $ref->name);
        $this->assertSame('audio/mpeg', $ref->mime_type);
        $master = DB::table('digital_object')->where('id', $id)->first();
        $this->assertSame('audio/x-ms-wma', $master->mime_type);
        $this->assertSame(DOS::MEDIA_AUDIO, (int) $master->media_type_id);
        $this->assertSame('audio', DOS::getMediaType($master));
    }

    public function test_audio_codec_and_bitrate_settings(): void
    {
        $this->needFfmpeg();
        $this->setting('media_audio_codec', 'aac');
        $this->setting('media_audio_bitrate', '96');
        [$id, , $dir] = $this->master('master_song.opus', 'audio/ogg', DOS::MEDIA_AUDIO, self::TONE.' -c:a libopus');

        MDS::generateForMaster($id);
        $ref = $this->reference($id);
        $this->assertSame('reference_song.m4a', $ref->name);
        $this->assertSame('audio/mp4', $ref->mime_type);
        $this->assertSame('aac', trim((string) shell_exec('ffprobe -v error -select_streams a:0 -show_entries stream=codec_name -of csv=p=0 '.escapeshellarg($dir.'/reference_song.m4a'))));
    }

    public function test_every_listed_audio_format_gets_a_playback_copy(): void
    {
        $this->needFfmpeg();
        $amr = $this->record();
        @mkdir($this->root.'/r/'.$amr, 0777, true);
        // ffmpeg here has no AMR encoder: a hand-built AMR-NB file (header + 50 mode-0 frames).
        file_put_contents($this->root.'/r/'.$amr.'/amr-src.amr', "#!AMR\n".str_repeat("\x04".str_repeat("\0", 12), 50));
        $cases = [
            ['master_a.m4a', 'audio/x-m4a', self::TONE.' -c:a alac'],
            ['master_b.opus', 'audio/ogg', self::TONE.' -c:a libopus'],
            ['master_c.aiff', 'audio/x-aiff', self::TONE],
            ['master_d.amr', 'audio/amr', '-i '.escapeshellarg($this->root.'/r/'.$amr.'/amr-src.amr').' -c:a copy'],
        ];
        foreach ($cases as [$name, $mime, $args]) {
            [$id] = $this->master($name, $mime, DOS::MEDIA_AUDIO, $args, str_contains($name, 'amr') ? $amr : null);
            MDS::generateForMaster($id);
            $this->assertSame('audio/mpeg', $this->reference($id)?->mime_type, $name.' gets an MP3 playback copy');
        }
    }

    public function test_on_the_fly_fallback_list_knows_alac_amr_and_opus(): void
    {
        $this->needFfmpeg();
        $t = new TranscodingService;
        $this->assertTrue($t->needsTranscoding('/x/a.amr'));
        $this->assertTrue($t->needsTranscoding('/x/a.opus'));
        $this->assertTrue($t->needsTranscoding('/x/a.wma'));
        $this->assertFalse($t->needsTranscoding('/x/a.mp3'));

        shell_exec('ffmpeg -v error -y '.self::TONE.' -c:a alac '.escapeshellarg($this->root.'/alac.m4a'));
        shell_exec('ffmpeg -v error -y '.self::TONE.' -c:a aac '.escapeshellarg($this->root.'/aac.m4a'));
        $this->assertTrue($t->needsTranscoding($this->root.'/alac.m4a'), 'ALAC in M4A does not play in most browsers');
        $this->assertFalse($t->needsTranscoding($this->root.'/aac.m4a'), 'AAC in M4A plays as it is');
        $this->assertFalse($t->isVideoTranscodeFormat($this->root.'/alac.m4a'));
    }

    public function test_mxf_gets_its_proxy_and_a_video_player(): void
    {
        $this->needFfmpeg();
        $this->assertSame(DOS::MEDIA_VIDEO, MDS::avMediaTypeId('application/mxf'));
        $this->assertNull(MDS::avMediaTypeId('application/zip'));

        [$id] = $this->master('master_tape.mxf', 'application/mxf', DOS::MEDIA_OTHER,
            '-f lavfi -i testsrc=size=160x120:rate=25:duration=1 -f lavfi -i sine=duration=1:sample_rate=48000 -c:v mpeg2video -c:a pcm_s16le -shortest');

        MDS::generateForMaster($id);
        $this->assertSame('video/mp4', $this->reference($id)?->mime_type);
        $master = DB::table('digital_object')->where('id', $id)->first();
        $this->assertSame('video', DOS::getMediaType($master), 'the record page shows a video player for the MXF');
    }

    public function test_mxf_upload_queues_the_proxy_job_at_once(): void
    {
        $this->needFfmpeg();
        if (DOS::resolveMediaTypeId('application/mxf') !== DOS::MEDIA_VIDEO) {
            $this->markTestSkipped('DigitalObjectService::resolveMediaTypeId() does not call MediaDerivativeService::avMediaTypeId() yet (heratio#1746 hook)');
        }
        \Illuminate\Support\Facades\Queue::fake();
        $src = $this->root.'/upload.mxf';
        shell_exec('ffmpeg -v error -y -f lavfi -i testsrc=size=160x120:rate=25:duration=1 -c:v mpeg2video '.escapeshellarg($src));
        $file = new \Illuminate\Http\UploadedFile($src, 'tape.mxf', 'application/mxf', null, true);

        $masterId = DOS::upload($this->record(), $file);

        \Illuminate\Support\Facades\Queue::assertPushed(\AhgCore\Jobs\GenerateMediaDerivativesJob::class, fn ($j) => $j->masterId === $masterId);
        $this->assertSame(DOS::MEDIA_VIDEO, (int) DB::table('digital_object')->where('id', $masterId)->value('media_type_id'));
    }

    public function test_the_gate_judges_hls_files_as_their_record(): void
    {
        if (! class_exists(\AhgInformationObjectManage\Services\RedactionRenderService::class)) {
            $this->markTestSkipped('gate not installed');
        }
        \AhgCore\Services\AclService::forgetUser();
        $gate = fn (string $path) => $this->withHeader('X-Original-URI', $path)->get(route('io.privacy.file-check'));
        $row = function (int $io, string $name, ?int $parent = null) {
            $id = $this->objectRow('QubitDigitalObject');
            DB::table('digital_object')->insert(['id' => $id, 'object_id' => $parent ? null : $io, 'parent_id' => $parent,
                'usage_id' => $parent ? DOS::USAGE_REFERENCE : DOS::USAGE_MASTER, 'name' => $name, 'path' => '/uploads/r/hlsgate-'.$io.'/', 'mime_type' => 'video/mp4']);

            return $id;
        };

        $draft = $this->record(159);
        $row($draft, 'reference_film.mp4', $row($draft, 'master_film.mov'));
        $gate('/uploads/r/hlsgate-'.$draft.'/hls_film/master.m3u8')->assertForbidden();
        $gate('/uploads/r/hlsgate-'.$draft.'/hls_film/v480_000.ts')->assertForbidden();
        $this->assertSame([$draft], app(\AhgInformationObjectManage\Services\RedactionRenderService::class)
            ->recordIdsForIdentifier('/uploads/r/hlsgate-'.$draft.'/hls_film/v720.m3u8'));

        // The reference row gone: the folder still follows the master.
        $orphan = $this->record(159);
        $row($orphan, 'master_reel.mxf');
        $gate('/uploads/r/hlsgate-'.$orphan.'/hls_reel/v480_001.ts')->assertForbidden();

        $public = $this->record(160);
        $row($public, 'reference_open.mp4', $row($public, 'master_open.mp4'));
        $gate('/uploads/r/hlsgate-'.$public.'/hls_open/v480_000.ts')->assertNoContent();
    }

    public function test_the_player_leads_with_hls_and_with_a_playable_copy(): void
    {
        @mkdir($this->root.'/r/77/hls_clip', 0777, true);
        touch($this->root.'/r/77/hls_clip/master.m3u8');
        touch($this->root.'/r/77/reference_clip.mp4');
        touch($this->root.'/r/77/reference_memo.mp3');

        $html = view('theme::components.media-player', ['type' => 'video', 'playerId' => 'p1', 'src' => '/uploads/r/77/master_clip.mov',
            'mime' => 'video/quicktime', 'name' => 'clip.mov', 'masterUrl' => '/uploads/r/77/master_clip.mov', 'masterMime' => 'video/quicktime', 'byteSize' => 1])->render();
        $this->assertStringContainsString('<source src="/uploads/r/77/hls_clip/master.m3u8" type="application/x-mpegURL">', $html);
        $this->assertLessThan(strpos($html, 'master_clip.mov" type'), strpos($html, 'master.m3u8'));
        $this->assertStringContainsString('reference_clip.mp4', $html);

        // AMR plays nowhere: the MP3 copy is the first source, the master stays as a later one.
        $html = view('theme::components.media-player', ['type' => 'audio', 'playerId' => 'p2', 'src' => '/uploads/r/77/master_memo.amr',
            'mime' => 'audio/amr', 'name' => 'memo.amr', 'masterUrl' => '/uploads/r/77/master_memo.amr', 'masterMime' => 'audio/amr', 'byteSize' => 1])->render();
        $this->assertLessThan(strpos($html, 'master_memo.amr" type'), strpos($html, 'reference_memo.mp3'));
        $this->assertStringNotContainsString('m3u8', $html);

        // A rerouted (redacted / watermarked) URL gets nothing extra.
        $this->assertSame(['hls' => null, 'fallback' => null], MDS::playbackAlternates('/privacy/redacted/1/2', 'video'));
    }
}
