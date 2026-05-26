<?php

/**
 * NerAnnotationBridgeIntegrationTest - issue #697 finishing pass.
 *
 * End-to-end check of the NER -> Web Annotation -> Mirador loop:
 *
 *   1. Seed iiif_ocr_text + iiif_ocr_block rows for a synthetic
 *      digital_object that is attached to a real information_object
 *      (we re-use the lowest-id IO present in the database so we do
 *      not have to invent FK-clean object/IO rows for the test - the
 *      bridge only reads slug() to build the manifest IRI and we
 *      tear down our annotation rows on completion).
 *   2. Bind a fake NerService into the container that returns a
 *      fixed entity list (no live AHG AI API call from the suite).
 *   3. Dispatch BuildNerAnnotationsForCanvas::dispatchSync() and
 *      assert ahg_iiif_annotation rows landed for the seeded canvas.
 *   4. Hit GET /api/annotations/search?targetId=<canvas_iri> - the
 *      same endpoint Mirador's annotations companion-window reads -
 *      and assert the seeded entity appears in the W3C container.
 *
 * Skipped automatically when any of the prerequisite tables are
 * unreachable (CI without MySQL, fresh worktree without core schema,
 * etc.). The test never modifies real information_object rows.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. AGPL v3 or later.
 */

namespace Tests\Feature\Iiif;

use AhgAiServices\Services\NerService;
use AhgIiifCollection\Jobs\BuildNerAnnotationsForCanvas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NerAnnotationBridgeIntegrationTest extends TestCase
{
    /**
     * Seeded digital_object.id used by the test. Chosen high so we
     * never collide with a real DAM record; we tear it down on
     * teardown along with every annotation pinned to its canvas.
     */
    private const TEST_DO_ID = 990001;

    /**
     * iiif_ocr_text.id placeholder - set in setUp once we know we can
     * actually seed (so tearDown does not blindly run on a skipped
     * test).
     */
    private ?int $ocrId = null;

    /**
     * The IO we attach the synthetic digital_object to - the lowest
     * id that has a slug row. Captured in seedFixture().
     */
    private ?int $ioId = null;

    /**
     * Canvas IRI the bridge will emit annotations against. Computed
     * from app.url + slug; recomputed in each test once we have a
     * slug.
     */
    private ?string $canvasIri = null;

    protected function tearDown(): void
    {
        try {
            if ($this->canvasIri && Schema::hasTable('ahg_iiif_annotation')) {
                DB::table('ahg_iiif_annotation')
                    ->where('target_iri', $this->canvasIri)
                    ->delete();
            }
            if (Schema::hasTable('iiif_ocr_block') && $this->ocrId !== null) {
                DB::table('iiif_ocr_block')->where('ocr_id', $this->ocrId)->delete();
            }
            if (Schema::hasTable('iiif_ocr_text')) {
                DB::table('iiif_ocr_text')->where('digital_object_id', self::TEST_DO_ID)->delete();
            }
            if (Schema::hasTable('digital_object')) {
                DB::table('digital_object')->where('id', self::TEST_DO_ID)->delete();
            }
        } catch (\Throwable $e) {
            // Best-effort - if any table is missing, nothing to clean.
        }
        parent::tearDown();
    }

    /**
     * Skip the test cleanly when the prereqs aren't available.
     */
    private function ensurePrereqs(): void
    {
        foreach ([
            'ahg_iiif_annotation', 'iiif_ocr_text', 'iiif_ocr_block',
            'digital_object', 'information_object', 'slug', 'object',
        ] as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $this->markTestSkipped("table {$table} not available");
                }
            } catch (\Throwable $e) {
                $this->markTestSkipped('database not reachable: '.$e->getMessage());
            }
        }

        // OpenTelemetry stack must load for the annotations route to
        // resolve (RequestIdMiddleware spans the request). Mirrors the
        // guard in tests/Feature/Annotations/AnnotationsW3cTest.php.
        if (! class_exists(\OpenTelemetry\API\Trace\NoopTracer::class)) {
            $this->markTestSkipped('OpenTelemetry NoopTracer not loadable in this test env');
        }
    }

    /**
     * Pick a real IO that has a slug row and bolt a synthetic
     * digital_object + OCR text + OCR blocks onto it.
     */
    private function seedFixture(): void
    {
        $ioRow = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->orderBy('information_object.id')
            ->select('slug.slug as slug', 'information_object.id as id')
            ->first();
        if (! $ioRow) {
            $this->markTestSkipped('no information_object row available to bolt fixture onto');
        }
        $this->ioId = (int) $ioRow->id;

        // Compose the canvas IRI exactly the way
        // BuildNerAnnotationsForCanvas::buildCanvasMap() does. The
        // test's seeded digital_object is the only DO under this IO
        // for the duration of the test, so canvasIndex = 1.
        $baseUrl = rtrim(config('app.url'), '/');
        $this->canvasIri = "{$baseUrl}/iiif-manifest/{$ioRow->slug}/canvas/1";

        // Synthetic digital_object - small, JPEG, single canvas. We
        // bypass the multi-page TIFF probe by setting a non-TIFF mime,
        // so the bridge takes the single-canvas branch with no
        // Cantaloupe round-trip.
        DB::table('object')->insertOrIgnore(['id' => self::TEST_DO_ID, 'class_name' => 'QubitDigitalObject']);
        DB::table('digital_object')->insertOrIgnore([
            'id' => self::TEST_DO_ID,
            'object_id' => $this->ioId,
            'name' => 'ner-bridge-test.jpg',
            'path' => '/tmp/ner-bridge-test/',
            'mime_type' => 'image/jpeg',
        ]);

        // OCR full_text and one matching block. The bridge looks up
        // word blocks by case-insensitive prefix; we use "Mandela"
        // as the matched first word for the persons bucket.
        $this->ocrId = DB::table('iiif_ocr_text')->insertGetId([
            'digital_object_id' => self::TEST_DO_ID,
            'object_id'         => $this->ioId,
            'full_text'         => 'Nelson Mandela was here in 1962.',
            'format'            => 'plain',
            'language'          => 'en',
            'confidence'        => 87.5,
        ]);

        DB::table('iiif_ocr_block')->insert([
            'ocr_id'       => $this->ocrId,
            'page_number'  => 1,
            'block_type'   => 'word',
            'text'         => 'Nelson',
            'x'            => 50,  'y'      => 60,
            'width'        => 120, 'height' => 28,
            'confidence'   => 92.0,
            'block_order'  => 1,
        ]);
    }

    /**
     * Stand up a stub NerService bound into the container so the
     * job's resolved $ner dependency is the stub, not the live one.
     */
    private function bindFakeNer(array $entities): void
    {
        $stub = new class($entities) extends NerService {
            private array $payload;
            public function __construct(array $payload)
            {
                $this->payload = $payload;
                // NB: skip parent::__construct - no LlmService needed
                // for the stub, and the parent would otherwise read
                // ahg_settings + try to reach the API URL on instantiation.
            }
            public function extract(string $text): array
            {
                return $this->payload;
            }
        };
        $this->app->instance(NerService::class, $stub);
    }

    public function test_dispatchSync_writes_annotations_for_seeded_canvas(): void
    {
        $this->ensurePrereqs();
        $this->seedFixture();
        $this->bindFakeNer([
            'persons'       => ['Nelson Mandela'],
            'organizations' => [],
            'places'        => [],
            'dates'         => ['1962'],
        ]);

        BuildNerAnnotationsForCanvas::dispatchSync($this->ioId, self::TEST_DO_ID);

        $rows = DB::table('ahg_iiif_annotation')
            ->where('target_iri', $this->canvasIri)
            ->get();
        $this->assertGreaterThanOrEqual(
            1,
            $rows->count(),
            'Bridge must persist at least one annotation for the seeded canvas.'
        );

        // The person entity must be present with the correct entity-type classifier.
        $persons = $rows->filter(function ($row) {
            $ann = json_decode((string) $row->body_json, true);
            $cls = collect($ann['body'] ?? [])
                ->first(fn ($b) => is_array($b) && ($b['purpose'] ?? null) === 'classifying');
            return $cls && ($cls['value'] ?? null) === 'Person';
        });
        $this->assertSame(1, $persons->count(), 'Exactly one Person annotation expected for "Nelson Mandela".');

        // Provenance: every emitted row must carry source=ner and a non-empty run_id.
        foreach ($rows as $row) {
            $ann = json_decode((string) $row->body_json, true);
            $this->assertSame('ner', $ann['_heratio']['source'] ?? null);
            $this->assertNotEmpty($ann['_heratio']['run_id'] ?? null);
        }
    }

    public function test_annotation_appears_in_search_endpoint_container(): void
    {
        $this->ensurePrereqs();
        $this->seedFixture();
        $this->bindFakeNer([
            'persons'       => ['Nelson Mandela'],
            'organizations' => [],
            'places'        => [],
            'dates'         => [],
        ]);

        BuildNerAnnotationsForCanvas::dispatchSync($this->ioId, self::TEST_DO_ID);

        // The W3C Web Annotation container served by ahg-annotations.
        // Mirador's companion window dereferences this URL.
        $response = $this->getJson('/api/annotations/search?targetId='.urlencode($this->canvasIri));
        $response->assertStatus(200);

        // WAP Content-Type per the existing endpoint contract.
        $this->assertStringContainsString(
            'application/ld+json',
            (string) $response->headers->get('Content-Type')
        );

        $json = $response->json();
        $this->assertIsArray($json);
        // The endpoint returns an Annotot-shaped envelope with a
        // `resources` array of full annotations. Drill into the
        // resources to find the NER-emitted one.
        $resources = $json['resources'] ?? $json['first']['items'] ?? $json['items'] ?? [];
        $this->assertNotEmpty(
            $resources,
            'Search container must contain at least one annotation for the seeded canvas.'
        );

        $found = false;
        foreach ($resources as $entry) {
            // Annotot wraps W3C in "resource"; W3C envelope inlines body.
            $body = $entry['body'] ?? $entry['resource'] ?? [];
            if (! is_array($body)) {
                continue;
            }
            // Normalise to a list - body can be a single object or a list.
            if (isset($body['type']) || isset($body['value'])) {
                $body = [$body];
            }
            foreach ($body as $b) {
                if (is_array($b) && ($b['value'] ?? null) === 'Nelson Mandela') {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertTrue(
            $found,
            'NER-emitted "Nelson Mandela" annotation must surface in the canvas annotation container.'
        );
    }
}
