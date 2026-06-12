<?php

/**
 * AiUsageController - Controller for Heratio
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
 * AI Usage transparency report - a read-only, operator-facing aggregate of how
 * much AI has assisted with the catalogue: which inference types ran, which
 * models produced them, the trend over time, and how much of that AI output a
 * human has since reviewed or corrected.
 *
 * The point of this report is ACCOUNTABILITY, framed honestly. AI here is an
 * assistant that proposes metadata; a person remains responsible for the record.
 * Nothing on this page implies that AI decides anything. The headline oversight
 * metric is deliberately about the human review share, not about AI accuracy.
 *
 * It reads two existing provenance tables (and writes to neither):
 *
 *   - ahg_ai_inference  - the inference log. One row per AI inference applied to
 *     a record. Real, DESCRIBE-verified columns this report uses:
 *       service_name        the inference type / task (NER, SUMMARIZE, HTR,
 *                           TRANSLATION, DONUT, LLM, ...) - the type breakdown
 *       model_name          the model that produced it - the model breakdown
 *       endpoint            the gateway / endpoint URL (nullable) - gateway hint
 *       target_entity_type  +
 *       target_entity_id    together identify the touched record (distinct count)
 *       created_at          drives the per-month trend
 *       id                  the FK that ahg_ai_override.inference_id references
 *
 *   - ahg_ai_override   - the human review / correction log. One row per human
 *     review of an inference. Real, DESCRIBE-verified columns this report uses:
 *       inference_id        FK back to ahg_ai_inference.id (the reviewed share)
 *
 * Every metric is a single grouped/aggregate COUNT (or a COUNT over a DISTINCT
 * inference_id existence join) - never a per-row PHP scan of the log. Every probe
 * is Schema::hasTable-guarded and wrapped in try/catch, so a fresh install with no
 * AI activity, or a missing table, degrades to a calm empty state and never 500s.
 */
class AiUsageController extends Controller
{
    /** The inference log table. */
    private const T_INFERENCE = 'ahg_ai_inference';

    /** The human review / override log table. */
    private const T_OVERRIDE = 'ahg_ai_override';

    /** How many trailing months the over-time trend spans. */
    private const TREND_MONTHS = 12;

    public function index(): View
    {
        return view('ahg-reports::ai-usage.index', $this->buildReport());
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
            'available'       => false,
            'total'           => 0,
            'records_touched' => 0,
            'reviewed'        => 0,
            'reviewed_pct'    => 0.0,
            'by_type'         => [],
            'by_model'        => [],
            'trend'           => [],
            'trend_max'       => 0,
        ];

        try {
            if (! Schema::hasTable(self::T_INFERENCE)) {
                return $empty;
            }

            $total = $this->inferenceTotal();

            if ($total <= 0) {
                // Table exists but no AI has been recorded yet. Calm empty state,
                // but flagged available so the page frames it as "none recorded".
                return array_merge($empty, ['available' => true]);
            }

            $recordsTouched = $this->distinctRecordsTouched();
            $reviewed       = $this->reviewedCount();

            $byType  = $this->breakdown('service_name', $total);
            $byModel = $this->breakdown('model_name', $total);
            $trend   = $this->monthlyTrend();

            return [
                'available'       => true,
                'total'           => $total,
                'records_touched' => $recordsTouched,
                'reviewed'        => $reviewed,
                'reviewed_pct'    => $this->pct($reviewed, $total),
                'by_type'         => $byType,
                'by_model'        => $byModel,
                'trend'           => $trend,
                'trend_max'       => empty($trend) ? 0 : max(array_column($trend, 'count')),
            ];
        } catch (\Throwable $e) {
            // Absent column, missing table, locked table, driver error - none of
            // these should ever break the report. Degrade to empty state.
            return $empty;
        }
    }

    /** Total inferences logged (the denominator for every share below). */
    private function inferenceTotal(): int
    {
        return (int) DB::table(self::T_INFERENCE)->count();
    }

    /**
     * Distinct catalogue records touched by at least one AI inference. A record
     * is identified by the (target_entity_type, target_entity_id) pair, so the
     * same id under two entity types counts as two distinct targets. A single
     * COUNT(DISTINCT ...), not a per-row scan.
     */
    private function distinctRecordsTouched(): int
    {
        return (int) DB::table(self::T_INFERENCE)
            ->select(DB::raw('COUNT(DISTINCT target_entity_type, target_entity_id) as c'))
            ->value('c');
    }

    /**
     * Inferences that carry at least one human review / override. Counts the
     * DISTINCT inference rows referenced by the override log (so two corrections
     * to one inference still count as one reviewed inference). A single bounded
     * aggregate over an existence join, not a per-row scan. Returns 0 if the
     * override table is absent.
     */
    private function reviewedCount(): int
    {
        if (! Schema::hasTable(self::T_OVERRIDE)) {
            return 0;
        }

        return (int) DB::table(self::T_INFERENCE . ' as inf')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from(self::T_OVERRIDE . ' as ovr')
                  ->whereColumn('ovr.inference_id', 'inf.id');
            })
            ->count();
    }

    /**
     * A grouped count breakdown over one column of the inference log (the type
     * column service_name, or the model column model_name). One GROUP BY ...
     * COUNT(*) query, ordered most-used first. Blank/NULL group keys are folded
     * into a single "(unspecified)" bucket. Each row carries its share of the
     * total and a presentational endpoint hint where it is cheap to derive.
     *
     * @return array<int,array<string,mixed>>
     */
    private function breakdown(string $column, int $total): array
    {
        $rows = DB::table(self::T_INFERENCE)
            ->select($column . ' as label', DB::raw('COUNT(*) as count'))
            ->groupBy($column)
            ->orderByDesc('count')
            ->orderBy('label')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row->label ?? ''));
            $count = (int) $row->count;

            $out[] = [
                'label'   => $label !== '' ? $label : __('(unspecified)'),
                'count'   => $count,
                'pct'     => $this->pct($count, $total),
                'gateway' => $column === 'service_name' ? $this->gatewayHint($row->label) : null,
            ];
        }

        return $out;
    }

    /**
     * A cheap, presentational gateway hint for a service type: the most common
     * non-empty endpoint recorded against that service, reduced to its host. The
     * endpoint column is nullable, so this is best-effort and returns null when
     * nothing useful is recorded. One bounded aggregate per service type.
     */
    private function gatewayHint(?string $service): ?string
    {
        if ($service === null || $service === '') {
            return null;
        }

        try {
            $endpoint = DB::table(self::T_INFERENCE)
                ->where('service_name', $service)
                ->whereNotNull('endpoint')
                ->whereRaw("TRIM(endpoint) <> ''")
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->groupBy('endpoint')
                ->value('endpoint');

            if (! $endpoint) {
                return null;
            }

            $host = parse_url((string) $endpoint, PHP_URL_HOST);

            return $host ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Inferences per calendar month for the trailing window, oldest first. A
     * single GROUP BY month aggregate over created_at; months with no activity
     * are back-filled with a zero so the CSS bar chart has an even axis. No
     * per-row PHP scan.
     *
     * @return array<int,array<string,mixed>>
     */
    private function monthlyTrend(): array
    {
        $since = now()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);

        $rows = DB::table(self::T_INFERENCE)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $since->format('Y-m-d 00:00:00'))
            ->groupBy('ym')
            ->pluck('count', 'ym');

        $trend  = [];
        $cursor = $since->copy();
        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $key = $cursor->format('Y-m');
            $trend[] = [
                'ym'    => $key,
                'label' => $cursor->format('M Y'),
                'count' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addMonth();
        }

        return $trend;
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
