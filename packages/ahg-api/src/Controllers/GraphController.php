<?php

/**
 * GraphController - Open Memory Protocol public Linked-Data endpoint.
 *
 * First slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). Exposes a single archival record's graph neighbourhood as
 * open, read-only Linked Data so any external agent or application can fetch
 * a record's connected entities without an API key.
 *
 * GET /api/v1/graph/{idOrSlug}
 *
 *   - Resolves the record by numeric id or slug.
 *   - Assembles the node itself (title, type, identifier, slug/URI) plus its
 *     cross-collection neighbours via the unified G/L/A/M graph built in
 *     ahg-ric (RelationshipService::crossCollectionNeighbours), each with a
 *     stable @id URI that resolves back to this same endpoint (crawlable).
 *   - Content negotiation: JSON-LD by default (schema.org / rico / crm
 *     @context + an @graph of node + neighbours); Turtle on request, reusing
 *     ahg-ric's CrmSerializer for the CIDOC-CRM view of the node.
 *   - Open data: permissive CORS (Access-Control-Allow-Origin: *), no auth.
 *   - Honest + safe: read-only; respects the same publication-status gate as
 *     the rest of the public v1 API (status.type_id=158, status_id=160 =
 *     Published) so unpublished drafts are never leaked; 404 for unknown ids;
 *     neighbour count is bounded.
 *
 * Jurisdiction-neutral: emits standards-based Linked Data (schema.org, RiC,
 * CIDOC-CRM) with no market-specific assumptions.
 *
 * This controller only READS from ahg-ric services (resolved via app(...));
 * it does not modify any other package.
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
use Symfony\Component\HttpFoundation\Response;

class GraphController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Hard cap on neighbours emitted, so the open endpoint stays cheap. */
    private const MAX_NEIGHBOURS = 200;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    /**
     * GET /api/v1/graph/{idOrSlug}
     *
     * Returns the record's graph neighbourhood as Linked Data.
     *
     * Format selection (in priority order):
     *   1. ?format=jsonld|json-ld|turtle|ttl|crm  query param
     *   2. Accept header (text/turtle -> Turtle, application/ld+json -> JSON-LD)
     *   3. Default: JSON-LD
     */
    public function show(Request $request, string $idOrSlug): Response
    {
        $format = $this->negotiateFormat($request);

        // Resolve numeric id or slug to an object id.
        $objectId = is_numeric($idOrSlug)
            ? (int) $idOrSlug
            : (int) DB::table('slug')->where('slug', $idOrSlug)->value('object_id');

        if (! $objectId) {
            return $this->notFound($idOrSlug);
        }

        // Load the node itself (and enforce the publication-status gate). Only
        // published archival descriptions are exposed as open data.
        $node = $this->loadNode($objectId);
        if (! $node) {
            return $this->notFound($idOrSlug);
        }

        // Turtle: hand off to ahg-ric's CrmSerializer for the CIDOC-CRM view of
        // the node (creators, time-spans, repository, subjects, places). This
        // is the canonical RDF rendering of the record graph; read-only call.
        if ($format === 'turtle') {
            return $this->turtleResponse($objectId);
        }

        // JSON-LD (default): node + cross-collection neighbours as an @graph.
        return $this->jsonLdResponse($node);
    }

    /**
     * OPTIONS preflight for the open endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    // -----------------------------------------------------------------
    // Format negotiation
    // -----------------------------------------------------------------

    protected function negotiateFormat(Request $request): string
    {
        $param = strtolower((string) $request->query('format', ''));
        if (in_array($param, ['turtle', 'ttl', 'crm', 'rdf'], true)) {
            return 'turtle';
        }
        if (in_array($param, ['jsonld', 'json-ld', 'json'], true)) {
            return 'jsonld';
        }

        // Fall back to the Accept header.
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'text/turtle') || str_contains($accept, 'application/x-turtle')) {
            return 'turtle';
        }

        return 'jsonld';
    }

    // -----------------------------------------------------------------
    // Node loading + publication-status gate
    // -----------------------------------------------------------------

    /**
     * Load the central node, enforcing the published-only gate. Returns null
     * for a missing record OR an unpublished one (never leaks drafts).
     *
     * @return array<string,mixed>|null
     */
    protected function loadNode(int $objectId): ?array
    {
        $row = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
            })
            ->join('object as o', 'io.id', '=', 'o.id')
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION);
            })
            ->where('io.id', $objectId)
            ->where('io.id', '!=', 1) // exclude the synthetic root
            ->select(
                'io.id',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                'i18n.title',
                'i18n.scope_and_content',
                's.slug',
                'st.status_id',
                'o.updated_at'
            )
            ->first();

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
            'slug' => $row->slug,
            'level' => $this->termName($row->level_of_description_id),
            'updated_at' => $row->updated_at,
        ];
    }

    // -----------------------------------------------------------------
    // JSON-LD assembly
    // -----------------------------------------------------------------

    /**
     * Build the JSON-LD document: a schema.org / rico / crm @context plus an
     * @graph holding the node and its cross-collection neighbours. Every node
     * carries an @id that resolves back to this endpoint, so a consumer can
     * crawl outward.
     */
    protected function jsonLdResponse(array $node): Response
    {
        // Cross-collection neighbours from the unified graph (read-only).
        $neighbourGroups = $this->neighbourGroups($node['id']);

        $context = [
            '@vocab' => 'https://schema.org/',
            'schema' => 'https://schema.org/',
            'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            'crm' => 'http://www.cidoc-crm.org/cidoc-crm/',
            'dcterms' => 'http://purl.org/dc/terms/',
            // Project-local discovery predicate: "this node is related to".
            'omp' => $this->endpointBase() . '/ns#',
            'name' => 'schema:name',
            'identifier' => 'schema:identifier',
            'description' => 'schema:description',
            'additionalType' => ['@id' => 'schema:additionalType', '@type' => '@id'],
            'isRelatedTo' => ['@id' => 'omp:isRelatedTo', '@type' => '@id'],
            'relationshipDomain' => 'omp:relationshipDomain',
            'sameAs' => ['@id' => 'schema:sameAs', '@type' => '@id'],
            'dateModified' => 'schema:dateModified',
        ];

        $nodeUri = $this->graphUri($node['id']);
        $publicUrl = $this->recordPublicUrl($node);

        // The central node.
        $central = [
            '@id' => $nodeUri,
            '@type' => $this->schemaType($node['level']),
            'name' => $node['title'],
        ];
        if (! empty($node['identifier'])) {
            $central['identifier'] = $node['identifier'];
        }
        if (! empty($node['scope_and_content'])) {
            $central['description'] = $node['scope_and_content'];
        }
        if (! empty($node['level'])) {
            $central['additionalType'] = $this->ricTypeForLevel($node['level']);
        }
        if (! empty($node['updated_at'])) {
            $central['dateModified'] = (string) $node['updated_at'];
        }
        // Discovery: link the canonical public record page too.
        if ($publicUrl) {
            $central['sameAs'] = $publicUrl;
        }

        // Collect neighbour @ids onto the central node (isRelatedTo) and build
        // a thin typed node per neighbour in the @graph.
        $related = [];
        $graph = [$central];
        $emitted = 0;

        foreach ($neighbourGroups as $group) {
            foreach ($group['items'] as $item) {
                if ($emitted >= self::MAX_NEIGHBOURS) {
                    break 2;
                }
                $nUri = $this->graphUri((int) $item['id']);
                $related[] = $nUri;

                $neighbourNode = [
                    '@id' => $nUri,
                    '@type' => 'schema:Thing',
                    'name' => $item['name'],
                    'relationshipDomain' => $group['domain'],
                ];
                // Slug-bearing neighbours get a resolvable public sameAs too.
                if (! empty($item['slug'])) {
                    $neighbourNode['sameAs'] = $this->endpointBase() . '/' . ltrim((string) $item['slug'], '/');
                }
                $graph[] = $neighbourNode;
                $emitted++;
            }
        }

        if ($related) {
            $central['isRelatedTo'] = $related;
            $graph[0] = $central; // refresh the (array-copied) central node
        }

        $doc = [
            '@context' => $context,
            '@graph' => $graph,
        ];

        $body = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // Turtle (CIDOC-CRM) via ahg-ric CrmSerializer
    // -----------------------------------------------------------------

    protected function turtleResponse(int $objectId): Response
    {
        // Read-only call into ahg-ric. Guard the dependency so a missing
        // ahg-ric never 500s this open endpoint.
        $serializerClass = \AhgRic\Crm\CrmSerializer::class;
        if (! class_exists($serializerClass)) {
            return $this->withCors(response(
                "# Turtle (CIDOC-CRM) view unavailable: ahg-ric not installed.\n",
                501,
                ['Content-Type' => 'text/turtle; charset=utf-8']
            ));
        }

        /** @var \AhgRic\Crm\CrmSerializer $serializer */
        $serializer = app($serializerClass);
        $ttl = $serializer->serializeRecord(
            $objectId,
            $this->culture,
            \AhgRic\Crm\CrmSerializer::FORMAT_TURTLE
        );

        if ($ttl === '') {
            return $this->notFound((string) $objectId, 'text/turtle');
        }

        return $this->withCors(
            response($ttl, 200, ['Content-Type' => 'text/turtle; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // Neighbours (read-only call into ahg-ric)
    // -----------------------------------------------------------------

    /**
     * Fetch the cross-collection neighbour groups from ahg-ric's unified
     * graph. Returns [] when ahg-ric is absent or the call fails - the open
     * endpoint still returns the node itself.
     *
     * @return array<int,array{domain:string,items:array}>
     */
    protected function neighbourGroups(int $objectId): array
    {
        $serviceClass = \AhgRic\Services\RelationshipService::class;
        if (! class_exists($serviceClass)) {
            return [];
        }

        try {
            /** @var \AhgRic\Services\RelationshipService $svc */
            $svc = app($serviceClass);
            $result = $svc->crossCollectionNeighbours($objectId);

            return is_array($result['groups'] ?? null) ? $result['groups'] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------
    // URI + type helpers
    // -----------------------------------------------------------------

    /**
     * Stable @id for a graph node: resolves back to this very endpoint so a
     * consumer can crawl the graph by dereferencing each @id.
     */
    protected function graphUri(int $objectId): string
    {
        return $this->endpointBase() . '/api/v1/graph/' . $objectId;
    }

    protected function endpointBase(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Canonical public record page (slug-based) for schema:sameAs.
     */
    protected function recordPublicUrl(array $node): ?string
    {
        if (! empty($node['slug'])) {
            return $this->endpointBase() . '/' . ltrim((string) $node['slug'], '/');
        }

        return null;
    }

    /**
     * Map an archival level-of-description label to a schema.org type. The
     * RiC-precise type is carried separately as additionalType so both a
     * generic schema.org consumer and a RiC-aware one are served.
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
     * RiC ontology type CURIE for the record (carried as additionalType).
     */
    protected function ricTypeForLevel(?string $level): string
    {
        $l = strtolower((string) $level);
        if (str_contains($l, 'fonds') || str_contains($l, 'collection')) {
            return 'rico:RecordSet';
        }

        return 'rico:Record';
    }

    protected function termName(?int $termId): ?string
    {
        if (! $termId) {
            return null;
        }

        return DB::table('term_i18n')
            ->where('id', $termId)
            ->where('culture', $this->culture)
            ->value('name');
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    protected function notFound(string $idOrSlug, string $contentType = 'application/ld+json'): Response
    {
        if (str_contains($contentType, 'turtle')) {
            return $this->withCors(response(
                "# Not Found: '{$idOrSlug}' is not a published record.\n",
                404,
                ['Content-Type' => 'text/turtle; charset=utf-8']
            ));
        }

        $body = json_encode([
            '@context' => ['schema' => 'https://schema.org/'],
            '@type' => 'schema:Error',
            'error' => 'Not Found',
            'message' => "No published record for '{$idOrSlug}'.",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $this->withCors(
            response($body, 404, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    /**
     * Apply permissive open-data CORS headers. This endpoint is intentionally
     * world-readable (open data), so any origin may fetch it.
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        // Advertise the open licence stance for crawlers.
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }
}
