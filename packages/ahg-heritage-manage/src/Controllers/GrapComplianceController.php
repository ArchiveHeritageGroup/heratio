<?php

/**
 * GrapComplianceController - Controller for Heratio
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



namespace AhgHeritageManage\Controllers;

use AhgHeritageManage\Services\GrapComplianceService;
use AhgHeritageManage\Services\HeritageAccountingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * GRAP 103 compliance module (per-market): dashboard, single-asset check,
 * batch check and the National Treasury register.
 */
class GrapComplianceController extends Controller
{
    public function __construct(
        protected GrapComplianceService $grap,
        protected HeritageAccountingService $accounting
    ) {
    }

    public function dashboard()
    {
        $standardId = $this->grap->getStandardId();
        $stats = $this->accounting->getDashboardStats(null, $standardId);
        $complianceSummary = $this->grap->getComplianceSummary();
        $recentAssets = $standardId ? $this->accounting->browse(['standard_id' => $standardId], 10)['items'] : collect();
        $compliance = [];
        foreach ($recentAssets as $a) {
            $compliance[$a->id] = $this->grap->checkCompliance($a->id)['summary'] ?? null;
        }

        return view('ahg-heritage-manage::grap-compliance.dashboard', compact('stats', 'complianceSummary', 'recentAssets', 'standardId', 'compliance'));
    }

    public function check(?int $id = null)
    {
        if (! $id) {
            return redirect()->route('heritage.grap.batch-check');
        }
        $asset = $this->accounting->getAsset($id) ?? abort(404);
        $complianceResult = $this->grap->checkCompliance($asset->id);

        return view('ahg-heritage-manage::grap-compliance.check', compact('asset', 'complianceResult'));
    }

    public function batchCheck()
    {
        $standardId = $this->grap->getStandardId();
        $results = [];
        if ($standardId) {
            foreach ($this->accounting->browse(['standard_id' => $standardId], 1000)['items'] as $asset) {
                $results[] = ['asset' => $asset, 'compliance' => $this->grap->checkCompliance($asset->id)];
            }
        }

        return view('ahg-heritage-manage::grap-compliance.batch-check', compact('results'));
    }

    /**
     * GRAP 103 National Treasury register for a financial year: summary by
     * asset class and the detailed register. ?export=csv downloads it.
     */
    public function nationalTreasuryReport(Request $request)
    {
        $request->validate(['fy' => 'nullable|integer|min:1900|max:2999']);
        $financialYear = (int) ($request->query('fy') ?: date('Y'));
        $standardId = $this->grap->getStandardId();

        $assets = $standardId ? $this->accounting->browse(['standard_id' => $standardId], 1000)['items'] : collect();
        $byClass = ! $standardId ? collect() : DB::table('heritage_asset as ha')
            ->leftJoin('heritage_asset_class as hc', 'ha.asset_class_id', '=', 'hc.id')
            ->where('ha.accounting_standard_id', $standardId)
            ->groupBy('hc.id', 'hc.name')
            ->get([
                'hc.name as class_name',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(ha.current_carrying_amount) as total_value'),
                DB::raw('SUM(ha.impairment_loss) as total_impairment'),
                DB::raw('SUM(ha.revaluation_surplus) as total_surplus'),
            ]);

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($assets) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Asset ID', 'Description', 'Class', 'Status', 'Basis', 'Cost', 'Carrying Amount', 'Acquisition Date']);
                foreach ($assets as $a) {
                    fputcsv($out, [
                        $a->object_identifier, $a->object_title, $a->class_name, $a->recognition_status,
                        $a->measurement_basis, $a->acquisition_cost, $a->current_carrying_amount, $a->acquisition_date,
                    ]);
                }
                fclose($out);
            }, 'grap103-register-' . $financialYear . '.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
        }

        return view('ahg-heritage-manage::grap-compliance.national-treasury-report', compact('financialYear', 'assets', 'byClass', 'standardId'));
    }
}
