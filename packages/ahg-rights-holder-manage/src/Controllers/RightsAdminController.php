<?php

/**
 * RightsAdminController - Controller for Heratio
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



namespace AhgRightsHolderManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RightsAdminController extends Controller
{
    public function index()
    {
        $stats = [
            'total_rights' => DB::table('rights')->count(),
            'active_embargoes' => Schema::hasTable('embargo') ? DB::table('embargo')->where('is_active', true)->count() : 0,
            'orphan_works' => Schema::hasTable('rights_orphan_work') ? DB::table('rights_orphan_work')->count() : 0,
        ];
        return view('ahg-rights-holder-manage::rightsAdmin.index', compact('stats'));
    }

    public function embargoes()
    {
        $embargoes = collect();
        if (Schema::hasTable('embargo')) {
            $culture = app()->getLocale();
            $embargoes = DB::table('embargo')
                ->leftJoin('information_object_i18n', function ($j) use ($culture) {
                    $j->on('embargo.object_id', '=', 'information_object_i18n.id')
                      ->where('information_object_i18n.culture', '=', $culture);
                })
                ->select('embargo.*', 'information_object_i18n.title')
                ->orderBy('embargo.created_at', 'desc')
                ->get();
        }
        return view('ahg-rights-holder-manage::rightsAdmin.embargoes', compact('embargoes'));
    }

    public function embargoEdit(int $id)
    {
        $embargo = Schema::hasTable('embargo') ? DB::table('embargo')->where('id', $id)->first() : null;
        if (!$embargo) abort(404);
        return view('ahg-rights-holder-manage::rightsAdmin.embargo-edit', compact('embargo'));
    }

    public function embargoUpdate(Request $request, int $id)
    {
        if (Schema::hasTable('embargo')) {
            DB::table('embargo')->where('id', $id)->update([
                'embargo_type' => $request->input('embargo_type'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'reason' => $request->input('reason'),
                'is_perpetual' => $request->boolean('is_perpetual'),
                'is_active' => $request->boolean('is_active'),
                'updated_at' => now(),
            ]);
        }
        return redirect()->route('rights-admin.embargoes')->with('success', 'Embargo updated.');
    }

    public function orphanWorks()
    {
        $orphanWorks = Schema::hasTable('rights_orphan_work') ? DB::table('rights_orphan_work')->orderBy('created_at', 'desc')->get() : collect();
        return view('ahg-rights-holder-manage::rightsAdmin.orphan-works', compact('orphanWorks'));
    }

    public function orphanWorkEdit(int $id)
    {
        $orphanWork = Schema::hasTable('rights_orphan_work') ? DB::table('rights_orphan_work')->where('id', $id)->first() : null;
        if (!$orphanWork) abort(404);
        return view('ahg-rights-holder-manage::rightsAdmin.orphan-work-edit', compact('orphanWork'));
    }

    public function orphanWorkUpdate(Request $request, int $id)
    {
        // Canonical `rights_orphan_work` columns: status (lifecycle),
        // search_started_date / search_completed_date, contact_response (free text).
        if (Schema::hasTable('rights_orphan_work')) {
            DB::table('rights_orphan_work')->where('id', $id)->update([
                'search_started_date' => $request->input('designation_date'),
                'status'              => $request->input('search_status'),
                'contact_response'    => $request->input('search_notes'),
                'updated_at'          => now(),
            ]);
        }
        return redirect()->route('rights-admin.orphan-works')->with('success', 'Orphan work updated.');
    }

    public function report()
    {
        // In AtoM, rights are linked to objects via the `relation` table (no
        // `object_id` column on `rights` itself). Count distinct IOs that
        // have at least one rights-relation pointing at them.
        $withRights = DB::table('relation')
            ->join('rights', 'rights.id', '=', 'relation.subject_id')
            ->distinct('relation.object_id')
            ->count('relation.object_id');

        $stats = [
            'total_objects' => DB::table('information_object')->where('id', '!=', 1)->count(),
            'with_rights' => $withRights,
            'without_rights' => 0,
            'coverage_pct' => 0,
        ];
        $stats['without_rights'] = $stats['total_objects'] - $stats['with_rights'];
        $stats['coverage_pct'] = $stats['total_objects'] > 0 ? round(($stats['with_rights'] / $stats['total_objects']) * 100, 1) : 0;

        $culture = app()->getLocale();
        $byBasis = DB::table('rights')
            ->leftJoin('term_i18n', function ($j) use ($culture) {
                $j->on('rights.basis_id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', $culture);
            })
            ->select('term_i18n.name', DB::raw('COUNT(*) as count'))
            ->groupBy('term_i18n.name')
            ->orderBy('count', 'desc')
            ->get();

        $byHolder = DB::table('rights')
            ->join('actor_i18n', function ($j) use ($culture) {
                $j->on('rights.rights_holder_id', '=', 'actor_i18n.id')->where('actor_i18n.culture', '=', $culture);
            })
            ->select('actor_i18n.authorized_form_of_name as name', DB::raw('COUNT(*) as count'))
            ->groupBy('actor_i18n.authorized_form_of_name')
            ->orderBy('count', 'desc')
            ->limit(20)
            ->get();

        return view('ahg-rights-holder-manage::rightsAdmin.report', compact('stats', 'byBasis', 'byHolder'));
    }

    public function statements()
    {
        $statements = Schema::hasTable('rights_statement') ? DB::table('rights_statement')->orderBy('sort_order')->get() : collect();
        return view('ahg-rights-holder-manage::rightsAdmin.statements', compact('statements'));
    }

    public function tkLabels()
    {
        $tkLabels = Schema::hasTable('rights_tk_label') ? DB::table('rights_tk_label')->orderBy('sort_order')->get() : collect();
        return view('ahg-rights-holder-manage::rightsAdmin.tk-labels', compact('tkLabels'));
    }
}
