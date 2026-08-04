<?php

/**
 * HeritageAccountingController - Controller for Heratio
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
        $culture = app()->getLocale();
        $items = collect();
        $columns = ['Asset', 'Class', 'Status', 'Carrying value', 'Recognised'];

        try {
            if (Schema::hasTable('heritage_asset')) {
                // Resolve the item NAME from the linked information_object title
                // (heritage_asset has no name column of its own) + its slug so the
                // browse can link each row to the record, and the asset-class name.
                $items = DB::table('heritage_asset as ha')
                    ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                        $j->on('i18n.id', '=', 'ha.information_object_id')
                            ->where('i18n.culture', '=', $culture);
                    })
                    ->leftJoin('slug as s', 's.object_id', '=', 'ha.information_object_id')
                    ->leftJoin('heritage_asset_class as ac', 'ac.id', '=', 'ha.asset_class_id')
                    ->orderByDesc('ha.created_at')
                    ->select(
                        'ha.id',
                        'ha.information_object_id',
                        's.slug',
                        'i18n.title as item_name',
                        'ac.name as class_name',
                        'ha.recognition_status',
                        'ha.current_carrying_amount',
                        'ha.recognition_date'
                    )
                    ->paginate(25);
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

    /**
     * Inline status change from the browse grid (AJAX). Whitelisted to the same
     * recognition_status values the add form offers.
     */
    public function updateStatus(Request $request, int $id)
    {
        $allowed = ['pending', 'recognised', 'not_recognised'];
        $status = (string) $request->input('recognition_status');
        if (! in_array($status, $allowed, true)) {
            return response()->json(['ok' => false, 'error' => 'Invalid status.'], 422);
        }
        if (! Schema::hasTable('heritage_asset')
            || ! DB::table('heritage_asset')->where('id', $id)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Asset not found.'], 404);
        }

        $update = ['recognition_status' => $status, 'updated_at' => now()];
        // Recognising an asset stamps the recognition date if not already set;
        // moving off "recognised" leaves history intact.
        if ($status === 'recognised') {
            $current = DB::table('heritage_asset')->where('id', $id)->value('recognition_date');
            if (empty($current)) {
                $update['recognition_date'] = now()->toDateString();
            }
        }
        if (Schema::hasColumn('heritage_asset', 'updated_by') && auth()->id()) {
            $update['updated_by'] = auth()->id();
        }
        DB::table('heritage_asset')->where('id', $id)->update($update);

        $row = DB::table('heritage_asset')->where('id', $id)->first(['recognition_status', 'recognition_date']);

        return response()->json([
            'ok' => true,
            'recognition_status' => $row->recognition_status,
            'recognition_date' => $row->recognition_date,
            'label' => ucwords(str_replace('_', ' ', (string) $row->recognition_status)),
        ]);
    }
    public function edit(int $id) { $asset = null; try { if (Schema::hasTable('heritage_asset')) $asset = DB::table('heritage_asset')->where('id', $id)->first(); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-accounting.edit', ['asset' => $asset, 'fields' => [], 'formAction' => route('heritage.accounting.update', $id)]); }
    public function update(Request $request, int $id) { return redirect()->route('heritage.accounting.view', $id)->with('success', 'Asset updated.'); }
    /**
     * #1454 - the full GRAP heritage-asset accounting record (all recognition,
     * measurement, valuation, depreciation, impairment, derecognition,
     * restriction, insurance and location/condition fields), grouped by GRAP
     * lifecycle. This is the rich replacement for the thin Spectrum grap
     * dashboard, which now redirects here.
     */
    public function view(int $id)
    {
        $asset = null;
        if (Schema::hasTable('heritage_asset')) {
            $asset = DB::table('heritage_asset as ha')
                ->leftJoin('heritage_asset_class as ac', 'ac.id', '=', 'ha.asset_class_id')
                ->leftJoin('heritage_accounting_standard as st', 'st.id', '=', 'ha.accounting_standard_id')
                ->where('ha.id', $id)
                ->select('ha.*', 'ac.name as asset_class_name', 'st.code as standard_code', 'st.name as standard_name')
                ->first();
        }
        if (! $asset) {
            abort(404);
        }

        $io = null;
        $ioId = $asset->information_object_id ?: ($asset->object_id ?? null);
        if ($ioId) {
            $culture = app()->getLocale();
            $io = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
                })
                ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
                ->where('io.id', $ioId)
                ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
                ->first();
        }

        return view('ahg-heritage-manage::heritage-accounting.view', compact('asset', 'io'));
    }

    /**
     * #1454 - resolve an information-object id to its heritage-asset record and
     * show the full GRAP record. When no accounting record exists yet, hand off
     * to the Add form pre-linked to the object. This is the target the legacy
     * /admin/spectrum/grap-dashboard?slug= surface redirects to.
     */
    public function viewByObject(int $id)
    {
        $asset = null;
        if (Schema::hasTable('heritage_asset')) {
            $asset = DB::table('heritage_asset')
                ->where(function ($q) use ($id) {
                    $q->where('information_object_id', $id)->orWhere('object_id', $id);
                })
                ->orderByDesc('id')
                ->first();
        }
        if ($asset) {
            return redirect()->route('heritage.accounting.view', $asset->id);
        }
        if (\Illuminate\Support\Facades\Route::has('heritage.accounting.add')) {
            return redirect()->route('heritage.accounting.add', ['io_id' => $id])
                ->with('info', __('No heritage-asset accounting record exists yet for this object. You can create one here.'));
        }
        abort(404);
    }
    public function addValuation(int $id = null) { return view('ahg-heritage-manage::heritage-accounting.add-valuation', ['asset' => null, 'fields' => [], 'formAction' => '#']); }
    public function addImpairment(int $id = null) { return view('ahg-heritage-manage::heritage-accounting.add-impairment', ['asset' => null, 'fields' => [], 'formAction' => '#']); }
    public function addJournal(int $id = null) { return view('ahg-heritage-manage::heritage-accounting.add-journal', ['asset' => null, 'fields' => [], 'formAction' => '#']); }
    public function addMovement(int $id = null) { return view('ahg-heritage-manage::heritage-accounting.add-movement', ['asset' => null, 'fields' => [], 'formAction' => '#']); }
    public function settings() { $items = collect(); $stats = []; return view('ahg-heritage-manage::heritage-accounting.settings', compact('items', 'stats')); }
}
