<?php

/**
 * ResearchFundingService - Heratio ahg-research
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

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1222 - Research OS: Research Funding tracker.
 *
 * The AWARDED-FUNDING ledger for a research project: a record of the funding
 * sources, amounts, currencies and award periods that support the work, so a
 * project's financial backing is documented alongside its DMP, outputs, grants
 * and ethics. This is the actual awarded-funding ledger - DISTINCT from the
 * sibling grant-DRAFTING slice (research_grant_draft), which is about composing
 * a proposal. Each record carries a funder name and type, an award reference, an
 * exact DECIMAL amount in an ISO 4217 currency, a status, an award period, and
 * can optionally reference the project's Data Management Plan (the sibling
 * research_dmp slice).
 *
 * Mirrors ResearchEthicsService exactly: scoped to a project, dropdown-backed
 * taxonomies (never ENUM), a machine-readable JSON export, and a per-project
 * summary. Every read is Schema::hasTable-guarded and try/catch-wrapped so a
 * partial install degrades cleanly rather than 500ing. No live writes outside
 * the one NEW research_funding table; no ALTER of any existing table.
 *
 * International and jurisdiction-neutral: NO currency, funder country or legal
 * regime is assumed or defaulted. Awarded totals are grouped PER CURRENCY and
 * NEVER cross-summed - adding USD to EUR is meaningless and is never done.
 */
class ResearchFundingService
{
    public const FUNDER_TYPE_TAXONOMY = 'research_funder_type';
    public const STATUS_TAXONOMY      = 'research_funding_status';
    public const CURRENCY_TAXONOMY    = 'research_currency';

    /** Statuses whose amount counts toward "awarded" totals (per currency). */
    public const AWARDED_STATUSES = ['awarded', 'active', 'completed'];

    // ---------------------------------------------------------------------
    // Funding records (CRUD)
    // ---------------------------------------------------------------------

    /** Funding records on a project (lightweight list rows, newest first). */
    public function listRecords(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_funding')) {
                return [];
            }

            return DB::table('research_funding')
                ->where('project_id', $projectId)
                ->orderByRaw('start_date IS NULL, start_date DESC')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($r) => $this->rowToArray($r))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single funding record as an array, scoped to its project, or null. */
    public function getRecord(int $id, ?int $projectId = null): ?array
    {
        try {
            if (! Schema::hasTable('research_funding')) {
                return null;
            }
            $q = DB::table('research_funding')->where('id', $id);
            if ($projectId !== null) {
                $q->where('project_id', $projectId);
            }
            $row = $q->first();

            return $row ? $this->rowToArray($row) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create a funding record for a project. Returns the new id, or null on failure.
     *
     * @param  array<string,mixed>  $data
     */
    public function createRecord(int $projectId, ?int $researcherId, array $data): ?int
    {
        try {
            if (! Schema::hasTable('research_funding')) {
                return null;
            }

            $now = now();
            $row = array_merge($this->normalise($projectId, $data), [
                'project_id' => $projectId,
                'owner_id'   => $researcherId,
                'created_by' => $researcherId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (int) DB::table('research_funding')->insertGetId($row);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] funding createRecord failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update a funding record, scoped to its project.
     *
     * @param  array<string,mixed>  $data
     */
    public function updateRecord(int $id, int $projectId, array $data): bool
    {
        try {
            if (! Schema::hasTable('research_funding')) {
                return false;
            }
            $owns = DB::table('research_funding')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }

            $row = array_merge($this->normalise($projectId, $data), ['updated_at' => now()]);
            DB::table('research_funding')->where('id', $id)->where('project_id', $projectId)->update($row);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] funding updateRecord failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Delete a funding record, scoped to its project. */
    public function deleteRecord(int $id, int $projectId): bool
    {
        try {
            if (! Schema::hasTable('research_funding')) {
                return false;
            }
            $owns = DB::table('research_funding')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }
            DB::table('research_funding')->where('id', $id)->where('project_id', $projectId)->delete();

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] funding deleteRecord failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Coerce validated request data into a writeable column map. Dropdown-backed
     * values are constrained to their known option codes; free-text is trimmed
     * and length-capped. The amount is kept as an exact decimal string (never a
     * float). The dmp_id is only kept if it points at a plan on the SAME project
     * (FK-by-convention, verified, never assumed).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalise(int $projectId, array $data): array
    {
        $type = (string) ($data['funder_type'] ?? 'other');
        if (! array_key_exists($type, $this->funderTypeOptions())) {
            $type = 'other';
        }

        $status = (string) ($data['status'] ?? 'applied');
        if (! array_key_exists($status, $this->statusOptions())) {
            $status = 'applied';
        }

        // Currency MUST come from the dropdown. There is no canonical default
        // currency; if the submitted code is unknown we fall back to the FIRST
        // configured option (administrator-defined), never a hardcoded country.
        $currencyOptions = $this->currencyOptions();
        $currency = strtoupper((string) ($data['currency'] ?? ''));
        if (! array_key_exists($currency, $currencyOptions)) {
            $currency = (string) (array_key_first($currencyOptions) ?? 'USD');
        }

        return [
            'title'           => mb_substr(trim((string) ($data['title'] ?? '')), 0, 512),
            'funder_name'     => mb_substr(trim((string) ($data['funder_name'] ?? '')), 0, 512),
            'funder_type'     => $type,
            'award_reference' => $this->trimOrNull($data['award_reference'] ?? null, 128),
            'amount'          => $this->amountOrNull($data['amount'] ?? null),
            'currency'        => $currency,
            'status'          => $status,
            'start_date'      => $this->dateOrNull($data['start_date'] ?? null),
            'end_date'        => $this->dateOrNull($data['end_date'] ?? null),
            'notes'           => isset($data['notes']) && trim((string) $data['notes']) !== ''
                ? mb_substr((string) $data['notes'], 0, 65000) : null,
            'dmp_id'          => $this->validDmpId($data['dmp_id'] ?? null, $projectId),
        ];
    }

    private function trimOrNull(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /**
     * Normalise a money input to an exact 2dp decimal STRING, or null. Strips
     * any thousands separators / stray spaces, clamps to the DECIMAL(14,2)
     * range, and formats without scientific notation - never a float round-trip.
     */
    private function amountOrNull(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $raw = str_replace([',', ' '], '', (string) $value);
        if (! is_numeric($raw)) {
            return null;
        }
        // DECIMAL(14,2): max 999999999999.99. Clamp defensively.
        $num = $raw;
        if (abs((float) $num) > 999999999999.99) {
            $num = ((float) $num < 0 ? '-' : '') . '999999999999.99';
        }

        return number_format((float) $num, 2, '.', '');
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Validate a candidate dmp_id - it must reference a research_dmp row on the
     * SAME project, or it is dropped. Resilient when the sibling slice is not
     * installed (no research_dmp table) - simply returns null.
     */
    private function validDmpId(mixed $value, int $projectId): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }
        try {
            if (! Schema::hasTable('research_dmp')) {
                return null;
            }
            $ok = DB::table('research_dmp')
                ->where('id', (int) $value)
                ->where('project_id', $projectId)
                ->exists();

            return $ok ? (int) $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------------------------------------------------------------
    // DMP link options (sibling slice, optional)
    // ---------------------------------------------------------------------

    /**
     * Plans on this project for the optional DMP link [id => label]. Returns an
     * empty array when the sibling slice is absent, so the form degrades to "no
     * plan" cleanly.
     *
     * @return array<int,string>
     */
    public function dmpOptions(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_dmp')) {
                return [];
            }
            $rows = DB::table('research_dmp')
                ->where('project_id', $projectId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id', 'title']);

            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r->id] = (string) $r->title;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Active-now helper (drives the active indicator)
    // ---------------------------------------------------------------------

    /**
     * Is a funding record active right now? True when its status is 'active' OR
     * its award period brackets today (start on/before today, end on/after today
     * or open-ended), and the status is not terminal/declined. Resilient to
     * missing / unparseable dates.
     *
     * @param  array<string,mixed>  $record
     */
    public function isActiveNow(array $record): bool
    {
        $status = (string) ($record['status'] ?? '');
        // 'applied' is not yet money in hand; 'declined'/'completed' are terminal.
        if (in_array($status, ['declined', 'completed', 'applied'], true)) {
            return false;
        }
        if ($status === 'active') {
            return true;
        }
        // 'awarded' (or any other non-terminal status): fall back to the period test.
        $start = trim((string) ($record['start_date'] ?? ''));
        $end   = trim((string) ($record['end_date'] ?? ''));
        if ($start === '' && $end === '') {
            return false;
        }
        try {
            $today = \Illuminate\Support\Carbon::now()->startOfDay();
            $afterStart = $start === '' || ! \Illuminate\Support\Carbon::parse($start)->startOfDay()->gt($today);
            $beforeEnd  = $end === '' || ! \Illuminate\Support\Carbon::parse($end)->startOfDay()->lt($today);

            return $afterStart && $beforeEnd;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Per-project summary (per-currency totals + counts by status)
    // ---------------------------------------------------------------------

    /**
     * Summary of a project's funding: total record count, awarded amount totals
     * GROUPED PER CURRENCY (never cross-summed - USD and EUR are reported on
     * separate lines), counts by status, counts by funder type, and the number
     * of awards that are active right now.
     *
     * The per-currency totals are computed with bcadd over decimal strings so no
     * float rounding is introduced. Only awarded/active/completed statuses count
     * toward the totals (an 'applied' or 'declined' line is excluded).
     *
     * @return array{total:int,by_currency:array<int,array{currency:string,amount:string,count:int}>,by_status:array<int,array{code:string,label:string,count:int}>,by_type:array<int,array{code:string,label:string,count:int}>,active_now:int}
     */
    public function summary(int $projectId): array
    {
        $empty = ['total' => 0, 'by_currency' => [], 'by_status' => [], 'by_type' => [], 'active_now' => 0];
        try {
            if (! Schema::hasTable('research_funding')) {
                return $empty;
            }

            $records = $this->listRecords($projectId);
            $total   = count($records);

            $statusCounts   = [];
            $typeCounts     = [];
            $activeNow      = 0;
            // currency => ['amount' => decimal string, 'count' => int]
            $currencyTotals = [];

            foreach ($records as $r) {
                $s = (string) ($r['status'] ?? '');
                $t = (string) ($r['funder_type'] ?? '');
                $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
                $typeCounts[$t]   = ($typeCounts[$t] ?? 0) + 1;

                if ($this->isActiveNow($r)) {
                    $activeNow++;
                }

                // Per-currency awarded totals - NEVER cross-summed.
                $amount = (string) ($r['amount'] ?? '');
                if ($amount !== '' && in_array($s, self::AWARDED_STATUSES, true)) {
                    $cur = (string) ($r['currency'] ?? '');
                    if ($cur === '') {
                        continue;
                    }
                    if (! isset($currencyTotals[$cur])) {
                        $currencyTotals[$cur] = ['amount' => '0.00', 'count' => 0];
                    }
                    $currencyTotals[$cur]['amount'] = $this->bcAdd($currencyTotals[$cur]['amount'], $amount);
                    $currencyTotals[$cur]['count']++;
                }
            }

            // Stable, currency-code-ordered list of per-currency totals.
            ksort($currencyTotals);
            $byCurrency = [];
            foreach ($currencyTotals as $cur => $agg) {
                $byCurrency[] = [
                    'currency' => (string) $cur,
                    'amount'   => $agg['amount'],
                    'count'    => (int) $agg['count'],
                ];
            }

            return [
                'total'       => $total,
                'by_currency' => $byCurrency,
                'by_status'   => $this->labelCounts($statusCounts, $this->statusOptions()),
                'by_type'     => $this->labelCounts($typeCounts, $this->funderTypeOptions()),
                'active_now'  => $activeNow,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Add two money strings exactly. Uses bcmath when available, otherwise a
     * safe integer-cents fallback - never a naive float add.
     */
    private function bcAdd(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a !== '' ? $a : '0', $b !== '' ? $b : '0', 2);
        }
        // Fallback: work in integer cents to avoid binary-float drift.
        $toCents = static function (string $v): int {
            $v = trim($v);
            if ($v === '') {
                return 0;
            }
            $neg = str_starts_with($v, '-');
            $v = ltrim($v, '+-');
            $parts = explode('.', $v, 2);
            $whole = (int) ($parts[0] === '' ? '0' : $parts[0]);
            $frac  = isset($parts[1]) ? substr(str_pad($parts[1], 2, '0'), 0, 2) : '00';
            $cents = $whole * 100 + (int) $frac;

            return $neg ? -$cents : $cents;
        };
        $cents = $toCents($a) + $toCents($b);
        $sign  = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Order a code=>count map by a label taxonomy, attaching labels. Any orphan
     * code not in the taxonomy still surfaces with a humanised label.
     *
     * @param  array<string,int>  $counts
     * @param  array<string,string>  $labels
     * @return array<int,array{code:string,label:string,count:int}>
     */
    private function labelCounts(array $counts, array $labels): array
    {
        $out = [];
        foreach ($labels as $code => $label) {
            if (isset($counts[$code])) {
                $out[] = ['code' => (string) $code, 'label' => (string) $label, 'count' => (int) $counts[$code]];
            }
        }
        foreach ($counts as $code => $c) {
            if (! isset($labels[$code])) {
                $out[] = ['code' => (string) $code, 'label' => ucfirst(str_replace('_', ' ', (string) $code)), 'count' => (int) $c];
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Machine-readable export
    // ---------------------------------------------------------------------

    /**
     * Build a machine-readable export of a project's funding records. Each entry
     * carries the funder name and type, award reference, exact amount + currency,
     * status, award period and active flag (codes + human labels). The shape is a
     * top-level object with a "project" block, a generated_at timestamp, a
     * "count", a "totals_by_currency" array (awarded totals grouped per currency,
     * NEVER cross-summed) and a "funding" array.
     *
     * @param  array<int,array<string,mixed>>  $records
     * @param  object|null  $project
     * @return array<string,mixed>
     */
    public function buildExport(array $records, ?object $project = null, ?array $summary = null): array
    {
        $typeLabels   = $this->funderTypeOptions();
        $statusLabels = $this->statusOptions();
        $curLabels    = $this->currencyOptions();

        $items = [];
        foreach ($records as $r) {
            $type   = (string) ($r['funder_type'] ?? '');
            $status = (string) ($r['status'] ?? '');
            $cur    = (string) ($r['currency'] ?? '');
            $amount = (string) ($r['amount'] ?? '');
            $items[] = [
                'id'                => (int) ($r['id'] ?? 0),
                'title'             => (string) ($r['title'] ?? ''),
                'funder_name'       => (string) ($r['funder_name'] ?? ''),
                'funder_type'       => $type,
                'funder_type_label' => $typeLabels[$type] ?? $type,
                'award_reference'   => (string) ($r['award_reference'] ?? ''),
                'amount'            => $amount !== '' ? $amount : null,
                'currency'          => $cur,
                'currency_label'    => $curLabels[$cur] ?? $cur,
                'status'            => $status,
                'status_label'      => $statusLabels[$status] ?? $status,
                'start_date'        => (string) ($r['start_date'] ?? ''),
                'end_date'          => (string) ($r['end_date'] ?? ''),
                'active_now'        => $this->isActiveNow($r),
                'dmp_id'            => $r['dmp_id'] ?? null,
                'notes'             => (string) ($r['notes'] ?? ''),
            ];
        }

        $summary = $summary ?? $this->summaryFromRecords($records);

        return [
            'project' => [
                'id'    => isset($project->id) ? (int) $project->id : null,
                'title' => isset($project->title) ? (string) $project->title : '',
            ],
            'generated_at'       => now()->toIso8601String(),
            'count'              => count($items),
            // Awarded totals grouped PER CURRENCY - never summed across currencies.
            'totals_by_currency' => $summary['by_currency'] ?? [],
            'active_now'         => $summary['active_now'] ?? 0,
            'funding'            => $items,
        ];
    }

    /**
     * Per-currency / active summary from an in-memory record list (used by the
     * export so it does not re-query). Mirrors summary()'s aggregation rules.
     *
     * @param  array<int,array<string,mixed>>  $records
     * @return array{by_currency:array<int,array{currency:string,amount:string,count:int}>,active_now:int}
     */
    private function summaryFromRecords(array $records): array
    {
        $currencyTotals = [];
        $activeNow = 0;
        foreach ($records as $r) {
            if ($this->isActiveNow($r)) {
                $activeNow++;
            }
            $s = (string) ($r['status'] ?? '');
            $amount = (string) ($r['amount'] ?? '');
            if ($amount !== '' && in_array($s, self::AWARDED_STATUSES, true)) {
                $cur = (string) ($r['currency'] ?? '');
                if ($cur === '') {
                    continue;
                }
                if (! isset($currencyTotals[$cur])) {
                    $currencyTotals[$cur] = ['amount' => '0.00', 'count' => 0];
                }
                $currencyTotals[$cur]['amount'] = $this->bcAdd($currencyTotals[$cur]['amount'], $amount);
                $currencyTotals[$cur]['count']++;
            }
        }
        ksort($currencyTotals);
        $byCurrency = [];
        foreach ($currencyTotals as $cur => $agg) {
            $byCurrency[] = ['currency' => (string) $cur, 'amount' => $agg['amount'], 'count' => (int) $agg['count']];
        }

        return ['by_currency' => $byCurrency, 'active_now' => $activeNow];
    }

    // ---------------------------------------------------------------------
    // Dropdown-backed taxonomies (Dropdown Manager - never ENUM)
    // ---------------------------------------------------------------------

    /** Funder-type options [code => label], with a safe fallback. */
    public function funderTypeOptions(): array
    {
        return $this->dropdownOptions(self::FUNDER_TYPE_TAXONOMY, [
            'government' => 'Government', 'research_council' => 'Research council',
            'foundation' => 'Foundation', 'charity' => 'Charity / non-profit',
            'industry' => 'Industry / commercial', 'internal' => 'Internal / institutional',
            'other' => 'Other',
        ]);
    }

    /** Status options [code => label], with a safe fallback. */
    public function statusOptions(): array
    {
        return $this->dropdownOptions(self::STATUS_TAXONOMY, [
            'applied' => 'Applied', 'awarded' => 'Awarded', 'active' => 'Active',
            'completed' => 'Completed', 'declined' => 'Declined',
        ]);
    }

    /**
     * Currency options [code => label], ISO 4217. Safe fallback is a small,
     * region-spread seed; NO single currency is canonical or defaulted.
     *
     * @return array<string,string>
     */
    public function currencyOptions(): array
    {
        return $this->dropdownOptions(self::CURRENCY_TAXONOMY, [
            'USD' => 'USD - US Dollar', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - Pound Sterling',
            'ZAR' => 'ZAR - South African Rand', 'AUD' => 'AUD - Australian Dollar',
            'CAD' => 'CAD - Canadian Dollar', 'JPY' => 'JPY - Japanese Yen', 'CHF' => 'CHF - Swiss Franc',
        ]);
    }

    /** Generic dropdown reader [code => label] with a fallback map. */
    private function dropdownOptions(string $taxonomy, array $fallback): array
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return $fallback;
            }
            $rows = DB::table('ahg_dropdown')
                ->where('taxonomy', $taxonomy)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get(['code', 'label']);

            if ($rows->isEmpty()) {
                return $fallback;
            }

            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r->code] = (string) $r->label;
            }

            return $out;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function rowToArray(object $r): array
    {
        return [
            'id'              => (int) $r->id,
            'project_id'      => (int) $r->project_id,
            'title'           => (string) $r->title,
            'funder_name'     => (string) $r->funder_name,
            'funder_type'     => (string) $r->funder_type,
            'award_reference' => $r->award_reference !== null ? (string) $r->award_reference : '',
            // Keep money as an exact string - never cast through float.
            'amount'          => $r->amount !== null ? (string) $r->amount : '',
            'currency'        => (string) $r->currency,
            'status'          => (string) $r->status,
            'start_date'      => $r->start_date !== null ? (string) $r->start_date : '',
            'end_date'        => $r->end_date !== null ? (string) $r->end_date : '',
            'notes'           => $r->notes !== null ? (string) $r->notes : '',
            'dmp_id'          => $r->dmp_id !== null ? (int) $r->dmp_id : null,
            'owner_id'        => $r->owner_id !== null ? (int) $r->owner_id : null,
            'created_by'      => $r->created_by !== null ? (int) $r->created_by : null,
            'created_at'      => (string) ($r->created_at ?? ''),
            'updated_at'      => (string) ($r->updated_at ?? ''),
        ];
    }
}
