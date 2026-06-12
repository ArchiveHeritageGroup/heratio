<?php

/**
 * RecentlyAddedController - Heratio ahg-core
 *
 * Public "Recently added" surface: the newest PUBLISHED archival descriptions,
 * most-recent first, so visitors and returning researchers can see what is new.
 * Three faces of the same bounded, read-only list:
 *
 *   - GET /recent       a Bootstrap-5 / central-theme card grid (this is the
 *                       single-segment public page).
 *   - GET /recent.json  the same list as machine data: each item is
 *                       {id, slug, title, created_at, url}. CORS-open so a
 *                       harvester or a "what's new" widget on any origin can
 *                       consume it.
 *   - GET /recent.atom  a valid Atom 1.0 feed of recent additions, for feed
 *                       readers / aggregators. All dynamic values are XML-escaped.
 *
 * Every figure comes from the read-only RecentlyAddedService (already null-safe
 * and bounded). This surface is PUBLIC (no auth), READ-ONLY, makes no AI calls
 * and no DB writes. Hosts/URLs are built from url()/route(), never hardcoded. A
 * zero-result / missing-table state degrades to a calm empty page (or an empty
 * but valid feed/JSON), never a 500.
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

namespace AhgCore\Controllers;

use AhgCore\Services\RecentlyAddedService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class RecentlyAddedController extends Controller
{
    /** Neutral fallback feed title when no siteTitle setting is configured. */
    private const DEFAULT_SITE_NAME = 'Heratio';

    /** How many items the Atom feed carries (its own bounded page). */
    private const FEED_LIMIT = 50;

    public function __construct(private RecentlyAddedService $service)
    {
    }

    /**
     * GET /recent - the public HTML card grid.
     *
     * Reads ?page= (1-based) for simple offset paging; never 500s (the service
     * already degrades to an empty list, and the view renders a calm empty-state).
     */
    public function index(Request $request)
    {
        $page = $this->pageParam($request);

        $data = $this->service->recent($page, RecentlyAddedService::DEFAULT_PER_PAGE);

        return view('ahg-core::recently-added.index', [
            'items'            => $data['items'] ?? [],
            'page'             => (int) ($data['page'] ?? 1),
            'perPage'          => (int) ($data['per_page'] ?? RecentlyAddedService::DEFAULT_PER_PAGE),
            'hasMore'          => (bool) ($data['has_more'] ?? false),
            'orderedByCreated' => (bool) ($data['ordered_by_created'] ?? true),
            'generatedAt'      => $data['generated_at'] ?? null,
            'hasError'         => (bool) ($data['error'] ?? false),
            'hasJson'          => true,
            'hasFeed'          => true,
        ]);
    }

    /**
     * GET /recent.json - the same list as machine data, CORS-open.
     *
     * Each item is {id, slug, title, created_at, url}. The envelope carries the
     * paging hints so a consumer can walk pages. Never 500s.
     */
    public function json(Request $request)
    {
        $page = $this->pageParam($request);

        $data = $this->service->recent($page, RecentlyAddedService::DEFAULT_PER_PAGE);

        $items = [];
        foreach (($data['items'] ?? []) as $row) {
            $items[] = [
                'id'         => (int) ($row['id'] ?? 0),
                'slug'       => $row['slug'] ?? null,
                'title'      => (string) ($row['title'] ?? ''),
                'created_at' => $row['created_at'] ?? null,
                'url'        => $row['url'] ?? null,
            ];
        }

        $payload = [
            'generated_at'       => $data['generated_at'] ?? null,
            'ordered_by_created' => (bool) ($data['ordered_by_created'] ?? true),
            'page'               => (int) ($data['page'] ?? 1),
            'per_page'           => (int) ($data['per_page'] ?? RecentlyAddedService::DEFAULT_PER_PAGE),
            'has_more'           => (bool) ($data['has_more'] ?? false),
            'count'              => count($items),
            'items'              => $items,
        ];

        return response()->json($payload, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=300',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /recent.atom - a valid Atom 1.0 feed of recent additions.
     *
     * Builds the feed inside a guard and falls back to a minimal-but-valid empty
     * feed on any failure, so it never 500s. Every dynamic value is XML-escaped.
     */
    public function atom(Request $request)
    {
        try {
            $data = $this->service->recent(1, self::FEED_LIMIT);
            $xml = $this->buildFeed($data);
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] recent.atom build failed: '.$e->getMessage());
            $xml = $this->minimalFeed();
        }

        return response($xml, 200, [
            'Content-Type'  => 'application/atom+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    // ---------------------------------------------------------------------
    // Atom feed building
    // ---------------------------------------------------------------------

    /** Build the full Atom 1.0 feed from a service result. */
    private function buildFeed(array $data): string
    {
        $siteName = $this->siteName();
        $feedTitle = $siteName.' - '.__('Recently added');
        $self  = url('/recent.atom');
        $alt   = url('/recent');
        $items = $data['items'] ?? [];

        // The feed's updated time = the newest item's created_at, else now().
        $updated = $this->atomDate($items[0]['created_at'] ?? null) ?? $this->atomDate(now()->toDateTimeString());

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<feed xmlns="http://www.w3.org/2005/Atom">';
        $lines[] = '  <title>'.$this->x($feedTitle).'</title>';
        $lines[] = '  <subtitle>'.$this->x(__('The newest published records in this collection.')).'</subtitle>';
        $lines[] = '  <link rel="self" type="application/atom+xml" href="'.$this->x($self).'"/>';
        $lines[] = '  <link rel="alternate" type="text/html" href="'.$this->x($alt).'"/>';
        $lines[] = '  <id>'.$this->x($self).'</id>';
        $lines[] = '  <updated>'.$this->x($updated).'</updated>';
        $lines[] = '  <generator>Heratio</generator>';

        foreach ($items as $item) {
            $url = (string) ($item['url'] ?? '');
            if ($url === '') {
                // No slug -> no stable, resolvable link; skip rather than emit a dead entry.
                continue;
            }
            $title = (string) ($item['title'] ?? __('Untitled record'));
            $entryUpdated = $this->atomDate($item['created_at'] ?? null) ?? $updated;
            $snippet = (string) ($item['snippet'] ?? '');

            $lines[] = '  <entry>';
            $lines[] = '    <title>'.$this->x($title).'</title>';
            $lines[] = '    <link rel="alternate" type="text/html" href="'.$this->x($url).'"/>';
            $lines[] = '    <id>'.$this->x($url).'</id>';
            $lines[] = '    <updated>'.$this->x($entryUpdated).'</updated>';
            if ($snippet !== '') {
                $lines[] = '    <summary>'.$this->x($snippet).'</summary>';
            }
            $lines[] = '  </entry>';
        }

        $lines[] = '</feed>';

        return implode("\n", $lines)."\n";
    }

    /** The minimal-but-valid empty Atom feed (never throws). */
    private function minimalFeed(): string
    {
        $self = url('/recent.atom');
        $alt  = url('/recent');
        $updated = $this->atomDate(now()->toDateTimeString());

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<feed xmlns="http://www.w3.org/2005/Atom">';
        $lines[] = '  <title>'.$this->x(self::DEFAULT_SITE_NAME.' - '.__('Recently added')).'</title>';
        $lines[] = '  <link rel="self" type="application/atom+xml" href="'.$this->x($self).'"/>';
        $lines[] = '  <link rel="alternate" type="text/html" href="'.$this->x($alt).'"/>';
        $lines[] = '  <id>'.$this->x($self).'</id>';
        $lines[] = '  <updated>'.$this->x($updated).'</updated>';
        $lines[] = '</feed>';

        return implode("\n", $lines)."\n";
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** Read and clamp the ?page= query parameter to a sane 1-based integer. */
    private function pageParam(Request $request): int
    {
        $page = (int) $request->query('page', 1);

        return $page > 0 ? $page : 1;
    }

    /**
     * The institution / site name, read from the existing setting/setting_i18n
     * `siteTitle` value (the same value the theme header renders). Falls back to
     * the neutral default on any failure or when unset.
     */
    private function siteName(): string
    {
        try {
            $setting = \Illuminate\Support\Facades\DB::table('setting')->where('name', 'siteTitle')->first();
            if ($setting) {
                $i18n = \Illuminate\Support\Facades\DB::table('setting_i18n')
                    ->where('id', $setting->id)
                    ->where('culture', 'en')
                    ->first();
                $value = trim((string) ($i18n->value ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        } catch (Throwable $e) {
            // fall through to default
        }

        return self::DEFAULT_SITE_NAME;
    }

    /**
     * Convert a "YYYY-MM-DD HH:MM:SS" DB timestamp to an RFC 3339 string for Atom
     * (e.g. 2026-06-10T15:07:55+00:00). Returns null on unparseable / empty input.
     */
    private function atomDate(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->toAtomString();
        } catch (Throwable $e) {
            return null;
        }
    }

    /** XML-escape an element value / attribute value for the Atom feed. */
    private function x(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
