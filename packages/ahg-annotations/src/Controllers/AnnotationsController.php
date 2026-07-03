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

use AhgCore\Services\AclService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * IIIF Web Annotations REST endpoint (Annotot-shaped + W3C WAP-conformant).
 *
 * Closes #100 (the persistence half of #81). Phase 1 of #648 widens the
 * surface to full W3C Web Annotation Data Model coverage:
 *
 *   * SpecificResource body shape accepted on POST/PUT.
 *   * TextQuoteSelector / TimeSelector / GeoSelector / MediaFragmentSelector
 *     on the target.selector round-trip losslessly through body_json plus
 *     a denormalised body_selector_json column for body-side selectors.
 *   * Web Annotation Protocol (WAP) header conformance via the
 *     AnnotationContentTypeMiddleware (Content-Type, Link, Accept-Post,
 *     Vary, Allow).
 *   * ETag + If-Match / If-None-Match optimistic concurrency.
 *   * Prefer: contained-iris / contained-descriptions on container fetches.
 *
 * Mirador's `mirador-annotations` plugin is configured at viewer init with
 * `endpointUrl: '/api/annotations'`; this controller serves the five verbs
 * the plugin needs:
 *
 *   POST   /api/annotations               create
 *   GET    /api/annotations/search?targetId=<canvas_iri>   list-by-canvas
 *   GET    /api/annotations/{uuid}        fetch one
 *   PUT    /api/annotations/{uuid}        update
 *   DELETE /api/annotations/{uuid}        remove
 *
 * Body shape is W3C Web Annotation JSON-LD. We store the document verbatim
 * in body_json so the full spec flexibility (multiple bodies, motivation,
 * selectors, etc.) round-trips intact.
 *
 * Auth: anonymous users can read; only authenticated users can write
 * (POST/PUT/DELETE). Gated inside each write verb so the JSON-401 shape
 * lands instead of a 302-to-/login that the Mirador fetch adapter can't
 * parse.
 */
class AnnotationsController extends Controller
{
    /**
     * Selector types we explicitly recognise on the target.selector or
     * body.selector. Unknown types are still round-tripped via body_json,
     * so spec extensions don't get clobbered - this list only governs
     * the shape we look for when normalising during expandSelector().
     */
    private const KNOWN_SELECTOR_TYPES = [
        'FragmentSelector',
        'CssSelector',
        'XPathSelector',
        'TextQuoteSelector',
        'TextPositionSelector',
        'DataPositionSelector',
        'SvgSelector',
        'RangeSelector',
        'TimeSelector',
        'GeoSelector',
        'PointSelector',
        'MediaFragmentSelector',
    ];

    public function search(Request $request): JsonResponse
    {
        $targetId = (string) $request->query('targetId', '');
        if ($targetId === '') {
            return $this->emptyContainer();
        }

        // SECURITY (#1365): object-state gate. The search is scoped to a
        // single target IRI, so resolve it once: anonymous readers only see
        // annotations when the underlying IO is published (status 158/160).
        // Unresolvable / unpublished targets return an empty container.
        if (! auth()->check() && ! $this->targetIoIsPublishedForAnon($targetId)) {
            return $this->emptyContainer();
        }

        $projectId = $request->query('projectId');
        $visibility = $request->query('visibility');
        $authorId = $request->query('createdBy');

        $q = DB::table('ahg_iiif_annotation')
            ->where('target_iri', $targetId)
            ->orderBy('id');

        // SECURITY (#1365): clamp the result set to what the caller is
        // allowed to read BEFORE any client-supplied filter narrows it.
        // Without this, an anonymous GET /api/annotations/search returns
        // every private/project annotation on the target. The client-side
        // ?visibility=/?createdBy= filters below only AND-narrow further,
        // so they can never widen past this scope.
        $this->applyReadVisibilityScope($q);

        // Shared-annotation-layer filters (W3C-Web-Annotation native search
        // ignores these so plain Mirador requests still return everything).
        if ($projectId !== null && $projectId !== '') {
            $q->where('project_id', (int) $projectId);
        }
        if ($visibility !== null && $visibility !== '') {
            $q->where('visibility', (string) $visibility);
        }
        if ($authorId !== null && $authorId !== '') {
            $q->where('created_by', (int) $authorId);
        }

        $rows = $q->get(['uuid', 'body_json', 'created_at', 'updated_at']);

        // WAP Prefer header: clients can request just the IRI list rather
        // than the full annotation descriptions. include="...contained-iris"
        // returns a stub PartOfPage with only ids; contained-descriptions
        // (default) returns full annotations.
        $prefer = (string) $request->header('Prefer', '');
        $wantsIrisOnly = stripos($prefer, 'contained-iris') !== false
            && stripos($prefer, 'contained-descriptions') === false;

        // Annotot's response shape: { resources: [W3C Annotation, ...] }
        // Each resource is the body_json with `id` rewritten to our
        // canonical URL so the client uses the right id for PUT/DELETE.
        $resources = $rows->map(function ($row) use ($wantsIrisOnly) {
            $body = json_decode($row->body_json, true) ?: [];
            $iri = url('/api/annotations/'.$row->uuid);
            if ($wantsIrisOnly) {
                return $iri;
            }
            $body['id'] = $iri;

            return $this->expandSelector($body);
        })->all();

        $response = response()->json($this->wrapContainer($targetId, $resources, $wantsIrisOnly));

        if ($wantsIrisOnly) {
            // Echo back the Preference-Applied header so the client knows
            // its Prefer was honoured. Required by RFC 7240.
            $response->headers->set('Preference-Applied', 'return=representation; include="http://www.w3.org/ns/oa#PreferContainedIRIs"');
        }

        return $response;
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $row = DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->first();
        if (! $row) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // SECURITY (#1365): direct uuid fetch must honour the same read
        // visibility model as search(). A non-owner (anonymous or another
        // user) requesting a private/project annotation gets a 404 so we
        // neither leak the body nor confirm the row exists.
        if (! $this->canReadRow($row)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // SECURITY (#1365): object-state gate. An anonymous reader must not
        // see a (public) annotation whose underlying IO is not published
        // (status 158/160). Resolve the target IRI to its IO and require
        // published status; unresolvable targets fail safe (404 for anon).
        if (! auth()->check() && ! $this->targetIoIsPublishedForAnon($row->target_iri ?? null)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $etag = $this->etagFor($row);

        // If-None-Match short-circuit (304 Not Modified). The client sends
        // its cached etag; if it matches the live row, skip the payload.
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch && $this->etagsMatch($ifNoneMatch, $etag)) {
            $response = response()->json(null, 304);
            $response->headers->set('ETag', '"'.$etag.'"');

            return $response;
        }

        $body = json_decode($row->body_json, true) ?: [];
        $body['id'] = url('/api/annotations/'.$uuid);

        $response = response()->json($this->expandSelector($body));
        $response->headers->set('ETag', '"'.$etag.'"');

        return $response;
    }

    /**
     * Bridge MAE's save format with Mirador's stock canvas-overlay reader,
     * AND extend target.selector + body[*].selector with the W3C-spec
     * selector types added in Phase 1 of #648 (TextQuote / Time / Geo /
     * MediaFragment). Round-trip is lossless: unknown selector types are
     * preserved verbatim in body_json.
     *
     * mirador-annotation-editor stores the drawn SVG inside
     * `maeData.target.svg` and writes the W3C `target` field as a bare
     * string (the canvas IRI). Mirador's stock canvas annotation overlay,
     * which is what actually paints SVG shapes onto the OpenSeadragon
     * canvas, reads `target.selector.value` for SvgSelector content. With
     * MAE's format alone, the overlay finds no selector and nothing
     * renders - even though the drawing was saved correctly.
     *
     * On read, we expand the shape: when maeData.target.svg is present
     * and target is still a bare string, we promote target to an object
     * with a SvgSelector populated from maeData. MAE's own readers still
     * find maeData unchanged, so editing continues to work.
     */
    private function expandSelector(array $body): array
    {
        $svg = $body['maeData']['target']['svg'] ?? null;
        if (! $svg) {
            // No MAE SVG to inject - but selectors on target may still need
            // normalising into an array form for downstream consumers, and
            // body-side SpecificResource selectors should still expand.
            return $this->normaliseSelectors($body);
        }

        $targetSource = is_string($body['target'] ?? null)
            ? $body['target']
            : ($body['target']['source'] ?? $body['target']['id'] ?? null);
        if (! $targetSource) {
            return $this->normaliseSelectors($body);
        }

        // Preserve any existing selector entries (e.g. FragmentSelector
        // from older saves) and append our SvgSelector.
        $existing = (is_array($body['target'] ?? null) && isset($body['target']['selector']))
            ? $body['target']['selector']
            : [];
        $existingArr = is_array($existing) && array_is_list($existing) ? $existing : ($existing ? [$existing] : []);

        $hasSvg = false;
        foreach ($existingArr as $sel) {
            if (is_array($sel) && ($sel['type'] ?? null) === 'SvgSelector' && ! empty($sel['value'])) {
                $hasSvg = true;
                break;
            }
        }
        if (! $hasSvg) {
            $existingArr[] = ['type' => 'SvgSelector', 'value' => $svg];
        }

        $body['target'] = [
            'source' => $targetSource,
            'selector' => count($existingArr) === 1 ? $existingArr[0] : $existingArr,
        ];

        return $this->normaliseSelectors($body);
    }

    /**
     * Round-trip the W3C selectors we explicitly support. The shape stays
     * exactly as the client sent it; this pass exists to recognise unknown
     * spec-valid types in tests and to attach implicit conformsTo links to
     * the spec context where useful.
     */
    private function normaliseSelectors(array $body): array
    {
        $target = $body['target'] ?? null;
        if (is_array($target) && isset($target['selector'])) {
            $body['target']['selector'] = $this->tagSelectorRecursive($target['selector']);
        }

        // Body-side SpecificResource selectors (e.g. annotate the second
        // paragraph of an external URL).
        $bodies = $body['body'] ?? null;
        if (is_array($bodies)) {
            if (array_is_list($bodies)) {
                foreach ($bodies as $i => $b) {
                    if (is_array($b) && isset($b['selector'])) {
                        $body['body'][$i]['selector'] = $this->tagSelectorRecursive($b['selector']);
                    }
                }
            } elseif (isset($bodies['selector'])) {
                $body['body']['selector'] = $this->tagSelectorRecursive($bodies['selector']);
            }
        }

        return $body;
    }

    private function tagSelectorRecursive(mixed $selector): mixed
    {
        if (! is_array($selector)) {
            return $selector;
        }
        if (array_is_list($selector)) {
            return array_map(fn ($s) => $this->tagSelectorRecursive($s), $selector);
        }
        $type = $selector['type'] ?? null;
        if ($type && in_array($type, self::KNOWN_SELECTOR_TYPES, true)) {
            // Spec compliance: ensure conformsTo is populated where the
            // selector type implies a specific external standard. We never
            // overwrite a client-supplied conformsTo.
            if ($type === 'MediaFragmentSelector' && empty($selector['conformsTo'])) {
                $selector['conformsTo'] = 'http://www.w3.org/TR/media-frags/';
            }
            if ($type === 'TimeSelector' && empty($selector['conformsTo'])) {
                // TimeSelector is itself a W3C Web Annotation concept that
                // typically delegates `t=...` syntax to RFC 7826 (NPT) or
                // Media Fragments. We tag the W3C Web Annotation context
                // explicitly so spec-validating clients don't need to guess.
                $selector['conformsTo'] = 'http://www.w3.org/TR/annotation-model/';
            }
            if ($type === 'GeoSelector' && empty($selector['conformsTo'])) {
                $selector['conformsTo'] = 'http://www.opengis.net/doc/IS/wkt-crs/1.0';
            }
        }
        // Nested refinedBy selectors get the same treatment.
        if (isset($selector['refinedBy'])) {
            $selector['refinedBy'] = $this->tagSelectorRecursive($selector['refinedBy']);
        }

        return $selector;
    }

    public function store(Request $request): JsonResponse
    {
        // Return JSON 401 instead of redirecting unauthenticated callers to
        // /login (the auth.required group does that by default and the
        // mirador-annotation-editor's adapter chokes on HTML responses).
        if (! Auth::check()) {
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

        // Ensure schema exists. Defensive - the package install.sql runs on
        // first boot via the service provider, but a fresh deployment that
        // hasn't booted the provider yet would 500 instead of returning a
        // tidy error.
        if (! Schema::hasTable('ahg_iiif_annotation')) {
            return response()->json(['error' => 'Annotations storage not initialised'], 503);
        }

        // Annotot+Mirador set body.id to the eventual server URL after
        // create. We'll patch it in the saved JSON to point at our route
        // before storing, so re-fetches round-trip without rewriting.
        $body['id'] = url('/api/annotations/'.$uuid);

        $projectId = $request->query('projectId') ?: ($body['_heratio']['project_id'] ?? null);
        $visibility = $request->query('visibility') ?: ($body['_heratio']['visibility'] ?? 'private');
        if (! in_array($visibility, ['private', 'project', 'public'], true)) {
            $visibility = 'private';
        }

        $now = now();
        $bodySelectorJson = $this->extractBodySelectorJson($body);
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        $etag = sha1($bodyJson.'|'.$now->toIso8601String());

        DB::table('ahg_iiif_annotation')->insert([
            'uuid' => $uuid,
            'target_iri' => $targetIri,
            'information_object_id' => $this->resolveIoIdFromTarget($targetIri),
            'project_id' => $projectId ? (int) $projectId : null,
            'visibility' => $visibility,
            'body_json' => $bodyJson,
            'body_selector_json' => $bodySelectorJson,
            'etag' => $etag,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = response()->json($body, 201);
        $response->headers->set('Location', url('/api/annotations/'.$uuid));
        $response->headers->set('ETag', '"'.$etag.'"');

        return $response;
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required to save annotations.'], 401);
        }
        $row = DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->first();
        if (! $row) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // SECURITY (#1365): ownership gate. Auth::check() alone let any
        // authenticated user overwrite anyone else's annotation (IDOR).
        // Only the creator (or an admin/editor) may mutate a row.
        if (! $this->canWriteRow($row)) {
            return response()->json(['error' => 'You do not have permission to modify this annotation.'], 403);
        }

        // Optimistic-concurrency precondition. If the client sent If-Match
        // and the current row's etag differs, 412 Precondition Failed.
        $ifMatch = $request->header('If-Match');
        if ($ifMatch !== null && $ifMatch !== '') {
            $currentEtag = $this->etagFor($row);
            if (! $this->etagsMatch($ifMatch, $currentEtag)) {
                $response = response()->json([
                    'error' => 'Precondition failed: ETag mismatch.',
                    'currentEtag' => '"'.$currentEtag.'"',
                ], 412);
                $response->headers->set('ETag', '"'.$currentEtag.'"');

                return $response;
            }
        }

        $body = $request->json()->all();
        if (empty($body)) {
            return response()->json(['error' => 'Empty body'], 422);
        }

        // Allow target.id change (rare, but legitimate for cross-canvas moves).
        $targetIri = $this->extractTargetIri($body) ?: $row->target_iri;
        $body['id'] = url('/api/annotations/'.$uuid);

        $now = now();
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        $bodySelectorJson = $this->extractBodySelectorJson($body);
        $etag = sha1($bodyJson.'|'.$now->toIso8601String());

        DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->update([
            'target_iri' => $targetIri,
            'information_object_id' => $this->resolveIoIdFromTarget($targetIri) ?? $row->information_object_id,
            'body_json' => $bodyJson,
            'body_selector_json' => $bodySelectorJson,
            'etag' => $etag,
            'updated_by' => Auth::id(),
            'updated_at' => $now,
        ]);

        $response = response()->json($body);
        $response->headers->set('ETag', '"'.$etag.'"');

        return $response;
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required to save annotations.'], 401);
        }
        $row = DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->first();
        if (! $row) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // SECURITY (#1365): ownership gate (see update()). Only the creator
        // or an admin/editor may delete a row.
        if (! $this->canWriteRow($row)) {
            return response()->json(['error' => 'You do not have permission to delete this annotation.'], 403);
        }

        // If-Match on DELETE is the WAP-recommended way to prevent two
        // editors from blowing each other's deletes away.
        $ifMatch = $request->header('If-Match');
        if ($ifMatch !== null && $ifMatch !== '') {
            $currentEtag = $this->etagFor($row);
            if (! $this->etagsMatch($ifMatch, $currentEtag)) {
                $response = response()->json([
                    'error' => 'Precondition failed: ETag mismatch.',
                    'currentEtag' => '"'.$currentEtag.'"',
                ], 412);
                $response->headers->set('ETag', '"'.$currentEtag.'"');

                return $response;
            }
        }

        DB::table('ahg_iiif_annotation')->where('uuid', $uuid)->delete();

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
        if (is_string($t)) {
            return $t;
        }
        if (is_array($t)) {
            // Single target as object
            if (isset($t['id']) && is_string($t['id'])) {
                return $t['id'];
            }
            if (isset($t['source']) && is_string($t['source'])) {
                return $t['source'];
            }
            // Array of targets: take the first parseable
            foreach ($t as $entry) {
                if (is_string($entry)) {
                    return $entry;
                }
                if (is_array($entry)) {
                    if (isset($entry['id']) && is_string($entry['id'])) {
                        return $entry['id'];
                    }
                    if (isset($entry['source']) && is_string($entry['source'])) {
                        return $entry['source'];
                    }
                }
            }
        }

        return '';
    }

    /**
     * If the annotation's body is a SpecificResource (or list containing
     * one) with its own selector, return the selector(s) as a JSON string
     * for storage in body_selector_json. Returns null when no body-side
     * selector is present.
     *
     * Storing this lets future phases (and admin tooling) filter or
     * faceted-search annotations by their body's quoted text without
     * having to JSON_EXTRACT into body_json on every query.
     */
    private function extractBodySelectorJson(array $annotation): ?string
    {
        $body = $annotation['body'] ?? null;
        if ($body === null) {
            return null;
        }

        $selectors = [];
        $candidates = (is_array($body) && array_is_list($body)) ? $body : [$body];
        foreach ($candidates as $b) {
            if (! is_array($b)) {
                continue;
            }
            $type = $b['type'] ?? null;
            if ($type !== 'SpecificResource') {
                continue;
            }
            if (! isset($b['selector'])) {
                continue;
            }
            $selectors[] = [
                'source' => $b['source'] ?? null,
                'selector' => $b['selector'],
            ];
        }

        if (empty($selectors)) {
            return null;
        }

        return json_encode(count($selectors) === 1 ? $selectors[0] : $selectors, JSON_UNESCAPED_SLASHES);
    }

    /**
     * SECURITY (#1365) — read visibility model, applied identically by
     * search() (as a query scope) and show() (as a per-row check via
     * canReadRow()).
     *
     * Rules:
     *   - Admin / editor (AclService::canAdmin) — see everything.
     *   - Anonymous (! Auth::check()) — only visibility='public'.
     *   - Authenticated non-admin — visibility='public' OR own rows
     *     (created_by = Auth::id()).
     *
     * NOTE on 'project' visibility: ahg-annotations does not (today) have a
     * clean, authoritative way to resolve a row's project_id to a set of
     * permitted users. ahg-research keys project membership on
     * research_project_collaborator.researcher_id (research_researcher.id),
     * NOT directly on the auth user id, and there is no documented contract
     * that an annotation's project_id references research_project.id — the
     * value arrives unvalidated from the viewer's ?projectId= query param.
     * Wiring it would also add a cross-package dependency
     * (ahg-annotations -> ahg-research) this package does not currently
     * declare. Rather than risk leaking project rows on a guessed join, we
     * implement the safe subset (public + own + admin) and exclude project
     * rows from non-admin, non-owner callers.
     *
     * TODO(#1365): include project-member annotations once a trustworthy
     * project-membership lookup (auth-user -> researcher -> accepted
     * collaborator on annotation.project_id) is wired and the project_id ->
     * research_project linkage is validated.
     */
    private function applyReadVisibilityScope($query): void
    {
        $userId = Auth::id();

        if (AclService::canAdmin($userId)) {
            return; // admins/editors see all
        }

        if (! Auth::check()) {
            $query->where('visibility', 'public');

            return;
        }

        // Authenticated non-admin: public rows OR rows they authored.
        $query->where(function ($q) use ($userId) {
            $q->where('visibility', 'public')
                ->orWhere('created_by', $userId);
        });
    }

    /**
     * SECURITY (#1365) — single-row mirror of applyReadVisibilityScope()
     * for show(). Returns true when the current caller may read $row.
     */
    private function canReadRow(object $row): bool
    {
        $userId = Auth::id();

        if (AclService::canAdmin($userId)) {
            return true;
        }

        if (($row->visibility ?? 'private') === 'public') {
            return true;
        }

        // Own rows are always readable to the authenticated owner.
        // 'project' rows are deferred (see applyReadVisibilityScope()).
        return Auth::check() && (int) ($row->created_by ?? 0) === (int) $userId;
    }

    /**
     * SECURITY (#1365) — write ownership gate for update()/destroy().
     * Only the row's creator or an admin/editor may mutate it. Callers
     * have already enforced Auth::check() before reaching this.
     */
    private function canWriteRow(object $row): bool
    {
        $userId = Auth::id();

        if (AclService::canAdmin($userId)) {
            return true;
        }

        return (int) ($row->created_by ?? 0) === (int) $userId;
    }

    /**
     * Resolve an annotation target IRI to its information_object.id.
     *
     * Local canvas/manifest IRIs are built by the IIIF manifest service as
     *   {baseUrl}/iiif-manifest/{slug}[/canvas/{n}[/...]]
     * (IiifCollectionService::buildSingleCanvasV3 + routes/web.php), so the
     * IO slug is the path segment immediately after `/iiif-manifest/`. We
     * lift it, strip any selector fragment / query string, URL-decode it,
     * and round-trip through the `slug` table to the object id.
     *
     * Returns null when the IRI is not a local manifest IRI (remote IIIF
     * servers, legacy shapes) or the slug doesn't resolve — callers MUST
     * fail safe (treat null as "not published" for anonymous readers).
     */
    private function resolveIoIdFromTarget(string $iri): ?int
    {
        if ($iri === '') {
            return null;
        }

        // Drop selector fragment (#xywh=...) and query string before matching.
        $path = parse_url($iri, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $iri;
        }

        // NB: delimiter is ~ (not #) — the pattern's char class contains a
        // literal '#', which would otherwise be read as the closing delimiter
        // and throw "preg_match(): Unknown modifier ']'" (500 on anon search).
        if (! preg_match('~/iiif-manifest/([^/?#]+)~', $path, $m)) {
            return null;
        }

        $slug = rawurldecode($m[1]);
        if ($slug === '') {
            return null;
        }

        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');

        return $objectId ? (int) $objectId : null;
    }

    /**
     * #1365 object-state gate: is the IO behind this annotation target
     * published (status type_id=158 => status_id=160)? Used to hide
     * annotations on unpublished IOs from anonymous readers. Unresolvable
     * targets fail safe (return false → not visible to anon).
     */
    private function targetIoIsPublishedForAnon(?string $iri): bool
    {
        $ioId = ($iri !== null && $iri !== '') ? $this->resolveIoIdFromTarget($iri) : null;
        if ($ioId === null) {
            return false;
        }

        return DB::table('status')
            ->where('object_id', $ioId)
            ->where('type_id', 158)
            ->where('status_id', 160)
            ->exists();
    }

    /**
     * Compute the ETag for a stored row. Prefers the stored etag column
     * (set on insert/update) and falls back to a hash of body_json +
     * updated_at for rows created before the etag column landed.
     */
    private function etagFor(object $row): string
    {
        if (! empty($row->etag)) {
            return $row->etag;
        }

        return sha1(($row->body_json ?? '').'|'.($row->updated_at ?? ''));
    }

    /**
     * Compare an HTTP If-Match / If-None-Match header value against a
     * computed etag. Header values are quoted ("..." or W/"..."); we
     * normalise both sides before comparing. A header of "*" matches
     * any existing resource per RFC 7232 §3.1 / §3.2.
     */
    private function etagsMatch(string $headerValue, string $etag): bool
    {
        $headerValue = trim($headerValue);
        if ($headerValue === '*') {
            return true;
        }
        // Allow a list of etags separated by commas (RFC 7232 §3.1).
        foreach (explode(',', $headerValue) as $candidate) {
            $candidate = trim($candidate);
            // Strip the weak-validator W/ prefix; we treat strong and weak
            // matches identically because our etag is content-hash based.
            $candidate = preg_replace('/^W\//i', '', $candidate);
            $candidate = trim($candidate, '"');
            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the AnnotationContainer / AnnotationPage envelope returned by
     * /api/annotations/search. We keep the Annotot-shaped `resources`
     * field for the legacy Mirador adapter AND emit the W3C-spec
     * AnnotationPage fields side-by-side so WAP-conformant clients see a
     * valid container.
     */
    private function wrapContainer(string $targetId, array $resources, bool $irisOnly): array
    {
        $containerId = url('/api/annotations/search?targetId='.urlencode($targetId));
        $pageId = url('/api/annotations/page?targetId='.urlencode($targetId));

        // In iris-only mode, `$resources` is already a list of bare IRI
        // strings (built up in search()). Otherwise it's a list of full
        // annotation envelopes. The W3C AnnotationPage `items` array
        // accepts either shape; the Annotot-shaped `resources` mirror
        // below also accepts either for the existing HeratioAnnotationAdapter
        // in tools/mirador-build (which currently never sets Prefer, so it
        // continues to receive the full envelopes).
        return [
            // W3C Web Annotation context - clients keying off @context can
            // confirm they're looking at an annotation container.
            '@context' => [
                'http://www.w3.org/ns/anno.jsonld',
                'http://iiif.io/api/presentation/3/context.json',
            ],
            'id' => $containerId,
            'type' => ['BasicContainer', 'AnnotationCollection'],
            'total' => count($resources),
            'first' => [
                'id' => $pageId,
                'type' => 'AnnotationPage',
                'partOf' => $containerId,
                'items' => $resources,
            ],
            // Annotot-shaped echo for the existing HeratioAnnotationAdapter
            // in tools/mirador-build - keep this until clients have moved
            // over to the W3C AnnotationPage shape above.
            'resources' => $resources,
        ];
    }

    private function emptyContainer(): JsonResponse
    {
        // Mirador's HeratioAnnotationAdapter reads `resources` directly,
        // so the container shape must include it even on empty fetches.
        return response()->json([
            '@context' => [
                'http://www.w3.org/ns/anno.jsonld',
                'http://iiif.io/api/presentation/3/context.json',
            ],
            'type' => ['BasicContainer', 'AnnotationCollection'],
            'total' => 0,
            'resources' => [],
        ]);
    }
}
