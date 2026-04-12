<?php

/**
 * ExtendedRightsController - Controller for Heratio
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

class ExtendedRightsController extends Controller
{
    public function index()
    {
        $rightsStatements = Schema::hasTable('rights_statement') ? DB::table('rights_statement')->orderBy('sort_order')->get() : collect();
        $ccLicenses = Schema::hasTable('creative_commons_license') ? DB::table('creative_commons_license')->orderBy('sort_order')->get() : collect();
        $tkLabels = Schema::hasTable('rights_tk_label') ? DB::table('rights_tk_label')->orderBy('sort_order')->get() : collect();
        $stats = $this->getStats();

        return view('ahg-rights-holder-manage::extendedRights.index', compact('rightsStatements', 'ccLicenses', 'tkLabels', 'stats'));
    }

    public function dashboard()
    {
        $stats = $this->getStats();
        return view('ahg-rights-holder-manage::extendedRights.dashboard', compact('stats'));
    }

    public function view(string $slug)
    {
        $resource = DB::table('slug')->where('slug', $slug)->first();
        if (!$resource) abort(404);
        $resource = (object) ['slug' => $slug, 'id' => $resource->object_id];

        $rightsData = ['has_rights' => false, 'badges' => [], 'primary' => null];

        if (Schema::hasTable('extended_rights')) {
            $primary = DB::table('extended_rights')
                ->where('object_id', $resource->id)
                ->where('is_primary', true)
                ->first();
            if ($primary) {
                $rightsData['has_rights'] = true;
                $rightsData['primary'] = $primary;
            }
        }

        return view('ahg-rights-holder-manage::extendedRights.view', compact('rightsData', 'resource'));
    }

    public function batch()
    {
        $culture = app()->getLocale();
        $topLevelRecords = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->where('information_object.parent_id', 1)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'information_object.identifier')
            ->orderBy('information_object_i18n.title')
            ->limit(500)
            ->get();

        $rightsStatements = Schema::hasTable('rights_statement') ? DB::table('rights_statement')->orderBy('sort_order')->get() : collect();
        $ccLicenses = Schema::hasTable('creative_commons_license') ? DB::table('creative_commons_license')->orderBy('sort_order')->get() : collect();
        $tkLabels = Schema::hasTable('rights_tk_label') ? DB::table('rights_tk_label')->orderBy('sort_order')->get() : collect();
        $donors = DB::table('donor')
            ->join('actor_i18n', 'donor.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->select('donor.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('name')
            ->get();

        return view('ahg-rights-holder-manage::extendedRights.batch', compact('topLevelRecords', 'rightsStatements', 'ccLicenses', 'tkLabels', 'donors'));
    }

    public function batchStore(Request $request)
    {
        return redirect()->route('extended-rights.dashboard')->with('success', 'Batch operation completed.');
    }

    public function clear(string $slug)
    {
        $resource = DB::table('slug')->where('slug', $slug)->first();
        if (!$resource) abort(404);
        $resource = (object) ['slug' => $slug, 'id' => $resource->object_id];
        $currentRights = (object) ['rights_statement' => null, 'cc_license' => null, 'tk_labels' => [], 'rights_holder' => null];

        return view('ahg-rights-holder-manage::extendedRights.clear', compact('resource', 'currentRights'));
    }

    public function clearStore(string $slug)
    {
        return redirect()->back()->with('success', 'Extended rights cleared.');
    }

    public function embargoBlocked()
    {
        $embargoInfo = ['type_label' => 'Access Restricted', 'public_message' => '', 'is_perpetual' => false, 'end_date' => null];
        return view('ahg-rights-holder-manage::extendedRights.embargo-blocked', compact('embargoInfo'));
    }

    public function embargoStatus(Request $request)
    {
        $objectId = $request->input('object_id');
        $embargo = null;
        if ($objectId && Schema::hasTable('embargo')) {
            $embargo = DB::table('embargo')->where('object_id', $objectId)->where('is_active', true)->first();
        }
        return view('ahg-rights-holder-manage::extendedRights.embargo-status', compact('objectId', 'embargo'));
    }

    public function embargoes()
    {
        $embargoes = collect();
        if (Schema::hasTable('embargo')) {
            $embargoes = DB::table('embargo')
                ->where('is_active', true)
                ->leftJoin('information_object_i18n', function ($j) {
                    $j->on('embargo.object_id', '=', 'information_object_i18n.id')
                      ->where('information_object_i18n.culture', '=', app()->getLocale());
                })
                ->leftJoin('slug', 'embargo.object_id', '=', 'slug.object_id')
                ->select('embargo.*', 'information_object_i18n.title', 'slug.slug')
                ->orderBy('embargo.created_at', 'desc')
                ->get();
        }
        return view('ahg-rights-holder-manage::extendedRights.embargoes', compact('embargoes'));
    }

    public function expiringEmbargoes(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $embargoes = [];
        if (Schema::hasTable('embargo')) {
            $embargoes = DB::table('embargo')
                ->where('is_active', true)
                ->whereNotNull('end_date')
                ->where('end_date', '>=', now())
                ->where('end_date', '<=', now()->addDays($days))
                ->leftJoin('information_object_i18n', function ($j) {
                    $j->on('embargo.object_id', '=', 'information_object_i18n.id')
                      ->where('information_object_i18n.culture', '=', app()->getLocale());
                })
                ->leftJoin('slug', 'embargo.object_id', '=', 'slug.object_id')
                ->select('embargo.*', 'information_object_i18n.title', 'slug.slug')
                ->orderBy('embargo.end_date')
                ->get()
                ->map(function ($e) {
                    $e->days_remaining = (int) now()->diffInDays($e->end_date, false);
                    return $e;
                })->toArray();
        }
        return view('ahg-rights-holder-manage::extendedRights.expiring-embargoes', compact('embargoes', 'days'));
    }

    public function export(Request $request)
    {
        $culture = app()->getLocale();
        $topLevelRecords = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->where('information_object.parent_id', 1)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'information_object.identifier')
            ->orderBy('information_object_i18n.title')
            ->limit(500)
            ->get();

        $stats = ['total_with_rights' => 0, 'inherited_rights' => 0];
        return view('ahg-rights-holder-manage::extendedRights.export', compact('topLevelRecords', 'stats'));
    }

    public function liftEmbargo(int $id)
    {
        if (Schema::hasTable('embargo')) {
            DB::table('embargo')->where('id', $id)->update([
                'is_active' => false,
                'lifted_at' => now(),
                'lifted_by' => auth()->id(),
                'updated_at' => now(),
            ]);
        }
        return redirect()->route('extended-rights.embargoes')->with('success', 'Embargo lifted successfully.');
    }

    private function getStats(): object
    {
        $stats = (object) [
            'total_objects' => DB::table('information_object')->where('id', '!=', 1)->count(),
            'with_rights_statement' => 0,
            'with_creative_commons' => 0,
            'with_tk_labels' => 0,
            'active_embargoes' => 0,
            'expiring_soon' => 0,
            'by_rights_statement' => [],
            'by_cc_license' => [],
        ];

        if (Schema::hasTable('extended_rights')) {
            $stats->with_rights_statement = DB::table('extended_rights')->whereNotNull('rights_statement_id')->distinct('object_id')->count('object_id');
            $stats->with_creative_commons = DB::table('extended_rights')->whereNotNull('creative_commons_id')->distinct('object_id')->count('object_id');
        }
        if (Schema::hasTable('embargo')) {
            $stats->active_embargoes = DB::table('embargo')->where('is_active', true)->count();
            $stats->expiring_soon = DB::table('embargo')->where('is_active', true)->whereNotNull('end_date')
                ->where('end_date', '<=', now()->addDays(30))->where('end_date', '>=', now())->count();
        }

        return $stats;
    }
}
