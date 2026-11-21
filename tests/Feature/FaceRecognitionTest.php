<?php

/**
 * FaceRecognitionTest - heratio#1764 (GCIS 5.4.3 face recognition search).
 *
 * The AI gateway face route is faked with Http::fake: no live calls. Images
 * and a short video are made in a temp folder standing in for the uploads
 * path. Covers: detection stores faces with unit-length embeddings; a 404
 * from the gateway stores nothing and does not throw; video frames sampled
 * at the interval's times; naming, then matching suggestions over the
 * threshold (and a rejected one gone); confirming links the record and adds
 * the name access point; the person page lists the records and video times,
 * with the public held to DisclosureGate; staff-only screens; search by
 * photo; the purge command; audit rows.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Constants\TermId;
use AhgCore\Models\AclGroup;
use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\DisclosureGate;
use AhgCore\Services\FaceDetectionService;
use AhgCore\Services\FaceRecognitionService;
use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaceRecognitionTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['digital_object_faces', 'face_person', 'face_match_rejection', 'ahg_settings', 'ahg_ner_settings', 'relation'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present - run the 2026_11_20_210000_face_recognition migration.");
            }
        }
        $this->root = sys_get_temp_dir().'/ahg-faces-'.uniqid();
        mkdir($this->root, 0777, true);
        config(['heratio.uploads_path' => $this->root]);
        $this->setting('face_enabled', '1');
        $this->setting('face_backend', 'gateway');
        $this->setting('face_search_public', '0');
        DB::table('ahg_settings')->whereIn('setting_key', ['face_video_interval', 'face_match_threshold', 'face_gateway_path'])->delete();
        DB::table('ahg_ner_settings')->updateOrInsert(['setting_key' => 'api_key'], ['setting_value' => 'test-gateway-key']);
        DB::table('ahg_ner_settings')->where('setting_key', 'ai_services_api_url')->delete();
        Cache::forget('face_gateway_404_logged');
        $this->fresh();
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root));
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    // ── detection ──────────────────────────────────────────────────────

    public function test_detection_stores_faces_with_unit_embeddings(): void
    {
        Http::fake(['ai.theahg.co.za/ai/v1/vision/faces' => Http::response(['model' => 'm1', 'faces' => [
            ['box' => [10, 20, 30, 40], 'embedding' => [3, 4, 0], 'score' => 0.99],
            ['box' => [50, 5, 20, 20], 'embedding' => [0, 0, 2], 'score' => 0.8],
        ]])]);
        [$do] = $this->imageMaster();

        $this->assertSame(2, app(FaceDetectionService::class)->detectAndStore($do));

        $rows = DB::table('digital_object_faces')->where('digital_object_id', $do)->orderBy('face_index')->get();
        $this->assertCount(2, $rows);
        $this->assertEquals(['x' => 10, 'y' => 20, 'w' => 30, 'h' => 40], json_decode($rows[0]->bounding_box, true));
        $this->assertSame('gateway', $rows[0]->backend);
        $this->assertNull($rows[0]->frame_time_seconds);
        $this->assertSame(3, (int) $rows[0]->embedding_dim);
        $v = FaceRecognitionService::unpack($rows[0]->embedding);
        $this->assertEqualsWithDelta(0.6, $v[0], 1e-6);
        $this->assertEqualsWithDelta(0.8, $v[1], 1e-6);

        Http::assertSent(fn ($r) => $r->url() === 'https://ai.theahg.co.za/ai/v1/vision/faces'
            && $r->hasHeader('Authorization', 'Bearer test-gateway-key')
            && $r['return_embedding'] === true && base64_decode($r['image']) !== '');
    }

    public function test_gateway_404_stores_nothing_and_does_not_throw(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'Not Found'], 404)]);
        [$do] = $this->imageMaster();

        $this->assertSame(0, app(FaceDetectionService::class)->detectAndStore($do));
        $this->assertSame(0, DB::table('digital_object_faces')->where('digital_object_id', $do)->count());
        $this->assertTrue(Cache::has('face_gateway_404_logged'), 'the 404 is logged once and remembered');
    }

    public function test_face_processing_off_makes_no_call(): void
    {
        Http::fake();
        $this->setting('face_enabled', '0');
        [$do] = $this->imageMaster();

        $this->assertSame(0, app(FaceDetectionService::class)->detectAndStore($do));
        Http::assertNothingSent();
    }

    public function test_video_frames_are_sampled_at_the_interval(): void
    {
        if (trim((string) shell_exec('command -v ffmpeg')) === '' || trim((string) shell_exec('command -v ffprobe')) === '') {
            $this->markTestSkipped('ffmpeg/ffprobe not installed');
        }
        $this->setting('face_video_interval', '10');
        Http::fake(['*' => Http::response(['faces' => [['box' => [1, 2, 3, 4], 'embedding' => [1, 0], 'score' => 0.9]]])]);
        $io = $this->record();
        $dir = $this->root.'/r/'.$io;
        mkdir($dir, 0777, true);
        shell_exec('ffmpeg -v error -y -f lavfi -i testsrc=size=160x120:rate=5:duration=25 -c:v libx264 -preset ultrafast '.escapeshellarg($dir.'/clip.mp4').' 2>&1');
        $do = $this->digitalObject($io, 'clip.mp4', 'video/mp4');

        $this->assertSame(3, app(FaceDetectionService::class)->detectAndStore($do));

        $times = DB::table('digital_object_faces')->where('digital_object_id', $do)->orderBy('frame_time_seconds')
            ->pluck('frame_time_seconds')->map(fn ($t) => (float) $t)->all();
        $this->assertSame([0.0, 10.0, 20.0], $times);
        Http::assertSentCount(3);
    }

    // ── naming, matching, confirming ──────────────────────────────────

    public function test_naming_then_suggestions_over_the_threshold(): void
    {
        $io = $this->record();
        $do = $this->digitalObject($io, 'a.jpg', 'image/jpeg');
        $a = $this->face($do, [1, 0, 0]);
        $b = $this->face($do, [0.9, 0.436, 0]);   // cos 0.9 to a
        $c = $this->face($do, [0.1, 0.995, 0]);   // cos 0.1 to a
        $svc = app(FaceRecognitionService::class);
        $person = $svc->createPerson('Test Person');

        $this->actingAs($this->user(AclGroup::EDITOR_ID));
        $this->assertTrue($svc->nameFace($a, $person));
        $this->assertSame($person, (int) DB::table('digital_object_faces')->where('id', $a)->value('person_id'));

        $sugg = $svc->suggestions($person);
        $this->assertSame([$b], array_column($sugg, 'face_id'), 'only the face over 0.6 is suggested');
        $this->assertEqualsWithDelta(0.9, $sugg[0]['similarity'], 0.01);

        $this->setting('face_match_threshold', '0.05');
        $this->assertSame([$b, $c], array_column($svc->suggestions($person), 'face_id'));

        $svc->reject($c, $person);
        $this->assertSame([$b], array_column($svc->suggestions($person), 'face_id'), 'a rejected face is not suggested again');

        $this->get(route('faces.object', $do))->assertOk()->assertSee('Test Person')->assertSee(__('Unnamed'));
        $this->assertTrue(DB::table('ahg_audit_log')->where('action', 'face_named')->where('entity_id', $a)->exists());
        $this->assertTrue(DB::table('ahg_audit_log')->where('action', 'face_rejected')->where('entity_id', $c)->exists());
    }

    public function test_confirming_links_the_record_and_adds_the_name_access_point(): void
    {
        $actor = $this->actor();
        $io = $this->record();
        $do = $this->digitalObject($io, 'b.jpg', 'image/jpeg');
        $a = $this->face($do, [1, 0]);
        $svc = app(FaceRecognitionService::class);
        $person = $svc->createPerson('With Actor', $actor);

        $this->actingAs($editor = $this->user(AclGroup::EDITOR_ID))
            ->post(route('faces.face.confirm', $a), ['person_id' => $person, 'similarity' => 0.91])->assertRedirect();

        $face = DB::table('digital_object_faces')->where('id', $a)->first();
        $this->assertSame($person, (int) $face->person_id);
        $this->assertSame('verified', $face->identification_source);
        $this->assertSame($actor, (int) $face->matched_actor_id);
        $rel = DB::table('relation')->where('subject_id', $io)->where('object_id', $actor)->where('type_id', TermId::RELATION_NAME_ACCESS_POINT)->first();
        $this->assertNotNull($rel, 'name access point added');
        $this->assertNotEmpty($rel->source_culture);
        $this->assertSame('QubitRelation', DB::table('object')->where('id', $rel->id)->value('class_name'));

        // Confirming a second face on the same record does not add the access point twice.
        $a2 = $this->face($do, [1, 0]);
        $svc->confirm($a2, $person);
        $this->assertSame(1, DB::table('relation')->where('subject_id', $io)->where('object_id', $actor)->where('type_id', TermId::RELATION_NAME_ACCESS_POINT)->count());
        $this->assertSame((int) $editor->id, (int) DB::table('ahg_audit_log')->where('action', 'face_confirmed')->where('entity_id', $a)->value('user_id'));
    }

    // ── search ─────────────────────────────────────────────────────────

    public function test_person_search_lists_records_and_video_times_and_respects_disclosure(): void
    {
        $svc = app(FaceRecognitionService::class);
        $person = $svc->createPerson('Searched Person');
        $published = $this->record('Published film');
        $draft = $this->record('Draft photo', false);
        $doV = $this->digitalObject($published, 'v.mp4', 'video/mp4');
        $doI = $this->digitalObject($draft, 'i.jpg', 'image/jpeg');
        foreach ([[$doV, 30.0], [$doV, 90.0], [$doI, null]] as [$do, $t]) {
            $svc->nameFace($this->face($do, [1, 0], $t), $person);
        }
        $this->face($doI, [0.95, 0.31]); // a possible match, unconfirmed

        $staff = collect($svc->itemsForPerson($person, false))->keyBy('io_id');
        $this->assertEqualsCanonicalizing([$published, $draft], $staff->keys()->all());
        $this->assertSame([30.0, 90.0], $staff[$published]->times);

        $public = $svc->itemsForPerson($person, true);
        $this->assertSame([$published], array_column($public, 'io_id'), 'the public sees only disclosable records');

        $this->actingAs($this->user(AclGroup::EDITOR_ID))->get(route('faces.person', $person))
            ->assertOk()->assertSee('Published film')->assertSee('Draft photo')->assertSee('00:01:30')->assertSee(__('Possible matches'));
    }

    public function test_face_search_is_staff_only_by_default(): void
    {
        $person = app(FaceRecognitionService::class)->createPerson('Private Person');
        $io = $this->record('Public record');
        app(FaceRecognitionService::class)->nameFace($this->face($this->digitalObject($io, 'p.jpg', 'image/jpeg'), [1, 0]), $person);

        $this->get(route('faces.person', $person))->assertNotFound();
        $this->get(route('faces.index'))->assertRedirect(route('login'));
        $this->actingAs($this->user())->get(route('faces.index'))->assertForbidden();
        $this->actingAs($this->user())->post(route('faces.people.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->user(AclGroup::EDITOR_ID))->get(route('faces.index'))->assertOk()->assertSee('Private Person');

        // Opened by the setting: the public sees confirmed appearances only, no possible matches.
        auth()->logout();
        $this->setting('face_search_public', '1');
        $this->get(route('faces.person', $person))->assertOk()->assertSee('Public record')->assertDontSee(__('Possible matches'));

        // Face processing off: everything is closed.
        $this->setting('face_enabled', '0');
        $this->get(route('faces.person', $person))->assertNotFound();
    }

    public function test_search_by_photo_ranks_stored_faces(): void
    {
        Http::fake(['*' => Http::response(['faces' => [['box' => [0, 0, 10, 10], 'embedding' => [1, 0], 'score' => 0.9]]])]);
        $io = $this->record('Photo match record');
        $do = $this->digitalObject($io, 'q.jpg', 'image/jpeg');
        $near = $this->face($do, [0.98, 0.2]);
        $this->face($do, [0, 1]);

        $r = app(FaceRecognitionService::class)->searchByPhoto($this->png($this->root.'/probe.png'));
        $this->assertSame(1, $r['detected']);
        $this->assertSame([$near], array_column($r['matches'], 'face_id'));

        $this->actingAs($this->user(AclGroup::EDITOR_ID))
            ->post(route('faces.search-photo'), ['photo' => UploadedFile::fake()->image('who.jpg', 40, 40)])
            ->assertOk()->assertSee('Photo match record');
    }

    // ── privacy ───────────────────────────────────────────────────────

    public function test_purge_deletes_one_persons_face_data_and_is_audited(): void
    {
        $svc = app(FaceRecognitionService::class);
        $gone = $svc->createPerson('Data Subject');
        $kept = $svc->createPerson('Someone Else');
        $do = $this->digitalObject($this->record(), 'z.jpg', 'image/jpeg');
        $f1 = $this->face($do, [1, 0]);
        $f2 = $this->face($do, [0, 1]);
        $other = $this->face($do, [0.7, 0.7]);
        $svc->nameFace($f1, $gone);
        $svc->nameFace($f2, $gone);
        $svc->nameFace($other, $kept);
        $svc->reject($other, $gone);

        $this->artisan('ahg:faces-purge', ['person' => $gone, '--force' => true])->assertSuccessful();

        $this->assertFalse(DB::table('face_person')->where('id', $gone)->exists());
        $this->assertSame(0, DB::table('digital_object_faces')->whereIn('id', [$f1, $f2])->count());
        $this->assertSame(0, DB::table('face_match_rejection')->where('person_id', $gone)->count());
        $this->assertSame($kept, (int) DB::table('digital_object_faces')->where('id', $other)->value('person_id'));
        $audit = DB::table('ahg_audit_log')->where('action', 'face_person_purged')->where('entity_id', $gone)->first();
        $this->assertNotNull($audit);
        $this->assertSame(2, json_decode($audit->metadata, true)['faces']);
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function fresh(): void
    {
        AhgSettingsService::clearCache();
        AclService::forgetUser();
        app()->forgetInstance(DisclosureGate::class);
    }

    private function setting(string $key, string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value, 'setting_group' => 'faces']);
        $this->fresh();
    }

    private function record(string $title = 'Face test record', bool $published = true): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'face-test-'.$id]);
        StatusRow::set($id, 158, $published ? 160 : 159);

        return $id;
    }

    private function digitalObject(int $io, string $name, string $mime): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $io, 'usage_id' => DigitalObjectService::USAGE_MASTER, 'name' => $name,
            'path' => '/uploads/r/'.$io.'/', 'mime_type' => $mime]);

        return $id;
    }

    /** @return array{0: int, 1: int} [digital object id, record id] for a real PNG master on disk */
    private function imageMaster(): array
    {
        $io = $this->record();
        @mkdir($this->root.'/r/'.$io, 0777, true);
        $this->png($this->root.'/r/'.$io.'/photo.png');

        return [$this->digitalObject($io, 'photo.png', 'image/png'), $io];
    }

    private function png(string $path): string
    {
        $im = imagecreatetruecolor(40, 40);
        imagepng($im, $path);

        return $path;
    }

    private function face(int $do, array $vector, ?float $time = null): int
    {
        return (int) DB::table('digital_object_faces')->insertGetId([
            'digital_object_id' => $do, 'frame_time_seconds' => $time,
            'bounding_box' => json_encode(['x' => 0, 'y' => 0, 'w' => 10, 'h' => 10]),
            'embedding' => FaceRecognitionService::pack($vector), 'embedding_dim' => count($vector),
            'confidence' => 0.9, 'backend' => 'gateway', 'created_at' => now(),
        ]);
    }

    private function actor(): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitActor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $id, 'culture' => 'en', 'authorized_form_of_name' => 'Face Test Actor']);

        return $id;
    }

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'face-'.$id, 'email' => uniqid('face-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        return User::findOrFail($id);
    }
}
