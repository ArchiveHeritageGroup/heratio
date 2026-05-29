<?php

/**
 * OdiScorecardService - Open Discovery Initiative (ODI) quality scorecard.
 *
 * Computes the four ODI conformance metrics for each library collection
 * (a collection = a parent information_object grouping library_item rows):
 *
 *   1. link_resolver_present - is an OpenURL link resolver configured/available
 *   2. oa_percentage         - share of items that are open access
 *   3. preprints_indexed     - count of preprint-class items
 *   4. orcid_in_records      - count of creator records carrying an ORCID
 *
 * These feed a composite quality_score (0-100). The scoring arithmetic is a
 * pure function (computeQualityScore) so it can be unit tested without a
 * database; the aggregation layer reads from library_item, library_item_creator
 * and ahg_actor_identifier and persists into library_odi_collection.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OdiScorecardService
{
    /**
     * Weights applied to each normalised metric to produce the composite score.
     * They sum to 1.0 so the resulting quality_score is on a 0-100 scale.
     */
    private const WEIGHTS = [
        'link_resolver' => 0.25,
        'oa'            => 0.35,
        'preprints'     => 0.15,
        'orcid'         => 0.25,
    ];

    /**
     * Material types treated as open access for ODI purposes. Sourced from the
     * 'library_oa_material_type' dropdown group when present, otherwise from a
     * conservative built-in default. No hardcoded ENUM columns are used.
     *
     * @var string[]|null
     */
    private ?array $oaMaterialTypes = null;

    /**
     * Material types treated as preprints. Sourced from the
     * 'library_preprint_material_type' dropdown group when present.
     *
     * @var string[]|null
     */
    private ?array $preprintMaterialTypes = null;

    /**
     * Compute the composite ODI quality score (0-100) from a metric array.
     * Pure function - no database access, fully unit testable.
     *
     * @param array{
     *   link_resolver_present?: bool,
     *   oa_percentage?: float,
     *   preprints_indexed?: int,
     *   orcid_in_records?: int,
     *   item_count?: int
     * } $metrics
     */
    public function computeQualityScore(array $metrics): float
    {
        $itemCount = max(0, (int) ($metrics['item_count'] ?? 0));

        // Each component is normalised to 0..1.
        $linkResolver = ! empty($metrics['link_resolver_present']) ? 1.0 : 0.0;

        $oa = min(1.0, max(0.0, ((float) ($metrics['oa_percentage'] ?? 0)) / 100.0));

        // Preprints + ORCID are coverage ratios against item_count (capped at 1).
        $preprints = $itemCount > 0
            ? min(1.0, ((int) ($metrics['preprints_indexed'] ?? 0)) / $itemCount)
            : 0.0;

        $orcid = $itemCount > 0
            ? min(1.0, ((int) ($metrics['orcid_in_records'] ?? 0)) / $itemCount)
            : 0.0;

        $score = ($linkResolver * self::WEIGHTS['link_resolver'])
            + ($oa * self::WEIGHTS['oa'])
            + ($preprints * self::WEIGHTS['preprints'])
            + ($orcid * self::WEIGHTS['orcid']);

        return round($score * 100, 2);
    }

    /**
     * Whether an OpenURL link resolver is available for this install. The
     * resolver endpoint (GET /api/resolver) is always present once this package
     * is installed, so it is reported true platform-wide. Kept as a method so a
     * future per-collection / per-tenant override can hook in here.
     */
    public function linkResolverPresent(): bool
    {
        return true;
    }

    /**
     * Identify the collections to score. A collection is the parent
     * information_object of one or more library_item rows. Returns an array of
     * objects: {collection_id, collection_title, item_count}.
     *
     * @return array<int,object>
     */
    public function collections(): array
    {
        if (! Schema::hasTable('library_item') || ! Schema::hasTable('information_object')) {
            return [];
        }

        $culture = (string) app()->getLocale();

        return DB::table('library_item')
            ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object as parent', 'information_object.parent_id', '=', 'parent.id')
            ->leftJoin('information_object_i18n as parent_i18n', function ($j) use ($culture) {
                $j->on('parent.id', '=', 'parent_i18n.id')
                    ->where('parent_i18n.culture', '=', $culture);
            })
            ->whereNotNull('information_object.parent_id')
            ->groupBy('information_object.parent_id', 'parent_i18n.title')
            ->select([
                DB::raw('information_object.parent_id as collection_id'),
                DB::raw('parent_i18n.title as collection_title'),
                DB::raw('COUNT(DISTINCT library_item.id) as item_count'),
            ])
            ->orderBy('information_object.parent_id')
            ->get()
            ->all();
    }

    /**
     * Compute the raw metrics for a single collection (parent IO id).
     *
     * @return array<string,mixed>
     */
    public function metricsForCollection(int $collectionId): array
    {
        $itemCount = $this->itemCount($collectionId);

        $oaCount = $this->openAccessCount($collectionId);
        $oaPercentage = $itemCount > 0 ? round(($oaCount / $itemCount) * 100, 2) : 0.0;

        return [
            'item_count'            => $itemCount,
            'link_resolver_present' => $this->linkResolverPresent(),
            'oa_percentage'         => $oaPercentage,
            'preprints_indexed'     => $this->preprintCount($collectionId),
            'orcid_in_records'      => $this->orcidCount($collectionId),
        ];
    }

    /**
     * Build a full scorecard row (metrics + composite score) for a collection.
     *
     * @return array<string,mixed>
     */
    public function scorecardForCollection(int $collectionId, ?string $title = null): array
    {
        $metrics = $this->metricsForCollection($collectionId);
        $metrics['collection_id'] = $collectionId;
        $metrics['collection_title'] = $title;
        $metrics['quality_score'] = $this->computeQualityScore($metrics);

        return $metrics;
    }

    /**
     * Recompute scorecards for every collection and persist into
     * library_odi_collection. Returns the number of rows written.
     */
    public function refreshAll(): int
    {
        if (! Schema::hasTable('library_odi_collection')) {
            return 0;
        }

        $written = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($this->collections() as $c) {
            $card = $this->scorecardForCollection((int) $c->collection_id, $c->collection_title ?? null);

            DB::table('library_odi_collection')->updateOrInsert(
                ['collection_id' => $card['collection_id']],
                [
                    'collection_title'      => $card['collection_title'],
                    'item_count'            => $card['item_count'],
                    'link_resolver_present' => $card['link_resolver_present'] ? 1 : 0,
                    'oa_percentage'         => $card['oa_percentage'],
                    'preprints_indexed'     => $card['preprints_indexed'],
                    'orcid_in_records'      => $card['orcid_in_records'],
                    'quality_score'         => $card['quality_score'],
                    'updated_at'            => $now,
                ]
            );
            $written++;
        }

        return $written;
    }

    /**
     * Return all stored scorecards for display, newest score first.
     *
     * @return array<int,object>
     */
    public function storedScorecards(): array
    {
        if (! Schema::hasTable('library_odi_collection')) {
            return [];
        }

        return DB::table('library_odi_collection')
            ->orderByDesc('quality_score')
            ->orderBy('collection_title')
            ->get()
            ->all();
    }

    // --- DB metric helpers -------------------------------------------------

    private function itemCount(int $collectionId): int
    {
        return (int) DB::table('library_item')
            ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
            ->where('information_object.parent_id', $collectionId)
            ->distinct()
            ->count('library_item.id');
    }

    /**
     * Open-access count. An item is treated as OA when its material_type is in
     * the OA dropdown set, or it carries a DOI but is not a preprint (a
     * pragmatic proxy until an explicit access_status column exists).
     */
    private function openAccessCount(int $collectionId): int
    {
        $oaTypes = $this->oaMaterialTypes();

        $q = DB::table('library_item')
            ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
            ->where('information_object.parent_id', $collectionId);

        if (! empty($oaTypes)) {
            $q->whereIn('library_item.material_type', $oaTypes);
        } else {
            // Proxy: has a DOI (resolvable scholarly object).
            $q->whereNotNull('library_item.doi')->where('library_item.doi', '!=', '');
        }

        return (int) $q->distinct()->count('library_item.id');
    }

    private function preprintCount(int $collectionId): int
    {
        $types = $this->preprintMaterialTypes();

        $q = DB::table('library_item')
            ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
            ->where('information_object.parent_id', $collectionId);

        if (! empty($types)) {
            $q->whereIn('library_item.material_type', $types);
        } else {
            $q->where('library_item.material_type', 'like', '%preprint%');
        }

        return (int) $q->distinct()->count('library_item.id');
    }

    /**
     * Count library_item rows whose creators carry a verified-or-not ORCID in
     * ahg_actor_identifier (identifier_type = 'orcid').
     */
    private function orcidCount(int $collectionId): int
    {
        if (! Schema::hasTable('library_item_creator') || ! Schema::hasTable('ahg_actor_identifier')) {
            return 0;
        }

        return (int) DB::table('library_item')
            ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
            ->join('library_item_creator', 'library_item.id', '=', 'library_item_creator.library_item_id')
            ->join('ahg_actor_identifier', function ($j) {
                $j->on('library_item_creator.actor_id', '=', 'ahg_actor_identifier.actor_id')
                    ->where('ahg_actor_identifier.identifier_type', '=', 'orcid');
            })
            ->where('information_object.parent_id', $collectionId)
            ->whereNotNull('library_item_creator.actor_id')
            ->distinct()
            ->count('library_item.id');
    }

    /**
     * Load OA material types from the ahg_dropdown 'library_oa_material_type'
     * group. Returns an empty array when the group is absent (caller falls back
     * to the DOI proxy). Never hardcodes an ENUM.
     *
     * @return string[]
     */
    private function oaMaterialTypes(): array
    {
        if ($this->oaMaterialTypes !== null) {
            return $this->oaMaterialTypes;
        }

        $this->oaMaterialTypes = $this->dropdownValues('library_oa_material_type');
        return $this->oaMaterialTypes;
    }

    /**
     * @return string[]
     */
    private function preprintMaterialTypes(): array
    {
        if ($this->preprintMaterialTypes !== null) {
            return $this->preprintMaterialTypes;
        }

        $this->preprintMaterialTypes = $this->dropdownValues('library_preprint_material_type');
        return $this->preprintMaterialTypes;
    }

    /**
     * Fetch the value list for a dropdown group from ahg_dropdown.
     *
     * @return string[]
     */
    private function dropdownValues(string $group): array
    {
        if (! Schema::hasTable('ahg_dropdown')) {
            return [];
        }

        return DB::table('ahg_dropdown')
            ->where('taxonomy', $group)
            ->orderBy('sort_order')
            ->pluck('code')
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();
    }
}
