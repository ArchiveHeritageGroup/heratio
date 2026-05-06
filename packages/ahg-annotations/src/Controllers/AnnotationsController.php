<?php

/**
 * AnnotationsController - Controller for Heratio
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

namespace AhgAnnotations\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * IIIF Web Annotations REST endpoint (Annotot-shaped).
 *
 * Closes #100 (the persistence half of #81). Mirador's
 * `mirador-annotations` plugin is configured at viewer init with
 * `endpointUrl: '/api/annotations'`; this controller serves the five
 * verbs the plugin needs:
 *
 *   POST   /api/annotations               create
 *   GET    /api/annotations/search?targetId=<canvas_iri>   list-by-canvas
 *   GET    /api/annotations/{uuid}        fetch one
 *   PUT    /api/annotations/{uuid}        update
 *   DELETE /api/annotations/{uuid}        remove
 *
 * Body shape is W3C Web Annotation JSON-LD. We store the document
 * verbatim in body_json so the full spec flexibility (multiple bodies,
 * motivation, FragmentSelector, etc.) round-trips intact.
 *
 * Auth: anonymous users can read; only authenticated users can write
 * (POST/PUT/DELETE). Gated in routes/web.php via auth.required middleware.
 */
class AnnotationsController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $targetId = (string) $request->query('targetId', '');
        if ($targetId === '') {
            return response()->json(['resources' => []]);
        }

        $rows = DB::table('ahg_iiif_annotation')
            ->where('target_iri', $targetId)
            ->orderBy('id')
            ->get(['uuid', 'body_json', 'created_at', 'updated_at']);

        // Annotot's response shape: { resources: [W3C Annotation, ...] }
        // Each resource is the body_json with `id` rewritten to our
        // canonical URL so the client uses the right id for PUT/DELETE.
        $resources = $rows->map(function ($row) {
            $body = json_decode($row->body_json, true) ?: [];
            $body['id'] = url('/api/annotations/' . $row->uuid);
            return $this->expandSelector($body);
        })->all();

        return response()->json(['resources' => $resources]);
    }

    public function show(string $uuid): JsonResponse
    {
        $row = DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $body = json_decode($row->body_json, true) ?: [];
        $body['id'] = url('/api/annotations/' . $uuid);
        return response()->json($this->expandSelector($body));
    }

    /**
     * Bridge MAE's save format with Mirador's stock canvas-overlay reader.
     *
     * mirador-annotation-editor stores the drawn SVG inside
     * `maeData.target.svg` and writes the W3C `target` field as a bare
     * string (the canvas IRI). Mirador's stock canvas annotation overlay,
     * which is what actually paints SVG shapes onto the OpenSeadragon
     * canvas, reads `target.selector.value` for SvgSelector content. With
     * MAE's format alone, the overlay finds no selector and nothing
     * renders — even though the drawing was saved correctly.
     *
     * On read, we expand the shape: when maeData.target.svg is present
     * and target is still a bare string, we promote target to an object
     * with a SvgSelector populated from maeData. MAE's own readers still
     * find maeData unchanged, so editing continues to work.
     */
    private function expandSelector(array $body): array
    {
        $svg = $body['maeData']['target']['svg'] ?? null;
        if (!$svg) return $body;

        $targetSource = is_string($body['target'] ?? null)
            ? $body['target']
            : ($body['target']['source'] ?? $body['target']['id'] ?? null);
        if (!$targetSource) return $body;

        // Preserve any existing selector entries (e.g. FragmentSelector
        // from older saves) and append our SvgSelector.
        $existing = (is_array($body['target'] ?? null) && isset($body['target']['selector']))
            ? $body['target']['selector']
            : [];
        $existingArr = is_array($existing) && array_is_list($existing) ? $existing : ($existing ? [$existing] : []);

        $hasSvg = false;
        foreach ($existingArr as $sel) {
            if (is_array($sel) && ($sel['type'] ?? null) === 'SvgSelector' && !empty($sel['value'])) {
                $hasSvg = true;
                break;
            }
        }
        if (!$hasSvg) {
            $existingArr[] = ['type' => 'SvgSelector', 'value' => $svg];
        }

        $body['target'] = [
            'source'   => $targetSource,
            'selector' => count($existingArr) === 1 ? $existingArr[0] : $existingArr,
        ];

        return $body;
    }

    public function store(Request $request): JsonResponse
    {
        // Return JSON 401 instead of redirecting unauthenticated callers to
        // /login (the auth.required group does that by default and the
        // mirador-annotation-editor's adapter chokes on HTML responses).
        if (!Auth::check()) {
            return response()->json(['error' => 'Authentication required to save annotations.'], 401);
        }

        $body = $request->json()->all();
        if (empty($body)) {
            return response()->json(['error' => 'Empty body'], 422);
        }

        $targetIri = $this->extractTargetIri($body);
        if ($targetIri === '') {
            return response()->json(['error' => 'target.id (or target string) required'], 422);
        }

        $uuid = (string) Str::uuid();
        $userId = Auth::id();

        // Ensure schema exists. Defensive — the package install.sql runs on
        // first boot via the service provider, but a fresh deployment that
        // hasn't booted the provider yet would 500 instead of returning a
        // tidy error.
        if (!Schema::hasTable('ahg_iiif_annotation')) {
            return response()->json(['error' => 'Annotations storage not initialised'], 503);
        }

        // Annotot+Mirador set body.id to the eventual server URL after
        // create. We'll patch it in the saved JSON to point at our route
        // before storing, so re-fetches round-trip without rewriting.
        $body['id'] = url('/api/annotations/' . $uuid);

        DB::table('ahg_iiif_annotation')->insert([
            'uuid'                  => $uuid,
            'target_iri'            => $targetIri,
            'information_object_id' => $this->resolveIoIdFromTarget($targetIri),
            'body_json'             => json_encode($body, JSON_UNESCAPED_SLASHES),
            'created_by'            => $userId,
            'updated_by'            => $userId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        return response()->json($body, 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Authentication required to save annotations.'], 401);
        }
        $row = DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $body = $request->json()->all();
        if (empty($body)) {
            return response()->json(['error' => 'Empty body'], 422);
        }

        // Allow target.id change (rare, but legitimate for cross-canvas moves).
        $targetIri = $this->extractTargetIri($body) ?: $row->target_iri;
        $body['id'] = url('/api/annotations/' . $uuid);

        DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->update([
            'target_iri'            => $targetIri,
            'information_object_id' => $this->resolveIoIdFromTarget($targetIri) ?? $row->information_object_id,
            'body_json'             => json_encode($body, JSON_UNESCAPED_SLASHES),
            'updated_by'            => Auth::id(),
            'updated_at'            => now(),
        ]);

        return response()->json($body);
    }

    public function destroy(string $uuid): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Authentication required to save annotations.'], 401);
        }
        $deleted = DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['deleted' => true]);
    }

    /**
     * Pull the target canvas IRI out of a W3C Web Annotation document. The
     * spec allows several shapes: target as a string, target as an object
     * with `id` or `source`, target as an array of those. We accept any of
     * the common forms and return the first IRI found.
     */
    private function extractTargetIri(array $body): string
    {
        $t = $body['target'] ?? null;
        if (is_string($t)) return $t;
        if (is_array($t)) {
            // Single target as object
            if (isset($t['id']) && is_string($t['id'])) return $t['id'];
            if (isset($t['source']) && is_string($t['source'])) return $t['source'];
            // Array of targets: take the first parseable
            foreach ($t as $entry) {
                if (is_string($entry)) return $entry;
                if (is_array($entry)) {
                    if (isset($entry['id']) && is_string($entry['id'])) return $entry['id'];
                    if (isset($entry['source']) && is_string($entry['source'])) return $entry['source'];
                }
            }
        }
        return '';
    }

    /**
     * Best-effort IO lookup. The local IIIF service emits canvas IRIs with
     * the IO slug embedded — we parse it out so admin can filter by IO.
     * Returns null when the IRI doesn't match the local pattern (remote
     * IIIF servers, etc.).
     */
    private function resolveIoIdFromTarget(string $iri): ?int
    {
        // Expect: https://host/iiif/3/<encoded-id>/canvas/<n>
        // Where encoded-id has _SL_ separators from ahg-iiif-viewer.js.
        // Resolving this back to information_object.id is non-trivial
        // without round-tripping the slug; punt for now.
        return null;
    }
}
