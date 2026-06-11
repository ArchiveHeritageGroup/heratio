<?php

/**
 * EntityController - content-negotiated Linked-Data entity endpoint.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). Where GraphController exposes a record's graph NEIGHBOURHOOD for
 * crawling and DatasetController dumps the WHOLE catalogue, this controller
 * gives every published record a single, stable, dereferenceable Linked-Data
 * IDENTITY - the "thing" itself described in full:
 *
 *   GET /id/{slug}     - the canonical entity URI (content-negotiated)
 *   GET /data/{slug}   - an explicit alias for the same description
 *
 * Content negotiation (Accept header, lowest-priority default last):
 *   - application/ld+json   -> JSON-LD            (machine default)
 *   - text/turtle           -> Turtle
 *   - application/rdf+xml    -> RDF/XML
 *   - text/html (browser)   -> 303 See Other to the canonical /{slug} record
 *                              page, so a browser lands on the human view while
 *                              data clients get data. (Linked-Data "303
 *                              redirect" httpRange-14 pattern.)
 *
 * The description carries: title, type (schema.org + RiC additionalType),
 * identifier, dates (event display date / start-end span), creators (actors via
 * the event table), subjects (taxonomy 35) and places (taxonomy 42), the
 * holding repository (dcterms:publisher), the parent record
 * (dcterms:isPartOf), and rdfs:seeAlso links back to the graph neighbourhood
 * endpoint, the public record page, and the per-entity dataset surfaces. The
 * @id is the /id/{slug} URI itself, so the entity is its own stable name.
 *
 * Every URI is built from url() - never a hardcoded host - so a fresh install
 * on its own domain self-describes. Jurisdiction-neutral: standards-based
 * vocabularies (schema.org, RiC, CIDOC-CRM, Dublin Core) with no market
 * assumptions.
 *
 * Honest + safe: read-only; enforces the SAME publication-status gate as the
 * rest of the public v1 API (status.type_id=158, status_id=160 = Published),
 * excludes the synthetic root (id=1); an unknown or unpublished slug yields a
 * clean 404 in the negotiated media type, never a 500. Every enrichment query
 * is guarded (Schema::hasTable + try/catch) so a schema variance degrades to a
 * thinner description rather than an error.
 *
 * The RDF serialisations reuse GraphSerializerService (the single source for
 * the @context, the namespace table and the Turtle / RDF-XML rendering), so
 * the three formats can never drift from each other or from the rest of the
 * protocol.
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

class EntityController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    /** Subject access-point taxonomy id (dcterms:subject). */
    private const TAXONOMY_SUBJECT = 35;

    /** Place access-point taxonomy id (dcterms:spatial). */
    private const TAXONOMY_PLACE = 42;

    protected string $culture = 'en';

    protected GraphSerializerService $serializer;

    public function __construct(GraphSerializerService $serializer)
    {
        $this->culture = app()->getLocale() ?: 'en';
        $this->serializer = $serializer;
    }

    /**
     * OPTIONS preflight for the entity endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /id/{slug}  (alias GET /data/{slug})
     *
     * Returns the full Linked-Data description of ONE published record, the
     * format chosen by the Accept header. For an HTML (browser) request,
     * 303-redirects to the canonical human record page.
     */
    public function show(Request $request, string $slug): Response
    {
        $format = $this->negotiateFormat($request);

        // A browser / human request: send them to the canonical record page via
        // a 303 See Other (the Linked-Data idiom for "the document about this
        // thing lives over there"). We still verify the slug is a published
        // record first, so an unknown slug 404s rather than redirecting blindly.
        if ($format === 'html') {
            $node = $this->loadNode($slug);
            if (! $node) {
                return $this->notFound($slug, 'text/html');
            }

            return $this->withCors(
                redirect()->to($this->recordPublicUrl($slug), 303)
            );
        }

        $node = $this->loadNode($slug);
        if (! $node) {
            return $this->notFound($slug, $this->contentTypeFor($format));
        }

        // Build the neutral graph array once; all three serialisations derive
        // from it so the formats can never drift.
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

        // JSON-LD (default).
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
     * Build the JSON-LD document for one record as a neutral PHP array: the
     * shared @context (extended with the descriptive predicates this view
     * adds) plus an @graph holding the entity node. Every value is guarded so
     * a missing enrichment simply omits a key.
     *
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    protected function buildGraph(string $slug, array $node): array
    {
        $entityUri = $this->entityUri($slug);
        $id = (int) $node['id'];

        $entity = [
            '@id' => $entityUri,
            '@type' => $this->schemaType($node['level']),
            'name' => (string) ($node['title'] ?? '[Untitled]'),
        ];

        $ricType = $this->ricTypeForLevel($node['level']);
        if ($ricType !== null) {
            $entity['additionalType'] = $ricType;
        }
        if (! empty($node['identifier'])) {
            $entity['identifier'] = (string) $node['identifier'];
        }
        $abstract = $this->plainText((string) ($node['scope_and_content'] ?? ''));
        if ($abstract !== '') {
            $entity['description'] = $abstract;
        }
        if (! empty($node['updated_at'])) {
            $entity['dateModified'] = (string) $node['updated_at'];
        }

        // Dates (event display date / start-end span) -> schema:temporalCoverage.
        $dates = $this->dates($id);
        if ($dates) {
            $entity['temporalCoverage'] = count($dates) === 1 ? $dates[0] : array_values($dates);
        }

        // Creators (actors linked through the event table) -> dcterms:creator.
        $creators = $this->creators($id);
        if ($creators) {
            $entity['creator'] = count($creators) === 1 ? $creators[0] : array_values($creators);
        }

        // Subject access points (taxonomy 35) -> dcterms:subject.
        $subjects = $this->terms($id, self::TAXONOMY_SUBJECT);
        if ($subjects) {
            $entity['subject'] = count($subjects) === 1 ? $subjects[0] : array_values($subjects);
        }

        // Place access points (taxonomy 42) -> dcterms:spatial.
        $places = $this->terms($id, self::TAXONOMY_PLACE);
        if ($places) {
            $entity['spatial'] = count($places) === 1 ? $places[0] : array_values($places);
        }

        // Holding repository -> dcterms:publisher.
        $publisher = $this->publisher($node['repository_id'] ?? null);
        if ($publisher !== null) {
            $entity['publisher'] = $publisher;
        }

        // Parent record -> dcterms:isPartOf (a dereferenceable entity URI).
        $parent = $this->parentEntityUri((int) ($node['parent_id'] ?? 0));
        if ($parent !== null) {
            $entity['isPartOf'] = $parent;
        }

        // Discovery links (rdfs:seeAlso): the canonical record page, the graph
        // neighbourhood endpoint, and the per-entity dataset surfaces. Every
        // entity is therefore a hub back into the rest of the protocol.
        $seeAlso = array_values(array_filter([
            $this->recordPublicUrl($slug),
            $this->graphUri($id),
            $this->graphUri($id).'.ttl',
        ]));
        if ($seeAlso) {
            $entity['seeAlso'] = $seeAlso;
        }

        // schema:sameAs to the canonical public record page (human view).
        $entity['sameAs'] = $this->recordPublicUrl($slug);

        $context = array_merge($this->serializer->context(), [
            'dcterms' => 'http://purl.org/dc/terms/',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'temporalCoverage' => 'schema:temporalCoverage',
            'creator' => ['@id' => 'dcterms:creator'],
            'subject' => ['@id' => 'dcterms:subject'],
            'spatial' => ['@id' => 'dcterms:spatial'],
            'publisher' => ['@id' => 'dcterms:publisher'],
            'isPartOf' => ['@id' => 'dcterms:isPartOf', '@type' => '@id'],
            'seeAlso' => ['@id' => 'rdfs:seeAlso', '@type' => '@id'],
        ]);

        return [
            '@context' => $context,
            '@graph' => [$entity],
        ];
    }

    // -----------------------------------------------------------------
    // Node loading + publication-status gate
    // -----------------------------------------------------------------

    /**
     * Resolve a slug to its published record, enforcing the published-only gate.
     * Returns null for an unknown slug OR an unpublished one (never leaks
     * drafts). Resilient: a schema variance yields null, not an exception.
     *
     * @return array<string,mixed>|null
     */
    protected function loadNode(string $slug): ?array
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('slug')) {
                return null;
            }

            $row = DB::table('slug as s')
                ->join('information_object as io', 'io.id', '=', 's.object_id')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
                })
                ->leftJoin('object as o', 'io.id', '=', 'o.id')
                ->leftJoin('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION);
                })
                ->where('s.slug', $slug)
                ->where('io.id', '!=', self::ROOT_ID)
                ->select(
                    'io.id',
                    'io.identifier',
                    'io.level_of_description_id',
                    'io.repository_id',
                    'io.parent_id',
                    'i18n.title',
                    'i18n.scope_and_content',
                    'st.status_id',
                    'o.updated_at'
                )
                ->first();
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
            'identifier' => $row->identifier,
            'title' => $row->title,
            'scope_and_content' => $row->scope_and_content,
            'level' => $this->termName($row->level_of_description_id),
            'repository_id' => $row->repository_id,
            'parent_id' => $row->parent_id,
            'updated_at' => $row->updated_at,
        ];
    }

    // -----------------------------------------------------------------
    // Per-record enrichments (best-effort, guarded)
    // -----------------------------------------------------------------

    /**
     * Creator names (actors linked via the event table). dcterms:creator.
     *
     * @return array<int,string>
     */
    protected function creators(int $objectId): array
    {
        try {
            return DB::table('event')
                ->join('actor_i18n', function ($j) {
                    $j->on('event.actor_id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', $this->culture);
                })
                ->where('event.object_id', $objectId)
                ->whereNotNull('event.actor_id')
                ->whereNotNull('actor_i18n.authorized_form_of_name')
                ->distinct()
                ->pluck('actor_i18n.authorized_form_of_name')
                ->filter()
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Display dates (event display date, else a start/end span). Best-effort.
     *
     * @return array<int,string>
     */
    protected function dates(int $objectId): array
    {
        try {
            $rows = DB::table('event as e')
                ->leftJoin('event_i18n as ei', function ($j) {
                    $j->on('e.id', '=', 'ei.id')->where('ei.culture', $this->culture);
                })
                ->where('e.object_id', $objectId)
                ->select('ei.date as display_date', 'e.start_date', 'e.end_date')
                ->get();

            $dates = [];
            foreach ($rows as $r) {
                if (! empty($r->display_date)) {
                    $dates[] = (string) $r->display_date;
                } elseif (! empty($r->start_date)) {
                    $dates[] = $this->trimDate((string) $r->start_date)
                        .(! empty($r->end_date) ? '/'.$this->trimDate((string) $r->end_date) : '');
                }
            }

            return array_values(array_unique(array_filter($dates)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The holding repository's authorised name (dcterms:publisher).
     */
    protected function publisher($repositoryId): ?string
    {
        if (empty($repositoryId)) {
            return null;
        }

        try {
            $name = DB::table('repository as r')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('r.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('r.id', (int) $repositoryId)
                ->value('ai.authorized_form_of_name');

            return $name ? (string) $name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Access-point term names for a record within one taxonomy (35 = subjects,
     * 42 = places). Best-effort - [] on a schema variance.
     *
     * @return array<int,string>
     */
    protected function terms(int $objectId, int $taxonomyId): array
    {
        try {
            return DB::table('object_term_relation as otr')
                ->join('term as t', 'otr.term_id', '=', 't.id')
                ->join('term_i18n as ti', function ($j) {
                    $j->on('otr.term_id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->where('otr.object_id', $objectId)
                ->where('t.taxonomy_id', $taxonomyId)
                ->whereNotNull('ti.name')
                ->distinct()
                ->pluck('ti.name')
                ->filter()
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The parent record's entity URI (dcterms:isPartOf), only when the parent
     * is itself a published, non-root record. Returns null otherwise so a
     * draft / root parent is never disclosed.
     */
    protected function parentEntityUri(int $parentId): ?string
    {
        if ($parentId <= self::ROOT_ID) {
            return null;
        }

        try {
            $row = DB::table('information_object as io')
                ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
                ->leftJoin('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION);
                })
                ->where('io.id', $parentId)
                ->select('s.slug', 'st.status_id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row || empty($row->slug)) {
            return null;
        }
        if ((int) $row->status_id !== self::STATUS_PUBLISHED) {
            return null;
        }

        return $this->entityUri((string) $row->slug);
    }

    protected function termName($termId): ?string
    {
        if (empty($termId)) {
            return null;
        }

        try {
            return DB::table('term_i18n')
                ->where('id', (int) $termId)
                ->where('culture', $this->culture)
                ->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Content negotiation
    // -----------------------------------------------------------------

    /**
     * Resolve the wire format from the Accept header (plus a ?format= override
     * for convenience). One of: 'jsonld', 'turtle', 'rdfxml', 'html'.
     *
     * Precedence: explicit ?format= wins; then the Accept header is scanned for
     * an RDF media type, then for an explicit text/html. The DEFAULT is JSON-LD
     * (a machine endpoint), EXCEPT when a browser clearly asks for HTML.
     */
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

        // A browser sends "text/html,..." (often with */*). Honour an explicit
        // text/html preference by sending the human to the record page. An
        // Accept of only */* (curl's default) falls through to the JSON-LD
        // machine default below.
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
     * The canonical, stable entity URI for a slug (this very endpoint).
     */
    protected function entityUri(string $slug): string
    {
        return $this->endpointBase().'/id/'.ltrim($slug, '/');
    }

    /**
     * The per-entity graph-neighbourhood URL (GraphController), for seeAlso.
     */
    protected function graphUri(int $objectId): string
    {
        return $this->endpointBase().'/api/v1/graph/'.$objectId;
    }

    /**
     * The canonical public record page (slug-based) on this host.
     */
    protected function recordPublicUrl(string $slug): string
    {
        return $this->endpointBase().'/'.ltrim($slug, '/');
    }

    protected function endpointBase(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Map a level-of-description label to a schema.org type (mirrors
     * GraphController / DatasetController so the open surfaces stay consistent).
     */
    protected function schemaType(?string $level): string
    {
        $l = strtolower((string) $level);
        if (str_contains($l, 'collection') || str_contains($l, 'fonds')) {
            return 'schema:Collection';
        }
        if (str_contains($l, 'item')) {
            return 'schema:CreativeWork';
        }

        return 'schema:ArchiveComponent';
    }

    /**
     * RiC ontology type CURIE for the record, carried as schema:additionalType.
     */
    protected function ricTypeForLevel(?string $level): ?string
    {
        $l = strtolower((string) $level);
        if ($l === '') {
            return 'rico:Record';
        }
        if (str_contains($l, 'fonds') || str_contains($l, 'collection')) {
            return 'rico:RecordSet';
        }

        return 'rico:Record';
    }

    // -----------------------------------------------------------------
    // Text helpers
    // -----------------------------------------------------------------

    /**
     * Strip HTML and collapse whitespace for a clean literal.
     */
    protected function plainText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }

    /**
     * Trim AtoM-style "-00" month/day placeholders so "1923-00-00" reads "1923".
     */
    protected function trimDate(string $value): string
    {
        $value = trim($value);
        $value = (string) preg_replace('/-00(-00)?$/', '', $value);

        return (string) preg_replace('/-00$/', '', $value);
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    /**
     * A clean, negotiated 404 for an unknown/unpublished slug. Never a 500.
     */
    protected function notFound(string $slug, string $contentType = 'application/ld+json'): Response
    {
        if (str_contains($contentType, 'turtle')) {
            return $this->withCors(response(
                "# Not Found: '".str_replace("\n", ' ', $slug)."' is not a published record.\n",
                404,
                ['Content-Type' => 'text/turtle; charset=utf-8']
            ));
        }

        if (str_contains($contentType, 'rdf+xml')) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n"
                .'  <!-- Not Found: '.htmlspecialchars($slug, ENT_XML1, 'UTF-8').' is not a published record. -->'."\n"
                .'</rdf:RDF>'."\n";

            return $this->withCors(response($body, 404, ['Content-Type' => 'application/rdf+xml; charset=utf-8']));
        }

        if (str_contains($contentType, 'html')) {
            return $this->withCors(response(
                '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                .'<title>Not found</title></head><body><h1>404 Not found</h1>'
                .'<p>No published record matches that identifier.</p></body></html>',
                404,
                ['Content-Type' => 'text/html; charset=utf-8']
            ));
        }

        $body = json_encode([
            '@context' => ['schema' => 'https://schema.org/'],
            '@type' => 'schema:Error',
            'error' => 'Not Found',
            'message' => "No published record for '".$slug."'.",
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
