<?php

/**
 * CookbookController - the developer cookbook for consuming the open data.
 *
 * Next slice of north-star #1204 ("the open memory protocol"). Where
 * ProtocolController publishes the machine-discoverable INDEX of every open
 * surface, and MaturityController GRADES the offering, this controller TEACHES
 * it: a developer-facing guide of copy-paste worked examples for actually
 * consuming the open data - content negotiation against entity URIs, bulk
 * downloads, harvesting, discovery, and loading the data into common tools
 * (rdflib, Apache Jena, triple stores) for local SPARQL.
 *
 *   GET /open-data/cookbook        - content-negotiated:
 *                                    text/html (a browser) -> a human guide,
 *                                    everyone else (and ?format=json) -> a JSON
 *                                    machine index of the same examples.
 *   GET /open-data/cookbook.json   - the JSON example index, explicitly.
 *
 * Every example is generated from the REAL surfaces: the example URLs are
 * resolved from ProtocolController::surfaces() (the ONE canonical surface list)
 * via url() / route(), never a hardcoded host. An example whose surface is not
 * registered on this deployment is simply omitted, so the guide never teaches a
 * surface that is not actually served, and never dead-links.
 *
 * Honest where a capability is absent: there is NO live SPARQL endpoint, so the
 * "query with SPARQL" recipes show the LOCAL path - load the Turtle / CIDOC-CRM
 * dump into rdflib, Apache Jena (riot / tdb2 / Fuseki) or another triple store
 * and query it there - and say so plainly rather than implying a hosted SPARQL
 * service exists.
 *
 * Honest + safe: read-only; performs NO DB access and NO AI calls. It only
 * resolves route URLs and inspects the protocol surface list, so it can never
 * 500 over data. Permissive open-data CORS. Jurisdiction-neutral; every URI is
 * built from url() / route(), never a hardcoded host.
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

class CookbookController extends Controller
{
    private const LICENSE = 'https://creativecommons.org/licenses/by/4.0/';

    /**
     * A representative slug used in the worked content-negotiation examples. It
     * is a placeholder - the developer substitutes a real record / actor / term
     * slug taken from the crawl seed, the data sitemap, or any record page. Kept
     * neutral and obviously-a-placeholder so nobody mistakes it for live data.
     */
    private const SAMPLE_SLUG = 'an-example-record-slug';

    /**
     * OPTIONS preflight for the cookbook endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /open-data/cookbook  (and /open-data/cookbook.json)
     *
     * A browser (text/html) gets the human guide; everyone else (and
     * ?format=json) gets the JSON example index. The .json route forces JSON.
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
     * Build the cookbook as a neutral PHP array: a set of recipe groups, each a
     * list of worked examples. Never touches the DB; only inspects the protocol
     * surface list and resolves route URLs.
     *
     * @return array<string,mixed>
     */
    protected function document(): array
    {
        $groups = $this->recipeGroups();

        $exampleCount = 0;
        foreach ($groups as $group) {
            $exampleCount += count($group['examples']);
        }

        return [
            '@context' => [
                'schema' => 'https://schema.org/',
                'dcterms' => 'http://purl.org/dc/terms/',
            ],
            '@type' => 'schema:TechArticle',
            'name' => (string) config('app.name', 'Heratio').' open-data cookbook',
            'description' => 'A developer-facing cookbook of copy-paste worked examples for consuming this '
                .'platform\'s open data: content negotiation against entity URIs, bulk dataset downloads, '
                .'OAI-PMH harvesting, discovery documents, and loading the data into common tools (rdflib, '
                .'Apache Jena, a triple store) to run SPARQL locally. Every example URL is resolved from the '
                .'live open-data surfaces, so the commands target this deployment\'s real URLs. All surfaces '
                .'are read-only, expose published records only, and are open data under CC-BY-4.0.',
            'license' => self::LICENSE,
            'licenseName' => 'CC-BY-4.0',
            'attribution' => 'Attribute to '.(string) config('app.name', 'Heratio').' and link back to the source record URI (CC-BY-4.0).',
            'cors' => 'Access-Control-Allow-Origin: * (every open-data surface is fetchable cross-origin from the browser).',
            'sparqlEndpoint' => null,
            'sparqlNote' => 'There is NO live, hosted SPARQL endpoint. To run SPARQL, download a bulk RDF dump '
                .'(the CIDOC-CRM Turtle dump or the JSON-LD dataset) and load it into a local triple store - '
                .'rdflib (Python), Apache Jena (riot / tdb2 / Fuseki), or another store - then query it there. '
                .'The "load and query locally" recipes below show exactly how.',
            'authentication' => 'none (open data)',
            'related' => [
                'protocol' => $this->resolveRoute('open-data.protocol', '/open-data/protocol'),
                'catalog' => $this->resolveRoute('open-data.catalog', '/data/catalog'),
                'maturity' => $this->resolveRoute('open-data.maturity', '/open-data/maturity'),
                'hub' => $this->base().'/open-data',
            ],
            'count' => $exampleCount,
            'recipeGroups' => $groups,
        ];
    }

    /**
     * The cookbook recipe groups. Each example resolves its URL(s) from the
     * canonical surface list, so every command targets a real, registered
     * surface on this deployment. Groups (and individual examples) whose
     * underlying surface is absent are dropped, so the guide never teaches a
     * surface that is not served and never dead-links.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function recipeGroups(): array
    {
        $byId = $this->surfacesById();
        $base = $this->base();

        $groups = [];

        // 1. Content negotiation against an entity identity URI ----------------
        // The /id/{slug} surface is a urlTemplate; build a concrete example URI
        // from its template by substituting the sample slug, so the curl lines
        // target this host's real /id/ path.
        $entity = $byId['entity'] ?? null;
        if ($entity !== null) {
            $entityUri = $this->fillTemplate(
                (string) ($entity['urlTemplate'] ?? ($base.'/id/{slug}')),
                ['slug' => self::SAMPLE_SLUG]
            );

            $examples = [
                $this->example(
                    'negotiate-jsonld',
                    'Fetch a record as JSON-LD',
                    'Dereference a record URI asking for Linked Data as JSON-LD. The same URI also serves Turtle and RDF-XML by Accept header (below), and a browser is 303-redirected to the human page.',
                    'curl -H "Accept: application/ld+json" "'.$entityUri.'"',
                    'application/ld+json'
                ),
                $this->example(
                    'negotiate-turtle',
                    'Fetch the same record as Turtle',
                    'The identical URI, content-negotiated to RDF 1.1 Turtle by the Accept header.',
                    'curl -H "Accept: text/turtle" "'.$entityUri.'"',
                    'text/turtle'
                ),
                $this->example(
                    'negotiate-rdfxml',
                    'Fetch the same record as RDF/XML',
                    'The identical URI, content-negotiated to RDF/XML.',
                    'curl -H "Accept: application/rdf+xml" "'.$entityUri.'"',
                    'application/rdf+xml'
                ),
                $this->example(
                    'negotiate-suffix',
                    'Or force a format with a path suffix',
                    'When you cannot set an Accept header, append a .jsonld / .ttl / .rdf suffix to pick the format (the per-record graph neighbourhood surface accepts the same suffixes).',
                    'curl "'.$entityUri.'.ttl"',
                    'text/turtle'
                ),
            ];

            // Actor / term identity URIs - same negotiation, different subjects.
            foreach ([['entity-actor', 'actor'], ['entity-term', 'term']] as [$id, $label]) {
                $surface = $byId[$id] ?? null;
                if ($surface === null) {
                    continue;
                }
                $uri = $this->fillTemplate(
                    (string) ($surface['urlTemplate'] ?? ($base.'/id/'.$label.'/{slug}')),
                    ['slug' => self::SAMPLE_SLUG]
                );
                $examples[] = $this->example(
                    'negotiate-'.$label,
                    'Fetch a '.$label.' authority as JSON-LD',
                    'A '.$label.' identity URI dereferenced as Linked Data. The same URI also serves Turtle and RDF-XML.',
                    'curl -H "Accept: application/ld+json" "'.$uri.'"',
                    'application/ld+json'
                );
            }

            $groups[] = [
                'id' => 'content-negotiation',
                'title' => 'Dereference an entity URI (content negotiation)',
                'description' => 'Every record, actor and term has a stable /id/... URI. Ask for the format you '
                    .'want with an Accept header (or a path suffix). Substitute a real slug - take one from the '
                    .'crawl seed, the data sitemap, or any record page.',
                'placeholder' => self::SAMPLE_SLUG,
                'examples' => $examples,
            ];
        }

        // 2. Bulk downloads + harvesting --------------------------------------
        $bulk = [];
        if ($url = $this->surfaceUrl($byId, 'dataset-csv')) {
            $bulk[] = $this->example(
                'dump-csv',
                'Download the whole catalogue as CSV',
                'The entire published catalogue as a streamed CSV file, one row per record - the simplest bulk surface for a spreadsheet or a pandas DataFrame.',
                'curl -L -o catalogue.csv "'.$url.'"',
                'text/csv'
            );
        }
        if ($url = $this->surfaceUrl($byId, 'dataset-jsonld')) {
            $bulk[] = $this->example(
                'dump-jsonld',
                'Download the whole catalogue as JSON-LD',
                'The whole catalogue as a bounded, cursor-paged JSON-LD @graph. Follow the cursor links to page through the complete set.',
                'curl -L -H "Accept: application/ld+json" -o dataset.jsonld "'.$url.'"',
                'application/ld+json'
            );
        }
        if ($url = $this->surfaceUrl($byId, 'dataset-cidoc-crm')) {
            $bulk[] = $this->example(
                'dump-cidoc-crm',
                'Download the combined CIDOC-CRM Turtle graph',
                'The whole catalogue as ONE combined CIDOC-CRM (ISO 21127) Turtle graph - records joined by shared object fragments. This is the file to load into a triple store for local SPARQL (see the tooling recipes below).',
                'curl -L -H "Accept: text/turtle" -o heritage-crm.ttl "'.$url.'"',
                'text/turtle'
            );
        }
        if ($url = $this->surfaceUrl($byId, 'oai-pmh')) {
            $bulk[] = $this->example(
                'harvest-oai-identify',
                'Harvest: identify the OAI-PMH repository',
                'Bulk metadata harvesting uses the standards-compliant OAI-PMH 2.0 endpoint. Start with Identify to confirm the repository, then ListRecords to harvest. This is the surface a library / archive aggregator (a union catalogue harvester) points at.',
                'curl "'.$url.'?verb=Identify"',
                'text/xml'
            );
            $bulk[] = $this->example(
                'harvest-oai-listrecords',
                'Harvest: list records in Dublin Core',
                'Stream the published corpus as OAI-PMH records in simple Dublin Core. Follow the resumptionToken in each response to page through the whole repository; add &from=YYYY-MM-DD for selective (incremental) harvesting.',
                'curl "'.$url.'?verb=ListRecords&metadataPrefix=oai_dc"',
                'text/xml'
            );
        }
        if ($bulk !== []) {
            $groups[] = [
                'id' => 'bulk-and-harvest',
                'title' => 'Bulk download and harvest',
                'description' => 'Pull the whole corpus in one go: the CSV / JSON-LD / CIDOC-CRM dumps for a '
                    .'snapshot, or OAI-PMH for incremental, standards-based harvesting into an aggregator.',
                'examples' => $bulk,
            ];
        }

        // 3. Discovery documents ----------------------------------------------
        $discovery = [];
        $protocolUrl = $this->resolveRoute('open-data.protocol', '/open-data/protocol');
        if ($protocolUrl !== null) {
            $discovery[] = $this->example(
                'discover-protocol',
                'Fetch the capabilities document',
                'The machine-discoverable index of every open surface, with each URL and its media types. Fetch this one document and you learn how to consume everything else - no prior knowledge of the host required.',
                'curl -H "Accept: application/json" "'.$protocolUrl.'"',
                'application/json'
            );
        }
        if ($url = $this->surfaceUrl($byId, 'discovery')) {
            $discovery[] = $this->example(
                'discover-void',
                'Fetch the VoID / DCAT discovery description',
                'The zero-knowledge entry point: a VoID / DCAT dataset description (Turtle) that links on to every other surface.',
                'curl -H "Accept: text/turtle" "'.$url.'"',
                'text/turtle'
            );
        }
        $catalogUrl = $this->resolveRoute('open-data.catalog', '/data/catalog');
        if ($catalogUrl !== null) {
            $discovery[] = $this->example(
                'discover-dcat',
                'Fetch the DCAT data catalogue',
                'The same surface list re-described as a dcat:Catalog of dcat:Datasets and dcat:Distributions, for DCAT-aware open-data harvesters (CKAN, the European Data Portal).',
                'curl -H "Accept: application/ld+json" "'.$catalogUrl.'"',
                'application/ld+json'
            );
        }
        if ($url = $this->surfaceUrl($byId, 'dataset-schema-org')) {
            $discovery[] = $this->example(
                'discover-schema-org',
                'Fetch the schema.org Dataset descriptor',
                'A single schema.org/Dataset node describing the whole collection, shaped for general web search engines (Google Dataset Search, Bing) that index schema.org markup.',
                'curl -H "Accept: application/ld+json" "'.$url.'"',
                'application/ld+json'
            );
        }
        if ($url = $this->surfaceUrl($byId, 'sitemap-data')) {
            $discovery[] = $this->example(
                'discover-sitemap',
                'Fetch the linked-data crawl sitemap',
                'A sitemap index linking per-type XML sitemaps of every dereferenceable /id/... entity URI, so a crawler can walk the whole graph from one starting point.',
                'curl "'.$url.'"',
                'application/xml'
            );
        }
        if ($url = $this->surfaceUrl($byId, 'crawl-seed')) {
            $discovery[] = $this->example(
                'discover-crawl-seed',
                'Page through the crawl seed (entity URIs)',
                'A cursor-paginated enumeration of published entity URIs - the programmatic way to discover real slugs to plug into the content-negotiation recipes above.',
                'curl -H "Accept: application/ld+json" "'.$url.'"',
                'application/ld+json'
            );
        }
        if ($discovery !== []) {
            $groups[] = [
                'id' => 'discovery',
                'title' => 'Discover what is on offer',
                'description' => 'Self-describing documents that let an agent learn the whole offering with no '
                    .'prior knowledge: the capabilities index, VoID, DCAT, the schema.org Dataset, and the '
                    .'crawl sitemap / seed.',
                'examples' => $discovery,
            ];
        }

        // 4. Load into common tools + query locally (no hosted SPARQL) --------
        // The recipes target the CIDOC-CRM dump when present, otherwise a
        // placeholder filename so the guide is still a usable how-to. We are
        // explicit that SPARQL is run LOCALLY against a downloaded dump.
        $ttlUrl = $this->surfaceUrl($byId, 'dataset-cidoc-crm');
        $ttlFile = 'heritage-crm.ttl';
        $tooling = [
            $this->example(
                'load-rdflib',
                'Load the Turtle dump into rdflib (Python) and query with SPARQL',
                'There is no hosted SPARQL endpoint, so query a downloaded dump locally. rdflib parses the '
                    .'Turtle dump into an in-memory graph you can run SPARQL against.'
                    .($ttlUrl !== null ? ' Download it first: curl -L -o '.$ttlFile.' "'.$ttlUrl.'"' : ''),
                "python3 - <<'PY'\n"
                    ."import rdflib\n"
                    ."g = rdflib.Graph()\n"
                    ."g.parse('".$ttlFile."', format='turtle')\n"
                    ."print(len(g), 'triples loaded')\n"
                    ."q = '''SELECT ?s ?p ?o WHERE { ?s ?p ?o } LIMIT 10'''\n"
                    ."for row in g.query(q):\n"
                    ."    print(row)\n"
                    ."PY",
                'text/turtle'
            ),
            $this->example(
                'load-jena-riot',
                'Validate / convert the dump with Apache Jena (riot)',
                'Apache Jena\'s riot tool validates the Turtle and can convert it to N-Triples or RDF/XML before loading. A clean riot run confirms the dump parses.',
                'riot --validate '.$ttlFile."\n"
                    .'riot --output=ntriples '.$ttlFile.' > heritage.nt',
                'text/turtle'
            ),
            $this->example(
                'load-jena-tdb2-sparql',
                'Load into Apache Jena TDB2 and query with SPARQL locally',
                'Load the dump into a local Jena TDB2 dataset, then run SPARQL against it on your own machine - '
                    .'this is the local replacement for a hosted SPARQL endpoint.',
                'tdb2.tdbloader --loc=./heritage-tdb '.$ttlFile."\n"
                    ."tdb2.tdbquery --loc=./heritage-tdb \\\n"
                    ."  'SELECT (COUNT(*) AS ?triples) WHERE { ?s ?p ?o }'",
                'application/sparql-results+json'
            ),
            $this->example(
                'load-fuseki',
                'Serve the dump from a local Fuseki SPARQL server',
                'To get a SPARQL endpoint, run one yourself: Apache Jena Fuseki serves the loaded dataset over '
                    .'a local SPARQL protocol endpoint at http://localhost:3030/heritage/sparql. (This is your '
                    .'own instance - the platform does not host one.)',
                'fuseki-server --file='.$ttlFile.' /heritage',
                'application/sparql-query'
            ),
        ];
        $groups[] = [
            'id' => 'load-and-query',
            'title' => 'Load into a triple store and query with SPARQL (locally)',
            'description' => 'There is no live, hosted SPARQL endpoint. The honest path is to download a bulk '
                .'RDF dump and load it into a triple store you run - rdflib, Apache Jena (riot / TDB2 / Fuseki), '
                .'or another store - then query it there. These recipes show exactly that.'
                .($ttlUrl !== null ? '' : ' (No Turtle dump surface is registered on this install; the '
                    .'commands assume a Turtle file you obtained from another surface.)'),
            'sparqlEndpoint' => null,
            'dumpUrl' => $ttlUrl,
            'examples' => $tooling,
        ];

        // 5. Licence + attribution + CORS -------------------------------------
        $groups[] = [
            'id' => 'licence-attribution',
            'title' => 'Licence, attribution and CORS',
            'description' => 'All open-data surfaces are released under CC-BY-4.0. Re-use freely, including '
                .'commercially, provided you attribute the source and link back to the record URI. Every surface '
                .'sends Access-Control-Allow-Origin: *, so you can fetch any of them directly from browser '
                .'JavaScript (fetch / XMLHttpRequest) with no proxy.',
            'license' => self::LICENSE,
            'licenseName' => 'CC-BY-4.0',
            'examples' => [
                $this->example(
                    'inspect-cors-headers',
                    'Confirm the open-data CORS + licence headers',
                    'Every open surface is world-readable and cross-origin fetchable. The response carries '
                        .'X-Open-Data: true and Access-Control-Allow-Origin: *.',
                    'curl -I "'.($protocolUrl ?? $base.'/open-data/protocol').'"',
                    'application/json'
                ),
            ],
        ];

        return $groups;
    }

    /**
     * Shape one worked example as a neutral array.
     *
     * @return array<string,mixed>
     */
    protected function example(string $id, string $title, string $description, string $command, ?string $mediaType = null): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'command' => $command,
            'mediaType' => $mediaType,
        ];
    }

    /**
     * The canonical surface list from ProtocolController, indexed by id. Resolved
     * defensively: if the protocol controller is unavailable for any reason the
     * cookbook degrades to the tooling + licence recipes (which need no surface)
     * rather than throwing.
     *
     * @return array<string,array<string,mixed>>
     */
    protected function surfacesById(): array
    {
        try {
            $surfaces = app(ProtocolController::class)->surfaces();
        } catch (\Throwable $e) {
            return [];
        }

        $byId = [];
        foreach ($surfaces as $surface) {
            if (! empty($surface['id'])) {
                $byId[(string) $surface['id']] = $surface;
            }
        }

        return $byId;
    }

    /**
     * The resolved URL (or URL template) of a surface by id, or null when the
     * surface is not registered on this deployment.
     *
     * @param array<string,array<string,mixed>> $byId
     */
    protected function surfaceUrl(array $byId, string $id): ?string
    {
        $surface = $byId[$id] ?? null;
        if ($surface === null) {
            return null;
        }
        $url = $surface['url'] ?? ($surface['urlTemplate'] ?? null);

        return ! empty($url) ? (string) $url : null;
    }

    /**
     * Substitute {placeholders} in a URL template with concrete values, so an
     * example command targets a real path on this host.
     *
     * @param array<string,string> $vars
     */
    protected function fillTemplate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{'.$key.'}', $value, $template);
        }

        return $template;
    }

    /**
     * Resolve a named route to its absolute URL, falling back to a literal root
     * path when the route is not registered. Returns null when neither is
     * available (so the link can be dropped, never dead).
     */
    protected function resolveRoute(string $routeName, ?string $fallbackPath = null): ?string
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
     * Render a small, dependency-free human guide from the same document() array
     * so the two views can never drift. No Blade (this package has no public
     * layout), just escaped inline HTML.
     */
    protected function html(): string
    {
        $doc = $this->document();
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $sections = '';
        foreach ($doc['recipeGroups'] as $group) {
            $recipes = '';
            foreach ($group['examples'] as $ex) {
                $type = ! empty($ex['mediaType'])
                    ? ' <span class="mt"><code>'.$e($ex['mediaType']).'</code></span>'
                    : '';
                $recipes .= '<div class="recipe">'
                    .'<h3>'.$e($ex['title']).$type.'</h3>'
                    .'<p>'.$e($ex['description']).'</p>'
                    .'<pre><code>'.$e($ex['command']).'</code></pre>'
                    .'</div>'."\n";
            }

            $note = '';
            if (array_key_exists('sparqlEndpoint', $group) && empty($group['sparqlEndpoint'])) {
                $note = '<p class="warn">No hosted SPARQL endpoint: these recipes run SPARQL locally against a downloaded dump.</p>';
            }

            $sections .= '<section><h2>'.$e($group['title']).'</h2>'
                .'<p class="gdesc">'.$e($group['description']).'</p>'
                .$note
                .$recipes
                .'</section>'."\n";
        }

        $title = $e($doc['name']);
        $desc = $e($doc['description']);
        $jsonUrl = $e($this->base().'/open-data/cookbook.json');
        $protocol = $e((string) ($doc['related']['protocol'] ?? ''));
        $catalog = $e((string) ($doc['related']['catalog'] ?? ''));
        $maturity = $e((string) ($doc['related']['maturity'] ?? ''));

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.$title.'</title>'
            .'<style>body{font-family:system-ui,Arial,sans-serif;max-width:62rem;margin:2rem auto;padding:0 1rem;color:#1a1a1a;line-height:1.5}'
            .'h1{font-size:1.6rem;margin-bottom:.2rem}h2{font-size:1.25rem;margin:1.6rem 0 .3rem;border-bottom:1px solid #eee;padding-bottom:.2rem}'
            .'h3{font-size:1.02rem;margin:.9rem 0 .3rem}'
            .'.gdesc{color:#444}.recipe{margin:.6rem 0 1rem}'
            .'pre{background:#1e1e1e;color:#e6e6e6;padding:.8rem 1rem;border-radius:6px;overflow:auto;font-size:.85rem}'
            .'pre code{background:none;padding:0;color:inherit}'
            .'code{background:#f2f2f2;padding:.1rem .3rem;border-radius:3px;font-size:.85rem}'
            .'.mt code{background:#eef4ff;color:#244}'
            .'.warn{background:#fff7e6;border:1px solid #f0d28a;color:#7a5a00;padding:.5rem .8rem;border-radius:6px}'
            .'.meta{color:#555;margin:.3rem 0 1.2rem}</style></head><body>'
            .'<h1>'.$title.'</h1>'
            .'<p>'.$desc.'</p>'
            .'<p class="meta">Licence: <a href="'.$e($doc['license']).'">CC-BY-4.0</a> &middot; '
            .'Authentication: '.$e($doc['authentication']).' &middot; '
            .'Machine index: <a href="'.$jsonUrl.'">'.$jsonUrl.'</a></p>'
            .'<p class="meta">See also: '
            .($protocol !== '' ? '<a href="'.$protocol.'">capabilities index</a>' : '')
            .($catalog !== '' ? ' &middot; <a href="'.$catalog.'">DCAT catalogue</a>' : '')
            .($maturity !== '' ? ' &middot; <a href="'.$maturity.'">maturity scorecard</a>' : '')
            .'</p>'
            .$sections
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
