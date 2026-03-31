<?php

/**
 * EmbargoController - Controller for Heratio
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



namespace AhgRightsHolderManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmbargoController extends Controller
{
    public function index()
    {
        $activeEmbargoes = collect();
        $expiringEmbargoes = collect();

        if (Schema::hasTable('embargo')) {
            $activeEmbargoes = DB::table('embargo')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            $expiringEmbargoes = DB::table('embargo')
                ->where('is_active', true)
                ->whereNotNull('end_date')
                ->where('end_date', '>=', now())
                ->where('end_date', '<=', now()->addDays(30))
                ->orderBy('end_date')
                ->get()
                ->map(function ($e) {
                    $e->days_remaining = (int) now()->diffInDays($e->end_date, false);
                    return $e;
                });
        }

        return view('ahg-rights-holder-manage::embargo.index', compact('activeEmbargoes', 'expiringEmbargoes'));
    }

    public function create(int $objectId)
    {
        $resource = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.id', $objectId)
            ->where('information_object_i18n.culture', app()->getLocale())
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug',
                     'information_object.lft', 'information_object.rgt')
            ->first();

        if (!$resource) abort(404);

        $descendantCount = DB::table('information_object')
            ->where('lft', '>', $resource->lft ?? 0)
            ->where('rgt', '<', $resource->rgt ?? 0)
            ->count();

        return view('ahg-rights-holder-manage::embargo.add', compact('resource', 'objectId', 'descendantCount'));
    }

    public function store(Request $request, int $objectId)
    {
        $request->validate([
            'embargo_type' => 'required|string',
            'start_date' => 'required|date',
        ]);

        if (Schema::hasTable('embargo')) {
            DB::table('embargo')->insert([
                'object_id' => $objectId,
                'embargo_type' => $request->input('embargo_type'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'is_perpetual' => $request->boolean('is_perpetual'),
                'is_active' => true,
                'reason' => $request->input('reason'),
                'public_message' => $request->input('public_message'),
                'notes' => $request->input('notes'),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('embargo.index')->with('success', 'Embargo created successfully.');
    }

    public function show(int $id)
    {
        $embargo = [];
        if (Schema::hasTable('embargo')) {
            $row = DB::table('embargo')->where('id', $id)->first();
            if ($row) {
                $embargo = (array) $row;
                $embargo['status'] = $row->is_active ? 'active' : 'lifted';
                $embargo['exceptions'] = [];
                $embargo['audit_log'] = [];
            }
        }
        if (empty($embargo)) abort(404);

        return view('ahg-rights-holder-manage::embargo.view', compact('embargo'));
    }

    public function liftForm(int $id)
    {
        $embargo = null;
        $resource = null;
        if (Schema::hasTable('embargo')) {
            $embargo = DB::table('embargo')->where('id', $id)->first();
            if ($embargo) {
                $resource = DB::table('information_object')
                    ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                    ->join('slug', 'information_object.id', '=', 'slug.object_id')
                    ->where('information_object.id', $embargo->object_id)
                    ->where('information_object_i18n.culture', app()->getLocale())
                    ->select('information_object_i18n.title', 'slug.slug')
                    ->first();
            }
        }
        if (!$embargo) abort(404);

        return view('ahg-rights-holder-manage::embargo.lift', compact('embargo', 'resource'));
    }

    public function lift(Request $request, int $id)
    {
        if (Schema::hasTable('embargo')) {
            DB::table('embargo')->where('id', $id)->update([
                'is_active' => false,
                'lift_reason' => $request->input('lift_reason'),
                'lifted_at' => now(),
                'lifted_by' => auth()->id(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('embargo.index')->with('success', 'Embargo lifted successfully.');
    }
}
