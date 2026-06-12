<?php

/**
 * DataQualityController - Controller for Heratio
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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Collection Data-Quality report - a read-only, archivist-facing dashboard of
 * ISAD(G) descriptive completeness across the PUBLISHED catalogue.
 *
 * ISAD(G) (General International Standard Archival Description) is the
 * international descriptive standard for archival material. This report measures
 * how completely the published descriptions populate the core ISAD(G) elements
 * so a cataloguer can see, at a glance, what is missing and where to spend
 * effort. It is jurisdiction-neutral: it asserts no single country's cataloguing
 * rule, only the international standard's element set.
 *
 * The report owns NO write path and NO standard-specific logic beyond the
 * element-presence checks below. Every metric is a single grouped/aggregate
 * COUNT or a LEFT JOIN ... IS NULL existence check over existing tables - never
 * a per-row scan of the catalogue in PHP. Every probe is Schema::hasTable-guarded
 * and wrapped in try/catch, so a fresh install or a missing table degrades to a
 * calm empty state and never 500s.
 *
 * The denominator is the PUBLISHED real-record universe:
 *   - information_object rows beneath the synthetic root (parent_id != ROOT_ID),
 *   - carrying a publication-status row set to Published
 *     (status.type_id = STATUS_TYPE_PUBLICATION, status.status_id = PUBLISHED).
 *
 * Where a browse filter can target a gap (e.g. "show me the records with no
 * creator"), the card links to it - but ONLY via a Route::has-gated route() and
 * ONLY when such a filter actually exists. No filter exists for these element
 * gaps today, so the cards show the number alone with no dead link; the moment a
 * gap-filter route is registered, wiring it here is a one-line addition.
 */
class DataQualityController extends Controller
{
    /** Synthetic root information_object id; real records sit beneath it. */
    private const ROOT_ID = 1;

    /** status.type_id for the publication-status taxonomy. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id for "Published". */
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** event.type_id for a creation event (links an actor + date to a record). */
    private const EVENT_TYPE_CREATION = 111;

    public function index(): View
    {
        $report = $this->buildReport();

        return view('ahg-reports::data-quality.index', $report);
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
            'available' => false,
            'total'     => 0,
            'elements'  => [],
            'score'     => 0.0,
            'present'   => 0,
            'top_gaps'  => [],
        ];

        try {
            if (! Schema::hasTable('information_object')) {
                return $empty;
            }

            $total = $this->publishedTotal();

            if ($total <= 0) {
                // Nothing published to measure yet. Calm empty state.
                return array_merge($empty, ['available' => true]);
            }

            $elements = $this->measureElements($total);

            // Overall completeness: share of published records carrying ALL the
            // core ISAD(G) elements at once (a single LEFT JOIN ... IS NULL
            // existence query, not a per-row PHP scan).
            $present = $this->coreCompleteCount();
            $score   = $this->pct($present, $total);

            // Top gaps: the elements ranked by how many records are missing them.
            $topGaps = $elements;
            usort($topGaps, static fn ($a, $b) => $b['missing'] <=> $a['missing']);
            $topGaps = array_values(array_filter(
                array_slice($topGaps, 0, 3),
                static fn ($e) => $e['missing'] > 0
            ));

            return [
                'available' => true,
                'total'     => $total,
                'elements'  => $elements,
                'score'     => $score,
                'present'   => $present,
                'top_gaps'  => $topGaps,
            ];
        } catch (\Throwable $e) {
            // Absent column, missing table, locked table, driver error - none of
            // these should ever break the report. Degrade to empty state.
            return $empty;
        }
    }

    /**
     * Total published real records (the denominator for every share below).
     */
    private function publishedTotal(): int
    {
        return (int) $this->publishedBase()->count();
    }

    /**
     * One element-completeness row per ISAD(G) element we measure. Each "missing"
     * count is a bounded aggregate / existence query against the published base.
     *
     * @param  int  $total
     * @return array<int,array<string,mixed>>
     */
    private function measureElements(int $total): array
    {
        $defs = [
            [
                'key'   => 'title',
                'label' => 'Title',
                'isad'  => '3.1.2',
                'desc'  => 'A name for the unit of description.',
                'icon'  => 'type',
            ],
            [
                'key'   => 'reference',
                'label' => 'Reference code / identifier',
                'isad'  => '3.1.1',
                'desc'  => 'A unique reference code or identifier for the record.',
                'icon'  => 'hash',
            ],
            [
                'key'   => 'date',
                'label' => 'Date(s)',
                'isad'  => '3.1.3',
                'desc'  => 'A date or date range for the unit of description, recorded as a linked event.',
                'icon'  => 'calendar-event',
            ],
            [
                'key'   => 'creator',
                'label' => 'Creator',
                'isad'  => '3.2.1',
                'desc'  => 'The body or person responsible for creating the records, recorded as a linked creation event with an actor.',
                'icon'  => 'person',
            ],
            [
                'key'   => 'scope',
                'label' => 'Scope and content',
                'isad'  => '3.3.1',
                'desc'  => 'A summary of the scope and content of the unit of description.',
                'icon'  => 'text-paragraph',
            ],
            [
                'key'   => 'extent',
                'label' => 'Extent and medium',
                'isad'  => '3.1.5',
                'desc'  => 'The physical or logical extent and the medium of the unit of description.',
                'icon'  => 'rulers',
            ],
            [
                'key'   => 'level',
                'label' => 'Level of description',
                'isad'  => '3.1.4',
                'desc'  => 'The level of arrangement of the unit of description (fonds, series, file, item).',
                'icon'  => 'diagram-3',
            ],
            [
                'key'   => 'repository',
                'label' => 'Repository',
                'isad'  => '3.4 / 3.4.x',
                'desc'  => 'The repository that holds the unit of description.',
                'icon'  => 'building',
            ],
        ];

        $out = [];
        foreach ($defs as $def) {
            $missing = $this->missingCount($def['key']);
            $present = max(0, $total - $missing);

            $out[] = [
                'key'         => $def['key'],
                'label'       => $def['label'],
                'isad'        => $def['isad'],
                'desc'        => $def['desc'],
                'icon'        => $def['icon'],
                'missing'     => $missing,
                'present'     => $present,
                'missing_pct' => $this->pct($missing, $total),
                'present_pct' => $this->pct($present, $total),
                // A gap-targeting browse filter would be wired here, Route::has
                // gated, IF one existed. None does today, so no link is offered
                // (no dead links, no fabricated filter).
                'filter_url'  => $this->gapFilterUrl($def['key']),
            ];
        }

        return $out;
    }

    /**
     * Count published records MISSING a given ISAD(G) element. Each branch is a
     * bounded aggregate: either a WHERE on a nullable/empty column or a
     * NOT EXISTS existence check. No catalogue-wide PHP scan.
     */
    private function missingCount(string $key): int
    {
        switch ($key) {
            case 'title':
                // No title row, or a blank/empty title in the source culture.
                return (int) $this->publishedBase()
                    ->leftJoin('information_object_i18n as ioi', function ($j) {
                        $j->on('ioi.id', '=', 'io.id')
                          ->on('ioi.culture', '=', 'io.source_culture');
                    })
                    ->where(function ($q) {
                        $q->whereNull('ioi.title')
                          ->orWhereRaw("TRIM(ioi.title) = ''");
                    })
                    ->count();

            case 'reference':
                // No identifier / reference code on the record.
                return (int) $this->publishedBase()
                    ->where(function ($q) {
                        $q->whereNull('io.identifier')
                          ->orWhereRaw("TRIM(io.identifier) = ''");
                    })
                    ->count();

            case 'date':
                // No linked event carrying any date (structured start/end date
                // or a free-text event_i18n.date).
                return (int) $this->publishedBase()
                    ->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                          ->from('event as ev')
                          ->leftJoin('event_i18n as evi', 'evi.id', '=', 'ev.id')
                          ->whereColumn('ev.object_id', 'io.id')
                          ->where(function ($w) {
                              $w->whereNotNull('ev.start_date')
                                ->orWhereNotNull('ev.end_date')
                                ->orWhereRaw("TRIM(COALESCE(evi.date, '')) <> ''");
                          });
                    })
                    ->count();

            case 'creator':
                // No creation event linking an actor to the record.
                return (int) $this->publishedBase()
                    ->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                          ->from('event as ev')
                          ->whereColumn('ev.object_id', 'io.id')
                          ->where('ev.type_id', self::EVENT_TYPE_CREATION)
                          ->whereNotNull('ev.actor_id');
                    })
                    ->count();

            case 'scope':
                return (int) $this->publishedBase()
                    ->leftJoin('information_object_i18n as ioi', function ($j) {
                        $j->on('ioi.id', '=', 'io.id')
                          ->on('ioi.culture', '=', 'io.source_culture');
                    })
                    ->where(function ($q) {
                        $q->whereNull('ioi.scope_and_content')
                          ->orWhereRaw("TRIM(ioi.scope_and_content) = ''");
                    })
                    ->count();

            case 'extent':
                return (int) $this->publishedBase()
                    ->leftJoin('information_object_i18n as ioi', function ($j) {
                        $j->on('ioi.id', '=', 'io.id')
                          ->on('ioi.culture', '=', 'io.source_culture');
                    })
                    ->where(function ($q) {
                        $q->whereNull('ioi.extent_and_medium')
                          ->orWhereRaw("TRIM(ioi.extent_and_medium) = ''");
                    })
                    ->count();

            case 'level':
                return (int) $this->publishedBase()
                    ->whereNull('io.level_of_description_id')
                    ->count();

            case 'repository':
                return (int) $this->publishedBase()
                    ->whereNull('io.repository_id')
                    ->count();
        }

        return 0;
    }

    /**
     * Count published records carrying EVERY core ISAD(G) element at once. A
     * single bounded query (one i18n join + two NOT EXISTS sub-selects), not a
     * per-row PHP loop. Drives the headline completeness gauge.
     */
    private function coreCompleteCount(): int
    {
        return (int) $this->publishedBase()
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'io.id')
                  ->on('ioi.culture', '=', 'io.source_culture');
            })
            // Title present
            ->whereNotNull('ioi.title')
            ->whereRaw("TRIM(ioi.title) <> ''")
            // Reference code present
            ->whereNotNull('io.identifier')
            ->whereRaw("TRIM(io.identifier) <> ''")
            // Scope and content present
            ->whereNotNull('ioi.scope_and_content')
            ->whereRaw("TRIM(ioi.scope_and_content) <> ''")
            // Extent and medium present
            ->whereNotNull('ioi.extent_and_medium')
            ->whereRaw("TRIM(ioi.extent_and_medium) <> ''")
            // Level of description present
            ->whereNotNull('io.level_of_description_id')
            // Repository present
            ->whereNotNull('io.repository_id')
            // A creator (creation event with an actor) present
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('event as ev')
                  ->whereColumn('ev.object_id', 'io.id')
                  ->where('ev.type_id', self::EVENT_TYPE_CREATION)
                  ->whereNotNull('ev.actor_id');
            })
            // A date (any linked event date) present
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('event as ev')
                  ->leftJoin('event_i18n as evi', 'evi.id', '=', 'ev.id')
                  ->whereColumn('ev.object_id', 'io.id')
                  ->where(function ($w) {
                      $w->whereNotNull('ev.start_date')
                        ->orWhereNotNull('ev.end_date')
                        ->orWhereRaw("TRIM(COALESCE(evi.date, '')) <> ''");
                  });
            })
            ->count();
    }

    /**
     * The published real-record base query, aliased "io". Every metric builds on
     * a fresh copy so filters never leak between counts.
     */
    private function publishedBase(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->where('io.id', '!=', self::ROOT_ID)
            ->where('io.parent_id', '!=', self::ROOT_ID)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('status as st')
                  ->whereColumn('st.object_id', 'io.id')
                  ->where('st.type_id', self::STATUS_TYPE_PUBLICATION)
                  ->where('st.status_id', self::PUBLICATION_STATUS_PUBLISHED);
            });
    }

    /**
     * A gap-targeting browse-filter URL for a given element, but ONLY when such
     * a named route actually exists on this install (Route::has gated). No such
     * filter route exists today, so this returns null and the card shows the
     * count alone - no dead link, no fabricated filter. The lookup table below
     * is the single place to wire a real filter route if one is added later.
     */
    private function gapFilterUrl(string $key): ?string
    {
        // Map element key -> [routeName, queryParams]. Empty today by design:
        // the GLAM browse has no "records missing element X" filter to target.
        $filters = [];

        if (! isset($filters[$key])) {
            return null;
        }

        [$routeName, $params] = $filters[$key];

        try {
            if (Route::has($routeName)) {
                return route($routeName, $params);
            }
        } catch (\Throwable $e) {
            // Never let link generation break the report.
        }

        return null;
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
