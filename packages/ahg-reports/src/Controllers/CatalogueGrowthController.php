<?php

/**
 * CatalogueGrowthController - Controller for Heratio
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



namespace AhgReports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Catalogue Growth report - a read-only, management-facing view of how the
 * catalogue has grown and how it is composed. It answers the questions a manager
 * asks: how big is the catalogue, how much of it is published, how much carries a
 * digital surrogate, and - where the data allows - how fast it has been growing
 * month on month.
 *
 * This is deliberately DISTINCT from its two siblings shipped alongside it:
 *   - the data-quality report measures descriptive completeness (what is missing);
 *   - the AI-usage report measures where AI assisted;
 *   - THIS report measures size, growth and composition (how the catalogue grew).
 *
 * Honesty about the growth signal
 * --------------------------------
 * A "records created per month" time series is only shown when a real creation
 * timestamp exists. On this schema the information_object table itself carries NO
 * created_at / updated_at column (verified by DESCRIBE). The creation timestamp
 * lives on the Class-Table-Inheritance root table `object` (object.id =
 * information_object.id; for an archival description object.class_name is
 * 'QubitInformationObject'), which carries created_at / updated_at. Because the
 * series is built from the information_object base joined to object on id, the id
 * restriction alone already scopes it to archival descriptions. This controller
 * probes for that column at
 * runtime (Schema::hasColumn on `object.created_at`) and:
 *   - if present, renders a records-created-per-month time series from it;
 *   - if absent, OMITS the time series entirely and the view states plainly that
 *     creation timestamps are not recorded, showing current composition only.
 * There is NO publication-time signal on this schema (the `status` table has no
 * timestamp column), so no published-per-month series is ever fabricated; the
 * report says so. No date is ever invented.
 *
 * Every metric is a single grouped/aggregate COUNT (or an EXISTS existence check)
 * over existing tables - never a per-row PHP scan of the catalogue. Every probe is
 * Schema::hasTable / Schema::hasColumn guarded and wrapped in try/catch, so a fresh
 * install or a missing table degrades to a calm empty state and never 500s.
 *
 * The real-record universe is information_object rows that are not the synthetic
 * root and do not sit directly under it (id != ROOT_ID and parent_id != ROOT_ID),
 * matching the sibling reports. Published is the same publication-status signal:
 * status.type_id = STATUS_TYPE_PUBLICATION, status.status_id = PUBLISHED.
 */
class CatalogueGrowthController extends Controller
{
    /** Synthetic root information_object id; real records sit beneath it. */
    private const ROOT_ID = 1;

    /** Synthetic root actor id; real actors sit beneath it. */
    private const ROOT_ACTOR_ID = 3;

    /** status.type_id for the publication-status taxonomy. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id for "Published". */
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** How many trailing months the growth time series spans. */
    private const TREND_MONTHS = 12;

    /** How many repositories the composition list shows at most. */
    private const TOP_REPOSITORIES = 10;

    /** How many levels of description the composition list shows at most. */
    private const TOP_LEVELS = 10;

    public function index(): View
    {
        return view('ahg-reports::catalogue-growth.index', $this->buildReport());
    }

    /**
     * Assemble the whole report defensively. Any failure anywhere collapses to
     * the empty state rather than a 500.
     *
     * @return array<string,mixed>
     */
    private function buildReport(): array
    {
        $empty = [
            'available'        => false,
            'total'            => 0,
            'published'        => 0,
            'unpublished'      => 0,
            'published_pct'    => 0.0,
            'with_digital'     => 0,
            'with_digital_pct' => 0.0,
            'digital_objects'  => 0,
            'actors'           => 0,
            'repositories'     => 0,
            'has_timeline'     => false,
            'timeline'         => [],
            'timeline_max'     => 0,
            'by_level'         => [],
            'by_repository'    => [],
            'by_digital'       => [],
        ];

        try {
            if (! Schema::hasTable('information_object')) {
                return $empty;
            }

            $total = $this->realTotal();

            if ($total <= 0) {
                // No real records yet. Calm empty state, flagged available so the
                // page frames it as "nothing catalogued yet" rather than an error.
                return array_merge($empty, ['available' => true]);
            }

            $published   = $this->publishedTotal();
            $withDigital = $this->withDigitalCount();

            // Growth time series only when a real creation timestamp exists.
            $hasTimeline = $this->creationTimestampAvailable();
            $timeline    = $hasTimeline ? $this->monthlyCreated() : [];

            return [
                'available'        => true,
                'total'            => $total,
                'published'        => $published,
                'unpublished'      => max(0, $total - $published),
                'published_pct'    => $this->pct($published, $total),
                'with_digital'     => $withDigital,
                'with_digital_pct' => $this->pct($withDigital, $total),
                'digital_objects'  => $this->digitalObjectTotal(),
                'actors'           => $this->actorTotal(),
                'repositories'     => $this->repositoryTotal(),
                'has_timeline'     => $hasTimeline,
                'timeline'         => $timeline,
                'timeline_max'     => empty($timeline) ? 0 : max(array_column($timeline, 'count')),
                'by_level'         => $this->byLevel($total),
                'by_repository'    => $this->byRepository($total),
                'by_digital'       => $this->byDigital($total, $withDigital),
            ];
        } catch (\Throwable $e) {
            // Absent column, missing table, locked table, driver error - none of
            // these should ever break the report. Degrade to empty state.
            return $empty;
        }
    }

    /** Total real records (the denominator for the composition shares). */
    private function realTotal(): int
    {
        return (int) $this->realBase()->count();
    }

    /** Published real records. */
    private function publishedTotal(): int
    {
        return (int) $this->realBase()
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('status as st')
                  ->whereColumn('st.object_id', 'io.id')
                  ->where('st.type_id', self::STATUS_TYPE_PUBLICATION)
                  ->where('st.status_id', self::PUBLICATION_STATUS_PUBLISHED);
            })
            ->count();
    }

    /**
     * Real records carrying at least one digital object (a digital surrogate). A
     * single bounded EXISTS, not a per-row scan. Returns 0 if the table is absent.
     */
    private function withDigitalCount(): int
    {
        if (! Schema::hasTable('digital_object')) {
            return 0;
        }

        return (int) $this->realBase()
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('digital_object as dobj')
                  ->whereColumn('dobj.object_id', 'io.id');
            })
            ->count();
    }

    /** Total digital objects held across the whole system. */
    private function digitalObjectTotal(): int
    {
        if (! Schema::hasTable('digital_object')) {
            return 0;
        }

        return (int) DB::table('digital_object')->count();
    }

    /**
     * Total real actors (authority records), excluding the synthetic root actor.
     * The actor table is the Class-Table-Inheritance parent of repositories,
     * donors and users; this is the broad authority-record count.
     */
    private function actorTotal(): int
    {
        if (! Schema::hasTable('actor')) {
            return 0;
        }

        return (int) DB::table('actor')
            ->where('id', '!=', self::ROOT_ACTOR_ID)
            ->count();
    }

    /** Total repositories. */
    private function repositoryTotal(): int
    {
        if (! Schema::hasTable('repository')) {
            return 0;
        }

        return (int) DB::table('repository')->count();
    }

    /**
     * Whether a real creation timestamp is available for the time series. On this
     * schema information_object has no created_at, so the timestamp is read from
     * the Class-Table-Inheritance root table `object`. Both the table and the
     * column are probed; absent either, the time series is omitted (never faked).
     */
    private function creationTimestampAvailable(): bool
    {
        try {
            return Schema::hasTable('object')
                && Schema::hasColumn('object', 'created_at');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Records created per calendar month for the trailing window, oldest first. A
     * single GROUP BY month aggregate over object.created_at (joined to the real
     * information_object base on object.id = io.id); months with no creations are
     * back-filled with a zero so the CSS bar chart has an even axis. No per-row
     * PHP scan. Only ever called when creationTimestampAvailable() is true.
     *
     * @return array<int,array<string,mixed>>
     */
    private function monthlyCreated(): array
    {
        $since = now()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);

        $rows = $this->realBase()
            ->join('object as obj', 'obj.id', '=', 'io.id')
            ->whereNotNull('obj.created_at')
            ->where('obj.created_at', '>=', $since->format('Y-m-d 00:00:00'))
            ->selectRaw("DATE_FORMAT(obj.created_at, '%Y-%m') as ym, COUNT(*) as count")
            ->groupBy('ym')
            ->pluck('count', 'ym');

        $timeline = [];
        $cursor   = $since->copy();
        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $key = $cursor->format('Y-m');
            $timeline[] = [
                'ym'    => $key,
                'label' => $cursor->format('M Y'),
                'count' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addMonth();
        }

        return $timeline;
    }

    /**
     * Composition by level of description (fonds, series, file, item, ...). One
     * GROUP BY level_of_description_id with the term label resolved from term_i18n.
     * Records with no level are folded into a single "(no level)" bucket. Each row
     * carries its share of the total. Bounded aggregate, not a per-row scan.
     *
     * @return array<int,array<string,mixed>>
     */
    private function byLevel(int $total): array
    {
        $hasTerm = Schema::hasTable('term_i18n');

        $rows = $this->realBase()
            ->selectRaw('io.level_of_description_id as lid, COUNT(*) as count')
            ->groupBy('io.level_of_description_id')
            ->orderByDesc('count')
            ->limit(self::TOP_LEVELS + 1)
            ->get();

        // Resolve term labels in one batched lookup (a single pluck over the small
        // set of level ids actually present - no per-row queries in a loop).
        $labels = [];
        if ($hasTerm) {
            $ids = array_values(array_filter(
                $rows->pluck('lid')->all(),
                static fn ($v) => $v !== null
            ));
            if (! empty($ids)) {
                $labels = DB::table('term_i18n')
                    ->whereIn('id', $ids)
                    ->where('culture', 'en')
                    ->pluck('name', 'id')
                    ->all();
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $lid   = $row->lid;
            $count = (int) $row->count;

            if ($lid === null) {
                $label = __('(no level of description)');
            } else {
                $label = $labels[$lid] ?? ('#' . $lid);
            }

            $out[] = [
                'label' => $label,
                'count' => $count,
                'pct'   => $this->pct($count, $total),
            ];
        }

        // Most-used first; the no-level bucket sorts naturally by its own count.
        usort($out, static fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($out, 0, self::TOP_LEVELS);
    }

    /**
     * Composition by holding repository (top N). One GROUP BY repository_id with
     * the repository name resolved from actor_i18n (a repository is an actor under
     * Class-Table-Inheritance). Records with no repository are reported as a
     * separate "(no repository)" row appended after the top N. Bounded aggregate.
     *
     * @return array<int,array<string,mixed>>
     */
    private function byRepository(int $total): array
    {
        $hasActorI18n = Schema::hasTable('actor_i18n');

        // Top N named repositories by record count.
        $rows = $this->realBase()
            ->whereNotNull('io.repository_id')
            ->selectRaw('io.repository_id as rid, COUNT(*) as count')
            ->groupBy('io.repository_id')
            ->orderByDesc('count')
            ->limit(self::TOP_REPOSITORIES)
            ->get();

        $labels = [];
        if ($hasActorI18n) {
            $ids = $rows->pluck('rid')->all();
            if (! empty($ids)) {
                // authorized_form_of_name; take the first non-blank culture row
                // for each repository id (source culture is not guaranteed here).
                $labels = DB::table('actor_i18n')
                    ->whereIn('id', $ids)
                    ->select('id', 'authorized_form_of_name')
                    ->get()
                    ->groupBy('id')
                    ->map(function ($group) {
                        foreach ($group as $r) {
                            $name = trim((string) ($r->authorized_form_of_name ?? ''));
                            if ($name !== '') {
                                return $name;
                            }
                        }
                        return '';
                    })
                    ->all();
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $rid   = $row->rid;
            $count = (int) $row->count;
            $name  = $labels[$rid] ?? '';

            $out[] = [
                'label' => $name !== '' ? $name : (__('Repository') . ' #' . $rid),
                'count' => $count,
                'pct'   => $this->pct($count, $total),
            ];
        }

        // Records with no repository assigned, as an explicit closing row.
        $noRepo = (int) $this->realBase()
            ->whereNull('io.repository_id')
            ->count();

        if ($noRepo > 0) {
            $out[] = [
                'label'    => __('(no repository assigned)'),
                'count'    => $noRepo,
                'pct'      => $this->pct($noRepo, $total),
                'is_unset' => true,
            ];
        }

        return $out;
    }

    /**
     * Composition by digital-surrogate presence: how many records have a digital
     * object versus how many do not. Two counts, both shares of the total.
     *
     * @return array<int,array<string,mixed>>
     */
    private function byDigital(int $total, int $withDigital): array
    {
        $without = max(0, $total - $withDigital);

        return [
            [
                'label' => __('With a digital object'),
                'count' => $withDigital,
                'pct'   => $this->pct($withDigital, $total),
                'icon'  => 'image',
            ],
            [
                'label' => __('Without a digital object'),
                'count' => $without,
                'pct'   => $this->pct($without, $total),
                'icon'  => 'file-earmark',
            ],
        ];
    }

    /**
     * The real-record base query, aliased "io". Every metric builds on a fresh
     * copy so filters never leak between counts.
     */
    private function realBase(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->where('io.id', '!=', self::ROOT_ID)
            ->where('io.parent_id', '!=', self::ROOT_ID);
    }

    /** Percentage of $part over $whole, one decimal, guards divide-by-zero. */
    private function pct(int $part, int $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }

        return round($part / $whole * 100, 1);
    }
}
