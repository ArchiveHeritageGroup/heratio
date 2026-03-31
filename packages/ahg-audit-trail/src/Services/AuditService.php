<?php

/**
 * AuditService - Audit trail management for Heratio
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

namespace Ahg\AuditTrail\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditService
{
    /**
     * Action types
     */
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_VIEW = 'view';
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_EXPORT = 'export';
    public const ACTION_IMPORT = 'import';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_PUBLISH = 'publish';
    public const ACTION_UNPUBLISH = 'unpublish';

    /**
     * Log an audit event
     */
    public function log(string $action, int $objectId, ?int $userId = null, array $details = []): int
    {
        return DB::table('audit_log')->insertGetId([
            'action' => $action,
            'object_id' => $objectId,
            'object_type' => $details['object_type'] ?? null,
            'user_id' => $userId ?? auth()->id(),
            'ip_address' => $details['ip_address'] ?? request()->ip(),
            'user_agent' => $details['user_agent'] ?? request()->userAgent(),
            'old_values' => isset($details['old_values']) ? json_encode($details['old_values']) : null,
            'new_values' => isset($details['new_values']) ? json_encode($details['new_values']) : null,
            'description' => $details['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Browse audit logs with filters
     */
    public function browse(array $filters = []): array
    {
        $query = DB::table('audit_log')
            ->select('audit_log.*')
            ->orderBy('created_at', 'desc');

        // User filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Action filter
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // Object type filter
        if (!empty($filters['object_type'])) {
            $query->where('object_type', $filters['object_type']);
        }

        // Object ID filter
        if (!empty($filters['object_id'])) {
            $query->where('object_id', $filters['object_id']);
        }

        // Date range
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, (int) ($filters['limit'] ?? 50));

        $logs = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'data' => array_map(fn($l) => $this->formatLog($l), $logs),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get audit trail for specific object
     */
    public function getForObject(int $objectId, ?string $objectType = null, int $limit = 100): array
    {
        $query = DB::table('audit_log')
            ->where('object_id', $objectId);

        if ($objectType) {
            $query->where('object_type', $objectType);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($l) => $this->formatLog($l))
            ->toArray();
    }

    /**
     * Get audit trail for specific user
     */
    public function getForUser(int $userId, int $limit = 100): array
    {
        return DB::table('audit_log')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($l) => $this->formatLog($l))
            ->toArray();
    }

    /**
     * Get statistics
     */
    public function getStats(array $filters = []): array
    {
        $query = DB::table('audit_log');

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Count by action
        $byAction = DB::table('audit_log')
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        // Count by user
        $byUser = DB::table('audit_log')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        // Recent activity (last 24h, 7d, 30d)
        $last24h = DB::table('audit_log')
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->count();

        $last7d = DB::table('audit_log')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $last30d = DB::table('audit_log')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        return [
            'total' => $query->count(),
            'by_action' => $byAction,
            'by_user' => $byUser,
            'last_24h' => $last24h,
            'last_7d' => $last7d,
            'last_30d' => $last30d,
        ];
    }

    /**
     * Clean old audit logs
     */
    public function clean(int $daysOld = 90): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);

        $count = DB::table('audit_log')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count > 0) {
            DB::table('audit_log')
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        return $count;
    }

    /**
     * Format log for display
     */
    protected function formatLog(object $log): array
    {
        $data = (array) $log;
        
        // Decode JSON fields
        $data['old_values'] = $data['old_values'] ? json_decode($data['old_values'], true) : null;
        $data['new_values'] = $data['new_values'] ? json_decode($data['new_values'], true) : null;
        
        return $data;
    }
}
