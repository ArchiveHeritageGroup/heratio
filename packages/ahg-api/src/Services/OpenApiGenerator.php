<?php

/**
 * OpenApiGenerator
 *
 * Reflective OpenAPI 3.1 spec generator for the Heratio REST API.
 * Walks Laravel's route table, filters /api/* routes, introspects
 * controller signatures + FormRequest rules, and emits a minimal
 * but spec-correct JSON document.
 *
 * Issue #652 Phase 1.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgApi\Services;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class OpenApiGenerator
{
    /** @var string */
    protected string $title = 'Heratio REST API';

    /** @var string */
    protected string $description = 'OpenAPI 3.1 specification for the Heratio archival management REST API (v1 + v2).';

    /** @var string */
    protected string $version = '1.0.0';

    /**
     * Build the full OpenAPI 3.1 document.
     */
    public function generate(): array
    {
        $appVersion = $this->resolveAppVersion();

        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->title,
                'description' => $this->description,
                'version' => $appVersion,
                'contact' => [
                    'name' => 'The Archive and Heritage Group',
                    'email' => 'johan@theahg.co.za',
                ],
                'license' => [
                    'name' => 'AGPL-3.0-or-later',
                    'identifier' => 'AGPL-3.0-or-later',
                ],
            ],
            'servers' => [
                [
                    'url' => rtrim((string) config('app.url', 'http://localhost'), '/'),
                    'description' => 'Current host',
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                        'description' => 'API key issued via /api/v2/keys. Alternatively use Authorization: Bearer <token>.',
                    ],
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Same API key supplied as Authorization: Bearer <token>.',
                    ],
                ],
                'schemas' => $this->commonSchemas(),
            ],
            'security' => [
                ['ApiKeyAuth' => []],
                ['BearerAuth' => []],
            ],
            'paths' => $this->buildPaths(),
            'tags' => [
                ['name' => 'v1', 'description' => 'REST API v1 (read-mostly, CRUD on a few entities)'],
                ['name' => 'v2', 'description' => 'REST API v2 (full REST with batch, search, audit, webhooks)'],
                ['name' => 'legacy', 'description' => 'Legacy /api/* routes for backward compatibility'],
                ['name' => 'docs', 'description' => 'Spec + Swagger UI endpoints'],
                ['name' => 'Open Data / Linked Data', 'description' => 'Public, key-free Linked-Data graph (Open Memory Protocol, north-star #1204): the VoID/DCAT dataset front door, JSON-LD @context, crawl seed/index, per-entity graph neighbourhoods with JSON-LD / Turtle / RDF-XML content negotiation, XML sitemap, and the zero-knowledge /.well-known/void discovery document.'],
                ['name' => 'OAI-PMH', 'description' => 'Open Archives Initiative Protocol for Metadata Harvesting 2.0 over the published corpus (simple Dublin Core, oai_dc). Verbs Identify, ListMetadataFormats, ListIdentifiers, ListRecords, GetRecord, with selective harvesting via from/until and an opaque resumptionToken. No API key.'],
            ],
        ];

        return $spec;
    }

    /**
     * Walk Route::getRoutes() and convert /api/* routes into OpenAPI path items.
     */
    protected function buildPaths(): array
    {
        $paths = [];

        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            // Convert Laravel {slug} -> OpenAPI {slug}, normalize leading slash
            $path = '/'.preg_replace('#\{([^\}]+)\}#', '{$1}', $uri);

            $methods = array_filter(
                $route->methods(),
                fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS'], true)
            );

            foreach ($methods as $method) {
                $operation = $this->describeOperation($route, $method);
                $paths[$path][strtolower($method)] = $operation;
            }
        }

        // Overlay curated, hand-written documentation for the public open-data /
        // Linked-Data endpoints and the OAI-PMH endpoint. These replace the
        // generic reflected stubs (same path keys) with proper summaries,
        // descriptions, parameters, content-types and examples, and add the
        // /.well-known/void path that the reflective walk misses because it is
        // not under the "api/" prefix. Merge per-operation so any reflected
        // method on the same path that is not curated here is preserved.
        foreach ($this->openDataPaths() as $path => $operations) {
            foreach ($operations as $httpMethod => $operation) {
                $paths[$path][$httpMethod] = $operation;
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * Curated OpenAPI path items for the public open-data / Linked-Data graph
     * (Open Memory Protocol, north-star #1204) and the OAI-PMH 2.0 endpoint.
     *
     * Hand-written rather than reflected because these endpoints negotiate
     * multiple RDF content-types, take protocol-specific query parameters
     * (OAI verbs, graph format selectors) and are open (no API key) - none of
     * which the generic route reflector can express. Keyed by OpenAPI path so
     * they overlay the reflected entries of the same name in buildPaths(); the
     * /.well-known/void path is additive (the reflector skips non-api paths).
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected function openDataPaths(): array
    {
        $openSecurity = []; // no API key required for these endpoints

        $ldContentTypes = [
            'application/ld+json' => [
                'schema' => ['type' => 'object', 'description' => 'JSON-LD document with @context, @id and @graph.'],
            ],
            'text/turtle' => [
                'schema' => ['type' => 'string', 'description' => 'RDF serialised as Turtle.'],
            ],
            'application/rdf+xml' => [
                'schema' => ['type' => 'string', 'description' => 'RDF serialised as RDF/XML.'],
            ],
        ];

        $acceptParam = [
            'name' => 'Accept',
            'in' => 'header',
            'required' => false,
            'description' => 'Content negotiation. text/turtle -> Turtle, application/rdf+xml -> RDF/XML, application/ld+json (or unset) -> JSON-LD. Lowest priority: a path suffix (.jsonld/.ttl/.rdf) or ?format= wins over Accept.',
            'schema' => [
                'type' => 'string',
                'enum' => ['application/ld+json', 'text/turtle', 'application/rdf+xml'],
            ],
        ];

        $formatParam = [
            'name' => 'format',
            'in' => 'query',
            'required' => false,
            'description' => 'Override content negotiation: jsonld | json-ld | json (JSON-LD), ttl | turtle | crm (Turtle), rdf | rdfxml | rdf-xml (RDF/XML). Takes precedence over the Accept header; a path suffix takes precedence over this.',
            'schema' => [
                'type' => 'string',
                'enum' => ['jsonld', 'json-ld', 'json', 'ttl', 'turtle', 'crm', 'rdf', 'rdfxml', 'rdf-xml'],
            ],
        ];

        $idOrSlugParam = [
            'name' => 'idOrSlug',
            'in' => 'path',
            'required' => true,
            'description' => 'Numeric object id or URL-safe slug of the published archival description. Grammar: [A-Za-z0-9-_]+.',
            'schema' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9\\-_]+$'],
        ];

        $ldTag = ['Open Data / Linked Data'];
        $oaiTag = ['OAI-PMH'];

        return [
            '/api/v1/graph' => [
                'get' => [
                    'tags' => $ldTag,
                    'operationId' => 'getOpenMemoryGraphDataset',
                    'summary' => 'Open Memory Protocol dataset description (VoID/DCAT front door)',
                    'description' => "The front door of the public Linked-Data graph. Returns a VoID/DCAT dataset description: total published entity count, a class partition (counts per RiC/schema.org class), the namespaces in use, and discovery links to the JSON-LD @context, the crawl seed/index, the XML sitemap and the zero-knowledge /.well-known/void document. Open data, no API key. Published records only (status.type_id=158, status_id=160).",
                    'security' => $openSecurity,
                    'parameters' => [],
                    'responses' => [
                        '200' => [
                            'description' => 'VoID/DCAT dataset description as JSON-LD.',
                            'content' => [
                                'application/ld+json' => [
                                    'schema' => ['type' => 'object'],
                                    'example' => [
                                        '@id' => 'https://example.org/api/v1/graph',
                                        '@type' => ['void:Dataset', 'dcat:Dataset'],
                                        'title' => 'Heratio Open Memory Protocol graph',
                                        'license' => 'https://creativecommons.org/licenses/by/4.0/',
                                        'entities' => 12840,
                                        'context' => 'https://example.org/api/v1/graph/context.jsonld',
                                        'rootResource' => 'https://example.org/api/v1/graph/index',
                                        'sitemap' => 'https://example.org/api/v1/graph/sitemap.xml',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\GraphController@dataset',
                ],
            ],
            '/api/v1/graph/context.jsonld' => [
                'get' => [
                    'tags' => $ldTag,
                    'operationId' => 'getOpenMemoryGraphContext',
                    'summary' => 'Stable JSON-LD @context document',
                    'description' => 'The stable JSON-LD @context shared by every graph response (schema.org, RiC and CIDOC-CRM term mappings). Cache it and reference it by URL from your own JSON-LD. Open data, no API key.',
                    'security' => $openSecurity,
                    'parameters' => [],
                    'responses' => [
                        '200' => [
                            'description' => 'The JSON-LD @context document.',
                            'content' => [
                                'application/ld+json' => [
                                    'schema' => ['type' => 'object'],
                                    'example' => [
                                        '@context' => [
                                            'schema' => 'https://schema.org/',
                                            'rico' => 'https://www.ica.org/standards/RiC/ontology#',
                                            'crm' => 'http://www.cidoc-crm.org/cidoc-crm/',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\GraphController@context',
                ],
            ],
            '/api/v1/graph/index' => [
                'get' => [
                    'tags' => $ldTag,
                    'operationId' => 'getOpenMemoryGraphIndex',
                    'summary' => 'Crawlable seed / index (cursor-paginated)',
                    'description' => 'A cursor-paginated enumeration of every published entity (id, slug, class, @id) so a crawler can walk the whole graph. Keyset pagination via an opaque `after` cursor; bounded `pageSize`. Each item @id dereferences to GET /api/v1/graph/{idOrSlug}. Alias of GET /api/v1/graph/seed. Open data, no API key.',
                    'security' => $openSecurity,
                    'parameters' => [
                        [
                            'name' => 'after',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Opaque keyset cursor: return entities with an object id greater than this value. Use the cursor echoed in the previous page rather than constructing it.',
                            'schema' => ['type' => 'integer', 'format' => 'int64', 'default' => 0],
                        ],
                        [
                            'name' => 'pageSize',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Page size. Default 200, hard ceiling 500.',
                            'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 200],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'A page of entity stubs plus a next-cursor link.',
                            'content' => [
                                'application/ld+json' => [
                                    'schema' => ['type' => 'object'],
                                    'example' => [
                                        '@id' => 'https://example.org/api/v1/graph/index',
                                        'items' => [
                                            [
                                                '@id' => 'https://example.org/api/v1/graph/42',
                                                '@type' => 'schema:CreativeWork',
                                                'identifier' => 42,
                                            ],
                                        ],
                                        'next' => 'https://example.org/api/v1/graph/index?after=42',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\GraphController@index',
                ],
            ],
            '/api/v1/graph/seed' => [
                'get' => [
                    'tags' => $ldTag,
                    'operationId' => 'getOpenMemoryGraphSeed',
                    'summary' => 'Crawlable seed (alias of /index)',
                    'description' => 'Alias of GET /api/v1/graph/index. Same cursor-paginated enumeration of published entities. Open data, no API key.',
                    'security' => $openSecurity,
                    'parameters' => [
                        [
                            'name' => 'after',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Opaque keyset cursor (see /api/v1/graph/index).',
                            'schema' => ['type' => 'integer', 'format' => 'int64', 'default' => 0],
                        ],
                        [
                            'name' => 'pageSize',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Page size. Default 200, hard ceiling 500.',
                            'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 200],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'A page of entity stubs plus a next-cursor link (see /api/v1/graph/index).',
                            'content' => [
                                'application/ld+json' => ['schema' => ['type' => 'object']],
                            ],
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\GraphController@index',
                ],
            ],
            '/api/v1/graph/sitemap.xml' => [
                'get' => [
                    'tags' => $ldTag,
                    'operationId' => 'getOpenMemoryGraphSitemap',
                    'summary' => 'XML sitemap of per-entity graph URLs',
                    'description' => 'A sitemaps.org XML sitemap enumerating every published per-entity graph URL, so a standards-aware crawler can go discovery -> sitemap -> per-entity crawl. When the published entity count exceeds one page the root document is a <sitemapindex> whose ?page=N children are <urlset> documents. Open data, no API key.',
                    'security' => $openSecurity,
                    'parameters' => [
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Child sitemap page (0-based). Omit for the root <sitemapindex> / single <urlset>. Page size is 5000 URLs.',
                            'schema' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'An XML <sitemapindex> or <urlset> document.',
                            'content' => [
                                'application/xml' => [
                                    'schema' => ['type' => 'string'],
                                    'example' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n  <url><loc>https://example.org/api/v1/graph/42</loc></url>\n</urlset>",
                                ],
                            ],
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\GraphController@sitemap',
                ],
            ],
            '/api/v1/graph/{idOrSlug}' => [
                'get' => [
                    'tags' => $ldTag,
                    'operationId' => 'getOpenMemoryGraphEntity',
                    'summary' => "A record's graph neighbourhood (content-negotiable Linked Data)",
                    'description' => "Returns one published archival record plus its cross-collection (G/L/A/M) neighbours as Linked Data. Each neighbour @id dereferences back to this same endpoint, so the graph is crawlable. Content negotiation chooses JSON-LD (default), Turtle or RDF/XML via, in priority order: the path suffix (.jsonld/.ttl/.rdf - see the suffixed variant), then ?format=, then the Accept header. Open data, no API key. Published records only; 404 (in the negotiated content-type) for unknown or unpublished ids.",
                    'security' => $openSecurity,
                    'parameters' => [$idOrSlugParam, $formatParam, $acceptParam],
                    'responses' => [
                        '200' => [
                            'description' => "The record's graph neighbourhood in the negotiated RDF serialisation.",
                            'content' => $ldContentTypes,
                        ],
                        '404' => [
                            'description' => 'No published record for the supplied id or slug (rendered in the negotiated content-type).',
                            'content' => $ldContentTypes,
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\GraphController@show',
                ],
            ],
            '/api/v1/graph/{idOrSlug}.{suffix}' => [
                'get' => [
                    'tags' => $ldTag,
                    'operationId' => 'getOpenMemoryGraphEntitySuffixed',
                    'summary' => "A record's graph neighbourhood with an explicit RDF suffix",
                    'description' => "Same as GET /api/v1/graph/{idOrSlug} but the file-extension suffix selects the serialisation explicitly and overrides ?format= and Accept: .jsonld -> application/ld+json, .ttl -> text/turtle, .rdf -> application/rdf+xml. Open data, no API key.",
                    'security' => $openSecurity,
                    'parameters' => [
                        $idOrSlugParam,
                        [
                            'name' => 'suffix',
                            'in' => 'path',
                            'required' => true,
                            'description' => 'RDF serialisation: jsonld (application/ld+json), ttl (text/turtle), or rdf (application/rdf+xml).',
                            'schema' => ['type' => 'string', 'enum' => ['jsonld', 'ttl', 'rdf']],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => "The record's graph neighbourhood in the serialisation named by the suffix.",
                            'content' => $ldContentTypes,
                        ],
                        '404' => [
                            'description' => 'No published record for the supplied id or slug.',
                            'content' => $ldContentTypes,
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\GraphController@show',
                ],
            ],
            '/.well-known/void' => [
                'get' => [
                    'tags' => $ldTag,
                    'operationId' => 'getWellKnownVoid',
                    'summary' => 'Zero-knowledge discovery document (VoID/DCAT in Turtle)',
                    'description' => "The single URL a standards-aware crawler dereferences when it knows nothing else about this host. Returns a VoID/DCAT dataset description in Turtle that links to the graph front door (/api/v1/graph), the JSON-LD @context, the crawl seed/index and the XML sitemap, so discovery -> sitemap -> per-entity crawl is a connected path. A /.well-known/void.ttl alias returns the same document. Open data, no API key.",
                    'security' => $openSecurity,
                    'parameters' => [],
                    'responses' => [
                        '200' => [
                            'description' => 'VoID/DCAT dataset description serialised as Turtle.',
                            'content' => [
                                'text/turtle' => [
                                    'schema' => ['type' => 'string'],
                                    'example' => "@prefix void: <http://rdfs.org/ns/void#> .\n@prefix dcat: <http://www.w3.org/ns/dcat#> .\n<https://example.org/api/v1/graph> a void:Dataset, dcat:Dataset ;\n  void:rootResource <https://example.org/api/v1/graph/index> ;\n  void:dataDump <https://example.org/api/v1/graph/sitemap.xml> .",
                                ],
                            ],
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\GraphController@void',
                ],
            ],
            '/api/oai' => [
                'get' => [
                    'tags' => $oaiTag,
                    'operationId' => 'getOaiPmh',
                    'summary' => 'OAI-PMH 2.0 harvesting endpoint',
                    'description' => "A single standards-based OAI-PMH 2.0 endpoint over the PUBLISHED archival corpus, serving simple Dublin Core (oai_dc). The OAI verb is selected with the ?verb= argument; selective harvesting uses from/until plus an opaque, bounded resumptionToken. No API key (open data). Identifiers take the form oai:{host}:io/{id}. Every response (including protocol errors) is a valid OAI-PMH XML document returned as text/xml. POST is also accepted (arguments still arrive as query/form params).\n\nVerbs:\n- Identify: repository metadata (name, baseURL, protocolVersion, granularity, oai_dc).\n- ListMetadataFormats: advertises oai_dc (optionally scoped to one identifier).\n- ListIdentifiers: record headers only; supports from/until/metadataPrefix and resumptionToken.\n- ListRecords: full oai_dc records; same selection/paging as ListIdentifiers.\n- GetRecord: one record by identifier + metadataPrefix.",
                    'security' => $openSecurity,
                    'parameters' => [
                        [
                            'name' => 'verb',
                            'in' => 'query',
                            'required' => true,
                            'description' => 'The OAI-PMH verb to dispatch.',
                            'schema' => [
                                'type' => 'string',
                                'enum' => ['Identify', 'ListMetadataFormats', 'ListIdentifiers', 'ListRecords', 'GetRecord'],
                            ],
                        ],
                        [
                            'name' => 'metadataPrefix',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Metadata format. Only oai_dc is supported. Required for ListIdentifiers/ListRecords (unless a resumptionToken is supplied) and for GetRecord.',
                            'schema' => ['type' => 'string', 'enum' => ['oai_dc'], 'default' => 'oai_dc'],
                        ],
                        [
                            'name' => 'identifier',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'OAI identifier of a single record, form oai:{host}:io/{id} (a bare numeric id is also accepted leniently). Required for GetRecord; optional scope for ListMetadataFormats.',
                            'schema' => ['type' => 'string', 'example' => 'oai:example.org:io/42'],
                        ],
                        [
                            'name' => 'from',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Selective harvesting lower bound (inclusive) on the record datestamp. UTC, ISO-8601 / OAI granularity.',
                            'schema' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                        [
                            'name' => 'until',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Selective harvesting upper bound (inclusive) on the record datestamp. UTC, ISO-8601 / OAI granularity.',
                            'schema' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                        [
                            'name' => 'resumptionToken',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Opaque token from a previous ListIdentifiers/ListRecords response to fetch the next page. Mutually exclusive with from/until/metadataPrefix (per the OAI-PMH spec).',
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'A valid OAI-PMH 2.0 XML response (or a well-formed OAI <error> document for badVerb / badArgument / unknown identifier).',
                            'content' => [
                                'text/xml' => [
                                    'schema' => ['type' => 'string'],
                                    'example' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<OAI-PMH xmlns=\"http://www.openarchives.org/OAI/2.0/\">\n  <responseDate>2026-06-11T00:00:00Z</responseDate>\n  <request verb=\"Identify\">https://example.org/api/oai</request>\n  <Identify>\n    <repositoryName>Heratio</repositoryName>\n    <baseURL>https://example.org/api/oai</baseURL>\n    <protocolVersion>2.0</protocolVersion>\n  </Identify>\n</OAI-PMH>",
                                ],
                            ],
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\OaiPmhController@handle',
                ],
                'post' => [
                    'tags' => $oaiTag,
                    'operationId' => 'postOaiPmh',
                    'summary' => 'OAI-PMH 2.0 harvesting endpoint (POST)',
                    'description' => 'Identical to GET /api/oai. POST is permitted by the OAI-PMH spec; arguments arrive as form/query params. Returns text/xml. No API key.',
                    'security' => $openSecurity,
                    'parameters' => [],
                    'requestBody' => [
                        'required' => false,
                        'description' => 'OAI-PMH arguments as application/x-www-form-urlencoded (verb, metadataPrefix, identifier, from, until, resumptionToken).',
                        'content' => [
                            'application/x-www-form-urlencoded' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'verb' => ['type' => 'string', 'enum' => ['Identify', 'ListMetadataFormats', 'ListIdentifiers', 'ListRecords', 'GetRecord']],
                                        'metadataPrefix' => ['type' => 'string', 'enum' => ['oai_dc']],
                                        'identifier' => ['type' => 'string'],
                                        'from' => ['type' => 'string', 'format' => 'date-time'],
                                        'until' => ['type' => 'string', 'format' => 'date-time'],
                                        'resumptionToken' => ['type' => 'string'],
                                    ],
                                    'required' => ['verb'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'A valid OAI-PMH 2.0 XML response.',
                            'content' => [
                                'text/xml' => ['schema' => ['type' => 'string']],
                            ],
                        ],
                    ],
                    'x-laravel-action' => 'AhgApi\\Controllers\\OaiPmhController@handle',
                ],
            ],
        ];
    }

    /**
     * Build an OpenAPI operation object for a single route+method.
     */
    protected function describeOperation(Route $route, string $method): array
    {
        $action = $route->getActionName();
        $tag = $this->tagFor($route);

        $operation = [
            'tags' => [$tag],
            'operationId' => $this->operationId($route, $method),
            'summary' => $this->summaryFor($route, $method),
            'parameters' => $this->parametersFor($route),
            'responses' => $this->responsesFor($method),
        ];

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            $body = $this->requestBodyFor($route);
            if ($body !== null) {
                $operation['requestBody'] = $body;
            }
        }

        // Annotate with controller@method for traceability
        $operation['x-laravel-action'] = $action;

        // Idempotency-Key hint for non-idempotent POSTs
        if (strtoupper($method) === 'POST') {
            $operation['parameters'][] = [
                'name' => 'Idempotency-Key',
                'in' => 'header',
                'required' => false,
                'description' => 'Client-supplied idempotency token (RFC draft). Replays within 24h return the cached response.',
                'schema' => ['type' => 'string', 'maxLength' => 64],
            ];
        }

        // ETag hint for GETs
        if (strtoupper($method) === 'GET') {
            $operation['parameters'][] = [
                'name' => 'If-None-Match',
                'in' => 'header',
                'required' => false,
                'description' => 'Conditional GET. If the supplied ETag matches the current resource, the server returns 304 Not Modified.',
                'schema' => ['type' => 'string'],
            ];
            $operation['responses']['304'] = [
                'description' => 'Resource has not changed since the supplied ETag was issued.',
            ];
        }

        return $operation;
    }

    protected function tagFor(Route $route): string
    {
        $uri = $route->uri();
        if (str_starts_with($uri, 'api/v1/')) {
            return 'v1';
        }
        if (str_starts_with($uri, 'api/v2/') || $uri === 'api/v2') {
            return 'v2';
        }
        if (str_starts_with($uri, 'api/openapi') || str_starts_with($uri, 'api/docs')) {
            return 'docs';
        }

        return 'legacy';
    }

    protected function operationId(Route $route, string $method): string
    {
        $name = $route->getName();
        if ($name) {
            return strtolower($method).'_'.str_replace(['.', '/'], '_', $name);
        }

        $uri = $route->uri();
        $slug = preg_replace('#[^a-zA-Z0-9]+#', '_', $uri);

        return strtolower($method).'_'.trim($slug, '_');
    }

    protected function summaryFor(Route $route, string $method): string
    {
        $action = $route->getActionName();
        if ($action === 'Closure') {
            return strtoupper($method).' '.$route->uri();
        }

        [$class, $methodName] = array_pad(explode('@', $action), 2, '');
        $short = $class !== '' ? class_basename($class) : 'Handler';

        return sprintf('%s %s::%s', strtoupper($method), $short, $methodName);
    }

    /**
     * Path parameters extracted from {param} placeholders.
     */
    protected function parametersFor(Route $route): array
    {
        $params = [];

        foreach ($route->parameterNames() as $name) {
            $params[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'description' => $this->paramDescription($name),
                'schema' => $this->paramSchema($name, $route),
            ];
        }

        return $params;
    }

    protected function paramDescription(string $name): string
    {
        return match ($name) {
            'slug' => 'Slug of the target object (URL-safe).',
            'id' => 'Numeric primary key.',
            'objectId' => 'Numeric object ID.',
            'photoId' => 'Numeric photo ID.',
            default => ucfirst($name).' path parameter.',
        };
    }

    protected function paramSchema(string $name, Route $route): array
    {
        $wheres = $route->wheres ?? [];
        $pattern = $wheres[$name] ?? null;
        if ($pattern === '[0-9]+' || in_array($name, ['id', 'objectId', 'photoId'], true)) {
            return ['type' => 'integer', 'format' => 'int64'];
        }

        return ['type' => 'string'];
    }

    /**
     * Best-effort request-body schema derived from the controller method signature
     * (FormRequest rules() if present) or a generic JSON object.
     */
    protected function requestBodyFor(Route $route): ?array
    {
        $properties = $this->formRequestSchema($route);

        if ($properties === null) {
            // Generic fallback so the spec is still valid
            return [
                'required' => false,
                'description' => 'Request payload (JSON).',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                ],
            ];
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $properties,
                ],
            ],
        ];
    }

    /**
     * Reflect on the controller method to look for a FormRequest with rules().
     */
    protected function formRequestSchema(Route $route): ?array
    {
        $action = $route->getActionName();
        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $action, 2);
        if (! class_exists($class)) {
            return null;
        }

        try {
            $ref = new ReflectionClass($class);
            if (! $ref->hasMethod($method)) {
                return null;
            }
            $m = $ref->getMethod($method);
        } catch (\ReflectionException $e) {
            return null;
        }

        foreach ($m->getParameters() as $param) {
            $type = $param->getType();
            if (! $type instanceof ReflectionNamedType) {
                continue;
            }
            $typeName = $type->getName();
            if (! class_exists($typeName)) {
                continue;
            }
            if (! is_subclass_of($typeName, \Illuminate\Foundation\Http\FormRequest::class)) {
                continue;
            }

            // Try to instantiate and read rules()
            try {
                $instance = new $typeName();
                if (! method_exists($instance, 'rules')) {
                    continue;
                }
                $rules = $instance->rules();
                if (! is_array($rules) || empty($rules)) {
                    continue;
                }

                return $this->rulesToSchema($rules);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Translate a Laravel validation rules array into a JSON-schema-ish object.
     */
    protected function rulesToSchema(array $rules): array
    {
        $properties = [];
        $required = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);
            $ruleList = array_map(fn ($r) => is_object($r) ? get_class($r) : (string) $r, $ruleList);

            $type = 'string';
            $format = null;
            foreach ($ruleList as $r) {
                if ($r === 'integer' || $r === 'numeric') {
                    $type = 'integer';
                }
                if ($r === 'array') {
                    $type = 'array';
                }
                if ($r === 'boolean') {
                    $type = 'boolean';
                }
                if ($r === 'date') {
                    $type = 'string';
                    $format = 'date';
                }
                if ($r === 'email') {
                    $type = 'string';
                    $format = 'email';
                }
                if ($r === 'url') {
                    $type = 'string';
                    $format = 'uri';
                }
            }

            // Handle dot-notation fields (foo.bar) by collapsing to root key
            $rootField = explode('.', $field, 2)[0];

            $schema = ['type' => $type];
            if ($format !== null) {
                $schema['format'] = $format;
            }
            if ($type === 'array') {
                $schema['items'] = ['type' => 'string'];
            }

            $properties[$rootField] = $schema;

            if (in_array('required', $ruleList, true)) {
                $required[] = $rootField;
            }
        }

        $out = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $out['required'] = array_values(array_unique($required));
        }

        return $out;
    }

    protected function responsesFor(string $method): array
    {
        $responses = [
            '200' => [
                'description' => 'Success.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'],
                    ],
                ],
            ],
            '401' => [
                'description' => 'Missing or invalid API key.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                    ],
                ],
            ],
            '403' => [
                'description' => 'Authenticated but missing required scope.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                    ],
                ],
            ],
            '404' => [
                'description' => 'Resource not found.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                    ],
                ],
            ],
        ];

        $up = strtoupper($method);
        if ($up === 'POST') {
            $responses['201'] = [
                'description' => 'Created.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'],
                    ],
                ],
            ];
            $responses['422'] = [
                'description' => 'Validation failed.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                    ],
                ],
            ];
        }
        if ($up === 'DELETE') {
            $responses['204'] = ['description' => 'Deleted.'];
        }

        return $responses;
    }

    /**
     * Components / shared schemas.
     */
    protected function commonSchemas(): array
    {
        return [
            'SuccessEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'const' => true],
                    'data' => ['description' => 'Endpoint-specific payload.'],
                    'meta' => ['type' => 'object', 'description' => 'Pagination + counters (optional).'],
                    'links' => ['type' => 'object', 'description' => 'HATEOAS-style links (optional).'],
                    'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'required' => ['success'],
            ],
            'ErrorEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'const' => false],
                    'error' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                    'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'required' => ['success', 'error', 'message'],
            ],
        ];
    }

    /**
     * Pull version from version.json if present, falling back to a constant.
     */
    protected function resolveAppVersion(): string
    {
        $path = base_path('version.json');
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $json = json_decode($raw, true);
                if (is_array($json) && ! empty($json['version'])) {
                    return (string) $json['version'];
                }
            }
        }

        return $this->version;
    }
}
