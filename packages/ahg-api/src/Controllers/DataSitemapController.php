<?php

/**
 * DataSitemapController - crawl sitemap for the Linked-Data entity graph.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). Where PublicSitemapController enumerates the human /{slug} record
 * PAGES for search engines, and GraphController::sitemap enumerates the
 * per-record /api/v1/graph/{id} NEIGHBOURHOOD URLs, THIS controller enumerates
 * the canonical dereferenceable ENTITY IDENTITY URIs - the /id/... surfaces
 * served by EntityController / ActorEntityController / TermEntityController - so
 * search engines and Linked-Open-Data crawlers discover every entity's stable
 * "thing" URI:
 *
 *   GET /sitemap-data.xml          - a <sitemapindex> linking the per-type
 *                                     sitemaps below (one entry per page of each
 *                                     type), so a crawler walks the whole graph.
 *   GET /sitemap-data-records.xml  - a <urlset> of /id/{slug} record URIs.
 *   GET /sitemap-data-actors.xml   - a <urlset> of /id/actor/{slug} actor URIs.
 *   GET /sitemap-data-terms.xml    - a <urlset> of /id/term/{slug} term URIs.
 *
 * Each per-type sitemap is bounded and paginated: ?page=N selects one slice,
 * capped at MAX_URLS_PER_SITEMAP (the sitemaps.org 50000-URL ceiling). The index
 * lists every page of every type. The query for each page is a single bounded
 * SELECT (offset + limit over a deterministic id order) so the whole catalogue
 * is never materialised in memory.
 *
 * Published-only, root-excluded enumeration - the EXACT gate the rest of the
 * public open-data surfaces use:
 *   - RECORDS: information_object joined to a Published status row
 *     (status.type_id=158, status_id=160), synthetic root id=1 excluded, slug
 *     from the slug table. Drafts are NEVER exposed. (Mirrors
 *     EntityController / PublicSitemapController.)
 *   - ACTORS + TERMS: reference / authority entities with no publication gate of
 *     their own (mirrors ActorEntityController / TermEntityController), root
 *     excluded; actors exclude the repository subtype so /id/actor/ stays
 *     people / corporate bodies / families.
 *
 * Each <loc> is the /id/... URI built with Laravel's url() helper - never a
 * hardcoded host - so a fresh install on its own domain self-describes. lastmod
 * is emitted where it is cheap (records carry object.updated_at; actors and
 * terms have no per-row timestamp on the open path, so they omit it).
 *
 * Resilience: an empty corpus yields a valid EMPTY <urlset> / a minimal index,
 * never a 500. Every query is guarded (Schema::hasTable + try/catch); a schema
 * variance degrades to an empty set rather than an error. Read-only throughout;
 * public (no auth); permissive open CORS so any crawler may fetch it. Every
 * emitted document is well-formed, entity-escaped XML.
 *
 * Jurisdiction-neutral: standards-based sitemaps.org 0.9 schema, no market
 * assumptions, no hardcoded host.
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
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class DataSitemapController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object / actor / term id, always excluded. */
    private const ROOT_ID = 1;

    /**
     * Per-sitemap URL ceiling. The sitemaps.org spec allows up to 50000 URLs
     * (and 50 MB) per <urlset>; this is that hard cap. A type with more entities
     * than this is split across ?page=N child sitemaps, each listed in the index.
     */
    private const MAX_URLS_PER_SITEMAP = 50000;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    // -----------------------------------------------------------------
    // GET /sitemap-data.xml  - the sitemap INDEX
    // -----------------------------------------------------------------

    /**
     * The sitemap index: a <sitemapindex> linking every page of every per-type
     * entity sitemap (records, actors, terms). A crawler that fetches this one
     * document discovers all the /id/... entity URIs in the graph.
     *
     * Each type contributes ceil(count / MAX_URLS_PER_SITEMAP) child entries
     * (at least one, so an empty type still advertises its - empty but valid -
     * page-1 sitemap). Resilient: a data-layer fault counts a type as empty, so
     * the index is always a valid document, never a 500.
     */
    public function index(Request $request): Response
    {
        $types = ['records', 'actors', 'terms'];

        $now = $this->w3cDate((string) now()->toIso8601String());

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($types as $type) {
            try {
                $total = $this->countFor($type);
            } catch (\Throwable $e) {
                $total = 0;
            }

            $pageCount = $total > 0 ? (int) ceil($total / self::MAX_URLS_PER_SITEMAP) : 1;

            for ($p = 1; $p <= $pageCount; $p++) {
                $loc = $p === 1
                    ? $this->absolute('/sitemap-data-'.$type.'.xml')
                    : $this->absolute('/sitemap-data-'.$type.'.xml?page='.$p);

                $out .= '  <sitemap>'."\n";
                $out .= '    <loc>'.$this->xmlEscape($loc).'</loc>'."\n";
                $out .= '    <lastmod>'.$this->xmlEscape($now).'</lastmod>'."\n";
                $out .= '  </sitemap>'."\n";
            }
        }

        $out .= '</sitemapindex>'."\n";

        return $this->xmlResponse($out);
    }

    // -----------------------------------------------------------------
    // GET /sitemap-data-records.xml  - /id/{slug} record URIs
    // -----------------------------------------------------------------

    /**
     * A <urlset> over one page of PUBLISHED record entity URIs (/id/{slug}).
     * Same published-only gate as EntityController. Each <url> carries the
     * /id/{slug} URI plus a <lastmod> from object.updated_at when present.
     */
    public function records(Request $request): Response
    {
        $page = $this->pageNo($request);
        $offset = ($page - 1) * self::MAX_URLS_PER_SITEMAP;

        try {
            $rows = $this->recordsSlice($offset, self::MAX_URLS_PER_SITEMAP);
        } catch (\Throwable $e) {
            $rows = collect();
        }

        $out = $this->urlsetOpen();
        foreach ($rows as $row) {
            if (empty($row->slug)) {
                continue;
            }
            $loc = $this->absolute('/id/'.ltrim((string) $row->slug, '/'));
            $out .= $this->urlNode($loc, $row->updated_at ?? null);
        }
        $out .= $this->urlsetClose();

        return $this->xmlResponse($out);
    }

    // -----------------------------------------------------------------
    // GET /sitemap-data-actors.xml  - /id/actor/{slug} actor URIs
    // -----------------------------------------------------------------

    /**
     * A <urlset> over one page of actor entity URIs (/id/actor/{slug}). Actors
     * are reference / authority entities (no publication gate of their own,
     * mirroring ActorEntityController); the synthetic root and the repository
     * subtype are excluded so this lists people / corporate bodies / families.
     */
    public function actors(Request $request): Response
    {
        $page = $this->pageNo($request);
        $offset = ($page - 1) * self::MAX_URLS_PER_SITEMAP;

        try {
            $rows = $this->actorsSlice($offset, self::MAX_URLS_PER_SITEMAP);
        } catch (\Throwable $e) {
            $rows = collect();
        }

        $out = $this->urlsetOpen();
        foreach ($rows as $row) {
            if (empty($row->slug)) {
                continue;
            }
            $loc = $this->absolute('/id/actor/'.ltrim((string) $row->slug, '/'));
            $out .= $this->urlNode($loc, null);
        }
        $out .= $this->urlsetClose();

        return $this->xmlResponse($out);
    }

    // -----------------------------------------------------------------
    // GET /sitemap-data-terms.xml  - /id/term/{slug} term URIs
    // -----------------------------------------------------------------

    /**
     * A <urlset> over one page of term entity URIs (/id/term/{slug}). Terms are
     * reference entities (no publication gate, mirroring TermEntityController);
     * the synthetic root is excluded.
     */
    public function terms(Request $request): Response
    {
        $page = $this->pageNo($request);
        $offset = ($page - 1) * self::MAX_URLS_PER_SITEMAP;

        try {
            $rows = $this->termsSlice($offset, self::MAX_URLS_PER_SITEMAP);
        } catch (\Throwable $e) {
            $rows = collect();
        }

        $out = $this->urlsetOpen();
        foreach ($rows as $row) {
            if (empty($row->slug)) {
                continue;
            }
            $loc = $this->absolute('/id/term/'.ltrim((string) $row->slug, '/'));
            $out .= $this->urlNode($loc, null);
        }
        $out .= $this->urlsetClose();

        return $this->xmlResponse($out);
    }

    // -----------------------------------------------------------------
    // Counts (for the index page split) - read-only, guarded
    // -----------------------------------------------------------------

    /**
     * Total enumerable entities of a type, for the index page split. A fault or
     * a schema variance yields 0 so the index still advertises a valid (empty)
     * page-1 sitemap rather than 500ing.
     */
    protected function countFor(string $type): int
    {
        return match ($type) {
            'records' => $this->recordsCount(),
            'actors' => $this->actorsCount(),
            'terms' => $this->termsCount(),
            default => 0,
        };
    }

    protected function recordsCount(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('slug') || ! Schema::hasTable('status')) {
            return 0;
        }

        return (int) $this->recordsQuery()->count('io.id');
    }

    protected function actorsCount(): int
    {
        if (! Schema::hasTable('actor') || ! Schema::hasTable('slug')) {
            return 0;
        }

        return (int) $this->actorsQuery()->count('a.id');
    }

    protected function termsCount(): int
    {
        if (! Schema::hasTable('term') || ! Schema::hasTable('slug')) {
            return 0;
        }

        return (int) $this->termsQuery()->count('t.id');
    }

    // -----------------------------------------------------------------
    // Bounded slices (offset + limit over a deterministic id order)
    // -----------------------------------------------------------------

    /**
     * One ordered page of published, slug-bearing records.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function recordsSlice(int $offset, int $limit): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('slug') || ! Schema::hasTable('status')) {
            return collect();
        }

        return $this->recordsQuery()
            ->leftJoin('object as o', 'io.id', '=', 'o.id')
            ->orderBy('io.id')
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->select('io.id', 's.slug', 'o.updated_at')
            ->get();
    }

    /**
     * One ordered page of actor slugs.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function actorsSlice(int $offset, int $limit): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('actor') || ! Schema::hasTable('slug')) {
            return collect();
        }

        return $this->actorsQuery()
            ->orderBy('a.id')
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->select('a.id', 's.slug')
            ->get();
    }

    /**
     * One ordered page of term slugs.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function termsSlice(int $offset, int $limit): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('term') || ! Schema::hasTable('slug')) {
            return collect();
        }

        return $this->termsQuery()
            ->orderBy('t.id')
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->select('t.id', 's.slug')
            ->get();
    }

    // -----------------------------------------------------------------
    // Shared base queries (the published-only / reference gates)
    // -----------------------------------------------------------------

    /**
     * Published, root-excluded, slug-bearing records. The EXACT gate of
     * EntityController / PublicSitemapController (status.type_id=158,
     * status_id=160; root id=1 excluded).
     */
    protected function recordsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', '=', self::STATUS_PUBLISHED);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', '!=', self::ROOT_ID);
    }

    /**
     * Slug-bearing actors, root excluded, the repository subtype excluded (it
     * has its own ISDIAH surface) - mirroring ActorEntityController::loadActor.
     */
    protected function actorsQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('actor as a')
            ->join('slug as s', 's.object_id', '=', 'a.id')
            ->where('a.id', '!=', self::ROOT_ID);

        if (Schema::hasTable('repository')) {
            $q->whereNotIn('a.id', function ($sub) {
                $sub->from('repository')->select('id');
            });
        }

        return $q;
    }

    /**
     * Slug-bearing terms, root excluded - mirroring TermEntityController.
     */
    protected function termsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('term as t')
            ->join('slug as s', 's.object_id', '=', 't.id')
            ->where('t.id', '!=', self::ROOT_ID);
    }

    // -----------------------------------------------------------------
    // XML builders
    // -----------------------------------------------------------------

    protected function urlsetOpen(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    }

    protected function urlsetClose(): string
    {
        return '</urlset>'."\n";
    }

    /**
     * One <url> node for an entity URI, with an optional <lastmod>.
     */
    protected function urlNode(string $loc, $updatedAt): string
    {
        $out = '  <url>'."\n";
        $out .= '    <loc>'.$this->xmlEscape($loc).'</loc>'."\n";
        if (! empty($updatedAt)) {
            $out .= '    <lastmod>'.$this->xmlEscape($this->w3cDate((string) $updatedAt)).'</lastmod>'."\n";
        }
        $out .= '    <changefreq>monthly</changefreq>'."\n";
        $out .= '  </url>'."\n";

        return $out;
    }

    // -----------------------------------------------------------------
    // Request + URL + escaping helpers
    // -----------------------------------------------------------------

    /**
     * The requested ?page= (1-based). Anything below 1 collapses to page 1, so a
     * missing / bogus value always yields the first page.
     */
    protected function pageNo(Request $request): int
    {
        $page = (int) $request->query('page', '1');

        return $page < 1 ? 1 : $page;
    }

    /**
     * An absolute URL on this host. Uses Laravel's url() so the scheme/host come
     * from the request / APP_URL - never a hardcoded host.
     */
    protected function absolute(string $path): string
    {
        return (string) url($path);
    }

    /**
     * XML-escape text content for the sitemap (handles & < > " ').
     */
    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Normalise a DB timestamp to a W3C-datetime <lastmod>. Falls back to the
     * raw string when it cannot be parsed (never throws).
     */
    protected function w3cDate(string $value): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return $value;
        }
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    /**
     * Wrap an XML body in a well-formed application/xml response with open CORS.
     */
    protected function xmlResponse(string $xml): Response
    {
        return $this->withCors(
            response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8'])
        );
    }

    /**
     * Apply permissive open-data CORS so any crawler/origin may fetch the
     * sitemaps.
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }
}
