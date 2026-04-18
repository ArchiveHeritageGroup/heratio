<?php

/**
 * AuditController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AuditController - Audit trail management.
 *
 * Migrated from AtoM: ahgResearchPlugin/modules/audit/actions/actions.class.php
 */
class AuditController extends Controller
{
    public function __construct()
    {
        // Admin-only access enforced in routes middleware
    }

    /**
     * Audit log index with filters and pagination.
     */
    public function index(Request $request)
    {
        $query = DB::table('audit_log as a')
            ->leftJoin('user as u', 'a.user_id', '=', 'u.id')
            ->select('a.*', 'u.username as user_name');

        if ($request->filled('table')) {
            $query->where('a.table_name', $request->input('table'));
        }
        if ($request->filled('form_action')) {
            $query->where('a.action', $request->input('form_action'));
        }
        if ($request->filled('from_date')) {
            $query->where('a.created_at', '>=', $request->input('from_date') . ' 00:00:00');
        }
        if ($request->filled('to_date')) {
            $query->where('a.created_at', '<=', $request->input('to_date') . ' 23:59:59');
        }
        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('a.old_value', 'LIKE', "%{$search}%")
                  ->orWhere('a.new_value', 'LIKE', "%{$search}%")
                  ->orWhere('a.action_description', 'LIKE', "%{$search}%");
            });
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;

        try {
            $totalCount = $query->count();
            $totalPages = max(1, (int) ceil($totalCount / $perPage));

            $logs = $query
                ->orderBy('a.created_at', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->toArray();

            $tables = DB::table('audit_log')
                ->select('table_name')
                ->distinct()
                ->orderBy('table_name')
                ->pluck('table_name')
                ->toArray();

            $stats = [
                'total' => $totalCount,
                'today' => DB::table('audit_log')->whereDate('created_at', date('Y-m-d'))->count(),
                'creates' => DB::table('audit_log')->where('action', 'create')->count(),
                'updates' => DB::table('audit_log')->where('action', 'update')->count(),
                'deletes' => DB::table('audit_log')->where('action', 'delete')->count(),
            ];
        } catch (\Exception $e) {
            $totalCount = 0;
            $totalPages = 1;
            $logs = [];
            $tables = [];
            $stats = ['total' => 0, 'today' => 0, 'creates' => 0, 'updates' => 0, 'deletes' => 0];
        }

        return view('research::audit.index', compact(
            'logs', 'tables', 'stats', 'totalCount', 'totalPages', 'page'
        ) + ['currentPage' => $page]);
    }

    /**
     * View a single audit entry.
     */
    public function view(int $id)
    {
        try {
            $entry = DB::table('audit_log as a')
                ->leftJoin('user as u', 'a.user_id', '=', 'u.id')
                ->select('a.*', 'u.username as user_name')
                ->where('a.id', $id)
                ->first();

            $changes = [];
            if ($entry && $entry->record_id) {
                $allChanges = DB::table('audit_log')
                    ->where('record_id', $entry->record_id)
                    ->where('table_name', $entry->table_name)
                    ->where('created_at', $entry->created_at)
                    ->whereNotNull('field_name')
                    ->get();
                foreach ($allChanges as $c) {
                    $changes[$c->field_name] = ['old' => $c->old_value, 'new' => $c->new_value];
                }
            }
        } catch (\Exception $e) {
            $entry = (object) ['id' => $id, 'action' => '', 'table_name' => '', 'record_id' => '', 'created_at' => '', 'user_name' => 'System', 'field_name' => null, 'old_value' => null, 'new_value' => null, 'ip_address' => null, 'action_description' => 'Error loading entry'];
            $changes = [];
        }

        return view('research::audit.view', compact('entry', 'changes'));
    }

    /**
     * View record history (all audit entries for a specific record).
     */
    public function record(string $tableName, int $recordId)
    {
        try {
            $history = DB::table('audit_log as a')
                ->leftJoin('user as u', 'a.user_id', '=', 'u.id')
                ->select('a.*', 'u.username as user_name')
                ->where('a.table_name', $tableName)
                ->where('a.record_id', $recordId)
                ->orderBy('a.created_at', 'desc')
                ->get();

            $timeline = [];
            foreach ($history as $entry) {
                $date = date('Y-m-d', strtotime($entry->created_at));
                $timeline[$date][] = $entry;
            }
        } catch (\Exception $e) {
            $history = [];
            $timeline = [];
        }

        return view('research::audit.record', compact('tableName', 'recordId', 'history', 'timeline'));
    }

    /**
     * View user activity.
     */
    public function user(int $userId)
    {
        try {
            $user = DB::table('user')->where('id', $userId)->first();
            if (!$user) {
                $user = (object) ['id' => $userId, 'username' => 'Unknown'];
            }

            $tableStats = DB::table('audit_log')
                ->where('user_id', $userId)
                ->select('table_name', DB::raw('COUNT(*) as count'))
                ->groupBy('table_name')
                ->orderByDesc('count')
                ->get();

            $actionStats = DB::table('audit_log')
                ->where('user_id', $userId)
                ->select('action', DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->get();

            $totalCount = DB::table('audit_log')->where('user_id', $userId)->count();

            $activity = DB::table('audit_log')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        } catch (\Exception $e) {
            $user = (object) ['id' => $userId, 'username' => 'Unknown'];
            $tableStats = [];
            $actionStats = [];
            $totalCount = 0;
            $activity = [];
        }

        return view('research::audit.user', compact('user', 'tableStats', 'actionStats', 'totalCount', 'activity'));
    }
}
