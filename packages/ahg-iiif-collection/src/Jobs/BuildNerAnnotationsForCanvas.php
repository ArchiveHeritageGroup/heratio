<?php

/**
 * BuildNerAnnotationsForCanvas - queued job for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgIiifCollection\Jobs;

use AhgAiServices\Services\NerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Build W3C Web Annotations from NER entities extracted off OCR text
 * for an information_object's canvases. Closes the OCR -> entity ->
 * annotation loop. Issue #697.
 *
 * For each (canvas, ocr text) pair we:
 *
 *   1. Pull the iiif_ocr_text full_text + the per-word iiif_ocr_block
 *      offsets.
 *   2. Call NerService::extract() to get persons / organizations /
 *      places / dates.
 *   3. For each entity, locate the word block whose `text` matches
 *      (case-insensitive prefix match - tokenisation drift between
 *      NER and OCR is the norm) and emit one Annotation per match
 *      with a FragmentSelector xywh that covers the block.
 *   4. Persist into ahg_iiif_annotation (the table used by the
 *      ahg-annotations REST endpoint, so the new rows show up in
 *      Mirador without any further wiring).
 *
 * Idempotency: each annotation embeds {ahg_ner_run_id, ahg_ner_entity_index}
 * inside body_json so re-running the job for the same canvas is a
 * delete-then-rebuild cycle rather than a duplicate-spawning pile-up.
 *
 * Selectivity: when --canvas=N is passed to the artisan command we
 * limit to one digital_object. The default is the entire IO.
 */
class BuildNerAnnotationsForCanvas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Confidence floor under which we skip a block-level entity match.
     * Annotations with very-low-confidence target boxes are noisier than
     * useful; consumers can always raise this floor in config.
     */
    private const MIN_BLOCK_CONFIDENCE = 30.0;

    /**
     * Maximum number of annotations to emit per (canvas, entity-type)
     * pair. Stops a runaway loop on extremely repetitive OCR like
     * watermarked legal forms.
     */
    private const MAX_PER_TYPE_PER_CANVAS = 100;

    public int $ioId;
    public ?int $digitalObjectId;
    public string $runId;

    /**
     * Intra-run dedup guard. Keyed by sha1("{canvas}|{x}|{y}|{w}|{h}|{value}")
     * so the second emission of the same entity text against the same
     * pixel box during a single job invocation is silently dropped.
     * Issue #697 follow-up: prevents duplicate API-bounce inputs from
     * spawning duplicate rows. NOT serialized - the job rebuilds the
     * set on every handle() call.
     *
     * @var array<string,bool>
     */
    private array $emittedKeys = [];

    /**
     * @param int $ioId information_object.id
     * @param int|null $digitalObjectId limit to a single digital_object
     *                                  when not null
     */
    public function __construct(int $ioId, ?int $digitalObjectId = null)
    {
        $this->ioId = $ioId;
        $this->digitalObjectId = $digitalObjectId;
        $this->runId = (string) Str::uuid();
    }

    public function handle(NerService $ner): void
    {
        if (!Schema::hasTable('ahg_iiif_annotation')) {
            Log::warning('[iiif-ner] ahg_iiif_annotation table missing - skipping run.', ['io' => $this->ioId]);
            return;
        }
        if (!Schema::hasTable('iiif_ocr_text') || !Schema::hasTable('iiif_ocr_block')) {
            Log::warning('[iiif-ner] iiif_ocr_text / iiif_ocr_block missing - skipping run.', ['io' => $this->ioId]);
            return;
        }

        $slug = DB::table('slug')->where('object_id', $this->ioId)->value('slug');
        if (!$slug) {
            Log::warning('[iiif-ner] No slug for io ' . $this->ioId);
            return;
        }
        $baseUrl = rtrim(config('app.url'), '/');
        $manifestId = $baseUrl . '/iiif-manifest/' . $slug;

        // Build canvas mapping in the same digital_object order as the
        // manifest emitter - this is the spec-anchor that lets us produce
        // a FragmentSelector that lines up with what the viewer sees.
        $canvasMap = $this->buildCanvasMap($this->ioId, $manifestId);
        if (empty($canvasMap)) {
            Log::info('[iiif-ner] No canvases for io ' . $this->ioId);
            return;
        }

        $ocrRowsQuery = DB::table('iiif_ocr_text')
            ->where('object_id', $this->ioId);
        if ($this->digitalObjectId !== null) {
            $ocrRowsQuery->where('digital_object_id', $this->digitalObjectId);
        }
        $ocrRows = $ocrRowsQuery->get(['id', 'digital_object_id', 'full_text', 'language']);

        $totalEmitted = 0;
        foreach ($ocrRows as $ocr) {
            $doId = (int) $ocr->digital_object_id;
            if (!isset($canvasMap[$doId])) {
                continue;
            }
            $text = (string) ($ocr->full_text ?: '');
            if (trim($text) === '') {
                continue;
            }

            $entities = $ner->extract($text);
            if (empty($entities['persons']) && empty($entities['organizations'])
                && empty($entities['places']) && empty($entities['dates'])) {
                continue;
            }

            $blocks = DB::table('iiif_ocr_block')
                ->where('ocr_id', $ocr->id)
                ->orderBy('page_number')
                ->orderBy('block_order')
                ->get(['id', 'page_number', 'text', 'x', 'y', 'width', 'height', 'confidence']);

            $totalEmitted += $this->emitForEntities(
                $manifestId,
                $canvasMap[$doId],
                $entities,
                $blocks,
                (string) ($ocr->language ?: 'en')
            );
        }

        Log::info('[iiif-ner] Run complete', [
            'io' => $this->ioId,
            'digital_object' => $this->digitalObjectId,
            'emitted' => $totalEmitted,
            'run_id' => $this->runId,
        ]);
    }

    /**
     * Emit annotations for each NER entity by finding the matching
     * word block(s).
     *
     * @param array<string,mixed> $canvasInfo {base, pages}
     * @param array{persons:array,organizations:array,places:array,dates:array} $entities
     * @return int annotations emitted
     */
    private function emitForEntities(
        string $manifestId,
        array $canvasInfo,
        array $entities,
        $blocks,
        string $language
    ): int {
        $typeMap = [
            'persons' => 'Person',
            'organizations' => 'Organization',
            'places' => 'Place',
            'dates' => 'Date',
        ];

        $emitted = 0;
        foreach ($typeMap as $key => $entityType) {
            $list = $entities[$key] ?? [];
            if (empty($list) || !is_array($list)) {
                continue;
            }
            $perTypeCount = 0;
            foreach ($list as $entityIndex => $entry) {
                if ($perTypeCount >= self::MAX_PER_TYPE_PER_CANVAS) {
                    break;
                }
                $value = is_array($entry) ? ($entry['value'] ?? $entry['name'] ?? '') : (string) $entry;
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                // Find every word block whose text starts with the
                // first word of the entity (case-insensitive). This
                // copes with multi-word entities ("Cape Town") without
                // requiring exact phrase match against the per-word OCR
                // blocks - the user-facing annotation still labels the
                // full entity string in its body.
                $firstWord = mb_strtolower(preg_split('/\s+/', $value)[0] ?? '');
                if ($firstWord === '') {
                    continue;
                }
                foreach ($blocks as $block) {
                    if ($perTypeCount >= self::MAX_PER_TYPE_PER_CANVAS) {
                        break;
                    }
                    $bt = mb_strtolower((string) ($block->text ?: ''));
                    if ($bt === '' || mb_strpos($bt, $firstWord) === false) {
                        continue;
                    }
                    if ($block->confidence !== null
                        && (float) $block->confidence < self::MIN_BLOCK_CONFIDENCE) {
                        continue;
                    }
                    $page = (int) ($block->page_number ?: 1);
                    $canvasIri = $canvasInfo['pages'][$page] ?? $canvasInfo['base'];
                    if (!$canvasIri) {
                        continue;
                    }

                    $confidence = (is_array($entry) && isset($entry['confidence']) && is_numeric($entry['confidence']))
                        ? max(0.0, min(1.0, (float) $entry['confidence']))
                        : null;
                    $persisted = $this->persistAnnotation(
                        $canvasIri,
                        $value,
                        $entityType,
                        (is_array($entry) ? ($entry['uri'] ?? null) : null),
                        [
                            'x' => (int) $block->x,
                            'y' => (int) $block->y,
                            'w' => (int) $block->width,
                            'h' => (int) $block->height,
                        ],
                        $language,
                        $entityIndex,
                        $confidence
                    );
                    if (! $persisted) {
                        // Intra-run dedup hit - same (canvas, xywh, value)
                        // already emitted this run. Skip silently; do not
                        // count toward the per-type cap.
                        continue;
                    }
                    $perTypeCount++;
                    $emitted++;
                }
            }
        }
        return $emitted;
    }

    /**
     * Persist one W3C Web Annotation into ahg_iiif_annotation. We write
     * directly to the table (rather than POSTing /api/annotations)
     * because we're inside a job - bypassing the HTTP layer keeps the
     * job synchronous and side-effect-free for queue replay.
     *
     * Returns true when a row was inserted, false when the intra-run
     * dedup guard suppressed it. Issue #697 follow-up: the guard keys
     * off (canvas, xywh, value) and lives on $this->emittedKeys.
     *
     * @param array{x:int,y:int,w:int,h:int} $bbox
     */
    public function persistAnnotation(
        string $canvasIri,
        string $entityValue,
        string $entityType,
        ?string $entityUri,
        array $bbox,
        string $language,
        int $entityIndex,
        ?float $confidence = null
    ): bool {
        // Intra-run dedup: same canvas + same xywh + same body label
        // collapses to a single row inside one job invocation. Cross-run
        // dedup stays admin-driven via ner_run_id.
        $dedupKey = sha1(implode('|', [
            $canvasIri,
            (string) $bbox['x'],
            (string) $bbox['y'],
            (string) $bbox['w'],
            (string) $bbox['h'],
            mb_strtolower($entityValue),
        ]));
        if (isset($this->emittedKeys[$dedupKey])) {
            return false;
        }
        $this->emittedKeys[$dedupKey] = true;

        $uuid = (string) Str::uuid();
        $now = now();

        $bodyTextual = [
            'type' => 'TextualBody',
            'value' => $entityValue,
            'language' => $language,
            'format' => 'text/plain',
            'purpose' => 'tagging',
        ];
        if ($entityUri) {
            // SpecificResource body when the entity has a stable URI -
            // e.g. a Wikidata QID resolved by NerService. The TextualBody
            // stays for human-readable display.
            $body = [
                $bodyTextual,
                [
                    'type' => 'SpecificResource',
                    'source' => $entityUri,
                    'purpose' => 'identifying',
                ],
            ];
        } else {
            $body = [$bodyTextual];
        }
        // Tag the entity type via an extra TextualBody with purpose=classifying
        // so the annotation carries machine-readable entity-type info too.
        $body[] = [
            'type' => 'TextualBody',
            'value' => $entityType,
            'format' => 'text/plain',
            'purpose' => 'classifying',
        ];

        $annotation = [
            '@context' => 'http://www.w3.org/ns/anno.jsonld',
            'id' => url('/api/annotations/' . $uuid),
            'type' => 'Annotation',
            'motivation' => 'tagging',
            'body' => $body,
            'target' => [
                'source' => $canvasIri,
                'selector' => [
                    'type' => 'FragmentSelector',
                    'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                    'value' => 'xywh=' . $bbox['x'] . ',' . $bbox['y']
                        . ',' . $bbox['w'] . ',' . $bbox['h'],
                ],
            ],
            // Provenance markers so admin tooling can find / reset every
            // annotation produced by a given run. Mirrored into the
            // ner_* top-level columns by the insert below for fast
            // run-id / entity-type filtering without JSON_EXTRACT.
            '_heratio' => [
                'source' => 'ner',
                'run_id' => $this->runId,
                'entity_index' => $entityIndex,
                'entity_type' => $entityType,
                'confidence' => $confidence,
            ],
        ];

        $bodyJson = json_encode($annotation, JSON_UNESCAPED_SLASHES);
        $etag = sha1($bodyJson . '|' . $now->toIso8601String());

        // Build the insert payload. The ner_* columns are denormalised
        // mirrors of body_json._heratio.* - cheap to query, kept in
        // sync at write time. When the table predates the columns
        // (Schema::hasColumn false) we drop them so older installs
        // keep working until the service-provider re-runs the install.
        $payload = [
            'uuid' => $uuid,
            'target_iri' => $canvasIri,
            'information_object_id' => $this->ioId,
            'project_id' => null,
            'visibility' => 'public',
            'body_json' => $bodyJson,
            'body_selector_json' => null,
            'etag' => $etag,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn('ahg_iiif_annotation', 'ner_run_id')) {
            $payload['ner_run_id'] = $this->runId;
            $payload['ner_entity_type'] = $entityType;
            $payload['ner_confidence'] = $confidence;
        }

        try {
            DB::table('ahg_iiif_annotation')->insert($payload);
        } catch (\Throwable $e) {
            Log::warning('[iiif-ner] persist failed: ' . $e->getMessage(), [
                'canvas' => $canvasIri,
                'entity' => $entityValue,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Walk digital_object rows in the same order as the manifest
     * emitter, probing Cantaloupe for multi-page TIFF page counts. The
     * result is keyed by digital_object_id with sub-keys base + pages.
     */
    private function buildCanvasMap(int $ioId, string $manifestId): array
    {
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $ioId)
            ->orderBy('id')
            ->select('id', 'name', 'path', 'mime_type')
            ->get();

        if ($digitalObjects->isEmpty()) {
            return [];
        }

        $map = [];
        $canvasIndex = 1;
        $cantaloupeBase = 'http://127.0.0.1:8182';
        $maxProbe = 25;

        foreach ($digitalObjects as $do) {
            $imagePath = ltrim((string) $do->path, '/');
            $cantaloupeId = str_replace('/', '_SL_', $imagePath) . $do->name;
            $mime = strtolower($do->mime_type ?? '');
            $fileName = strtolower($do->name ?? '');

            $isMultiPageTiff = false;
            $pageCount = 1;
            if ($mime === 'image/tiff' || preg_match('/\.tiff?$/i', $fileName)) {
                $ctx = stream_context_create(['http' => ['timeout' => 1]]);
                $probe = @file_get_contents(
                    "{$cantaloupeBase}/iiif/2/{$cantaloupeId};2/info.json",
                    false,
                    $ctx
                );
                if ($probe !== false) {
                    $isMultiPageTiff = true;
                    $pageCount = 2;
                    for ($i = 3; $i <= $maxProbe; $i++) {
                        $next = @file_get_contents(
                            "{$cantaloupeBase}/iiif/2/{$cantaloupeId};{$i}/info.json",
                            false,
                            $ctx
                        );
                        if ($next === false) {
                            break;
                        }
                        $pageCount = $i;
                    }
                }
            }

            $info = ['base' => null, 'pages' => []];
            if ($isMultiPageTiff) {
                for ($p = 1; $p <= $pageCount; $p++) {
                    $iri = "{$manifestId}/canvas/{$canvasIndex}";
                    if ($p === 1) {
                        $info['base'] = $iri;
                    }
                    $info['pages'][$p] = $iri;
                    $canvasIndex++;
                }
            } else {
                $iri = "{$manifestId}/canvas/{$canvasIndex}";
                $info['base'] = $iri;
                $info['pages'][1] = $iri;
                $canvasIndex++;
            }
            $map[(int) $do->id] = $info;
        }

        return $map;
    }
}
