<?php

/**
 * NerAnnotationBridgeTest - issue #697 finishing pass.
 *
 * Exercises the NER -> Web Annotation bridge that lives inside
 * \AhgIiifCollection\Jobs\BuildNerAnnotationsForCanvas. The job is the
 * single shipped bridge surface today (no standalone NerAnnotationBridge
 * service class is registered - the job IS the bridge). These tests
 * pin the on-disk behaviour:
 *
 *   * NER entity buckets (persons/organizations/places/dates) round-trip
 *     into W3C Web Annotation documents with the IIIF-compatible
 *     FragmentSelector xywh shape and the canvas IRI as target.source.
 *   * OCR-block confidence threshold (MIN_BLOCK_CONFIDENCE = 30.0)
 *     suppresses blocks with very-low-confidence boxes.
 *   * Per-(canvas, entity-type) cap (MAX_PER_TYPE_PER_CANVAS = 100)
 *     prevents runaway emissions on repetitive OCR.
 *   * Entity URIs (e.g. Wikidata QIDs) materialise as SpecificResource
 *     bodies alongside the TextualBody label.
 *   * Provenance markers (_heratio.source / run_id / entity_index /
 *     entity_type) are embedded in body_json so admin tooling can
 *     identify, query, and delete-by-run.
 *   * Deduplication of same-text-same-position emissions inside a
 *     single run - GAP: the current job is append-only; documented as
 *     a skipped test so the gap is visible in the suite, not silently
 *     papered over (issue #697 follow-up).
 *
 * The tests work entirely against the ahg_iiif_annotation table; no
 * NER service, no Cantaloupe probe, no OCR fixture rows are needed.
 * Private helpers on the job are exercised via reflection - this is
 * deliberate because the bridge is the ONLY consumer those helpers
 * have, so leaking them public for testability would widen the
 * surface area of a locked package for no operational gain.
 *
 * Skipped automatically when the ahg_iiif_annotation table is
 * unreachable (CI without MySQL).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 */

namespace Tests\Unit\Iiif;

use AhgAiServices\Services\NerService;
use AhgIiifCollection\Jobs\BuildNerAnnotationsForCanvas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class NerAnnotationBridgeTest extends TestCase
{
    /**
     * Canvas IRI used for the bridge tests. Distinctive so tearDown
     * can sweep without colliding with real annotations.
     */
    private const TEST_CANVAS = 'https://test.heratio.local/iiif-manifest/ner-bridge-test/canvas/1';

    /**
     * Best-effort cleanup of any annotation pinned to TEST_CANVAS.
     * The job inserts with DB::table()->insert() outside any test
     * transaction, so DatabaseTransactions would not unwind it.
     */
    protected function tearDown(): void
    {
        try {
            if (Schema::hasTable('ahg_iiif_annotation')) {
                DB::table('ahg_iiif_annotation')
                    ->where('target_iri', self::TEST_CANVAS)
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Schema unavailable: nothing to clean.
        }
        parent::tearDown();
    }

    /**
     * Guard the suite when the ahg_iiif_annotation table is not
     * reachable (typically CI without a writable MySQL).
     */
    private function ensureBridgeStorageAvailable(): void
    {
        try {
            if (! Schema::hasTable('ahg_iiif_annotation')) {
                $this->markTestSkipped('ahg_iiif_annotation table not available');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('database not reachable: '.$e->getMessage());
        }
    }

    /**
     * Build a job instance plus the canvas mapping the private
     * emitForEntities() helper expects.
     *
     * @return array{0:BuildNerAnnotationsForCanvas, 1:array<string,mixed>}
     */
    private function makeJobAndCanvasInfo(): array
    {
        $job = new BuildNerAnnotationsForCanvas(ioId: 9999999, digitalObjectId: null);
        $canvasInfo = [
            'base'  => self::TEST_CANVAS,
            'pages' => [1 => self::TEST_CANVAS],
        ];
        return [$job, $canvasInfo];
    }

    /**
     * Invoke the private emitForEntities() helper via reflection.
     * Returns the row count it reported emitting.
     */
    private function invokeEmit(
        BuildNerAnnotationsForCanvas $job,
        array $canvasInfo,
        array $entities,
        array $blocks,
        string $language = 'en'
    ): int {
        $rc = new ReflectionClass(BuildNerAnnotationsForCanvas::class);
        $method = $rc->getMethod('emitForEntities');
        $method->setAccessible(true);
        // The job iterates with foreach; an array of stdClass works
        // identically to the Query Builder's Collection.
        $blockObjs = array_map(fn ($b) => (object) $b, $blocks);
        return (int) $method->invokeArgs(
            $job,
            [
                'https://test.heratio.local/iiif-manifest/ner-bridge-test',
                $canvasInfo,
                $entities,
                $blockObjs,
                $language,
            ]
        );
    }

    public function test_person_entity_round_trips_to_w3c_annotation(): void
    {
        $this->ensureBridgeStorageAvailable();
        [$job, $canvasInfo] = $this->makeJobAndCanvasInfo();

        $entities = [
            'persons'       => ['Jane Cooper'],
            'organizations' => [],
            'places'        => [],
            'dates'         => [],
        ];
        $blocks = [[
            'id'           => 1,
            'page_number'  => 1,
            'text'         => 'Jane',
            'x'            => 100,
            'y'            => 200,
            'width'        => 80,
            'height'       => 24,
            'confidence'   => 90.5,
            'block_order'  => 1,
        ]];

        $emitted = $this->invokeEmit($job, $canvasInfo, $entities, $blocks);
        $this->assertSame(1, $emitted, 'A single block-text-prefix match must emit exactly one annotation.');

        $row = DB::table('ahg_iiif_annotation')
            ->where('target_iri', self::TEST_CANVAS)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row, 'Bridge must persist an ahg_iiif_annotation row.');

        $ann = json_decode((string) $row->body_json, true);
        $this->assertIsArray($ann);
        $this->assertSame('Annotation', $ann['type'], 'type must be W3C Annotation.');
        $this->assertSame('tagging', $ann['motivation'], 'NER bridge always emits motivation=tagging.');
        $this->assertSame('http://www.w3.org/ns/anno.jsonld', $ann['@context']);

        // Target shape: SpecificResource on the canvas with a FragmentSelector xywh.
        $this->assertSame(self::TEST_CANVAS, $ann['target']['source']);
        $this->assertSame('FragmentSelector', $ann['target']['selector']['type']);
        $this->assertSame('http://www.w3.org/TR/media-frags/', $ann['target']['selector']['conformsTo']);
        $this->assertSame('xywh=100,200,80,24', $ann['target']['selector']['value']);

        // Body shape: TextualBody (display) + TextualBody (classifying entity type).
        $this->assertIsArray($ann['body']);
        $textual = array_values(array_filter(
            $ann['body'],
            fn ($b) => is_array($b) && ($b['purpose'] ?? null) === 'tagging'
        ));
        $this->assertNotEmpty($textual, 'NER bridge must emit a tagging TextualBody.');
        $this->assertSame('Jane Cooper', $textual[0]['value']);

        $classifying = array_values(array_filter(
            $ann['body'],
            fn ($b) => is_array($b) && ($b['purpose'] ?? null) === 'classifying'
        ));
        $this->assertNotEmpty($classifying, 'NER bridge must classify with entity type.');
        $this->assertSame('Person', $classifying[0]['value'], 'persons bucket -> Person classifier.');

        // Provenance markers.
        $this->assertSame('ner', $ann['_heratio']['source']);
        $this->assertSame('Person', $ann['_heratio']['entity_type']);
        $this->assertNotEmpty($ann['_heratio']['run_id'], 'run_id must be present for delete-by-run.');
    }

    public function test_entity_with_uri_emits_specific_resource_identifying_body(): void
    {
        $this->ensureBridgeStorageAvailable();
        [$job, $canvasInfo] = $this->makeJobAndCanvasInfo();

        $entities = [
            'persons'       => [],
            'organizations' => [],
            'places'        => [[
                'value' => 'Cape Town',
                'uri'   => 'http://www.wikidata.org/entity/Q5465',
            ]],
            'dates'         => [],
        ];
        $blocks = [[
            'id' => 2, 'page_number' => 1, 'text' => 'Cape',
            'x' => 10, 'y' => 20, 'width' => 60, 'height' => 18,
            'confidence' => 80.0, 'block_order' => 1,
        ]];

        $this->invokeEmit($job, $canvasInfo, $entities, $blocks);

        $row = DB::table('ahg_iiif_annotation')
            ->where('target_iri', self::TEST_CANVAS)
            ->first();
        $this->assertNotNull($row);
        $ann = json_decode((string) $row->body_json, true);

        $identifying = array_values(array_filter(
            $ann['body'],
            fn ($b) => is_array($b) && ($b['purpose'] ?? null) === 'identifying'
        ));
        $this->assertNotEmpty(
            $identifying,
            'Entities with a URI must carry a SpecificResource body with purpose=identifying.'
        );
        $this->assertSame('SpecificResource', $identifying[0]['type']);
        $this->assertSame('http://www.wikidata.org/entity/Q5465', $identifying[0]['source']);
    }

    public function test_low_confidence_block_is_filtered(): void
    {
        $this->ensureBridgeStorageAvailable();
        [$job, $canvasInfo] = $this->makeJobAndCanvasInfo();

        // confidence=10 sits below MIN_BLOCK_CONFIDENCE=30.0 - the
        // bridge must skip the emission entirely.
        $entities = [
            'persons'       => ['Smith'],
            'organizations' => [],
            'places'        => [],
            'dates'         => [],
        ];
        $blocks = [[
            'id' => 3, 'page_number' => 1, 'text' => 'Smith',
            'x' => 0, 'y' => 0, 'width' => 50, 'height' => 12,
            'confidence' => 10.0, 'block_order' => 1,
        ]];

        $emitted = $this->invokeEmit($job, $canvasInfo, $entities, $blocks);
        $this->assertSame(0, $emitted, 'Block confidence below threshold must yield zero emissions.');

        $count = DB::table('ahg_iiif_annotation')
            ->where('target_iri', self::TEST_CANVAS)
            ->count();
        $this->assertSame(0, $count, 'No annotation row should be persisted for filtered blocks.');
    }

    public function test_null_confidence_block_is_kept(): void
    {
        // OCR engines often emit no per-block confidence (Tesseract
        // legacy / unstructured PDF text). The bridge must not treat
        // null as "below threshold" - it keeps the emission so we
        // don't silently drop the entire run on confidence-less OCR.
        $this->ensureBridgeStorageAvailable();
        [$job, $canvasInfo] = $this->makeJobAndCanvasInfo();

        $entities = [
            'persons'       => [],
            'organizations' => ['Acme Holdings'],
            'places'        => [],
            'dates'         => [],
        ];
        $blocks = [[
            'id' => 4, 'page_number' => 1, 'text' => 'Acme',
            'x' => 5, 'y' => 7, 'width' => 90, 'height' => 22,
            'confidence' => null, 'block_order' => 1,
        ]];

        $emitted = $this->invokeEmit($job, $canvasInfo, $entities, $blocks);
        $this->assertSame(1, $emitted, 'Null confidence must not be treated as "below threshold".');
    }

    public function test_per_type_cap_bounds_emissions(): void
    {
        $this->ensureBridgeStorageAvailable();
        [$job, $canvasInfo] = $this->makeJobAndCanvasInfo();

        // 150 candidate blocks, all matching "doe" - the cap is
        // MAX_PER_TYPE_PER_CANVAS=100. Anything above that is dropped.
        $entities = [
            'persons'       => ['Doe'],
            'organizations' => [],
            'places'        => [],
            'dates'         => [],
        ];
        $blocks = [];
        for ($i = 0; $i < 150; $i++) {
            $blocks[] = [
                'id' => 1000 + $i,
                'page_number' => 1,
                'text' => 'doe',
                'x' => $i, 'y' => 0, 'width' => 30, 'height' => 12,
                'confidence' => 80.0,
                'block_order' => $i,
            ];
        }

        $emitted = $this->invokeEmit($job, $canvasInfo, $entities, $blocks);
        $this->assertSame(100, $emitted, 'Bridge must cap at 100 emissions per (canvas, entity-type) pair.');

        $count = DB::table('ahg_iiif_annotation')
            ->where('target_iri', self::TEST_CANVAS)
            ->count();
        $this->assertSame(100, $count);
    }

    public function test_first_word_match_is_case_insensitive_and_supports_multiword_entities(): void
    {
        $this->ensureBridgeStorageAvailable();
        [$job, $canvasInfo] = $this->makeJobAndCanvasInfo();

        // Entity "Cape Town" tokenises against the per-word OCR block
        // "Cape" via case-insensitive first-word prefix match. The
        // annotation labels the full entity string in its body.
        $entities = [
            'persons'       => [],
            'organizations' => [],
            'places'        => ['Cape Town'],
            'dates'         => [],
        ];
        $blocks = [[
            'id' => 5, 'page_number' => 1, 'text' => 'CAPE',
            'x' => 11, 'y' => 22, 'width' => 60, 'height' => 18,
            'confidence' => 88.0, 'block_order' => 1,
        ]];

        $this->invokeEmit($job, $canvasInfo, $entities, $blocks);

        $row = DB::table('ahg_iiif_annotation')
            ->where('target_iri', self::TEST_CANVAS)
            ->first();
        $this->assertNotNull($row, 'Case-insensitive prefix match must hit.');
        $ann = json_decode((string) $row->body_json, true);
        $tagging = array_values(array_filter(
            $ann['body'],
            fn ($b) => is_array($b) && ($b['purpose'] ?? null) === 'tagging'
        ));
        $this->assertSame('Cape Town', $tagging[0]['value'],
            'Body label is the full entity, not the matched first word.');
    }

    public function test_date_entity_classifies_as_date(): void
    {
        $this->ensureBridgeStorageAvailable();
        [$job, $canvasInfo] = $this->makeJobAndCanvasInfo();

        $entities = [
            'persons' => [], 'organizations' => [], 'places' => [],
            'dates'   => ['1899'],
        ];
        $blocks = [[
            'id' => 6, 'page_number' => 1, 'text' => '1899',
            'x' => 1, 'y' => 1, 'width' => 40, 'height' => 16,
            'confidence' => 95.0, 'block_order' => 1,
        ]];

        $this->invokeEmit($job, $canvasInfo, $entities, $blocks);
        $row = DB::table('ahg_iiif_annotation')->where('target_iri', self::TEST_CANVAS)->first();
        $this->assertNotNull($row);
        $ann = json_decode((string) $row->body_json, true);
        $classifying = array_values(array_filter(
            $ann['body'],
            fn ($b) => is_array($b) && ($b['purpose'] ?? null) === 'classifying'
        ));
        $this->assertSame('Date', $classifying[0]['value']);
        $this->assertSame('Date', $ann['_heratio']['entity_type']);
    }

    /**
     * Intra-run dedup landed in the #697 finishing pass: the bridge keys
     * the second emission of the same (canvas, xywh, body value) against
     * an in-memory set on the job instance and silently drops it. Cross-run
     * dedup remains admin-driven via the embedded run_id (and now the
     * ner_run_id column).
     */
    public function test_intra_run_dedup_same_text_same_position(): void
    {
        $this->ensureBridgeStorageAvailable();
        [$job, $canvasInfo] = $this->makeJobAndCanvasInfo();

        // Same entity, same OCR block - two identical matches inside a
        // single emit pass. The dedup guard must collapse them to one
        // persisted row.
        $entities = [
            'persons'       => ['Mandela', 'Mandela'],
            'organizations' => [],
            'places'        => [],
            'dates'         => [],
        ];
        $blocks = [[
            'id' => 7, 'page_number' => 1, 'text' => 'Mandela',
            'x' => 33, 'y' => 44, 'width' => 70, 'height' => 20,
            'confidence' => 88.0, 'block_order' => 1,
        ]];

        $emitted = $this->invokeEmit($job, $canvasInfo, $entities, $blocks);
        $this->assertSame(
            1,
            $emitted,
            'Second emission of identical (canvas, xywh, value) must be deduped within one run.'
        );

        $count = DB::table('ahg_iiif_annotation')
            ->where('target_iri', self::TEST_CANVAS)
            ->count();
        $this->assertSame(
            1,
            $count,
            'Only one row should be persisted for the duplicate entity pair.'
        );
    }

    /**
     * The ingestion endpoint surfaces persistAnnotation directly. The
     * dedup guard must hold when called twice with identical args, even
     * across HTTP boundary semantics.
     */
    public function test_persist_annotation_dedup_via_public_helper(): void
    {
        $this->ensureBridgeStorageAvailable();
        $job = new BuildNerAnnotationsForCanvas(ioId: 9999998, digitalObjectId: null);

        $first = $job->persistAnnotation(
            self::TEST_CANVAS,
            'Acme Holdings',
            'Organization',
            null,
            ['x' => 10, 'y' => 10, 'w' => 100, 'h' => 30],
            'en',
            0,
            0.92
        );
        $second = $job->persistAnnotation(
            self::TEST_CANVAS,
            'Acme Holdings',
            'Organization',
            null,
            ['x' => 10, 'y' => 10, 'w' => 100, 'h' => 30],
            'en',
            1,
            0.92
        );

        $this->assertTrue($first, 'First persist must insert.');
        $this->assertFalse($second, 'Second identical persist must be deduped.');

        $count = DB::table('ahg_iiif_annotation')
            ->where('target_iri', self::TEST_CANVAS)
            ->count();
        $this->assertSame(1, $count);
    }
}
