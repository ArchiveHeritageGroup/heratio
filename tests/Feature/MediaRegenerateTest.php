<?php

/**
 * MediaRegenerateTest - ahg:media-regenerate (heratio#1771 follow-up).
 *
 * Tiny ffmpeg testsrc clips in a temp folder standing in for the uploads
 * path, HLS keys in another, as in MediaProtectionTest. Covers: running
 * twice leaves one set of derivative rows (and clears older leftovers); the
 * watermark reaches an existing copy after it is switched on; HLS is
 * encrypted after encryption is switched on, with old keys and segments
 * gone; the master is never changed; a file the shared-uploads guard
 * objects to is refused; --dry-run changes nothing; --queue, the settings
 * button and a replaced master queue one job per master.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Jobs\RegenerateMediaJob;
use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DigitalObjectService as DOS;
use AhgCore\Services\DisclosureGate;
use AhgCore\Services\MasterAccess;
use AhgCore\Services\MediaDerivativeService as MDS;
use AhgCore\Services\MediaProtection as MP;
use AhgCore\Services\MediaRegenerateService as MRS;
use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaRegenerateTest extends TestCase
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
        $this->root = sys_get_temp_dir().'/ahg-mregen-'.uniqid();
        $this->keys = sys_get_temp_dir().'/ahg-mregen-keys-'.uniqid();
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

    private function fresh(): void
    {
        AhgSettingsService::clearCache();
        AclService::forgetUser();
        MasterAccess::forget();
        app()->forgetInstance(DisclosureGate::class);
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

    private function record(): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        StatusRow::set($id, 158, 160);

        return $id;
    }

    /** A video master made by ffmpeg in uploads/r/<folder>/ (the record id unless given). */
    private function master(string $size = '320x240', int $seconds = 1, ?int $io = null, ?string $folder = null): array
    {
        $io ??= $this->record();
        $folder ??= (string) $io;
        $dir = $this->root.'/r/'.$folder;
        @mkdir($dir, 0777, true);
        shell_exec('ffmpeg -v error -y '.sprintf(self::CLIP, $size, $seconds).' '.escapeshellarg($dir.'/master_clip.mp4').' 2>&1');
        $this->assertFileExists($dir.'/master_clip.mp4');
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $io, 'usage_id' => DOS::USAGE_MASTER, 'name' => 'master_clip.mp4',
            'path' => '/uploads/r/'.$folder.'/', 'mime_type' => 'video/mp4', 'media_type_id' => DOS::MEDIA_VIDEO, 'byte_size' => filesize($dir.'/master_clip.mp4')]);

        return [$id, $io, $dir];
    }

    private function derivativeRow(int $master, int $io, string $name, int $usage, string $mime): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $io, 'parent_id' => $master, 'usage_id' => $usage, 'name' => $name,
            'path' => '/uploads/r/'.$io.'/', 'mime_type' => $mime, 'media_type_id' => DOS::MEDIA_VIDEO]);

        return $id;
    }

    /** usage_id => [names] of a master's derivative rows. */
    private function rowsOf(int $master): array
    {
        $out = [];
        foreach (DB::table('digital_object')->where('parent_id', $master)->orderBy('id')->get() as $r) {
            $out[(int) $r->usage_id][] = $r->name;
        }

        return $out;
    }

    /** Every file under $dir with its sha256. */
    private function tree(string $dir): array
    {
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $out[$f->getPathname()] = hash_file('sha256', $f->getPathname());
        }
        ksort($out);

        return $out;
    }

    private function cornerDiff(string $a, string $b): float
    {
        $grab = fn ($f) => (string) shell_exec('ffmpeg -v error -i '.escapeshellarg($f).' -frames:v 1 -vf '.escapeshellarg('crop=iw/3:ih/4:iw*2/3:ih*3/4,format=gray').' -f rawvideo - 2>/dev/null');
        [$x, $y] = [$grab($a), $grab($b)];
        $this->assertNotSame('', $x);
        $this->assertSame(strlen($x), strlen($y));
        $sum = 0;
        for ($i = 0, $n = strlen($x); $i < $n; $i++) {
            $sum += abs(ord($x[$i]) - ord($y[$i]));
        }

        return $sum / max(1, strlen($x));
    }

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'mregen-'.$id, 'email' => uniqid('mregen-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    // ---------------------------------------------------------------

    public function test_running_twice_leaves_one_set_of_derivative_rows(): void
    {
        $this->needFfmpeg();
        [$id, $io, $dir] = $this->master('320x240', 2); // the poster is taken 1 s in
        $sha = hash_file('sha256', $dir.'/master_clip.mp4');
        MDS::generateForMaster($id);
        MDS::generateForMaster($id); // the plain generator is now idempotent too
        $this->assertSame([DOS::USAGE_REFERENCE => ['reference_clip.mp4'], DOS::USAGE_THUMBNAIL => ['thumbnail_clip.jpg']], $this->rowsOf($id));

        // Leftovers from before: a duplicate row of the same copy, and an older profile's copy with its file.
        $this->derivativeRow($id, $io, 'reference_clip.mp4', DOS::USAGE_REFERENCE, 'video/mp4');
        $this->derivativeRow($id, $io, 'reference_clip.webm', DOS::USAGE_REFERENCE, 'video/webm');
        file_put_contents($dir.'/reference_clip.webm', 'old');

        $this->artisan('ahg:media-regenerate', ['--id' => [$id]])->expectsOutputToContain('1 regenerated')->assertSuccessful();
        $this->artisan('ahg:media-regenerate', ['--id' => [$id]])->assertSuccessful();

        $this->assertSame([DOS::USAGE_REFERENCE => ['reference_clip.mp4'], DOS::USAGE_THUMBNAIL => ['thumbnail_clip.jpg']], $this->rowsOf($id));
        $this->assertFileDoesNotExist($dir.'/reference_clip.webm', 'a stale copy beside the master is removed');
        $this->assertFileExists($dir.'/reference_clip.mp4');
        $row = DB::table('digital_object')->where('parent_id', $id)->where('usage_id', DOS::USAGE_REFERENCE)->first();
        $this->assertSame(hash_file('sha256', $dir.'/reference_clip.mp4'), $row->checksum, 'the row describes the file now on disk');
        $this->assertSame($sha, hash_file('sha256', $dir.'/master_clip.mp4'), 'the master is never changed');
    }

    public function test_the_watermark_is_applied_after_switching_it_on(): void
    {
        $this->needFfmpeg();
        DB::table('watermark_setting')->updateOrInsert(['setting_key' => 'default_watermark_enabled'], ['setting_value' => '0']);
        $io = $this->record();
        DB::table('object_watermark_setting')->insert(['object_id' => $io, 'watermark_enabled' => 1,
            'watermark_type_id' => DB::table('watermark_type')->where('code', 'COPYRIGHT')->value('id')]);
        [$id, , $dir] = $this->master('320x240', 1, $io);
        $sha = hash_file('sha256', $dir.'/master_clip.mp4');

        MDS::generateForMaster($id); // made while the watermark was off
        copy($dir.'/reference_clip.mp4', $this->root.'/before.mp4');

        $this->setting('media_video_watermark_enabled', 'true');
        $this->setting('media_video_watermark_opacity', '1');
        $r = MRS::regenerate($id);
        $this->assertSame('regenerated', $r['status'], $r['detail']);
        $this->assertGreaterThan(5, $this->cornerDiff($this->root.'/before.mp4', $dir.'/reference_clip.mp4'), 'the watermark is burnt into the existing copy');
        $this->assertSame($sha, hash_file('sha256', $dir.'/master_clip.mp4'));
        $this->assertCount(1, $this->rowsOf($id)[DOS::USAGE_REFERENCE]);
    }

    public function test_hls_is_encrypted_after_switching_it_on_and_old_keys_are_gone(): void
    {
        $this->needFfmpeg();
        $this->setting('media_hls_enabled', 'true');
        $this->setting('media_rendition_ladder', '480');
        [$id, , $dir] = $this->master('320x240', 2);
        $sha = hash_file('sha256', $dir.'/master_clip.mp4');
        MDS::generateForMaster($id);
        $hls = $dir.'/hls_clip';
        $this->assertStringNotContainsString('#EXT-X-KEY', (string) file_get_contents($hls.'/v240.m3u8'));

        // Leftovers: an old key of a rendition no longer made, and an old segment.
        @mkdir($this->keys.'/'.$id, 0777, true);
        file_put_contents(MP::keyPath($id, 1080), random_bytes(16));
        file_put_contents($hls.'/v1080_000.ts', 'old');

        $this->setting('media_hls_encrypt', 'true');
        $r = MRS::regenerate($id);
        $this->assertSame('regenerated', $r['status'], $r['detail']);
        $this->assertStringContainsString('HLS encrypted', $r['detail']);
        $pl = (string) file_get_contents($hls.'/v240.m3u8');
        $this->assertStringContainsString('#EXT-X-KEY:METHOD=AES-128,URI="/media-streaming/hls-key/'.$id.'/240"', $pl);
        $first = file_get_contents(MP::keyPath($id, 240));
        $this->assertSame(16, strlen($first));
        $this->assertFileDoesNotExist(MP::keyPath($id, 1080), 'old keys are removed');
        $this->assertFileDoesNotExist($hls.'/v1080_000.ts', 'old segments are removed');

        // Again: fresh keys.
        MRS::regenerate($id);
        $this->assertNotSame($first, file_get_contents(MP::keyPath($id, 240)));

        // HLS switched off: the old HLS folder and its keys go.
        $this->setting('media_hls_enabled', 'false');
        MRS::regenerate($id);
        $this->assertDirectoryDoesNotExist($hls);
        $this->assertFileDoesNotExist(MP::keyPath($id, 240));
        $this->assertSame($sha, hash_file('sha256', $dir.'/master_clip.mp4'), 'the master is never changed');
    }

    public function test_a_file_the_shared_uploads_guard_objects_to_is_refused(): void
    {
        $this->needFfmpeg();
        // Guard on (a shared catalogue is configured); a repository-slug folder is the other system's.
        config(['database.connections.shared_uploads' => array_merge(config('database.connections.mysql'), ['database' => 'shared_catalogue_test'])]);
        [$id, , $dir] = $this->master('320x240', 1, null, 'demo-repository');
        $before = $this->tree($dir);

        $r = MRS::regenerate($id);
        $this->assertSame('refused', $r['status']);
        $this->assertStringContainsString('numeric folder', $r['detail']);
        $this->assertSame($before, $this->tree($dir), 'nothing written beside a shared file');
        $this->assertSame([], $this->rowsOf($id));

        $this->artisan('ahg:media-regenerate', ['--id' => [$id]])->expectsOutputToContain('1 refused')->assertSuccessful();
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->needFfmpeg();
        $this->setting('media_hls_enabled', 'true');
        $this->setting('media_rendition_ladder', '480');
        [$id, $io, $dir] = $this->master();
        MDS::generateForMaster($id);
        $this->derivativeRow($id, $io, 'reference_clip.mp4', DOS::USAGE_REFERENCE, 'video/mp4'); // a duplicate to clean
        $files = $this->tree($this->root);
        $rows = DB::table('digital_object')->where('parent_id', $id)->orderBy('id')->get()->toArray();
        $this->setting('media_hls_encrypt', 'true');

        $this->artisan('ahg:media-regenerate', ['--record' => [$io], '--dry-run' => true])
            ->expectsOutputToContain('would-regenerate')->assertSuccessful();

        $this->assertSame($files, $this->tree($this->root));
        $this->assertEquals($rows, DB::table('digital_object')->where('parent_id', $id)->orderBy('id')->get()->toArray());
        $this->assertDirectoryDoesNotExist($this->keys.'/'.$id);
    }

    public function test_queue_option_button_and_replaced_master_queue_one_job_per_master(): void
    {
        $io = $this->record();
        $video = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $video, 'object_id' => $io, 'usage_id' => DOS::USAGE_MASTER, 'name' => 'master_x.mov',
            'path' => '/uploads/r/'.$io.'/', 'mime_type' => 'video/quicktime', 'media_type_id' => DOS::MEDIA_VIDEO]);
        $audio = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $audio, 'object_id' => $io, 'usage_id' => DOS::USAGE_MASTER, 'name' => 'master_y.wav',
            'path' => '/uploads/r/'.$io.'/', 'mime_type' => 'audio/wav', 'media_type_id' => DOS::MEDIA_AUDIO]);

        $this->assertContains($video, MRS::targets([], [], true));
        $this->assertNotContains($audio, MRS::targets([], [], true), '--all-video is video only');
        $this->assertSame([$video, $audio], MRS::targets([], [$io]));

        Queue::fake();
        $this->artisan('ahg:media-regenerate', ['--record' => [$io], '--queue' => true])->expectsOutputToContain('2 master(s) queued')->assertSuccessful();
        Queue::assertPushed(RegenerateMediaJob::class, 2);
        $this->assertSame(1800, (new RegenerateMediaJob($video))->timeout);

        // A replaced or checked-in video gets the clean regenerate.
        mkdir($this->root.'/r/'.$io, 0777, true);
        file_put_contents($this->root.'/r/'.$io.'/master_x.mov', 'x');
        DOS::refreshDerivatives($video);
        Queue::assertPushed(RegenerateMediaJob::class, fn ($j) => $j->masterId === $video);
        Queue::assertPushed(RegenerateMediaJob::class, 3);

        // The settings button: administrators only.
        $total = count(MRS::targets([], [], true));
        $this->actingAs($this->user());
        $this->fresh();
        $this->post(route('settings.ahg.media.regenerate'))->assertStatus(403);
        Queue::assertPushed(RegenerateMediaJob::class, 3);

        $this->actingAs($this->user(100));
        $this->fresh();
        $this->get(route('settings.ahg.media'))->assertOk()->assertSee('Regenerate playback copies for all videos')
            ->assertSee(route('settings.ahg.media.regenerate'));
        $this->post(route('settings.ahg.media.regenerate'))->assertRedirect(route('settings.ahg.media'))
            ->assertSessionHas('notice', fn ($n) => str_contains($n, $total.' video master(s) queued'));
        Queue::assertPushed(RegenerateMediaJob::class, 3 + $total);
    }
}
