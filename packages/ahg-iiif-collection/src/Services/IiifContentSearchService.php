<?php
/**
 * Heratio - IIIF Content Search 2.0 service (issue #694).
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgIiifCollection\Services;

use Illuminate\Support\Facades\DB;

/**
 * IiifContentSearchService - executes a Content Search API 2.0 query
 * against the per-canvas OCR text stored in iiif_ocr_text / iiif_ocr_block
 * (populated by the discovery PageIndexService) and returns a W3C
 * AnnotationPage with one annotation per matching block region.
 *
 * Spec: https://iiif.io/api/search/2.0/
 *
 * Data model assumptions:
 *   - iiif_ocr_text(id, digital_object_id, object_id, full_text, language)
 *     holds one row per digital_object (a page or multi-page TIFF).
 *     full_text is FULLTEXT-indexed (ft_text).
 *   - iiif_ocr_block(id, ocr_id, page_number, text, x, y, width, height,
 *     confidence) holds per-block bounding boxes.
 *   - Canvas index follows the same digital_object ordering used by
 *     IiifCollectionService::generateObjectManifest(): outer foreach
 *     over digital_object rows ordered by id, with multi-page TIFFs
 *     expanded to one canvas per page. We mirror that ordering here
 *     so block.page_number maps to canvas N for multi-page TIFFs, and
 *     single-page digital objects map 1:1 to their canvas slot.
 */
class IiifContentSearchService
{
    /**
     * Bounding box for the empty-result fallback. Matches the
     * placeholder dimensions used in the manifest emitter when no
     * info.json is reachable.
     */
    private const DEFAULT_CANVAS_W = 1000;

    private const DEFAULT_CANVAS_H = 1000;

    /**
     * Hard cap on result count to protect against pathological wildcard
     * queries. AnnotationPage is single-page (Search API 2.0 supports
     * pagination but this initial cut returns one page).
     */
    private const MAX_HITS = 200;

    /**
     * Run a Content Search 2.0 query for a manifest slug. Returns the
     * full AnnotationPage document, or null if the slug doesn't resolve
     * to an information_object.
     *
     * @param string $slug information_object slug (matches the manifest URL)
     * @param string $query the q= search term
     * @param string|null $motivation optional motivation filter (highlighting | commenting | painting)
     * @return array<string,mixed>|null
     */
    public function search(string $slug, string $query, ?string $motivation = null): ?array
    {
        $object = $this->resolveObject($slug);
        if (!$object) {
            return null;
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $manifestId = $baseUrl . '/iiif-manifest/' . $slug;
        $searchPageId = $baseUrl . '/iiif-manifest/' . $slug . '/search?q=' . urlencode($query);

        $envelope = $this->emptyAnnotationPage($searchPageId, $query);

        $term = trim($query);
        if ($term === '') {
            return $envelope;
        }

        // Build the canvas index map first so we can attach correct
        // canvas IRIs to each hit. The map is keyed by digital_object_id
        // and yields ['base' => "manifestId/canvas/N", 'pages' => [pageNum => canvasIndex]].
        $canvasMap = $this->buildCanvasMap((int) $object->id, $manifestId);
        if (empty($canvasMap)) {
            return $envelope;
        }

        // MATCH ... AGAINST in NATURAL LANGUAGE MODE handles multi-word
        // phrases and stop-words correctly. Boolean mode is reserved for
        // an explicit advanced query syntax in a later phase.
        $ocrRows = DB::table('iiif_ocr_text')
            ->where('object_id', $object->id)
            ->whereRaw('MATCH(full_text) AGAINST (? IN NATURAL LANGUAGE MODE)', [$term])
            ->select('id', 'digital_object_id', 'language')
            ->get();

        if ($ocrRows->isEmpty()) {
            // Fallback LIKE for short terms (FULLTEXT skips tokens shorter
            // than innodb_ft_min_token_size, default 3, and stop-words).
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
            $ocrRows = DB::table('iiif_ocr_text')
                ->where('object_id', $object->id)
                ->where('full_text', 'LIKE', $like)
                ->select('id', 'digital_object_id', 'language')
                ->get();
        }

        if ($ocrRows->isEmpty()) {
            return $envelope;
        }

        $ocrIds = $ocrRows->pluck('id')->all();
        $ocrLangByDo = [];
        $ocrToDo = [];
        foreach ($ocrRows as $row) {
            $ocrToDo[(int) $row->id] = (int) $row->digital_object_id;
            $ocrLangByDo[(int) $row->digital_object_id] = $row->language ?: 'en';
        }

        // Pull matching blocks. We match block.text directly with LIKE
        // here because the block table doesn't carry a FULLTEXT index
        // (block text is short - usually one word). The OCR-row FULLTEXT
        // filter above already narrows the work substantially.
        $likeTerm = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        $blocks = DB::table('iiif_ocr_block')
            ->whereIn('ocr_id', $ocrIds)
            ->where('text', 'LIKE', $likeTerm)
            ->orderBy('ocr_id')
            ->orderBy('page_number')
            ->orderBy('block_order')
            ->orderBy('id')
            ->limit(self::MAX_HITS)
            ->select('id', 'ocr_id', 'page_number', 'text', 'x', 'y', 'width', 'height', 'confidence')
            ->get();

        // If no block-level hit was found but the OCR FULLTEXT matched,
        // emit one whole-canvas annotation per matched page so the user
        // at least sees which canvases contain the term. This keeps the
        // service useful when OCR exists as plain full_text without per-
        // word block coordinates.
        $items = [];
        if ($blocks->isEmpty()) {
            foreach ($ocrRows as $row) {
                $doId = (int) $row->digital_object_id;
                $canvasIri = $canvasMap[$doId]['base'] ?? null;
                if (!$canvasIri) {
                    continue;
                }
                $items[] = $this->buildAnnotation(
                    $manifestId,
                    count($items) + 1,
                    $canvasIri,
                    null,
                    $term,
                    $row->language ?: 'en',
                    $motivation
                );
                if (count($items) >= self::MAX_HITS) {
                    break;
                }
            }
        } else {
            $hitIndex = 0;
            foreach ($blocks as $block) {
                $doId = $ocrToDo[(int) $block->ocr_id] ?? null;
                if ($doId === null || !isset($canvasMap[$doId])) {
                    continue;
                }
                $canvasInfo = $canvasMap[$doId];
                $pageNum = (int) ($block->page_number ?: 1);
                $canvasIri = $canvasInfo['pages'][$pageNum] ?? $canvasInfo['base'];

                $hitIndex++;
                $items[] = $this->buildAnnotation(
                    $manifestId,
                    $hitIndex,
                    $canvasIri,
                    [
                        'x' => (int) $block->x,
                        'y' => (int) $block->y,
                        'w' => (int) $block->width,
                        'h' => (int) $block->height,
                    ],
                    (string) $block->text,
                    $ocrLangByDo[$doId] ?? 'en',
                    $motivation
                );
            }
        }

        $envelope['items'] = $items;
        $envelope['partOf'] = [
            'id' => $manifestId,
            'type' => 'Manifest',
        ];

        return $envelope;
    }

    /**
     * Autocomplete endpoint per Search API 2.0 - returns terms from the
     * stored OCR text that prefix-match the supplied query. Lightweight:
     * we don't keep a separate term index, we sample block.text values.
     *
     * @return array<string,mixed>
     */
    public function autocomplete(string $slug, string $query): ?array
    {
        $object = $this->resolveObject($slug);
        if (!$object) {
            return null;
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $id = $baseUrl . '/iiif-manifest/' . $slug . '/autocomplete?q=' . urlencode($query);

        $term = trim($query);
        $items = [];
        if ($term !== '') {
            $like = str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
            $ocrIds = DB::table('iiif_ocr_text')
                ->where('object_id', $object->id)
                ->pluck('id')
                ->all();

            if (!empty($ocrIds)) {
                $rows = DB::table('iiif_ocr_block')
                    ->whereIn('ocr_id', $ocrIds)
                    ->where('text', 'LIKE', $like)
                    ->groupBy('text')
                    ->orderByRaw('COUNT(*) DESC')
                    ->limit(20)
                    ->select('text', DB::raw('COUNT(*) as hit_count'))
                    ->get();

                foreach ($rows as $row) {
                    $items[] = [
                        'type' => 'TextualBody',
                        'value' => (string) $row->text,
                        'format' => 'text/plain',
                    ];
                }
            }
        }

        return [
            '@context' => 'http://iiif.io/api/search/2/context.json',
            'id' => $id,
            'type' => 'AnnotationCollection',
            'label' => [
                'en' => ['Autocomplete terms for "' . $query . '"'],
            ],
            'items' => $items,
        ];
    }

    /**
     * Returns the SearchService 2.0 service block to attach to a manifest
     * or canvas. The caller (manifest emitter) is responsible for choosing
     * the attachment point - per Search API 2.0 the service block is most
     * commonly placed at the manifest level so all canvases share it.
     *
     * @return array<int,array<string,string>>
     */
    public function buildServiceBlock(string $slug): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $manifestRoot = $baseUrl . '/iiif-manifest/' . $slug;

        return [
            [
                '@id' => $manifestRoot . '/search',
                'id' => $manifestRoot . '/search',
                '@type' => 'SearchService2',
                'type' => 'SearchService2',
                'profile' => 'http://iiif.io/api/search/2/search',
                'service' => [
                    [
                        '@id' => $manifestRoot . '/autocomplete',
                        'id' => $manifestRoot . '/autocomplete',
                        '@type' => 'AutoCompleteService2',
                        'type' => 'AutoCompleteService2',
                        'profile' => 'http://iiif.io/api/search/2/autocomplete',
                    ],
                ],
            ],
        ];
    }

    /**
     * Resolve a manifest slug to its information_object row. Returns an
     * object with at least id, slug; null when nothing matches.
     */
    private function resolveObject(string $slug): ?object
    {
        return DB::table('information_object as io')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->where('s.slug', $slug)
            // Guests can content-search published objects only (status 158/160) —
            // don't leak OCR full-text of unpublished records to anon (#1363).
            ->when(! auth()->check(), fn ($q) => $q->whereExists(function ($s2) {
                $s2->select(DB::raw(1))->from('status as pub_st')
                    ->whereColumn('pub_st.object_id', 'io.id')
                    ->where('pub_st.type_id', 158)->where('pub_st.status_id', 160);
            }))
            ->select('io.id', 's.slug')
            ->first();
    }

    /**
     * Walk the digital_object rows in the same order generateObjectManifest()
     * uses, probing Cantaloupe for multi-page TIFF page counts and assigning
     * each (digital_object, pageNumber) pair a canvas index N. Returns a
     * map keyed by digital_object_id with sub-keys:
     *   - base: canvas IRI for the first page of this DO
     *   - pages: array<int pageNumber, string canvasIri>
     *
     * Multi-page expansion only probes pages 2..MAX_PROBE; we cap at 25 to
     * keep the search call cheap (manifest generation probes up to 100 but
     * runs less often). For our usage this is enough because the block
     * page_number is bounded by what was extracted at indexing time.
     */
    private function buildCanvasMap(int $objectId, string $manifestId): array
    {
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->select('id', 'name', 'path', 'mime_type')
            ->get();

        if ($digitalObjects->isEmpty()) {
            return [];
        }

        $map = [];
        $canvasIndex = 1;
        $cantaloupeBase = 'http://127.0.0.1:8182';
        $maxProbePages = 25;

        foreach ($digitalObjects as $do) {
            $imagePath = ltrim((string) $do->path, '/');
            $cantaloupeId = str_replace('/', '_SL_', $imagePath) . $do->name;

            $mimeType = strtolower($do->mime_type ?? '');
            $fileName = strtolower($do->name ?? '');
            $isMultiPageTiff = false;
            $pageCount = 1;

            if ($mimeType === 'image/tiff' || preg_match('/\.tiff?$/i', $fileName)) {
                $ctx = stream_context_create(['http' => ['timeout' => 1]]);
                $page2 = @file_get_contents(
                    "{$cantaloupeBase}/iiif/2/{$cantaloupeId};2/info.json",
                    false,
                    $ctx
                );
                if ($page2 !== false) {
                    $isMultiPageTiff = true;
                    $pageCount = 2;
                    for ($i = 3; $i <= $maxProbePages; $i++) {
                        $probe = @file_get_contents(
                            "{$cantaloupeBase}/iiif/2/{$cantaloupeId};{$i}/info.json",
                            false,
                            $ctx
                        );
                        if ($probe === false) {
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

    /**
     * Build one W3C annotation hit. When $bbox is null we omit the
     * FragmentSelector and target the whole canvas (per Search API 2.0
     * the target can be a string or a SpecificResource).
     *
     * @param array<string,int>|null $bbox xywh integers
     */
    private function buildAnnotation(
        string $manifestId,
        int $idx,
        string $canvasIri,
        ?array $bbox,
        string $matchedText,
        string $language,
        ?string $motivation
    ): array {
        $annId = $manifestId . '/search/annotation/' . $idx;
        $body = [
            'type' => 'TextualBody',
            'value' => $matchedText,
            'format' => 'text/plain',
            'language' => $language,
        ];

        if ($bbox !== null) {
            $target = [
                'type' => 'SpecificResource',
                'source' => [
                    'id' => $canvasIri,
                    'type' => 'Canvas',
                    'partOf' => [
                        'id' => $manifestId,
                        'type' => 'Manifest',
                    ],
                ],
                'selector' => [
                    'type' => 'FragmentSelector',
                    'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                    'value' => sprintf(
                        'xywh=%d,%d,%d,%d',
                        $bbox['x'],
                        $bbox['y'],
                        max(1, $bbox['w']),
                        max(1, $bbox['h'])
                    ),
                ],
            ];
        } else {
            // Whole-canvas target when we don't have block coords.
            $target = [
                'type' => 'SpecificResource',
                'source' => [
                    'id' => $canvasIri,
                    'type' => 'Canvas',
                    'partOf' => [
                        'id' => $manifestId,
                        'type' => 'Manifest',
                    ],
                ],
                'selector' => [
                    'type' => 'FragmentSelector',
                    'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                    'value' => sprintf(
                        'xywh=0,0,%d,%d',
                        self::DEFAULT_CANVAS_W,
                        self::DEFAULT_CANVAS_H
                    ),
                ],
            ];
        }

        return [
            'id' => $annId,
            'type' => 'Annotation',
            'motivation' => $motivation ?: 'highlighting',
            'body' => $body,
            'target' => $target,
        ];
    }

    /**
     * Empty AnnotationPage envelope for "no hits" responses. Search API
     * 2.0 still requires a valid AnnotationPage shape on zero results.
     *
     * @return array<string,mixed>
     */
    private function emptyAnnotationPage(string $id, string $query): array
    {
        return [
            '@context' => [
                'http://iiif.io/api/search/2/context.json',
                'http://iiif.io/api/presentation/3/context.json',
            ],
            'id' => $id,
            'type' => 'AnnotationPage',
            'label' => [
                'en' => ['Content Search results for "' . $query . '"'],
            ],
            'items' => [],
        ];
    }
}
