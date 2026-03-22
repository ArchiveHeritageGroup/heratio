<?php

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
