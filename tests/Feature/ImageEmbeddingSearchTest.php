<?php

/**
 * ImageEmbeddingSearchTest - heratio#1272. Image embedding through the AI
 * gateway (ledger, pending sweep, failure handling) and image-to-image /
 * text-to-image search gated by DisclosureGate and FileScreeningGate.
 * The gateway and Qdrant are faked; no live call is made.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Support\StatusRow;
use AhgDiscovery\Services\ImageEmbeddingService;
use AhgDiscovery\Services\Search\ImageSearchStrategy;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImageEmbeddingSearchTest extends TestCase
{
    use DatabaseTransactions;

    private const QDRANT = 'http://qdrant.test:6333';

    private string $root;

    private array $pendingBaseline = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_image_embedding')) {
            $this->markTestSkipped('ahg_image_embedding missing - run the 2026_11_21_120000 migration');
        }
        \AhgCore\Services\AclService::forgetUser();
        $this->root = sys_get_temp_dir().'/img-embed-'.uniqid();
        mkdir($this->root.'/r', 0777, true);
        config(['heratio.uploads_path' => $this->root, 'heratio.storage_path' => $this->root]);
        foreach ([
            'semantic_qdrant_url' => self::QDRANT,
            'semantic_qdrant_min_score' => '0',
            'ahg_discovery_image_enabled' => '1',
            'ahg_discovery_image_min_score' => '0.30',
            'ahg_discovery_image_collection' => 'test_images',
            'ahg_discovery_image_embed_url' => 'https://ai.theahg.co.za/ai/v1',
            'ahg_discovery_image_embed_model' => 'nomic-embed-vision-v1.5',
            'ahg_discovery_image_text_model' => 'nomic-embed-text',
            'ahg_discovery_image_text_prefix' => 'search_query: ',
            'privacy_screening_repositories' => '',
            'privacy_screening_collections' => '',
        ] as $k => $v) {
            DB::table('ahg_settings')->updateOrInsert(['setting_key' => $k], ['setting_value' => $v, 'setting_group' => 'discovery']);
        }
        // Only what this test creates is pending.
        $this->pendingBaseline = app(ImageEmbeddingService::class)->pending()->pluck('d.id')->all();
        foreach ($this->pendingBaseline as $id) {
            DB::table('ahg_image_embedding')->updateOrInsert(['digital_object_id' => $id],
                ['information_object_id' => 0, 'model' => 'nomic-embed-vision-v1.5', 'status' => 'skipped', 'attempts' => 0]);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/r/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->root.'/r/*') ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($this->root.'/r');
        @rmdir($this->root);
        parent::tearDown();
    }

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A record with a master image on disk. Returns [ioId, doId]. */
    private function record(bool $published = true, ?int $repo = null): array
    {
        $io = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $io, 'source_culture' => 'en', 'repository_id' => $repo]);
        if ($published) {
            StatusRow::set($io, 158, 160);
        }
        $do = $this->obj('QubitDigitalObject');
        mkdir($this->root.'/r/'.$io, 0777, true);
        file_put_contents($this->root.'/r/'.$io.'/master_pic.jpg', 'JPEGBYTES-'.$io);
        DB::table('digital_object')->insert(['id' => $do, 'object_id' => $io, 'usage_id' => 140, 'name' => 'master_pic.jpg',
            'path' => '/uploads/r/'.$io.'/', 'mime_type' => 'image/jpeg', 'checksum' => md5('JPEGBYTES-'.$io)]);

        return [$io, $do];
    }

    private function staff(): User
    {
        $id = $this->obj('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'img-'.$id, 'email' => uniqid('img-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function fakeGateway(int $imageStatus = 200, int $dims = 4, int $existingDims = 0): void
    {
        $vec = array_fill(0, $dims, 0.5);
        Http::swap(new \Illuminate\Http\Client\Factory); // a fresh fake each call: stubs would otherwise stack, first match winning
        Http::fake([
            'ai.theahg.co.za/ai/v1/embed/image' => $imageStatus === 200
                ? Http::response(['embedding' => $vec], 200)
                : Http::response(['detail' => 'no upstream'], $imageStatus),
            'ai.theahg.co.za/ai/v1/ollama/api/embeddings' => Http::response(['embedding' => $vec], 200),
            self::QDRANT.'/collections/test_images' => $existingDims
                ? Http::response(['result' => ['config' => ['params' => ['vectors' => ['size' => $existingDims]]]]], 200)
                : Http::sequence()->push(['status' => ['error' => 'Not found']], 404)->whenEmpty(Http::response(['result' => true], 200)),
            self::QDRANT.'/collections/test_images/points*' => Http::response(['status' => 'ok', 'result' => ['status' => 'completed']], 200),
            '*' => Http::response('stray', 599),
        ]);
    }

    /** Qdrant search replaced by fixed hits; point id = digital object id, payload names the record. */
    private function fakeSearch(array $hits): void
    {
        $this->app->instance(VectorSearchService::class, new class($hits) extends VectorSearchService
        {
            public function __construct(private array $hits) {}

            public function qdrantSearch(string $collection, array $vector, int $limit, ?array $filter = null): ?array
            {
                return $this->hits;
            }

            public function fetchPointVector(string $collection, int $pointId): ?array
            {
                return [0.1, 0.2, 0.3, 0.4];
            }
        });
        $this->app->forgetInstance(ImageSearchStrategy::class);
    }

    private function hit(int $doId, int $ioId, float $score): array
    {
        return ['id' => $doId, 'score' => $score, 'payload' => ['information_object_id' => $ioId, 'title' => 'T'.$ioId, 'slug' => null]];
    }

    public function test_an_image_is_embedded_through_the_gateway_and_recorded(): void
    {
        $this->fakeGateway();
        [$io, $do] = $this->record();

        $this->assertSame('embedded', app(ImageEmbeddingService::class)->indexDigitalObject($do));

        $row = DB::table('ahg_image_embedding')->where('digital_object_id', $do)->first();
        $this->assertSame($io, (int) $row->information_object_id);
        $this->assertSame(4, (int) $row->dimensions);
        Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://ai.theahg.co.za/ai/v1/embed/image'
            && $r['model'] === 'nomic-embed-vision-v1.5' && base64_decode($r['image']) === 'JPEGBYTES-'.$io);
        Http::assertSent(fn (ClientRequest $r) => $r->method() === 'PUT' && $r->url() === self::QDRANT.'/collections/test_images'
            && $r['vectors']['size'] === 4 && $r['vectors']['distance'] === 'Cosine');
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/points?wait=true')
            && $r['points'][0]['id'] === $do && $r['points'][0]['payload']['information_object_id'] === $io);
    }

    public function test_a_node_address_in_the_override_is_ignored_for_the_gateway(): void
    {
        DB::table('ahg_settings')->where('setting_key', 'ahg_discovery_image_embed_url')->update(['setting_value' => 'http://192.168.0.115:5004/ai/v1']);
        $this->fakeGateway();
        [, $do] = $this->record();

        app(ImageEmbeddingService::class)->indexDigitalObject($do);

        Http::assertNotSent(fn (ClientRequest $r) => str_contains($r->url(), '192.168.'));
        Http::assertSent(fn (ClientRequest $r) => str_starts_with($r->url(), 'https://ai.theahg.co.za/ai/v1/embed/image'));
    }

    public function test_gateway_down_records_nothing_and_a_bad_image_is_retried_then_dropped(): void
    {
        [, $do] = $this->record();
        $svc = app(ImageEmbeddingService::class);

        $this->fakeGateway(503);
        $this->assertSame(ImageEmbeddingService::UNAVAILABLE, $svc->indexDigitalObject($do));
        $this->assertFalse(DB::table('ahg_image_embedding')->where('digital_object_id', $do)->exists());

        $this->fakeGateway(400);
        foreach ([1, 2, 3] as $n) {
            $this->assertContains($do, $svc->pending(3)->pluck('d.id')->all());
            $this->assertSame('failed', $svc->indexDigitalObject($do));
            $this->assertSame($n, (int) DB::table('ahg_image_embedding')->where('digital_object_id', $do)->value('attempts'));
        }
        $this->assertNotContains($do, $svc->pending(3)->pluck('d.id')->all());
    }

    public function test_a_collection_of_another_size_is_never_written_to(): void
    {
        $this->fakeGateway(200, 4, 512);
        [, $do] = $this->record();
        $svc = app(ImageEmbeddingService::class);

        $this->assertSame(ImageEmbeddingService::UNAVAILABLE, $svc->indexDigitalObject($do));
        $this->assertStringContainsString('--recreate', $svc->lastError);
        Http::assertNotSent(fn (ClientRequest $r) => str_contains($r->url(), '/points'));
    }

    public function test_the_sweep_embeds_new_images_once_and_stops_quietly_when_the_gateway_is_down(): void
    {
        [, $a] = $this->record();
        [, $b] = $this->record();

        $this->fakeGateway(503);
        $this->artisan('ahg:image-embed')->expectsOutputToContain('not available')->assertSuccessful();
        $this->assertSame(0, DB::table('ahg_image_embedding')->whereIn('digital_object_id', [$a, $b])->count());

        $this->fakeGateway();
        $this->artisan('ahg:image-embed')->assertSuccessful();
        $this->assertSame(2, DB::table('ahg_image_embedding')->whereIn('digital_object_id', [$a, $b])->where('status', 'embedded')->count());

        $this->fakeGateway();
        $this->artisan('ahg:image-embed')->expectsOutputToContain('Embedded 0')->assertSuccessful();
        Http::assertSentCount(1); // the probe only
    }

    public function test_text_search_uses_the_paired_text_embedder_and_visitors_get_only_disclosable_records(): void
    {
        $this->fakeGateway();
        $repo = $this->obj('QubitRepository');
        DB::table('actor')->insert(['id' => $repo, 'source_culture' => 'en']);
        DB::table('repository')->insert(['id' => $repo, 'source_culture' => 'en']);
        DB::table('ahg_settings')->where('setting_key', 'privacy_screening_repositories')->update(['setting_value' => (string) $repo]);

        [$open, $openDo] = $this->record();
        [$draft, $draftDo] = $this->record(false);
        [$screened, $screenedDo] = $this->record(true, $repo);
        [$redacted, $redactedDo] = $this->record();
        DB::table('privacy_visual_redaction')->insert(['object_id' => $redacted, 'coordinates' => '{}']);
        $this->fakeSearch([
            $this->hit($openDo, $open, 0.9), $this->hit($draftDo, $draft, 0.8),
            $this->hit($screenedDo, $screened, 0.7), $this->hit($redactedDo, $redacted, 0.6),
            $this->hit(999999999, $open, 0.95), // a second image of the same record folds into one row
        ]);

        $res = $this->getJson('/discovery/image/search?q=steam+train')->assertOk()->json();
        $this->assertSame('text', $res['mode']);
        $this->assertSame([$open], array_column($res['results'], 'object_id'));
        $this->assertSame(0.95, $res['results'][0]['score']);
        Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://ai.theahg.co.za/ai/v1/ollama/api/embeddings'
            && $r['model'] === 'nomic-embed-text' && $r['prompt'] === 'search_query: steam train');

        $staff = $this->actingAs($this->staff())->getJson('/discovery/image/search?q=steam+train')->assertOk()->json();
        $this->assertEqualsCanonicalizing([$open, $draft, $screened, $redacted], array_column($staff['results'], 'object_id'));
    }

    public function test_image_upload_search_and_similar_records_honour_the_gates(): void
    {
        $this->fakeGateway();
        [$open, $openDo] = $this->record();
        [$draft, $draftDo] = $this->record(false);
        $this->fakeSearch([$this->hit($openDo, $open, 0.9), $this->hit($draftDo, $draft, 0.8)]);

        $upload = UploadedFile::fake()->image('query.jpg', 20, 20);
        $res = $this->post('/discovery/image/search', ['image' => $upload], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertSame('image', $res['mode']);
        $this->assertSame([$open], array_column($res['results'], 'object_id'));
        Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), '/ai/v1/embed/image') && $r['image'] !== '');

        // A visitor cannot seed "more like this" from an unpublished record's image.
        $this->getJson('/discovery/image/search?similar='.$draft)->assertOk()->assertJson(['total' => 0]);
        $this->getJson('/discovery/image/search?similar='.$open)->assertOk()->assertJson(['total' => 0]); // only itself and a draft match
    }

    public function test_an_unavailable_embedder_is_reported_not_shown_as_no_results(): void
    {
        $this->fakeGateway(503);
        $this->fakeSearch([]);
        $upload = UploadedFile::fake()->image('query.png', 10, 10);

        $this->post('/discovery/image/search', ['image' => $upload], ['Accept' => 'application/json'])
            ->assertStatus(503)->assertJson(['success' => false]);
        $this->getJson('/discovery/image/search')->assertStatus(422);
        $this->get('/discovery/image')->assertOk()->assertSee('Image search');
    }
}
