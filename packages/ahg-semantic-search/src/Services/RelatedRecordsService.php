<?php

/**
 * RelatedRecordsService - the read-only data layer behind the public "Related
 * records" discovery surface.
 *
 * Given ONE published archival record, this service returns the most similar
 * OTHER published records by reusing the EXISTING semantic vector index - it does
 * NOT build a new index and does NOT call any AI service itself.
 *
 * How relatedness is computed (no new AI call):
 *
 *   1. The record's own embedding is ALREADY stored in the existing Qdrant
 *      collection (the same one the "Find Similar Records" widget and
 *      /api/search/semantic/similar/{ioId} use). Each point id == the
 *      information_object id.
 *   2. We hand the record's id straight to
 *      AhgSearch\Services\VectorSearchService::searchSimilarToPoint(). That
 *      method FETCHES the record's already-stored vector from Qdrant
 *      (fetchPointVector) and runs a bounded k-NN /points/search against the
 *      same collection. No query string is embedded, so no embedding call is
 *      made at all - it is pure stored-vector reuse.
 *   3. We then apply Heratio's publication gate to the neighbours (status
 *      type_id = 158 / status_id = 160), drop the record itself and the
 *      catalogue root (id = 1), hydrate {title, slug, url} and cap the list.
 *
 * NOTE on the gateway rule: this service reaches AI/embeddings ONLY through the
 * existing VectorSearchService abstraction, and in the stored-vector path it
 * triggers NO embedding call whatsoever (only a Qdrant vector read + k-NN). It
 * never opens a socket to a GPU node port (11434 / 5004 / 5006 / 8011) of its
 * own. If a deployment ever wires query-string embedding, that stays inside
 * VectorSearchService where the operator points it at the AHG gateway.
 *
 * It is STRICTLY read-only. It never writes, never ALTERs, adds no table, and
 * adds no index - it is a thin published-gated VIEW over the existing vector
 * index plus the catalogue's title / slug / status tables. Every path is
 * Schema/availability guarded and wrapped so a missing table, an empty index,
 * a record with no stored vector, or an unreachable vector store all degrade to
 * an EMPTY list rather than a 500. International, jurisdiction-neutral.
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

class RelatedRecordsService
{
    /** Catalogue root id, never surfaced as a real record. */
    protected const ROOT_ID = 1;

    /** Publication-status type_id and the "published" status_id in the status table. */
    protected const PUBLICATION_TYPE_ID = 158;

    protected const PUBLISHED_STATUS_ID = 160;

    /** Default number of related records returned. */
    public const DEFAULT_LIMIT = 12;

    /** Hard ceiling on how many related records are ever returned. */
    public const MAX_LIMIT = 20;

    /**
     * Resolve a route value (numeric id or slug) to a PUBLISHED information_object
     * id. Returns null when the value matches no information object, the object is
     * the catalogue root, or the object is not published. This is the 404 gate for
     * the controller - an unknown OR unpublished record yields null.
     */
    public function resolvePublishedId(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            if (! Schema::hasTable('information_object')) {
                return null;
            }
        } catch (\Throwable $e) {
            Log::info('[related] information_object probe failed: '.$e->getMessage());

            return null;
        }

        $id = null;

        // Numeric id - verify it is an information object.
        if (ctype_digit($value)) {
            $candidate = (int) $value;
            try {
                if (DB::table('information_object')->where('id', $candidate)->exists()) {
                    $id = $candidate;
                }
            } catch (\Throwable $e) {
                Log::info('[related] numeric id probe failed: '.$e->getMessage());
            }
        } else {
            // Slug -> object id, then verify it is an information object.
            try {
                if (Schema::hasTable('slug')) {
                    $resolved = DB::table('slug')->where('slug', $value)->value('object_id');
                    if ($resolved) {
                        $candidate = (int) $resolved;
                        if (DB::table('information_object')->where('id', $candidate)->exists()) {
                            $id = $candidate;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::info('[related] slug probe failed: '.$e->getMessage());
            }
        }

        if ($id === null || $id === self::ROOT_ID) {
            return null;
        }

        return $this->isPublished($id) ? $id : null;
    }

    /**
     * The most-similar OTHER published records to a given published record id.
     *
     * Reuses the EXISTING vector index via VectorSearchService::searchSimilarToPoint
     * (stored-vector k-NN; no embedding call). Each neighbour is then published-
     * gated, the source record + root are excluded, titles / slugs / urls are
     * hydrated, and the list is capped at $limit. Read-only; never throws -
     * degrades to []. The returned shape is stable for both the .json twin and the
     * HTML page.
     *
     * @return array<int,array{id:int,slug:?string,title:string,score:float,url:?string}>
     */
    public function relatedTo(int $objectId, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($objectId <= 0 || $objectId === self::ROOT_ID) {
            return [];
        }

        $limit = max(1, min($limit, self::MAX_LIMIT));

        $hits = $this->neighbourHits($objectId, $limit);
        if (empty($hits)) {
            return [];
        }

        // Collect candidate ids (exclude the source record + the catalogue root),
        // preserving the k-NN order, then gate them to published in one bounded
        // query. We over-fetch from the index (limit was padded in neighbourHits)
        // so the post-gate list can still fill up to $limit.
        $ordered = [];
        $scores = [];
        foreach ($hits as $h) {
            $hid = (int) ($h['id'] ?? 0);
            if ($hid <= 0 || $hid === $objectId || $hid === self::ROOT_ID) {
                continue;
            }
            if (! isset($scores[$hid])) {
                $ordered[] = $hid;
                $scores[$hid] = round((float) ($h['score'] ?? 0), 6);
            }
        }

        if (empty($ordered)) {
            return [];
        }

        $publishedIds = $this->filterPublished($ordered);
        if (empty($publishedIds)) {
            return [];
        }

        // Keep k-NN order, drop unpublished, cap at $limit.
        $keep = [];
        foreach ($ordered as $id) {
            if (in_array($id, $publishedIds, true)) {
                $keep[] = $id;
            }
            if (count($keep) >= $limit) {
                break;
            }
        }

        if (empty($keep)) {
            return [];
        }

        $meta = $this->hydrate($keep);

        $out = [];
        foreach ($keep as $id) {
            $m = $meta[$id] ?? ['title' => null, 'slug' => null];
            $slug = $m['slug'] ?? null;
            $out[] = [
                'id' => $id,
                'slug' => $slug,
                'title' => $m['title'] ?? (__('Record').' #'.$id),
                'score' => $scores[$id] ?? 0.0,
                'url' => $slug ? url('/'.$slug) : null,
            ];
        }

        return $out;
    }

    /**
     * Ask the EXISTING vector index for the nearest neighbours of a stored point.
     *
     * This is the single integration point with the existing semantic index. It
     * calls AhgSearch\Services\VectorSearchService::searchSimilarToPoint(), which
     * reads the record's already-stored vector from Qdrant and runs k-NN against
     * the same collection - no embedding / AI call. We pad the limit so the
     * post-publication gate still has enough candidates to fill the page.
     *
     * Returns [] when ahg-search is absent, the index is empty, the record has no
     * stored vector, or the vector store is unreachable. Never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function neighbourHits(int $objectId, int $limit): array
    {
        $serviceClass = 'AhgSearch\\Services\\VectorSearchService';
        if (! class_exists($serviceClass)) {
            // The existing semantic index lives in ahg-search; if that package is
            // not installed there is no index to reuse - degrade to empty.
            return [];
        }

        try {
            /** @var object $vector */
            $vector = app($serviceClass);

            // Pad the fetch so dropping the source record + any unpublished
            // neighbours still leaves room to reach $limit. Bounded by the
            // service's own internal cap (<= 100).
            $fetch = min(self::MAX_LIMIT * 3, max($limit * 3, $limit + 5));

            $result = $vector->searchSimilarToPoint($objectId, $fetch);
        } catch (\Throwable $e) {
            Log::info('[related] vector lookup failed for '.$objectId.': '.$e->getMessage());

            return [];
        }

        if (! is_array($result) || empty($result['ok']) || ! isset($result['hits']) || ! is_array($result['hits'])) {
            // ok=false covers "point not in collection" (no stored vector) and
            // "qdrant unavailable" - both degrade calmly to no related records.
            return [];
        }

        return $result['hits'];
    }

    /**
     * Is a single information object published? type_id 158 / status_id 160.
     * Read-only; never throws - treats any failure as "not published".
     */
    public function isPublished(int $objectId): bool
    {
        if ($objectId <= 0 || $objectId === self::ROOT_ID) {
            return false;
        }

        try {
            if (! Schema::hasTable('status')) {
                return false;
            }

            return DB::table('status')
                ->where('object_id', $objectId)
                ->where('type_id', self::PUBLICATION_TYPE_ID)
                ->where('status_id', self::PUBLISHED_STATUS_ID)
                ->exists();
        } catch (\Throwable $e) {
            Log::info('[related] publish probe failed for '.$objectId.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Reduce a candidate id list to the published ones in one bounded query.
     * Read-only; never throws - degrades to [].
     *
     * @param  array<int,int>  $ids
     * @return array<int,int>
     */
    protected function filterPublished(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > self::ROOT_ID)));
        if (empty($ids)) {
            return [];
        }

        try {
            if (! Schema::hasTable('status')) {
                return [];
            }

            return DB::table('status')
                ->whereIn('object_id', $ids)
                ->where('type_id', self::PUBLICATION_TYPE_ID)
                ->where('status_id', self::PUBLISHED_STATUS_ID)
                ->pluck('object_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable $e) {
            Log::info('[related] published filter failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Turn a list of information_object ids into {title, slug} metadata. One
     * bounded query each over the i18n title and the slug table, preferring the
     * active culture for the title. Read-only; never throws - degrades to [].
     *
     * @param  array<int,int>  $ids
     * @return array<int,array{title:?string,slug:?string}>
     */
    protected function hydrate(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        if (empty($ids)) {
            return [];
        }

        $culture = $this->culture();
        $titles = [];
        $slugs = [];

        try {
            if (Schema::hasTable('information_object_i18n')) {
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
            Log::info('[related] title hydrate failed: '.$e->getMessage());
        }

        try {
            if (Schema::hasTable('slug')) {
                $rows = DB::table('slug')->whereIn('object_id', $ids)->select('object_id', 'slug')->get();
                foreach ($rows as $r) {
                    $slugs[(int) $r->object_id] = (string) $r->slug;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[related] slug hydrate failed: '.$e->getMessage());
        }

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [
                'title' => $titles[$id] ?? null,
                'slug' => $slugs[$id] ?? null,
            ];
        }

        return $out;
    }

    /**
     * The title + slug of the source record itself, for the page header. Read-only;
     * never throws - degrades to nulls.
     *
     * @return array{title:string,slug:?string}
     */
    public function recordHeader(int $objectId): array
    {
        $meta = $this->hydrate([$objectId]);
        $m = $meta[$objectId] ?? ['title' => null, 'slug' => null];

        return [
            'title' => $m['title'] ?? (__('Record').' #'.$objectId),
            'slug' => $m['slug'] ?? null,
        ];
    }

    /**
     * The active UI culture, defaulting to English. Used to prefer localised record
     * titles.
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
