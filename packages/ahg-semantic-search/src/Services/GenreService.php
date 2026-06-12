<?php

/**
 * GenreService - the read-only data layer behind the public "Browse by genre /
 * form" discovery surface (the genre/form slice; sibling of the "Explore by
 * theme" subject slice in ThemeService and the "Browse by place" geography slice
 * in PlaceService).
 *
 * A genre/form is a genre or document-form access point in the catalogue: the
 * genre terms (taxonomy 78) that the most PUBLISHED records carry are the
 * collection's busiest genres/forms. This service surfaces them as "ways into the
 * collection by genre/form":
 *
 *   - topGenres()        - the busiest genre terms by published-record count
 *                          (one cheap bounded GROUP BY aggregate over
 *                          object_term_relation -> term, gated to published
 *                          records, root excluded), each with its count.
 *   - genreList()        - the same compact list for the machine-readable .json
 *                          twin (id, label, count only).
 *   - genre()            - one genre: its label + scope note + a paginated,
 *                          bounded list of the published records of that
 *                          genre/form.
 *
 * It is STRICTLY read-only. It never writes, never ALTERs, never calls AI, and
 * adds no table - it is a cheap aggregate VIEW over the existing taxonomy
 * (term / term_i18n / object_term_relation) and the publication-status table.
 * Every path is Schema::hasTable-guarded and wrapped so a missing table degrades
 * to an empty result rather than a 500. International, jurisdiction-neutral: the
 * genre names come entirely from the data; no vocabulary is hardcoded and there
 * is no country default.
 *
 * Published gate (mirrors the rest of Heratio): a record is "published" when its
 * row in the status table (type_id = 158) carries status_id = 160; the catalogue
 * root (id = 1) is never surfaced.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GenreService
{
    /** Genre / form taxonomy id - the genre/form access points of the collection. */
    public const GENRE_TAXONOMY_ID = 78;

    /** Catalogue root id, never surfaced as a real record. */
    protected const ROOT_ID = 1;

    /** Publication-status type_id and the "published" status_id in the status table. */
    protected const PUBLICATION_TYPE_ID = 158;

    protected const PUBLISHED_STATUS_ID = 160;

    /** Hard ceiling on how many genres the landing aggregate ever returns. */
    public const MAX_GENRES = 120;

    /** Default genres shown on the landing cloud. */
    public const DEFAULT_GENRES = 80;

    /** Default + ceiling for the per-genre paginated record list. */
    public const PER_PAGE = 24;

    public const MAX_PER_PAGE = 60;

    /**
     * Are the tables this surface needs present? Every path gates on this so a
     * fresh (un-booted) install renders the empty-state rather than fataling.
     */
    public function available(): bool
    {
        try {
            return Schema::hasTable('object_term_relation')
                && Schema::hasTable('term')
                && Schema::hasTable('term_i18n');
        } catch (\Throwable $e) {
            Log::info('[genres] table probe failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * The busiest genre terms by PUBLISHED-record count - the collection's
     * genre/form "ways in". One cheap bounded GROUP BY aggregate over
     * object_term_relation joined to term (taxonomy 78) and the publication-status
     * table, ordered by count, capped at MAX_GENRES. Read-only; never throws -
     * degrades to [].
     *
     * @param  int  $limit  how many genres to return (clamped 1..MAX_GENRES)
     * @return array<int,array{term_id:int,label:string,record_count:int,url:string,browse_url:string}>
     */
    public function topGenres(int $limit = self::DEFAULT_GENRES): array
    {
        $rows = $this->aggregate($limit);
        if (empty($rows)) {
            return [];
        }

        $culture = $this->culture();
        $labels = $this->termLabels(array_map(static fn ($r) => (int) $r->term_id, $rows), $culture);

        $out = [];
        foreach ($rows as $row) {
            $termId = (int) $row->term_id;
            $out[] = [
                'term_id' => $termId,
                'label' => $labels[$termId] ?? (__('Genre').' #'.$termId),
                'record_count' => (int) $row->record_count,
                'url' => $this->genreUrl($termId),
                'browse_url' => $this->browseUrl($termId),
            ];
        }

        return $out;
    }

    /**
     * A compact genre list for the machine-readable .json twin: id, label and
     * published-record count only. Read-only; never throws - degrades to [].
     *
     * @return array<int,array{term_id:int,label:string,record_count:int}>
     */
    public function genreList(int $limit = self::MAX_GENRES): array
    {
        $rows = $this->aggregate($limit);
        if (empty($rows)) {
            return [];
        }

        $culture = $this->culture();
        $labels = $this->termLabels(array_map(static fn ($r) => (int) $r->term_id, $rows), $culture);

        $out = [];
        foreach ($rows as $row) {
            $termId = (int) $row->term_id;
            $out[] = [
                'term_id' => $termId,
                'label' => $labels[$termId] ?? (__('Genre').' #'.$termId),
                'record_count' => (int) $row->record_count,
            ];
        }

        return $out;
    }

    /**
     * The shared bounded aggregate behind topGenres() and genreList(): group the
     * published object<->genre-term links by term, count distinct records, keep the
     * busiest. The EXISTS sub-select applies the publication gate (type 158 /
     * status 160) without a wide join, and excludes the catalogue root. Read-only;
     * never throws - degrades to an empty array.
     *
     * @return array<int,object>  rows of {term_id, record_count}
     */
    protected function aggregate(int $limit): array
    {
        if (! $this->available()) {
            return [];
        }

        $limit = max(1, min($limit, self::MAX_GENRES));

        try {
            return DB::table('object_term_relation as otr')
                ->join('term as t', function ($j) {
                    $j->on('t.id', '=', 'otr.term_id')
                        ->where('t.taxonomy_id', '=', self::GENRE_TAXONOMY_ID);
                })
                ->whereRaw('otr.object_id <> ?', [self::ROOT_ID])
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('status')
                        ->whereRaw('status.object_id = otr.object_id')
                        ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                        ->where('status.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->groupBy('otr.term_id')
                ->select('otr.term_id', DB::raw('COUNT(DISTINCT otr.object_id) AS record_count'))
                ->orderByDesc('record_count')
                ->orderBy('otr.term_id')
                ->limit($limit)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            Log::info('[genres] aggregate failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * One genre in full: its label + scope note, its total published-record count,
     * and a single bounded page of the published records of it. The record list is
     * paginated (page + per-page offset) and capped at MAX_PER_PAGE so a busy genre
     * can never run an unbounded query. Returns null when the term is missing, is
     * not a genre term, or has no published records. Read-only; never throws.
     *
     * @return array<string,mixed>|null
     */
    public function genre(int $termId, int $page = 1, int $perPage = self::PER_PAGE): ?array
    {
        if (! $this->available() || $termId <= 0 || $termId === self::ROOT_ID) {
            return null;
        }

        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page = max(1, $page);
        $culture = $this->culture();

        // The term must exist AND be a genre term (taxonomy 78). A subject or place
        // id would not be a "genre" and is rejected here.
        try {
            $isGenre = DB::table('term')
                ->where('id', $termId)
                ->where('taxonomy_id', self::GENRE_TAXONOMY_ID)
                ->exists();
        } catch (\Throwable $e) {
            Log::info('[genres] term probe failed for '.$termId.': '.$e->getMessage());

            return null;
        }

        if (! $isGenre) {
            return null;
        }

        $label = $this->termLabels([$termId], $culture)[$termId] ?? (__('Genre').' #'.$termId);
        $note = $this->termNote($termId, $culture);

        // Total published records of this genre (cheap COUNT DISTINCT).
        try {
            $total = (int) DB::table('object_term_relation as otr')
                ->where('otr.term_id', $termId)
                ->whereRaw('otr.object_id <> ?', [self::ROOT_ID])
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('status')
                        ->whereRaw('status.object_id = otr.object_id')
                        ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                        ->where('status.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->distinct()
                ->count('otr.object_id');
        } catch (\Throwable $e) {
            Log::info('[genres] genre count failed for '.$termId.': '.$e->getMessage());
            $total = 0;
        }

        if ($total <= 0) {
            return null;
        }

        $records = $this->publishedRecordsPage($termId, $page, $perPage, $culture);

        return [
            'term_id' => $termId,
            'label' => $label,
            'note' => $note,
            'total' => $total,
            'records' => $records,           // array<int,array{id,title,slug}>
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            'browse_url' => $this->browseUrl($termId),
        ];
    }

    /**
     * A single bounded page of PUBLISHED records of a genre term, ordered by record
     * id for a stable page sequence. Read-only; never throws - degrades to [].
     *
     * @return array<int,array{id:int,title:string,slug:?string}>
     */
    protected function publishedRecordsPage(int $termId, int $page, int $perPage, string $culture): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            $ids = DB::table('object_term_relation as otr')
                ->where('otr.term_id', $termId)
                ->whereRaw('otr.object_id <> ?', [self::ROOT_ID])
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('status')
                        ->whereRaw('status.object_id = otr.object_id')
                        ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                        ->where('status.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->groupBy('otr.object_id')
                ->orderBy('otr.object_id')
                ->offset($offset)
                ->limit($perPage)
                ->pluck('otr.object_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::info('[genres] records page failed for '.$termId.': '.$e->getMessage());

            return [];
        }

        if (empty($ids)) {
            return [];
        }

        return $this->hydrateRecords($ids, $culture);
    }

    /**
     * Turn a small list of information_object ids into {id,title,slug} rows for
     * the record links. One bounded query each over the i18n title and the slug
     * table; preserves the incoming id order. Read-only; never throws.
     *
     * @param  array<int,int>  $ids
     * @return array<int,array{id:int,title:string,slug:?string}>
     */
    protected function hydrateRecords(array $ids, string $culture): array
    {
        if (empty($ids)) {
            return [];
        }

        $titles = [];
        $slugs = [];

        try {
            if (Schema::hasTable('information_object_i18n')) {
                // Prefer the requested culture; fall back to any row per id.
                $rows = DB::table('information_object_i18n')
                    ->whereIn('id', $ids)
                    ->select('id', 'culture', 'title')
                    ->get();
                foreach ($rows as $r) {
                    $id = (int) $r->id;
                    $title = trim((string) ($r->title ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    if (! isset($titles[$id]) || (string) $r->culture === $culture) {
                        $titles[$id] = $title;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('[genres] title hydrate failed: '.$e->getMessage());
        }

        try {
            if (Schema::hasTable('slug')) {
                $rows = DB::table('slug')->whereIn('object_id', $ids)->select('object_id', 'slug')->get();
                foreach ($rows as $r) {
                    $slugs[(int) $r->object_id] = (string) $r->slug;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[genres] slug hydrate failed: '.$e->getMessage());
        }

        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $out[] = [
                'id' => $id,
                'title' => $titles[$id] ?? (__('Record').' #'.$id),
                'slug' => $slugs[$id] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Resolve term labels for a set of term ids, preferring the active culture and
     * falling back to any available row. One bounded query. Read-only; never
     * throws.
     *
     * @param  array<int,int>  $termIds
     * @return array<int,string>  term_id => label
     */
    protected function termLabels(array $termIds, string $culture): array
    {
        $termIds = array_values(array_unique(array_filter(array_map('intval', $termIds), fn ($v) => $v > 0)));
        if (empty($termIds)) {
            return [];
        }

        $labels = [];
        try {
            $rows = DB::table('term_i18n')
                ->whereIn('id', $termIds)
                ->select('id', 'culture', 'name')
                ->get();
            foreach ($rows as $r) {
                $id = (int) $r->id;
                $name = trim((string) ($r->name ?? ''));
                if ($name === '') {
                    continue;
                }
                if (! isset($labels[$id]) || (string) $r->culture === $culture) {
                    $labels[$id] = $name;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[genres] term labels failed: '.$e->getMessage());
        }

        return $labels;
    }

    /**
     * A scope note for one term, if the deployment carries term notes. The base
     * schema keeps optional scope notes in note rows; we read them best-effort and
     * degrade to null. Read-only; never throws.
     */
    protected function termNote(int $termId, string $culture): ?string
    {
        try {
            if (! Schema::hasTable('note') || ! Schema::hasTable('note_i18n')) {
                return null;
            }
            $noteIds = DB::table('note')->where('object_id', $termId)->limit(5)->pluck('id')->all();
            if (empty($noteIds)) {
                return null;
            }
            $rows = DB::table('note_i18n')->whereIn('id', $noteIds)->select('culture', 'content')->get();
            $best = null;
            foreach ($rows as $r) {
                $content = trim((string) ($r->content ?? ''));
                if ($content === '') {
                    continue;
                }
                if ($best === null || (string) $r->culture === $culture) {
                    $best = $content;
                }
            }

            return $best;
        } catch (\Throwable $e) {
            Log::info('[genres] term note failed for '.$termId.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * The public per-genre detail URL for one genre term. url()-relative, never a
     * hardcoded host.
     */
    public function genreUrl(int $termId): string
    {
        return url('/genres/'.$termId);
    }

    /**
     * The GLAM-browse deep link for one genre - reuses the single canonical browse
     * page (ahg-display) with its `genre=<term_id>` filter, so "browse all records
     * of this genre" lands in the same place as the facet. url()-relative, never a
     * hardcoded host.
     */
    public function browseUrl(int $termId): string
    {
        return url('/glam/browse?genre='.$termId);
    }

    /**
     * The active UI culture, defaulting to English. Used to prefer localised term
     * labels and record titles.
     */
    protected function culture(): string
    {
        try {
            $loc = (string) app()->getLocale();

            return $loc !== '' ? $loc : 'en';
        } catch (\Throwable $e) {
            return 'en';
        }
    }
}
