<?php

namespace AhgHeritageManage\Controllers;

use AhgHeritageManage\Services\HeritageAccountingService;
use AhgHeritageManage\Services\HeritageRollForwardService;
use AhgHeritageManage\Services\RollForwardProfileRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeritageRollForwardController extends Controller
{
    public function index(Request $request, HeritageRollForwardService $service, RollForwardProfileRegistry $profiles, HeritageAccountingService $accounting)
    {
        $v = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'standard_id' => 'nullable|integer|exists:heritage_accounting_standard,id',
            'class_id' => 'nullable|integer|exists:heritage_asset_class,id',
        ]);
        [$defaultFrom, $defaultTo] = $this->currentFinancialYear();
        // One date given: the other completes a twelve-month period around it.
        $from = $v['from'] ?? (isset($v['to']) ? date('Y-m-d', strtotime($v['to'].' -1 year +1 day')) : $defaultFrom);
        $to = $v['to'] ?? (isset($v['from']) ? date('Y-m-d', strtotime($v['from'].' +1 year -1 day')) : $defaultTo);
        if ($from > $to) {
            return redirect()->route('heritage.hreport.roll-forward')
                ->withErrors(['to' => __('The end of the period cannot be before its start.')]);
        }
        $standardId = isset($v['standard_id']) ? (int) $v['standard_id'] : null;
        $classId = isset($v['class_id']) ? (int) $v['class_id'] : null;
        $code = $standardId ? DB::table('heritage_accounting_standard')->where('id', $standardId)->value('code') : null;

        return view('ahg-heritage-manage::heritage-report.roll-forward', [
            'result' => $service->compute($from, $to, $standardId, $classId),
            'profile' => $profiles->for($code),
            'standards' => $accounting->getAccountingStandards(),
            'classes' => $accounting->getAssetClasses(),
            'currency' => $accounting->currency(),
            'filters' => ['from' => $from, 'to' => $to, 'standard_id' => $standardId, 'class_id' => $classId],
        ]);
    }

    /** The configured financial year containing today, else the calendar year. */
    private function currentFinancialYear(): array
    {
        $start = Schema::hasTable('heritage_institution_config')
            ? DB::table('heritage_institution_config')->whereNull('repository_id')->value('financial_year_start')
            : null;
        if (! is_string($start) || ! preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $start) || ! checkdate((int) substr($start, 0, 2), (int) substr($start, 3), 2023)) {
            return [date('Y-01-01'), date('Y-12-31')];
        }
        $year = date('m-d') >= $start ? (int) date('Y') : (int) date('Y') - 1;

        return ["{$year}-{$start}", date('Y-m-d', strtotime(($year + 1)."-{$start} -1 day"))];
    }
}
