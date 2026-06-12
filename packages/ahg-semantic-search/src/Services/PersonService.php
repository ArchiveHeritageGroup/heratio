<?php

/**
 * PersonService - the read-only data layer behind the public "People and
 * organisations" discovery surface (the creator slice; sibling of the "Explore
 * by theme" subject slice and the "Browse by place" geography slice).
 *
 * A creator is an actor (a person or an organisation) credited as having created
 * a record: the actors that the `event` table links to an information_object
 * (the creation linkage). The actors credited across the most PUBLISHED records
 * are the collection's busiest creators. This service surfaces them as "ways into
 * the collection by the people and organisations that made the holdings":
 *
 *   - topCreators()       - the busiest creators by published-record count (one
 *                           cheap bounded GROUP BY aggregate over event ->
 *                           actor_id, gated to published records, root excluded),
 *                           each with its count.
 *   - creatorList()       - the same compact list for the machine-readable .json
 *                           twin (actor_id, name, count only).
 *   - creator()           - one creator: the authorized form of name + dates /
 *                           history if present + a paginated, bounded list of the
 *                           published records they created.
 *
 * It is STRICTLY read-only. It never writes, never ALTERs, never calls AI, and
 * adds no table - it is a cheap aggregate VIEW over the existing entity tables
 * (event / actor / actor_i18n) and the publication-status table. Every path is
 * Schema::hasTable-guarded and wrapped so a missing table degrades to an empty
 * result rather than a 500. International, jurisdiction-neutral: the names come
 * entirely from the data; no person or organisation is hardcoded and there is no
 * country default.
 *
 * Creator linkage (DESCRIBE-verified, mirrors EntityController::creators): the
 * `event` table carries object_id + actor_id; an event row whose actor_id is set
 * links that record to its creator actor, and actor_i18n.authorized_form_of_name
 * (preferring the active culture) is the creator's name. The creation event type
 * is term id 111 ("Creation"); like EntityController we count every actor-bearing
 * event so co-creation / production credits are not silently dropped, while the
 * published gate keeps the surface to released holdings only.
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

class PersonService
{
    /** Creation event type id ("Creation") - the canonical creator linkage. */
    public const CREATION_EVENT_TYPE_ID = 111;

    /** Catalogue root id, never surfaced as a real record. */
    protected const ROOT_ID = 1;

    /** Publication-status type_id and the "published" status_id in the status table. */
    protected const PUBLICATION_TYPE_ID = 158;

    protected const PUBLISHED_STATUS_ID = 160;

    /** Hard ceiling on how many creators the landing aggregate ever returns. */
    public const MAX_CREATORS = 120;

    /** Default creators shown on the landing cloud. */
    public const DEFAULT_CREATORS = 80;

    /** Default + ceiling for the per-creator paginated record list. */
    public const PER_PAGE = 24;

    public const MAX_PER_PAGE = 60;

    /**
     * Are the tables this surface needs present? Every path gates on this so a
     * fresh (un-booted) install renders the empty-state rather than fataling.
     */
    public function available(): bool
    {
        try {
            return Schema::hasTable('event')
                && Schema::hasTable('actor')
                && Schema::hasTable('actor_i18n');
        } catch (\Throwable $e) {
            Log::info('[people] table probe failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * The busiest creators by PUBLISHED-record count - the collection's "ways in"
     * via the people and organisations that made it. One cheap bounded GROUP BY
     * aggregate over event joined to actor, gated to published records, ordered by
     * count, capped at MAX_CREATORS. Read-only; never throws - degrades to [].
     *
     * @param  int  $limit  how many creators to return (clamped 1..MAX_CREATORS)
     * @return array<int,array{actor_id:int,name:string,record_count:int,url:string,browse_url:string}>
     */
    public function topCreators(int $limit = self::DEFAULT_CREATORS): array
    {
        $rows = $this->aggregate($limit);
        if (empty($rows)) {
            return [];
        }

        $culture = $this->culture();
        $names = $this->actorNames(array_map(static fn ($r) => (int) $r->actor_id, $rows), $culture);

        $out = [];
        foreach ($rows as $row) {
            $actorId = (int) $row->actor_id;
            $out[] = [
                'actor_id' => $actorId,
                'name' => $names[$actorId] ?? (__('Creator').' #'.$actorId),
                'record_count' => (int) $row->record_count,
                'url' => $this->creatorUrl($actorId),
                'browse_url' => $this->browseUrl($actorId),
            ];
        }

        return $out;
    }

    /**
     * A compact creator list for the machine-readable .json twin: actor_id, name
     * and published-record count only. Read-only; never throws - degrades to [].
     *
     * @return array<int,array{actor_id:int,name:string,record_count:int}>
     */
    public function creatorList(int $limit = self::MAX_CREATORS): array
    {
        $rows = $this->aggregate($limit);
        if (empty($rows)) {
            return [];
        }

        $culture = $this->culture();
        $names = $this->actorNames(array_map(static fn ($r) => (int) $r->actor_id, $rows), $culture);

        $out = [];
        foreach ($rows as $row) {
            $actorId = (int) $row->actor_id;
            $out[] = [
                'actor_id' => $actorId,
                'name' => $names[$actorId] ?? (__('Creator').' #'.$actorId),
                'record_count' => (int) $row->record_count,
            ];
        }

        return $out;
    }

    /**
     * The shared bounded aggregate behind topCreators() and creatorList(): group the
     * published object<->creator-actor links by actor, count distinct records, keep
     * the busiest. The EXISTS sub-select applies the publication gate (type 158 /
     * status 160) without a wide join, and excludes the catalogue root. Read-only;
     * never throws - degrades to an empty array.
     *
     * @return array<int,object>  rows of {actor_id, record_count}
     */
    protected function aggregate(int $limit): array
    {
        if (! $this->available()) {
            return [];
        }

        $limit = max(1, min($limit, self::MAX_CREATORS));

        try {
            return DB::table('event as e')
                ->join('actor as a', 'a.id', '=', 'e.actor_id')
                ->whereNotNull('e.actor_id')
                ->whereRaw('e.object_id <> ?', [self::ROOT_ID])
                ->whereNotNull('e.object_id')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('status')
                        ->whereRaw('status.object_id = e.object_id')
                        ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                        ->where('status.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->groupBy('e.actor_id')
                ->select('e.actor_id', DB::raw('COUNT(DISTINCT e.object_id) AS record_count'))
                ->orderByDesc('record_count')
                ->orderBy('e.actor_id')
                ->limit($limit)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            Log::info('[people] aggregate failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * One creator in full: the authorized form of name, optional dates of existence
     * and history, the total published-record count, and a single bounded page of
     * the published records they created. The record list is paginated (page +
     * per-page offset) and capped at MAX_PER_PAGE so a prolific creator can never
     * run an unbounded query. Returns null when the actor is missing or has no
     * published records. Read-only; never throws.
     *
     * @return array<string,mixed>|null
     */
    public function creator(int $actorId, int $page = 1, int $perPage = self::PER_PAGE): ?array
    {
        if (! $this->available() || $actorId <= 0) {
            return null;
        }

        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page = max(1, $page);
        $culture = $this->culture();

        // The actor must exist as a real entity row.
        try {
            $isActor = DB::table('actor')->where('id', $actorId)->exists();
        } catch (\Throwable $e) {
            Log::info('[people] actor probe failed for '.$actorId.': '.$e->getMessage());

            return null;
        }

        if (! $isActor) {
            return null;
        }

        $detail = $this->actorDetail($actorId, $culture);
        $name = $detail['name'] ?? (__('Creator').' #'.$actorId);

        // Total published records created by this actor (cheap COUNT DISTINCT).
        try {
            $total = (int) DB::table('event as e')
                ->where('e.actor_id', $actorId)
                ->whereRaw('e.object_id <> ?', [self::ROOT_ID])
                ->whereNotNull('e.object_id')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('status')
                        ->whereRaw('status.object_id = e.object_id')
                        ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                        ->where('status.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->distinct()
                ->count('e.object_id');
        } catch (\Throwable $e) {
            Log::info('[people] creator count failed for '.$actorId.': '.$e->getMessage());
            $total = 0;
        }

        if ($total <= 0) {
            return null;
        }

        $records = $this->publishedRecordsPage($actorId, $page, $perPage, $culture);

        return [
            'actor_id' => $actorId,
            'name' => $name,
            'dates' => $detail['dates'] ?? null,
            'history' => $detail['history'] ?? null,
            'total' => $total,
            'records' => $records,           // array<int,array{id,title,slug}>
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            'browse_url' => $this->browseUrl($actorId),
        ];
    }

    /**
     * A single bounded page of PUBLISHED records created by an actor, ordered by
     * record id for a stable page sequence. Read-only; never throws - degrades to
     * [].
     *
     * @return array<int,array{id:int,title:string,slug:?string}>
     */
    protected function publishedRecordsPage(int $actorId, int $page, int $perPage, string $culture): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            $ids = DB::table('event as e')
                ->where('e.actor_id', $actorId)
                ->whereRaw('e.object_id <> ?', [self::ROOT_ID])
                ->whereNotNull('e.object_id')
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('status')
                        ->whereRaw('status.object_id = e.object_id')
                        ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                        ->where('status.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->groupBy('e.object_id')
                ->orderBy('e.object_id')
                ->offset($offset)
                ->limit($perPage)
                ->pluck('e.object_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::info('[people] records page failed for '.$actorId.': '.$e->getMessage());

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
            Log::info('[people] title hydrate failed: '.$e->getMessage());
        }

        try {
            if (Schema::hasTable('slug')) {
                $rows = DB::table('slug')->whereIn('object_id', $ids)->select('object_id', 'slug')->get();
                foreach ($rows as $r) {
                    $slugs[(int) $r->object_id] = (string) $r->slug;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[people] slug hydrate failed: '.$e->getMessage());
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
     * Resolve the authorized form of name for a set of actor ids, preferring the
     * active culture and falling back to any available row. One bounded query.
     * Read-only; never throws.
     *
     * @param  array<int,int>  $actorIds
     * @return array<int,string>  actor_id => authorized form of name
     */
    protected function actorNames(array $actorIds, string $culture): array
    {
        $actorIds = array_values(array_unique(array_filter(array_map('intval', $actorIds), fn ($v) => $v > 0)));
        if (empty($actorIds)) {
            return [];
        }

        $names = [];
        try {
            $rows = DB::table('actor_i18n')
                ->whereIn('id', $actorIds)
                ->select('id', 'culture', 'authorized_form_of_name')
                ->get();
            foreach ($rows as $r) {
                $id = (int) $r->id;
                $name = trim((string) ($r->authorized_form_of_name ?? ''));
                if ($name === '') {
                    continue;
                }
                if (! isset($names[$id]) || (string) $r->culture === $culture) {
                    $names[$id] = $name;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[people] actor names failed: '.$e->getMessage());
        }

        return $names;
    }

    /**
     * The display detail for one actor: authorized form of name, dates of existence
     * and history, preferring the active culture and falling back to any row. One
     * bounded query. Read-only; never throws - degrades to a name-only array.
     *
     * @return array{name:?string,dates:?string,history:?string}
     */
    protected function actorDetail(int $actorId, string $culture): array
    {
        $out = ['name' => null, 'dates' => null, 'history' => null];

        try {
            $rows = DB::table('actor_i18n')
                ->where('id', $actorId)
                ->select('culture', 'authorized_form_of_name', 'dates_of_existence', 'history')
                ->get();

            foreach ($rows as $r) {
                $isPreferred = (string) $r->culture === $culture;

                $name = trim((string) ($r->authorized_form_of_name ?? ''));
                if ($name !== '' && ($out['name'] === null || $isPreferred)) {
                    $out['name'] = $name;
                }

                $dates = trim((string) ($r->dates_of_existence ?? ''));
                if ($dates !== '' && ($out['dates'] === null || $isPreferred)) {
                    $out['dates'] = $dates;
                }

                $history = trim((string) ($r->history ?? ''));
                if ($history !== '' && ($out['history'] === null || $isPreferred)) {
                    $out['history'] = $history;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[people] actor detail failed for '.$actorId.': '.$e->getMessage());
        }

        return $out;
    }

    /**
     * The public per-creator detail URL for one actor. url()-relative, never a
     * hardcoded host.
     */
    public function creatorUrl(int $actorId): string
    {
        return url('/people/'.$actorId);
    }

    /**
     * The GLAM-browse deep link for one creator - reuses the single canonical
     * browse page (ahg-display) with its `creator=<actor_id>` filter (the same
     * actor id the browse matches on event.actor_id), so "browse all by this
     * creator" lands in the same place as the facet. url()-relative, never a
     * hardcoded host.
     */
    public function browseUrl(int $actorId): string
    {
        return url('/glam/browse?creator='.$actorId);
    }

    /**
     * The active UI culture, defaulting to English. Used to prefer localised actor
     * names, dates, history and record titles.
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
