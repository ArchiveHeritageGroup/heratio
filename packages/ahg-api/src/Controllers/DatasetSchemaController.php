<?php

/**
 * DatasetSchemaController - the schema.org/Dataset descriptor for general search
 * engines (Google Dataset Search, Bing, ...).
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). The platform already publishes its open-data offering twice for
 * machines: as a bespoke capabilities document (ProtocolController,
 * /open-data/protocol) and as a W3C DCAT-AP catalogue (CatalogController,
 * /data/catalog). Both speak to data-portal harvesters. This controller adds the
 * THIRD machine view, aimed at a different audience entirely: the general web
 * search engines that index schema.org markup. Google Dataset Search (and Bing)
 * recognise a single schema.org/Dataset node with schema.org/DataDownload
 * distributions; emitting exactly that shape gets the whole published collection
 * indexed AS A DATASET, so it surfaces in dataset search results, not only in the
 * generic web index.
 *
 *   GET /data/dataset.jsonld  - the schema.org/Dataset as JSON-LD (machine
 *                               default), application/ld+json.
 *   GET /data/dataset         - content-negotiated: a browser (text/html) is
 *                               303-redirected to the /open-data landing page
 *                               (the human "Open Data & APIs" hub); everyone
 *                               else (and a bare curl) gets the JSON-LD.
 *
 * Distinct from /data/catalog: the DCAT catalogue describes the offering as a
 * dcat:Catalog of many dcat:Datasets (one per surface) in the open-data-portal
 * vocabulary. THIS surface describes the published collection as ONE
 * schema.org/Dataset in the vocabulary the general search engines crawl, with the
 * bulk dumps + entity endpoints as schema.org/DataDownload distributions. They do
 * not compete; they target different consumers (data portals vs web search).
 *
 * Shape (a single schema.org/Dataset node):
 *   - name, description, url, identifier, license (CC-BY-4.0), keywords;
 *   - creator + publisher (a schema.org/Organization from config('app.name'));
 *   - includedInDataCatalog -> the DCAT catalogue (/data/catalog);
 *   - temporalCoverage (the collection's date span, cheap MIN/MAX over events)
 *     and spatialCoverage (a handful of the most-used place terms) - both
 *     best-effort and guarded, omitted rather than faked when unavailable;
 *   - size hints via schema:size + the distribution count, with the
 *     published-record count reused from the SAME aggregate the /data/stats
 *     surface computes (StatsController), so the figures never drift;
 *   - distribution[] - one schema.org/DataDownload per bulk dump + entity
 *     endpoint (CSV, JSON-LD, CIDOC-CRM Turtle, plus the linked-data graph,
 *     OAI-PMH and the VoID description), each with encodingFormat + contentUrl,
 *     derived from ProtocolController::surfaces() so the list never diverges from
 *     the canonical open-surface list.
 *
 * Honest + safe: read-only throughout. The only DB access is the cheap aggregate
 * counts (reused from StatsController) plus a guarded MIN/MAX date span and a
 * guarded top-places query - all COUNT / GROUP BY / MIN / MAX, no per-record
 * loop, no writes, no ALTER. Every figure is independently try/guarded so a
 * missing table yields an omitted field, never a 500; an empty corpus yields a
 * valid minimal Dataset. Permissive open-data CORS. Every URI is built from
 * url() / route(), never a hardcoded host. Jurisdiction-neutral.
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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class DatasetSchemaController extends Controller
{
    /** The open-data licence the whole offering is published under. */
    private const LICENSE = 'https://creativecommons.org/licenses/by/4.0/';

    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    /** Controlled-vocabulary taxonomy id for places (mirrors StatsController). */
    private const TAXONOMY_PLACE = 42;

    /** How many top place terms to surface as spatialCoverage (kept small). */
    private const SPATIAL_TOP_N = 12;

    /**
     * The schema.org distribution surfaces, by their ProtocolController surface
     * id, in the order they should appear. Only these surfaces become a
     * schema:DataDownload (the bulk dumps + the crawlable entry points a dataset
     * consumer actually downloads or harvests); presentation surfaces (HTML
     * dashboards, Swagger UI, sitemaps, feeds) are intentionally excluded - they
     * are not dataset distributions.
     *
     * @var array<int,string>
     */
    private const DISTRIBUTION_SURFACE_IDS = [
        'dataset-csv',
        'dataset-jsonld',
        'dataset-cidoc-crm',
        'graph-dataset',
        'graph-entity',
        'oai-pmh',
        'discovery',
    ];

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    /**
     * OPTIONS preflight for the dataset descriptor endpoints.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /data/dataset.jsonld  (and /data/dataset, content-negotiated).
     *
     * The .jsonld route always serves JSON-LD. The suffix-less /data/dataset
     * route 303-redirects a browser (text/html) to the /open-data human hub and
     * serves JSON-LD to everyone else (a bare curl included). Resilient: any data
     * fault degrades a field to omitted, never a 500.
     */
    public function index(Request $request, bool $forceJson = false): Response
    {
        if (! $forceJson && $this->wantsHtml($request)) {
            // Send a browser to the human "Open Data & APIs" hub, where the same
            // schema.org Dataset JSON-LD can be embedded as a <script> snippet.
            return $this->withCors(
                redirect()->to($this->openDataLanding(), 303)
            );
        }

        $body = json_encode(
            $this->dataset(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->withCors(response($body, 200, [
            'Content-Type' => 'application/ld+json; charset=utf-8',
        ]));
    }

    // -----------------------------------------------------------------
    // The schema.org/Dataset node (the single source view)
    // -----------------------------------------------------------------

    /**
     * Build the schema.org/Dataset as a neutral PHP array (the exact JSON-LD that
     * is encoded for the endpoint and that the /open-data page may embed).
     *
     * @return array<string,mixed>
     */
    public function dataset(): array
    {
        $base = $this->base();
        $name = (string) config('app.name', 'Heratio');

        $doc = [
            '@context' => 'https://schema.org/',
            '@type' => 'Dataset',
            '@id' => $base.'/data/dataset.jsonld',
            'name' => $name.' published heritage collection',
            'description' => 'The complete published catalogue of '.$name.' as open data: '
                .'archival descriptions, the people / organisations / families that created them, '
                .'and the places, subjects and genres they are about, with stable linked-data '
                .'identifiers. Available as bulk CSV and JSON-LD dumps, a combined CIDOC-CRM '
                .'graph, an OAI-PMH harvesting endpoint and per-entity linked-data URIs. '
                .'Read-only; published records only; open data under CC-BY-4.0.',
            'url' => $this->openDataLanding(),
            'sameAs' => $base.'/data/catalog',
            'identifier' => $base.'/data/dataset.jsonld',
            'license' => self::LICENSE,
            'isAccessibleForFree' => true,
            'keywords' => $this->keywords(),
            'inLanguage' => $this->culture,
            'creator' => $this->organization($name, $base),
            'publisher' => $this->organization($name, $base),
            // Point a dataset consumer at the full DCAT catalogue this Dataset is
            // a member of (the open-data-portal view of the same offering).
            'includedInDataCatalog' => [
                '@type' => 'DataCatalog',
                '@id' => $base.'/data/catalog',
                'name' => $name.' open-data catalogue',
                'url' => $base.'/data/catalog',
            ],
            'distribution' => $this->distributions($base),
        ];

        // Cheap, best-effort coverage + size figures. Each is omitted (never
        // faked) when its source table is absent or empty.
        $temporal = $this->temporalCoverage();
        if ($temporal !== null) {
            $doc['temporalCoverage'] = $temporal;
        }

        $spatial = $this->spatialCoverage();
        if ($spatial) {
            $doc['spatialCoverage'] = $spatial;
        }

        $size = $this->publishedRecordCount();
        if ($size > 0) {
            // schema:size as a human-readable QuantitativeValue (record count).
            $doc['size'] = [
                '@type' => 'QuantitativeValue',
                'value' => $size,
                'unitText' => 'records',
            ];
            $doc['variableMeasured'] = 'Published archival descriptions';
        }

        $doc['dateModified'] = now()->toIso8601String();

        return $doc;
    }

    /**
     * The publisher / creator as a schema.org Organization. Jurisdiction-neutral:
     * the name comes from config('app.name') and the homepage is this host - no
     * tenant constant.
     *
     * @return array<string,string>
     */
    protected function organization(string $name, string $base): array
    {
        return [
            '@type' => 'Organization',
            '@id' => $base.'/#organization',
            'name' => $name,
            'url' => $base,
        ];
    }

    /**
     * Dataset keywords. A small, stable, jurisdiction-neutral set describing the
     * domain (so Dataset Search can categorise the collection) plus the app name.
     *
     * @return array<int,string>
     */
    protected function keywords(): array
    {
        return [
            'archives',
            'cultural heritage',
            'GLAM',
            'linked open data',
            'archival descriptions',
            'finding aids',
            (string) config('app.name', 'Heratio'),
        ];
    }

    // -----------------------------------------------------------------
    // Distributions (schema:DataDownload from the canonical surface list)
    // -----------------------------------------------------------------

    /**
     * Build the schema:DataDownload distributions from ProtocolController's
     * canonical surface list, so this Dataset's downloads never diverge from the
     * open-surface index. Only the bulk dumps + crawlable entry points (the
     * DISTRIBUTION_SURFACE_IDS allow-list) become distributions; presentation
     * surfaces (HTML, Swagger UI, sitemaps, feeds) are excluded. A surface that
     * serves several media types yields several DataDownloads (one per format).
     *
     * Resolved defensively: if the protocol surface list is unavailable for any
     * reason, the Dataset degrades to no distributions (still valid), never a 500.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function distributions(string $base): array
    {
        try {
            $surfaces = app(ProtocolController::class)->surfaces();
        } catch (\Throwable $e) {
            $surfaces = [];
        }

        // Index the surfaces by id for a stable, allow-listed ordering.
        $byId = [];
        foreach ($surfaces as $surface) {
            if (! empty($surface['id'])) {
                $byId[(string) $surface['id']] = $surface;
            }
        }

        $protocolUrl = $this->resolve('open-data.protocol', '/open-data/protocol');

        $downloads = [];
        foreach (self::DISTRIBUTION_SURFACE_IDS as $id) {
            if (! isset($byId[$id])) {
                continue;
            }
            $surface = $byId[$id];

            $url = $surface['url'] ?? null;
            $template = $surface['urlTemplate'] ?? null;
            $isTemplate = $url === null && ! empty($template);

            // A URL template (e.g. /api/v1/graph/{idOrSlug}) is not a
            // dereferenceable contentUrl. For a template surface the
            // schema:contentUrl points at the protocol capabilities document (a
            // real URL) and the literal template is carried in the description.
            $contentUrl = $url ?? $protocolUrl;
            if (empty($contentUrl)) {
                continue;
            }

            $mediaTypes = is_array($surface['mediaTypes'] ?? null) ? $surface['mediaTypes'] : [];
            if (! $mediaTypes) {
                $mediaTypes = ['application/octet-stream'];
            }

            foreach ($mediaTypes as $mt) {
                // For an entity-template surface the descriptive media types
                // include text/html (the 303 to a human page) - skip that one as
                // a "download": a Dataset distribution is the data, not the page.
                if ($isTemplate && in_array(strtolower((string) $mt), ['text/html', 'application/xhtml+xml'], true)) {
                    continue;
                }

                $dl = [
                    '@type' => 'DataDownload',
                    'name' => (string) ($surface['title'] ?? 'Distribution'),
                    'encodingFormat' => (string) $mt,
                    'contentUrl' => (string) $contentUrl,
                    'license' => self::LICENSE,
                ];
                if ($isTemplate) {
                    $dl['description'] = 'URL template: '.$template
                        .' (substitute the placeholder; see /open-data/protocol).';
                } elseif (! empty($surface['description'])) {
                    $dl['description'] = (string) $surface['description'];
                }
                $downloads[] = $dl;
            }
        }

        return $downloads;
    }

    // -----------------------------------------------------------------
    // Coverage + size (cheap, guarded; figures reused from the stats logic)
    // -----------------------------------------------------------------

    /**
     * Published record count: reuse the SAME aggregate the /data/stats surface
     * computes (StatsController::compute() -> published_records), so the size on
     * this Dataset never diverges from the stats dashboard. Falls back to a local
     * guarded count if the stats controller is unavailable.
     */
    protected function publishedRecordCount(): int
    {
        try {
            $stats = app(StatsController::class)->compute();
            if (is_array($stats) && isset($stats['published_records'])) {
                return (int) $stats['published_records'];
            }
        } catch (\Throwable $e) {
            // Fall through to the local guarded count.
        }

        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('status')) {
                return 0;
            }

            return (int) $this->publishedQuery()->count('io.id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * The collection's date span as an ISO 8601 interval "START/END" (years), a
     * cheap MIN(start)/MAX(end) over the event table restricted to published
     * records. Best-effort: returns null when there are no usable dates so the
     * field is omitted rather than faked. AtoM stores dates as strings, so the
     * span is computed from the four-digit leading year only (robust to "-00-00"
     * placeholders and partial dates).
     */
    protected function temporalCoverage(): ?string
    {
        try {
            if (! Schema::hasTable('event') || ! Schema::hasTable('information_object') || ! Schema::hasTable('status')) {
                return null;
            }

            // Leading 4-digit year of a stored date string, NULL when absent.
            $startYear = 'NULLIF(CAST(LEFT(e.start_date, 4) AS UNSIGNED), 0)';
            $endYear = 'NULLIF(CAST(LEFT(e.end_date, 4) AS UNSIGNED), 0)';

            $row = $this->publishedQuery()
                ->join('event as e', 'e.object_id', '=', 'io.id')
                ->selectRaw("MIN($startYear) as min_year, MAX(COALESCE($endYear, $startYear)) as max_year")
                ->first();

            $min = $row && $row->min_year ? (int) $row->min_year : 0;
            $max = $row && $row->max_year ? (int) $row->max_year : 0;

            // Sanity bounds: a plausible historical-to-near-future window. A wild
            // value (a malformed string) is dropped rather than published.
            if ($min < 1 || $min > 2200) {
                $min = 0;
            }
            if ($max < 1 || $max > 2200) {
                $max = 0;
            }

            if ($min > 0 && $max > 0 && $max >= $min) {
                return $min === $max ? (string) $min : $min.'/'.$max;
            }
            if ($min > 0) {
                return (string) $min;
            }
            if ($max > 0) {
                return (string) $max;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A small spatialCoverage list: the most-referenced place terms, as
     * schema:Place nodes. Cheap: a single GROUP BY over object_term_relation
     * joined to the place taxonomy, limited to SPATIAL_TOP_N. Best-effort:
     * returns [] (the field is omitted) on any schema variance or an empty
     * corpus.
     *
     * @return array<int,array<string,string>>
     */
    protected function spatialCoverage(): array
    {
        try {
            if (! Schema::hasTable('object_term_relation') || ! Schema::hasTable('term') || ! Schema::hasTable('term_i18n')) {
                return [];
            }

            $rows = DB::table('object_term_relation as otr')
                ->join('term as t', function ($j) {
                    $j->on('otr.term_id', '=', 't.id')
                        ->where('t.taxonomy_id', '=', self::TAXONOMY_PLACE);
                })
                ->join('term_i18n as ti', function ($j) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->whereNotNull('ti.name')
                ->where('ti.name', '!=', '')
                ->select('ti.name', DB::raw('COUNT(*) as n'))
                ->groupBy('ti.name')
                ->orderByDesc('n')
                ->limit(self::SPATIAL_TOP_N)
                ->pluck('ti.name');

            $places = [];
            foreach ($rows as $placeName) {
                $places[] = [
                    '@type' => 'Place',
                    'name' => (string) $placeName,
                ];
            }

            return $places;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------

    /**
     * The shared published-only query: information_object joined to a Published
     * status row (type_id=158, status_id=160), synthetic root (id 1) excluded.
     * Identical gate to StatsController / GraphController / DatasetController.
     */
    protected function publishedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', '=', self::STATUS_PUBLISHED);
            })
            ->where('io.id', '!=', self::ROOT_ID);
    }

    /**
     * Resolve a named route to its absolute URL, falling back to a literal root
     * path when the route is not registered.
     */
    protected function resolve(string $routeName, ?string $fallbackPath = null): ?string
    {
        if (Route::has($routeName)) {
            try {
                return route($routeName);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return $fallbackPath !== null ? url($fallbackPath) : null;
    }

    /**
     * The human "Open Data & APIs" landing page. Resolved via its route name with
     * a literal /open-data fallback so a slimmer install still gets a real URL.
     */
    protected function openDataLanding(): string
    {
        return (string) $this->resolve('open-data.index', '/open-data');
    }

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Whether the client prefers HTML (a browser). A bare curl's catch-all Accept
     * does NOT count as HTML, so a plain curl gets the JSON-LD.
     */
    protected function wantsHtml(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));

        if (str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json')) {
            return false;
        }

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml');
    }

    // -----------------------------------------------------------------
    // CORS
    // -----------------------------------------------------------------

    /**
     * Apply permissive open-data CORS headers. This descriptor is intentionally
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
