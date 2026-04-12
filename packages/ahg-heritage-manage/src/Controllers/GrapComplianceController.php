<?php

/**
 * GrapComplianceController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GrapComplianceController extends Controller
{
    public function dashboard()
    {
        $stats = ['total' => 0];
        $items = collect();

        try {
            if (Schema::hasTable('heritage_asset')) {
                $stats['total'] = DB::table('heritage_asset')->count();
            }
        } catch (\Exception $e) {}

        return view('ahg-heritage-manage::grap-compliance.dashboard', compact('stats', 'items'));
    }

    public function batchCheck() { return view('ahg-heritage-manage::grap-compliance.batch-check', ['stats' => [], 'items' => collect()]); }
    public function check(int $id = null) { return view('ahg-heritage-manage::grap-compliance.check', ['stats' => [], 'items' => collect()]); }

    /**
     * GRAP 103 National Treasury Report.
     * Builds the dataset that South African public-sector entities submit to National Treasury
     * for heritage asset disclosures (capitalised + non-capitalised assets, current carrying
     * amount, accumulated depreciation, valuations).
     */
    public function nationalTreasuryReport(Request $request)
    {
        $stats = ['total_assets' => 0, 'capitalised' => 0, 'non_capitalised' => 0, 'total_value_zar' => 0];
        $items = collect();
        $columns = ['ID', 'Asset', 'Class', 'Standard', 'Status', 'Carrying Amount (R)', 'Last Valuation', 'Date'];

        if (!Schema::hasTable('heritage_asset')) {
            return view('ahg-heritage-manage::grap-compliance.national-treasury-report', compact('stats', 'items', 'columns'));
        }

        $statusFilter = $request->input('status');
        $standardFilter = $request->input('standard');

        $stats['total_assets'] = DB::table('heritage_asset')->count();
        $stats['capitalised'] = DB::table('heritage_asset')->where('recognition_status', 'recognised')->count();
        $stats['non_capitalised'] = DB::table('heritage_asset')->whereIn('recognition_status', ['pending', 'unrecognised'])->count();
        $stats['total_value_zar'] = (float) DB::table('heritage_asset')->sum('current_carrying_amount');

        $query = DB::table('heritage_asset as a')
            ->leftJoin('heritage_asset_class as ac', 'a.asset_class_id', '=', 'ac.id')
            ->leftJoin('heritage_accounting_standard as s', 'a.accounting_standard_id', '=', 's.id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'a.information_object_id')
                    ->where('ioi.culture', '=', app()->getLocale());
            })
            ->select(
                'a.id',
                'ioi.title as asset_title',
                'ac.name as class_name',
                's.code as standard_code',
                'a.recognition_status',
                'a.current_carrying_amount',
                'a.last_valuation_amount',
                'a.last_valuation_date'
            );

        if ($statusFilter) {
            $query->where('a.recognition_status', $statusFilter);
        }
        if ($standardFilter) {
            $query->where('s.code', $standardFilter);
        }

        $items = $query->orderByDesc('a.current_carrying_amount')->limit(500)->get();
        $standards = DB::table('heritage_accounting_standard')->where('is_active', 1)->orderBy('sort_order')->get(['code', 'name']);

        return view('ahg-heritage-manage::grap-compliance.national-treasury-report', compact('stats', 'items', 'columns', 'statusFilter', 'standardFilter', 'standards'));
    }
}
