<?php

namespace AhgIpsas\Controllers;

use AhgIpsas\Services\IpsasService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IpsasController extends Controller
{
    protected IpsasService $service;

    public function __construct()
    {
        $this->service = new IpsasService();
    }

    public function index()
    {
        $stats = $this->service->getDashboardStats();
        $compliance = $this->service->getComplianceStatus();
        $config = $this->service->getAllConfig();

        $recentAssets = \Illuminate\Support\Facades\DB::table('ipsas_heritage_asset')
            ->orderByDesc('created_at')->limit(5)->get();

        $expiringInsurance = \Illuminate\Support\Facades\DB::table('ipsas_insurance')
            ->where('status', 'active')
            ->whereRaw('coverage_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
            ->orderBy('coverage_end')->limit(5)->get();

        return view('ahg-ipsas::index', compact('stats', 'compliance', 'config', 'recentAssets', 'expiringInsurance'));
    }

    public function assets(Request $request)
    {
        $filters = [
            'category_id' => $request->get('category'),
            'status' => $request->get('status'),
            'valuation_basis' => $request->get('basis'),
            'search' => $request->get('q'),
        ];

        $assets = $this->service->getAssets($filters);
        $categories = $this->service->getCategories();

        return view('ahg-ipsas::assets', compact('assets', 'categories', 'filters'));
    }

    public function assetCreate(Request $request)
    {
        $categories = $this->service->getCategories();

        if ($request->isMethod('post')) {
            $id = $this->service->createAsset($request->only([
                'information_object_id', 'category_id', 'title', 'description',
                'location', 'repository_id', 'acquisition_date', 'acquisition_method',
                'acquisition_source', 'acquisition_cost', 'acquisition_currency',
                'valuation_basis', 'current_value', 'condition_rating',
            ]) + ['user_id' => auth()->id()]);

            return redirect()->route('ipsas.asset.view', $id)->with('notice', 'Asset registered');
        }

        return view('ahg-ipsas::asset-create', compact('categories'));
    }

    public function assetView(int $id)
    {
        $asset = $this->service->getAsset($id);
        abort_unless($asset, 404, 'Asset not found');

        $valuations = $this->service->getAssetValuations($id);
        $impairments = $this->service->getImpairments(['asset_id' => $id]);

        return view('ahg-ipsas::asset-view', compact('asset', 'valuations', 'impairments'));
    }

    public function assetEdit(Request $request, int $id)
    {
        $asset = $this->service->getAsset($id);
        abort_unless($asset, 404, 'Asset not found');

        $categories = $this->service->getCategories();

        if ($request->isMethod('post')) {
            $this->service->updateAsset($id, $request->only([
                'title', 'description', 'location', 'status',
                'condition_rating', 'risk_level', 'risk_notes',
            ]), auth()->id());

            return redirect()->route('ipsas.asset.view', $id)->with('notice', 'Asset updated');
        }

        return view('ahg-ipsas::asset-edit', compact('asset', 'categories'));
    }

    public function valuations(Request $request)
    {
        $filters = [
            'type' => $request->get('type'),
            'year' => $request->get('year', date('Y')),
        ];

        $valuations = $this->service->getValuations($filters);

        return view('ahg-ipsas::valuations', compact('valuations', 'filters'));
    }

    public function valuationCreate(Request $request)
    {
        $assetId = $request->get('asset_id');
        $asset = $assetId ? $this->service->getAsset((int) $assetId) : null;

        if ($request->isMethod('post')) {
            $this->service->createValuation($request->only([
                'asset_id', 'valuation_date', 'valuation_type', 'valuation_basis',
                'previous_value', 'new_value', 'valuer_name', 'valuer_qualification',
                'valuer_type', 'valuation_method', 'market_evidence',
                'documentation_ref', 'notes',
            ]) + ['user_id' => auth()->id()]);

            return redirect()->route('ipsas.asset.view', $request->get('asset_id'))->with('notice', 'Valuation recorded');
        }

        return view('ahg-ipsas::valuation-create', compact('asset'));
    }

    public function impairments(Request $request)
    {
        $impairments = $this->service->getImpairments([
            'recognized_only' => $request->get('recognized_only'),
        ]);

        return view('ahg-ipsas::impairments', compact('impairments'));
    }

    public function insurance(Request $request)
    {
        $policies = $this->service->getInsurancePolicies([
            'status' => $request->get('status'),
        ]);
        $currentStatus = $request->get('status');

        return view('ahg-ipsas::insurance', compact('policies', 'currentStatus'));
    }

    public function reports(Request $request)
    {
        $year = $request->get('year', date('Y'));

        $report = $request->get('report');
        if ($report) {
            return $this->generateReport($report, $year);
        }

        return view('ahg-ipsas::reports', compact('year'));
    }

    public function financialYear(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $summary = $this->service->calculateFinancialYearSummary($year);

        return view('ahg-ipsas::financial-year', compact('year', 'summary'));
    }

    public function config(Request $request)
    {
        if ($request->isMethod('post')) {
            $configs = [
                'default_currency', 'financial_year_start', 'depreciation_policy',
                'valuation_frequency_years', 'insurance_review_months',
                'impairment_threshold_percent', 'nominal_value',
                'organization_name', 'accounting_standard',
            ];

            foreach ($configs as $key) {
                $value = $request->get($key);
                if ($value !== null) {
                    $this->service->setConfig($key, $value);
                }
            }

            return redirect()->route('ipsas.config')->with('notice', 'Configuration saved');
        }

        $config = $this->service->getAllConfig();

        return view('ahg-ipsas::config', compact('config'));
    }

    /**
     * Admin dashboard for IPSAS configuration.
     */
    public function admin()
    {
        $config = $this->service->getAllConfig();
        $stats = $this->service->getDashboardStats();

        return view('ahg-ipsas::admin', compact('config', 'stats'));
    }

    /**
     * Handle POST actions for IPSAS.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');

        if ($action === 'delete_asset') {
            $id = (int) $request->get('id');
            $this->service->deleteAsset($id);

            return redirect()->route('ipsas.assets')->with('notice', 'Asset deleted.');
        }

        if ($action === 'recalculate') {
            $year = $request->get('year', date('Y'));
            $this->service->calculateFinancialYearSummary($year);

            return redirect()->route('ipsas.financialYear', ['year' => $year])->with('notice', 'Financial year recalculated.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }

    protected function generateReport(string $report, string $year)
    {
        $assets = $this->service->getAssets([]);
        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['Asset #', 'Title', 'Category', 'Current Value', 'Status']);

        foreach ($assets as $a) {
            fputcsv($output, [$a->asset_number ?? '', $a->title ?? '', $a->category_name ?? '', $a->current_value ?? 0, $a->status ?? '']);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$report}_{$year}.csv\"",
        ]);
    }
}
