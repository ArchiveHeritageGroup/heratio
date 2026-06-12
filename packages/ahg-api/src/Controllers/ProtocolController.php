<?php

/**
 * ProtocolController - the Open Memory Protocol capabilities document.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). This is the machine-discoverable INDEX of the platform's whole
 * open-data offering: one self-describing document that enumerates every open
 * surface, its URL (built from url(), never a hardcoded host) and its media
 * types, so an agent that fetches this single resource learns how to consume
 * everything else without prior knowledge of the host.
 *
 *   GET /open-data/protocol          - content-negotiated:
 *                                       application/json -> JSON capabilities,
 *                                       text/html (browser) -> a human page.
 *   GET /open-data/protocol.json     - the JSON capabilities, explicitly.
 *
 * Surfaces enumerated (each declared only when its named route is registered,
 * via Route::has(), so a slimmer install degrades gracefully to a smaller
 * document rather than dead-linking):
 *   - VoID / DCAT discovery        (/.well-known/void, Turtle)
 *   - graph dataset front door     (/api/v1/graph, JSON-LD)
 *   - per-entity graph             (/api/v1/graph/{idOrSlug}[.ext])
 *   - entity identity (record)     (/id/{slug}, content-negotiated)
 *   - entity identity (actor)      (/id/actor/{slug}, content-negotiated) -
 *                                   a person / corporate body / family
 *   - entity identity (term)       (/id/term/{slug}, content-negotiated) -
 *                                   a place / subject / genre concept
 *   - JSON-LD @context             (/api/v1/graph/context.jsonld)
 *   - crawl seed / index           (/api/v1/graph/index)
 *   - bulk dataset dumps           (/api/v1/dataset.csv, /api/v1/dataset.jsonld)
 *   - OAI-PMH harvesting           (/api/oai)
 *   - sitemaps                     (/sitemap.xml, /api/v1/graph/sitemap.xml)
 *   - syndication feeds            (/feed.atom, /feed.rss)
 *   - OpenAPI spec + Swagger UI    (/api/openapi.json, /api/docs)
 *
 * Honest + safe: read-only; performs no DB access and no AI calls. It only
 * resolves route URLs, so it can never 500 over data. Permissive open-data
 * CORS. Jurisdiction-neutral.
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
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class ProtocolController extends Controller
{
    /**
     * OPTIONS preflight for the protocol endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /open-data/protocol  (and /open-data/protocol.json)
     *
     * Content negotiation: a browser (text/html) gets the human page; everyone
     * else (and ?format=json) gets the JSON capabilities document. The .json
     * route always forces JSON regardless of Accept.
     */
    public function index(Request $request, bool $forceJson = false): Response
    {
        if (! $forceJson && $this->wantsHtml($request)) {
            return $this->withCors(response($this->html(), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]));
        }

        $body = json_encode(
            $this->document(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->withCors(response($body, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]));
    }

    /**
     * Build the capabilities document as a neutral PHP array. Each surface is
     * resolved defensively (Route::has + the literal path fallback) so a
     * missing package drops the surface rather than dead-linking.
     *
     * @return array<string,mixed>
     */
    protected function document(): array
    {
        $surfaces = $this->surfaces();

        return [
            '@context' => [
                'schema' => 'https://schema.org/',
                'dcterms' => 'http://purl.org/dc/terms/',
            ],
            '@type' => 'schema:WebAPI',
            'protocol' => 'Open Memory Protocol',
            'protocolVersion' => '1.0',
            'name' => (string) config('app.name', 'Heratio').' open-data protocol',
            'description' => 'The machine-discoverable index of this platform\'s open-data surfaces. '
                .'Every surface lists its URL (or URL template) and the media types it serves. '
                .'All surfaces are read-only, expose published records only, and are open data under CC-BY-4.0.',
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'documentation' => $this->base().'/open-data',
            // The DCAT-AP data catalogue: the same surface list re-described as a
            // dcat:Catalog of dcat:Datasets + dcat:Distributions, for DCAT-aware
            // open-data harvesters (data portals, CKAN, the EU data portal, ...).
            'catalog' => $this->resolve('open-data.catalog', '/data/catalog'),
            'cors' => 'Access-Control-Allow-Origin: *',
            'authentication' => 'none (open data)',
            'count' => count($surfaces),
            'surfaces' => $surfaces,
        ];
    }

    /**
     * The single source-of-truth list of open-data surfaces, as neutral PHP
     * arrays. Public so the DCAT CatalogController can re-describe the SAME list
     * as a dcat:Catalog without the two ever drifting (one list, two views).
     *
     * Each surface is resolved defensively (Route::has + a literal path
     * fallback) so a missing package drops the surface rather than dead-linking.
     *
     * @return array<int,array<string,mixed>>
     */
    public function surfaces(): array
    {
        $base = $this->base();

        $surfaces = [
            [
                'id' => 'discovery',
                'title' => 'VoID / DCAT discovery',
                'description' => 'The zero-knowledge entry point: a VoID / DCAT dataset description that links on to every other surface.',
                'url' => $this->resolve('wellknown.void', '/.well-known/void'),
                'mediaTypes' => ['text/turtle'],
                'method' => 'GET',
            ],
            [
                'id' => 'stats',
                'title' => 'Graph statistics (at a glance)',
                'description' => 'Aggregate size-and-shape statistics for the published open graph: record / actor / term / edge cardinalities, descriptive coverage and a VoID-aligned dataset description. Content-negotiated (a browser gets a human dashboard).',
                'url' => $this->resolve('open-data.stats', '/data/stats'),
                'mediaTypes' => ['text/html', 'application/ld+json', 'application/json'],
                'method' => 'GET',
            ],
            [
                'id' => 'graph-dataset',
                'title' => 'Linked-data graph (dataset front door)',
                'description' => 'A self-describing VoID / DCAT dataset description of the published heritage graph, with namespaces, class counts and discovery links.',
                'url' => $this->resolve('api.v1.graph.dataset', '/api/v1/graph'),
                'mediaTypes' => ['application/ld+json'],
                'method' => 'GET',
            ],
            [
                'id' => 'graph-entity',
                'title' => 'Per-record graph neighbourhood',
                'description' => 'One record plus its cross-collection neighbours as Linked Data. Content-negotiated by Accept header, ?format= or a .jsonld / .ttl / .rdf path suffix.',
                'urlTemplate' => $base.'/api/v1/graph/{idOrSlug}',
                'mediaTypes' => ['application/ld+json', 'text/turtle', 'application/rdf+xml'],
                'method' => 'GET',
            ],
            [
                'id' => 'entity',
                'title' => 'Entity identity endpoint (record)',
                'description' => 'The stable, dereferenceable URI of a single published record, described in full (title, type, dates, creators, subjects, places, repository, parent). Content-negotiated by Accept header; a browser is 303-redirected to the human record page.',
                'urlTemplate' => $base.'/id/{slug}',
                'aliasTemplate' => $base.'/data/{slug}',
                'mediaTypes' => ['application/ld+json', 'text/turtle', 'application/rdf+xml', 'text/html'],
                'method' => 'GET',
            ],
            [
                'id' => 'entity-actor',
                'title' => 'Entity identity endpoint (actor)',
                'description' => 'The stable, dereferenceable URI of an actor - a person, corporate body or family - described in full (name, schema.org Person/Organization plus a RiC additionalType, dates of existence, biography / administrative history, and the published records it is linked to). Content-negotiated by Accept header; a browser is 303-redirected to the human authority page.',
                'urlTemplate' => $base.'/id/actor/{slug}',
                'aliasTemplate' => $base.'/data/actor/{slug}',
                'mediaTypes' => ['application/ld+json', 'text/turtle', 'application/rdf+xml', 'text/html'],
                'method' => 'GET',
            ],
            [
                'id' => 'entity-term',
                'title' => 'Entity identity endpoint (term)',
                'description' => 'The stable, dereferenceable URI of a controlled-vocabulary term - a place, subject or genre - modelled as a skos:Concept (a place is also a schema:Place), with skos:broader / skos:narrower hierarchy and the published records that reference it. Content-negotiated by Accept header; a browser is 303-redirected to the browse page filtered by the term.',
                'urlTemplate' => $base.'/id/term/{slug}',
                'aliasTemplate' => $base.'/data/term/{slug}',
                'mediaTypes' => ['application/ld+json', 'text/turtle', 'application/rdf+xml', 'text/html'],
                'method' => 'GET',
            ],
            [
                'id' => 'context',
                'title' => 'JSON-LD @context',
                'description' => 'The stable, dereferenceable JSON-LD @context shared by every graph and entity response.',
                'url' => $this->resolve('api.v1.graph.context', '/api/v1/graph/context.jsonld'),
                'mediaTypes' => ['application/ld+json'],
                'method' => 'GET',
            ],
            [
                'id' => 'crawl-seed',
                'title' => 'Crawl seed / index',
                'description' => 'A cursor-paginated enumeration of published entity URIs so a crawler can walk the whole graph.',
                'url' => $this->resolve('api.v1.graph.index', '/api/v1/graph/index'),
                'mediaTypes' => ['application/ld+json'],
                'method' => 'GET',
            ],
            [
                'id' => 'dataset-csv',
                'title' => 'Bulk dataset dump (CSV)',
                'description' => 'The whole published catalogue as a streamed CSV file (one row per record).',
                'url' => $this->resolve('api.v1.dataset.csv', '/api/v1/dataset.csv'),
                'mediaTypes' => ['text/csv'],
                'method' => 'GET',
            ],
            [
                'id' => 'dataset-jsonld',
                'title' => 'Bulk dataset dump (JSON-LD)',
                'description' => 'The whole published catalogue as a bounded, cursor-paged JSON-LD @graph.',
                'url' => $this->resolve('api.v1.dataset.jsonld', '/api/v1/dataset.jsonld'),
                'mediaTypes' => ['application/ld+json'],
                'method' => 'GET',
            ],
            [
                'id' => 'oai-pmh',
                'title' => 'OAI-PMH harvesting',
                'description' => 'A standards-compliant OAI-PMH 2.0 endpoint over the published corpus (Identify, ListRecords, GetRecord, and the rest), in simple Dublin Core.',
                'url' => $this->resolve('api.oai', '/api/oai'),
                'mediaTypes' => ['text/xml'],
                'method' => 'GET',
            ],
            [
                'id' => 'sitemap-public',
                'title' => 'Public record sitemap',
                'description' => 'An XML sitemap of the canonical public record pages for search-engine discovery.',
                'url' => $this->resolve('public.sitemap', '/sitemap.xml'),
                'mediaTypes' => ['application/xml'],
                'method' => 'GET',
            ],
            [
                'id' => 'sitemap-graph',
                'title' => 'Graph entity sitemap',
                'description' => 'An XML sitemap enumerating every per-entity graph URL so discovery -> sitemap -> entity crawl is a connected path.',
                'url' => $this->resolve('api.v1.graph.sitemap', '/api/v1/graph/sitemap.xml'),
                'mediaTypes' => ['application/xml'],
                'method' => 'GET',
            ],
            [
                'id' => 'feed-atom',
                'title' => 'Recent-changes feed (Atom)',
                'description' => 'An Atom 1.0 feed of the most recently updated published records - the "what changed recently" surface.',
                'url' => $this->resolve('public.feed.atom', '/feed.atom'),
                'mediaTypes' => ['application/atom+xml'],
                'method' => 'GET',
            ],
            [
                'id' => 'feed-rss',
                'title' => 'Recent-changes feed (RSS)',
                'description' => 'The same recency window as an RSS 2.0 channel.',
                'url' => $this->resolve('public.feed.rss', '/feed.rss'),
                'mediaTypes' => ['application/rss+xml'],
                'method' => 'GET',
            ],
            [
                'id' => 'openapi',
                'title' => 'OpenAPI specification',
                'description' => 'The machine-readable OpenAPI 3 description of the full read API.',
                'url' => $this->resolve('api.openapi.spec', '/api/openapi.json'),
                'mediaTypes' => ['application/json'],
                'method' => 'GET',
            ],
            [
                'id' => 'api-docs',
                'title' => 'API reference (Swagger UI)',
                'description' => 'Interactive human documentation for the read API.',
                'url' => $this->resolve('api.openapi.docs', '/api/docs'),
                'mediaTypes' => ['text/html'],
                'method' => 'GET',
            ],
            [
                'id' => 'dataset-cidoc-crm',
                'title' => 'Bulk dataset dump (CIDOC-CRM Turtle)',
                'description' => 'The whole published catalogue as ONE combined CIDOC-CRM (ISO 21127) Turtle graph - every record, joined by shared #crm-object fragments. Served from a scheduled dump, or a bounded on-the-fly graph when no dump is staged.',
                'url' => $this->resolve('ahgmetadataexport.cidoc.graph', '/data/cidoc-crm.ttl'),
                'mediaTypes' => ['text/turtle'],
                'method' => 'GET',
            ],
            [
                'id' => 'sitemap-data',
                'title' => 'Linked-data crawl sitemap',
                'description' => 'A sitemap index linking per-type XML sitemaps of the dereferenceable /id/... entity identity URIs (records, actors, terms), so search engines and Linked-Open-Data crawlers discover every entity URI. Bounded + paginated (50000 URLs per file).',
                'url' => $this->resolve('public.data-sitemap', '/sitemap-data.xml'),
                'mediaTypes' => ['application/xml'],
                'method' => 'GET',
            ],
            [
                'id' => 'dataset-schema-org',
                'title' => 'schema.org Dataset descriptor (search-engine indexing)',
                'description' => 'A single schema.org/Dataset node describing the whole published collection, shaped for the general web search engines that index schema.org markup (Google Dataset Search, Bing), with schema.org/DataDownload distributions (CSV, JSON-LD, CIDOC-CRM, the linked-data graph, OAI-PMH, VoID). Content-negotiated (a browser is 303-redirected to /open-data).',
                'url' => $this->resolve('open-data.dataset', '/data/dataset'),
                'mediaTypes' => ['application/ld+json', 'text/html'],
                'method' => 'GET',
            ],
        ];

        // Drop any surface that resolved to neither a URL nor a template.
        return array_values(array_filter($surfaces, static function (array $s): bool {
            return ! empty($s['url']) || ! empty($s['urlTemplate']);
        }));
    }

    /**
     * Resolve a named route to its absolute URL, falling back to a literal root
     * path when the route is not registered. Returns null when neither is
     * available so the surface can be dropped (never a dead link).
     */
    protected function resolve(string $routeName, ?string $fallbackPath = null): ?string
    {
        if (Route::has($routeName)) {
            try {
                return route($routeName);
            } catch (\Throwable $e) {
                // fall through to the literal path
            }
        }

        return $fallbackPath !== null ? url($fallbackPath) : null;
    }

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Whether the client prefers HTML (a browser). curl's default catch-all
     * Accept does NOT count as HTML, so a bare curl gets JSON.
     */
    protected function wantsHtml(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));

        if (str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json')) {
            return false;
        }

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml');
    }

    /**
     * Render a small, dependency-free human page that lists the surfaces. Built
     * from the same document() array so the two views can never drift. No Blade
     * (this package has no public layout), just escaped inline HTML.
     */
    protected function html(): string
    {
        $doc = $this->document();
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($doc['surfaces'] as $s) {
            $url = $s['url'] ?? ($s['urlTemplate'] ?? '');
            $isTemplate = empty($s['url']);
            $link = $isTemplate
                ? '<code>'.$e($url).'</code>'
                : '<a href="'.$e($url).'">'.$e($url).'</a>';
            if (! empty($s['aliasTemplate'])) {
                $link .= '<br><small>alias <code>'.$e($s['aliasTemplate']).'</code></small>';
            }
            $types = $e(implode(', ', $s['mediaTypes'] ?? []));
            $rows .= '<tr><td><strong>'.$e($s['title']).'</strong><br><small>'.$e($s['description']).'</small></td>'
                .'<td>'.$link.'</td><td><code>'.$types.'</code></td></tr>'."\n";
        }

        $title = $e($doc['name']);
        $desc = $e($doc['description']);
        $jsonUrl = $e($this->base().'/open-data/protocol.json');

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.$title.'</title>'
            .'<style>body{font-family:system-ui,Arial,sans-serif;max-width:62rem;margin:2rem auto;padding:0 1rem;color:#1a1a1a}'
            .'h1{font-size:1.5rem}table{border-collapse:collapse;width:100%}'
            .'td,th{border:1px solid #ddd;padding:.6rem;vertical-align:top;text-align:left}'
            .'th{background:#f5f5f5}code{background:#f2f2f2;padding:.1rem .3rem;border-radius:3px}'
            .'small{color:#555}.meta{color:#555;margin:.3rem 0 1.2rem}</style></head><body>'
            .'<h1>'.$title.'</h1>'
            .'<p>'.$desc.'</p>'
            .'<p class="meta">Protocol: <strong>Open Memory Protocol '.$e($doc['protocolVersion']).'</strong> &middot; '
            .'Licence: <a href="'.$e($doc['license']).'">CC-BY-4.0</a> &middot; '
            .'Authentication: '.$e($doc['authentication']).' &middot; '
            .'Machine view: <a href="'.$jsonUrl.'">'.$jsonUrl.'</a></p>'
            .'<table><thead><tr><th>Surface</th><th>URL</th><th>Media types</th></tr></thead>'
            .'<tbody>'."\n".$rows.'</tbody></table>'
            .'</body></html>';
    }

    /**
     * Apply permissive open-data CORS headers. This document is intentionally
     * world-readable (open data), so any origin may fetch it.
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
