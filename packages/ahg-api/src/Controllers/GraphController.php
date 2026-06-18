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

use AhgApi\Services\GraphSerializerService;
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

    /** Default page size for the crawlable index; also the hard ceiling. */
    private const INDEX_PAGE_SIZE = 200;

    private const INDEX_MAX_PAGE_SIZE = 500;

    /**
     * Per-file ceiling for the XML sitemap. The sitemaps.org spec allows up to
     * 50000 URLs per <urlset>; we cap far lower so each sitemap stays cheap to
     * build and serve, paginating via a <sitemapindex> over child sitemaps.
     */
    private const SITEMAP_PAGE_SIZE = 5000;

    protected string $culture = 'en';

    protected GraphSerializerService $serializer;

    public function __construct(GraphSerializerService $serializer)
    {
        $this->culture = app()->getLocale() ?: 'en';
        $this->serializer = $serializer;
    }

    /**
     * GET /api/v1/graph/{idOrSlug}
     *
     * Returns the record's graph neighbourhood as Linked Data.
     *
     * Format selection (in priority order):
     *   1. Path suffix (.jsonld / .ttl / .rdf), passed in by the route.
     *   2. ?format=jsonld|json-ld|json|ttl|turtle|rdf|crm  query param
     *   3. Accept header (text/turtle -> Turtle, application/rdf+xml ->
     *      RDF/XML, application/ld+json or application/json -> JSON-LD)
     *   4. Default: JSON-LD
     *
     * The JSON-LD (default) response is byte-for-byte the historical shape, so
     * existing callers are unaffected.
     */
    public function show(Request $request, string $idOrSlug, ?string $suffix = null): Response
    {
        $format = $this->negotiateFormat($request, $suffix);

        // Resolve numeric id or slug to an object id.
        $objectId = is_numeric($idOrSlug)
            ? (int) $idOrSlug
            : (int) DB::table('slug')->where('slug', $idOrSlug)->value('object_id');

        if (! $objectId) {
            return $this->notFound($idOrSlug, $this->contentTypeFor($format));
        }

        // Load the node itself (and enforce the publication-status gate). Only
        // published archival descriptions are exposed as open data.
        $node = $this->loadNode($objectId);
        if (! $node) {
            return $this->notFound($idOrSlug, $this->contentTypeFor($format));
        }

        // Build the neutral graph array once; every serialisation derives from
        // it so the formats can never drift.
        $graph = $this->buildGraph($node);

        // Turtle: prefer ahg-ric's CrmSerializer for the richer CIDOC-CRM view
        // of the node (creators, time-spans, repository, subjects, places).
        // Fall back to the in-package serializer when ahg-ric is absent, so the
        // open endpoint never 501s for a published record.
        if ($format === 'turtle') {
            $ric = $this->ricTurtle($objectId);
            if ($ric !== null) {
                return $this->withCors(
                    response($ric, 200, ['Content-Type' => 'text/turtle; charset=utf-8'])
                );
            }

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

        // JSON-LD (default): node + cross-collection neighbours as an @graph.
        $body = json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    /**
     * GET /api/v1/graph/{idOrSlug}/federated
     *
     * LIVE cross-peer graph aggregation (Federation Query Protocol, north-star
     * #1204). Returns the local record's graph neighbourhood merged with the
     * SAME record's neighbourhood fetched live from every active federation
     * peer's Open Memory Protocol endpoint. Each node carries a `source_peer`
     * (null = local) so provenance is preserved, and a `federation` block
     * reports the peers queried, per-peer node counts, and any warnings.
     *
     * Delegates to ahg-federation's FederationGraphService, which mirrors
     * FederatedSearchService's curl_multi parallel-fetch + SSRF guard. It fails
     * soft: a dead peer or zero peers yields just the local graph + warnings,
     * never a 500. If ahg-federation is absent the response degrades to the
     * plain local graph with a warning. Open data; permissive CORS; JSON-LD.
     */
    public function federated(Request $request, string $idOrSlug): Response
    {
        $serviceClass = \AhgFederation\Services\FederationGraphService::class;

        if (! class_exists($serviceClass)) {
            // ahg-federation not installed: degrade to the local graph so the
            // endpoint never hard-fails on a slimmer install.
            $objectId = is_numeric($idOrSlug)
                ? (int) $idOrSlug
                : (int) DB::table('slug')->where('slug', $idOrSlug)->value('object_id');

            $node = $objectId ? $this->loadNode($objectId) : null;
            if (! $node) {
                return $this->notFound($idOrSlug, 'application/ld+json');
            }

            $graph = $this->buildGraph($node);
            $graph['federation'] = [
                'mode'          => 'live',
                'reference'     => $idOrSlug,
                'peers_queried' => 0,
                'peers'         => [],
                'warnings'      => ['Federation is not installed; returning the local graph only.'],
            ];

            $body = json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $this->withCors(
                response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
            );
        }

        try {
            /** @var \AhgFederation\Services\FederationGraphService $svc */
            $svc = app($serviceClass);
            $aggregated = $svc->aggregate($idOrSlug);
        } catch (\Throwable $e) {
            // Last-resort fail-soft: never 500. Fall back to the local graph.
            $objectId = is_numeric($idOrSlug)
                ? (int) $idOrSlug
                : (int) DB::table('slug')->where('slug', $idOrSlug)->value('object_id');

            $node = $objectId ? $this->loadNode($objectId) : null;
            if (! $node) {
                return $this->notFound($idOrSlug, 'application/ld+json');
            }

            $aggregated = $this->buildGraph($node);
            $aggregated['federation'] = [
                'mode'          => 'live',
                'reference'     => $idOrSlug,
                'peers_queried' => 0,
                'peers'         => [],
                'warnings'      => ['Federation aggregation failed: ' . $e->getMessage()],
            ];
        }

        $body = json_encode($aggregated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    /**
     * OPTIONS preflight for the open endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    // -----------------------------------------------------------------
    // Protocol front door: dataset description (VoID / DCAT-ish)
    // -----------------------------------------------------------------

    /**
     * GET /api/v1/graph
     *
     * The protocol's discovery root: a self-describing dataset description
     * (VoID / DCAT flavoured) so a crawler can learn the title, licence, the
     * namespaces in use, entity counts by class, and where to find the
     * context document and the crawlable seed/index. Read-only.
     */
    public function dataset(Request $request): Response
    {
        $base = $this->endpointBase();
        $counts = $this->classCounts();
        $total = array_sum($counts);

        $doc = [
            '@context' => array_merge($this->serializer->context(), [
                'void' => 'http://rdfs.org/ns/void#',
                'dcat' => 'http://www.w3.org/ns/dcat#',
                'title' => 'dcterms:title',
                'license' => ['@id' => 'dcterms:license', '@type' => '@id'],
                'modified' => 'dcterms:modified',
                'triples' => 'void:triples',
                'entities' => 'void:entities',
                'classPartition' => 'void:classPartition',
                'class' => ['@id' => 'void:class', '@type' => '@id'],
                'dataDump' => ['@id' => 'void:dataDump', '@type' => '@id'],
                'rootResource' => ['@id' => 'void:rootResource', '@type' => '@id'],
                'sitemap' => ['@id' => 'void:dataDump', '@type' => '@id'],
            ]),
            '@id' => $base.'/api/v1/graph',
            '@type' => ['void:Dataset', 'dcat:Dataset'],
            'title' => (string) config('app.name', 'Heratio').' Open Memory Protocol graph',
            'description' => 'Open, read-only Linked-Data graph of published archival '
                .'descriptions and their cross-collection neighbours. Content-'
                .'negotiable JSON-LD (default), Turtle and RDF/XML. Crawlable: '
                .'every entity @id dereferences back to this endpoint.',
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'modified' => now()->toIso8601String(),
            'entities' => $total,
            // Namespaces in use, mirrored from the serializer.
            'omp:namespaces' => $this->serializer->namespaces(),
            // Class partition (VoID) - published entity counts per class.
            'classPartition' => $this->classPartition($counts),
            // Discovery links.
            'context' => $base.'/api/v1/graph/context.jsonld',
            'rootResource' => $base.'/api/v1/graph/index',
            'omp:seed' => $base.'/api/v1/graph/index',
            // Bulk dataset dumps: the whole published catalogue as CSV and as a
            // paginated JSON-LD @graph (DatasetController). Researchers can pull
            // the corpus in one download instead of crawling per-entity.
            'dataDump' => [
                $base.'/api/v1/graph/index',
                $base.'/api/v1/dataset.csv',
                $base.'/api/v1/dataset.jsonld',
            ],
            // The XML sitemap that enumerates every per-entity graph URL, so
            // discovery -> sitemap -> per-entity crawl is a connected path.
            'sitemap' => $base.'/api/v1/graph/sitemap.xml',
            // The zero-knowledge VoID entry point.
            'omp:wellKnown' => $base.'/.well-known/void',
        ];

        $body = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // Crawlable seed / index (cursor-paginated)
    // -----------------------------------------------------------------

    /**
     * GET /api/v1/graph/index  (alias: /seed)
     *
     * A cursor-paginated list of published entity ids/slugs+classes so a
     * crawler can enumerate the whole graph. Bounded page size; an opaque
     * (here: id-keyset) `next` cursor. Read-only over the same tables the
     * per-entity endpoint uses, with the identical publication-status gate.
     */
    public function index(Request $request): Response
    {
        $base = $this->endpointBase();

        $size = (int) $request->query('pageSize', (string) self::INDEX_PAGE_SIZE);
        if ($size < 1) {
            $size = self::INDEX_PAGE_SIZE;
        }
        $size = min($size, self::INDEX_MAX_PAGE_SIZE);

        // Keyset cursor: "after this object id". Opaque to the consumer.
        $after = (int) $request->query('after', '0');

        $rows = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', '=', self::STATUS_PUBLISHED);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
            })
            ->where('io.id', '>', $after)
            ->where('io.id', '!=', 1) // exclude synthetic root
            ->orderBy('io.id')
            ->limit($size + 1) // one extra row to detect a next page
            ->select('io.id', 'io.level_of_description_id', 's.slug', 'i18n.title')
            ->get();

        $hasMore = $rows->count() > $size;
        $page = $hasMore ? $rows->slice(0, $size) : $rows;

        $items = [];
        $lastId = $after;
        foreach ($page as $row) {
            $lastId = (int) $row->id;
            $level = $this->termName($row->level_of_description_id);
            $items[] = [
                '@id' => $this->graphUri((int) $row->id),
                '@type' => $this->schemaType($level),
                'additionalType' => $this->ricTypeForLevel($level),
                'identifier' => (int) $row->id,
                'slug' => $row->slug,
                'name' => $row->title,
            ];
        }

        $context = array_merge($this->serializer->context(), [
            'hydra' => 'http://www.w3.org/ns/hydra/core#',
            'next' => ['@id' => 'hydra:next', '@type' => '@id'],
            'pageSize' => 'hydra:limit',
            'member' => ['@id' => 'hydra:member', '@type' => '@id'],
            'items' => 'hydra:member',
        ]);

        $doc = [
            '@context' => $context,
            '@id' => $base.'/api/v1/graph/index'.($after > 0 ? '?after='.$after : ''),
            '@type' => 'hydra:PartialCollectionView',
            'pageSize' => $size,
            'count' => count($items),
            'items' => $items,
        ];

        if ($hasMore) {
            $doc['next'] = $base.'/api/v1/graph/index?after='.$lastId.'&pageSize='.$size;
            $doc['cursor'] = (string) $lastId;
        }

        $body = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // Stand-alone @context document
    // -----------------------------------------------------------------

    /**
     * GET /api/v1/graph/context.jsonld
     *
     * The shared JSON-LD @context as a stable, dereferenceable document. The
     * per-entity responses inline the same context (single source in the
     * GraphSerializerService), so the two never drift.
     */
    public function context(): Response
    {
        $doc = ['@context' => $this->serializer->context()];

        $body = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // Zero-knowledge discovery: VoID dataset description (.well-known)
    // -----------------------------------------------------------------

    /**
     * GET /.well-known/void  (alias: /.well-known/void.ttl)
     *
     * The zero-knowledge entry point. A crawler that knows nothing about this
     * host can dereference a single well-known URL and learn:
     *   - the dataset front door (/api/v1/graph),
     *   - the stand-alone JSON-LD @context,
     *   - the crawl seed/index,
     *   - the XML sitemap that enumerates every per-entity graph URL,
     *   - the licence, the namespaces in use, and the published entity count.
     *
     * VoID is conventionally Turtle, so this surface emits text/turtle. It is
     * built from the SAME class counts / namespace table the JSON-LD dataset()
     * front door uses, so the two descriptions can never drift. Resilient: an
     * empty graph still yields a valid VoID document (header + dataset stanza),
     * never a 500.
     */
    public function void(Request $request): Response
    {
        $base = $this->endpointBase();

        // Reuse the exact enumeration the dataset front door publishes.
        try {
            $counts = $this->classCounts();
        } catch (\Throwable $e) {
            $counts = [];
        }
        $total = array_sum($counts);

        $namespaces = $this->serializer->namespaces();
        // VoID + DCAT + FOAF prefixes the document itself needs, layered on top
        // of the protocol's own namespace table (no duplication).
        $prefixes = array_merge($namespaces, [
            'void' => 'http://rdfs.org/ns/void#',
            'dcat' => 'http://www.w3.org/ns/dcat#',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
        ]);

        $datasetUri = $base.'/api/v1/graph';
        $sitemapUri = $base.'/api/v1/graph/sitemap.xml';
        $contextUri = $base.'/api/v1/graph/context.jsonld';
        $seedUri = $base.'/api/v1/graph/index';
        $title = (string) config('app.name', 'Heratio').' Open Memory Protocol graph';

        $lines = [];
        foreach ($prefixes as $prefix => $uri) {
            $lines[] = '@prefix '.$prefix.': <'.$this->ttlIri($uri).'> .';
        }
        $lines[] = '';

        // The dataset stanza. Every object that is a URI is bracketed; literals
        // are escaped. dataDump points at BOTH the crawl seed and the sitemap so
        // a consumer can pick either enumeration path.
        $stanza = [];
        $stanza[] = 'a void:Dataset, dcat:Dataset';
        $stanza[] = 'dcterms:title "'.$this->ttlLiteral($title).'"';
        $stanza[] = 'dcterms:description "'.$this->ttlLiteral(
            'Open, read-only Linked-Data graph of published archival descriptions '
            .'and their cross-collection neighbours. Crawlable: every entity URI '
            .'dereferences back to the graph endpoint.'
        ).'"';
        $stanza[] = 'dcterms:license <https://creativecommons.org/licenses/by/4.0/>';
        $stanza[] = 'dcterms:modified "'.$this->ttlLiteral(now()->toIso8601String()).'"';
        $stanza[] = 'void:entities '.(int) $total;
        // Discovery links: the JSON-LD front door (rootResource), the crawl seed
        // and the XML sitemap (both as dataDump), and the @context (feature).
        // This is the connected path: discovery -> seed/sitemap -> entity crawl.
        $stanza[] = 'void:rootResource <'.$this->ttlIri($datasetUri).'>';
        $stanza[] = 'void:dataDump <'.$this->ttlIri($seedUri).'>';
        $stanza[] = 'void:dataDump <'.$this->ttlIri($sitemapUri).'>';
        // Bulk dataset dumps of the whole published catalogue (DatasetController).
        $stanza[] = 'void:dataDump <'.$this->ttlIri($base.'/api/v1/dataset.csv').'>';
        $stanza[] = 'void:dataDump <'.$this->ttlIri($base.'/api/v1/dataset.jsonld').'>';
        // Content-syndication feed of recently updated published records (the
        // "what changed recently" surface). Linked as a related resource so a
        // change-watching consumer can subscribe rather than re-crawl.
        $stanza[] = 'dcterms:isReferencedBy <'.$this->ttlIri($base.'/feed.atom').'>';
        $stanza[] = 'void:feature <'.$this->ttlIri($contextUri).'>';
        $stanza[] = 'dcat:contactPoint <'.$this->ttlIri($datasetUri).'>';

        // Class partition: published entity counts per schema.org class.
        foreach ($this->classPartition($counts) as $part) {
            $stanza[] = 'void:classPartition [ void:class '.$this->ttlClassRef((string) $part['class'])
                .' ; void:entities '.(int) $part['entities'].' ]';
        }

        $lines[] = '<'.$this->ttlIri($datasetUri).'>';
        $lines[] = '    '.implode(" ;\n    ", $stanza).' .';
        $lines[] = '';

        $body = implode("\n", $lines)."\n";

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'text/turtle; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // XML sitemap of graph entities (crawlable enumeration)
    // -----------------------------------------------------------------

    /**
     * GET /api/v1/graph/sitemap.xml
     *
     * A standards-compliant sitemap over the per-entity graph URLs. Built from
     * the SAME published-only, root-excluded enumeration the crawl index() uses
     * (information_object joined to a Published status row, id != 1).
     *
     * Paging: the sitemaps.org spec caps a single <urlset> at 50000 URLs; this
     * implementation caps far lower (self::SITEMAP_PAGE_SIZE) for safety and
     * cheapness. When the published entity count exceeds one page, the root
     * sitemap.xml becomes a <sitemapindex> that links child sitemaps
     * (?page=N); each child emits its slice as a <urlset>. Resilient: an empty
     * graph yields a valid empty <urlset>, never a 500.
     */
    public function sitemap(Request $request): Response
    {
        $base = $this->endpointBase();

        // Total published, root-excluded entity count (same gate as index()).
        try {
            $total = $this->publishedCount();
        } catch (\Throwable $e) {
            $total = 0;
        }

        $pageSize = self::SITEMAP_PAGE_SIZE;
        $pageCount = $total > 0 ? (int) ceil($total / $pageSize) : 1;

        $page = (int) $request->query('page', '0');

        // Root with no ?page and more than one page: emit a <sitemapindex>
        // that links each child sitemap. Otherwise emit a <urlset> slice.
        if ($page < 1 && $pageCount > 1) {
            $xml = $this->sitemapIndexXml($base, $pageCount);

            return $this->withCors(
                response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8'])
            );
        }

        // A single <urlset>. page<1 means "the only/first page".
        $pageNo = $page < 1 ? 1 : min($page, max($pageCount, 1));
        $after = ($pageNo - 1) * $pageSize;

        try {
            $rows = $this->publishedSlice($after, $pageSize);
        } catch (\Throwable $e) {
            $rows = collect();
        }

        $xml = $this->urlsetXml($base, $rows);

        return $this->withCors(
            response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8'])
        );
    }

    /**
     * Build a <sitemapindex> linking the child <urlset> sitemaps.
     */
    protected function sitemapIndexXml(string $base, int $pageCount): string
    {
        $now = $this->xmlEscape(now()->toIso8601String());
        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        for ($p = 1; $p <= $pageCount; $p++) {
            $loc = $this->xmlEscape($base.'/api/v1/graph/sitemap.xml?page='.$p);
            $out .= '  <sitemap>'."\n";
            $out .= '    <loc>'.$loc.'</loc>'."\n";
            $out .= '    <lastmod>'.$now.'</lastmod>'."\n";
            $out .= '  </sitemap>'."\n";
        }
        $out .= '</sitemapindex>'."\n";

        return $out;
    }

    /**
     * Build a <urlset> over one slice of published entity graph URLs.
     *
     * @param  iterable<int,object>  $rows  rows with ->id and ->updated_at
     */
    protected function urlsetXml(string $base, iterable $rows): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($rows as $row) {
            $loc = $this->xmlEscape($base.'/api/v1/graph/'.(int) $row->id);
            $out .= '  <url>'."\n";
            $out .= '    <loc>'.$loc.'</loc>'."\n";
            if (! empty($row->updated_at)) {
                $out .= '    <lastmod>'.$this->xmlEscape($this->w3cDate((string) $row->updated_at)).'</lastmod>'."\n";
            }
            $out .= '    <changefreq>monthly</changefreq>'."\n";
            $out .= '  </url>'."\n";
        }
        $out .= '</urlset>'."\n";

        return $out;
    }

    // -----------------------------------------------------------------
    // Shared published-only, root-excluded enumeration (mirrors index())
    // -----------------------------------------------------------------

    /**
     * Total count of published, root-excluded archival descriptions - the same
     * population the crawl index() walks and the per-entity endpoint serves.
     */
    protected function publishedCount(): int
    {
        return (int) $this->publishedQuery()->count('io.id');
    }

    /**
     * One ordered slice of published, root-excluded entities for a sitemap
     * page. Offset-based paging is fine here: the population is stable enough
     * for a sitemap and the order is deterministic (by id).
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function publishedSlice(int $offset, int $limit): \Illuminate\Support\Collection
    {
        return $this->publishedQuery()
            ->leftJoin('object as o', 'io.id', '=', 'o.id')
            ->orderBy('io.id')
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->select('io.id', 'o.updated_at')
            ->get();
    }

    /**
     * The shared base query: information_object with a Published status row,
     * synthetic root (id 1) excluded. Identical gate to index()/classCounts().
     */
    protected function publishedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', '=', self::STATUS_PUBLISHED);
            })
            ->where('io.id', '!=', 1);
    }

    // -----------------------------------------------------------------
    // Class counts for the dataset description (read-only)
    // -----------------------------------------------------------------

    /**
     * Count published archival descriptions grouped by schema.org class.
     *
     * @return array<string,int>
     */
    protected function classCounts(): array
    {
        try {
            $rows = DB::table('information_object as io')
                ->join('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                        ->where('st.status_id', '=', self::STATUS_PUBLISHED);
                })
                ->where('io.id', '!=', 1)
                ->select('io.level_of_description_id', DB::raw('COUNT(*) as n'))
                ->groupBy('io.level_of_description_id')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $class = $this->schemaType($this->termName($row->level_of_description_id));
            $counts[$class] = ($counts[$class] ?? 0) + (int) $row->n;
        }

        return $counts;
    }

    /**
     * Shape class counts into a VoID classPartition list.
     *
     * @param  array<string,int>  $counts
     * @return array<int,array<string,mixed>>
     */
    protected function classPartition(array $counts): array
    {
        $partition = [];
        foreach ($counts as $class => $n) {
            $partition[] = [
                'class' => $class,
                'entities' => $n,
            ];
        }

        return $partition;
    }

    // -----------------------------------------------------------------
    // Format negotiation
    // -----------------------------------------------------------------

    /**
     * Resolve the wire format. Returns one of: 'jsonld', 'turtle', 'rdfxml'.
     *
     * @param  string|null  $suffix  path suffix captured by the route
     *                               ('jsonld' | 'ttl' | 'rdf'), highest priority.
     */
    protected function negotiateFormat(Request $request, ?string $suffix = null): string
    {
        // 1. Path suffix wins (e.g. /graph/123.ttl).
        $sfx = strtolower((string) $suffix);
        if ($sfx === 'ttl') {
            return 'turtle';
        }
        if ($sfx === 'rdf') {
            return 'rdfxml';
        }
        if ($sfx === 'jsonld') {
            return 'jsonld';
        }

        // 2. ?format= query param.
        $param = strtolower((string) $request->query('format', ''));
        if (in_array($param, ['turtle', 'ttl', 'crm'], true)) {
            return 'turtle';
        }
        if (in_array($param, ['rdf', 'rdfxml', 'rdf-xml', 'rdf/xml'], true)) {
            return 'rdfxml';
        }
        if (in_array($param, ['jsonld', 'json-ld', 'json'], true)) {
            return 'jsonld';
        }

        // 3. Accept header.
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'text/turtle') || str_contains($accept, 'application/x-turtle')) {
            return 'turtle';
        }
        if (str_contains($accept, 'application/rdf+xml')) {
            return 'rdfxml';
        }

        // 4. Default.
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
     * Build the JSON-LD document as a neutral PHP array: a schema.org / rico /
     * crm @context plus an @graph holding the node and its cross-collection
     * neighbours. Every node carries an @id that resolves back to this
     * endpoint, so a consumer can crawl outward. This array is the single
     * source the RDF serialisers (Turtle, RDF/XML) and the JSON-LD response
     * all derive from, so the formats can never drift.
     *
     * The shape is byte-for-byte the historical JSON-LD response.
     *
     * @return array<string,mixed>
     */
    protected function buildGraph(array $node): array
    {
        // Cross-collection neighbours from the unified graph (read-only).
        $neighbourGroups = $this->neighbourGroups($node['id']);

        // Shared @context owned by GraphSerializerService (single source).
        $context = $this->serializer->context();

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

        return [
            '@context' => $context,
            '@graph' => $graph,
        ];
    }

    // -----------------------------------------------------------------
    // Turtle (CIDOC-CRM) via ahg-ric CrmSerializer (optional enrichment)
    // -----------------------------------------------------------------

    /**
     * Render the richer CIDOC-CRM Turtle view via ahg-ric's CrmSerializer.
     * Returns null when ahg-ric is absent or yields nothing, so the caller
     * can fall back to the in-package Turtle serialisation (and the open
     * endpoint never 501s for a published record).
     */
    protected function ricTurtle(int $objectId): ?string
    {
        $serializerClass = \AhgRic\Crm\CrmSerializer::class;
        if (! class_exists($serializerClass)) {
            return null;
        }

        try {
            /** @var \AhgRic\Crm\CrmSerializer $serializer */
            $serializer = app($serializerClass);
            $ttl = $serializer->serializeRecord(
                $objectId,
                $this->culture,
                \AhgRic\Crm\CrmSerializer::FORMAT_TURTLE
            );

            return $ttl === '' ? null : $ttl;
        } catch (\Throwable $e) {
            return null;
        }
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

    // -----------------------------------------------------------------
    // Turtle + XML escaping helpers (VoID + sitemap)
    // -----------------------------------------------------------------

    /**
     * Strip characters illegal in a Turtle IRIREF (angle brackets, quotes,
     * whitespace, control chars) so an emitted <...> stays well-formed.
     */
    protected function ttlIri(string $iri): string
    {
        return (string) preg_replace('/[\x00-\x20<>"{}|\^`\\\\]/u', '', $iri);
    }

    /**
     * Escape a Turtle string literal body (the content between the quotes).
     */
    protected function ttlLiteral(string $value): string
    {
        return str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $value
        );
    }

    /**
     * Render a schema.org class for void:class. The class counts are already
     * schema: CURIEs (e.g. schema:Collection); pass a CURIE through, otherwise
     * bracket an absolute IRI.
     */
    protected function ttlClassRef(string $class): string
    {
        if ($class === '') {
            return 'rdfs:Resource';
        }
        // A CURIE like "schema:Collection" (prefix is in the namespace table)
        // is emitted verbatim; anything else is treated as an absolute IRI.
        if (str_contains($class, ':')) {
            [$prefix] = explode(':', $class, 2);
            if (array_key_exists($prefix, $this->serializer->namespaces())) {
                return $class;
            }
        }

        return '<'.$this->ttlIri($class).'>';
    }

    /**
     * XML-escape text/attribute content for the sitemap.
     */
    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Normalise a DB timestamp to a W3C-datetime <lastmod> value. Falls back to
     * the raw string when it cannot be parsed (never throws).
     */
    protected function w3cDate(string $value): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return $value;
        }
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

        return $this->signFederation($response);
    }

    /**
     * Federation trust handshake (T1, heratio#1316): attach a DETACHED Ed25519
     * signature header over the EXACT response bytes so a federating peer can
     * cryptographically verify this graph came from this instance. Reuses the
     * platform's one Ed25519 key via ahg-federation's FederationSigner (which
     * wraps the inference-receipts signer); never mutates the JSON body, so a
     * consumer that ignores the header is unaffected. Fail-soft: when the signer
     * package is absent the response is returned unsigned, never an error.
     */
    protected function signFederation(Response $response): Response
    {
        $signerClass = \AhgFederation\Services\FederationSigner::class;
        if (! class_exists($signerClass)) {
            return $response;
        }

        try {
            return app($signerClass)->attach($response);
        } catch (\Throwable $e) {
            return $response;
        }
    }
}
