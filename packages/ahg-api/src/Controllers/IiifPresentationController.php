<?php

/**
 * IiifPresentationController - a IIIF Presentation API 3.0 Manifest per record.
 *
 * A self-contained, standards-clean PRESENTATION manifest for ONE published
 * archival record, so any IIIF viewer (Mirador, Universal Viewer) can open the
 * record's images and any IIIF aggregator / harvester can ingest it:
 *
 *   GET /iiif-presentation/{idOrSlug}/manifest.json
 *       - a valid IIIF Presentation 3.0 Manifest (application/ld+json),
 *         CORS-open, for a single PUBLISHED record. One Canvas per image
 *         digital object, each Canvas -> AnnotationPage -> Annotation
 *         (motivation: painting) -> Image body with a IIIF Image API 3.0
 *         `service` block pointing at the Cantaloupe image service for the
 *         file, plus a thumbnail.
 *
 * This is the PRESENTATION side only. It does NOT touch the locked Image API
 * delegate (ahg-core IiifController / Cantaloupe delegates.rb); it merely
 * references the Image API service URLs the deployed viewer already uses.
 *
 * Resolution + gate REUSED, not reinvented: resolve() is the same slug ->
 * information_object join + published-only gate as
 * AhgApi\Controllers\EntityController::loadNode() and
 * AhgApi\Controllers\CitationController::resolve()
 * (status.type_id=158, status_id=160 = Published; synthetic root id=1 excluded;
 * a numeric token is accepted as the information_object id; a schema variance
 * yields null, not an exception). An unknown / unpublished / root record yields
 * a CLEAN 404 JSON - never a 500, never a leak of a draft.
 *
 * Cantaloupe identifier construction REUSED, not invented: the IIIF Image API
 * identifier is built exactly as the existing (locked) IiifCollectionService and
 * the deployed viewer (ahg-iiif-viewer.js / docs/cantaloupe-iiif-setup.md) build
 * it - the file path relative to uploads with '/' replaced by the '_SL_'
 * path separator, with the filename appended:
 *
 *     $id = str_replace('/', '_SL_', ltrim($do->path, '/')) . $do->name;
 *
 * served under the '/iiif/3/' prefix (IIIF Image API 3.0, hostname-based path
 * resolution in the Cantaloupe delegate).
 *
 * Public IIIF base is DERIVED, never hardcoded: the image `service` id uses the
 * SAME public origin the viewer uses (url('/'), which follows the request host,
 * exactly like the viewer's location.origin). A fresh install on its own domain
 * therefore emits its own IIIF service URLs with no private host leaking out.
 *
 * Empty-but-valid: a published record with NO image digital objects yields a
 * valid Manifest with `items: []` (never a 500), so a harvester always gets a
 * well-formed document.
 *
 * CATCH-ALL SAFETY: the route is MULTI-SEGMENT and ends in the literal
 * "/manifest.json" ("/iiif-presentation/{idOrSlug}/manifest.json"). The
 * single-segment /{slug} archival-record catch-all (ahg-information-object-
 * manage, constraint '[a-z0-9][a-z0-9-]*$' - ONE segment, no slash) can never
 * capture a multi-segment path, so a normal record slug still resolves. The
 * {idOrSlug} matcher allows the slug grammar (including multi-segment slugs),
 * with the trailing "/manifest.json" pinned as a literal.
 *
 * Safe + neutral: read-only (SELECT only; no writes, no DDL, no new table);
 * permissive open CORS (any viewer / aggregator may fetch it); every emitted
 * label / metadata value is a plain JSON string (JSON encoding neutralises any
 * hostile title). IIIF Presentation 3.0 is an international standard; no
 * jurisdiction or locale assumptions.
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

namespace AhgApi\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use AhgApi\Concerns\ResolvesEventMeta;
use AhgApi\Concerns\ResolvesTermName;

class IiifPresentationController extends Controller
{
    use ResolvesTermName;

    use ResolvesEventMeta;

    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    /** Sane Canvas fallback dimensions when a file exposes none. */
    private const DEFAULT_WIDTH = 1000;

    private const DEFAULT_HEIGHT = 1000;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    /**
     * OPTIONS preflight for the manifest endpoint (CORS-open).
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /iiif-presentation/{idOrSlug}/manifest.json
     *
     * A valid IIIF Presentation 3.0 Manifest for one published record. An
     * unknown / unpublished / root record yields a clean 404 JSON; a published
     * record with no images yields a valid Manifest with empty items.
     */
    public function manifest(Request $request, string $idOrSlug): Response
    {
        $rec = $this->resolve($idOrSlug);
        if ($rec === null) {
            return $this->notFound($idOrSlug);
        }

        $manifest = $this->buildManifest($rec);

        return $this->withCors(response(
            (string) json_encode(
                $manifest,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ),
            200,
            ['Content-Type' => 'application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"; charset=utf-8']
        ));
    }

    // -----------------------------------------------------------------
    // Manifest assembly (IIIF Presentation 3.0)
    // -----------------------------------------------------------------

    /**
     * Build the full Presentation 3.0 Manifest array for a resolved record.
     *
     * @param  array<string,mixed>  $rec
     * @return array<string,mixed>
     */
    protected function buildManifest(array $rec): array
    {
        $manifestUrl = $this->manifestUrl($rec['slug']);
        $recordUrl = $this->recordPublicUrl($rec['slug']);
        $label = $rec['title'] !== '' ? $rec['title'] : '[Untitled]';

        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $manifestUrl,
            'type' => 'Manifest',
            'label' => $this->languageMap($label),
        ];

        // summary <- scope and content, when present.
        if ($rec['scope'] !== '') {
            $manifest['summary'] = $this->languageMap($rec['scope']);
        }

        // metadata <- a few safe, neutral ISAD fields (only when present).
        $metadata = $this->metadataPairs($rec);
        if (! empty($metadata)) {
            $manifest['metadata'] = $metadata;
        }

        // rights + requiredStatement: attribution to the holding repository.
        if ($rec['rights_uri'] !== '') {
            $manifest['rights'] = $rec['rights_uri'];
        }
        $attribution = $rec['publisher'] !== ''
            ? 'Held by '.$rec['publisher']
            : 'Provided by '.(string) config('app.name', 'Heratio');
        $manifest['requiredStatement'] = [
            'label' => $this->languageMap('Attribution'),
            'value' => $this->languageMap($attribution),
        ];

        // provider: the publishing institution.
        $providerName = $rec['publisher'] !== ''
            ? $rec['publisher']
            : (string) config('app.name', 'Heratio');
        $manifest['provider'] = [[
            'id' => $this->base().'/',
            'type' => 'Agent',
            'label' => $this->languageMap($providerName),
            'homepage' => [[
                'id' => $this->base().'/',
                'type' => 'Text',
                'label' => $this->languageMap($providerName),
                'format' => 'text/html',
            ]],
        ]];

        // homepage: a deep link back to the human record page.
        $manifest['homepage'] = [[
            'id' => $recordUrl,
            'type' => 'Text',
            'label' => $this->languageMap($label),
            'format' => 'text/html',
        ]];

        // items: one Canvas per image digital object (possibly empty).
        $canvases = $this->buildCanvases($rec, $manifestUrl);
        $manifest['items'] = $canvases;

        // Show one image at a time (individuals) rather than a paged book-spread,
        // even for multi-canvas manifests - matches how the Mirador gallery is
        // used here (ported from the artorius production change, 2026-07-16).
        $manifest['behavior'] = ['individuals'];

        // A first-canvas thumbnail for the whole manifest, when we have images.
        if (! empty($canvases)) {
            $painting = $canvases[0]['items'][0]['items'][0]['body'] ?? null;
            if (is_array($painting) && isset($painting['service'][0]['id'])) {
                $manifest['thumbnail'] = [[
                    'id' => $painting['service'][0]['id'].'/full/!200,200/0/default.jpg',
                    'type' => 'Image',
                    'format' => 'image/jpeg',
                    'service' => $painting['service'],
                ]];
            }
        }

        return $manifest;
    }

    /**
     * Build the Canvas list for the record's IMAGE digital objects. Each Canvas
     * carries one AnnotationPage -> one painting Annotation -> an Image body with
     * a IIIF Image API 3.0 service block. Returns [] when there are no images.
     *
     * @param  array<string,mixed>  $rec
     * @return array<int,array<string,mixed>>
     */
    protected function buildCanvases(array $rec, string $manifestUrl): array
    {
        $images = $this->imageDigitalObjects($rec['id']);
        $canvases = [];
        $index = 1;

        foreach ($images as $do) {
            $imagePath = ltrim((string) $do->path, '/');
            if ($imagePath === '' && (string) $do->name === '') {
                continue;
            }

            // heratio#1396: an encrypted-at-rest master (Heratio's own envelope
            // or the foreign AHG-ENC-V2 one) is ciphertext on disk - Cantaloupe
            // cannot identify a source format and every Image API request for
            // it 501s ("Unsupported source format"). Skip the Canvas so the
            // manifest never points a viewer / aggregator at a guaranteed-dead
            // image service. Detection failure fails OPEN (canvas kept) so a
            // transient filesystem error cannot hide servable images.
            try {
                $disk = \AhgCore\Services\DigitalObjectService::resolveDiskPath($do);
                if ($disk !== null
                    && app(\AhgCore\Services\EncryptionService::class)->isFileEncryptedAtRest($disk)) {
                    continue;
                }
            } catch (\Throwable $e) {
                // Fail-open: keep the canvas.
            }

            // SAME identifier construction as the deployed viewer and the
            // (locked) IiifCollectionService: file path relative to uploads,
            // '/' -> '_SL_', then the filename appended.
            $iiifId = str_replace('/', '_SL_', $imagePath).$do->name;

            $serviceId = $this->iiifBase().'/iiif/3/'.$iiifId;

            // Dimensions: prefer stored width/height; fall back to probing the
            // master file (uploads are not guaranteed to carry width/height, so
            // without this the manifest emits DEFAULT_WIDTH/HEIGHT and Mirador
            // mis-sizes the canvas). Adapted from the artorius production fix to
            // Heratio's uploads/r/<id>/ layout, with a path-traversal guard.
            $width = (int) ($do->width ?? 0);
            $height = (int) ($do->height ?? 0);
            if ($width <= 0 || $height <= 0) {
                $probed = $this->localImageDimensions($do);
                if ($probed !== null) {
                    [$width, $height] = $probed;
                }
            }
            $width = $width ?: self::DEFAULT_WIDTH;
            $height = $height ?: self::DEFAULT_HEIGHT;

            $canvasId = $manifestUrl.'/canvas/'.$index;
            $pageId = $canvasId.'/page/1';
            $annId = $canvasId.'/annotation/1';
            $label = ($do->name !== null && $do->name !== '')
                ? (string) $do->name
                : 'Image '.$index;

            $canvases[] = [
                'id' => $canvasId,
                'type' => 'Canvas',
                'label' => $this->languageMap($label),
                'height' => $height,
                'width' => $width,
                'thumbnail' => [[
                    'id' => $serviceId.'/full/!200,200/0/default.jpg',
                    'type' => 'Image',
                    'format' => 'image/jpeg',
                    'service' => [[
                        'id' => $serviceId,
                        'type' => 'ImageService3',
                        'profile' => 'level2',
                    ]],
                ]],
                'items' => [[
                    'id' => $pageId,
                    'type' => 'AnnotationPage',
                    'items' => [[
                        'id' => $annId,
                        'type' => 'Annotation',
                        'motivation' => 'painting',
                        'target' => $canvasId,
                        'body' => [
                            'id' => $serviceId.'/full/max/0/default.jpg',
                            'type' => 'Image',
                            'format' => 'image/jpeg',
                            'height' => $height,
                            'width' => $width,
                            'service' => [[
                                'id' => $serviceId,
                                'type' => 'ImageService3',
                                'profile' => 'level2',
                            ]],
                        ],
                    ]],
                ]],
            ];
            $index++;
        }

        return $canvases;
    }

    /**
     * A few safe, neutral ISAD metadata pairs (each emitted only when present),
     * as IIIF language-mapped label/value pairs.
     *
     * @param  array<string,mixed>  $rec
     * @return array<int,array<string,mixed>>
     */
    protected function metadataPairs(array $rec): array
    {
        $pairs = [];
        $add = function (string $label, string $value) use (&$pairs) {
            $value = trim($value);
            if ($value !== '') {
                $pairs[] = [
                    'label' => $this->languageMap($label),
                    'value' => $this->languageMap($value),
                ];
            }
        };

        $add('Reference code', (string) $rec['identifier']);
        $add('Dates', (string) $rec['date']);
        $add('Level of description', (string) ($rec['level'] ?? ''));
        $add('Repository', (string) $rec['publisher']);

        return $pairs;
    }

    // -----------------------------------------------------------------
    // Resolution + publication-status gate (REUSED from EntityController)
    // -----------------------------------------------------------------

    /**
     * Resolve an id-or-slug to its published record, enforcing the SAME
     * published-only gate as EntityController / CitationController. A purely
     * numeric token is treated as the information_object id; anything else is a
     * slug. Returns null for an unknown OR unpublished record (never leaks a
     * draft), and never throws (a schema variance yields null).
     *
     * @return array<string,mixed>|null
     */
    protected function resolve(string $idOrSlug): ?array
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('slug')) {
                return null;
            }

            $query = DB::table('information_object as io')
                ->join('slug as s', 's.object_id', '=', 'io.id')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
                })
                ->leftJoin('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION);
                })
                ->where('io.id', '!=', self::ROOT_ID);

            // Numeric token -> the information_object id; otherwise a slug.
            if (ctype_digit($idOrSlug)) {
                $query->where('io.id', (int) $idOrSlug);
            } else {
                $query->where('s.slug', $idOrSlug);
            }

            $row = $query->select(
                'io.id',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                's.slug',
                'i18n.title',
                'i18n.scope_and_content',
                'i18n.reproduction_conditions',
                'st.status_id'
            )->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row) {
            return null;
        }

        // Published-only gate, matching the rest of the public v1 API.
        if ((int) $row->status_id !== self::STATUS_PUBLISHED) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'slug' => (string) $row->slug,
            'identifier' => $row->identifier !== null ? (string) $row->identifier : '',
            'title' => ($row->title !== null && $row->title !== '') ? (string) $row->title : '',
            'scope' => $row->scope_and_content !== null ? trim((string) $row->scope_and_content) : '',
            'level' => $this->termName($row->level_of_description_id),
            'date' => $this->primaryDate((int) $row->id),
            'publisher' => $this->publisher($row->repository_id),
            'rights_uri' => $this->rightsUri($row->reproduction_conditions),
        ];
    }

    /**
     * The IMAGE master digital objects for a record. Master rows carry the IO id
     * in object_id (derivatives carry parent_id instead, so this naturally
     * returns one row per uploaded image). Only image MIME types / extensions
     * are kept - audio / video / PDF do not route through the Image API.
     * Dimensions come from the digital_object's property sidecar when present
     * (best-effort; absent -> Canvas defaults).
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function imageDigitalObjects(int $objectId)
    {
        try {
            $rows = DB::table('digital_object as do')
                ->where('do.object_id', $objectId)
                ->orderByRaw('COALESCE(do.sequence, 0)')
                ->orderBy('do.id')
                ->select('do.id', 'do.name', 'do.path', 'do.mime_type')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }

        $images = $rows->filter(function ($do) {
            return $this->isImage((string) ($do->mime_type ?? ''), (string) ($do->name ?? ''));
        })->values();

        // Best-effort native dimensions from the property sidecar (AtoM stores
        // width / height as object properties on the digital_object). Absent ->
        // null, and the Canvas falls back to sane defaults.
        foreach ($images as $do) {
            $do->width = $this->property((int) $do->id, 'width');
            $do->height = $this->property((int) $do->id, 'height');
        }

        return $images;
    }

    /**
     * Native [width, height] of a digital object's master, read from the file on
     * disk when the stored dimensions are absent. Resolves the physical path in
     * Heratio's uploads/r/<id>/ layout (with legacy fallbacks) and guards against
     * path traversal - the resolved file must live under the uploads root.
     * Returns null when it cannot be probed.
     *
     * @return array{0:int,1:int}|null
     */
    protected function localImageDimensions(object $do): ?array
    {
        $uploads = rtrim((string) config('heratio.uploads_path', ''), '/');
        $name = (string) ($do->name ?? '');
        if ($uploads === '' || $name === '') {
            return null;
        }

        $candidates = [];
        if (! empty($do->object_id)) {
            $candidates[] = $uploads.'/r/'.((int) $do->object_id).'/'.$name;
        }
        $rel = ltrim((string) ($do->path ?? ''), '/');
        foreach (['uploads/', ''] as $prefix) {
            if ($prefix === '' || str_starts_with($rel, $prefix)) {
                $candidates[] = $uploads.'/'.substr($rel, strlen($prefix)).$name;
            }
        }

        $rootReal = realpath($uploads);
        foreach (array_unique($candidates) as $candidate) {
            $real = realpath($candidate);
            if (! $real || ! $rootReal || ! str_starts_with($real, $rootReal) || ! is_file($real)) {
                continue;
            }
            $info = @getimagesize($real);
            if (is_array($info) && (int) ($info[0] ?? 0) > 0 && (int) ($info[1] ?? 0) > 0) {
                return [(int) $info[0], (int) $info[1]];
            }
        }

        return null;
    }

    /**
     * A single integer-valued digital_object property (e.g. width / height) from
     * the property / property_i18n sidecar. Best-effort; null when absent or on
     * any schema variance.
     */
    protected function property(int $digitalObjectId, string $name): ?int
    {
        try {
            if (! Schema::hasTable('property') || ! Schema::hasTable('property_i18n')) {
                return null;
            }

            $value = DB::table('property as p')
                ->join('property_i18n as pi', function ($j) {
                    $j->on('p.id', '=', 'pi.id')->where('pi.culture', $this->culture);
                })
                ->where('p.object_id', $digitalObjectId)
                ->where('p.name', $name)
                ->value('pi.value');

            if ($value === null || ! is_numeric($value)) {
                return null;
            }

            $n = (int) $value;

            return $n > 0 ? $n : null;
        } catch (\Throwable $e) {
            return null;
        }
    }


    /**
     * The holding repository's authorised name (the archival "publisher" /
     * attribution). Mirrors CitationController::publisher().
     */
    protected function publisher($repositoryId): string
    {
        if (empty($repositoryId)) {
            return '';
        }

        try {
            $name = DB::table('repository as r')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('r.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('r.id', (int) $repositoryId)
                ->value('ai.authorized_form_of_name');

            return $name ? trim((string) $name) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }


    /**
     * A `rights` value for the Manifest: emitted ONLY when the record's
     * reproduction-conditions field is itself a recognised rights URI (a
     * Creative Commons or RightsStatements.org URI), per the IIIF spec which
     * requires `rights` to be such a URI. Free-text conditions are NOT forced
     * into `rights` (they would be invalid); they remain available via the
     * human record page. '' when there is no qualifying URI.
     */
    protected function rightsUri($reproductionConditions): string
    {
        $text = trim((string) $reproductionConditions);
        if ($text === '') {
            return '';
        }

        if (preg_match('#https?://creativecommons\.org/\S+#i', $text, $m)) {
            return rtrim($m[0], '.,;');
        }
        if (preg_match('#https?://rightsstatements\.org/\S+#i', $text, $m)) {
            return rtrim($m[0], '.,;');
        }

        return '';
    }

    // -----------------------------------------------------------------
    // Small helpers
    // -----------------------------------------------------------------

    /**
     * Whether a digital object is an image (so it routes through the IIIF Image
     * API). MIME-first, with a filename-extension fallback for the formats
     * Cantaloupe serves.
     */
    protected function isImage(string $mime, string $name): bool
    {
        $mime = strtolower(trim($mime));
        if ($mime !== '') {
            return str_starts_with($mime, 'image/');
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|tiff?|jp2|jpx|bmp|webp)$/i', $name);
    }


    /**
     * An IIIF Presentation 3.0 language map: { "<culture>": ["<value>"] }, using
     * the active culture (BCP-47-ish two-letter), falling back to "none" when
     * the culture is empty.
     *
     * @return array<string,array<int,string>>
     */
    protected function languageMap(string $value): array
    {
        $lang = $this->culture !== '' ? $this->culture : 'none';

        return [$lang => [$value]];
    }

    /**
     * The canonical public origin (no trailing slash), derived from url() so no
     * host is hardcoded - it follows the request host like the rest of the
     * open-data surfaces.
     */
    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * The PUBLIC IIIF Image API base. Derived from the SAME public origin the
     * deployed viewer uses (the viewer builds its Image API URLs from
     * location.origin + '/iiif/3/'), so the manifest's image `service` ids and
     * the viewer agree, and nothing private is hardcoded.
     */
    protected function iiifBase(): string
    {
        return $this->base();
    }

    protected function manifestUrl(string $slug): string
    {
        return $this->base().'/iiif-presentation/'.ltrim($slug, '/').'/manifest.json';
    }

    protected function recordPublicUrl(string $slug): string
    {
        return $this->base().'/'.ltrim($slug, '/');
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    /**
     * A clean 404 for an unknown / unpublished / root record: a well-formed JSON
     * error with open CORS, never a 500, never a draft leak.
     */
    protected function notFound(string $idOrSlug): Response
    {
        $safe = trim((string) preg_replace('/[\r\n]+/', ' ', $idOrSlug));

        return $this->withCors(response(
            (string) json_encode([
                'error' => 'Not Found',
                'message' => 'No published record for '.$safe.'.',
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            404,
            ['Content-Type' => 'application/json; charset=utf-8']
        ));
    }

    /**
     * Apply permissive open CORS headers (the manifest is meant to be fetched by
     * any IIIF viewer / aggregator from any origin).
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Vary', 'Accept');
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }
}
