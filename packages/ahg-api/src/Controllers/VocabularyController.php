<?php

/**
 * VocabularyController - Heratio's controlled vocabularies as SKOS concept schemes.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). Where TermEntityController publishes ONE term as a dereferenceable
 * skos:Concept (/id/term/{slug}), this controller publishes the whole CONTROLLED
 * VOCABULARY as a standard SKOS skos:ConceptScheme, the conventional linked-data
 * way to expose an institution's authorities (subjects, places, genres / forms):
 *
 *   GET /vocabularies                         - an HTML index of the published
 *                                               concept schemes, with term counts
 *                                               and links to each scheme.
 *   GET /vocabulary/{taxonomy}(.ttl|.jsonld|.rdf)
 *                                             - ONE taxonomy as a skos:ConceptScheme:
 *                                               every term a skos:Concept with a
 *                                               language-tagged skos:prefLabel, a
 *                                               skos:notation, skos:broader /
 *                                               skos:narrower from the term
 *                                               hierarchy, skos:inScheme /
 *                                               skos:topConceptOf, and a
 *                                               skos:scopeNote where present.
 *                                               Content-negotiated; a browser gets
 *                                               JSON-LD by default (machine-first),
 *                                               so the deep-zoom of one scheme stays
 *                                               machine-consumable.
 *   GET /vocabulary/{taxonomy}/{termId}(.ttl|.jsonld|.rdf)
 *                                             - ONE skos:Concept in its scheme, with
 *                                               its labels, scheme membership,
 *                                               broader / narrower, scope note, and a
 *                                               bounded handful of example published
 *                                               records that use the concept
 *                                               (dct:subject). The /id/term/{slug}
 *                                               endpoint remains the canonical term
 *                                               URI; this scheme-scoped view nests
 *                                               the same concept under its scheme.
 *
 * The published schemes are a SAFE, FIXED SLUG SET (subjects | places | genres)
 * mapped to the underlying term taxonomy ids, so {taxonomy} can never collide with
 * a numeric id or swallow a sibling path. The TERMS themselves are entirely
 * data-driven (no hardcoded vocabulary): the labels, notations, hierarchy and
 * scope notes all come from the term / term_i18n / note tables, language-tagged by
 * culture. A fresh install with different authorities self-describes from its own
 * data. Jurisdiction-neutral: standards-based SKOS / Dublin Core, no market
 * assumptions.
 *
 * The three RDF serialisations (Turtle, JSON-LD, RDF/XML) REUSE the package's
 * GraphSerializerService (the single source of truth for namespaces + escaping,
 * shared by every entity / graph surface) so they can never drift and no new RDF
 * library is added. Every URI is built from url() - never a hardcoded host.
 *
 * Honest + safe: read-only (no DB writes, no ALTER, no new table); permissive
 * open-data CORS on the machine forms; a scheme dump is BOUNDED (capped, with an
 * honest skos:note when a taxonomy exceeds the cap); an unknown scheme slug or
 * term id yields a clean negotiated 404, never a 500; every enrichment query is
 * guarded (Schema::hasTable + try/catch) so a schema variance degrades to a
 * thinner description.
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

use AhgApi\Services\GraphSerializerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class VocabularyController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic / taxonomy-root term ids are never published as concepts. */
    private const ROOT_ID = 1;

    /** Scope-note type id (note.type_id) in the note / note_i18n tables. */
    private const NOTE_SCOPE = 122;

    /** Cap on concepts emitted in a single scheme dump (bounded, honest note). */
    private const MAX_SCHEME_CONCEPTS = 1000;

    /** Cap on narrower terms listed under one concept. */
    private const MAX_NARROWER = 500;

    /** Cap on example records listed under one concept. */
    private const MAX_EXAMPLES = 25;

    /**
     * The published concept schemes: a SAFE, FIXED slug set mapped to the
     * underlying term taxonomy ids. The slugs are the catch-all-safe surface
     * tokens; the ids are the data. Order here is the index display order.
     *
     * @var array<string,array{taxonomy_id:int,title:string,description:string}>
     */
    private const SCHEMES = [
        'subjects' => [
            'taxonomy_id' => 35,
            'title' => 'Subjects',
            'description' => 'Subject access points: the controlled list of topics records are described under.',
        ],
        'places' => [
            'taxonomy_id' => 42,
            'title' => 'Places',
            'description' => 'Place access points: the controlled list of geographic names records are described under.',
        ],
        'genres' => [
            'taxonomy_id' => 78,
            'title' => 'Genres / forms',
            'description' => 'Genre and form access points: the controlled list of material types and forms records are described under.',
        ],
    ];

    protected string $culture = 'en';

    protected GraphSerializerService $serializer;

    public function __construct(GraphSerializerService $serializer)
    {
        $this->culture = app()->getLocale() ?: 'en';
        $this->serializer = $serializer;
    }

    /**
     * OPTIONS preflight for the vocabulary endpoints.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    // -----------------------------------------------------------------
    // GET /vocabularies  - HTML index of the concept schemes
    // -----------------------------------------------------------------

    /**
     * The human index of every published concept scheme, with live term counts
     * and links to each scheme (HTML by default; ?format=json gives a machine
     * list so an agent can discover the schemes too). Read-only; CORS-open.
     */
    public function index(Request $request): Response
    {
        $schemes = $this->schemeSummaries();

        if ($this->wantsJson($request)) {
            $body = json_encode([
                '@context' => [
                    'schema' => 'https://schema.org/',
                    'skos' => 'http://www.w3.org/2004/02/skos/core#',
                ],
                '@type' => 'schema:DataCatalog',
                'name' => (string) config('app.name', 'Heratio').' controlled vocabularies',
                'description' => 'The published SKOS concept schemes (controlled vocabularies) of this platform.',
                'count' => count($schemes),
                'schemes' => array_map(static function (array $s): array {
                    return [
                        '@id' => $s['uri'],
                        '@type' => 'skos:ConceptScheme',
                        'slug' => $s['slug'],
                        'title' => $s['title'],
                        'description' => $s['description'],
                        'conceptCount' => $s['count'],
                        'jsonld' => $s['uri'].'.jsonld',
                        'turtle' => $s['uri'].'.ttl',
                        'rdfxml' => $s['uri'].'.rdf',
                    ];
                }, $schemes),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $this->withCors(response($body, 200, [
                'Content-Type' => 'application/json; charset=utf-8',
            ]));
        }

        return $this->withCors(response($this->indexHtml($schemes), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]));
    }

    // -----------------------------------------------------------------
    // GET /vocabulary/{taxonomy}  - ONE taxonomy as a skos:ConceptScheme
    // -----------------------------------------------------------------

    /**
     * One controlled vocabulary as a SKOS concept scheme. The {taxonomy} slug is
     * resolved against the fixed scheme set; an unknown slug -> a clean 404.
     */
    public function scheme(Request $request, string $taxonomy, ?string $suffix = null): Response
    {
        $format = $this->negotiateFormat($request, $suffix);

        $scheme = self::SCHEMES[$taxonomy] ?? null;
        if ($scheme === null) {
            return $this->notFound($taxonomy, $this->contentTypeFor($format));
        }

        $graph = $this->buildSchemeGraph($taxonomy, $scheme);

        return $this->respondGraph($graph, $format);
    }

    // -----------------------------------------------------------------
    // GET /vocabulary/{taxonomy}/{termId}  - ONE concept in its scheme
    // -----------------------------------------------------------------

    /**
     * One concept, nested under its scheme, with labels, hierarchy, scope note
     * and a bounded handful of example records. An unknown scheme or a term id
     * outside the scheme -> a clean 404.
     */
    public function concept(Request $request, string $taxonomy, string $termId, ?string $suffix = null): Response
    {
        $format = $this->negotiateFormat($request, $suffix);

        $scheme = self::SCHEMES[$taxonomy] ?? null;
        if ($scheme === null) {
            return $this->notFound($taxonomy, $this->contentTypeFor($format));
        }

        $id = (int) $termId;
        $node = $this->loadConcept($id, (int) $scheme['taxonomy_id']);
        if ($node === null) {
            return $this->notFound($taxonomy.'/'.$termId, $this->contentTypeFor($format));
        }

        $graph = $this->buildConceptGraph($taxonomy, $scheme, $node, true);

        return $this->respondGraph($graph, $format);
    }

    // -----------------------------------------------------------------
    // Graph assembly
    // -----------------------------------------------------------------

    /**
     * The full skos:ConceptScheme document for one taxonomy: the scheme node
     * plus every concept (bounded). Each concept carries prefLabel, notation,
     * inScheme, broader/narrower/topConceptOf and scopeNote.
     *
     * @param  array{taxonomy_id:int,title:string,description:string}  $scheme
     * @return array<string,mixed>
     */
    protected function buildSchemeGraph(string $taxonomy, array $scheme): array
    {
        $taxonomyId = (int) $scheme['taxonomy_id'];
        $schemeUri = $this->schemeUri($taxonomy);

        $rows = $this->loadSchemeTerms($taxonomyId);
        $total = $this->countSchemeTerms($taxonomyId);

        // Set of concept ids present in THIS dump, so broader/narrower only point
        // to concepts we actually emit (a partial dump stays internally honest).
        $present = [];
        foreach ($rows as $r) {
            $present[(int) $r['id']] = true;
        }

        $schemeNode = [
            '@id' => $schemeUri,
            '@type' => 'skos:ConceptScheme',
            'prefLabel' => $scheme['title'],
            'description' => $scheme['description'],
        ];

        $topConcepts = [];
        $conceptNodes = [];

        foreach ($rows as $r) {
            $node = $this->conceptNode($taxonomy, $taxonomyId, $schemeUri, $r, $present);
            // A concept whose parent is a taxonomy-root (not itself a concept) is
            // a top concept of the scheme.
            if (! $this->parentIsConcept($r, $present)) {
                $topConcepts[] = $node['@id'];
                $node['topConceptOf'] = $schemeUri;
            }
            $conceptNodes[] = $node;
        }

        if ($topConcepts) {
            $schemeNode['hasTopConcept'] = count($topConcepts) === 1
                ? $topConcepts[0]
                : array_values($topConcepts);
        }

        // Bounded + honest: if the taxonomy exceeds the cap, say so in a note.
        if ($total > count($rows)) {
            $schemeNode['note'] = 'This concept scheme has '.$total.' concepts; this document lists the first '
                .count($rows).'. Dereference an individual concept at '.$schemeUri.'/{termId} for its full description.';
        }

        $graph = array_merge([$schemeNode], $conceptNodes);

        return [
            '@context' => $this->skosContext(),
            '@graph' => $graph,
        ];
    }

    /**
     * Build ONE concept node (used both inside a scheme dump and standalone).
     *
     * @param  array<string,mixed>  $r        term row
     * @param  array<int,bool>      $present  concept ids in the current dump
     * @return array<string,mixed>
     */
    protected function conceptNode(string $taxonomy, int $taxonomyId, string $schemeUri, array $r, array $present): array
    {
        $id = (int) $r['id'];
        $label = (string) ($r['name'] ?? '[Unlabelled term]');

        $node = [
            '@id' => $this->conceptUri($taxonomy, $id),
            '@type' => 'skos:Concept',
            'prefLabel' => $label,
            'inScheme' => $schemeUri,
        ];

        // skos:notation from the term code (a stable in-house notation) or the id.
        $notation = $this->notationFor($r);
        if ($notation !== null) {
            $node['notation'] = $notation;
        }

        // The canonical dereferenceable term URI (the /id/term/{slug} surface),
        // so the two views of the same concept link to each other.
        $canonical = $this->canonicalTermUri($r);
        if ($canonical !== null) {
            $node['exactMatch'] = $canonical;
        }

        // skos:broader -> the parent, only when the parent is itself a concept.
        if ($this->parentIsConcept($r, $present)) {
            $node['broader'] = $this->conceptUri($taxonomy, (int) $r['parent_id']);
        }

        // skos:narrower -> children that are concepts in the same scheme.
        $narrower = $this->narrowerConceptUris($taxonomy, $taxonomyId, $id, $present);
        if ($narrower) {
            $node['narrower'] = count($narrower) === 1 ? $narrower[0] : array_values($narrower);
        }

        // skos:scopeNote where the term carries one (language-tagged content).
        $scope = $this->scopeNoteFor($id);
        if ($scope !== null && $scope !== '') {
            $node['scopeNote'] = $scope;
        }

        return $node;
    }

    /**
     * The standalone concept document: the concept node, its scheme, and a
     * bounded list of example published records (dct:subject) that use it.
     *
     * @param  array{taxonomy_id:int,title:string,description:string}  $scheme
     * @param  array<string,mixed>  $r
     * @return array<string,mixed>
     */
    protected function buildConceptGraph(string $taxonomy, array $scheme, array $r, bool $withExamples): array
    {
        $taxonomyId = (int) $scheme['taxonomy_id'];
        $schemeUri = $this->schemeUri($taxonomy);
        $id = (int) $r['id'];

        // For a standalone concept, broader/narrower may reference concepts not
        // in any "present" set, so allow any same-taxonomy concept.
        $present = $this->allConceptIds($taxonomyId);

        $concept = $this->conceptNode($taxonomy, $taxonomyId, $schemeUri, $r, $present);
        if (! $this->parentIsConcept($r, $present)) {
            $concept['topConceptOf'] = $schemeUri;
        }

        // A bounded handful of published records that reference this concept, so
        // the concept page shows real usage. dcterms:relation -> record entity
        // URIs (the records carry dct:subject -> this concept; here we surface the
        // inverse as a generic relation, exactly as the /id/term surface does, and
        // it is @id-typed in the shared context so it renders as an IRI in Turtle
        // / RDF-XML too).
        if ($withExamples) {
            $examples = $this->exampleRecordUris($id);
            if ($examples) {
                $concept['relation'] = count($examples) === 1 ? $examples[0] : array_values($examples);
            }
        }

        $schemeNode = [
            '@id' => $schemeUri,
            '@type' => 'skos:ConceptScheme',
            'prefLabel' => $scheme['title'],
        ];

        return [
            '@context' => $this->skosContext(),
            '@graph' => [$concept, $schemeNode],
        ];
    }

    /**
     * The JSON-LD @context for the SKOS surfaces. The shared serializer context
     * (the single source of truth) already carries every namespace and every
     * predicate this surface uses (skos:prefLabel / notation / note / inScheme /
     * topConceptOf / hasTopConcept / broader / narrower / scopeNote / exactMatch,
     * dcterms:relation / description), so the JSON-LD, Turtle and RDF/XML views
     * all render the SAME predicates from ONE context - they can never drift.
     *
     * @return array<string,mixed>
     */
    protected function skosContext(): array
    {
        return $this->serializer->context();
    }

    // -----------------------------------------------------------------
    // Term loading (read-only, guarded)
    // -----------------------------------------------------------------

    /**
     * Term rows for a scheme (bounded), with label + slug. Ordered by the tree
     * (lft) where available so siblings read naturally; the synthetic root and
     * taxonomy-root terms are excluded.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function loadSchemeTerms(int $taxonomyId): array
    {
        try {
            if (! Schema::hasTable('term')) {
                return [];
            }

            $q = DB::table('term as t')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->leftJoin('slug as s', 's.object_id', '=', 't.id')
                ->where('t.taxonomy_id', $taxonomyId)
                ->where('t.id', '!=', self::ROOT_ID)
                ->whereNotNull('t.parent_id')
                ->orderByRaw('COALESCE(t.lft, t.id)')
                ->limit(self::MAX_SCHEME_CONCEPTS)
                ->select('t.id', 't.taxonomy_id', 't.parent_id', 't.code', 't.lft', 't.rgt', 'ti.name', 's.slug');

            $rows = $q->get();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'taxonomy_id' => (int) $row->taxonomy_id,
                'parent_id' => $row->parent_id,
                'code' => $row->code,
                'lft' => $row->lft,
                'rgt' => $row->rgt,
                'name' => $row->name,
                'slug' => $row->slug,
            ];
        }

        return $out;
    }

    /**
     * Total concept count in a scheme (for the honest "more than shown" note).
     */
    protected function countSchemeTerms(int $taxonomyId): int
    {
        try {
            if (! Schema::hasTable('term')) {
                return 0;
            }

            return (int) DB::table('term')
                ->where('taxonomy_id', $taxonomyId)
                ->where('id', '!=', self::ROOT_ID)
                ->whereNotNull('parent_id')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Load ONE concept row in a given taxonomy. Returns null when the id is not
     * a published concept of that scheme (so a term from another taxonomy 404s).
     *
     * @return array<string,mixed>|null
     */
    protected function loadConcept(int $id, int $taxonomyId): ?array
    {
        if ($id <= self::ROOT_ID) {
            return null;
        }

        try {
            if (! Schema::hasTable('term')) {
                return null;
            }

            $row = DB::table('term as t')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->leftJoin('slug as s', 's.object_id', '=', 't.id')
                ->where('t.id', $id)
                ->where('t.taxonomy_id', $taxonomyId)
                ->whereNotNull('t.parent_id')
                ->select('t.id', 't.taxonomy_id', 't.parent_id', 't.code', 't.lft', 't.rgt', 'ti.name', 's.slug')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'taxonomy_id' => (int) $row->taxonomy_id,
            'parent_id' => $row->parent_id,
            'code' => $row->code,
            'lft' => $row->lft,
            'rgt' => $row->rgt,
            'name' => $row->name,
            'slug' => $row->slug,
        ];
    }

    /**
     * All concept ids in a taxonomy (for resolving broader/narrower membership
     * on the standalone concept view). Bounded by the scheme cap.
     *
     * @return array<int,bool>
     */
    protected function allConceptIds(int $taxonomyId): array
    {
        try {
            if (! Schema::hasTable('term')) {
                return [];
            }

            $ids = DB::table('term')
                ->where('taxonomy_id', $taxonomyId)
                ->where('id', '!=', self::ROOT_ID)
                ->whereNotNull('parent_id')
                ->limit(self::MAX_SCHEME_CONCEPTS)
                ->pluck('id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }

        $set = [];
        foreach ($ids as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }

    // -----------------------------------------------------------------
    // Hierarchy helpers (SKOS broader / narrower)
    // -----------------------------------------------------------------

    /**
     * Whether a term's parent is itself a concept of the scheme (so the term
     * has a skos:broader), as opposed to a taxonomy-root (so the term is a
     * top concept). The parent is a concept only when it is in the present set.
     *
     * @param  array<string,mixed>  $r
     * @param  array<int,bool>      $present
     */
    protected function parentIsConcept(array $r, array $present): bool
    {
        $parentId = (int) ($r['parent_id'] ?? 0);
        if ($parentId <= self::ROOT_ID) {
            return false;
        }

        return isset($present[$parentId]);
    }

    /**
     * The skos:narrower concept URIs: children of this term that are concepts in
     * the same scheme (bounded).
     *
     * @param  array<int,bool>  $present
     * @return array<int,string>
     */
    protected function narrowerConceptUris(string $taxonomy, int $taxonomyId, int $termId, array $present): array
    {
        try {
            if (! Schema::hasTable('term')) {
                return [];
            }

            $ids = DB::table('term')
                ->where('parent_id', $termId)
                ->where('taxonomy_id', $taxonomyId)
                ->where('id', '!=', self::ROOT_ID)
                ->orderByRaw('COALESCE(lft, id)')
                ->limit(self::MAX_NARROWER)
                ->pluck('id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }

        $uris = [];
        foreach ($ids as $id) {
            $cid = (int) $id;
            // Only point at children we would actually publish as concepts.
            if ($present === [] || isset($present[$cid])) {
                $uris[] = $this->conceptUri($taxonomy, $cid);
            }
        }

        return array_values(array_unique($uris));
    }

    /**
     * The scope note (skos:scopeNote) for a term, from the note / note_i18n
     * tables (note.type_id = scope note), in the active culture. Null if none.
     */
    protected function scopeNoteFor(int $termId): ?string
    {
        try {
            if (! Schema::hasTable('note') || ! Schema::hasTable('note_i18n')) {
                return null;
            }

            $content = DB::table('note as n')
                ->join('note_i18n as ni', function ($j) {
                    $j->on('n.id', '=', 'ni.id')->where('ni.culture', $this->culture);
                })
                ->where('n.object_id', $termId)
                ->where('n.type_id', self::NOTE_SCOPE)
                ->value('ni.content');
        } catch (\Throwable $e) {
            return null;
        }

        return $content !== null ? trim((string) $content) : null;
    }

    // -----------------------------------------------------------------
    // Example records (published-only gate applied here)
    // -----------------------------------------------------------------

    /**
     * Entity URIs of a bounded handful of PUBLISHED records that reference this
     * concept, via object_term_relation, filtered through the published-only
     * gate so a draft is never leaked. Returns dereferenceable /id/{slug} URIs.
     *
     * @return array<int,string>
     */
    protected function exampleRecordUris(int $termId): array
    {
        try {
            if (! Schema::hasTable('object_term_relation')
                || ! Schema::hasTable('information_object')
                || ! Schema::hasTable('slug')
                || ! Schema::hasTable('status')) {
                return [];
            }

            $slugs = DB::table('object_term_relation as otr')
                ->join('information_object as io', 'io.id', '=', 'otr.object_id')
                ->join('slug as s', 's.object_id', '=', 'io.id')
                ->join('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                        ->where('st.status_id', '=', self::STATUS_PUBLISHED);
                })
                ->where('otr.term_id', $termId)
                ->where('io.id', '!=', self::ROOT_ID)
                ->distinct()
                ->orderBy('s.slug')
                ->limit(self::MAX_EXAMPLES)
                ->pluck('s.slug')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }

        $uris = [];
        foreach ($slugs as $slug) {
            if (! empty($slug)) {
                $uris[] = $this->recordEntityUri((string) $slug);
            }
        }

        return array_values(array_unique($uris));
    }

    // -----------------------------------------------------------------
    // Scheme summaries (for the index)
    // -----------------------------------------------------------------

    /**
     * The schemes with their live term counts and URIs, for the index page.
     *
     * @return array<int,array{slug:string,title:string,description:string,count:int,uri:string}>
     */
    protected function schemeSummaries(): array
    {
        $out = [];
        foreach (self::SCHEMES as $slug => $scheme) {
            $out[] = [
                'slug' => $slug,
                'title' => $scheme['title'],
                'description' => $scheme['description'],
                'count' => $this->countSchemeTerms((int) $scheme['taxonomy_id']),
                'uri' => $this->schemeUri($slug),
            ];
        }

        return $out;
    }

    // -----------------------------------------------------------------
    // Notation + URI helpers
    // -----------------------------------------------------------------

    /**
     * A skos:notation for a concept: the term's in-house code when present, else
     * the stable numeric id (so every concept carries a notation).
     *
     * @param  array<string,mixed>  $r
     */
    protected function notationFor(array $r): ?string
    {
        $code = $r['code'] ?? null;
        if (is_string($code) && trim($code) !== '') {
            return trim($code);
        }

        $id = (int) ($r['id'] ?? 0);

        return $id > 0 ? (string) $id : null;
    }

    /**
     * The canonical dereferenceable term URI (the /id/term/{slug} surface) for a
     * term that has a slug, so the scheme view links to the canonical concept.
     *
     * @param  array<string,mixed>  $r
     */
    protected function canonicalTermUri(array $r): ?string
    {
        $slug = $r['slug'] ?? null;

        return (is_string($slug) && $slug !== '')
            ? $this->base().'/id/term/'.ltrim($slug, '/')
            : null;
    }

    protected function schemeUri(string $taxonomy): string
    {
        return $this->base().'/vocabulary/'.$taxonomy;
    }

    protected function conceptUri(string $taxonomy, int $termId): string
    {
        return $this->base().'/vocabulary/'.$taxonomy.'/'.$termId;
    }

    protected function recordEntityUri(string $slug): string
    {
        return $this->base().'/id/'.ltrim($slug, '/');
    }

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    // -----------------------------------------------------------------
    // Responses + content negotiation
    // -----------------------------------------------------------------

    /**
     * Serialise a neutral graph array in the negotiated format and wrap in CORS.
     *
     * @param  array<string,mixed>  $graph
     */
    protected function respondGraph(array $graph, string $format): Response
    {
        if ($format === 'turtle') {
            return $this->withCors(response(
                $this->serializer->toTurtle($graph),
                200,
                ['Content-Type' => 'text/turtle; charset=utf-8']
            ));
        }

        if ($format === 'rdfxml') {
            return $this->withCors(response(
                $this->serializer->toRdfXml($graph),
                200,
                ['Content-Type' => 'application/rdf+xml; charset=utf-8']
            ));
        }

        $body = json_encode(
            $graph,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    /**
     * Negotiate the RDF serialisation. A dotted path suffix (.ttl / .jsonld /
     * .rdf) is authoritative; otherwise ?format= then the Accept header. JSON-LD
     * is the machine-first default (a browser hitting a scheme still gets data).
     */
    protected function negotiateFormat(Request $request, ?string $suffix = null): string
    {
        $suffix = strtolower((string) $suffix);
        if ($suffix === 'ttl') {
            return 'turtle';
        }
        if ($suffix === 'rdf') {
            return 'rdfxml';
        }
        if ($suffix === 'jsonld') {
            return 'jsonld';
        }

        $param = strtolower((string) $request->query('format', ''));
        if (in_array($param, ['turtle', 'ttl'], true)) {
            return 'turtle';
        }
        if (in_array($param, ['rdf', 'rdfxml', 'rdf-xml', 'rdf/xml'], true)) {
            return 'rdfxml';
        }
        if (in_array($param, ['jsonld', 'json-ld', 'json'], true)) {
            return 'jsonld';
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'text/turtle') || str_contains($accept, 'application/x-turtle')) {
            return 'turtle';
        }
        if (str_contains($accept, 'application/rdf+xml')) {
            return 'rdfxml';
        }

        return 'jsonld';
    }

    protected function contentTypeFor(string $format): string
    {
        return match ($format) {
            'turtle' => 'text/turtle',
            'rdfxml' => 'application/rdf+xml',
            default => 'application/ld+json',
        };
    }

    /**
     * Whether the client wants the machine list (JSON) of the schemes index. A
     * browser (text/html) gets the human page; ?format=json or a JSON Accept
     * gets the machine list.
     */
    protected function wantsJson(Request $request): bool
    {
        $param = strtolower((string) $request->query('format', ''));
        if (in_array($param, ['json', 'jsonld', 'json-ld'], true)) {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml')) {
            return false;
        }

        return str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json');
    }

    /**
     * Negotiated 404 for an unknown scheme slug or concept id.
     */
    protected function notFound(string $what, string $contentType = 'application/ld+json'): Response
    {
        if (str_contains($contentType, 'turtle')) {
            return $this->withCors(response(
                "# Not Found: '".str_replace("\n", ' ', $what)."' is not a published concept scheme.\n",
                404,
                ['Content-Type' => 'text/turtle; charset=utf-8']
            ));
        }

        if (str_contains($contentType, 'rdf+xml')) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n"
                .'  <!-- Not Found: '.htmlspecialchars($what, ENT_XML1, 'UTF-8').' is not a published concept scheme. -->'."\n"
                .'</rdf:RDF>'."\n";

            return $this->withCors(response($body, 404, ['Content-Type' => 'application/rdf+xml; charset=utf-8']));
        }

        $body = json_encode([
            '@context' => ['schema' => 'https://schema.org/'],
            '@type' => 'schema:Error',
            'error' => 'Not Found',
            'message' => "No published concept scheme or concept for '".$what."'.",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $this->withCors(
            response($body, 404, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // HTML index rendering (dependency-free, escaped)
    // -----------------------------------------------------------------

    /**
     * The human index page of the concept schemes. No Blade (this package has no
     * public layout), just escaped inline HTML - the same idiom ProtocolController
     * uses for its capabilities page.
     *
     * @param  array<int,array{slug:string,title:string,description:string,count:int,uri:string}>  $schemes
     */
    protected function indexHtml(array $schemes): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $base = $this->base();

        $rows = '';
        foreach ($schemes as $s) {
            $rows .= '<tr>'
                .'<td><strong>'.$e($s['title']).'</strong><br><small>'.$e($s['description']).'</small></td>'
                .'<td>'.$e($s['count']).'</td>'
                .'<td><a href="'.$e($s['uri']).'">'.$e($s['uri']).'</a><br>'
                .'<small>'
                .'<a href="'.$e($s['uri']).'.jsonld">JSON-LD</a> &middot; '
                .'<a href="'.$e($s['uri']).'.ttl">Turtle</a> &middot; '
                .'<a href="'.$e($s['uri']).'.rdf">RDF/XML</a>'
                .'</small></td>'
                .'</tr>'."\n";
        }

        $title = $e((string) config('app.name', 'Heratio')).' controlled vocabularies';
        $protocolUrl = $e($base.'/open-data/protocol');

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.$title.'</title>'
            .'<style>body{font-family:system-ui,Arial,sans-serif;max-width:62rem;margin:2rem auto;padding:0 1rem;color:#1a1a1a}'
            .'h1{font-size:1.5rem}table{border-collapse:collapse;width:100%}'
            .'td,th{border:1px solid #ddd;padding:.6rem;vertical-align:top;text-align:left}'
            .'th{background:#f5f5f5}code{background:#f2f2f2;padding:.1rem .3rem;border-radius:3px}'
            .'small{color:#555}.meta{color:#555;margin:.3rem 0 1.2rem}</style></head><body>'
            .'<h1>'.$title.'</h1>'
            .'<p>The platform\'s controlled vocabularies (authorities), published as standard '
            .'<a href="https://www.w3.org/2004/02/skos/">SKOS</a> concept schemes. Each scheme dereferences as '
            .'JSON-LD, Turtle or RDF/XML; each concept resolves at <code>/vocabulary/{scheme}/{termId}</code>.</p>'
            .'<p class="meta">Open data under <a href="https://creativecommons.org/licenses/by/4.0/">CC-BY-4.0</a> &middot; '
            .'Authentication: none &middot; '
            .'Protocol index: <a href="'.$protocolUrl.'">'.$protocolUrl.'</a></p>'
            .'<table><thead><tr><th>Concept scheme</th><th>Concepts</th><th>URI &amp; serialisations</th></tr></thead>'
            .'<tbody>'."\n".$rows.'</tbody></table>'
            .'</body></html>';
    }

    /**
     * Apply permissive open-data CORS headers. These surfaces are intentionally
     * world-readable (open data), so any origin may fetch them.
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
