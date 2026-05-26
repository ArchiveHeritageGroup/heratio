<?php

/**
 * OciMovementController - admin UI for the OCI / revaluation reserve ledger.
 *
 * Backs the heritage_oci_movement table. Shows the movement history, supports
 * filtering by period and asset, and lets admins record revaluations,
 * impairments, reversals, and disposals through OciMovementService.
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

use AhgHeritageManage\Services\OciMovementService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OciMovementController extends Controller
{
    public function __construct(protected OciMovementService $oci)
    {
    }

    public function index(Request $request)
    {
        $items = collect();
        $summary = null;
        try {
            if (Schema::hasTable('ahg_heritage_oci_movement')) {
                $q = DB::table('ahg_heritage_oci_movement as m')
                    ->leftJoin('ahg_valuer as v', 'v.id', '=', 'm.valuer_id')
                    ->select('m.*', 'v.name as valuer_name');
                if ($from = $request->query('from')) {
                    $q->where('m.valuation_date', '>=', $from);
                }
                if ($to = $request->query('to')) {
                    $q->where('m.valuation_date', '<=', $to);
                }
                if ($type = $request->query('movement_type')) {
                    $q->where('m.movement_type', $type);
                }
                if ($asset = $request->query('heritage_asset_id')) {
                    $q->where('m.heritage_asset_id', (int) $asset);
                }
                $items = $q->orderByDesc('m.valuation_date')->orderByDesc('m.id')
                    ->paginate(25)->appends($request->query());

                $from = $request->query('from') ?: date('Y-01-01');
                $to = $request->query('to') ?: date('Y-m-d');
                $summary = $this->oci->summariseForPeriod($from, $to);
            }
        } catch (\Exception $e) {
        }
        return view('ahg-heritage-manage::oci.index', [
            'items'   => $items,
            'summary' => $summary,
            'filters' => $request->only(['from', 'to', 'movement_type', 'heritage_asset_id']),
        ]);
    }

    public function create()
    {
        $valuers = collect();
        try {
            if (Schema::hasTable('ahg_valuer')) {
                $valuers = DB::table('ahg_valuer')->where('active', 1)->orderBy('name')->get();
            }
        } catch (\Exception $e) {
        }
        return view('ahg-heritage-manage::oci.add', [
            'valuers'    => $valuers,
            'formAction' => route('heritage.oci.store'),
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'heritage_asset_id'     => 'required|integer',
            'information_object_id' => 'nullable|integer',
            'movement_type'         => 'required|in:revaluation,impairment,reversal,disposal',
            'previous_value'        => 'nullable|numeric',
            'new_value'             => 'nullable|numeric',
            'amount'                => 'nullable|numeric',
            'disposal_proceeds'     => 'nullable|numeric',
            'carrying_at_disposal'  => 'nullable|numeric',
            'valuation_date'        => 'required|date',
            'valuer_id'             => 'nullable|integer',
            'valuation_method'      => 'nullable|string|max:64',
            'reason'                => 'nullable|string',
            'currency'              => 'nullable|string|size:3',
        ]);

        $currency = $v['currency'] ?? 'ZAR';
        $userId = optional($request->user())->id;
        $ids = [];

        switch ($v['movement_type']) {
            case 'revaluation':
                $ids = $this->oci->recordRevaluation(
                    (int) $v['heritage_asset_id'],
                    (float) ($v['previous_value'] ?? 0),
                    (float) ($v['new_value'] ?? 0),
                    $v['valuation_date'],
                    $v['valuer_id'] ?? null,
                    $v['valuation_method'] ?? null,
                    $v['reason'] ?? null,
                    $v['information_object_id'] ?? null,
                    $userId,
                    $currency
                );
                break;
            case 'impairment':
                $ids = $this->oci->recordImpairment(
                    (int) $v['heritage_asset_id'],
                    (float) ($v['amount'] ?? 0),
                    $v['valuation_date'],
                    $v['reason'] ?? null,
                    $v['information_object_id'] ?? null,
                    $userId,
                    $currency
                );
                break;
            case 'reversal':
                $ids = $this->oci->recordReversal(
                    (int) $v['heritage_asset_id'],
                    (float) ($v['amount'] ?? 0),
                    $v['valuation_date'],
                    $v['reason'] ?? null,
                    $v['information_object_id'] ?? null,
                    $userId,
                    $currency
                );
                break;
            case 'disposal':
                $ids = $this->oci->recordDisposal(
                    (int) $v['heritage_asset_id'],
                    (float) ($v['disposal_proceeds'] ?? 0),
                    (float) ($v['carrying_at_disposal'] ?? 0),
                    $v['valuation_date'],
                    $v['reason'] ?? null,
                    $v['information_object_id'] ?? null,
                    $userId,
                    $currency
                );
                break;
        }

        return redirect()->route('heritage.oci.index')
            ->with('success', __('Recorded :count OCI movement row(s).', ['count' => count($ids)]));
    }
}
