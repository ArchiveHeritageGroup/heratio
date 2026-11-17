<?php

/**
 * IpsasService - Service for Heratio
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

namespace AhgIpsas\Services;

use AhgCore\Support\DropdownOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Heritage asset management under IPSAS: asset register, valuations,
 * impairments, insurance, financial-year summary, reports and configuration.
 * Configuration lives in ipsas_config (config_key / config_value).
 */
class IpsasService
{
    /** Statuses that take an asset out of the register totals. */
    public const INACTIVE_STATUSES = ['disposed', 'lost', 'destroyed'];

    /** Report types offered on the Reports page (code => label). */
    public const REPORTS = [
        'asset_register' => 'Asset Register',
        'valuation_summary' => 'Valuation Summary',
        'impairments' => 'Impairment Report',
        'insurance' => 'Insurance Coverage',
        'compliance' => 'Compliance Checklist',
    ];

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function getDashboardStats(): array
    {
        $live = fn () => DB::table('ipsas_heritage_asset')->whereNotIn('status', self::INACTIVE_STATUSES);

        return [
            'assets' => [
                'total' => DB::table('ipsas_heritage_asset')->count(),
                'active' => DB::table('ipsas_heritage_asset')->where('status', 'active')->count(),
                'on_loan' => DB::table('ipsas_heritage_asset')->where('status', 'on_loan')->count(),
                'disposed' => DB::table('ipsas_heritage_asset')->where('status', 'disposed')->count(),
            ],
            'values' => [
                'total' => (float) $live()->sum('current_value'),
                'insured' => (float) $live()->sum('insured_value'),
            ],
            'valuation_basis' => $live()
                ->selectRaw('valuation_basis, COUNT(*) as cnt')
                ->whereNotNull('valuation_basis')
                ->groupBy('valuation_basis')
                ->pluck('cnt', 'valuation_basis')
                ->toArray(),
            'categories' => DB::table('ipsas_heritage_asset as a')
                ->join('ipsas_asset_category as c', 'a.category_id', '=', 'c.id')
                ->whereNotIn('a.status', self::INACTIVE_STATUSES)
                ->selectRaw('c.name as name, COUNT(*) as count, COALESCE(SUM(a.current_value), 0) as value')
                ->groupBy('c.id', 'c.name')
                ->orderByDesc('count')
                ->get(),
            'insurance' => [
                'active' => DB::table('ipsas_insurance')->where('status', 'active')->count(),
                'expiring_soon' => $this->expiringInsuranceQuery()->count(),
                'total_insured' => (float) DB::table('ipsas_insurance')->where('status', 'active')->sum('sum_insured'),
            ],
            'recent_valuations' => DB::table('ipsas_valuation')
                ->whereYear('valuation_date', (int) date('Y'))
                ->count(),
        ];
    }

    public function getComplianceStatus(): array
    {
        $issues = [];
        $warnings = [];
        $live = fn () => DB::table('ipsas_heritage_asset')->whereNotIn('status', self::INACTIVE_STATUSES);

        $unvalued = $live()->whereNull('current_value')->count();
        if ($unvalued > 0) {
            $warnings[] = __(':n asset(s) have no recorded value', ['n' => $unvalued]);
        }

        // Fair-value assets not revalued within the configured revaluation cycle.
        $years = max(1, (int) $this->getConfig('valuation_frequency_years', 5));
        $overdue = $live()
            ->where('valuation_basis', 'fair_value')
            ->where(function ($q) use ($years) {
                $q->whereNull('current_value_date')
                    ->orWhereRaw('current_value_date < DATE_SUB(CURDATE(), INTERVAL ? YEAR)', [$years]);
            })
            ->count();
        if ($overdue > 0) {
            $issues[] = __(':n asset(s) overdue for revaluation', ['n' => $overdue]);
        }

        $expiring = $this->expiringInsuranceQuery()->count();
        if ($expiring > 0) {
            $warnings[] = __(':n insurance policy/policies expiring within 30 days', ['n' => $expiring]);
        }

        $uninsuredHighValue = $live()->where('current_value', '>', 10000)->whereNull('insured_value')->count();
        if ($uninsuredHighValue > 0) {
            $warnings[] = __(':n high-value asset(s) are uninsured', ['n' => $uninsuredHighValue]);
        }

        $critical = $live()->where('condition_rating', 'critical')->count();
        if ($critical > 0) {
            $issues[] = __(':n asset(s) in critical condition - impairment review needed', ['n' => $critical]);
        }

        $status = 'compliant';
        if (! empty($issues)) {
            $status = 'non_compliant';
        } elseif (! empty($warnings)) {
            $status = 'warning';
        }

        return ['status' => $status, 'issues' => $issues, 'warnings' => $warnings];
    }

    public function getRecentAssets(int $limit = 5): Collection
    {
        return DB::table('ipsas_heritage_asset')->orderByDesc('created_at')->limit($limit)->get();
    }

    public function getExpiringInsurance(int $limit = 5): Collection
    {
        return $this->expiringInsuranceQuery()->orderBy('coverage_end')->limit($limit)->get();
    }

    private function expiringInsuranceQuery()
    {
        return DB::table('ipsas_insurance')
            ->where('status', 'active')
            ->whereRaw('coverage_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)');
    }

    // =========================================================================
    // ASSETS
    // =========================================================================

    public function getAssets(array $filters = []): Collection
    {
        $query = DB::table('ipsas_heritage_asset as a')
            ->leftJoin('ipsas_asset_category as c', 'a.category_id', '=', 'c.id')
            ->select('a.*', 'c.name as category_name', 'c.code as category_code');

        if (! empty($filters['category_id'])) {
            $query->where('a.category_id', $filters['category_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('a.status', $filters['status']);
        }
        if (! empty($filters['valuation_basis'])) {
            $query->where('a.valuation_basis', $filters['valuation_basis']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('a.title', 'like', $search)
                    ->orWhere('a.asset_number', 'like', $search)
                    ->orWhere('a.description', 'like', $search);
            });
        }
        if (! empty($filters['min_value'])) {
            $query->where('a.current_value', '>=', $filters['min_value']);
        }
        if (! empty($filters['max_value'])) {
            $query->where('a.current_value', '<=', $filters['max_value']);
        }

        return $query->orderBy('a.asset_number')->get();
    }

    public function getAsset(int $id): ?object
    {
        return DB::table('ipsas_heritage_asset as a')
            ->leftJoin('ipsas_asset_category as c', 'a.category_id', '=', 'c.id')
            ->select('a.*', 'c.name as category_name', 'c.code as category_code')
            ->where('a.id', $id)
            ->first();
    }

    /**
     * Register an asset. The number is <category code>-<year>-<seq>; an
     * initial valuation row is written so the value has an audit trail.
     */
    public function createAsset(array $data): int
    {
        $category = ! empty($data['category_id'])
            ? DB::table('ipsas_asset_category')->where('id', $data['category_id'])->first()
            : null;
        $prefix = $category->code ?? 'AST';
        $year = date('Y');
        $seq = DB::table('ipsas_heritage_asset')->whereYear('created_at', $year)->count() + 1;
        $assetNumber = sprintf('%s-%s-%04d', $prefix, $year, $seq);

        $currency = $this->defaultCurrency();
        $value = ($data['current_value'] ?? '') !== '' && $data['current_value'] !== null
            ? (float) $data['current_value']
            : (float) $this->getConfig('nominal_value', 1.00);
        $basis = $data['valuation_basis'] ?: 'nominal';

        $id = (int) DB::table('ipsas_heritage_asset')->insertGetId([
            'asset_number' => $assetNumber,
            'information_object_id' => $data['information_object_id'] ?: null,
            'category_id' => $data['category_id'] ?: null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'repository_id' => $data['repository_id'] ?: null,
            'acquisition_date' => $data['acquisition_date'] ?: null,
            'acquisition_method' => $data['acquisition_method'] ?: 'unknown',
            'acquisition_source' => $data['acquisition_source'] ?? null,
            'acquisition_cost' => ($data['acquisition_cost'] ?? '') === '' ? null : $data['acquisition_cost'],
            'acquisition_currency' => strtoupper($data['acquisition_currency'] ?: $currency),
            'valuation_basis' => $basis,
            'current_value' => $value,
            'current_value_currency' => $currency,
            'current_value_date' => date('Y-m-d'),
            'depreciation_policy' => $data['depreciation_policy'] ?? ($category->depreciation_policy ?? 'none'),
            'useful_life_years' => $data['useful_life_years'] ?? ($category->useful_life_years ?? null),
            'status' => 'active',
            'condition_rating' => $data['condition_rating'] ?: 'good',
            'created_by' => $data['user_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createValuation([
            'asset_id' => $id,
            'valuation_date' => date('Y-m-d'),
            'valuation_type' => 'initial',
            'valuation_basis' => $basis,
            'previous_value' => 0,
            'new_value' => $value,
            'valuer_type' => 'internal',
            'notes' => 'Initial registration',
            'user_id' => $data['user_id'] ?? null,
        ]);

        $this->logAction('asset_created', 'heritage_asset', $id, $data['user_id'] ?? null, null, $data);

        return $id;
    }

    /** Only fields actually supplied are written (null = leave unchanged). */
    public function updateAsset(int $id, array $data, ?int $userId): bool
    {
        $old = DB::table('ipsas_heritage_asset')->where('id', $id)->first();
        if (! $old) {
            return false;
        }

        $keys = ['title', 'description', 'location', 'status', 'condition_rating',
            'insured_value', 'insurance_policy', 'insurance_expiry', 'risk_level', 'risk_notes'];
        $update = array_filter(
            array_intersect_key($data, array_flip($keys)),
            fn ($v) => $v !== null
        );

        if (! empty($update)) {
            $update['updated_at'] = now();
            DB::table('ipsas_heritage_asset')->where('id', $id)->update($update);
            $this->logAction('asset_updated', 'heritage_asset', $id, $userId, (array) $old, $update);
        }

        return true;
    }

    public function getCategories(): Collection
    {
        return DB::table('ipsas_asset_category')->where('is_active', 1)->orderBy('name')->get();
    }

    /** id => "number - title" for the valuation asset picker. */
    public function getAssetChoices(): Collection
    {
        return DB::table('ipsas_heritage_asset')
            ->whereNotIn('status', self::INACTIVE_STATUSES)
            ->orderBy('asset_number')
            ->get(['id', 'asset_number', 'title', 'current_value']);
    }

    // =========================================================================
    // VALUATIONS
    // =========================================================================

    public function getAssetValuations(int $assetId): Collection
    {
        return DB::table('ipsas_valuation')->where('asset_id', $assetId)->orderByDesc('valuation_date')->get();
    }

    public function getValuations(array $filters = []): Collection
    {
        $query = DB::table('ipsas_valuation as v')
            ->join('ipsas_heritage_asset as a', 'v.asset_id', '=', 'a.id')
            ->select('v.*', 'a.asset_number', 'a.title as asset_title');

        if (! empty($filters['type'])) {
            $query->where('v.valuation_type', $filters['type']);
        }
        if (! empty($filters['year'])) {
            $query->whereYear('v.valuation_date', $filters['year']);
        }
        if (! empty($filters['asset_id'])) {
            $query->where('v.asset_id', $filters['asset_id']);
        }

        return $query->orderByDesc('v.valuation_date')->get();
    }

    /**
     * Record a valuation, compute the change, and carry the new value, date
     * and basis onto the asset.
     */
    public function createValuation(array $data): int
    {
        $previous = (float) ($data['previous_value'] ?? 0);
        $new = (float) ($data['new_value'] ?? 0);
        $change = $new - $previous;
        $percent = $previous > 0 ? ($change / $previous) * 100 : 0;

        $id = (int) DB::table('ipsas_valuation')->insertGetId([
            'asset_id' => $data['asset_id'],
            'valuation_date' => $data['valuation_date'],
            'valuation_type' => $data['valuation_type'],
            'valuation_basis' => $data['valuation_basis'],
            'previous_value' => $previous,
            'new_value' => $new,
            'currency' => $data['currency'] ?? $this->defaultCurrency(),
            'change_amount' => $change,
            'change_percent' => $percent,
            'valuer_name' => $data['valuer_name'] ?? null,
            'valuer_qualification' => $data['valuer_qualification'] ?? null,
            'valuer_type' => $data['valuer_type'] ?? 'internal',
            'valuation_method' => $data['valuation_method'] ?? null,
            'market_evidence' => $data['market_evidence'] ?? null,
            'documentation_ref' => $data['documentation_ref'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['user_id'] ?? null,
            'created_at' => now(),
        ]);

        DB::table('ipsas_heritage_asset')->where('id', $data['asset_id'])->update([
            'current_value' => $new,
            'current_value_date' => $data['valuation_date'],
            'valuation_basis' => $data['valuation_basis'],
            'updated_at' => now(),
        ]);

        $this->logAction('valuation_recorded', 'valuation', $id, $data['user_id'] ?? null, null, $data);

        return $id;
    }

    // =========================================================================
    // IMPAIRMENTS + INSURANCE
    // =========================================================================

    public function getImpairments(array $filters = []): Collection
    {
        $query = DB::table('ipsas_impairment as i')
            ->join('ipsas_heritage_asset as a', 'i.asset_id', '=', 'a.id')
            ->select('i.*', 'a.asset_number', 'a.title as asset_title', 'a.current_value_currency as currency');

        if (! empty($filters['asset_id'])) {
            $query->where('i.asset_id', $filters['asset_id']);
        }
        if (! empty($filters['recognized_only'])) {
            $query->where('i.impairment_recognized', 1);
        }

        return $query->orderByDesc('i.assessment_date')->get();
    }

    /** The indicator flags set on an impairment row, as labels. */
    public static function impairmentIndicators(object $row): array
    {
        $labels = [
            'physical_damage' => __('Physical damage'),
            'obsolescence' => __('Obsolescence'),
            'decline_in_demand' => __('Decline in demand'),
            'market_value_decline' => __('Market value decline'),
            'other_indicator' => __('Other'),
        ];

        return array_values(array_filter($labels, fn ($label, $key) => ! empty($row->{$key}), ARRAY_FILTER_USE_BOTH));
    }

    public function getInsurancePolicies(array $filters = []): Collection
    {
        $query = DB::table('ipsas_insurance as ins')
            ->leftJoin('ipsas_heritage_asset as a', 'ins.asset_id', '=', 'a.id')
            ->select('ins.*', 'a.asset_number', 'a.title as asset_title');

        if (! empty($filters['status'])) {
            $query->where('ins.status', $filters['status']);
        }
        if (! empty($filters['expiring_within_days'])) {
            $query->where('ins.status', 'active')
                ->whereRaw('ins.coverage_end <= DATE_ADD(CURDATE(), INTERVAL ? DAY)', [(int) $filters['expiring_within_days']]);
        }

        return $query->orderBy('ins.coverage_end')->get();
    }

    // =========================================================================
    // FINANCIAL YEAR
    // =========================================================================

    /**
     * Movement of the register over one financial year (start from config,
     * stored as MM-DD). Opening values are acquisition cost of assets that
     * existed before the year; closing is today's carrying value.
     */
    public function calculateFinancialYearSummary(string $year): array
    {
        $start = (string) $this->getConfig('financial_year_start', '01-01');
        if (preg_match('/^\d{2}$/', $start)) {
            $start .= '-01';
        }
        if (! preg_match('/^\d{2}-\d{2}$/', $start)) {
            $start = '01-01';
        }
        $startDate = $year.'-'.$start;
        $endDate = date('Y-m-d', strtotime($startDate.' +1 year -1 day'));

        $live = fn () => DB::table('ipsas_heritage_asset')->whereNotIn('status', self::INACTIVE_STATUSES);

        $openingAssets = $live()->whereDate('created_at', '<', $startDate)->count();
        $openingValue = (float) $live()->whereDate('created_at', '<', $startDate)->sum('acquisition_cost');

        $additions = DB::table('ipsas_heritage_asset')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(acquisition_cost), 0) as val')
            ->first();

        $disposals = DB::table('ipsas_disposal')
            ->whereDate('disposal_date', '>=', $startDate)
            ->whereDate('disposal_date', '<=', $endDate)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(carrying_value), 0) as val')
            ->first();

        $reval = fn () => DB::table('ipsas_valuation')
            ->where('valuation_type', 'revaluation')
            ->whereDate('valuation_date', '>=', $startDate)
            ->whereDate('valuation_date', '<=', $endDate);
        $revalUp = (float) $reval()->where('change_amount', '>', 0)->sum('change_amount');
        $revalDown = abs((float) $reval()->where('change_amount', '<', 0)->sum('change_amount'));

        $impairments = (float) DB::table('ipsas_impairment')
            ->where('impairment_recognized', 1)
            ->whereDate('recognition_date', '>=', $startDate)
            ->whereDate('recognition_date', '<=', $endDate)
            ->sum('impairment_loss');

        $closingAssets = $live()->count();
        $closingValue = (float) $live()->sum('current_value');

        return [
            'financial_year' => $year,
            'year_start' => $startDate,
            'year_end' => $endDate,
            'opening_total_assets' => $openingAssets,
            'opening_total_value' => $openingValue,
            'additions_count' => (int) ($additions->cnt ?? 0),
            'additions_value' => (float) ($additions->val ?? 0),
            'disposals_count' => (int) ($disposals->cnt ?? 0),
            'disposals_value' => (float) ($disposals->val ?? 0),
            'revaluations_increase' => $revalUp,
            'revaluations_decrease' => $revalDown,
            'impairments' => $impairments,
            'closing_total_assets' => $closingAssets,
            'closing_total_value' => $closingValue,
            // Names the summary page reads.
            'total_assets' => $closingAssets,
            'total_value' => $closingValue,
            'additions' => (int) ($additions->cnt ?? 0),
            'opening_balance' => $openingValue,
            'closing_balance' => $closingValue,
            'revaluations' => $revalUp - $revalDown,
            'by_category' => DB::table('ipsas_heritage_asset as a')
                ->leftJoin('ipsas_asset_category as c', 'a.category_id', '=', 'c.id')
                ->whereNotIn('a.status', self::INACTIVE_STATUSES)
                ->selectRaw('c.name as name, COUNT(*) as count, COALESCE(SUM(a.current_value), 0) as total_value')
                ->groupBy('c.id', 'c.name')
                ->orderBy('c.name')
                ->get(),
            'by_basis' => $live()
                ->selectRaw('valuation_basis, COUNT(*) as count, COALESCE(SUM(current_value), 0) as total_value')
                ->groupBy('valuation_basis')
                ->get(),
        ];
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    /**
     * Rows for one CSV report: ['filename' => ..., 'headers' => [...], 'rows' => [[...]]],
     * or null for an unknown report code.
     */
    public function buildReport(string $report, string $year): ?array
    {
        $currency = $this->defaultCurrency();
        $label = fn (?string $v) => ucfirst(str_replace('_', ' ', (string) $v));
        $rows = [];

        switch ($report) {
            case 'asset_register':
                $headers = ['Asset #', 'Title', 'Category', 'Location', 'Valuation Basis', 'Current Value', 'Currency', 'Status', 'Condition', 'Acquisition Date', 'Acquisition Method'];
                foreach ($this->getAssets() as $a) {
                    $rows[] = [$a->asset_number, $a->title, $a->category_name ?? '', $a->location ?? '',
                        $label($a->valuation_basis), $a->current_value ?? 0, $a->current_value_currency ?: $currency,
                        $label($a->status), $label($a->condition_rating), $a->acquisition_date ?? '', $label($a->acquisition_method)];
                }
                break;

            case 'valuation_summary':
                $headers = ['Date', 'Asset #', 'Asset Title', 'Type', 'Basis', 'Previous Value', 'New Value', 'Change', 'Currency', 'Valuer'];
                foreach ($this->getValuations(['year' => $year]) as $v) {
                    $rows[] = [$v->valuation_date, $v->asset_number, $v->asset_title, $label($v->valuation_type),
                        $label($v->valuation_basis), $v->previous_value ?? 0, $v->new_value ?? 0,
                        ($v->new_value ?? 0) - ($v->previous_value ?? 0), $v->currency ?: $currency, $v->valuer_name ?? ''];
                }
                break;

            case 'impairments':
                $headers = ['Date', 'Asset #', 'Asset Title', 'Indicators', 'Carrying Amount', 'Recoverable Amount', 'Impairment Loss', 'Recognised'];
                foreach ($this->getImpairments() as $i) {
                    $rows[] = [$i->assessment_date, $i->asset_number, $i->asset_title, implode('; ', self::impairmentIndicators($i)),
                        $i->carrying_amount ?? 0, $i->recoverable_amount ?? 0, $i->impairment_loss ?? 0,
                        $i->impairment_recognized ? 'Yes' : 'No'];
                }
                break;

            case 'insurance':
                $headers = ['Policy #', 'Asset #', 'Asset Title', 'Insurer', 'Type', 'Sum Insured', 'Currency', 'Coverage Start', 'Coverage End', 'Status'];
                foreach ($this->getInsurancePolicies() as $p) {
                    $rows[] = [$p->policy_number, $p->asset_number ?? 'Blanket', $p->asset_title ?? '-', $p->insurer,
                        $label($p->policy_type), $p->sum_insured ?? 0, $p->currency ?: $currency,
                        $p->coverage_start, $p->coverage_end, $label($p->status)];
                }
                break;

            case 'compliance':
                $headers = ['Check', 'Status', 'Count', 'Details'];
                $compliance = $this->getComplianceStatus();
                $stats = $this->getDashboardStats();
                $rows[] = ['Total Assets', 'Info', $stats['assets']['total'], 'Total heritage assets in register'];
                $rows[] = ['Active Assets', 'Info', $stats['assets']['active'], 'Assets currently active'];
                $rows[] = ['Total Value', 'Info', number_format($stats['values']['total'], 2, '.', ''), 'Combined current value ('.$currency.')'];
                $rows[] = ['Insured Value', 'Info', number_format($stats['values']['insured'], 2, '.', ''), 'Combined insured value ('.$currency.')'];
                foreach ($compliance['issues'] as $issue) {
                    $rows[] = ['Issue', 'Non-Compliant', '-', $issue];
                }
                foreach ($compliance['warnings'] as $warning) {
                    $rows[] = ['Warning', 'Warning', '-', $warning];
                }
                if (empty($compliance['issues']) && empty($compliance['warnings'])) {
                    $rows[] = ['Overall Status', 'Compliant', '-', 'No issues or warnings found'];
                }
                break;

            default:
                return null;
        }

        $filename = $report === 'insurance' ? "insurance_coverage_{$year}"
            : ($report === 'compliance' ? "compliance_report_{$year}" : "{$report}_{$year}");

        return ['filename' => $filename, 'headers' => $headers, 'rows' => $rows];
    }

    // =========================================================================
    // CONFIGURATION + OPTIONS
    // =========================================================================

    public function getConfig(string $key, $default = null)
    {
        $value = DB::table('ipsas_config')->where('config_key', $key)->value('config_value');

        return $value ?? $default;
    }

    public function setConfig(string $key, ?string $value): void
    {
        DB::table('ipsas_config')->updateOrInsert(
            ['config_key' => $key],
            ['config_value' => $value, 'updated_at' => now()]
        );
    }

    public function getAllConfig(): array
    {
        return DB::table('ipsas_config')->pluck('config_value', 'config_key')->toArray();
    }

    public function defaultCurrency(): string
    {
        return strtoupper((string) ($this->getConfig('default_currency') ?: 'USD'));
    }

    /** Enumerated values for the forms and filters, from the Dropdown Manager. */
    public function options(): array
    {
        return [
            'status' => DropdownOptions::resolve('heritage_asset_status', ['active' => 'Active', 'on_loan' => 'On Loan', 'in_storage' => 'In Storage', 'under_conservation' => 'Under Conservation', 'disposed' => 'Disposed', 'lost' => 'Lost', 'destroyed' => 'Destroyed']),
            'valuation_basis' => DropdownOptions::resolve('valuation_basis', ['historical_cost' => 'Historical Cost', 'fair_value' => 'Fair Value', 'nominal' => 'Nominal', 'not_recognized' => 'Not Recognized']),
            'acquisition_method' => DropdownOptions::resolve('acquisition_method', ['purchase' => 'Purchase', 'donation' => 'Donation', 'bequest' => 'Bequest', 'transfer' => 'Transfer', 'exchange' => 'Exchange', 'found' => 'Found', 'unknown' => 'Unknown']),
            'condition_rating' => DropdownOptions::resolve('condition_rating', ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'critical' => 'Critical']),
            'risk_level' => DropdownOptions::resolve('risk_level', ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']),
            'valuation_type' => DropdownOptions::resolve('ipsas_valuation_type', ['initial' => 'Initial', 'revaluation' => 'Revaluation', 'impairment' => 'Impairment', 'reversal' => 'Reversal', 'disposal' => 'Disposal']),
            'valuation_method' => DropdownOptions::resolve('ipsas_valuation_method', ['market_comparison' => 'Market Comparison', 'income_approach' => 'Income Approach', 'cost_approach' => 'Cost Approach', 'expert_opinion' => 'Expert Opinion']),
            'valuer_type' => DropdownOptions::resolve('valuer_type', ['internal' => 'Internal', 'external' => 'External', 'government' => 'Government']),
            'insurance_status' => DropdownOptions::resolve('ipsas_insurance_status', ['active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'pending_renewal' => 'Pending Renewal']),
            'depreciation_policy' => DropdownOptions::resolve('depreciation_policy', ['none' => 'No Depreciation (Heritage)', 'straight_line' => 'Straight Line', 'reducing_balance' => 'Reducing Balance']),
            'accounting_standard' => DropdownOptions::resolve('ipsas_accounting_standard', ['IPSAS' => 'IPSAS', 'GRAP' => 'GRAP', 'IFRS' => 'IFRS', 'GAAP' => 'GAAP']),
        ];
    }

    // =========================================================================
    // AUDIT
    // =========================================================================

    protected function logAction(string $actionType, string $entityType, int $entityId, ?int $userId = null, ?array $old = null, ?array $new = null): void
    {
        DB::table('ipsas_audit_log')->insert([
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'ip_address' => request()?->ip(),
            'old_value' => $old ? json_encode($old) : null,
            'new_value' => $new ? json_encode($new) : null,
            'created_at' => now(),
        ]);
    }
}
