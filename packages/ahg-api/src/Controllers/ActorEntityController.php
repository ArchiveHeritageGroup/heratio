<?php

/**
 * ActorEntityController - content-negotiated Linked-Data identity for ACTORS.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). Where EntityController gives every published RECORD a stable,
 * dereferenceable Linked-Data identity, this controller does the same for the
 * ENTITIES that records are about - people, corporate bodies and families -
 * so the open memory graph covers actors, not just records:
 *
 *   GET /id/actor/{slug}    - the canonical actor URI (content-negotiated)
 *   GET /data/actor/{slug}  - an explicit alias for the same description
 *
 * Content negotiation (Accept header, lowest-priority default last):
 *   - application/ld+json   -> JSON-LD  (machine default)
 *   - text/turtle           -> Turtle
 *   - application/rdf+xml   -> RDF/XML
 *   - text/html (browser)   -> 303 See Other to the canonical /actor/{slug}
 *                              authority page (httpRange-14 303 idiom).
 *
 * The description carries: the authorised form of name (schema:name), the
 * actor type (schema.org Person / Organization, plus a RiC additionalType
 * derived from the entity_type taxonomy term), dates of existence
 * (schema:temporalCoverage), a biography / administrative-history note
 * (schema:description, derived from actor_i18n.history), the related PUBLISHED
 * records this actor created or is otherwise linked to (dcterms:relation, each
 * a dereferenceable record entity URI), and rdfs:seeAlso / schema:sameAs links
 * back to the human authority page and the RiC agent JSON-LD export.
 *
 * Actors are REFERENCE entities (an authority file), so the actor row itself
 * has no publication-status gate - but every record it links out to is filtered
 * through the SAME published-only gate as the rest of the public v1 API
 * (status.type_id=158, status_id=160), so a draft record is never disclosed
 * through an actor's relations.
 *
 * Every URI is built from url() - never a hardcoded host - so a fresh install
 * on its own domain self-describes. Jurisdiction-neutral: standards-based
 * vocabularies (schema.org, RiC, Dublin Core) with no market assumptions.
 *
 * Honest + safe: read-only; an unknown actor slug yields a clean negotiated
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

class ActorEntityController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object / actor ids, always excluded. */
    private const ROOT_ID = 1;

    /** entity_type taxonomy term ids (ISAAR(CPF) entity type). */
    private const ENTITY_CORPORATE_BODY = 131;

    private const ENTITY_PERSON = 132;

    private const ENTITY_FAMILY = 133;

    /** Cap on related records listed, keeps the document bounded. */
    private const MAX_RELATED = 200;

    protected string $culture = 'en';

    protected GraphSerializerService $serializer;

    public function __construct(GraphSerializerService $serializer)
    {
        $this->culture = app()->getLocale() ?: 'en';
        $this->serializer = $serializer;
    }

    /**
     * OPTIONS preflight for the actor entity endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /id/actor/{slug}  (alias GET /data/actor/{slug})
     *
     * The full Linked-Data description of ONE actor, format chosen by Accept.
     * A browser is 303-redirected to the canonical human authority page.
     */
    public function show(Request $request, string $slug): Response
    {
        $format = $this->negotiateFormat($request);

        if ($format === 'html') {
            $node = $this->loadActor($slug);
            if (! $node) {
                return $this->notFound($slug, 'text/html');
            }

            return $this->withCors(
                redirect()->to($this->actorPublicUrl($slug), 303)
            );
        }

        $node = $this->loadActor($slug);
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
     * Build the JSON-LD document for one actor as a neutral PHP array.
     *
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    protected function buildGraph(string $slug, array $node): array
    {
        $entityUri = $this->actorUri($slug);
        $id = (int) $node['id'];

        $entity = [
            '@id' => $entityUri,
            '@type' => $this->schemaType((int) ($node['entity_type_id'] ?? 0)),
            'name' => (string) ($node['name'] ?? '[Unnamed actor]'),
        ];

        $ricType = $this->ricType((int) ($node['entity_type_id'] ?? 0));
        if ($ricType !== null) {
            $entity['additionalType'] = $ricType;
        }

        // Dates of existence -> schema:temporalCoverage.
        $dates = $this->plainText((string) ($node['dates_of_existence'] ?? ''));
        if ($dates !== '') {
            $entity['temporalCoverage'] = $dates;
        }

        // Biography / administrative history -> schema:description.
        $history = $this->plainText((string) ($node['history'] ?? ''));
        if ($history !== '') {
            $entity['description'] = $history;
        }

        // Related PUBLISHED records (creator links via the event table, plus
        // generic relation-table links) -> dcterms:relation, each a record URI.
        $related = $this->relatedRecordUris($id);
        if ($related) {
            $entity['relation'] = count($related) === 1 ? $related[0] : array_values($related);
        }

        // Discovery links (rdfs:seeAlso): the human authority page and the RiC
        // agent JSON-LD export, so the actor is a hub back into the platform.
        $seeAlso = array_values(array_filter([
            $this->actorPublicUrl($slug),
            $this->ricAgentUri($slug),
        ]));
        if ($seeAlso) {
            $entity['seeAlso'] = $seeAlso;
        }

        // schema:sameAs to the canonical public authority page (human view).
        $entity['sameAs'] = $this->actorPublicUrl($slug);

        $context = array_merge($this->serializer->context(), [
            'dcterms' => 'http://purl.org/dc/terms/',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'temporalCoverage' => 'schema:temporalCoverage',
            'relation' => ['@id' => 'dcterms:relation', '@type' => '@id'],
            'seeAlso' => ['@id' => 'rdfs:seeAlso', '@type' => '@id'],
        ]);

        return [
            '@context' => $context,
            '@graph' => [$entity],
        ];
    }

    // -----------------------------------------------------------------
    // Actor loading
    // -----------------------------------------------------------------

    /**
     * Resolve a slug to an actor row. Actors are reference entities (no
     * publication gate), but the synthetic root and repositories are excluded
     * so this endpoint describes only people / corporate bodies / families.
     * Resilient: a schema variance yields null, not an exception.
     *
     * @return array<string,mixed>|null
     */
    protected function loadActor(string $slug): ?array
    {
        try {
            if (! Schema::hasTable('actor') || ! Schema::hasTable('slug')) {
                return null;
            }

            $query = DB::table('slug as s')
                ->join('actor as a', 'a.id', '=', 's.object_id')
                ->leftJoin('actor_i18n as ai', function ($j) {
                    $j->on('a.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('s.slug', $slug)
                ->where('a.id', '!=', self::ROOT_ID);

            // Exclude repositories (a sibling actor subtype with its own
            // ISDIAH surface) when that table is present, so /id/actor/ stays
            // people / corporate bodies / families.
            if (Schema::hasTable('repository')) {
                $query->whereNotIn('a.id', function ($sub) {
                    $sub->from('repository')->select('id');
                });
            }

            $row = $query->select(
                'a.id',
                'a.entity_type_id',
                'ai.authorized_form_of_name as name',
                'ai.dates_of_existence',
                'ai.history'
            )->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'entity_type_id' => $row->entity_type_id,
            'name' => $row->name,
            'dates_of_existence' => $row->dates_of_existence,
            'history' => $row->history,
        ];
    }

    // -----------------------------------------------------------------
    // Related records (published-only gate applied here)
    // -----------------------------------------------------------------

    /**
     * Entity URIs of PUBLISHED records linked to this actor: records it is a
     * named agent of (via the event table) plus generic relation-table links
     * where the actor is the subject or object. Every record is filtered
     * through the published-only gate so a draft is never leaked. Returns
     * dereferenceable /id/{slug} record URIs.
     *
     * @return array<int,string>
     */
    protected function relatedRecordUris(int $actorId): array
    {
        $objectIds = [];

        // Records this actor is a named agent of (creator etc.) via the event
        // table.
        try {
            if (Schema::hasTable('event')) {
                $ids = DB::table('event')
                    ->where('actor_id', $actorId)
                    ->whereNotNull('object_id')
                    ->distinct()
                    ->pluck('object_id')
                    ->all();
                foreach ($ids as $id) {
                    $objectIds[(int) $id] = true;
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        // Generic relation-table links where the actor appears on either side
        // and the OTHER side is an information_object.
        try {
            if (Schema::hasTable('relation') && Schema::hasTable('information_object')) {
                $subjectSide = DB::table('relation as r')
                    ->join('information_object as io', 'io.id', '=', 'r.object_id')
                    ->where('r.subject_id', $actorId)
                    ->distinct()
                    ->pluck('io.id')
                    ->all();
                $objectSide = DB::table('relation as r')
                    ->join('information_object as io', 'io.id', '=', 'r.subject_id')
                    ->where('r.object_id', $actorId)
                    ->distinct()
                    ->pluck('io.id')
                    ->all();
                foreach (array_merge($subjectSide, $objectSide) as $id) {
                    $objectIds[(int) $id] = true;
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        if (! $objectIds) {
            return [];
        }

        return $this->publishedRecordUris(array_keys($objectIds));
    }

    /**
     * Given a set of candidate information_object ids, return the entity URIs
     * of those that are published, non-root and have a slug. The published-only
     * gate lives here so no draft slips through any actor relation.
     *
     * @param  array<int,int>  $objectIds
     * @return array<int,string>
     */
    protected function publishedRecordUris(array $objectIds): array
    {
        $objectIds = array_values(array_unique(array_filter(array_map('intval', $objectIds), fn ($v) => $v > self::ROOT_ID)));
        if (! $objectIds) {
            return [];
        }

        try {
            $rows = DB::table('information_object as io')
                ->join('slug as s', 's.object_id', '=', 'io.id')
                ->join('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                        ->where('st.status_id', '=', self::STATUS_PUBLISHED);
                })
                ->whereIn('io.id', $objectIds)
                ->where('io.id', '!=', self::ROOT_ID)
                ->orderBy('io.id')
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
     * The canonical, stable actor entity URI for a slug (this endpoint).
     */
    protected function actorUri(string $slug): string
    {
        return $this->endpointBase().'/id/actor/'.ltrim($slug, '/');
    }

    /**
     * A record's entity URI (EntityController surface), for dcterms:relation.
     */
    protected function recordEntityUri(string $slug): string
    {
        return $this->endpointBase().'/id/'.ltrim($slug, '/');
    }

    /**
     * The canonical public authority page for the actor (human view).
     */
    protected function actorPublicUrl(string $slug): string
    {
        return $this->endpointBase().'/actor/'.ltrim($slug, '/');
    }

    /**
     * The RiC agent JSON-LD export URL (ahg-ric API), for seeAlso.
     */
    protected function ricAgentUri(string $slug): string
    {
        return $this->endpointBase().'/api/ric/v1/agents/'.ltrim($slug, '/');
    }

    protected function endpointBase(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Map the ISAAR(CPF) entity type to a schema.org class. A corporate body
     * is a schema:Organization; a person is a schema:Person; a family has no
     * native schema.org class, so it falls back to the broad schema:Person
     * grouping with a precise RiC additionalType carrying the real distinction.
     */
    protected function schemaType(int $entityTypeId): string
    {
        return match ($entityTypeId) {
            self::ENTITY_CORPORATE_BODY => 'schema:Organization',
            self::ENTITY_PERSON => 'schema:Person',
            self::ENTITY_FAMILY => 'schema:Person',
            default => 'schema:Thing',
        };
    }

    /**
     * RiC ontology type CURIE for the actor, carried as schema:additionalType.
     * RiC models all three as rico:Agent subclasses.
     */
    protected function ricType(int $entityTypeId): ?string
    {
        return match ($entityTypeId) {
            self::ENTITY_CORPORATE_BODY => 'rico:CorporateBody',
            self::ENTITY_PERSON => 'rico:Person',
            self::ENTITY_FAMILY => 'rico:Family',
            default => 'rico:Agent',
        };
    }

    // -----------------------------------------------------------------
    // Text helpers
    // -----------------------------------------------------------------

    protected function plainText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    protected function notFound(string $slug, string $contentType = 'application/ld+json'): Response
    {
        if (str_contains($contentType, 'turtle')) {
            return $this->withCors(response(
                "# Not Found: '".str_replace("\n", ' ', $slug)."' is not a known actor.\n",
                404,
                ['Content-Type' => 'text/turtle; charset=utf-8']
            ));
        }

        if (str_contains($contentType, 'rdf+xml')) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n"
                .'  <!-- Not Found: '.htmlspecialchars($slug, ENT_XML1, 'UTF-8').' is not a known actor. -->'."\n"
                .'</rdf:RDF>'."\n";

            return $this->withCors(response($body, 404, ['Content-Type' => 'application/rdf+xml; charset=utf-8']));
        }

        if (str_contains($contentType, 'html')) {
            return $this->withCors(response(
                '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                .'<title>Not found</title></head><body><h1>404 Not found</h1>'
                .'<p>No actor matches that identifier.</p></body></html>',
                404,
                ['Content-Type' => 'text/html; charset=utf-8']
            ));
        }

        $body = json_encode([
            '@context' => ['schema' => 'https://schema.org/'],
            '@type' => 'schema:Error',
            'error' => 'Not Found',
            'message' => "No actor for '".$slug."'.",
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
