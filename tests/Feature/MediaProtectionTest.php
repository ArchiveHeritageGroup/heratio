<?php

/**
 * MediaProtectionTest - GCIS 5.8.1, heratio#1771.
 *
 * Tiny clips are made with ffmpeg (testsrc, 3 seconds at most) in a temp
 * folder that stands in for the uploads path; HLS keys go to another temp
 * folder. Covers: the video watermark is burnt in only when the record's
 * watermark rule says so, and never into the master; HLS is AES-128
 * encrypted with a key per rendition, rotated on every run, kept outside the
 * uploads; the key route follows access (published, embargoed, draft,
 * uncleared; staff); uncleared media is hidden from the public at the
 * /uploads gate, the stream route and in the player, and clearing it is
 * recorded and audited.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DigitalObjectService as DOS;
use AhgCore\Services\MasterAccess;
use AhgCore\Services\MediaDerivativeService as MDS;
use AhgCore\Services\MediaProtection as MP;
use AhgCore\Support\StatusRow;
use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaProtectionTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    private string $keys;

    private const CLIP = '-f lavfi -i testsrc=size=%s:rate=10:duration=%d -f lavfi -i sine=duration=%2$d -c:v libx264 -preset ultrafast -c:a aac -shortest';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['digital_object', 'information_object', 'status', 'ahg_settings', MP::TABLE] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $this->root = sys_get_temp_dir().'/ahg-mprot-'.uniqid();
        $this->keys = sys_get_temp_dir().'/ahg-mprot-keys-'.uniqid();
        mkdir($this->root, 0777, true);
        config(['heratio.uploads_path' => $this->root, 'heratio.media_keys_path' => $this->keys]);
        DB::table('ahg_settings')->where('setting_key', 'like', 'media\_%')->delete();
        $this->fresh();
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root).' '.escapeshellarg($this->keys));
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    /** Forget every per-request memo, as a new request would. */
    private function fresh(): void
    {
        AhgSettingsService::clearCache();
        AclService::forgetUser();
        MasterAccess::forget();
        app()->forgetInstance(\AhgCore\Services\DisclosureGate::class);
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
        $this->fresh();
    }

    private function record(int $status = 160): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        StatusRow::set($id, 158, $status);

        return $id;
    }

    /** A video master made by ffmpeg in uploads/r/<io>/. */
    private function master(string $size = '320x240', int $seconds = 1, ?int $io = null): array
    {
        $io ??= $this->record();
        $dir = $this->root.'/r/'.$io;
        @mkdir($dir, 0777, true);
        shell_exec('ffmpeg -v error -y '.sprintf(self::CLIP, $size, $seconds).' '.escapeshellarg($dir.'/master_clip.mp4').' 2>&1');
        $this->assertFileExists($dir.'/master_clip.mp4');
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $io, 'usage_id' => DOS::USAGE_MASTER, 'name' => 'master_clip.mp4',
            'path' => '/uploads/r/'.$io.'/', 'mime_type' => 'video/mp4', 'media_type_id' => DOS::MEDIA_VIDEO, 'byte_size' => filesize($dir.'/master_clip.mp4')]);

        return [$id, $io, $dir];
    }

    /** A master + reference row pair with no files, for the gates. */
    private function rows(int $io): int
    {
        $ins = function (array $row) {
            $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('digital_object')->insert($row + ['id' => $id, 'path' => '/uploads/r/mp-'.$row['object_id'].'/', 'mime_type' => 'video/mp4', 'media_type_id' => DOS::MEDIA_VIDEO]);

            return $id;
        };
        $master = $ins(['object_id' => $io, 'usage_id' => DOS::USAGE_MASTER, 'name' => 'master_film.mov']);
        $ins(['object_id' => $io, 'parent_id' => $master, 'usage_id' => DOS::USAGE_REFERENCE, 'name' => 'reference_film.mp4']);

        return $master;
    }

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'mprot-'.$id, 'email' => uniqid('mprot-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }

        \Illuminate\Support\Facades\Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function embargo(int $io): void
    {
        DB::table('embargo')->insert(['object_id' => $io, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(),
            'status' => 'active', 'is_active' => 1]);
        $this->fresh();
    }

    /** Record $io carries (or, with false, switches off) a watermark of its own. */
    private function watermarkOn(int $io, bool $on = true): void
    {
        DB::table('object_watermark_setting')->insert(['object_id' => $io, 'watermark_enabled' => $on ? 1 : 0,
            'watermark_type_id' => DB::table('watermark_type')->where('code', 'COPYRIGHT')->value('id')]);
    }

    /** Mean absolute difference of the grey pixels of one corner of frame 1 of two videos. */
    private function cornerDiff(string $a, string $b, string $corner): float
    {
        $crop = $corner === 'br' ? 'crop=iw/3:ih/4:iw*2/3:ih*3/4' : 'crop=iw/3:ih/4:0:0';
        $grab = fn ($f) => (string) shell_exec('ffmpeg -v error -i '.escapeshellarg($f).' -frames:v 1 -vf '.escapeshellarg($crop.',format=gray').' -f rawvideo - 2>/dev/null');
        [$x, $y] = [$grab($a), $grab($b)];
        $this->assertNotSame('', $x);
        $this->assertSame(strlen($x), strlen($y));
        $sum = 0;
        for ($i = 0, $n = strlen($x); $i < $n; $i++) {
            $sum += abs(ord($x[$i]) - ord($y[$i]));
        }

        return $sum / max(1, strlen($x));
    }

    private function gate(string $path)
    {
        $this->fresh();

        return $this->withHeader('X-Original-URI', $path)->get(route('io.privacy.file-check'));
    }

    // ---------------------------------------------------------------

    public function test_the_watermark_follows_the_record_rule_and_never_touches_the_master(): void
    {
        $this->needFfmpeg();
        DB::table('watermark_setting')->updateOrInsert(['setting_key' => 'default_watermark_enabled'], ['setting_value' => '0']);
        $marked = $this->record();
        $this->watermarkOn($marked);
        $optedOut = $this->record();
        $this->watermarkOn($optedOut, false);
        $plain = $this->record();

        // Off by default: no record is watermarked, whatever its rule says.
        $this->assertNull(MP::videoWatermark($marked));

        $this->setting('media_video_watermark_enabled', 'true');
        $wm = MP::videoWatermark($marked);
        $this->assertSame('image', $wm['kind']);
        $this->assertStringEndsWith('copyright.png', $wm['image']);
        $this->assertSame('bottom-right', $wm['position']);
        $this->assertNull(MP::videoWatermark($optedOut), 'a record that switched its watermark off');
        $this->assertNull(MP::videoWatermark($plain), 'no record rule and the default watermark off');

        $g = MP::overlayGraph($wm, 240);
        $this->assertStringContainsString('overlay=', $g['graph']);
        $this->assertStringContainsString('colorchannelmixer=aa=0.4', $g['graph']);

        $this->setting('media_video_watermark_type', 'text');
        $this->setting('media_video_watermark_text', "Archive: 'x' 100%");
        $t = MP::videoWatermark($marked);
        $this->assertSame(['text', "Archive: 'x' 100%"], [$t['kind'], $t['text']]);
        $g = MP::overlayGraph($t, 240);
        $this->assertStringContainsString('drawtext=textfile=', $g['graph']);
        $this->assertStringContainsString('expansion=none', $g['graph']);
        array_map('unlink', $g['cleanup']);
        $this->setting('media_video_watermark_type', 'image');

        // Burnt in: the same source, with and without the rule (fully opaque, so it shows).
        $this->setting('media_video_watermark_opacity', '1');
        [$idA, , $dirA] = $this->master('320x240', 1, $marked);
        [$idB, , $dirB] = $this->master('320x240', 1, $plain);
        $before = hash_file('sha256', $dirA.'/master_clip.mp4');
        MDS::generateForMaster($idA);
        MDS::generateForMaster($idB);
        $this->assertSame($before, hash_file('sha256', $dirA.'/master_clip.mp4'), 'the master is never changed');
        $this->assertGreaterThan(5, $this->cornerDiff($dirA.'/reference_clip.mp4', $dirB.'/reference_clip.mp4', 'br'), 'the watermark sits bottom right');
        $this->assertLessThan(2, $this->cornerDiff($dirA.'/reference_clip.mp4', $dirB.'/reference_clip.mp4', 'tl'), 'and nowhere else');

        // While watermarked, the original is not handed to the public; the copy is.
        $this->gate('/uploads/r/'.$marked.'/master_clip.mp4')->assertForbidden();
        $this->gate('/uploads/r/'.$marked.'/reference_clip.mp4')->assertNoContent();
        $this->gate('/uploads/r/'.$plain.'/master_clip.mp4')->assertNoContent();
        $this->actingAs($this->user(100));
        $this->gate('/uploads/r/'.$marked.'/master_clip.mp4')->assertNoContent();
    }

    public function test_hls_is_encrypted_with_a_key_per_rendition(): void
    {
        $this->needFfmpeg();
        $this->setting('media_hls_enabled', 'true');
        $this->setting('media_hls_encrypt', 'true');
        $this->setting('media_rendition_ladder', '480,720');
        [$id, , $dir] = $this->master('1280x720', 3);

        MDS::generateForMaster($id);
        $hls = $dir.'/hls_clip';
        $master = (string) @file_get_contents($hls.'/master.m3u8');
        $this->assertMatchesRegularExpression('/#EXT-X-STREAM-INF:.*RESOLUTION=\d+x480.*\R v480\.m3u8/x', $master);
        $this->assertMatchesRegularExpression('/RESOLUTION=\d+x720/', $master);

        $keys = [];
        foreach ([480, 720] as $h) {
            $pl = (string) file_get_contents($hls.'/v'.$h.'.m3u8');
            $this->assertStringContainsString('#EXT-X-KEY:METHOD=AES-128,URI="/media-streaming/hls-key/'.$id.'/'.$h.'"', $pl);
            $this->assertMatchesRegularExpression('/IV=0x[0-9a-f]{32}/', $pl);
            $key = MP::keyPath($id, $h);
            $this->assertSame(16, filesize($key));
            $this->assertStringStartsNotWith($this->root, $key, 'keys are kept outside the served uploads');
            $keys[$h] = file_get_contents($key);
        }
        $this->assertNotSame($keys[480], $keys[720], 'each rendition has its own key');
        $this->assertEmpty(glob($hls.'/*.key*'), 'no key material beside the segments');
        $this->assertEmpty(glob($this->keys.'/'.$id.'/*.keyinfo'));

        // The segment is ciphertext; with the key and IV it is H.264 again.
        $seg = $hls.'/v480_000.ts';
        $this->assertSame('', trim((string) shell_exec('ffprobe -v error -show_entries stream=codec_name -of csv=p=0 '.escapeshellarg($seg).' 2>/dev/null')));
        preg_match('/IV=0x([0-9a-f]{32})/', file_get_contents($hls.'/v480.m3u8'), $m);
        $plain = $this->root.'/dec.ts';
        shell_exec(sprintf('openssl aes-128-cbc -d -K %s -iv %s -in %s -out %s 2>&1', bin2hex($keys[480]), $m[1], escapeshellarg($seg), escapeshellarg($plain)));
        $this->assertStringContainsString('h264', (string) shell_exec('ffprobe -v error -show_entries stream=codec_name -of csv=p=0 '.escapeshellarg($plain).' 2>/dev/null'));

        // Made again: new keys (rotation).
        MDS::generateHls('ffmpeg', $dir.'/master_clip.mp4', $hls, [480], $id);
        $this->assertNotSame($keys[480], file_get_contents(MP::keyPath($id, 480)));
        $this->assertFileDoesNotExist(MP::keyPath($id, 720), 'old keys are dropped');

        // Encryption off: plain HLS, as before.
        $this->setting('media_hls_encrypt', 'false');
        MDS::generateHls('ffmpeg', $dir.'/master_clip.mp4', $hls, [480], $id);
        $this->assertStringNotContainsString('#EXT-X-KEY', (string) file_get_contents($hls.'/v480.m3u8'));
    }

    public function test_the_key_route_follows_access(): void
    {
        $key = fn (int $master) => $this->get(route('media-streaming.hls-key', [$master, 480]));
        $withKey = function (int $io): int {
            $m = $this->rows($io);
            @mkdir($this->keys.'/'.$m, 0777, true);
            file_put_contents(MP::keyPath($m, 480), random_bytes(16));

            return $m;
        };
        $open = $withKey($this->record());
        $embargoed = $withKey($embargoIo = $this->record());
        $this->embargo($embargoIo);
        $draft = $withKey($this->record(159));
        $held = $withKey($this->record());
        MP::setClearance($held, false, 'awaiting legal');

        $this->fresh();
        $r = $key($open)->assertOk();
        $this->assertSame(16, strlen($r->getContent()));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        foreach ([$embargoed, $draft, $held] as $m) {
            $this->fresh();
            $key($m)->assertUnauthorized();
        }
        $this->fresh();
        $this->get(route('media-streaming.hls-key', [$open, 720]))->assertNotFound();

        // Signed in without staff rights: still refused.
        $this->actingAs($this->user());
        $this->fresh();
        $key($embargoed)->assertForbidden();
        $this->fresh();
        $key($open)->assertOk();

        // Staff play everything, as on the /uploads gate.
        $this->actingAs($this->user(101));
        foreach ([$embargoed, $draft, $held] as $m) {
            $this->fresh();
            $key($m)->assertOk();
        }
    }

    public function test_uncleared_media_is_hidden_from_the_public(): void
    {
        $io = $this->record();
        $master = $this->rows($io);
        $base = '/uploads/r/mp-'.$io.'/';

        // Nothing required, nothing held: plays as before.
        $this->gate($base.'reference_film.mp4')->assertNoContent();

        $this->setting('media_clearance_required', 'true');
        $this->assertTrue(MP::awaitsClearance(DB::table('digital_object')->find($master)));
        $this->gate($base.'reference_film.mp4')->assertForbidden();
        $this->gate($base.'master_film.mov')->assertForbidden();
        $this->gate($base.'hls_film/master.m3u8')->assertForbidden();
        $this->gate($base.'hls_film/v480_000.ts')->assertForbidden();
        $this->fresh();
        $this->get(route('media-streaming.stream', $master))->assertForbidden();

        // Images are not affected by audio/video clearance.
        $this->gate($base.'some_scan.jpg')->assertNoContent();

        $html = view('theme::components.media-player', ['type' => 'video', 'playerId' => 'p1', 'src' => $base.'master_film.mov',
            'mime' => 'video/quicktime', 'name' => 'film.mov', 'masterUrl' => $base.'master_film.mov', 'masterMime' => 'video/quicktime', 'byteSize' => 1])->render();
        $this->assertStringContainsString('not yet been cleared for release', $html);
        $this->assertStringNotContainsString('<video', $html);

        // Staff see it, with the clearance control.
        $editor = $this->user(101);
        $this->actingAs($editor);
        $this->gate($base.'reference_film.mp4')->assertNoContent();
        $html = view('theme::components.media-player', ['type' => 'video', 'playerId' => 'p2', 'src' => $base.'master_film.mov',
            'mime' => 'video/quicktime', 'name' => 'film.mov', 'masterUrl' => $base.'master_film.mov', 'masterMime' => 'video/quicktime', 'byteSize' => 1])->render();
        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('Clear for release', $html);

        // A plain user may not clear it; an editor may, and it is recorded and audited.
        $this->actingAs($this->user());
        $this->fresh();
        $this->post(route('media-streaming.clearance', $master), ['decision' => 'clear'])->assertForbidden();
        $this->actingAs($editor);
        $this->fresh();
        $this->post(route('media-streaming.clearance', $master), ['decision' => 'clear', 'note' => 'rights checked'])->assertRedirect();
        $row = MP::clearance($master);
        $this->assertSame(MP::STATUS_CLEARED, $row->status);
        $this->assertSame((int) $editor->id, (int) $row->cleared_by);
        $this->assertNotNull($row->cleared_at);
        $audit = DB::table('security_audit_log')->where('object_id', $master)->where('action', 'media_cleared_for_release')->first();
        $this->assertNotNull($audit);
        $this->assertSame((int) $editor->id, (int) $audit->user_id);
        $this->assertStringContainsString('rights checked', $audit->details);

        auth()->logout();
        $this->gate($base.'reference_film.mp4')->assertNoContent();
        $this->gate($base.'hls_film/v480_000.ts')->assertNoContent();

        // Held back again: hidden again, even with clearance not required.
        $this->setting('media_clearance_required', 'false');
        $this->actingAs($editor);
        MP::setClearance($master, false);
        auth()->logout();
        $this->gate($base.'reference_film.mp4')->assertForbidden();
        $this->assertSame(1, DB::table('security_audit_log')->where('object_id', $master)->where('action', 'media_release_withheld')->count());
    }

    public function test_encrypted_clip_on_an_embargoed_record_end_to_end(): void
    {
        $this->needFfmpeg();
        $this->setting('media_hls_enabled', 'true');
        $this->setting('media_hls_encrypt', 'true');
        $this->setting('media_rendition_ladder', '480');
        [$id, $io, $dir] = $this->master('320x240', 3);
        MDS::generateForMaster($id);
        $pl = (string) file_get_contents($dir.'/hls_clip/v240.m3u8');
        $this->assertStringContainsString('#EXT-X-KEY:METHOD=AES-128', $pl);
        preg_match('/URI="([^"]+)"/', $pl, $m);

        $this->fresh();
        $this->get($m[1])->assertOk();
        $this->embargo($io);
        $this->get($m[1])->assertUnauthorized();
        $this->gate('/uploads/r/'.$io.'/hls_clip/v240_000.ts')->assertForbidden();
    }
}
