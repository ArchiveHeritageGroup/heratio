<?php

/**
 * OpenDataController - Heratio ahg-core
 *
 * Public "Open Data & APIs" hub: a single, jurisdiction-neutral landing page that
 * gathers every open-data surface this platform exposes for researchers and
 * developers - the Linked-Data graph, bulk dataset dumps, OAI-PMH harvesting, the
 * VoID discovery document, the API reference (Swagger / OpenAPI), the content
 * credentials (C2PA) verification API, and the RiC linked-data / SPARQL surface.
 *
 * Each card declares the route name (or root URL) it needs and is gated by
 * Route::has(...). A surface only appears when its package is installed and its
 * route is registered, so a missing package degrades gracefully to a smaller hub -
 * never a 500 and never a dead link. URLs are resolved with route() / url() so no
 * internal host is ever hardcoded.
 *
 * This surface is PUBLIC (no auth, no API key) and strictly READ-ONLY. It performs
 * no DB writes and no AI calls. Every advertised endpoint serves PUBLISHED records
 * only and is open data under CC-BY-4.0 (as the dataset descriptions already
 * declare).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

class OpenDataController extends Controller
{
    /**
     * Render the public Open Data hub.
     *
     * Each candidate card lists one or more "endpoints". An endpoint is resolved to
     * a URL either from a named route (`route`, optionally with `params`) or from a
     * literal root path (`path`, resolved via url()). A card is shown only when at
     * least one of its endpoints resolves; endpoints that do not resolve are
     * silently dropped. The page therefore renders even when every optional surface
     * is absent (empty-state) and can never dead-link.
     */
    public function index()
    {
        // The whole-dataset front door, used for the worked curl example below. We
        // resolve it defensively so the example only appears when the graph exists.
        $graphUrl = $this->resolve(['route' => 'api.v1.graph.dataset'])
            ?? $this->resolve(['path' => '/api/v1/graph']);

        $candidates = [
            [
                'icon'  => 'fas fa-project-diagram',
                'title' => __('Linked-data graph'),
                'desc'  => __('Fetch the catalogue as a machine-readable graph. The front door describes the whole dataset; each record is dereferenceable on its own. Content is negotiated by the Accept header (or a path suffix).'),
                'format' => __('JSON-LD (default), Turtle, or RDF/XML'),
                'endpoints' => [
                    ['label' => __('Dataset front door'), 'route' => 'api.v1.graph.dataset'],
                    ['label' => __('Per record'), 'path' => '/api/v1/graph/{id}', 'literal' => true],
                    ['label' => __('JSON-LD @context'), 'route' => 'api.v1.graph.context'],
                    ['label' => __('Crawl seed / index'), 'route' => 'api.v1.graph.index'],
                ],
            ],
            [
                'icon'  => 'fas fa-file-csv',
                'title' => __('Dataset dumps'),
                'desc'  => __('Download the whole published catalogue in one request to take it offline as data - a streamed CSV for spreadsheets and analysis, or a bounded JSON-LD @graph for linked-data tooling.'),
                'format' => __('CSV (streamed) and JSON-LD'),
                'endpoints' => [
                    ['label' => __('CSV'), 'route' => 'api.v1.dataset.csv'],
                    ['label' => __('JSON-LD'), 'route' => 'api.v1.dataset.jsonld'],
                ],
            ],
            [
                'icon'  => 'fas fa-satellite-dish',
                'title' => __('OAI-PMH harvesting'),
                'desc'  => __('A standards-compliant Open Archives Initiative endpoint so aggregators and union catalogues can harvest the descriptions incrementally. Supports the usual OAI verbs (Identify, ListRecords, GetRecord, and so on).'),
                'format' => __('OAI-PMH / XML (Dublin Core)'),
                'endpoints' => [
                    ['label' => __('OAI-PMH endpoint'), 'route' => 'api.oai'],
                ],
            ],
            [
                'icon'  => 'fas fa-compass',
                'title' => __('Discovery document'),
                'desc'  => __('The single URL a standards-aware crawler dereferences when it knows nothing about this host. A VoID / DCAT dataset description that links on to the graph front door, the JSON-LD context, the crawl seed, and the sitemap.'),
                'format' => __('VoID / DCAT in Turtle, plus an XML sitemap'),
                'endpoints' => [
                    ['label' => __('VoID discovery'), 'route' => 'wellknown.void', 'path' => '/.well-known/void'],
                    ['label' => __('Graph sitemap'), 'route' => 'api.v1.graph.sitemap', 'path' => '/api/v1/graph/sitemap.xml'],
                ],
            ],
            [
                'icon'  => 'fas fa-book',
                'title' => __('API reference'),
                'desc'  => __('Interactive documentation for the full read API, plus a machine-readable OpenAPI specification you can import into your own client generator or testing tool.'),
                'format' => __('Swagger UI (HTML) and OpenAPI 3 (JSON)'),
                'endpoints' => [
                    ['label' => __('API docs (Swagger)'), 'route' => 'api.openapi.docs', 'path' => '/api/docs'],
                    ['label' => __('OpenAPI spec'), 'route' => 'api.openapi.spec', 'path' => '/api/openapi.json'],
                ],
            ],
            [
                'icon'  => 'fas fa-shield-alt',
                'title' => __('Content Credentials API'),
                'desc'  => __('Verify the provenance and authenticity of any image or document against its embedded Content Credentials (C2PA). A public check page accepts any upload; digital objects in the catalogue also expose an embeddable verification badge as JSON.'),
                'format' => __('HTML check page and JSON badge'),
                'endpoints' => [
                    ['label' => __('Verify any file'), 'route' => 'c2pa.verify.check'],
                    ['label' => __('Badge JSON (per object)'), 'path' => '/verify/{digitalObjectId}/badge.json', 'literal' => true, 'requires' => 'c2pa.verify.object.badge.json'],
                ],
            ],
            [
                'icon'  => 'fas fa-share-alt',
                'title' => __('Knowledge graph (RiC)'),
                'desc'  => __('Query the Records in Contexts (RiC) knowledge graph directly. A read-only SPARQL endpoint (SELECT / ASK / CONSTRUCT / DESCRIBE) lets federated linked-data clients ask their own questions across the catalogue\'s entities and relationships.'),
                'format' => __('SPARQL 1.1 over RiC-O / RDF'),
                'endpoints' => [
                    ['label' => __('SPARQL endpoint'), 'route' => 'ric.sparql-proxy'],
                ],
            ],
            [
                'icon'  => 'fas fa-sync-alt',
                'title' => __('ResourceSync'),
                'desc'  => __('A ResourceSync source so downstream systems can mirror the collection and keep their copy in step - a capability list pointing at the resource list and a change list for incremental syncs.'),
                'format' => __('ResourceSync / XML (Sitemap-based)'),
                'endpoints' => [
                    ['label' => __('Source description'), 'route' => 'resourcesync.source-description'],
                    ['label' => __('Capability list'), 'route' => 'resourcesync.capability-list'],
                    ['label' => __('Change list'), 'route' => 'resourcesync.change-list'],
                ],
            ],
        ];

        $cards = [];
        foreach ($candidates as $card) {
            $endpoints = [];
            foreach ($card['endpoints'] as $endpoint) {
                // A "requires" gate lets a literal/illustrative endpoint depend on a
                // named route existing (e.g. the C2PA badge URL pattern is only shown
                // when the badge route is registered) without trying to build a URL
                // for a value we cannot supply (the digital-object id).
                if (! empty($endpoint['requires']) && ! Route::has($endpoint['requires'])) {
                    continue;
                }

                if (! empty($endpoint['literal'])) {
                    // Illustrative URL pattern (contains a {placeholder}); resolve the
                    // host/prefix via url() but keep the placeholder visible.
                    $endpoints[] = [
                        'label'   => $endpoint['label'],
                        'url'     => url($endpoint['path']),
                        'pattern' => true,
                    ];
                    continue;
                }

                $url = $this->resolve($endpoint);
                if ($url === null) {
                    continue;
                }
                $endpoints[] = [
                    'label'   => $endpoint['label'],
                    'url'     => $url,
                    'pattern' => false,
                ];
            }

            // Every card needs at least one live endpoint, otherwise it is dropped so
            // nothing dead-links.
            if (empty($endpoints)) {
                continue;
            }

            $card['endpoints'] = $endpoints;
            $cards[] = $card;
        }

        return view('ahg-core::open-data', [
            'cards'    => $cards,
            'graphUrl' => $graphUrl,
        ]);
    }

    /**
     * Resolve an endpoint to a URL, preferring its named route and falling back to a
     * literal root path. Returns null when neither resolves so the caller can drop
     * the endpoint rather than risk a dead link or an exception.
     *
     * @param  array{route?:string,params?:array,path?:string}  $endpoint
     */
    private function resolve(array $endpoint): ?string
    {
        if (! empty($endpoint['route']) && Route::has($endpoint['route'])) {
            try {
                return route($endpoint['route'], $endpoint['params'] ?? []);
            } catch (\Throwable $e) {
                // A registered route whose URL cannot be generated (e.g. a missing
                // required parameter) falls through to the path fallback below.
            }
        }

        if (! empty($endpoint['path'])) {
            return url($endpoint['path']);
        }

        return null;
    }
}
