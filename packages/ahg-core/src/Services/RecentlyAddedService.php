<?php

/**
 * RecentlyAddedService - Heratio ahg-core
 *
 * Read-only "Recently added" aggregator. It returns the newest PUBLISHED
 * archival descriptions, most-recent first, so visitors and returning
 * researchers can see what is new in the collection.
 *
 * Creation signal
 * ---------------
 * `information_object` has NO created_at column. The real creation timestamp is
 * the class-table-inheritance ROOT row: object.created_at (object.id =
 * information_object.id) - confirmed present and fully populated on this
 * instance. So records are ordered by object.created_at DESC.
 *
 * When that column is absent (a slimmer schema / a future migration), the
 * service falls back honestly to ordering by id DESC (newest auto-increment
 * first) and flags `ordered_by_created` = false so the page can say so.
 *
 * Published gate
 * --------------
 * Published = a `status` row with type_id 158 (publication status) and
 * status_id 160 (published). The synthetic root description (id 1) is excluded.
 * Mirrors CollectionOverviewService / OpenSearchController exactly.
 *
 * Title / slug / snippet
 * ----------------------
 * Title + snippet come from information_object_i18n joined on the record's OWN
 * source_culture (information_object.source_culture), the same canonical-culture
 * join CollectionOverviewService uses for term/actor labels - so multi-culture
 * records yield the authoritative title, not a stray translation. The slug comes
 * from the `slug` table (object_id = record id) and the record URL is built from
 * it with url(), never a hardcoded host.
 *
 * Thumbnail
 * ---------
 * Cheap, optional: the master digital object (digital_object.object_id = record
 * id) and, when present, its usage_id=141 THUMBNAIL child (parent_id = master.id).
 * The web path is path + name; digital_object.path already begins with /uploads/
 * which nginx serves directly, so the public URL is url(path . name). No file IO,
 * no derivative generation - absence simply means no thumbnail.
 *
 * Bounded
 * -------
 * Every query is a bounded LIMIT (+ OFFSET for paging) over an indexed ORDER BY -
 * never a full scan. Each lookup is Schema::hasTable-guarded and wrapped in its
 * own try/catch, so a missing table or a transient failure yields a clean empty
 * list rather than a 500. The service performs NO writes and makes NO AI calls.
 *
 * Jurisdiction-neutral: no country-specific assumptions; all copy lives in the
 * view / feed and is internationalised there.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecentlyAddedService
{
    /** Publication-status taxonomy: status.type_id of a publication-status row. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id meaning "published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information object - never a real description. */
    private const ROOT_ID = 1;

    /** digital_object.usage_id for a generated THUMBNAIL surrogate. */
    private const USAGE_THUMBNAIL = 141;

    /** Default page size and the hard ceiling a caller may request. */
    public const DEFAULT_PER_PAGE = 24;
    public const MAX_PER_PAGE     = 100;

    /** How many characters of scope/content to keep as a card snippet. */
    private const SNIPPET_LENGTH = 220;

    /**
     * The newest PUBLISHED descriptions, most-recent first.
     *
     * Shape (every key always present):
     *
     * @return array{
     *     items: array<int, array{
     *         id:int, slug:?string, title:string, snippet:?string,
     *         created_at:?string, thumbnail:?string, url:?string
     *     }>,
     *     page:int, per_page:int, has_more:bool,
     *     ordered_by_created:bool, generated_at:string, error:bool
     * }
     *
     * @param  int  $page      1-based page number (clamped to >= 1)
     * @param  int  $perPage   items per page (clamped to 1..MAX_PER_PAGE)
     */
    public function recent(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $offset  = ($page - 1) * $perPage;

        // object.created_at is the real creation signal; degrade to id DESC if absent.
        $hasCreatedAt = $this->hasObjectCreatedAt();

        $empty = [
            'items'              => [],
            'page'               => $page,
            'per_page'           => $perPage,
            'has_more'           => false,
            'ordered_by_created' => $hasCreatedAt,
            'generated_at'       => now()->toDateTimeString(),
            'error'              => false,
        ];

        if (! Schema::hasTable('information_object')
            || ! Schema::hasTable('status')
            || ! Schema::hasTable('object')) {
            return $empty;
        }

        try {
            // Fetch one extra row to know whether a "next" page exists, cheaply.
            $rows = $this->query($hasCreatedAt, $perPage + 1, $offset);

            $hasMore = count($rows) > $perPage;
            if ($hasMore) {
                $rows = $rows->slice(0, $perPage)->values();
            }

            // Resolve thumbnails for this page in one batched query (no per-row IO).
            $thumbs = $this->thumbnailsFor($rows->pluck('id')->all());

            $items = [];
            foreach ($rows as $r) {
                $id    = (int) $r->id;
                $slug  = isset($r->slug) ? trim((string) $r->slug) : '';
                $title = trim((string) ($r->title ?? ''));
                if ($title === '') {
                    $title = __('Untitled record');
                }

                $items[] = [
                    'id'         => $id,
                    'slug'       => $slug !== '' ? $slug : null,
                    'title'      => $title,
                    'snippet'    => $this->snippet($r->snippet ?? null),
                    'created_at' => $this->normaliseDate($r->created_at ?? null),
                    'thumbnail'  => $thumbs[$id] ?? null,
                    'url'        => $this->recordUrl($slug),
                ];
            }

            return [
                'items'              => $items,
                'page'               => $page,
                'per_page'           => $perPage,
                'has_more'           => $hasMore,
                'ordered_by_created' => $hasCreatedAt,
                'generated_at'       => now()->toDateTimeString(),
                'error'              => false,
            ];
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] recently-added recent() failed: '.$e->getMessage());

            return ['error' => true] + $empty;
        }
    }

    /**
     * The bounded page query: PUBLISHED, non-root descriptions joined to their
     * CTI root (object) for created_at, their own-culture i18n (title + snippet),
     * and slug, ordered newest first.
     *
     * SQL (essence):
     *   SELECT o.id, o.created_at, s.slug, i.title, i.scope_and_content AS snippet
     *     FROM object o
     *     JOIN (published ids) pub ON pub.object_id = o.id
     *     JOIN information_object io ON io.id = o.id
     *     LEFT JOIN information_object_i18n i
     *            ON i.id = io.id AND i.culture = io.source_culture
     *     LEFT JOIN slug s ON s.object_id = o.id
     *    ORDER BY o.created_at DESC, o.id DESC
     *    LIMIT ? OFFSET ?
     *
     * When object.created_at is absent the ORDER BY is o.id DESC only.
     */
    private function query(bool $hasCreatedAt, int $limit, int $offset): \Illuminate\Support\Collection
    {
        $hasI18n   = Schema::hasTable('information_object_i18n');
        $hasSlug   = Schema::hasTable('slug');
        $hasSource = Schema::hasColumn('information_object', 'source_culture');

        $q = DB::table('object as o')
            ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'o.id')
            ->join('information_object as io', 'io.id', '=', 'o.id');

        if ($hasI18n) {
            $q->leftJoin('information_object_i18n as i', function ($j) use ($hasSource) {
                $j->on('i.id', '=', 'io.id');
                if ($hasSource) {
                    $j->on('i.culture', '=', 'io.source_culture');
                }
            });
        }

        if ($hasSlug) {
            $q->leftJoin('slug as s', 's.object_id', '=', 'o.id');
        }

        $select = ['o.id'];
        $select[] = $hasCreatedAt ? 'o.created_at' : DB::raw('NULL as created_at');
        $select[] = $hasSlug ? 's.slug' : DB::raw('NULL as slug');
        if ($hasI18n) {
            $select[] = 'i.title';
            $select[] = 'i.scope_and_content as snippet';
        } else {
            $select[] = DB::raw('NULL as title');
            $select[] = DB::raw('NULL as snippet');
        }

        $q->select($select);

        if ($hasCreatedAt) {
            $q->orderByDesc('o.created_at');
        }
        $q->orderByDesc('o.id');

        return $q->limit($limit)->offset($offset)->get();
    }

    /**
     * A reusable subquery of every PUBLISHED, non-root object_id. Mirrors
     * CollectionOverviewService::publishedIdSub exactly.
     */
    private function publishedIdSub()
    {
        return DB::table('status')
            ->select('object_id')
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::STATUS_PUBLISHED)
            ->where('object_id', '>', self::ROOT_ID);
    }

    /**
     * Batched thumbnail lookup for a set of record ids. For each id we take the
     * usage_id=141 THUMBNAIL child of the record's master digital object (the
     * master is the digital_object whose object_id is the record id). One query,
     * no file IO; ids with no thumbnail simply do not appear in the map.
     *
     * SQL (essence):
     *   SELECT m.object_id AS id, t.path, t.name
     *     FROM digital_object m
     *     JOIN digital_object t ON t.parent_id = m.id AND t.usage_id = 141
     *    WHERE m.object_id IN (...)
     *
     * @param  array<int,int>  $ids
     * @return array<int,string>  id => absolute thumbnail URL
     */
    private function thumbnailsFor(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids) || ! Schema::hasTable('digital_object')) {
            return [];
        }

        try {
            $rows = DB::table('digital_object as m')
                ->join('digital_object as t', function ($j) {
                    $j->on('t.parent_id', '=', 'm.id')
                        ->where('t.usage_id', '=', self::USAGE_THUMBNAIL);
                })
                ->whereIn('m.object_id', $ids)
                ->whereNotNull('t.name')
                ->select(['m.object_id as id', 't.path', 't.name'])
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $id = (int) $r->id;
                if (isset($out[$id])) {
                    continue; // first thumbnail wins
                }
                $web = $this->thumbnailWebPath((string) ($r->path ?? ''), (string) ($r->name ?? ''));
                if ($web !== null) {
                    $out[$id] = $web;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] recently-added thumbnailsFor failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Build an absolute thumbnail URL from a digital_object path + name. The path
     * already begins with /uploads/ (served directly by nginx); we join path+name
     * and resolve it through url() so the host is never hardcoded.
     */
    private function thumbnailWebPath(string $path, string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $rel = rtrim($path, '/').'/'.ltrim($name, '/');
        $rel = '/'.ltrim($rel, '/');

        return url($rel);
    }

    /** Absolute record URL from its slug (single-segment public show), or null. */
    private function recordUrl(string $slug): ?string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return url('/'.ltrim($slug, '/'));
    }

    /** Whether object.created_at is present (the real creation signal). */
    private function hasObjectCreatedAt(): bool
    {
        try {
            return Schema::hasTable('object') && Schema::hasColumn('object', 'created_at');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * A short plain-text snippet from a scope/content field: tags stripped,
     * whitespace collapsed, trimmed to SNIPPET_LENGTH on a word boundary with an
     * ellipsis. Returns null for empty input.
     */
    private function snippet($raw): ?string
    {
        $text = trim((string) ($raw ?? ''));
        if ($text === '') {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if ($text === '') {
            return null;
        }

        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($len <= self::SNIPPET_LENGTH) {
            return $text;
        }

        $cut = function_exists('mb_substr')
            ? mb_substr($text, 0, self::SNIPPET_LENGTH)
            : substr($text, 0, self::SNIPPET_LENGTH);

        $lastSpace = function_exists('mb_strrpos') ? mb_strrpos($cut, ' ') : strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = function_exists('mb_substr') ? mb_substr($cut, 0, $lastSpace) : substr($cut, 0, $lastSpace);
        }

        return rtrim($cut).'...';
    }

    /**
     * Normalise a created_at value to a "YYYY-MM-DD HH:MM:SS" string, or null.
     * The DB returns a string already; this just guards type and emptiness.
     */
    private function normaliseDate($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s !== '' ? $s : null;
    }
}
