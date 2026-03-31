<?php

/**
 * HeritageAdminController - Controller for Heratio
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

class HeritageAdminController extends Controller
{
    public function index() { return view('ahg-heritage-manage::heritage-admin.index', ['items' => collect()]); }
    public function regions() { $items = collect(); try { if (Schema::hasTable('heritage_region')) $items = DB::table('heritage_region')->orderBy('name')->get(); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-admin.regions', ['items' => $items]); }
    public function regionInfo(int $id) { return view('ahg-heritage-manage::heritage-admin.region-info', ['items' => collect()]); }
    public function ruleList() { $items = collect(); try { if (Schema::hasTable('heritage_rule')) $items = DB::table('heritage_rule')->orderBy('name')->get(); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-admin.rule-list', ['items' => $items]); }
    public function ruleAdd() { return view('ahg-heritage-manage::heritage-admin.rule-add', ['item' => null, 'formAction' => '#']); }
    public function ruleEdit(int $id) { $item = null; try { if (Schema::hasTable('heritage_rule')) $item = DB::table('heritage_rule')->where('id', $id)->first(); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-admin.rule-edit', ['item' => $item, 'formAction' => '#']); }
    public function standardList() { $items = collect(); try { if (Schema::hasTable('heritage_standard')) $items = DB::table('heritage_standard')->orderBy('name')->get(); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-admin.standard-list', ['items' => $items]); }
    public function standardAdd() { return view('ahg-heritage-manage::heritage-admin.standard-add', ['item' => null, 'formAction' => '#']); }
    public function standardEdit(int $id) { $item = null; try { if (Schema::hasTable('heritage_standard')) $item = DB::table('heritage_standard')->where('id', $id)->first(); } catch (\Exception $e) {} return view('ahg-heritage-manage::heritage-admin.standard-edit', ['item' => $item, 'formAction' => '#']); }
}
