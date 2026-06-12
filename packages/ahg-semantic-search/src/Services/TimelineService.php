<?php

/**
 * TimelineService - the read-only data layer behind the public "Collection
 * timeline" surface.
 *
 * The timeline shows the distribution of PUBLISHED records across time: how many
 * records each period (century, drilled to decade where the data is dense enough)
 * holds. It is an engaging "browse the holdings by period" entry point that links
 * each period straight into the canonical GLAM browse, filtered to that date
 * range.
 *
 *   - timeline()  - one cheap bounded GROUP BY aggregate that derives the year
 *                   from each PUBLISHED record's earliest event start_date and
 *                   buckets it by century, plus an honest "undated" bucket for
 *                   published records that carry no usable event year. Each
 *                   century optionally drills to its decades.
 *   - buckets()   - the same data flattened for the .json twin.
 *
 * It is STRICTLY read-only. It never writes, never ALTERs, never calls AI, and
 * adds no table - it is a single grouped aggregate VIEW over the existing event
 * table and the publication-status table, NOT a per-row PHP scan of the
 * catalogue. Every path is Schema::hasTable-guarded and wrapped so a missing
 * table degrades to an empty result rather than a 500.
 *
 * Date source (DESCRIBE-verified): the base catalogue stores a record's dates in
 * the `event` table - `event.start_date` / `event.end_date` are real DATE columns
 * and `event_i18n.date` holds the human display-date string. The bucket year is
 * derived from YEAR(MIN(event.start_date)) per object so a record with several
 * events (creation, accumulation, ...) is placed by its earliest dated event.
 * A published record with no event carrying a usable start_date year is reported,
 * never dropped, in the "undated" bucket.
 *
 * Published gate (mirrors the rest of Heratio): a record is "published" when its
 * row in the status table (type_id = 158) carries status_id = 160; the catalogue
 * root (id = 1) is never surfaced.
 *
 * International / jurisdiction-neutral: the only calendar assumption is the
 * Gregorian year already stored in the DATE columns. No era, no country history,
 * no per-market period names are hardcoded - a century is just "1900s", a decade
 * just "1910s", derived arithmetically from the year.
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

class TimelineService
{
    /** Catalogue root id, never counted as a real record. */
    protected const ROOT_ID = 1;

    /** Publication-status type_id and the "published" status_id in the status table. */
    protected const PUBLICATION_TYPE_ID = 158;

    protected const PUBLISHED_STATUS_ID = 160;

    /**
     * A century with at least this many records is offered a decade drill-down.
     * Below it, the decade detail would be noise, so the century bar stands alone.
     */
    public const DECADE_DRILL_THRESHOLD = 12;

    /**
     * Sanity bounds on a derived year. The DATE column can hold odd values
     * (0000, far-future typos); clamp so a single bad row cannot create a
     * thousand empty century buckets. These are arithmetic guards, NOT a
     * jurisdiction or era assumption.
     */
    public const MIN_YEAR = -4000;

    public const MAX_YEAR = 2100;

    /**
     * Are the tables this surface needs present? Every path gates on this so a
     * fresh (un-booted) install renders the empty-state rather than fataling.
     */
    public function available(): bool
    {
        try {
            return Schema::hasTable('event')
                && Schema::hasTable('status');
        } catch (\Throwable $e) {
            Log::info('[timeline] table probe failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * The full timeline: an ordered list of century buckets (each with its
     * record count, year range, browse deep-link and - where dense enough - a
     * decade drill-down), followed by an honest "undated" bucket for published
     * records that carry no usable event year.
     *
     * One cheap bounded aggregate derives YEAR(MIN(event.start_date)) per
     * published object and groups by the derived century. A second, equally cheap
     * aggregate groups the same data by decade for the drill-down. A third small
     * COUNT covers the undated published records. NONE of this is a per-row PHP
     * scan - it is three grouped SQL aggregates. Read-only; never throws -
     * degrades to a structure with empty buckets.
     *
     * @return array{
     *     centuries: array<int,array<string,mixed>>,
     *     undated: array{count:int},
     *     dated_total: int,
     *     max_count: int,
     *     min_year: ?int,
     *     max_year: ?int
     * }
     */
    public function timeline(): array
    {
        $empty = [
            'centuries' => [],
            'undated' => ['count' => 0],
            'dated_total' => 0,
            'max_count' => 0,
            'min_year' => null,
            'max_year' => null,
        ];

        if (! $this->available()) {
            return $empty;
        }

        // 1) Century aggregate: derive the earliest dated year per published
        //    object, bucket it by century, count distinct objects per century.
        $centuryRows = $this->aggregateByYearBucket('century');
        if ($centuryRows === null) {
            return $empty;
        }

        // 2) Decade aggregate (same derivation, finer bucket) so dense centuries
        //    can drill down without a second per-row pass.
        $decadeRows = $this->aggregateByYearBucket('decade') ?? collect();
        $decadesByCentury = [];
        foreach ($decadeRows as $r) {
            $decadeStart = (int) $r->bucket_year;
            $count = (int) $r->record_count;
            $centuryStart = $this->centuryStart($decadeStart);
            $decadesByCentury[$centuryStart][] = [
                'decade_start' => $decadeStart,
                'count' => $count,
            ];
        }

        // 3) Undated published records (no usable event year). Cheap COUNT DISTINCT.
        $undatedCount = $this->undatedPublishedCount();

        $centuries = [];
        $datedTotal = 0;
        $maxCount = 0;
        $minYear = null;
        $maxYear = null;

        foreach ($centuryRows as $row) {
            $centuryStart = (int) $row->bucket_year;
            $count = (int) $row->record_count;
            if ($count <= 0) {
                continue;
            }

            $datedTotal += $count;
            $maxCount = max($maxCount, $count);

            $fromYear = $centuryStart;
            $toYear = $centuryStart + 99;
            $minYear = $minYear === null ? $fromYear : min($minYear, $fromYear);
            $maxYear = $maxYear === null ? $toYear : max($maxYear, $toYear);

            $decades = [];
            if ($count >= self::DECADE_DRILL_THRESHOLD && ! empty($decadesByCentury[$centuryStart])) {
                $raw = $decadesByCentury[$centuryStart];
                usort($raw, fn ($a, $b) => $a['decade_start'] <=> $b['decade_start']);
                foreach ($raw as $d) {
                    $dStart = (int) $d['decade_start'];
                    $dCount = (int) $d['count'];
                    if ($dCount <= 0) {
                        continue;
                    }
                    $decades[] = [
                        'label' => $this->decadeLabel($dStart),
                        'from_year' => $dStart,
                        'to_year' => $dStart + 9,
                        'count' => $dCount,
                        'browse_url' => $this->browseUrl($dStart, $dStart + 9),
                    ];
                }
            }

            $centuries[] = [
                'label' => $this->centuryLabel($centuryStart),
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'count' => $count,
                'browse_url' => $this->browseUrl($fromYear, $toYear),
                'decades' => $decades,
            ];
        }

        return [
            'centuries' => $centuries,
            'undated' => ['count' => $undatedCount],
            'dated_total' => $datedTotal,
            'max_count' => max($maxCount, $undatedCount),
            'min_year' => $minYear,
            'max_year' => $maxYear,
        ];
    }

    /**
     * The same buckets, flattened, for the machine-readable .json twin: each row
     * is {period_label, from_year, to_year, count, browse_url}. Centuries first
     * (chronological), each followed by its decade rows where present, then the
     * undated group (from_year/to_year null, browse_url null). Read-only; never
     * throws.
     *
     * @return array<int,array{period_label:string,from_year:?int,to_year:?int,count:int,browse_url:?string}>
     */
    public function buckets(): array
    {
        $timeline = $this->timeline();
        $out = [];

        foreach ($timeline['centuries'] as $century) {
            $out[] = [
                'period_label' => $century['label'],
                'from_year' => $century['from_year'],
                'to_year' => $century['to_year'],
                'count' => $century['count'],
                'browse_url' => $century['browse_url'],
            ];
            foreach ($century['decades'] as $decade) {
                $out[] = [
                    'period_label' => $decade['label'],
                    'from_year' => $decade['from_year'],
                    'to_year' => $decade['to_year'],
                    'count' => $decade['count'],
                    'browse_url' => $decade['browse_url'],
                ];
            }
        }

        if ($timeline['undated']['count'] > 0) {
            $out[] = [
                'period_label' => 'Undated',
                'from_year' => null,
                'to_year' => null,
                'count' => $timeline['undated']['count'],
                // No date range, so no honest browse deep-link - never a dead link.
                'browse_url' => null,
            ];
        }

        return $out;
    }

    /**
     * One bounded grouped aggregate: per published object, derive the earliest
     * dated year (YEAR(MIN(event.start_date))), floor it to a century or decade
     * boundary, and COUNT the objects in each bucket. The publication gate
     * (status type 158 / status 160) and the root exclusion are applied in SQL;
     * only rows with a usable, in-range year survive (undated rows are counted
     * separately by undatedPublishedCount()). This is a single GROUP BY over a
     * per-object derived subquery, NOT a per-row PHP loop over the catalogue.
     * Returns null on failure so the caller can render the empty-state; never
     * throws.
     *
     * @param  string  $grain  'century' or 'decade'
     * @return \Illuminate\Support\Collection<int,object>|null
     */
    protected function aggregateByYearBucket(string $grain)
    {
        $divisor = $grain === 'decade' ? 10 : 100;

        try {
            // Per-object earliest dated year, gated to published non-root records.
            // event.start_date is a real DATE column (DESCRIBE-verified), so
            // YEAR() is a cheap derivation. NULL start_date rows are excluded here
            // and fall through to the undated count.
            $perObject = DB::table('event as e')
                ->whereNotNull('e.object_id')
                ->whereRaw('e.object_id <> ?', [self::ROOT_ID])
                ->whereNotNull('e.start_date')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('status')
                        ->whereRaw('status.object_id = e.object_id')
                        ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                        ->where('status.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->groupBy('e.object_id')
                ->select(
                    'e.object_id',
                    DB::raw('YEAR(MIN(e.start_date)) AS yr')
                );

            // Wrap the per-object derivation and bucket it. FLOOR(yr/divisor)*divisor
            // gives the period start (e.g. 1923 -> 1900 for century, 1920 for decade).
            $rows = DB::query()
                ->fromSub($perObject, 'o')
                ->whereNotNull('o.yr')
                ->whereBetween('o.yr', [self::MIN_YEAR, self::MAX_YEAR])
                ->where('o.yr', '<>', 0)
                ->groupByRaw('FLOOR(o.yr / '.$divisor.') * '.$divisor)
                ->select(
                    DB::raw('FLOOR(o.yr / '.$divisor.') * '.$divisor.' AS bucket_year'),
                    DB::raw('COUNT(*) AS record_count')
                )
                ->orderBy('bucket_year')
                ->get();

            return $rows;
        } catch (\Throwable $e) {
            Log::info('[timeline] '.$grain.' aggregate failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Count of PUBLISHED, non-root records that have NO usable event year: either
     * no event row at all, or every event row carries a NULL / out-of-range
     * start_date. Reported honestly as the "undated" bucket, never silently
     * dropped. Two cheap bounded COUNT DISTINCT queries (all published minus those
     * with a dated event). Read-only; never throws - degrades to 0.
     */
    protected function undatedPublishedCount(): int
    {
        try {
            // All published non-root records.
            $publishedTotal = (int) DB::table('status')
                ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                ->where('status.status_id', self::PUBLISHED_STATUS_ID)
                ->whereNotNull('status.object_id')
                ->whereRaw('status.object_id <> ?', [self::ROOT_ID])
                ->distinct()
                ->count('status.object_id');

            if ($publishedTotal <= 0) {
                return 0;
            }

            // Published non-root records that DO have a usable dated event year.
            $datedTotal = (int) DB::table('event as e')
                ->whereNotNull('e.object_id')
                ->whereRaw('e.object_id <> ?', [self::ROOT_ID])
                ->whereNotNull('e.start_date')
                ->whereRaw('YEAR(e.start_date) BETWEEN ? AND ?', [self::MIN_YEAR, self::MAX_YEAR])
                ->whereRaw('YEAR(e.start_date) <> 0')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('status')
                        ->whereRaw('status.object_id = e.object_id')
                        ->where('status.type_id', self::PUBLICATION_TYPE_ID)
                        ->where('status.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->distinct()
                ->count('e.object_id');

            $undated = $publishedTotal - $datedTotal;

            return $undated > 0 ? $undated : 0;
        } catch (\Throwable $e) {
            Log::info('[timeline] undated count failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Floor a year to the start of its century (1923 -> 1900, -44 -> -100).
     */
    protected function centuryStart(int $year): int
    {
        return (int) (floor($year / 100) * 100);
    }

    /**
     * A jurisdiction-neutral century label derived purely from the year. "1900s"
     * for 1900..1999. Negative years use a calendar-neutral "before year 0"
     * suffix rather than any era abbreviation, so no market's calendar convention
     * is assumed beyond the Gregorian year already in the data.
     */
    protected function centuryLabel(int $centuryStart): string
    {
        if ($centuryStart < 0) {
            return abs($centuryStart).'s '.__('before year 0');
        }

        return $centuryStart.'s';
    }

    /**
     * A jurisdiction-neutral decade label derived purely from the year. "1910s"
     * for 1910..1919.
     */
    protected function decadeLabel(int $decadeStart): string
    {
        if ($decadeStart < 0) {
            return abs($decadeStart).'s '.__('before year 0');
        }

        return $decadeStart.'s';
    }

    /**
     * The GLAM-browse deep link for one period - reuses the single canonical
     * browse page (ahg-display) with its real `startDate` / `endDate` filter
     * params (full ISO date strings compared against event.start_date /
     * event.end_date), so "browse all records in this period" lands in the same
     * place as the advanced-search date facet. url()-relative, never a hardcoded
     * host. Negative (pre-year-0) years are not expressible as an ISO date string
     * the browse filter accepts, so those periods get NO link rather than a dead
     * one (callers render the bar without a link).
     */
    public function browseUrl(int $fromYear, int $toYear): ?string
    {
        if ($fromYear < 0 || $toYear < 0) {
            return null;
        }

        $from = sprintf('%04d-01-01', $fromYear);
        $to = sprintf('%04d-12-31', $toYear);

        return url('/glam/browse?'.http_build_query([
            'startDate' => $from,
            'endDate' => $to,
            'rangeType' => 'inclusive',
        ]));
    }
}
