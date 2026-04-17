<?php

/**
 * DoiController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgDoiManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DoiController extends Controller
{
    /**
     * Dashboard — stats, recent DOIs, quick links.
     */
    public function index()
    {
        if (!Schema::hasTable('ahg_doi')) {
            return view('ahg-doi-manage::index', ['tablesExist' => false]);
        }

        $culture = app()->getLocale();

        $totalCount    = DB::table('ahg_doi')->count();
        $findableCount = DB::table('ahg_doi')->where('status', 'findable')->count();
        $registeredCount = DB::table('ahg_doi')->where('status', 'registered')->count();
        $draftCount    = DB::table('ahg_doi')->where('status', 'draft')->count();
        $doiFailedCount = DB::table('ahg_doi')->where('status', 'failed')->count();

        $queuePending = 0;
        $queueFailed  = 0;
        if (Schema::hasTable('ahg_doi_queue')) {
            $queuePending = DB::table('ahg_doi_queue')->where('status', 'pending')->count();
            $queueFailed  = DB::table('ahg_doi_queue')->where('status', 'failed')->count();
        }

        $recentDois = DB::table('ahg_doi')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('ahg_doi.information_object_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->select([
                'ahg_doi.id',
                'ahg_doi.doi',
                'ahg_doi.information_object_id',
                'ahg_doi.status',
                'ahg_doi.minted_at',
                'information_object_i18n.title as record_title',
            ])
            ->orderByDesc('ahg_doi.created_at')
            ->limit(10)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return view('ahg-doi-manage::index', [
            'tablesExist' => true,
            'stats' => [
                'total'      => $totalCount,
                'findable'   => $findableCount,
                'registered' => $registeredCount,
                'draft'      => $draftCount,
                'pending'    => $queuePending,
                'failed'     => $doiFailedCount + $queueFailed,
            ],
            'recentDois' => $recentDois,
        ]);
    }

    /**
     * Browse DOIs with status filter.
     */
    public function browse(Request $request)
    {
        if (!Schema::hasTable('ahg_doi')) {
            return view('ahg-doi-manage::browse', ['tablesExist' => false]);
        }

        $culture = app()->getLocale();
        $page    = max(1, (int) $request->get('page', 1));
        $limit   = max(1, (int) $request->get('limit', SettingHelper::hitsPerPage()));
        $status  = $request->get('status', '');

        $query = DB::table('ahg_doi')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('ahg_doi.information_object_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            });

        if ($status && in_array($status, ['draft', 'registered', 'findable'])) {
            $query->where('ahg_doi.status', $status);
        }

        $total = $query->count();

        $dois = $query->select([
                'ahg_doi.id',
                'ahg_doi.doi',
                'ahg_doi.information_object_id',
                'ahg_doi.status',
                'ahg_doi.minted_at',
                'information_object_i18n.title as record_title',
            ])
            ->orderByDesc('ahg_doi.minted_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        $pager = new SimplePager([
            'hits'  => $dois,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);

        return view('ahg-doi-manage::browse', [
            'tablesExist'   => true,
            'pager'         => $pager,
            'currentStatus' => $status,
        ]);
    }

    /**
     * Queue management with status tabs.
     */
    public function queue(Request $request)
    {
        if (!Schema::hasTable('ahg_doi_queue')) {
            return view('ahg-doi-manage::queue', ['tablesExist' => false]);
        }

        $culture = app()->getLocale();
        $page    = max(1, (int) $request->get('page', 1));
        $limit   = max(1, (int) $request->get('limit', SettingHelper::hitsPerPage()));
        $status  = $request->get('status', '');

        // Counts per status
        $statusCounts = [
            'pending'    => DB::table('ahg_doi_queue')->where('status', 'pending')->count(),
            'processing' => DB::table('ahg_doi_queue')->where('status', 'processing')->count(),
            'failed'     => DB::table('ahg_doi_queue')->where('status', 'failed')->count(),
            'completed'  => DB::table('ahg_doi_queue')->where('status', 'completed')->count(),
        ];

        $query = DB::table('ahg_doi_queue')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('ahg_doi_queue.information_object_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            });

        if ($status && in_array($status, ['pending', 'processing', 'completed', 'failed'])) {
            $query->where('ahg_doi_queue.status', $status);
        }

        $total = $query->count();

        $items = $query->select([
                'ahg_doi_queue.id',
                'ahg_doi_queue.information_object_id',
                'ahg_doi_queue.action',
                'ahg_doi_queue.status',
                'ahg_doi_queue.attempts',
                'ahg_doi_queue.scheduled_at',
                'ahg_doi_queue.last_error as error_message',
                'ahg_doi_queue.created_at',
                'information_object_i18n.title as record_title',
            ])
            ->orderByDesc('ahg_doi_queue.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        $pager = new SimplePager([
            'hits'  => $items,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);

        return view('ahg-doi-manage::queue', [
            'tablesExist'   => true,
            'pager'         => $pager,
            'statusCounts'  => $statusCounts,
            'currentStatus' => $status,
        ]);
    }

    /**
     * View a single DOI with activity log.
     */
    public function view(int $id)
    {
        if (!Schema::hasTable('ahg_doi')) {
            return view('ahg-doi-manage::view', ['tablesExist' => false]);
        }

        $culture = app()->getLocale();

        $doi = DB::table('ahg_doi')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('ahg_doi.information_object_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->where('ahg_doi.id', $id)
            ->select([
                'ahg_doi.id',
                'ahg_doi.doi',
                'ahg_doi.information_object_id',
                'ahg_doi.status',
                'ahg_doi.minted_at',
                'ahg_doi.updated_at',
                'ahg_doi.created_at',
                'information_object_i18n.title as record_title',
            ])
            ->first();

        if (!$doi) {
            abort(404);
        }

        $logs = [];
        if (Schema::hasTable('ahg_doi_log')) {
            $logs = DB::table('ahg_doi_log')
                ->where('doi_id', $id)
                ->orderByDesc('performed_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        }

        return view('ahg-doi-manage::view', [
            'tablesExist' => true,
            'doi'         => $doi,
            'logs'        => $logs,
        ]);
    }

    /**
     * Configuration form (GET).
     */
    public function config()
    {
        $settings = collect();
        if (Schema::hasTable('ahg_settings')) {
            $settings = DB::table('ahg_settings')
                ->where('setting_group', 'doi')
                ->pluck('setting_value', 'setting_key');
        }

        return view('ahg-doi-manage::config', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save configuration (POST).
     */
    public function configSave(Request $request)
    {
        $request->validate([
            'datacite_prefix'        => 'nullable|string|max:255',
            'datacite_repository_id' => 'nullable|string|max:255',
            'datacite_password'      => 'nullable|string|max:255',
            'datacite_url'           => 'nullable|url|max:500',
            'datacite_environment'   => 'nullable|in:test,production',
            'auto_mint'              => 'nullable|in:0,1',
            'default_publisher'      => 'nullable|string|max:500',
            'default_resource_type'  => 'nullable|string|max:255',
        ]);

        if (!Schema::hasTable('ahg_settings')) {
            return redirect()->route('doi.config')
                ->with('error', 'Settings table does not exist.');
        }

        $keys = [
            'datacite_prefix',
            'datacite_repository_id',
            'datacite_password',
            'datacite_url',
            'datacite_environment',
            'auto_mint',
            'default_publisher',
            'default_resource_type',
        ];

        foreach ($keys as $key) {
            $value = $request->input($key, '');
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_group' => 'doi', 'setting_key' => $key],
                ['setting_value' => $value]
            );
        }

        return redirect()->route('doi.config')
            ->with('success', 'DOI configuration saved.');
    }

    /**
     * Reporting — monthly minting stats, by-repository breakdown.
     */
    public function report()
    {
        if (!Schema::hasTable('ahg_doi')) {
            return view('ahg-doi-manage::report', ['tablesExist' => false]);
        }

        $culture = app()->getLocale();

        // Monthly stats
        $monthlyStats = DB::table('ahg_doi')
            ->selectRaw("DATE_FORMAT(minted_at, '%Y-%m') as `month`")
            ->selectRaw("SUM(CASE WHEN minted_at IS NOT NULL THEN 1 ELSE 0 END) as minted_count")
            ->selectRaw("SUM(CASE WHEN updated_at > created_at THEN 1 ELSE 0 END) as updated_count")
            ->whereNotNull('minted_at')
            ->groupByRaw("DATE_FORMAT(minted_at, '%Y-%m')")
            ->orderByDesc('month')
            ->limit(24)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        // By repository breakdown
        $byRepository = DB::table('ahg_doi')
            ->join('information_object', 'ahg_doi.information_object_id', '=', 'information_object.id')
            ->leftJoin('repository_i18n', function ($join) use ($culture) {
                $join->on('information_object.repository_id', '=', 'repository_i18n.id')
                    ->where('repository_i18n.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('information_object.repository_id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->selectRaw("COALESCE(actor_i18n.authorized_form_of_name, '[No repository]') as repository_name")
            ->selectRaw("COUNT(ahg_doi.id) as doi_count")
            ->groupByRaw("COALESCE(actor_i18n.authorized_form_of_name, '[No repository]')")
            ->orderByDesc('doi_count')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return view('ahg-doi-manage::report', [
            'tablesExist'  => true,
            'monthlyStats' => $monthlyStats,
            'byRepository' => $byRepository,
        ]);
    }

    public function batchMint(Request $request)
    {
        if ($request->isMethod('post')) {
            $ids = $request->input('object_ids', []);
            if (is_string($ids)) {
                $ids = preg_split('/\s+/', trim($ids));
            }
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));
            $state = $request->input('state', 'findable');
            if (!in_array($state, ['findable', 'registered', 'draft'], true)) {
                $state = 'findable';
            }

            $queued = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_doi_queue')) {
                foreach ($ids as $objectId) {
                    \Illuminate\Support\Facades\DB::table('ahg_doi_queue')->insert([
                        'object_id'  => $objectId,
                        'state'      => $state,
                        'status'     => 'pending',
                        'created_at' => now(),
                    ]);
                    $queued++;
                }
            }
            return redirect()->route('doi.batch-mint')
                ->with('success', "{$queued} record(s) queued for DOI minting ({$state}).");
        }

        $records = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('information_object')) {
            $records = \Illuminate\Support\Facades\DB::table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', app()->getLocale());
                })
                ->leftJoin('ahg_doi as d', 'io.id', '=', 'd.object_id')
                ->whereNull('d.id')
                ->select('io.id', 'ioi.title')
                ->orderByDesc('io.id')
                ->limit(100)
                ->get();
        }

        return view('ahg-doi-manage::batch-mint', [
            'records'    => $records,
            'formAction' => route('doi.batch-mint'),
        ]);
    }

    public function deactivate(int $id) { return view('ahg-doi-manage::deactivate', ['record' => (object)['id'=>$id]]); }

    public function mint(int $id) { return view('ahg-doi-manage::mint'); }

    public function sync(Request $request) { return view('ahg-doi-manage::sync'); }
}
