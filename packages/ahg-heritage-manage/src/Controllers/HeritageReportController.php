<?php

/**
 * HeritageReportController - Controller for Heratio
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

use AhgCore\Support\DropdownOptions;
use AhgHeritageManage\Services\HeritageAccountingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use AhgCore\Support\I18nFallback;

/**
 * Heritage asset reports: asset register, valuation report and movement
 * report, plus a per-standard entry point on the index.
 */
class HeritageReportController extends Controller
{
    public function __construct(protected HeritageAccountingService $accounting)
    {
    }

    public function index()
    {
        return view('ahg-heritage-manage::heritage-report.index', [
            'standards' => $this->accounting->getAccountingStandards(),
        ]);
    }

    public function assetRegister(Request $request)
    {
        $filters = [
            'standard_id' => $request->query('standard_id'),
            'class_id' => $request->query('class_id'),
            'recognition_status' => $request->query('status'),
        ];
        $assets = $this->accounting->browse(array_filter($filters), 1000)['items'];

        return view('ahg-heritage-manage::heritage-report.asset-register', [
            'assets' => $assets,
            'filters' => $filters,
            'standards' => $this->accounting->getAccountingStandards(),
            'classes' => $this->accounting->getAssetClasses(),
            'statusOptions' => DropdownOptions::resolve('recognition_status', [
                'recognised' => 'Recognised', 'not_recognised' => 'Not Recognised', 'pending' => 'Pending',
            ]),
            'currency' => $this->accounting->currency(),
        ]);
    }

    /** Assets that have been valued, newest valuation first. */
    public function valuation()
    {
        $culture = app()->getLocale();
        $assets = collect();
        if (Schema::hasTable('heritage_asset')) {
            $assets = DB::table('heritage_asset as ha')
                ->leftJoin('heritage_asset_class as hc', 'ha.asset_class_id', '=', 'hc.id')
                ->leftJoin('information_object as io', 'io.id', '=', DB::raw('COALESCE(ha.information_object_id, ha.object_id)'))
                ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as ioi', 'io.id', $culture))
                ->whereNotNull('ha.last_valuation_date')
                ->orderByDesc('ha.last_valuation_date')
                ->limit(100)
                ->get(['ha.*', 'hc.name as class_name', 'io.identifier', I18nFallback::col('ioi', 'title', 'title')]);
        }

        return view('ahg-heritage-manage::heritage-report.valuation', [
            'assets' => $assets,
            'currency' => $this->accounting->currency(),
        ]);
    }

    /** Movement register entries in a date range (default: this month). */
    public function movement(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $from = $request->query('from') ?: date('Y-m-01');
        $to = $request->query('to') ?: date('Y-m-d');
        $culture = app()->getLocale();

        $movements = collect();
        if (Schema::hasTable('heritage_movement_register')) {
            $movements = DB::table('heritage_movement_register as m')
                ->join('heritage_asset as ha', 'm.heritage_asset_id', '=', 'ha.id')
                ->leftJoin('information_object as io', 'io.id', '=', DB::raw('COALESCE(ha.information_object_id, ha.object_id)'))
                ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                })
                ->whereBetween('m.movement_date', [$from, $to])
                ->orderByDesc('m.movement_date')
                ->get(['m.*', 'io.identifier', 'ioi.title']);
        }

        return view('ahg-heritage-manage::heritage-report.movement', [
            'movements' => $movements,
            'from' => $from,
            'to' => $to,
            'movementTypes' => DropdownOptions::resolve('movement_type', []),
        ]);
    }
}
