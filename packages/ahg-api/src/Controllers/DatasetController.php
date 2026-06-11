<?php

/**
 * DatasetController - bulk open-data dataset export of the published catalogue.
 *
 * Extends the open-data line of north-star #1204 ("the world heritage graph /
 * open memory protocol"). Where GraphController exposes the catalogue for
 * per-entity Linked-Data crawling and OaiPmhController exposes it for OAI-PMH
 * metadata harvesting, this controller lets a researcher download the WHOLE
 * published catalogue as a single dataset, in two researcher-friendly bulk
 * formats:
 *
 *   GET /api/v1/dataset.csv     - streamed CSV (one row per published record).
 *   GET /api/v1/dataset.jsonld  - bounded JSON-LD @graph, ?after= cursor paged.
 *
 * Both reuse the EXACT published-enumeration pattern proven by GraphController /
 * OaiPmhController: information_object joined to a Published status row
 * (status.type_id=158, status_id=160), synthetic root id=1 excluded, i18n via
 * information_object_i18n at culture 'en'. Only published descriptions are ever
 * disclosed - unpublished drafts are never leaked.
 *
 * Memory discipline:
 *   - The CSV is STREAMED from a server-side DB cursor (DB::table(...)->cursor())
 *     so the whole catalogue is never materialised in memory; rows are written
 *     to the output buffer as they arrive.
 *   - The JSON-LD is BOUNDED: a single request emits at most PAGE_SIZE nodes and
 *     advertises a `next` link with an opaque keyset ?after= cursor, so a client
 *     walks the catalogue page by page rather than pulling a giant array.
 *
 * Resilience: an empty catalogue yields a valid header-only CSV / an empty
 * @graph, never a 500. CSV fields are quoted/escaped via fputcsv; JSON-LD is
 * encoded with json_encode (correct escaping). Read-only throughout.
 *
 * Jurisdiction-neutral: namespaces come from GraphSerializerService (driven by
 * config('heratio.ld')); no hardcoded tenant strings. The public record URL is
 * the slug-based /{slug} page on this host.
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatasetController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    /** Hard cap on JSON-LD nodes emitted per page (and the default page size). */
    private const JSONLD_PAGE_SIZE = 1000;

    /** Scope/abstract truncation length (characters) for the CSV column. */
    private const ABSTRACT_MAX = 2000;

    protected string $culture = 'en';

    protected GraphSerializerService $serializer;

    public function __construct(GraphSerializerService $serializer)
    {
        $this->culture = app()->getLocale() ?: 'en';
        $this->serializer = $serializer;
    }

    /**
     * OPTIONS preflight for the open dataset endpoints.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    // -----------------------------------------------------------------
    // CSV export (streamed from a DB cursor)
    // -----------------------------------------------------------------

    /**
     * GET /api/v1/dataset.csv
     *
     * Streams every published record as CSV. The catalogue is never loaded into
     * memory: rows arrive one at a time from a server-side cursor and are
     * written straight to the output stream. Subjects (a per-record one-to-many)
     * are fetched per row but only the ids of the current row, so the footprint
     * stays flat. Resilient: an empty catalogue still yields a valid file with
     * just the header row.
     *
     * Columns: id, reference_code, title, level_of_description, dates,
     * scope_and_content, repository, subjects, url.
     */
    public function csv(Request $request): StreamedResponse
    {
        $filename = 'heratio-catalogue-'.now()->format('Ymd').'.csv';

        $response = new StreamedResponse(function () {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so spreadsheet apps detect the encoding.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'id',
                'reference_code',
                'title',
                'level_of_description',
                'dates',
                'scope_and_content',
                'repository',
                'subjects',
                'url',
            ]);

            // Lazy cursor: rows are fetched/yielded one at a time. Wrapped so a
            // mid-stream fault cannot surface as a broken HTTP response (headers
            // are already sent); we simply stop writing.
            try {
                foreach ($this->publishedCursor() as $row) {
                    fputcsv($out, [
                        (int) $row->id,
                        (string) ($row->identifier ?? ''),
                        (string) ($row->title ?? ''),
                        (string) ($this->termName($row->level_of_description_id) ?? ''),
                        $this->datesFor((int) $row->id),
                        $this->plainText((string) ($row->scope_and_content ?? '')),
                        (string) ($this->publisher($row->repository_id) ?? ''),
                        $this->subjectsCsv((int) $row->id),
                        (string) ($this->recordPublicUrl($row->slug ?? null) ?? ''),
                    ]);
                }
            } catch (\Throwable $e) {
                // Best-effort stream: stop cleanly on any data-layer fault.
            }

            fclose($out);
        }, 200);

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $this->withCors($response);
    }

    // -----------------------------------------------------------------
    // JSON-LD export (bounded @graph, keyset ?after= cursor)
    // -----------------------------------------------------------------

    /**
     * GET /api/v1/dataset.jsonld
     *
     * Returns one bounded page of the published catalogue as a JSON-LD @graph
     * (schema.org / dcterms @context reused from GraphSerializerService). The
     * page holds at most JSONLD_PAGE_SIZE nodes; a `next` link with an opaque
     * keyset ?after= cursor walks the rest. The full catalogue is never built in
     * memory. Resilient: an empty catalogue yields a valid empty @graph.
     */
    public function jsonld(Request $request): Response
    {
        $base = $this->endpointBase();

        // Keyset cursor: "after this object id". Opaque to the consumer.
        $after = (int) $request->query('after', '0');
        if ($after < 0) {
            $after = 0;
        }

        $size = self::JSONLD_PAGE_SIZE;

        try {
            // One extra row to detect a further page without a second query.
            $rows = $this->publishedQuery()
                ->where('io.id', '>', $after)
                ->orderBy('io.id')
                ->limit($size + 1)
                ->select(
                    'io.id',
                    'io.identifier',
                    'io.level_of_description_id',
                    'io.repository_id',
                    'i18n.title',
                    'i18n.scope_and_content',
                    's.slug'
                )
                ->get();
        } catch (\Throwable $e) {
            $rows = collect();
        }

        $hasMore = $rows->count() > $size;
        $page = $hasMore ? $rows->slice(0, $size) : $rows;

        $graph = [];
        $lastId = $after;
        foreach ($page as $row) {
            $lastId = (int) $row->id;
            $node = [
                '@id' => $this->graphUri((int) $row->id),
                '@type' => $this->schemaType($this->termName($row->level_of_description_id)),
                'name' => (string) ($row->title ?? '[Untitled]'),
            ];
            if (! empty($row->identifier)) {
                $node['identifier'] = (string) $row->identifier;
            }
            $abstract = $this->plainText((string) ($row->scope_and_content ?? ''));
            if ($abstract !== '') {
                $node['description'] = $abstract;
            }
            $dates = $this->datesFor((int) $row->id);
            if ($dates !== '') {
                $node['temporalCoverage'] = $dates;
            }
            $publisher = $this->publisher($row->repository_id);
            if ($publisher !== null) {
                $node['publisher'] = $publisher;
            }
            $subjects = $this->subjectsList((int) $row->id);
            if ($subjects) {
                $node['about'] = $subjects;
            }
            $url = $this->recordPublicUrl($row->slug ?? null);
            if ($url !== null) {
                $node['sameAs'] = $url;
            }
            $graph[] = $node;
        }

        $context = array_merge($this->serializer->context(), [
            'hydra' => 'http://www.w3.org/ns/hydra/core#',
            'next' => ['@id' => 'hydra:next', '@type' => '@id'],
            'pageSize' => 'hydra:limit',
            'publisher' => 'schema:publisher',
            'about' => 'schema:about',
            'temporalCoverage' => 'schema:temporalCoverage',
        ]);

        $doc = [
            '@context' => $context,
            '@id' => $base.'/api/v1/dataset.jsonld'.($after > 0 ? '?after='.$after : ''),
            '@type' => 'schema:Dataset',
            'name' => (string) config('app.name', 'Heratio').' published catalogue (open data)',
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'pageSize' => $size,
            'count' => count($graph),
            '@graph' => $graph,
        ];

        if ($hasMore) {
            $doc['next'] = $base.'/api/v1/dataset.jsonld?after='.$lastId;
            $doc['cursor'] = (string) $lastId;
        }

        $body = json_encode(
            $doc,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->withCors(
            response($body, 200, ['Content-Type' => 'application/ld+json; charset=utf-8'])
        );
    }

    // -----------------------------------------------------------------
    // Published enumeration (mirrors GraphController / OaiPmhController)
    // -----------------------------------------------------------------

    /**
     * The shared published-only query: information_object joined to a Published
     * status row (type_id=158, status_id=160), synthetic root (id 1) excluded,
     * i18n at culture 'en', slug left-joined for the public URL. Identical gate
     * to the rest of the public v1 API. Ordered by the caller.
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
            ->where('io.id', '!=', self::ROOT_ID);
    }

    /**
     * A lazy server-side cursor over the whole published catalogue for the CSV
     * stream. ->cursor() yields rows one at a time (no full result set in
     * memory). Ordered by id for a deterministic export.
     *
     * @return \Illuminate\Support\LazyCollection<int,object>
     */
    protected function publishedCursor(): \Illuminate\Support\LazyCollection
    {
        return $this->publishedQuery()
            ->orderBy('io.id')
            ->select(
                'io.id',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                'i18n.title',
                'i18n.scope_and_content',
                's.slug'
            )
            ->cursor();
    }

    // -----------------------------------------------------------------
    // Per-record enrichments (best-effort, guarded)
    // -----------------------------------------------------------------

    /**
     * Display dates for a record (event display date, else start/end span),
     * joined with "; ". Best-effort - a schema variance yields ''.
     */
    protected function datesFor(int $objectId): string
    {
        try {
            $rows = DB::table('event as e')
                ->leftJoin('event_i18n as ei', function ($j) {
                    $j->on('e.id', '=', 'ei.id')->where('ei.culture', $this->culture);
                })
                ->where('e.object_id', $objectId)
                ->select('ei.date as display_date', 'e.start_date', 'e.end_date')
                ->get();

            $dates = [];
            foreach ($rows as $r) {
                if (! empty($r->display_date)) {
                    $dates[] = (string) $r->display_date;
                } elseif (! empty($r->start_date)) {
                    $dates[] = $this->trimDate((string) $r->start_date)
                        .(! empty($r->end_date) ? '/'.$this->trimDate((string) $r->end_date) : '');
                }
            }

            return implode('; ', array_values(array_unique(array_filter($dates))));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The holding repository's authorised name (dc:publisher / schema:publisher).
     */
    protected function publisher($repositoryId): ?string
    {
        if (empty($repositoryId)) {
            return null;
        }

        try {
            $name = DB::table('repository as r')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('r.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('r.id', (int) $repositoryId)
                ->value('ai.authorized_form_of_name');

            return $name ? (string) $name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Subject access points for a record as a flat list. Best-effort - [] on a
     * schema variance.
     *
     * @return array<int,string>
     */
    protected function subjectsList(int $objectId): array
    {
        try {
            return DB::table('object_term_relation as otr')
                ->join('term_i18n as ti', function ($j) {
                    $j->on('otr.term_id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->where('otr.object_id', $objectId)
                ->whereNotNull('ti.name')
                ->distinct()
                ->pluck('ti.name')
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Subjects joined into a single CSV cell with " | " (fputcsv quotes the cell
     * so commas inside a subject are safe; the visible separator stays readable).
     */
    protected function subjectsCsv(int $objectId): string
    {
        return implode(' | ', $this->subjectsList($objectId));
    }

    protected function termName($termId): ?string
    {
        if (empty($termId)) {
            return null;
        }

        try {
            return DB::table('term_i18n')
                ->where('id', (int) $termId)
                ->where('culture', $this->culture)
                ->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Text / URI helpers
    // -----------------------------------------------------------------

    /**
     * Strip HTML and collapse whitespace, then truncate to ABSTRACT_MAX so a
     * long scope-and-content note stays a manageable cell / literal.
     */
    protected function plainText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
        if (mb_strlen($text) > self::ABSTRACT_MAX) {
            $text = mb_substr($text, 0, self::ABSTRACT_MAX - 1).'…';
        }

        return $text;
    }

    /**
     * Trim AtoM-style "-00" month/day placeholders from a stored date so
     * "1923-00-00" reads as "1923".
     */
    protected function trimDate(string $value): string
    {
        $value = trim($value);
        $value = (string) preg_replace('/-00(-00)?$/', '', $value);

        return (string) preg_replace('/-00$/', '', $value);
    }

    /**
     * Stable @id for a node: the per-entity graph URL on this host (crawlable,
     * resolves back to the open Linked-Data endpoint).
     */
    protected function graphUri(int $objectId): string
    {
        return $this->endpointBase().'/api/v1/graph/'.$objectId;
    }

    /**
     * The canonical public record page (slug-based) on this host.
     */
    protected function recordPublicUrl(?string $slug): ?string
    {
        if (empty($slug)) {
            return null;
        }

        return $this->endpointBase().'/'.ltrim((string) $slug, '/');
    }

    protected function endpointBase(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Map a level-of-description label to a schema.org type (mirrors
     * GraphController so the two open surfaces stay consistent).
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

    // -----------------------------------------------------------------
    // CORS
    // -----------------------------------------------------------------

    /**
     * Apply permissive open-data CORS headers. These endpoints are intentionally
     * world-readable (open data), so any origin may fetch them.
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
