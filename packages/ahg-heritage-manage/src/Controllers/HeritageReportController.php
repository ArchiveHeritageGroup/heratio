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

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeritageReportController extends Controller
{
    public function index() { return view('ahg-heritage-manage::heritage-report.index', ['items' => collect()]); }
    public function assetRegister() { $items = collect(); try { if (Schema::hasTable('heritage_asset')) $items = DB::table('heritage_asset')->orderByDesc('created_at')->paginate(25); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-report.asset-register', compact('items')); }
    public function movement() { $items = collect(); try { if (Schema::hasTable('heritage_asset_movement')) $items = DB::table('heritage_asset_movement')->orderByDesc('created_at')->paginate(25); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-report.movement', compact('items')); }
    public function valuation() { $items = collect(); try { if (Schema::hasTable('heritage_asset_valuation')) $items = DB::table('heritage_asset_valuation')->orderByDesc('created_at')->paginate(25); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-report.valuation', compact('items')); }
}
