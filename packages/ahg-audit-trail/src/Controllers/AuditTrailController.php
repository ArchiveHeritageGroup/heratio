<?php

/**
 * AuditTrailController - Controller for Heratio
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



namespace AhgAuditTrail\Controllers;

use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditTrailController extends Controller
{
    /**
     * Determine which audit table is available.
     */
    private function resolveTable(): string
    {
        if (Schema::hasTable('ahg_audit_log')) {
            return 'ahg_audit_log';
        }

        return 'audit_log';
    }

    public function browse(Request $request)
    {
        $table = $this->resolveTable();
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', SettingHelper::hitsPerPage()));

        // Filter parameters (accept PSIS names first, fall back to prior Heratio names)
        $typeFilter = $request->get('entity_type', $request->get('type', ''));
        $actionFilter = $request->get('filter_action', $request->get('action', ''));
        $userFilter = $request->get('username', $request->get('user', ''));
        $fromFilter = $request->get('from_date', $request->get('from', ''));
        $toFilter = $request->get('to_date', $request->get('to', ''));

        if ($table === 'ahg_audit_log') {
            $query = DB::table('ahg_audit_log');

            // Apply filters
            if ($typeFilter) {
                $query->where('entity_type', $typeFilter);
            }
            if ($actionFilter) {
                $query->where('action', $actionFilter);
            }
            if ($userFilter) {
                $query->where(function ($q) use ($userFilter) {
                    $q->where('username', 'like', "%{$userFilter}%")
                      ->orWhere('user_email', 'like', "%{$userFilter}%");
                });
            }
            if ($fromFilter) {
                $query->where('created_at', '>=', $fromFilter . ' 00:00:00');
            }
            if ($toFilter) {
                $query->where('created_at', '<=', $toFilter . ' 23:59:59');
            }

            $total = $query->count();

            $entries = $query->select([
                    'id',
                    'uuid',
                    'user_id',
                    'username',
                    'user_email',
                    'ip_address',
                    'user_agent',
                    'session_id',
                    'action',
                    'entity_type',
                    'entity_id',
                    'entity_slug',
                    'entity_title',
                    'module',
                    'action_name',
                    'request_method',
                    'request_uri',
                    'old_values',
                    'new_values',
                    'changed_fields',
                    'metadata',
                    'security_classification',
                    'status',
                    'error_message',
                    'created_at',
                    'culture_id',
                ])
                ->orderBy('created_at', 'desc')
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->toArray();

            // Get distinct entity types and actions for filter dropdowns
            $entityTypes = DB::table('ahg_audit_log')
                ->select('entity_type')
                ->distinct()
                ->whereNotNull('entity_type')
                ->orderBy('entity_type')
                ->pluck('entity_type')
                ->toArray();

            $actions = DB::table('ahg_audit_log')
                ->select('action')
                ->distinct()
                ->whereNotNull('action')
                ->orderBy('action')
                ->pluck('action')
                ->toArray();
        } else {
            // Fallback: audit_log table
            $query = DB::table('audit_log');

            if ($typeFilter) {
                $query->where('table_name', $typeFilter);
            }
            if ($actionFilter) {
                $query->where('action', $actionFilter);
            }
            if ($userFilter) {
                $query->where('username', 'like', "%{$userFilter}%");
            }
            if ($fromFilter) {
                $query->where('created_at', '>=', $fromFilter . ' 00:00:00');
            }
            if ($toFilter) {
                $query->where('created_at', '<=', $toFilter . ' 23:59:59');
            }

            $total = $query->count();

            $entries = $query->select([
                    'id',
                    'table_name',
                    'record_id',
                    'action',
                    'field_name',
                    'old_value',
                    'new_value',
                    'old_record',
                    'new_record',
                    'user_id',
                    'username',
                    'ip_address',
                    'user_agent',
                    'module',
                    'action_description',
                    'created_at',
                ])
                ->orderBy('created_at', 'desc')
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->toArray();

            $entityTypes = DB::table('audit_log')
                ->select('table_name as entity_type')
                ->distinct()
                ->whereNotNull('table_name')
                ->orderBy('table_name')
                ->pluck('entity_type')
                ->toArray();

            $actions = DB::table('audit_log')
                ->select('action')
                ->distinct()
                ->whereNotNull('action')
                ->orderBy('action')
                ->pluck('action')
                ->toArray();
        }

        $totalPages = $limit > 0 ? (int) max(1, (int) ceil($total / $limit)) : 1;

        // Distinct usernames for sidebar dropdown
        $usernames = DB::table($table === 'ahg_audit_log' ? 'ahg_audit_log' : 'audit_log')
            ->select('username')
            ->distinct()
            ->whereNotNull('username')
            ->where('username', '<>', '')
            ->orderBy('username')
            ->pluck('username')
            ->toArray();

        // Build value => label maps for the PSIS-style sidebar dropdowns
        $actionTypes = [];
        foreach ($actions as $a) {
            $actionTypes[$a] = ucfirst($a);
        }
        $entityTypeMap = [];
        foreach ($entityTypes as $et) {
            $entityTypeMap[$et] = $et;
        }

        // Decorate entries with label fields used by the PSIS template
        foreach ($entries as &$row) {
            $row['action_label'] = ucfirst($row['action'] ?? '');
            $row['entity_type_label'] = $row['entity_type'] ?? ($row['table_name'] ?? '');
        }
        unset($row);

        // PSIS-shaped pager
        $from = $total === 0 ? 0 : (($page - 1) * $limit) + 1;
        $to = min($total, $page * $limit);
        $pager = [
            'data' => $entries,
            'total' => $total,
            'from' => $from,
            'to' => $to,
            'current_page' => $page,
            'last_page' => $totalPages,
        ];

        // PSIS-shaped current filters
        $currentFilters = [
            'action' => $actionFilter,
            'entity_type' => $typeFilter,
            'username' => $userFilter,
            'from_date' => $fromFilter,
            'to_date' => $toFilter,
        ];

        return view('ahg-audit-trail::browse', [
            // PSIS-compatible
            'pager' => $pager,
            'actionTypes' => $actionTypes,
            'entityTypes' => $entityTypeMap,
            'usernames' => $usernames,
            'currentFilters' => $currentFilters,
            // Legacy (kept for any callers still using them)
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'table' => $table,
            'actions' => $actions,
            'filters' => [
                'type' => $typeFilter,
                'action' => $actionFilter,
                'user' => $userFilter,
                'from' => $fromFilter,
                'to' => $toFilter,
            ],
        ]);
    }

    public function show(int $id)
    {
        $table = $this->resolveTable();

        if ($table === 'ahg_audit_log') {
            $entry = DB::table('ahg_audit_log')
                ->where('id', $id)
                ->select([
                    'id',
                    'uuid',
                    'user_id',
                    'username',
                    'user_email',
                    'ip_address',
                    'user_agent',
                    'session_id',
                    'action',
                    'entity_type',
                    'entity_id',
                    'entity_slug',
                    'entity_title',
                    'module',
                    'action_name',
                    'request_method',
                    'request_uri',
                    'old_values',
                    'new_values',
                    'changed_fields',
                    'metadata',
                    'security_classification',
                    'status',
                    'error_message',
                    'created_at',
                    'culture_id',
                ])
                ->first();
        } else {
            $entry = DB::table('audit_log')
                ->where('id', $id)
                ->select([
                    'id',
                    'table_name',
                    'record_id',
                    'action',
                    'field_name',
                    'old_value',
                    'new_value',
                    'old_record',
                    'new_record',
                    'user_id',
                    'username',
                    'ip_address',
                    'user_agent',
                    'module',
                    'action_description',
                    'created_at',
                ])
                ->first();
        }

        if (!$entry) {
            abort(404);
        }

        return view('ahg-audit-trail::show', [
            'entry' => $entry,
            'table' => $table,
        ]);
    }

    /**
     * Statistics dashboard for audit trail.
     */
    public function statistics(Request $request)
    {
        $table = $this->resolveTable();
        $days = (int) $request->get('days', 30);
        if (!in_array($days, [7, 30, 90])) {
            $days = 30;
        }
        $fromDate = Carbon::now()->subDays($days)->startOfDay();

        $baseQuery = DB::table($table)->where('created_at', '>=', $fromDate);

        // Total actions
        $totalActions = (clone $baseQuery)->count();

        // Counts by action type
        $createdCount = (clone $baseQuery)->where('action', 'create')->count();
        $updatedCount = (clone $baseQuery)->where('action', 'update')->count();
        $deletedCount = (clone $baseQuery)->where('action', 'delete')->count();

        // Most active users
        $usernameCol = Schema::hasColumn($table, 'username') ? 'username' : 'username';
        $hasUserId = Schema::hasColumn($table, 'user_id');
        $selectCols = $hasUserId
            ? [$usernameCol, 'user_id', DB::raw('COUNT(*) as action_count')]
            : [$usernameCol, DB::raw('COUNT(*) as action_count')];
        $groupCols = $hasUserId ? [$usernameCol, 'user_id'] : [$usernameCol];
        $mostActiveUsers = (clone $baseQuery)
            ->select($selectCols)
            ->whereNotNull($usernameCol)
            ->groupBy($groupCols)
            ->orderByDesc('action_count')
            ->limit(10)
            ->get();

        // Recent failed actions
        $failedQuery = DB::table($table)->where('created_at', '>=', $fromDate);
        if (Schema::hasColumn($table, 'status')) {
            $failedQuery->where(function ($q) {
                $q->where('status', 'error')
                  ->orWhere('action', 'failed');
            });
        } else {
            $failedQuery->where('action', 'failed');
        }
        $recentFailed = $failedQuery
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('ahg-audit-trail::statistics', [
            'days' => $days,
            'totalActions' => $totalActions,
            'createdCount' => $createdCount,
            'updatedCount' => $updatedCount,
            'deletedCount' => $deletedCount,
            'mostActiveUsers' => $mostActiveUsers,
            'recentFailed' => $recentFailed,
            'table' => $table,
        ]);
    }

    /**
     * Audit trail settings (read/save from ahg_settings).
     */
    public function settings(Request $request)
    {
        $settingKeys = [
            'audit_enabled',
            'audit_views',
            'audit_searches',
            'audit_downloads',
            'audit_api_requests',
            'audit_authentication',
            'audit_sensitive_access',
            'audit_mask_sensitive',
            'audit_ip_anonymize',
        ];

        if ($request->isMethod('post')) {
            foreach ($settingKeys as $key) {
                $value = $request->has("settings.{$key}") ? '1' : '0';

                $exists = DB::table('ahg_settings')
                    ->where('setting_key', $key)
                    ->where('setting_group', 'audit')
                    ->exists();

                if ($exists) {
                    DB::table('ahg_settings')
                        ->where('setting_key', $key)
                        ->where('setting_group', 'audit')
                        ->update([
                            'setting_value' => $value,
                            'updated_by' => Auth::id(),
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('ahg_settings')->insert([
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'setting_type' => 'boolean',
                        'setting_group' => 'audit',
                        'description' => str_replace('_', ' ', ucfirst($key)),
                        'is_sensitive' => 0,
                        'updated_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return redirect()->route('audit.settings')->with('success', 'Audit settings saved.');
        }

        // GET: load current settings
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'audit')
            ->whereIn('setting_key', $settingKeys)
            ->pluck('setting_value', 'setting_key');

        $settings = [];
        foreach ($settingKeys as $key) {
            $settings[$key] = $rows[$key] ?? '0';
        }

        return view('ahg-audit-trail::settings', [
            'settings' => $settings,
        ]);
    }

    public function authentication(Request $request)
    {
        $recentLogins = DB::table('audit_log')->where('action', 'login')->orderByDesc('created_at')->limit(50)->get();
        $suspiciousActivity = DB::table('audit_log')->whereIn('action', ['failed_login','locked'])->orderByDesc('created_at')->limit(50)->get();
        return view('ahg-audit-trail::authentication', compact('recentLogins', 'suspiciousActivity'));
    }

    public function entityHistory(int $id)
    {
        $rows = DB::table('audit_log')->where('entity_id', $id)->orderByDesc('created_at')->get();
        return view('ahg-audit-trail::entity-history', ['rows' => $rows]);
    }

    /**
     * Export audit trail as CSV or JSON stream download.
     * Mirrors PSIS ahgAuditTrailPlugin::executeExport + exportSuccess template.
     *
     * Accepted parameters:
     *   format      - 'csv' (default) or 'json'
     *   from_date   - ISO date filter (created_at >=)
     *   to_date     - ISO date filter (created_at <=)
     *   filter_action / action - action name filter
     *   entity_type - entity type filter
     */
    public function export(Request $request)
    {
        $table = $this->resolveTable();

        $format = strtolower((string) $request->get('format', 'csv'));
        if (!in_array($format, ['csv', 'json'], true)) {
            $format = 'csv';
        }

        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $actionFilter = $request->get('filter_action', $request->get('action'));
        $entityType = $request->get('entity_type');

        $query = DB::table($table)->orderByDesc('created_at');

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }
        if ($actionFilter) {
            $query->where('action', $actionFilter);
        }
        if ($entityType) {
            if ($table === 'ahg_audit_log') {
                $query->where('entity_type', $entityType);
            } else {
                $query->where('table_name', $entityType);
            }
        }

        // PSIS export caps at 10,000 rows per getFiltered() call.
        $logs = $query->limit(10000)->get();

        $filename = 'audit_log_export_' . date('Y-m-d_His');

        if ($format === 'json') {
            $payload = $logs->map(fn ($row) => (array) $row)->toArray();
            $body = json_encode($payload, JSON_PRETTY_PRINT);

            return response($body, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
            ]);
        }

        // CSV stream — columns match PSIS exportSuccess.php header row.
        $columns = [
            'id', 'uuid', 'created_at', 'user_id', 'username', 'ip_address',
            'action', 'entity_type', 'entity_id', 'entity_slug', 'entity_title', 'status',
        ];

        $csv = implode(',', $columns) . "\n";
        foreach ($logs as $log) {
            $log = (array) $log;
            // Map legacy audit_log column names onto PSIS schema.
            if ($table !== 'ahg_audit_log') {
                $log['uuid'] = $log['uuid'] ?? '';
                $log['entity_type'] = $log['table_name'] ?? '';
                $log['entity_id'] = $log['record_id'] ?? '';
                $log['entity_slug'] = $log['entity_slug'] ?? '';
                $log['entity_title'] = $log['entity_title'] ?? '';
                $log['status'] = $log['status'] ?? '';
            }

            $row = [
                (string) ($log['id'] ?? ''),
                '"' . ($log['uuid'] ?? '') . '"',
                '"' . ($log['created_at'] ?? '') . '"',
                (string) ($log['user_id'] ?? ''),
                '"' . addslashes((string) ($log['username'] ?? '')) . '"',
                '"' . ($log['ip_address'] ?? '') . '"',
                '"' . ($log['action'] ?? '') . '"',
                '"' . ($log['entity_type'] ?? '') . '"',
                (string) ($log['entity_id'] ?? ''),
                '"' . addslashes((string) ($log['entity_slug'] ?? '')) . '"',
                '"' . addslashes((string) ($log['entity_title'] ?? '')) . '"',
                '"' . ($log['status'] ?? '') . '"',
            ];
            $csv .= implode(',', $row) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    public function securityAccess(Request $request) { return view('ahg-audit-trail::security-access', ['rows' => collect()]); }

    public function userActivity(Request $request) { return view('ahg-audit-trail::user-activity', ['rows' => collect()]); }

    /**
     * Compare old/new data for a specific audit entry.
     */
    public function compareData(int $id)
    {
        $table = $this->resolveTable();

        if ($table === 'ahg_audit_log') {
            $entry = DB::table('ahg_audit_log')->where('id', $id)
                ->select(['id', 'entity_type', 'entity_id', 'entity_title', 'action', 'old_values', 'new_values', 'changed_fields', 'username', 'created_at'])
                ->first();
        } else {
            $entry = DB::table('audit_log')->where('id', $id)
                ->select(['id', 'table_name', 'record_id', 'action', 'old_value', 'new_value', 'old_record', 'new_record', 'field_name', 'username', 'created_at'])
                ->first();
        }

        if (!$entry) {
            abort(404);
        }

        return view('ahg-audit-trail::compare', [
            'entry' => $entry,
            'table' => $table,
        ]);
    }

    /**
     * User activity filtered by user ID (legacy URL).
     */
    public function userActivityById(int $userId)
    {
        $table = $this->resolveTable();

        $rows = DB::table($table)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $username = DB::table('user')->where('id', $userId)->value('username') ?? "User #{$userId}";

        return view('ahg-audit-trail::user-activity', [
            'rows' => $rows,
            'username' => $username,
        ]);
    }

    /**
     * Entity history filtered by type + ID (legacy URL).
     */
    public function entityHistoryByType(string $entityType, int $entityId)
    {
        $table = $this->resolveTable();

        if ($table === 'ahg_audit_log') {
            $rows = DB::table('ahg_audit_log')
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->orderByDesc('created_at')
                ->get();
        } else {
            $rows = DB::table('audit_log')
                ->where('table_name', $entityType)
                ->where('record_id', $entityId)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('ahg-audit-trail::entity-history', ['rows' => $rows]);
    }
}
