<?php

/**
 * IpsasController - Controller for Heratio
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

namespace AhgIpsas\Controllers;

use AhgIpsas\Services\IpsasService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Heritage asset management under IPSAS. Every page shares the configured
 * currency and the Dropdown Manager option lists.
 */
class IpsasController extends Controller
{
    protected IpsasService $service;

    public function __construct(IpsasService $service)
    {
        $this->service = $service;
    }

    /** Currency + option lists every IPSAS view uses. */
    private function shareCommon(): void
    {
        View::share('currency', $this->service->defaultCurrency());
        View::share('opts', $this->service->options());
    }

    public function index()
    {
        $this->shareCommon();

        return view('ahg-ipsas::index', [
            'stats' => $this->service->getDashboardStats(),
            'compliance' => $this->service->getComplianceStatus(),
            'config' => $this->service->getAllConfig(),
            'recentAssets' => $this->service->getRecentAssets(),
            'expiringInsurance' => $this->service->getExpiringInsurance(),
        ]);
    }

    public function assets(Request $request)
    {
        $this->shareCommon();
        $filters = [
            'category_id' => $request->query('category'),
            'status' => $request->query('status'),
            'valuation_basis' => $request->query('basis'),
            'search' => $request->query('q'),
        ];

        return view('ahg-ipsas::assets', [
            'assets' => $this->service->getAssets($filters),
            'categories' => $this->service->getCategories(),
            'filters' => $filters,
        ]);
    }

    public function assetCreate(Request $request)
    {
        $this->shareCommon();
        $opts = $this->service->options();

        if ($request->isMethod('post')) {
            $v = $request->validate([
                'title' => 'required|string|max:500',
                'description' => 'nullable|string',
                'category_id' => 'nullable|integer|exists:ipsas_asset_category,id',
                'location' => 'nullable|string|max:255',
                'information_object_id' => 'nullable|integer|exists:information_object,id',
                'repository_id' => 'nullable|integer',
                'acquisition_date' => 'nullable|date',
                'acquisition_method' => 'nullable|string|in:'.implode(',', array_keys($opts['acquisition_method'])),
                'acquisition_source' => 'nullable|string|max:255',
                'acquisition_cost' => 'nullable|numeric|min:0',
                'acquisition_currency' => 'nullable|string|size:3',
                'valuation_basis' => 'nullable|string|in:'.implode(',', array_keys($opts['valuation_basis'])),
                'current_value' => 'nullable|numeric|min:0',
                'condition_rating' => 'nullable|string|in:'.implode(',', array_keys($opts['condition_rating'])),
            ]);
            $keys = ['title', 'description', 'category_id', 'location', 'information_object_id', 'repository_id',
                'acquisition_date', 'acquisition_method', 'acquisition_source', 'acquisition_cost',
                'acquisition_currency', 'valuation_basis', 'current_value', 'condition_rating'];
            $data = array_merge(array_fill_keys($keys, null), $v, ['user_id' => auth()->id()]);

            $id = $this->service->createAsset($data);

            return redirect()->route('ipsas.asset.view', $id)->with('success', __('Asset registered'));
        }

        return view('ahg-ipsas::asset-create', ['categories' => $this->service->getCategories()]);
    }

    public function assetView(int $id)
    {
        $asset = $this->service->getAsset($id);
        abort_unless($asset, 404, 'Asset not found');
        $this->shareCommon();

        return view('ahg-ipsas::asset-view', [
            'asset' => $asset,
            'valuations' => $this->service->getAssetValuations($id),
            'impairments' => $this->service->getImpairments(['asset_id' => $id]),
        ]);
    }

    public function assetEdit(Request $request, int $id)
    {
        $asset = $this->service->getAsset($id);
        abort_unless($asset, 404, 'Asset not found');
        $this->shareCommon();
        $opts = $this->service->options();

        if ($request->isMethod('post')) {
            $v = $request->validate([
                'title' => 'required|string|max:500',
                'description' => 'nullable|string',
                'location' => 'nullable|string|max:255',
                'status' => 'required|string|in:'.implode(',', array_keys($opts['status'])),
                'condition_rating' => 'nullable|string|in:'.implode(',', array_keys($opts['condition_rating'])),
                'risk_level' => 'nullable|string|in:'.implode(',', array_keys($opts['risk_level'])),
                'risk_notes' => 'nullable|string',
            ]);
            // Blank text fields clear the value; a blank select leaves it unchanged.
            foreach (['description', 'location', 'risk_notes'] as $k) {
                $v[$k] = $v[$k] ?? '';
            }

            $this->service->updateAsset($id, $v, auth()->id());

            return redirect()->route('ipsas.asset.view', $id)->with('success', __('Asset updated'));
        }

        return view('ahg-ipsas::asset-edit', [
            'asset' => $asset,
            'categories' => $this->service->getCategories(),
        ]);
    }

    public function valuations(Request $request)
    {
        $this->shareCommon();
        $filters = [
            'type' => $request->query('type'),
            'year' => $request->query('year', date('Y')),
        ];

        return view('ahg-ipsas::valuations', [
            'valuations' => $this->service->getValuations($filters),
            'filters' => $filters,
        ]);
    }

    public function valuationCreate(Request $request)
    {
        $this->shareCommon();
        $opts = $this->service->options();

        if ($request->isMethod('post')) {
            $v = $request->validate([
                'asset_id' => 'required|integer|exists:ipsas_heritage_asset,id',
                'valuation_date' => 'required|date',
                'valuation_type' => 'required|string|in:'.implode(',', array_keys($opts['valuation_type'])),
                'valuation_basis' => 'required|string|in:'.implode(',', array_keys($opts['valuation_basis'])),
                'previous_value' => 'nullable|numeric|min:0',
                'new_value' => 'required|numeric|min:0',
                'valuer_name' => 'nullable|string|max:255',
                'valuer_qualification' => 'nullable|string|max:255',
                'valuer_type' => 'nullable|string|in:'.implode(',', array_keys($opts['valuer_type'])),
                'valuation_method' => 'nullable|string|max:255',
                'market_evidence' => 'nullable|string',
                'documentation_ref' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);
            $v['user_id'] = auth()->id();

            $this->service->createValuation($v);

            return redirect()->route('ipsas.asset.view', $v['asset_id'])->with('success', __('Valuation recorded'));
        }

        $assetId = (int) $request->query('asset_id');
        $asset = $assetId ? $this->service->getAsset($assetId) : null;

        return view('ahg-ipsas::valuation-create', [
            'asset' => $asset,
            'assetChoices' => $asset ? collect() : $this->service->getAssetChoices(),
        ]);
    }

    public function impairments(Request $request)
    {
        $this->shareCommon();
        $recognizedOnly = $request->boolean('recognized_only');

        return view('ahg-ipsas::impairments', [
            'impairments' => $this->service->getImpairments(['recognized_only' => $recognizedOnly]),
            'recognizedOnly' => $recognizedOnly,
        ]);
    }

    public function insurance(Request $request)
    {
        $this->shareCommon();
        $status = $request->query('status');

        return view('ahg-ipsas::insurance', [
            'policies' => $this->service->getInsurancePolicies(['status' => $status]),
            'currentStatus' => $status,
        ]);
    }

    public function reports(Request $request)
    {
        $year = (string) $request->query('year', date('Y'));
        if (! preg_match('/^\d{4}$/', $year)) {
            $year = date('Y');
        }

        $report = $request->query('report');
        if ($report) {
            return $this->downloadReport((string) $report, $year);
        }

        $this->shareCommon();

        return view('ahg-ipsas::reports', ['year' => $year, 'reports' => IpsasService::REPORTS]);
    }

    public function financialYear(Request $request)
    {
        $this->shareCommon();
        $year = (string) $request->query('year', date('Y'));
        if (! preg_match('/^\d{4}$/', $year)) {
            $year = date('Y');
        }

        return view('ahg-ipsas::financial-year', [
            'year' => $year,
            'summary' => $this->service->calculateFinancialYearSummary($year),
        ]);
    }

    public function config(Request $request)
    {
        $this->shareCommon();
        $opts = $this->service->options();

        if ($request->isMethod('post')) {
            $v = $request->validate([
                'organization_name' => 'nullable|string|max:255',
                'accounting_standard' => 'required|string|in:'.implode(',', array_keys($opts['accounting_standard'])),
                'default_currency' => 'required|string|size:3|alpha',
                'financial_year_start' => ['required', 'regex:/^(0[1-9]|1[0-2])-01$/'],
                'depreciation_policy' => 'required|string|in:'.implode(',', array_keys($opts['depreciation_policy'])),
                'valuation_frequency_years' => 'required|integer|min:1|max:10',
                'nominal_value' => 'required|numeric|min:0',
                'impairment_threshold_percent' => 'required|numeric|min:0|max:100',
                'insurance_review_months' => 'required|integer|min:1|max:24',
            ]);
            $v['default_currency'] = strtoupper($v['default_currency']);
            $v['organization_name'] = $v['organization_name'] ?? '';

            foreach ($v as $key => $value) {
                $this->service->setConfig($key, (string) $value);
            }

            return redirect()->route('ipsas.config')->with('success', __('Configuration saved'));
        }

        return view('ahg-ipsas::config', ['config' => $this->service->getAllConfig()]);
    }

    /** Stream one report as UTF-8 CSV (with BOM so spreadsheets read accents). */
    protected function downloadReport(string $report, string $year)
    {
        $data = $this->service->buildReport($report, $year);
        if ($data === null) {
            return redirect()->route('ipsas.reports')->with('error', __('Unknown report type'));
        }

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $data['headers']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $data['filename'].'.csv', [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }
}
