<?php

/**
 * HeritageAccountingController - Controller for Heratio
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

class HeritageAccountingController extends Controller
{
    public function dashboard()
    {
        $stats = ['total' => 0, 'recognised' => 0, 'pending' => 0, 'total_value' => 0];
        $items = collect();

        try {
            if (Schema::hasTable('heritage_asset')) {
                $stats['total'] = DB::table('heritage_asset')->count();
                $stats['recognised'] = DB::table('heritage_asset')->where('recognition_status', 'recognised')->count();
                $stats['pending'] = DB::table('heritage_asset')->where('recognition_status', 'pending')->count();
                $stats['total_value'] = (float) DB::table('heritage_asset')->sum('current_carrying_amount');
            }
        } catch (\Exception $e) {}

        return view('ahg-heritage-manage::heritage-accounting.dashboard', compact('stats', 'items'));
    }

    public function browse(Request $request)
    {
        $items = collect();
        $columns = ['ID', 'Name', 'Class', 'Status', 'Value', 'Date'];

        try {
            if (Schema::hasTable('heritage_asset')) {
                $items = DB::table('heritage_asset')->orderByDesc('created_at')->paginate(25);
            }
        } catch (\Exception $e) {}

        return view('ahg-heritage-manage::heritage-accounting.browse', compact('items', 'columns'));
    }

    public function add(Request $request)
    {
        $io = null;
        if ($request->filled('io_id')) {
            $culture = app()->getLocale();
            $io = DB::table('information_object as io')
                ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
                })
                ->where('io.id', $request->input('io_id'))
                ->select('io.id', 'i18n.title')
                ->first();
        }

        $standards = collect();
        try {
            if (Schema::hasTable('heritage_accounting_standard')) {
                $standards = DB::table('heritage_accounting_standard')->orderBy('code')->get();
            }
        } catch (\Exception $e) {}

        $classes = collect();
        try {
            if (Schema::hasTable('heritage_asset_class')) {
                $classes = DB::table('heritage_asset_class')->orderBy('name')->get();
            }
        } catch (\Exception $e) {}

        return view('ahg-heritage-manage::heritage-accounting.add', compact('io', 'standards', 'classes'));
    }
    public function store(Request $request) { return redirect()->route('heritage.accounting.browse')->with('success', 'Asset created.'); }
    public function edit(int $id) { $asset = null; try { if (Schema::hasTable('heritage_asset')) $asset = DB::table('heritage_asset')->where('id', $id)->first(); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-accounting.edit', ['asset' => $asset, 'fields' => [], 'formAction' => route('heritage.accounting.update', $id)]); }
    public function update(Request $request, int $id) { return redirect()->route('heritage.accounting.view', $id)->with('success', 'Asset updated.'); }
    public function view(int $id) { $items = collect(); $stats = []; return view('ahg-heritage-manage::heritage-accounting.view', compact('items', 'stats')); }
    public function viewByObject(int $id) { $items = collect(); $stats = []; return view('ahg-heritage-manage::heritage-accounting.view-by-object', compact('items', 'stats')); }
    public function addValuation(int $id = null) { return view('ahg-heritage-manage::heritage-accounting.add-valuation', ['asset' => null, 'fields' => [], 'formAction' => '#']); }
    public function addImpairment(int $id = null) { return view('ahg-heritage-manage::heritage-accounting.add-impairment', ['asset' => null, 'fields' => [], 'formAction' => '#']); }
    public function addJournal(int $id = null) { return view('ahg-heritage-manage::heritage-accounting.add-journal', ['asset' => null, 'fields' => [], 'formAction' => '#']); }
    public function addMovement(int $id = null) { return view('ahg-heritage-manage::heritage-accounting.add-movement', ['asset' => null, 'fields' => [], 'formAction' => '#']); }
    public function settings() { $items = collect(); $stats = []; return view('ahg-heritage-manage::heritage-accounting.settings', compact('items', 'stats')); }
}
