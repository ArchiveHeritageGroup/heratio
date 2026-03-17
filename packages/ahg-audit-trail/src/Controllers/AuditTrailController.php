<?php

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

        // Filter parameters
        $typeFilter = $request->get('type', '');
        $actionFilter = $request->get('action', '');
        $userFilter = $request->get('user', '');
        $fromFilter = $request->get('from', '');
        $toFilter = $request->get('to', '');

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

        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;

        return view('ahg-audit-trail::browse', [
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'table' => $table,
            'entityTypes' => $entityTypes,
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
        $mostActiveUsers = (clone $baseQuery)
            ->select($usernameCol, DB::raw('COUNT(*) as action_count'))
            ->whereNotNull($usernameCol)
            ->groupBy($usernameCol)
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
}
