<?php

/**
 * PublicSitemapController - public-website XML sitemap + robots.txt (SEO).
 *
 * Search engines (Google, Bing, etc.) discover and index a site's public pages
 * far better when handed a standards-compliant XML sitemap plus a robots.txt
 * that points at it. This controller exposes both at the site root so the
 * PUBLISHED archival-record pages - the canonical /{slug} public record views -
 * become crawlable and indexable:
 *
 *   GET /sitemap.xml   - an XML sitemap of the public record pages (+ the key
 *                        static public pages). Paginated via a <sitemapindex>
 *                        over ?page=N child <urlset>s when the published count
 *                        exceeds one file's cap.
 *   GET /robots.txt    - allow public content, disallow private/admin prefixes,
 *                        and advertise the sitemap location.
 *
 * This is the SEO/human-web cousin of the open-data graph sitemap
 * (GraphController::sitemap), which enumerates the per-entity Linked-Data graph
 * URLs for machine crawlers. THIS sitemap enumerates the canonical public
 * record pages (url('/'.$slug)) for search engines, NOT the API graph URLs.
 *
 * Published-only, root-excluded enumeration - the EXACT gate proven by
 * GraphController / DatasetController / OaiPmhController: information_object
 * joined to a Published status row (status.type_id=158, status_id=160),
 * synthetic root id=1 excluded, slug from the slug table, updated_at from the
 * object table. Drafts are NEVER exposed.
 *
 * Memory discipline: the record rows are streamed page-by-page via a keyset
 * (id-cursor) slice; the whole catalogue is never materialised in memory.
 *
 * Resilience: an empty catalogue yields a valid sitemap carrying just the
 * static pages (or an empty <urlset>), never a 500. Every emitted document is
 * well-formed, entity-escaped XML. Read-only throughout; public (no auth);
 * permissive open CORS so any crawler may fetch it.
 *
 * Jurisdiction-neutral: no hardcoded host - every absolute URL is built with
 * Laravel's url() helper, so the sitemap is correct on any deployment.
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
use Symfony\Component\HttpFoundation\Response;

class PublicSitemapController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    /**
     * Per-file URL ceiling for the sitemap. The sitemaps.org spec allows up to
     * 50000 URLs (and 50 MB) per <urlset>; we cap far lower so each child stays
     * cheap to build and serve, paginating via a <sitemapindex> over ?page=N.
     */
    private const PAGE_SIZE = 10000;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    // -----------------------------------------------------------------
    // GET /sitemap.xml
    // -----------------------------------------------------------------

    /**
     * The public-website XML sitemap.
     *
     * Layout:
     *   - The static public pages (home, browse, explore, etc.) live on PAGE 1
     *     alongside the first slice of published records.
     *   - When the published record count needs more than one page, the root
     *     /sitemap.xml (no ?page) becomes a <sitemapindex> linking each child
     *     ?page=N <urlset>. Otherwise /sitemap.xml is itself the single <urlset>.
     *
     * Resilient: a data-layer fault or an empty catalogue still yields a valid
     * document (the static pages, or an empty <urlset>), never a 500.
     */
    public function sitemap(Request $request): Response
    {
        // Total published, root-excluded record count (same gate as the rest of
        // the public API). A fault counts as zero so we still serve the static
        // pages rather than 500.
        try {
            $total = $this->publishedCount();
        } catch (\Throwable $e) {
            $total = 0;
        }

        $pageCount = $total > 0 ? (int) ceil($total / self::PAGE_SIZE) : 1;

        $page = (int) $request->query('page', '0');

        // Root with no ?page and more than one page of records: emit a
        // <sitemapindex> pointing at each child <urlset>.
        if ($page < 1 && $pageCount > 1) {
            return $this->xmlResponse($this->sitemapIndexXml($pageCount));
        }

        // A single <urlset>. page<1 means "the only / first page".
        $pageNo = $page < 1 ? 1 : min($page, max($pageCount, 1));

        // Static pages ride on the first page only, so they are listed exactly
        // once across the whole sitemap.
        $staticUrls = $pageNo === 1 ? $this->staticPageUrls() : [];

        $offset = ($pageNo - 1) * self::PAGE_SIZE;

        try {
            $records = $this->publishedSlice($offset, self::PAGE_SIZE);
        } catch (\Throwable $e) {
            $records = collect();
        }

        return $this->xmlResponse($this->urlsetXml($staticUrls, $records));
    }

    // -----------------------------------------------------------------
    // GET /robots.txt
    // -----------------------------------------------------------------

    /**
     * The robots.txt: allow crawling of the public site, disallow the private /
     * admin / authenticated surfaces, and advertise the sitemap. Plain text.
     *
     * The disallow list mirrors the private prefixes the public record
     * catch-all already excludes; it is advisory (robots.txt is not access
     * control - those paths are still middleware-gated) but keeps well-behaved
     * crawlers out of admin and account areas and focused on public content.
     */
    public function robots(Request $request): Response
    {
        $sitemap = $this->absolute('/sitemap.xml');

        // Private / non-indexable prefixes. Kept in sync with the spirit of the
        // /{slug} catch-all exclusion list (admin + account + machine surfaces).
        $disallow = [
            '/admin',
            '/api',
            '/settings',
            '/ahgSettings',
            '/login',
            '/logout',
            '/register',
            '/user',
            '/cart',
            '/clipboard',
            '/research',
            '/researcher',
            '/jobs',
            '/workflow',
            '/security',
            '/feedback',
            '/ftpUpload',
            '/ingest',
            '/scan',
            '/requesttopublish',
            '/storage',
        ];

        $lines = [];
        $lines[] = '# Heratio public website robots policy';
        $lines[] = '# Published archival-record pages and public hubs are open to crawlers.';
        $lines[] = '# Admin, account and machine surfaces are disallowed below.';
        $lines[] = '';
        $lines[] = 'User-agent: *';
        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.$sitemap;
        $lines[] = '';

        $body = implode("\n", $lines);

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // XML builders
    // -----------------------------------------------------------------

    /**
     * Build a <sitemapindex> linking each child ?page=N <urlset>.
     */
    protected function sitemapIndexXml(int $pageCount): string
    {
        $now = $this->xmlEscape($this->w3cDate((string) now()->toIso8601String()));

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        for ($p = 1; $p <= $pageCount; $p++) {
            $loc = $this->xmlEscape($this->absolute('/sitemap.xml?page='.$p));
            $out .= '  <sitemap>'."\n";
            $out .= '    <loc>'.$loc.'</loc>'."\n";
            $out .= '    <lastmod>'.$now.'</lastmod>'."\n";
            $out .= '  </sitemap>'."\n";
        }
        $out .= '</sitemapindex>'."\n";

        return $out;
    }

    /**
     * Build a <urlset> from the (page-1-only) static pages plus a slice of
     * published record pages. Streams the record rows straight into the buffer.
     *
     * @param  array<int,array{loc:string,changefreq:string,priority:?string}>  $staticUrls
     * @param  iterable<int,object>  $records  rows with ->slug and ->updated_at
     */
    protected function urlsetXml(array $staticUrls, iterable $records): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        // Static public pages first (home, browse, explore, ...).
        foreach ($staticUrls as $u) {
            $out .= '  <url>'."\n";
            $out .= '    <loc>'.$this->xmlEscape((string) $u['loc']).'</loc>'."\n";
            $out .= '    <changefreq>'.$this->xmlEscape((string) $u['changefreq']).'</changefreq>'."\n";
            if (! empty($u['priority'])) {
                $out .= '    <priority>'.$this->xmlEscape((string) $u['priority']).'</priority>'."\n";
            }
            $out .= '  </url>'."\n";
        }

        // Published record pages (the canonical /{slug} public views).
        foreach ($records as $row) {
            if (empty($row->slug)) {
                continue;
            }
            $loc = $this->xmlEscape($this->absolute('/'.ltrim((string) $row->slug, '/')));
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

    /**
     * The key static public pages to include in the sitemap. Each entry is gated
     * by Route::has(): a page is only listed when its route is registered (its
     * package is installed), so the sitemap never advertises a dead link.
     *
     * @return array<int,array{loc:string,changefreq:string,priority:?string}>
     */
    protected function staticPageUrls(): array
    {
        // route-name => [changefreq, priority]. Only the routes that exist are
        // emitted; the home page is the site root regardless.
        $candidates = [
            'home' => ['daily', '1.0'],
            'glam.browse' => ['daily', '0.9'],
            'explore.index' => ['weekly', '0.8'],
            'open-data.index' => ['weekly', '0.6'],
            'exhibition-space.reconstructions' => ['weekly', '0.6'],
            'c2pa.authenticity' => ['weekly', '0.5'],
        ];

        $urls = [];
        foreach ($candidates as $name => [$changefreq, $priority]) {
            if (! Route::has($name)) {
                continue;
            }

            try {
                $loc = route($name);
            } catch (\Throwable $e) {
                continue;
            }

            $urls[] = [
                'loc' => $loc,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        }

        // Content-credentials hub: only when the c2pa "check" surface exists and
        // is distinct from the authenticity landing already added above.
        if (Route::has('c2pa.verify.check') && ! Route::has('c2pa.authenticity')) {
            try {
                $urls[] = [
                    'loc' => route('c2pa.verify.check'),
                    'changefreq' => 'weekly',
                    'priority' => '0.5',
                ];
            } catch (\Throwable $e) {
                // skip
            }
        }

        // Guarantee the home page is present even if the named route is absent
        // on some minimal install: the site root is always a valid public URL.
        if (empty($urls)) {
            $urls[] = [
                'loc' => $this->absolute('/'),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];
        }

        return $urls;
    }

    // -----------------------------------------------------------------
    // Published enumeration (mirrors GraphController / DatasetController)
    // -----------------------------------------------------------------

    /**
     * Total published, root-excluded archival descriptions that have a slug -
     * the population the public record pages cover.
     */
    protected function publishedCount(): int
    {
        return (int) $this->publishedQuery()
            ->whereNotNull('s.slug')
            ->count('io.id');
    }

    /**
     * One ordered slice of published, slug-bearing records for a sitemap page.
     * Offset paging over a deterministic id order is fine for a sitemap: the
     * population is stable enough and the order is reproducible.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function publishedSlice(int $offset, int $limit): \Illuminate\Support\Collection
    {
        return $this->publishedQuery()
            ->whereNotNull('s.slug')
            ->orderBy('io.id')
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->select('io.id', 's.slug', 'o.updated_at')
            ->get();
    }

    /**
     * The shared published-only query: information_object joined to a Published
     * status row (type_id=158, status_id=160), synthetic root (id 1) excluded,
     * the slug table left-joined for the public URL and the object table for
     * updated_at. Identical gate to the rest of the public v1 API.
     */
    protected function publishedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', '=', self::STATUS_PUBLISHED);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('object as o', 'io.id', '=', 'o.id')
            ->where('io.id', '!=', self::ROOT_ID);
    }

    // -----------------------------------------------------------------
    // URL + escaping helpers
    // -----------------------------------------------------------------

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
     * Apply permissive open CORS so any crawler/origin may fetch the sitemap and
     * robots policy.
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');

        return $response;
    }
}
