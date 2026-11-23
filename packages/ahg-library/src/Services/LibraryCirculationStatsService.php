<?php

/**
 * LibraryCirculationStatsService - physical circulation statistics (#1476).
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

namespace AhgLibrary\Services;

use AhgLibrary\Support\LibraryBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Aggregates over library_checkout, library_hold and library_fine. Output is
 * counts and titles only: no patron is named or identifiable, so a report can
 * be exported or emailed without carrying personal data.
 *
 * Counting rules:
 *  - a loan belongs to the branch that issued it; stock to the branch holding it
 *  - renewals are counted against the loan's issue date, because renewal events
 *    are not logged individually (only library_checkout.renewed_count exists)
 *  - fines reach a branch through their checkout, so a fine with no checkout
 *    appears only in the all-branches view
 *  - money is never summed across currencies
 *  - holds fulfilled and holds awaiting collection are not reported: nothing
 *    writes fulfilled_date or closes a ready hold when the patron collects, so
 *    one would always read zero and the other would only ever grow
 *  - fine payments and waivers have no screen yet, so Paid and Waived appear
 *    only when stored rows carry them, with the limit stated
 *  - stock at a past date uses withdrawal_date; a copy marked withdrawn, lost
 *    or missing with no date is counted as held then, and the report says so
 */
class LibraryCirculationStatsService
{
    public const LIST_LIMIT = 25;

    /** Period codes resolvePeriod() can calculate. */
    public const PERIODS = ['monthly', 'quarterly', 'yearly', 'custom'];

    private const STOCK_NOTE = 'Copies now marked withdrawn, lost or missing with no recorded date are counted as held in past periods, because the date they left stock is unknown.';

    private const FINES_NOTE = 'Heratio does not yet record fine payments or waivers. Paid and Waived appear only when stored records carry them; otherwise Outstanding is the full amount assessed.';

    /** Copy statuses that are no longer part of the lendable stock. */
    private const OUT_OF_STOCK = ['withdrawn', 'lost', 'missing'];

    /** Borrowing bands for the patron activity distribution: [min, max]. */
    private const ACTIVITY_BANDS = [[1, 1], [2, 5], [6, 10], [11, null]];

    /**
     * Date range for a period code from the library_report_period dropdown.
     * The named periods are the last complete one, so a report never shows a
     * part-finished month as if it were comparable.
     *
     * @return array{0:string, 1:string} [from, to] as Y-m-d
     */
    public function resolvePeriod(string $code, ?string $from = null, ?string $to = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        switch ($code) {
            case 'monthly':
                $start = $now->startOfMonth()->subMonthNoOverflow();

                return [$start->toDateString(), $start->endOfMonth()->toDateString()];
            case 'quarterly':
                $start = $now->startOfQuarter()->subMonthsNoOverflow(3);

                return [$start->toDateString(), $start->endOfQuarter()->toDateString()];
            case 'yearly':
                $start = $now->startOfYear()->subYearNoOverflow();

                return [$start->toDateString(), $start->endOfYear()->toDateString()];
            case 'custom':
                if ($from === null || $to === null || $from > $to) {
                    throw new InvalidArgumentException('A custom period needs a start date on or before its end date.');
                }

                return [$from, $to];
        }

        throw new InvalidArgumentException("Unknown report period: {$code}");
    }

    /**
     * @param  int[]|null  $branches  branch ids to include; null means every branch
     *                                including activity not attributed to one
     */
    public function build(string $from, string $to, ?array $branches = null): array
    {
        $branches = $this->branchAxis() ? $branches : null;
        $end = CarbonImmutable::parse($to)->addDay()->toDateString();

        $issues = $this->loansIssued($from, $end, $branches)->count();
        $returns = $this->scoped(DB::table('library_checkout'), 'branch_id', $branches)
            ->where('return_date', '>=', $from)->where('return_date', '<', $end)->count();
        $renewedLoans = $this->loansIssued($from, $end, $branches)->where('renewed_count', '>', 0)->count();
        $renewals = (int) $this->loansIssued($from, $end, $branches)->sum('renewed_count');
        $late = $this->lateLoans($from, $end, $branches);
        $borrowers = $this->loansIssued($from, $end, $branches)->distinct()->count('patron_id');
        $copies = $this->stock($to, $branches)->count();
        $fines = $this->fines($from, $to, $branches);
        $undated = $this->stock($to, $branches)->whereIn('cp.status', self::OUT_OF_STOCK)
            ->whereNull('cp.withdrawal_date')->exists();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'branches' => $branches,
            'branch_label' => $this->branchLabel($branches),
            'summary' => [
                'issues' => $issues,
                'returns' => $returns,
                'renewals' => $renewals,
                'renewal_rate' => $this->ratio($renewedLoans, $issues),
                'late_loans' => $late,
                'overdue_rate' => $this->ratio($late, $issues),
                'active_borrowers' => $borrowers,
                'circulating_copies' => $copies,
                'turnover' => $copies > 0 ? round($issues / $copies, 2) : null,
            ] + $this->holdCounts($from, $end, $branches),
            'monthly' => $this->monthly($from, $to, $end, $branches),
            'by_branch' => $this->byBranch($from, $to, $end, $branches),
            'top_titles' => $this->topTitles($from, $end, $branches),
            'dead_stock' => $this->deadStock($from, $to, $end, $branches),
            'patron_activity' => $this->patronActivity($from, $to, $end, $branches),
            'fines' => $fines,
            'fines_settled' => collect($fines)->contains(fn ($r) => (float) $r['paid'] > 0 || (float) $r['waived'] > 0),
            'fines_note' => $fines !== [] ? self::FINES_NOTE : null,
            'stock_note' => $undated ? self::STOCK_NOTE : null,
        ];
    }

    /** The report as CSV, one labelled section after another. */
    public function toCsv(array $report): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        $put = function (array $row) use ($out): void {
            fputcsv($out, array_map([$this, 'csvCell'], $row), ',', '"', '');
        };

        $put(['Circulation statistics']);
        $put(['Period', $report['period']['from'], $report['period']['to']]);
        $put(['Branch', $report['branch_label']]);
        $put([]);
        $put(['Measure', 'Value']);
        foreach ($report['summary'] as $key => $value) {
            $put([self::measureLabel($key), $value ?? 'n/a']);
        }

        $section = function (string $title, array $headers, iterable $rows) use ($put): void {
            $put([]);
            $put([$title]);
            $put($headers);
            foreach ($rows as $row) {
                $put($row);
            }
        };

        $section('Issues per month', ['Month', 'Issues', 'Returns'], array_map(
            fn ($r) => [$r['month'], $r['issues'], $r['returns']], $report['monthly']));
        $section('By branch', ['Branch', 'Issues', 'Returns', 'Copies', 'Turnover'], array_map(
            fn ($r) => [$r['name'], $r['issues'], $r['returns'], $r['copies'], $r['turnover'] ?? 'n/a'], $report['by_branch']));
        $section('Busiest titles', ['Title', 'Call number', 'Issues'], array_map(
            fn ($r) => [$r['title'], $r['call_number'], $r['issues']], $report['top_titles']));
        $section('Dead stock (' . $report['dead_stock']['total'] . ' copies, oldest ' . count($report['dead_stock']['rows']) . ' listed)',
            ['Title', 'Barcode', 'Call number', 'Last loan'], array_map(
                fn ($r) => [$r['title'], $r['barcode'], $r['call_number'], $r['last_loan'] ?? 'never'], $report['dead_stock']['rows']));
        $section('Patron activity', ['Loans in period', 'Patrons'], array_map(
            fn ($r) => [$r['band'], $r['patrons']], $report['patron_activity']));
        $settled = $report['fines_settled'];
        $section('Fines', $settled ? ['Currency', 'Assessed', 'Paid', 'Waived', 'Outstanding'] : ['Currency', 'Assessed', 'Outstanding'], array_map(
            fn ($r) => $settled
                ? [$r['currency'], $r['assessed'], $r['paid'], $r['waived'], $r['outstanding']]
                : [$r['currency'], $r['assessed'], $r['outstanding']], $report['fines']));

        $notes = array_filter([$report['stock_note'], $report['fines_note']]);
        if ($notes !== []) {
            $section('Notes', ['Note'], array_map(fn ($n) => [$n], $notes));
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public function toPdf(array $report): string
    {
        $html = view('ahg-library::reports.circulation-pdf', ['report' => $report])->render();

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public static function measureLabel(string $key): string
    {
        return [
            'issues' => 'Issues',
            'returns' => 'Returns',
            'renewals' => 'Renewals',
            'renewal_rate' => 'Loans renewed (%)',
            'late_loans' => 'Late or overdue loans',
            'overdue_rate' => 'Overdue rate (%)',
            'active_borrowers' => 'Active borrowers',
            'circulating_copies' => 'Copies in stock',
            'turnover' => 'Collection turnover',
            'holds_placed' => 'Holds placed',
            'holds_cancelled' => 'Holds cancelled',
            'holds_expired' => 'Holds expired',
        ][$key] ?? $key;
    }

    /** Catalogue text can start with a spreadsheet formula character. */
    private function csvCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    private function branchAxis(): bool
    {
        return LibraryBranch::available() && Schema::hasColumn('library_checkout', 'branch_id');
    }

    private function scoped(Builder $query, string $column, ?array $branches): Builder
    {
        return $branches === null ? $query : $query->whereIn($column, $branches);
    }

    private function loansIssued(string $from, string $end, ?array $branches, string $alias = 'library_checkout'): Builder
    {
        $table = $alias === 'library_checkout' ? $alias : 'library_checkout as ' . $alias;

        return $this->scoped(DB::table($table), $alias . '.branch_id', $branches)
            ->where($alias . '.checkout_date', '>=', $from)
            ->where($alias . '.checkout_date', '<', $end);
    }

    /** Loans from the period returned after their due date, or still out past it. */
    private function lateLoans(string $from, string $end, ?array $branches): int
    {
        $today = CarbonImmutable::now()->toDateString();

        return $this->loansIssued($from, $end, $branches)
            ->where(function ($q) use ($today) {
                $q->whereRaw('DATE(return_date) > due_date')
                    ->orWhere(fn ($open) => $open->whereNull('return_date')->where('status', 'active')->where('due_date', '<', $today));
            })
            ->count();
    }

    /**
     * Copies held at the end of the period. A withdrawal date decides it; the
     * current status says nothing about the past, so it only removes an
     * undated copy from a period that reaches today.
     */
    private function stock(string $to, ?array $branches): Builder
    {
        $end = CarbonImmutable::parse($to)->addDay()->toDateString();
        $reachesToday = $to >= CarbonImmutable::now()->toDateString();

        return $this->scoped(DB::table('library_copy as cp'), 'cp.branch_id', $branches)
            ->where(fn ($q) => $q->whereNull('cp.withdrawal_date')->orWhere('cp.withdrawal_date', '>', $to))
            ->when($reachesToday, fn ($query) => $query->where(
                fn ($q) => $q->whereNotIn('cp.status', self::OUT_OF_STOCK)->orWhereNotNull('cp.withdrawal_date')
            ))
            ->where(function ($q) use ($to, $end) {
                $q->where('cp.acquisition_date', '<=', $to)
                    ->orWhere(fn ($n) => $n->whereNull('cp.acquisition_date')
                        ->where(fn ($c) => $c->whereNull('cp.created_at')->orWhere('cp.created_at', '<', $end)));
            });
    }

    private function holdCounts(string $from, string $end, ?array $branches): array
    {
        $holds = fn () => $this->scoped(DB::table('library_hold'), 'branch_id', $branches)
            ->where('hold_date', '>=', $from)->where('hold_date', '<', $end);

        return [
            'holds_placed' => $holds()->count(),
            'holds_cancelled' => $holds()->where('status', 'cancelled')->count(),
            'holds_expired' => $holds()->where('status', 'expired')->count(),
        ];
    }

    private function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function monthly(string $from, string $to, string $end, ?array $branches): array
    {
        $expr = $this->monthExpression('checkout_date');
        $issues = $this->loansIssued($from, $end, $branches)
            ->selectRaw("{$expr} as ym, COUNT(*) as n")->groupByRaw($expr)->pluck('n', 'ym');

        $expr = $this->monthExpression('return_date');
        $returns = $this->scoped(DB::table('library_checkout'), 'branch_id', $branches)
            ->where('return_date', '>=', $from)->where('return_date', '<', $end)
            ->selectRaw("{$expr} as ym, COUNT(*) as n")->groupByRaw($expr)->pluck('n', 'ym');

        $rows = [];
        $month = CarbonImmutable::parse($from)->startOfMonth();
        $last = CarbonImmutable::parse($to)->startOfMonth();
        while ($month <= $last) {
            $key = $month->format('Y-m');
            $rows[] = ['month' => $key, 'issues' => (int) ($issues[$key] ?? 0), 'returns' => (int) ($returns[$key] ?? 0)];
            $month = $month->addMonthNoOverflow();
        }

        return $rows;
    }

    private function byBranch(string $from, string $to, string $end, ?array $branches): array
    {
        if (! $this->branchAxis()) {
            return [];
        }

        $issues = $this->loansIssued($from, $end, $branches)
            ->selectRaw('branch_id, COUNT(*) as n')->groupBy('branch_id')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->branch_id => (int) $r->n]);
        $returns = $this->scoped(DB::table('library_checkout'), 'branch_id', $branches)
            ->where('return_date', '>=', $from)->where('return_date', '<', $end)
            ->selectRaw('branch_id, COUNT(*) as n')->groupBy('branch_id')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->branch_id => (int) $r->n]);
        $copies = $this->stock($to, $branches)
            ->selectRaw('cp.branch_id as branch_id, COUNT(*) as n')->groupBy('cp.branch_id')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->branch_id => (int) $r->n]);

        $ids = $issues->keys()->merge($returns->keys())->merge($copies->keys())->unique()->sort()->values();

        return $ids->map(function ($id) use ($issues, $returns, $copies) {
            $held = $copies[$id] ?? 0;
            $issued = $issues[$id] ?? 0;

            return [
                'branch_id' => $id ?: null,
                'name' => $id ? (LibraryBranch::name($id) ?? '#' . $id) : 'Not attributed to a branch',
                'issues' => $issued,
                'returns' => $returns[$id] ?? 0,
                'copies' => $held,
                'turnover' => $held > 0 ? round($issued / $held, 2) : null,
            ];
        })->all();
    }

    private function topTitles(string $from, string $end, ?array $branches): array
    {
        $rows = $this->loansIssued($from, $end, $branches, 'lc')
            ->join('library_copy as cp', 'cp.id', '=', 'lc.copy_id')
            ->selectRaw('cp.library_item_id as item_id, COUNT(*) as issues')
            ->groupBy('cp.library_item_id')
            ->orderByDesc('issues')->orderBy('cp.library_item_id')
            ->limit(self::LIST_LIMIT)->get();

        $items = $this->itemLabels($rows->pluck('item_id')->all());

        return $rows->map(fn ($r) => [
            'title' => $items[(int) $r->item_id]['title'],
            'call_number' => $items[(int) $r->item_id]['call_number'],
            'issues' => (int) $r->issues,
        ])->all();
    }

    private function deadStock(string $from, string $to, string $end, ?array $branches): array
    {
        $unused = fn () => $this->stock($to, $branches)->whereNotExists(function ($q) use ($from, $end) {
            $q->selectRaw('1')->from('library_checkout as lc')
                ->whereColumn('lc.copy_id', 'cp.id')
                ->where('lc.checkout_date', '>=', $from)
                ->where('lc.checkout_date', '<', $end);
        });

        $rows = $unused()
            ->select(['cp.id', 'cp.library_item_id', 'cp.barcode'])
            ->selectSub(DB::table('library_checkout as prior')->selectRaw('MAX(prior.checkout_date)')->whereColumn('prior.copy_id', 'cp.id'), 'last_loan')
            ->orderByRaw('CASE WHEN last_loan IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_loan')->orderBy('cp.id')
            ->limit(self::LIST_LIMIT)->get();

        $items = $this->itemLabels($rows->pluck('library_item_id')->all());

        return [
            'total' => $unused()->count(),
            'rows' => $rows->map(fn ($r) => [
                'title' => $items[(int) $r->library_item_id]['title'],
                'call_number' => $items[(int) $r->library_item_id]['call_number'],
                'barcode' => (string) $r->barcode,
                'last_loan' => $r->last_loan ? substr((string) $r->last_loan, 0, 10) : null,
            ])->all(),
        ];
    }

    private function patronActivity(string $from, string $to, string $end, ?array $branches): array
    {
        $perPatron = $this->loansIssued($from, $end, $branches)
            ->selectRaw('patron_id, COUNT(*) as n')->groupBy('patron_id');

        $inactive = $this->scoped(DB::table('library_patron as p'), 'p.branch_id', $branches)
            ->where('p.membership_start', '<=', $to)
            ->where(fn ($q) => $q->whereNull('p.membership_expiry')->orWhere('p.membership_expiry', '>=', $from))
            ->whereNotExists(function ($q) use ($from, $end, $branches) {
                $q->selectRaw('1')->from('library_checkout as lc')
                    ->whereColumn('lc.patron_id', 'p.id')
                    ->where('lc.checkout_date', '>=', $from)
                    ->where('lc.checkout_date', '<', $end);
                if ($branches !== null) {
                    $q->whereIn('lc.branch_id', $branches);
                }
            })->count();

        $rows = [['band' => '0', 'patrons' => $inactive]];
        foreach (self::ACTIVITY_BANDS as [$min, $max]) {
            $band = DB::query()->fromSub(clone $perPatron, 'pp')->where('pp.n', '>=', $min);
            if ($max !== null) {
                $band->where('pp.n', '<=', $max);
            }
            $label = $max === null ? $min . '+' : ($min === $max ? (string) $min : $min . ' to ' . $max);
            $rows[] = ['band' => $label, 'patrons' => $band->count()];
        }

        return $rows;
    }

    private function fines(string $from, string $to, ?array $branches): array
    {
        $query = DB::table('library_fine as f')
            ->where('f.fine_date', '>=', $from)
            ->where('f.fine_date', '<=', $to);

        if ($branches !== null) {
            $query->join('library_checkout as lc', 'lc.id', '=', 'f.checkout_id')
                ->whereIn('lc.branch_id', $branches);
        }

        return $query
            ->selectRaw("f.currency as currency,
                SUM(f.amount) as assessed,
                SUM(f.paid_amount) as paid,
                SUM(CASE WHEN f.status = 'waived' THEN f.amount ELSE 0 END) as waived,
                SUM(CASE WHEN f.status = 'outstanding' THEN f.amount - f.paid_amount ELSE 0 END) as outstanding")
            ->groupBy('f.currency')->orderBy('f.currency')->get()
            ->map(fn ($r) => [
                'currency' => (string) $r->currency,
                'assessed' => number_format((float) $r->assessed, 2, '.', ''),
                'paid' => number_format((float) $r->paid, 2, '.', ''),
                'waived' => number_format((float) $r->waived, 2, '.', ''),
                'outstanding' => number_format((float) $r->outstanding, 2, '.', ''),
            ])->all();
    }

    /** @return array<int, array{title:string, call_number:string}> */
    private function itemLabels(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = DB::table('library_item')->whereIn('id', $itemIds)->get(['id', 'information_object_id', 'call_number']);

        $titles = [];
        if (Schema::hasTable('information_object_i18n')) {
            $culture = app()->getLocale() ?: 'en';
            foreach (DB::table('information_object_i18n')
                ->whereIn('id', $rows->pluck('information_object_id')->all())
                ->whereNotNull('title')
                ->get(['id', 'culture', 'title']) as $t) {
                if (! isset($titles[$t->id]) || $t->culture === $culture) {
                    $titles[$t->id] = (string) $t->title;
                }
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = [
                'title' => $titles[$r->information_object_id] ?? ('Item #' . $r->id),
                'call_number' => (string) $r->call_number,
            ];
        }

        return $out;
    }

    private function branchLabel(?array $branches): string
    {
        if ($branches === null) {
            return 'All branches';
        }

        return implode(', ', array_map(fn ($id) => LibraryBranch::name((int) $id) ?? '#' . $id, $branches));
    }

    private function ratio(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
