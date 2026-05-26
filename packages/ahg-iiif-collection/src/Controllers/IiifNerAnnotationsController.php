<?php

/**
 * IiifNerAnnotationsController - issue #697 finishing pass.
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

namespace AhgIiifCollection\Controllers;

use AhgIiifCollection\Jobs\BuildNerAnnotationsForCanvas;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * IIIF NER-annotation surface for issue #697.
 *
 * Two endpoints sit here:
 *
 *   * GET  /iiif-manifest/{slug}/canvas/{n}/annotations
 *       Returns a W3C AnnotationPage of every NER-tagged ahg_iiif_annotation
 *       row pinned to that canvas IRI. Public read, but gated through
 *       odrl:use middleware on the route so private records honour the
 *       same access policy as the IO show page.
 *
 *   * POST /api/iiif/annotations/from-ner
 *       Ingestion endpoint: accepts an AI service's NER output for one
 *       canvas, persists it into ahg_iiif_annotation via the same
 *       BuildNerAnnotationsForCanvas helper (using the public
 *       persistAnnotation method). Auth via api.auth middleware -
 *       bearer token OR X-API-Key OR a logged-in session.
 *
 * Both share the canvas-IRI shape:
 *
 *   https://host/iiif-manifest/{slug}/canvas/{n}
 *
 * which matches what BuildNerAnnotationsForCanvas writes into
 * target_iri today. That gives us a single keyed-by-string surface
 * spanning the bridge job, the ingestion API, the manifest §3.3
 * `annotations` array, and the existing /api/annotations/search read.
 */
class IiifNerAnnotationsController extends Controller
{
    /**
     * GET /iiif-manifest/{slug}/canvas/{n}/annotations
     *
     * Returns a W3C AnnotationPage of every NER row pinned to the canvas.
     * `n` is the 1-based canvas index as emitted by IiifCollectionService.
     */
    public function canvasAnnotations(Request $request, string $slug, int $n): JsonResponse
    {
        if (! Schema::hasTable('ahg_iiif_annotation')) {
            return $this->emptyPage($slug, $n);
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $canvasIri = "{$baseUrl}/iiif-manifest/{$slug}/canvas/{$n}";

        // Prefer rows materialised with ner_run_id populated; fall back
        // to JSON_EXTRACT for legacy rows that pre-date the column
        // (backfilled via ahg:annotations:backfill-ner-columns, but the
        // endpoint must not blow up on an un-backfilled install).
        $query = DB::table('ahg_iiif_annotation')
            ->where('target_iri', $canvasIri)
            ->orderBy('id');
        if (Schema::hasColumn('ahg_iiif_annotation', 'ner_run_id')) {
            $query->where(function ($q) {
                $q->whereNotNull('ner_run_id')
                    ->orWhereRaw("JSON_EXTRACT(body_json, '$._heratio.source') = ?", ['"ner"']);
            });
        } else {
            $query->whereRaw("JSON_EXTRACT(body_json, '$._heratio.source') = ?", ['"ner"']);
        }

        $rows = $query->get(['uuid', 'body_json']);

        $items = $rows->map(function ($row) {
            $body = json_decode((string) $row->body_json, true) ?: [];
            // Stable canonical id - matches what /api/annotations/{uuid}
            // serves, so a client following the Pres 3 annotations link
            // can dereference any item back to the per-annotation view.
            $body['id'] = url('/api/annotations/'.$row->uuid);

            return $body;
        })->values()->all();

        $pageId = "{$canvasIri}/annotations";

        return response()->json([
            '@context' => [
                'http://www.w3.org/ns/anno.jsonld',
                'http://iiif.io/api/presentation/3/context.json',
            ],
            'id' => $pageId,
            'type' => 'AnnotationPage',
            'partOf' => $canvasIri,
            'items' => $items,
        ]);
    }

    /**
     * POST /api/iiif/annotations/from-ner
     *
     * Body shape (documented in docs/reference/ner-annotation-bridge.md):
     *
     *   {
     *     "canvas_id": "https://host/iiif-manifest/{slug}/canvas/3",
     *     "run_id":    "uuid-or-arbitrary-stable-string",
     *     "model":     "ahg-ner-v1",
     *     "model_version": "2026.05",
     *     "entities": [
     *       {
     *         "text":  "Nelson Mandela",
     *         "type":  "Person",
     *         "confidence": 0.94,
     *         "start": 102,
     *         "end":   116,
     *         "bbox":  { "x": 50, "y": 60, "w": 120, "h": 28 },
     *         "uri":   "http://www.wikidata.org/entity/Q8023"
     *       }
     *     ]
     *   }
     *
     * For each entity we persist one ahg_iiif_annotation row via
     * BuildNerAnnotationsForCanvas::persistAnnotation. The job-level
     * intra-run dedup guard applies: the SAME (canvas, xywh, text)
     * inside one request is collapsed to a single row.
     */
    public function ingestFromNer(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            return response()->json([
                'success' => false,
                'error' => 'Body must be a JSON object.',
            ], 422);
        }

        $canvasId = (string) ($payload['canvas_id'] ?? '');
        if ($canvasId === '') {
            return response()->json([
                'success' => false,
                'error' => 'canvas_id is required.',
            ], 422);
        }
        $entities = $payload['entities'] ?? [];
        if (! is_array($entities) || empty($entities)) {
            return response()->json([
                'success' => false,
                'error' => 'entities[] is required and must be non-empty.',
            ], 422);
        }

        // Resolve the IO id from the canvas IRI's slug segment so the
        // ahg_iiif_annotation row has an information_object_id and the
        // /admin filter-by-IO query works on ingested rows.
        $ioId = $this->resolveIoIdFromCanvasIri($canvasId);
        $runId = isset($payload['run_id']) && is_string($payload['run_id'])
            ? substr($payload['run_id'], 0, 64)
            : (string) Str::uuid();

        // Build a job instance to reuse persistAnnotation (dedup guard
        // + ner_* column wiring + body_json envelope all live there).
        // We rebind the runId so the caller's run id flows through.
        $job = new BuildNerAnnotationsForCanvas($ioId ?? 0, null);
        $reflect = new \ReflectionObject($job);
        if ($reflect->hasProperty('runId')) {
            $prop = $reflect->getProperty('runId');
            $prop->setAccessible(true);
            $prop->setValue($job, $runId);
        }

        $inserted = 0;
        $deduped = 0;
        $skipped = 0;
        foreach ($entities as $i => $entity) {
            if (! is_array($entity)) {
                $skipped++;
                continue;
            }
            $text = trim((string) ($entity['text'] ?? ''));
            $type = trim((string) ($entity['type'] ?? ''));
            if ($text === '' || $type === '') {
                $skipped++;
                continue;
            }
            $bbox = $entity['bbox'] ?? null;
            if (! is_array($bbox)) {
                // Without an xywh box we have nowhere to pin the
                // annotation - the bridge's FragmentSelector requires
                // it. Reject the entity rather than emit a degraded row.
                $skipped++;
                continue;
            }
            $confidence = isset($entity['confidence']) && is_numeric($entity['confidence'])
                ? max(0.0, min(1.0, (float) $entity['confidence']))
                : null;
            $uri = isset($entity['uri']) && is_string($entity['uri']) ? $entity['uri'] : null;

            $persisted = $job->persistAnnotation(
                $canvasId,
                $text,
                $type,
                $uri,
                [
                    'x' => (int) ($bbox['x'] ?? 0),
                    'y' => (int) ($bbox['y'] ?? 0),
                    'w' => (int) ($bbox['w'] ?? $bbox['width'] ?? 0),
                    'h' => (int) ($bbox['h'] ?? $bbox['height'] ?? 0),
                ],
                (string) ($payload['language'] ?? 'en'),
                (int) $i,
                $confidence
            );
            if ($persisted) {
                $inserted++;
            } else {
                $deduped++;
            }
        }

        return response()->json([
            'success' => true,
            'run_id' => $runId,
            'canvas_id' => $canvasId,
            'inserted' => $inserted,
            'deduped' => $deduped,
            'skipped' => $skipped,
            'model' => $payload['model'] ?? null,
            'model_version' => $payload['model_version'] ?? null,
        ], 201);
    }

    /**
     * Parse an information_object id out of a canvas IRI of the shape
     * https://host/iiif-manifest/{slug}/canvas/{n}. Returns null when
     * the slug doesn't resolve (annotation is still persisted, the
     * column just stays null - admin tooling can fix it up later).
     */
    private function resolveIoIdFromCanvasIri(string $iri): ?int
    {
        if (! preg_match('#/iiif-manifest/([^/]+)/canvas/\d+#', $iri, $m)) {
            return null;
        }
        $slug = $m[1];
        try {
            if (! Schema::hasTable('slug')) {
                return null;
            }
            $row = DB::table('slug')->where('slug', $slug)->first(['object_id']);
            return $row ? (int) $row->object_id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function emptyPage(string $slug, int $n): JsonResponse
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $canvasIri = "{$baseUrl}/iiif-manifest/{$slug}/canvas/{$n}";

        return response()->json([
            '@context' => [
                'http://www.w3.org/ns/anno.jsonld',
                'http://iiif.io/api/presentation/3/context.json',
            ],
            'id' => "{$canvasIri}/annotations",
            'type' => 'AnnotationPage',
            'partOf' => $canvasIri,
            'items' => [],
        ]);
    }
}
