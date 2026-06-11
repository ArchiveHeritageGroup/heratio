<?php

/**
 * TermEntityController - content-negotiated Linked-Data identity for TERMS.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). Where EntityController gives every published RECORD a stable
 * Linked-Data identity and ActorEntityController does the same for people /
 * corporate bodies / families, this controller covers the controlled-vocabulary
 * TERMS that records are tagged with - places, subjects and genres / forms -
 * so the open memory graph covers concepts, not just records and actors:
 *
 *   GET /id/term/{slug}    - the canonical term URI (content-negotiated)
 *   GET /data/term/{slug}  - an explicit alias for the same description
 *
 * Content negotiation (Accept header, lowest-priority default last):
 *   - application/ld+json   -> JSON-LD  (machine default)
 *   - text/turtle           -> Turtle
 *   - application/rdf+xml   -> RDF/XML
 *   - text/html (browser)   -> 303 See Other to the GLAM browse filtered by
 *                              this term (the human view of "what is tagged
 *                              with this concept"), via the httpRange-14 303.
 *
 * A term is modelled as a SKOS Concept (skos:Concept) - the standard for a
 * controlled-vocabulary term - and, for place terms (the spatial taxonomy),
 * ALSO typed schema:Place so a schema.org consumer recognises a geography. The
 * description carries: the preferred label (skos:prefLabel / schema:name), the
 * broader term if the taxonomy nests (skos:broader -> a dereferenceable term
 * URI), the narrower terms (skos:narrower), and the PUBLISHED records that
 * reference this term (dcterms:relation, each a record entity URI), plus
 * rdfs:seeAlso / schema:sameAs back to the filtered browse page.
 *
 * Terms are REFERENCE entities (a thesaurus), so the term row itself has no
 * publication-status gate - but every record it links out to is filtered
 * through the SAME published-only gate as the rest of the public v1 API
 * (status.type_id=158, status_id=160), so a draft record is never disclosed
 * through a term's relations.
 *
 * Every URI is built from url() - never a hardcoded host - so a fresh install
 * on its own domain self-describes. Jurisdiction-neutral: standards-based
 * vocabularies (SKOS, schema.org, Dublin Core) with no market assumptions.
 *
 * Honest + safe: read-only; an unknown term slug yields a clean negotiated
 * 404, never a 500. Every enrichment query is guarded (Schema::hasTable +
 * try/catch) so a schema variance degrades to a thinner description. The three
 * RDF serialisations reuse GraphSerializerService, so they can never drift.
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

class TermEntityController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root ids, always excluded. */
    private const ROOT_ID = 1;

    /** Subject access-point taxonomy id. */
    private const TAXONOMY_SUBJECT = 35;

    /** Place access-point taxonomy id (also schema:Place). */
    private const TAXONOMY_PLACE = 42;

    /** Genre / form taxonomy id. */
    private const TAXONOMY_GENRE = 78;

    /** Cap on related records / narrower terms listed, keeps it bounded. */
    private const MAX_RELATED = 200;

    private const MAX_NARROWER = 200;

    protected string $culture = 'en';

    protected GraphSerializerService $serializer;

    public function __construct(GraphSerializerService $serializer)
    {
        $this->culture = app()->getLocale() ?: 'en';
        $this->serializer = $serializer;
    }

    /**
     * OPTIONS preflight for the term entity endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /id/term/{slug}  (alias GET /data/term/{slug})
     *
     * The full Linked-Data description of ONE term, format chosen by Accept.
     * A browser is 303-redirected to the GLAM browse filtered by this term.
     */
    public function show(Request $request, string $slug): Response
    {
        $format = $this->negotiateFormat($request);

        if ($format === 'html') {
            $node = $this->loadTerm($slug);
            if (! $node) {
                return $this->notFound($slug, 'text/html');
            }

            return $this->withCors(
                redirect()->to($this->termBrowseUrl($node), 303)
            );
        }

        $node = $this->loadTerm($slug);
        if (! $node) {
            return $this->notFound($slug, $this->contentTypeFor($format));
        }

        $graph = $this->buildGraph($slug, $node);

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

    // -----------------------------------------------------------------
    // Graph assembly (neutral PHP array -> JSON-LD / Turtle / RDF-XML)
    // -----------------------------------------------------------------

    /**
     * Build the JSON-LD document for one term as a neutral PHP array.
     *
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    protected function buildGraph(string $slug, array $node): array
    {
        $entityUri = $this->termUri($slug);
        $id = (int) $node['id'];
        $taxonomyId = (int) ($node['taxonomy_id'] ?? 0);
        $label = (string) ($node['name'] ?? '[Unlabelled term]');

        $entity = [
            '@id' => $entityUri,
            '@type' => $this->types($taxonomyId),
            'name' => $label,
            'prefLabel' => $label,
        ];

        // Broader term (skos:broader) -> a dereferenceable term URI.
        $broader = $this->broaderTermUri((int) ($node['parent_id'] ?? 0));
        if ($broader !== null) {
            $entity['broader'] = $broader;
        }

        // Narrower terms (skos:narrower) -> dereferenceable term URIs.
        $narrower = $this->narrowerTermUris($id);
        if ($narrower) {
            $entity['narrower'] = count($narrower) === 1 ? $narrower[0] : array_values($narrower);
        }

        // Records that reference this term (object_term_relation), published
        // only -> dcterms:relation, each a record entity URI.
        $related = $this->relatedRecordUris($id);
        if ($related) {
            $entity['relation'] = count($related) === 1 ? $related[0] : array_values($related);
        }

        // Discovery links (rdfs:seeAlso): the filtered browse page, so the term
        // is a hub back into the platform.
        $browse = $this->termBrowseUrl($node);
        if ($browse !== '') {
            $entity['seeAlso'] = $browse;
        }

        // schema:sameAs to the filtered browse page (human "what uses this").
        if ($browse !== '') {
            $entity['sameAs'] = $browse;
        }

        $context = array_merge($this->serializer->context(), [
            'dcterms' => 'http://purl.org/dc/terms/',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'skos' => 'http://www.w3.org/2004/02/skos/core#',
            'prefLabel' => 'skos:prefLabel',
            'broader' => ['@id' => 'skos:broader', '@type' => '@id'],
            'narrower' => ['@id' => 'skos:narrower', '@type' => '@id'],
            'relation' => ['@id' => 'dcterms:relation', '@type' => '@id'],
            'seeAlso' => ['@id' => 'rdfs:seeAlso', '@type' => '@id'],
        ]);

        return [
            '@context' => $context,
            '@graph' => [$entity],
        ];
    }

    // -----------------------------------------------------------------
    // Term loading
    // -----------------------------------------------------------------

    /**
     * Resolve a slug to a term row. Terms are reference entities (no
     * publication gate); the synthetic root is excluded. Resilient: a schema
     * variance yields null, not an exception.
     *
     * @return array<string,mixed>|null
     */
    protected function loadTerm(string $slug): ?array
    {
        try {
            if (! Schema::hasTable('term') || ! Schema::hasTable('slug')) {
                return null;
            }

            $row = DB::table('slug as s')
                ->join('term as t', 't.id', '=', 's.object_id')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->where('s.slug', $slug)
                ->where('t.id', '!=', self::ROOT_ID)
                ->select('t.id', 't.taxonomy_id', 't.parent_id', 'ti.name')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'taxonomy_id' => $row->taxonomy_id,
            'parent_id' => $row->parent_id,
            'name' => $row->name,
        ];
    }

    // -----------------------------------------------------------------
    // Broader / narrower (SKOS hierarchy)
    // -----------------------------------------------------------------

    /**
     * The broader term's entity URI (skos:broader), only when the parent is a
     * real, non-root term with a slug. Null otherwise (e.g. a taxonomy root).
     */
    protected function broaderTermUri(int $parentId): ?string
    {
        if ($parentId <= self::ROOT_ID) {
            return null;
        }

        try {
            $slug = DB::table('slug')
                ->where('object_id', $parentId)
                ->value('slug');
        } catch (\Throwable $e) {
            return null;
        }

        return ! empty($slug) ? $this->termUri((string) $slug) : null;
    }

    /**
     * The narrower terms' entity URIs (skos:narrower): children of this term in
     * the same taxonomy that have a slug.
     *
     * @return array<int,string>
     */
    protected function narrowerTermUris(int $termId): array
    {
        try {
            $slugs = DB::table('term as t')
                ->join('slug as s', 's.object_id', '=', 't.id')
                ->where('t.parent_id', $termId)
                ->where('t.id', '!=', self::ROOT_ID)
                ->orderBy('t.id')
                ->limit(self::MAX_NARROWER)
                ->pluck('s.slug')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }

        $uris = [];
        foreach ($slugs as $slug) {
            if (! empty($slug)) {
                $uris[] = $this->termUri((string) $slug);
            }
        }

        return array_values(array_unique($uris));
    }

    // -----------------------------------------------------------------
    // Related records (published-only gate applied here)
    // -----------------------------------------------------------------

    /**
     * Entity URIs of PUBLISHED records that reference this term, via the
     * object_term_relation table. Filtered through the published-only gate so
     * a draft record is never leaked. Returns dereferenceable /id/{slug} URIs.
     *
     * @return array<int,string>
     */
    protected function relatedRecordUris(int $termId): array
    {
        try {
            if (! Schema::hasTable('object_term_relation')
                || ! Schema::hasTable('information_object')
                || ! Schema::hasTable('slug')
                || ! Schema::hasTable('status')) {
                return [];
            }

            $rows = DB::table('object_term_relation as otr')
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
                ->limit(self::MAX_RELATED)
                ->pluck('s.slug')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }

        $uris = [];
        foreach ($rows as $slug) {
            if (! empty($slug)) {
                $uris[] = $this->recordEntityUri((string) $slug);
            }
        }

        return array_values(array_unique($uris));
    }

    // -----------------------------------------------------------------
    // Content negotiation
    // -----------------------------------------------------------------

    protected function negotiateFormat(Request $request): string
    {
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
        if (in_array($param, ['html', 'page'], true)) {
            return 'html';
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        if (str_contains($accept, 'text/turtle') || str_contains($accept, 'application/x-turtle')) {
            return 'turtle';
        }
        if (str_contains($accept, 'application/rdf+xml')) {
            return 'rdfxml';
        }
        if (str_contains($accept, 'application/ld+json') || str_contains($accept, 'application/json')) {
            return 'jsonld';
        }
        if (str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml')) {
            return 'html';
        }

        return 'jsonld';
    }

    protected function contentTypeFor(string $format): string
    {
        return match ($format) {
            'turtle' => 'text/turtle',
            'rdfxml' => 'application/rdf+xml',
            'html' => 'text/html',
            default => 'application/ld+json',
        };
    }

    // -----------------------------------------------------------------
    // URI + type helpers
    // -----------------------------------------------------------------

    /**
     * The canonical, stable term entity URI for a slug (this endpoint).
     */
    protected function termUri(string $slug): string
    {
        return $this->endpointBase().'/id/term/'.ltrim($slug, '/');
    }

    /**
     * A record's entity URI (EntityController surface), for dcterms:relation.
     */
    protected function recordEntityUri(string $slug): string
    {
        return $this->endpointBase().'/id/'.ltrim($slug, '/');
    }

    /**
     * The GLAM browse URL filtered by this term. The browse facet expects the
     * numeric term id; the parameter name depends on the taxonomy (subject /
     * place / genre). Subjects are the safe default.
     *
     * @param  array<string,mixed>  $node
     */
    protected function termBrowseUrl(array $node): string
    {
        $taxonomyId = (int) ($node['taxonomy_id'] ?? 0);
        $id = (int) ($node['id'] ?? 0);
        if ($id <= 0) {
            return '';
        }

        $param = match ($taxonomyId) {
            self::TAXONOMY_PLACE => 'place',
            self::TAXONOMY_GENRE => 'genre',
            default => 'subject',
        };

        return $this->endpointBase().'/glam/browse?'.$param.'='.$id;
    }

    protected function endpointBase(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * The RDF types for a term: always a SKOS Concept; a place term is ALSO a
     * schema:Place so a schema.org consumer recognises the geography.
     *
     * @return array<int,string>|string
     */
    protected function types(int $taxonomyId)
    {
        if ($taxonomyId === self::TAXONOMY_PLACE) {
            return ['skos:Concept', 'schema:Place'];
        }

        return 'skos:Concept';
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    protected function notFound(string $slug, string $contentType = 'application/ld+json'): Response
    {
        if (str_contains($contentType, 'turtle')) {
            return $this->withCors(response(
                "# Not Found: '".str_replace("\n", ' ', $slug)."' is not a known term.\n",
                404,
                ['Content-Type' => 'text/turtle; charset=utf-8']
            ));
        }

        if (str_contains($contentType, 'rdf+xml')) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n"
                .'  <!-- Not Found: '.htmlspecialchars($slug, ENT_XML1, 'UTF-8').' is not a known term. -->'."\n"
                .'</rdf:RDF>'."\n";

            return $this->withCors(response($body, 404, ['Content-Type' => 'application/rdf+xml; charset=utf-8']));
        }

        if (str_contains($contentType, 'html')) {
            return $this->withCors(response(
                '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                .'<title>Not found</title></head><body><h1>404 Not found</h1>'
                .'<p>No term matches that identifier.</p></body></html>',
                404,
                ['Content-Type' => 'text/html; charset=utf-8']
            ));
        }

        $body = json_encode([
            '@context' => ['schema' => 'https://schema.org/'],
            '@type' => 'schema:Error',
            'error' => 'Not Found',
            'message' => "No term for '".$slug."'.",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $this->withCors(
            response($body, 404, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    /**
     * Apply permissive open-data CORS headers. These endpoints are intentionally
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
