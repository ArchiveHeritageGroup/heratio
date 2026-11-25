<?php

/**
 * PublishPackageTest - GCIS 6.8.2, heratio#1761.
 *
 * A tiny clip made by ffmpeg (testsrc, 2 seconds) stands in for a record's
 * reference MP4 in a temp uploads folder; packages go to another temp folder.
 * Covers: a record the public may not see, or whose media is not cleared,
 * is refused; the TikTok package is 1080x1920 and the WhatsApp one is under
 * 16 MB; the caption is cut to the platform's length; trimming happens only
 * when asked; the log row is made, downloaded and then marked posted, with
 * audit rows; and only staff with update permission get in.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DigitalObjectService as DOS;
use AhgCore\Services\MediaProtection as MP;
use AhgCore\Support\StatusRow;
use AhgMediaStreaming\Services\PublishPackageService as PPS;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublishPackageTest extends TestCase
{
    use DatabaseTransactions;

    // Empty until setUp() gets past its skip checks: a skipped test (CI has no
    // ffmpeg) still runs tearDown(), which must not touch unset folders.
    private string $root = '';

    private string $out = '';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['digital_object', 'information_object', 'status', 'ahg_dropdown', PPS::TABLE, MP::TABLE] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        if (trim((string) shell_exec('command -v ffmpeg')) === '' || trim((string) shell_exec('command -v ffprobe')) === '') {
            $this->markTestSkipped('ffmpeg/ffprobe not installed');
        }
        $this->root = sys_get_temp_dir().'/ahg-pub-'.uniqid();
        $this->out = sys_get_temp_dir().'/ahg-pub-out-'.uniqid();
        mkdir($this->root, 0777, true);
        config(['heratio.uploads_path' => $this->root, 'heratio.publish_packages_path' => $this->out]);
        DB::table('ahg_settings')->whereIn('setting_key', ['media_clearance_required', 'publish_credit_line'])->delete();
        $this->fresh();
    }

    protected function tearDown(): void
    {
        if ($this->root !== '') {
            shell_exec('rm -rf '.escapeshellarg($this->root).' '.escapeshellarg($this->out));
        }
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function fresh(): void
    {
        AhgSettingsService::clearCache();
        AclService::forgetUser();
        app()->forgetInstance(\AhgCore\Services\DisclosureGate::class);
    }

    private function record(int $status = 160, string $desc = 'A short film.'): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => 'Parliament opening 1994', 'scope_and_content' => $desc]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'pub-test-'.$id]);
        StatusRow::set($id, 158, $status);

        return $id;
    }

    /** A master row and its reference MP4 (a real 2 s clip) for record $io; returns the master id. */
    private function media(int $io, string $size = '320x240', int $seconds = 2): int
    {
        $dir = $this->root.'/r/'.$io;
        @mkdir($dir, 0777, true);
        shell_exec('ffmpeg -v error -y -f lavfi -i testsrc=size='.$size.':rate=10:duration='.$seconds.' -f lavfi -i sine=duration='.$seconds
            .' -c:v libx264 -preset ultrafast -c:a aac -shortest '.escapeshellarg($dir.'/reference_clip.mp4').' 2>&1');
        $this->assertFileExists($dir.'/reference_clip.mp4');
        $ins = function (array $row) use ($io) {
            $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('digital_object')->insert($row + ['id' => $id, 'object_id' => $io, 'path' => '/uploads/r/'.$io.'/', 'mime_type' => 'video/mp4', 'media_type_id' => DOS::MEDIA_VIDEO]);

            return $id;
        };
        $master = $ins(['usage_id' => DOS::USAGE_MASTER, 'name' => 'master_clip.mov']); // no master file: it must never be needed
        $ins(['parent_id' => $master, 'usage_id' => DOS::USAGE_REFERENCE, 'name' => 'reference_clip.mp4']);

        return $master;
    }

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'pub-'.$id, 'email' => uniqid('pub-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function editor(): User
    {
        return $this->user(\AhgCore\Models\AclGroup::EDITOR_ID);
    }

    /** Unpack a package zip into a temp folder; returns the folder. */
    private function unzip(string $zip): string
    {
        $dir = $this->out.'/x-'.uniqid();
        $z = new \ZipArchive;
        $this->assertTrue($z->open($zip) === true);
        $z->extractTo($dir);
        $z->close();

        return $dir;
    }

    private function dims(string $file): string
    {
        return trim((string) shell_exec('ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 '.escapeshellarg($file)));
    }

    // ---------------------------------------------------------------

    public function test_a_record_the_public_may_not_see_or_not_cleared_is_refused(): void
    {
        $svc = app(PPS::class);
        $this->actingAs($this->editor());

        // Draft: staff may see it, a platform may not.
        $draft = $this->record(159);
        $this->media($draft);
        $this->assertNotSame([], $svc->refusals($draft));
        $this->post(route('media-streaming.publish.store', $draft), ['platforms' => ['youtube']])->assertSessionHasErrors('platforms');
        $this->assertSame(0, DB::table(PPS::TABLE)->where('information_object_id', $draft)->count());

        // Embargoed.
        $emb = $this->record();
        $this->media($emb);
        DB::table('embargo')->insert(['object_id' => $emb, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(), 'status' => 'active', 'is_active' => 1]);
        $this->fresh();
        $this->assertNotSame([], $svc->refusals($emb));

        // Published but its media is held back from release.
        $held = $this->record();
        $master = $this->media($held);
        $this->assertSame([], $svc->refusals($held));
        MP::setClearance($master, false, 'rights not confirmed');
        $why = $svc->refusals($held);
        $this->assertContains(__('The media has not been cleared for release.'), $why);
        $this->post(route('media-streaming.publish.store', $held), ['platforms' => ['youtube']])->assertSessionHasErrors('platforms');
        $this->assertSame(0, DB::table(PPS::TABLE)->where('information_object_id', $held)->count());

        // Clearance required site-wide and never given: refused too.
        $req = $this->record();
        $this->media($req);
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'media_clearance_required'], ['setting_value' => 'true', 'setting_group' => 'media']);
        $this->fresh();
        $this->assertContains(__('The media has not been cleared for release.'), $svc->refusals($req));

        // The service refuses as well, not only the controller.
        $this->expectException(\RuntimeException::class);
        $svc->request($draft, ['youtube'], false, null);
    }

    public function test_tiktok_is_1080x1920_and_whatsapp_is_under_16_mb_with_caption_and_thumbnail(): void
    {
        $io = $this->record();
        $this->media($io);
        $this->actingAs($this->editor());

        $this->post(route('media-streaming.publish.store', $io), ['platforms' => ['tiktok', 'whatsapp']])->assertRedirect();
        $rows = DB::table(PPS::TABLE)->where('information_object_id', $io)->get()->keyBy('platform');
        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame(PPS::STATUS_MADE, $row->status, (string) $row->error);
            $this->assertStringStartsWith($this->out.'/', $row->package_path); // never in the uploads tree
            $this->assertStringNotContainsString($this->root, $row->package_path);
        }

        $tik = $this->unzip($rows['tiktok']->package_path);
        $this->assertFileExists($tik.'/caption.txt');
        $this->assertFileExists($tik.'/thumbnail.jpg');
        $this->assertSame('1080x1920', $this->dims($tik.'/video.mp4'));
        $codecs = trim((string) shell_exec('ffprobe -v error -show_entries stream=codec_name -of csv=p=0 '.escapeshellarg($tik.'/video.mp4')));
        $this->assertSame("h264\naac", $codecs);

        $wa = $this->unzip($rows['whatsapp']->package_path);
        $this->assertSame('1280x720', $this->dims($wa.'/video.mp4'));
        $this->assertLessThan(16000000, filesize($wa.'/video.mp4'));
        $this->assertSame(filesize($wa.'/video.mp4'), (int) $rows['whatsapp']->byte_size);
        $this->assertStringContainsString(url('/pub-test-'.$io), file_get_contents($wa.'/caption.txt'));
    }

    public function test_the_caption_is_cut_to_the_platform_length_keeping_credit_and_url(): void
    {
        $long = str_repeat('The opening of the first democratic Parliament, filmed from the press gallery. ', 30);
        $io = $this->record(160, $long);
        $svc = app(PPS::class);
        $parts = $svc->captionParts($io);
        $this->assertLessThanOrEqual(503, mb_strlen($parts['description'])); // "short description"

        foreach (['x' => 280, 'whatsapp' => 1024, 'tiktok' => 2200] as $platform => $max) {
            $this->assertSame($max, (int) $svc->presets()[$platform]['caption_max']);
            $c = $svc->caption($parts, $max);
            $this->assertLessThanOrEqual($max, mb_strlen($c), $platform);
            $this->assertStringContainsString('Parliament opening 1994', $c);
            $this->assertStringContainsString($parts['credit'], $c);
            $this->assertStringEndsWith(url('/pub-test-'.$io), $c);
        }
        $this->assertStringContainsString('...', $svc->caption($parts, 280));

        // The package's caption.txt is the cut caption.
        $this->media($io);
        $this->actingAs($this->editor());
        $this->post(route('media-streaming.publish.store', $io), ['platforms' => ['x']]);
        $row = DB::table(PPS::TABLE)->where('information_object_id', $io)->first();
        $this->assertSame(PPS::STATUS_MADE, $row->status, (string) $row->error);
        $txt = rtrim(file_get_contents($this->unzip($row->package_path).'/caption.txt'), "\n");
        $this->assertSame($row->caption, $txt);
        $this->assertLessThanOrEqual(280, mb_strlen($txt));
    }

    public function test_trim_only_when_asked(): void
    {
        DB::table('ahg_dropdown')->where('taxonomy', 'publish_platform')->where('code', 'x')
            ->update(['metadata' => DB::raw("JSON_SET(metadata, '$.max_duration', 1)")]);
        $io = $this->record();
        $this->media($io);
        $this->actingAs($this->editor());

        $this->post(route('media-streaming.publish.store', $io), ['platforms' => ['x']]);
        $row = DB::table(PPS::TABLE)->where('information_object_id', $io)->orderByDesc('id')->first();
        $this->assertSame(PPS::STATUS_FAILED, $row->status);
        $this->assertStringContainsString('trim', $row->error);

        $this->post(route('media-streaming.publish.store', $io), ['platforms' => ['x'], 'trim' => '1']);
        $row = DB::table(PPS::TABLE)->where('information_object_id', $io)->orderByDesc('id')->first();
        $this->assertSame(PPS::STATUS_MADE, $row->status, (string) $row->error);
        $this->assertEquals(1, (int) $row->trimmed);
        $this->assertLessThan(1.5, (float) $row->output_duration);
        $this->assertGreaterThan(1.9, (float) $row->source_duration);
    }

    public function test_the_log_row_is_made_downloaded_then_marked_posted_and_audited(): void
    {
        $io = $this->record();
        $this->media($io);
        $editor = $this->editor();
        $this->actingAs($editor);

        $this->post(route('media-streaming.publish.store', $io), ['platforms' => ['youtube']]);
        $row = DB::table(PPS::TABLE)->where('information_object_id', $io)->first();
        $this->assertSame(PPS::STATUS_MADE, $row->status, (string) $row->error);
        $this->assertSame($editor->id, (int) $row->made_by);
        $this->assertNotNull($row->made_at);
        $this->assertSame('1920x1080', $this->dims($this->unzip($row->package_path).'/video.mp4'));

        $this->get(route('media-streaming.publish.download', $row->id))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->assertSame(PPS::STATUS_DOWNLOADED, DB::table(PPS::TABLE)->where('id', $row->id)->value('status'));

        // A posted URL is validated.
        $this->post(route('media-streaming.publish.posted', $row->id), ['posted_url' => 'javascript:alert(1)', 'posted_at' => now()->toDateString()])
            ->assertSessionHasErrors('posted_url');

        $this->post(route('media-streaming.publish.posted', $row->id), ['posted_url' => 'https://www.youtube.com/watch?v=abc123', 'posted_at' => now()->toDateString()])
            ->assertSessionHasNoErrors();
        $row = DB::table(PPS::TABLE)->where('id', $row->id)->first();
        $this->assertSame(PPS::STATUS_POSTED, $row->status);
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $row->posted_url);
        $this->assertSame(now()->toDateString(), $row->posted_at);
        $this->assertSame('manual', $row->posted_via);
        $this->assertSame($editor->id, (int) $row->posted_by);

        if (Schema::hasTable('security_audit_log')) {
            $actions = DB::table('security_audit_log')->where('object_id', $io)->pluck('action')->all();
            foreach (['publish_package_requested', 'publish_package_made', 'publish_package_downloaded', 'publish_package_posted'] as $a) {
                $this->assertContains($a, $actions);
            }
        }

        // Shown in the record's log and the admin list.
        $this->get(route('media-streaming.publish.form', $io))->assertOk()->assertSee('https://www.youtube.com/watch?v=abc123')->assertSee('API');
        $this->get(route('media-streaming.publish.index'))->assertOk()->assertSee('Parliament opening 1994');

        // The record page carries the staff panel; a plain user does not see it.
        $this->get('/pub-test-'.$io)->assertOk()->assertSee(route('media-streaming.publish.form', $io));
        $this->actingAs($this->user());
        AclService::forgetUser();
        $this->get('/pub-test-'.$io)->assertOk()->assertDontSee(route('media-streaming.publish.form', $io));
    }

    public function test_staff_only(): void
    {
        $io = $this->record();
        $this->media($io);

        // Guest: sent to sign in.
        $this->get(route('media-streaming.publish.form', $io))->assertRedirect();
        $this->post(route('media-streaming.publish.store', $io), ['platforms' => ['youtube']])->assertRedirect();
        $this->assertSame(0, DB::table(PPS::TABLE)->where('information_object_id', $io)->count());

        // Signed in without a staff group: refused everywhere.
        $this->actingAs($this->user());
        $this->get(route('media-streaming.publish.form', $io))->assertForbidden();
        $this->post(route('media-streaming.publish.store', $io), ['platforms' => ['youtube']])->assertForbidden();
        $this->get(route('media-streaming.publish.index'))->assertForbidden();
        $this->assertSame(0, DB::table(PPS::TABLE)->where('information_object_id', $io)->count());

        // An editor's package stays out of reach of the plain user.
        $this->actingAs($this->editor());
        $this->post(route('media-streaming.publish.store', $io), ['platforms' => ['whatsapp']]);
        $id = (int) DB::table(PPS::TABLE)->where('information_object_id', $io)->value('id');
        $this->assertGreaterThan(0, $id);

        $this->actingAs($this->user());
        AclService::forgetUser();
        $this->get(route('media-streaming.publish.download', $id))->assertForbidden();
        $this->post(route('media-streaming.publish.posted', $id), ['posted_url' => 'https://example.test/x', 'posted_at' => now()->toDateString()])->assertForbidden();
        $this->assertSame(PPS::STATUS_MADE, DB::table(PPS::TABLE)->where('id', $id)->value('status'));
    }
}
