<?php

/**
 * FeedController - public content-syndication feeds of recently updated records.
 *
 * Extends the open-data line of north-star #1204 and complements the public
 * sitemap (PublicSitemapController) and the bulk dataset export
 * (DatasetController). Where the sitemap hands a crawler the WHOLE published
 * catalogue for indexing, this controller offers a small, time-ordered
 * SYNDICATION surface - "what changed recently" - the feed a reader, an
 * aggregator, or a change-watching agent subscribes to:
 *
 *   GET /feed.atom  - Atom 1.0 feed of the most recently UPDATED published
 *                     records (ORDER BY object.updated_at DESC).
 *   GET /feed.rss   - the same data as an RSS 2.0 channel.
 *
 * Both reuse the EXACT published-enumeration pattern proven by
 * PublicSitemapController / GraphController / DatasetController / OaiPmhController:
 * information_object joined to a Published status row (status.type_id=158,
 * status_id=160), synthetic root id=1 excluded, slug from the slug table,
 * updated_at from the object table, i18n title/scope at culture 'en'. Only
 * published descriptions are ever disclosed - drafts are never leaked.
 *
 * Bounding: a feed is a recency window, not the catalogue. The query is bounded
 * to DEFAULT_LIMIT entries (the newest by updated_at), raisable via ?limit= up
 * to MAX_LIMIT. The whole catalogue is never materialised.
 *
 * Resilience: an empty catalogue yields a VALID empty feed (the feed element
 * with no entries / channel with no items), never a 500. A data-layer fault is
 * caught and degrades to the same empty feed. Every emitted document is
 * well-formed, entity-escaped XML.
 *
 * Jurisdiction-neutral: no hardcoded host - every absolute URL is built with
 * Laravel's url() helper, so the feed is correct on any deployment.
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

class FeedController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    /** Default number of entries in a feed (the newest by updated_at). */
    private const DEFAULT_LIMIT = 50;

    /** Hard ceiling on ?limit= so the feed stays a bounded recency window. */
    private const MAX_LIMIT = 200;

    /** Scope/abstract truncation length (characters) for the entry summary. */
    private const SUMMARY_MAX = 500;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    /**
     * OPTIONS preflight for the feed endpoints.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    // -----------------------------------------------------------------
    // GET /feed.atom
    // -----------------------------------------------------------------

    /**
     * Atom 1.0 feed of the most recently updated published records.
     *
     * Feed-level: <title> (app.name), <id> (the site URL), <updated> (the
     * newest record's updated_at, or "now" when empty), a <link rel="self"> to
     * the feed and a <link> to the site. Each <entry>: <title>, <id> (the
     * record's public URL), <link> to url('/'.$slug), <updated> (RFC3339),
     * <summary> (scope/abstract, stripped + truncated), <author> (repository or
     * creator name when cheap).
     *
     * Resilient: an empty catalogue yields a valid <feed> with no <entry>.
     */
    public function atom(Request $request): Response
    {
        $records = $this->recentRecords($this->resolveLimit($request));

        $self = $this->absolute('/feed.atom');
        $siteUrl = $this->absolute('/');
        $title = (string) config('app.name', 'Heratio');

        // Feed <updated> = newest entry's updated_at, else "now" for a valid
        // empty feed.
        $feedUpdated = $this->rfc3339(
            $records->isNotEmpty()
                ? (string) ($records->first()->updated_at ?? '')
                : ''
        );

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<feed xmlns="http://www.w3.org/2005/Atom">'."\n";
        $out .= '  <title>'.$this->esc($title).'</title>'."\n";
        $out .= '  <subtitle>'.$this->esc('Recently updated published records').'</subtitle>'."\n";
        $out .= '  <id>'.$this->esc($siteUrl).'</id>'."\n";
        $out .= '  <updated>'.$this->esc($feedUpdated).'</updated>'."\n";
        $out .= '  <link rel="self" type="application/atom+xml" href="'.$this->esc($self).'"/>'."\n";
        $out .= '  <link rel="alternate" type="text/html" href="'.$this->esc($siteUrl).'"/>'."\n";
        $out .= '  <generator uri="'.$this->esc($siteUrl).'">'.$this->esc($title).'</generator>'."\n";

        foreach ($records as $row) {
            if (empty($row->slug)) {
                continue;
            }
            $url = $this->absolute('/'.ltrim((string) $row->slug, '/'));
            $entryTitle = (string) ($row->title ?? '[Untitled]');
            $updated = $this->rfc3339((string) ($row->updated_at ?? ''));
            $summary = $this->plainText((string) ($row->scope_and_content ?? ''));
            $author = $this->authorFor($row);

            $out .= '  <entry>'."\n";
            $out .= '    <title>'.$this->esc($entryTitle).'</title>'."\n";
            $out .= '    <id>'.$this->esc($url).'</id>'."\n";
            $out .= '    <link rel="alternate" type="text/html" href="'.$this->esc($url).'"/>'."\n";
            $out .= '    <updated>'.$this->esc($updated).'</updated>'."\n";
            if ($summary !== '') {
                $out .= '    <summary type="text">'.$this->esc($summary).'</summary>'."\n";
            }
            if ($author !== null && $author !== '') {
                $out .= '    <author><name>'.$this->esc($author).'</name></author>'."\n";
            }
            $out .= '  </entry>'."\n";
        }

        $out .= '</feed>'."\n";

        return $this->xmlResponse($out, 'application/atom+xml; charset=utf-8');
    }

    // -----------------------------------------------------------------
    // GET /feed.rss
    // -----------------------------------------------------------------

    /**
     * RSS 2.0 channel of the same recently updated published records. Provided
     * for the readers/aggregators that prefer RSS over Atom; identical data and
     * the same published-only gate + bounding.
     *
     * Resilient: an empty catalogue yields a valid <channel> with no <item>.
     */
    public function rss(Request $request): Response
    {
        $records = $this->recentRecords($this->resolveLimit($request));

        $self = $this->absolute('/feed.rss');
        $siteUrl = $this->absolute('/');
        $title = (string) config('app.name', 'Heratio');

        $lastBuild = $this->rfc2822(
            $records->isNotEmpty()
                ? (string) ($records->first()->updated_at ?? '')
                : ''
        );

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n";
        $out .= '  <channel>'."\n";
        $out .= '    <title>'.$this->esc($title).'</title>'."\n";
        $out .= '    <link>'.$this->esc($siteUrl).'</link>'."\n";
        $out .= '    <description>'.$this->esc('Recently updated published records').'</description>'."\n";
        $out .= '    <lastBuildDate>'.$this->esc($lastBuild).'</lastBuildDate>'."\n";
        $out .= '    <atom:link href="'.$this->esc($self).'" rel="self" type="application/rss+xml"/>'."\n";

        foreach ($records as $row) {
            if (empty($row->slug)) {
                continue;
            }
            $url = $this->absolute('/'.ltrim((string) $row->slug, '/'));
            $itemTitle = (string) ($row->title ?? '[Untitled]');
            $pubDate = $this->rfc2822((string) ($row->updated_at ?? ''));
            $summary = $this->plainText((string) ($row->scope_and_content ?? ''));

            $out .= '    <item>'."\n";
            $out .= '      <title>'.$this->esc($itemTitle).'</title>'."\n";
            $out .= '      <link>'.$this->esc($url).'</link>'."\n";
            $out .= '      <guid isPermaLink="true">'.$this->esc($url).'</guid>'."\n";
            if ($pubDate !== '') {
                $out .= '      <pubDate>'.$this->esc($pubDate).'</pubDate>'."\n";
            }
            if ($summary !== '') {
                $out .= '      <description>'.$this->esc($summary).'</description>'."\n";
            }
            $out .= '    </item>'."\n";
        }

        $out .= '  </channel>'."\n";
        $out .= '</rss>'."\n";

        return $this->xmlResponse($out, 'application/rss+xml; charset=utf-8');
    }

    // -----------------------------------------------------------------
    // Published enumeration (mirrors PublicSitemapController / DatasetController)
    // -----------------------------------------------------------------

    /**
     * Resolve the ?limit= query param, defaulting to DEFAULT_LIMIT and clamped
     * to [1, MAX_LIMIT] so the feed is always a bounded recency window.
     */
    protected function resolveLimit(Request $request): int
    {
        $limit = (int) $request->query('limit', (string) self::DEFAULT_LIMIT);
        if ($limit < 1) {
            $limit = self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * The newest published, slug-bearing records by object.updated_at DESC,
     * bounded to $limit. Best-effort: a data-layer fault yields an empty
     * collection so the caller still emits a valid empty feed (never 500).
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function recentRecords(int $limit): \Illuminate\Support\Collection
    {
        try {
            return $this->publishedQuery()
                ->whereNotNull('s.slug')
                ->orderByDesc('o.updated_at')
                ->orderByDesc('io.id')
                ->limit(max(1, $limit))
                ->select(
                    'io.id',
                    'io.repository_id',
                    's.slug',
                    'o.updated_at',
                    'i18n.title',
                    'i18n.scope_and_content'
                )
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * The shared published-only query: information_object joined to a Published
     * status row (type_id=158, status_id=160), synthetic root (id 1) excluded,
     * i18n at culture 'en', slug left-joined for the public URL and the object
     * table for updated_at. Identical gate to the rest of the public v1 API.
     */
    protected function publishedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', '=', self::STATUS_PUBLISHED);
            })
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('object as o', 'io.id', '=', 'o.id')
            ->where('io.id', '!=', self::ROOT_ID);
    }

    /**
     * A cheap author label for an entry: the holding repository's authorised
     * name, else the record's creator (event of type "creation"). Best-effort -
     * null on any schema variance so the <author> element is simply omitted.
     */
    protected function authorFor(object $row): ?string
    {
        // Repository (the publisher) is the cheapest, most reliable label.
        if (! empty($row->repository_id)) {
            try {
                $name = DB::table('repository as r')
                    ->join('actor_i18n as ai', function ($j) {
                        $j->on('r.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                    })
                    ->where('r.id', (int) $row->repository_id)
                    ->value('ai.authorized_form_of_name');
                if (! empty($name)) {
                    return (string) $name;
                }
            } catch (\Throwable $e) {
                // fall through to creator
            }
        }

        // Creator actor via an event row on this object. Single cheap lookup.
        try {
            $creator = DB::table('event as e')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('e.actor_id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('e.object_id', (int) $row->id)
                ->whereNotNull('e.actor_id')
                ->value('ai.authorized_form_of_name');

            return $creator ? (string) $creator : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Text / date / URL helpers
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
     * Strip HTML and collapse whitespace, then truncate to SUMMARY_MAX so a long
     * scope-and-content note stays a manageable feed summary.
     */
    protected function plainText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
        if (mb_strlen($text) > self::SUMMARY_MAX) {
            $text = mb_substr($text, 0, self::SUMMARY_MAX - 1).'...';
        }

        return $text;
    }

    /**
     * Normalise a DB timestamp to an RFC3339 value for Atom <updated>. Falls
     * back to "now" when the value is empty/unparseable so the feed stays valid.
     */
    protected function rfc3339(string $value): string
    {
        try {
            $value = trim($value);
            if ($value === '' || str_starts_with($value, '0000')) {
                return now()->toRfc3339String();
            }

            return \Illuminate\Support\Carbon::parse($value)->toRfc3339String();
        } catch (\Throwable $e) {
            return now()->toRfc3339String();
        }
    }

    /**
     * Normalise a DB timestamp to an RFC2822 value for RSS <pubDate>. Empty/
     * unparseable yields '' so the optional element is simply omitted.
     */
    protected function rfc2822(string $value): string
    {
        try {
            $value = trim($value);
            if ($value === '' || str_starts_with($value, '0000')) {
                return now()->toRfc2822String();
            }

            return \Illuminate\Support\Carbon::parse($value)->toRfc2822String();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * XML-escape text content (handles & < > " ').
     */
    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    /**
     * Wrap an XML body in a well-formed response with open CORS.
     */
    protected function xmlResponse(string $xml, string $contentType): Response
    {
        return $this->withCors(
            response($xml, 200, ['Content-Type' => $contentType])
        );
    }

    /**
     * Apply permissive open CORS so any reader/aggregator/origin may fetch the
     * feed.
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');

        return $response;
    }
}
