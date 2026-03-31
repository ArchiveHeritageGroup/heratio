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
    public function nationalTreasuryReport() { return view('ahg-heritage-manage::grap-compliance.national-treasury-report', ['stats' => [], 'items' => collect()]); }
}
