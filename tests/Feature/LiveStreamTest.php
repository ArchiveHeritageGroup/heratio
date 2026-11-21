<?php

/**
 * LiveStreamTest - GCIS 5.1.1, heratio#1743: live events.
 *
 * Fast tests (no ffmpeg): the event gets a random stream key and the ingest
 * URL from the settings; the live folder may not sit inside the uploads path;
 * one live worker per port; the playlist route follows the record's media
 * access (draft refused to the public, published allowed, staff always) and
 * serves only playlist and segment names; Start queues the worker job on the
 * live connection and is refused to a plain signed-in user; the record page
 * says "Starts at" before and "Ended" after.
 *
 * Live test (ffmpeg, about 10 seconds): the worker listens on a free high
 * port; a background ffmpeg first publishes testsrc under a WRONG key (the
 * worker drops it), then under the right key for 5 seconds. Checks the live
 * HLS playlist appears, the event goes live then ended, and the recording
 * becomes a video master on the record through the normal create path.
 * Skipped when ffmpeg / ffprobe are not installed.
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
use AhgCore\Services\MasterAccess;
use AhgCore\Support\StatusRow;
use AhgMediaStreaming\Jobs\RunLiveIngestJob;
use AhgMediaStreaming\Services\LiveStreamService as LSS;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiveStreamTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    private string $live;

    private LSS $svc;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['digital_object', 'information_object', 'status', 'ahg_settings', LSS::TABLE] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $this->root = sys_get_temp_dir().'/ahg-live-up-'.uniqid();
        $this->live = sys_get_temp_dir().'/ahg-live-'.uniqid();
        mkdir($this->root, 0777, true);
        config(['heratio.uploads_path' => $this->root, 'heratio.live_path' => $this->live]);
        DB::table(LSS::TABLE)->delete(); // inside the transaction: no other event holds a port
        $this->fresh();
        $this->svc = app(LSS::class);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root).' '.escapeshellarg($this->live).' '.escapeshellarg($this->live.'-push.log').' '.escapeshellarg($this->live.'-push.log.err'));
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function fresh(): void
    {
        AhgSettingsService::clearCache();
        AclService::forgetUser();
        MasterAccess::forget();
        app()->forgetInstance(\AhgCore\Services\DisclosureGate::class);
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

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'live-'.$id, 'email' => uniqid('live-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        \Illuminate\Support\Facades\Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function event(int $io, string $title = 'Launch'): object
    {
        return $this->svc->find($this->svc->create($io, $title, now()->addHour()->format('Y-m-d H:i:s')));
    }

    private function freePort(): int
    {
        $s = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($s, false), ':'), 1);
        fclose($s);

        return $port;
    }

    // ── fast ──────────────────────────────────────────────────────────────

    public function test_event_gets_a_random_key_and_the_ingest_url_from_settings(): void
    {
        $this->setting('live_ingest_host', 'live.example.test');
        $this->setting('live_ingest_port', '19350');
        $io = $this->record();
        $a = $this->event($io);
        $b = $this->event($io);

        $this->assertSame(LSS::SCHEDULED, $a->status);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $a->stream_key);
        $this->assertNotSame($a->stream_key, $b->stream_key);
        $this->assertSame([
            'server' => 'rtmp://live.example.test:19350/live',
            'key' => $a->stream_key,
            'url' => 'rtmp://live.example.test:19350/live/'.$a->stream_key,
        ], $this->svc->ingest($a));

        $this->setting('live_ingest_port', '');
        $this->assertSame(1935, $this->svc->port(), 'empty port setting falls back to 1935');
    }

    public function test_settings_are_seeded(): void
    {
        foreach (['live_ingest_host', 'live_ingest_port', 'live_wait_minutes', 'live_max_hours', 'live_queue'] as $k) {
            $this->assertTrue(DB::table('ahg_settings')->where('setting_key', $k)->exists(), "$k seeded");
        }
    }

    public function test_live_folder_inside_the_uploads_path_is_refused(): void
    {
        config(['heratio.live_path' => $this->root.'/live']);
        $this->expectException(\RuntimeException::class);
        $this->svc->root();
    }

    public function test_one_live_worker_per_port(): void
    {
        $io = $this->record();
        $a = $this->event($io, 'A');
        $b = $this->event($io, 'B');
        DB::table(LSS::TABLE)->where('id', $a->id)->update(['ingest_port' => 19351, 'worker_started_at' => now(), 'heartbeat_at' => now()]);

        $this->assertSame((int) $a->id, (int) $this->svc->activeOnPort(19351, (int) $b->id)->id);
        try {
            $this->svc->run((int) $b->id, 19351);
            $this->fail('a second event on a held port must be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('in use by live event #'.$a->id, $e->getMessage());
        }
        $this->setting('live_ingest_port', '19351');
        $this->assertStringContainsString('19351', (string) $this->svc->requestStart($this->svc->find((int) $b->id)));

        // A dead worker (stale heartbeat) no longer holds the port.
        DB::table(LSS::TABLE)->where('id', $a->id)->update(['heartbeat_at' => now()->subMinutes(5), 'status' => LSS::LIVE]);
        $this->assertNull($this->svc->activeOnPort(19351, (int) $b->id));
        $this->svc->reap();
        $this->assertSame(LSS::FAILED, $this->svc->find((int) $a->id)->status);
    }

    public function test_playlist_route_follows_the_record_media_access(): void
    {
        $draft = $this->event($this->record(159));
        $open = $this->event($this->record(160));
        foreach ([$draft, $open] as $e) {
            @mkdir($this->svc->hlsDir($e), 0777, true);
            file_put_contents($this->svc->hlsDir($e).'/index.m3u8', "#EXTM3U\n#EXT-X-TARGETDURATION:6\n#EXTINF:6.0,\nseg00000.ts\n");
            file_put_contents($this->svc->hlsDir($e).'/seg00000.ts', str_repeat("\x47", 188));
        }
        $url = fn ($e, $f = 'index.m3u8') => route('media-streaming.live.hls', ['eventId' => $e->id, 'file' => $f]);

        // Anonymous: the draft is refused, playlist and segment alike; the published record plays.
        $this->get($url($draft))->assertUnauthorized();
        $this->get($url($draft, 'seg00000.ts'))->assertUnauthorized();
        $this->get(route('media-streaming.live.status', $draft->id))->assertUnauthorized();
        $this->fresh();
        $this->get($url($open))->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');
        $this->fresh();
        $this->get($url($open, 'seg00000.ts'))->assertOk();
        $this->fresh();
        $this->get($url($open, 'seg00001.ts'))->assertNotFound();
        $this->get('/media-streaming/live/'.$open->id.'/hls/..%2F..%2Frecording.mkv')->assertNotFound();

        // Signed in without staff rights: still refused on the draft. Staff watch it.
        $this->actingAs($this->user());
        $this->fresh();
        $this->get($url($draft))->assertForbidden();
        $this->actingAs($this->user(101));
        $this->fresh();
        $this->get($url($draft))->assertOk();
    }

    public function test_start_queues_the_worker_on_the_live_connection_for_staff_only(): void
    {
        Queue::fake();
        $this->setting('live_ingest_port', (string) $this->freePort());
        $io = $this->record();
        $e = $this->event($io);

        $this->actingAs($this->user());
        $this->fresh();
        $this->post(route('media-streaming.live.start', $e->id))->assertForbidden();
        $this->get(route('media-streaming.live.show', $e->id))->assertForbidden();
        Queue::assertNothingPushed();

        $this->actingAs($this->user(101));
        $this->fresh();
        $this->get(route('media-streaming.live.show', $e->id))->assertOk()->assertSee($e->stream_key);
        $this->post(route('media-streaming.live.start', $e->id))->assertRedirect();
        Queue::assertPushed(RunLiveIngestJob::class, fn ($j) => $j->eventId === (int) $e->id && $j->connection === 'live' && $j->queue === 'live');
        $this->assertNotNull($this->svc->find((int) $e->id)->start_requested_at);

        $this->get(route('media-streaming.live.create', $io))->assertOk()->assertSee('Schedule a live event');
        $this->post(route('media-streaming.live.store', $io), ['title' => 'Second', 'scheduled_at' => '2026-12-01 10:00'])->assertRedirect();
        $this->assertSame(2, DB::table(LSS::TABLE)->where('information_object_id', $io)->count());
        $this->get(route('media-streaming.live.index'))->assertOk()->assertSee('Second');
    }

    public function test_record_page_says_starts_at_before_and_ended_after(): void
    {
        $io = $this->record();
        $e = $this->event($io, 'Budget speech');
        $html = view('ahg-media-streaming::live._record-player', ['ioId' => $io])->render();
        $this->assertStringContainsString('Budget speech', $html);
        $this->assertStringContainsString('Starts at', $html);

        // Live with a playlist: the site's media player, pointed at the access-checked playlist route.
        DB::table(LSS::TABLE)->where('id', $e->id)->update(['status' => LSS::LIVE, 'started_at' => now()]);
        @mkdir($this->svc->hlsDir($e), 0777, true);
        file_put_contents($this->svc->hlsDir($e).'/index.m3u8', "#EXTM3U\n");
        $html = view('ahg-media-streaming::live._record-player', ['ioId' => $io])->render();
        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('application/x-mpegURL', $html);
        $this->assertStringContainsString('/media-streaming/live/'.$e->id.'/hls/index.m3u8', $html);

        DB::table(LSS::TABLE)->where('id', $e->id)->update(['status' => LSS::ENDED, 'ended_at' => now()]);
        $html = view('ahg-media-streaming::live._record-player', ['ioId' => $io])->render();
        $this->assertStringContainsString('Ended', $html);
        $this->assertStringNotContainsString('<video', $html);

        // A draft record's event is not shown to the public at all.
        $this->event($draft = $this->record(159), 'Hidden');
        $this->fresh();
        $this->assertStringNotContainsString('Hidden', view('ahg-media-streaming::live._record-player', ['ioId' => $draft])->render());
    }

    // ── live: ffmpeg RTMP listener ───────────────────────────────────────

    public function test_live_ingest_end_to_end(): void
    {
        if (trim((string) shell_exec('command -v ffmpeg')) === '' || trim((string) shell_exec('command -v ffprobe')) === '') {
            $this->markTestSkipped('ffmpeg/ffprobe not installed');
        }
        $this->setting('live_wait_minutes', '1');
        $io = $this->record();
        $e = $this->event($io, 'Test broadcast');
        $port = $this->freePort();

        // The encoder: a wrong key first (must be dropped), then the event's key
        // for 5 seconds. Each push retries until the listener is up.
        // Each push retries only while the listener is not up yet (connection refused).
        $log = $this->live.'-push.log';
        $push = fn (string $key, int $secs) => 'for i in $(seq 1 80); do ffmpeg -v error -re -f lavfi -i testsrc=size=320x240:rate=10 '
            .'-f lavfi -i sine=frequency=440 -t '.$secs.' -c:v libx264 -preset ultrafast -pix_fmt yuv420p -g 20 -c:a aac '
            .'-f flv '.escapeshellarg("rtmp://127.0.0.1:$port/live/$key").' 2> '.escapeshellarg($log.'.err')
            .'; grep -q "Connection refused" '.escapeshellarg($log.'.err').' || break; sleep 0.25; done';
        exec('( '.$push('not-the-key', 2).'; sleep 0.5; '.$push($e->stream_key, 5).' ) > '.escapeshellarg($log).' 2>&1 &');

        $lines = [];
        $seen = [];
        $status = $this->svc->run((int) $e->id, $port, function (string $m) use (&$lines, &$seen, $e) {
            $lines[] = $m;
            $row = $this->svc->find((int) $e->id);
            if (str_contains($m, 'is live')) {
                $seen['live'] = $row->status;
                $seen['liveAt'] = microtime(true);
            }
            if (str_contains($m, 'ended')) {
                $seen['ended'] = $row->status;
                $seen['endedAt'] = microtime(true);
                $pl = $this->svc->hlsPath($row, 'index.m3u8');
                $seen['playlist'] = $pl ? (string) file_get_contents($pl) : '';
                $seen['recording'] = is_file($this->svc->recordingFile($row)) ? filesize($this->svc->recordingFile($row)) : 0;
            }
        });
        $msg = implode("\n", $lines)."\npusher: ".@file_get_contents($log);

        $this->assertSame(LSS::ENDED, $status, $msg);
        $this->assertTrue((bool) array_filter($lines, fn ($l) => str_contains($l, 'another stream key')), 'the wrong key was dropped: '.$msg);
        $this->assertSame(LSS::LIVE, $seen['live'] ?? null, 'marked live on the first frames');
        $this->assertSame(LSS::ENDED, $seen['ended'] ?? null, 'marked ended when the source stopped');
        $this->assertGreaterThan(2.0, $seen['endedAt'] - $seen['liveAt'], 'live while the broadcast ran, not only at its end: '.$msg);
        $this->assertStringContainsString('#EXTM3U', $seen['playlist']);
        $this->assertMatchesRegularExpression('/seg\d{5}\.ts/', $seen['playlist']);
        $this->assertStringContainsString('#EXT-X-ENDLIST', $seen['playlist']);
        $this->assertGreaterThan(0, $seen['recording']);

        $row = $this->svc->find((int) $e->id);
        $this->assertNotNull($row->started_at);
        $this->assertNotNull($row->ended_at);
        $this->assertNull($row->worker_started_at, 'the port is released');
        $master = DB::table('digital_object')->where('id', $row->recording_digital_object_id)->first();
        $this->assertNotNull($master, 'the recording is a digital object');
        $this->assertSame($io, (int) $master->object_id);
        $this->assertSame(DOS::USAGE_MASTER, (int) $master->usage_id);
        $this->assertSame(DOS::MEDIA_VIDEO, (int) $master->media_type_id);
        $this->assertSame('video/mp4', $master->mime_type);
        $file = $this->root.'/r/'.$io.'/'.$master->name;
        $this->assertFileExists($file);
        $dur = (float) shell_exec('ffprobe -v error -show_entries format=duration -of csv=p=0 '.escapeshellarg($file));
        $this->assertGreaterThan(3.0, $dur, 'the whole 5 s broadcast is recorded');
        $this->assertDirectoryDoesNotExist($this->svc->dir($row), 'the live folder is removed after archiving');
        $this->assertGreaterThan(0, DB::table('digital_object')->where('parent_id', $master->id)->count(), 'derivatives made the usual way');
    }
}
