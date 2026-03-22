<?php

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
    public function nationalTreasuryReport() { return view('ahg-heritage-manage::grap-compliance.national-treasury-report', ['stats' => [], 'items' => collect()]); }
}
