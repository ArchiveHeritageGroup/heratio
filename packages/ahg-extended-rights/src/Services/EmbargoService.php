<?php

/**
 * EmbargoService - Service for Heratio
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



namespace AhgExtendedRights\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * EmbargoService - Comprehensive embargo management for archival records
 *
 * Handles full lifecycle: create, update, lift, access control, exceptions,
 * hierarchical propagation, statistics, and audit logging.
 *
 * Migrated from AtoM ahgExtendedRightsPlugin EmbargoService.
 */
class EmbargoService
{
    protected string $culture;

    // Embargo type constants
    public const TYPE_FULL = 'full';
    public const TYPE_METADATA_ONLY = 'metadata_only';
    public const TYPE_DIGITAL_ONLY = 'digital_only';
    public const TYPE_PARTIAL = 'partial';

    /**
     * Per-request access cache to avoid repeated DB lookups.
     */
    private static array $accessCache = [];

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    // =========================================================================
    // RETRIEVAL METHODS
    // =========================================================================

    /**
     * Get a single embargo with i18n data.
     */
    public function getEmbargo(int $id): ?object
    {
        if (!Schema::hasTable('rights_embargo')) {
            return null;
        }

        return DB::table('rights_embargo as e')
            ->leftJoin('rights_embargo_i18n as ei', function ($join) {
                $join->on('ei.id', '=', 'e.id')
                    ->where('ei.culture', '=', $this->culture);
            })
            ->where('e.id', $id)
            ->select(['e.*', 'ei.reason_note as reason', 'ei.internal_note as notes'])
            ->first();
    }

    /**
     * Get all embargoes for a given object.
     */
    public function getObjectEmbargoes(int $objectId): Collection
    {
        if (!Schema::hasTable('rights_embargo')) {
            return collect();
        }

        return DB::table('rights_embargo as e')
            ->leftJoin('rights_embargo_i18n as ei', function ($join) {
                $join->on('ei.id', '=', 'e.id')
                    ->where('ei.culture', '=', $this->culture);
            })
            ->where('e.object_id', $objectId)
            ->orderByDesc('e.created_at')
            ->select(['e.*', 'ei.reason_note as reason', 'ei.internal_note as notes'])
            ->get();
    }

    /**
     * Get the currently active embargo for an object.
     */
    public function getActiveEmbargo(int $objectId): ?object
    {
        if (!Schema::hasTable('rights_embargo')) {
            return null;
        }

        $now = date('Y-m-d');

        return DB::table('rights_embargo as e')
            ->leftJoin('rights_embargo_i18n as ei', function ($join) {
                $join->on('ei.id', '=', 'e.id')
                    ->where('ei.culture', '=', $this->culture);
            })
            ->where('e.object_id', $objectId)
            ->where('e.status', 'active')
            ->where('e.start_date', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('e.end_date')
                    ->orWhere('e.end_date', '>=', $now);
            })
            ->select(['e.*', 'ei.reason_note as reason'])
            ->first();
    }

    /**
     * Get all active embargoes across the system, with optional filter.
     */
    public function getActiveEmbargoes(?string $filter = null): Collection
    {
        if (!Schema::hasTable('rights_embargo')) {
            return collect();
        }

        $now = date('Y-m-d');

        $query = DB::table('rights_embargo as e')
            ->leftJoin('rights_embargo_i18n as ei', function ($join) {
                $join->on('ei.id', '=', 'e.id')
                    ->where('ei.culture', '=', $this->culture);
            })
            ->leftJoin('information_object as io', 'e.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('e.status', 'active')
            ->where('e.start_date', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('e.end_date')
                    ->orWhere('e.end_date', '>=', $now);
            });

        // Apply optional filter on embargo type
        if ($filter && in_array($filter, [self::TYPE_FULL, self::TYPE_METADATA_ONLY, self::TYPE_DIGITAL_ONLY, self::TYPE_PARTIAL])) {
            $query->where('e.embargo_type', $filter);
        }

        return $query
            ->select([
                'e.*',
                'ei.reason_note as reason',
                'ioi.title as object_title',
                'slug.slug as object_slug',
            ])
            ->orderByDesc('e.created_at')
            ->get();
    }

    /**
     * Get embargoes expiring within the given number of days.
     */
    public function getExpiringEmbargoes(int $days = 30): Collection
    {
        if (!Schema::hasTable('rights_embargo')) {
            return collect();
        }

        $now = date('Y-m-d');
        $future = date('Y-m-d', strtotime("+{$days} days"));

        return DB::table('rights_embargo as e')
            ->leftJoin('rights_embargo_i18n as ei', function ($join) {
                $join->on('ei.id', '=', 'e.id')
                    ->where('ei.culture', '=', $this->culture);
            })
            ->leftJoin('information_object as io', 'e.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('e.status', 'active')
            ->where('e.auto_release', true)
            ->whereNotNull('e.end_date')
            ->whereBetween('e.end_date', [$now, $future])
            ->select([
                'e.*',
                'ei.reason_note as reason',
                'ioi.title as object_title',
                'slug.slug as object_slug',
            ])
            ->orderBy('e.end_date')
            ->get();
    }

    /**
     * Check if an object is currently embargoed.
     */
    public function isEmbargoed(int $objectId): bool
    {
        return $this->getActiveEmbargo($objectId) !== null;
    }

    // =========================================================================
    // ACCESS CONTROL METHODS
    // =========================================================================

    /**
     * Full access check: considers embargo, exceptions (user, IP, group).
     */
    public function checkAccess(int $objectId, ?int $userId = null, ?string $ipAddress = null): bool
    {
        $embargo = $this->getActiveEmbargo($objectId);

        if (!$embargo) {
            return true;
        }

        if (!Schema::hasTable('embargo_exception')) {
            return false;
        }

        // Check embargo exceptions
        $now = date('Y-m-d H:i:s');

        $exceptions = DB::table('embargo_exception')
            ->where('embargo_id', $embargo->id)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->get();

        foreach ($exceptions as $exc) {
            // User exception
            if ($exc->exception_type === 'user' && $userId !== null && $userId === (int) $exc->exception_id) {
                return true;
            }

            // IP range exception
            if ($exc->exception_type === 'ip_range' && $ipAddress) {
                if ($this->isIpInRange($ipAddress, $exc->ip_range_start, $exc->ip_range_end)) {
                    return true;
                }
            }

            // Group exception: check if user belongs to the group
            if ($exc->exception_type === 'group' && $userId !== null) {
                $inGroup = DB::table('acl_user_group')
                    ->where('user_id', $userId)
                    ->where('group_id', (int) $exc->exception_id)
                    ->exists();

                if ($inGroup) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user can access the record at all.
     * Full embargo blocks all access for non-privileged users.
     */
    public function canAccessRecord(int $objectId, ?int $userId = null): bool
    {
        if ($this->userCanBypassEmbargo($userId)) {
            return true;
        }

        $embargo = $this->getActiveEmbargo($objectId);

        if (!$embargo) {
            return true;
        }

        return $embargo->embargo_type !== self::TYPE_FULL;
    }

    /**
     * Check if user can view metadata.
     */
    public function canViewMetadata(int $objectId, ?int $userId = null): bool
    {
        if ($this->userCanBypassEmbargo($userId)) {
            return true;
        }

        $embargo = $this->getActiveEmbargo($objectId);

        if (!$embargo) {
            return true;
        }

        // Full embargo blocks metadata
        return $embargo->embargo_type !== self::TYPE_FULL;
    }

    /**
     * Check if user can view thumbnail.
     */
    public function canViewThumbnail(int $objectId, ?int $userId = null): bool
    {
        if ($this->userCanBypassEmbargo($userId)) {
            return true;
        }

        $embargo = $this->getActiveEmbargo($objectId);

        if (!$embargo) {
            return true;
        }

        // Full and metadata_only block thumbnails
        return !in_array($embargo->embargo_type, [self::TYPE_FULL, self::TYPE_METADATA_ONLY]);
    }

    /**
     * Check if user can view full digital object (master/reference).
     */
    public function canViewDigitalObject(int $objectId, ?int $userId = null): bool
    {
        if ($this->userCanBypassEmbargo($userId)) {
            return true;
        }

        $embargo = $this->getActiveEmbargo($objectId);

        if (!$embargo) {
            return true;
        }

        // All embargo types except partial block full digital object
        return $embargo->embargo_type === self::TYPE_PARTIAL;
    }

    /**
     * Check if user can download.
     */
    public function canDownload(int $objectId, ?int $userId = null): bool
    {
        if ($this->userCanBypassEmbargo($userId)) {
            return true;
        }

        $embargo = $this->getActiveEmbargo($objectId);

        if (!$embargo) {
            return true;
        }

        // All embargo types block downloads
        return false;
    }

    /**
     * Get embargo display info for blocked page (public-safe).
     */
    public function getEmbargoDisplayInfo(int $objectId): ?array
    {
        $embargo = $this->getActiveEmbargo($objectId);

        if (!$embargo) {
            return null;
        }

        $typeLabels = [
            self::TYPE_FULL => 'Full Access Restriction',
            self::TYPE_METADATA_ONLY => 'Digital Content Restricted',
            self::TYPE_DIGITAL_ONLY => 'Download Restricted',
            self::TYPE_PARTIAL => 'Partial Restriction',
        ];

        return [
            'type' => $embargo->embargo_type,
            'type_label' => $typeLabels[$embargo->embargo_type] ?? 'Access Restricted',
            'public_message' => $embargo->reason ?? null,
            'end_date' => $embargo->end_date,
            'is_perpetual' => !$embargo->auto_release,
        ];
    }

    /**
     * Bulk filter: return object IDs that are NOT under full embargo.
     * Useful for filtering search/browse results.
     */
    public function filterAccessibleIds(array $objectIds, ?int $userId = null): array
    {
        if (empty($objectIds)) {
            return [];
        }

        if (!Schema::hasTable('rights_embargo')) {
            return $objectIds;
        }

        // If user can bypass, return all IDs
        if ($this->userCanBypassEmbargo($userId)) {
            return $objectIds;
        }

        $now = date('Y-m-d');

        // Get IDs under full embargo
        $embargoedIds = DB::table('rights_embargo')
            ->whereIn('object_id', $objectIds)
            ->where('status', 'active')
            ->where('embargo_type', self::TYPE_FULL)
            ->where('start_date', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->pluck('object_id')
            ->toArray();

        return array_values(array_diff($objectIds, $embargoedIds));
    }

    // =========================================================================
    // MUTATION METHODS
    // =========================================================================

    /**
     * Create a new embargo.
     */
    public function createEmbargo(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $startDate = $data['start_date'] ?? date('Y-m-d');
        $status = strtotime($startDate) <= time() ? 'active' : 'pending';

        // Map embargo_type values
        $embargoType = $data['embargo_type'] ?? self::TYPE_FULL;
        if ($embargoType === 'digital_object') {
            $embargoType = self::TYPE_DIGITAL_ONLY;
        }

        // Map reason to enum
        $reasonEnum = $this->mapReasonToEnum($data['reason'] ?? null);

        $embargoId = DB::table('rights_embargo')->insertGetId([
            'object_id' => $data['object_id'],
            'embargo_type' => $embargoType,
            'reason' => $reasonEnum,
            'start_date' => $startDate,
            'end_date' => $data['end_date'] ?? null,
            'auto_release' => !($data['is_perpetual'] ?? false),
            'status' => $status,
            'created_by' => $data['created_by'] ?? null,
            'notify_before_days' => $data['notify_days_before'] ?? 30,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Insert i18n for notes
        if (!empty($data['reason']) || !empty($data['notes'])) {
            DB::table('rights_embargo_i18n')->insert([
                'id' => $embargoId,
                'culture' => $this->culture,
                'reason_note' => $data['reason'] ?? null,
                'internal_note' => $data['notes'] ?? null,
            ]);
        }

        // Audit log
        $this->logAudit($embargoId, 'create', $data['created_by'] ?? null, [], [
            'object_id' => $data['object_id'],
            'embargo_type' => $embargoType,
            'start_date' => $startDate,
            'end_date' => $data['end_date'] ?? null,
            'status' => $status,
        ]);

        self::clearCache();

        return $embargoId;
    }

    /**
     * Update an existing embargo.
     */
    public function updateEmbargo(int $id, array $data): bool
    {
        $now = date('Y-m-d H:i:s');

        // Capture old values for audit
        $old = $this->getEmbargo($id);
        $oldValues = $old ? (array) $old : [];

        $updateData = [
            'updated_at' => $now,
        ];

        if (isset($data['embargo_type'])) {
            $embargoType = $data['embargo_type'];
            if ($embargoType === 'digital_object') {
                $embargoType = self::TYPE_DIGITAL_ONLY;
            }
            $updateData['embargo_type'] = $embargoType;
        }

        if (isset($data['start_date'])) {
            $updateData['start_date'] = $data['start_date'];
        }
        if (isset($data['end_date'])) {
            $updateData['end_date'] = $data['end_date'];
        }
        if (isset($data['is_perpetual'])) {
            $updateData['auto_release'] = !$data['is_perpetual'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['notify_days_before'])) {
            $updateData['notify_before_days'] = $data['notify_days_before'];
        }
        if (isset($data['reason'])) {
            $updateData['reason'] = $this->mapReasonToEnum($data['reason']);
        }

        $updated = DB::table('rights_embargo')
            ->where('id', $id)
            ->update($updateData) > 0;

        // Update i18n
        if (!empty($data['reason']) || !empty($data['notes'])) {
            DB::table('rights_embargo_i18n')
                ->updateOrInsert(
                    ['id' => $id, 'culture' => $this->culture],
                    [
                        'reason_note' => $data['reason'] ?? null,
                        'internal_note' => $data['notes'] ?? null,
                    ]
                );
        }

        // Audit log
        $this->logAudit($id, 'update', $data['updated_by'] ?? null, $oldValues, $updateData);

        self::clearCache();

        return $updated;
    }

    /**
     * Lift an embargo with audit trail.
     */
    public function liftEmbargo(int $id, int $userId, ?string $reason = null): bool
    {
        $old = $this->getEmbargo($id);
        $oldValues = $old ? (array) $old : [];

        $now = date('Y-m-d H:i:s');

        $lifted = DB::table('rights_embargo')
            ->where('id', $id)
            ->update([
                'status' => 'lifted',
                'lifted_by' => $userId,
                'lifted_at' => $now,
                'lift_reason' => $reason,
                'updated_at' => $now,
            ]) > 0;

        if ($lifted) {
            $this->logAudit($id, 'lift', $userId, $oldValues, [
                'status' => 'lifted',
                'lifted_by' => $userId,
                'lifted_at' => $now,
                'lift_reason' => $reason,
            ]);
        }

        self::clearCache();

        return $lifted;
    }

    /**
     * Create embargo for an object and optionally all its descendants
     * using the nested set (lft/rgt) hierarchy in information_object.
     */
    public function createEmbargoWithPropagation(int $objectId, array $data, bool $applyToChildren = false): array
    {
        $results = [
            'created' => 0,
            'failed' => 0,
            'ids' => [],
        ];

        // Ensure object_id is in data
        $data['object_id'] = $objectId;

        // Create embargo for the main object
        try {
            $embargoId = $this->createEmbargo($data);
            $results['created']++;
            $results['ids'][] = $embargoId;
        } catch (\Exception $e) {
            $results['failed']++;
            Log::error("Failed to create embargo for object {$objectId}: " . $e->getMessage());
        }

        // If propagation requested, apply to all descendants via lft/rgt
        if ($applyToChildren) {
            $object = DB::table('information_object')
                ->where('id', $objectId)
                ->select(['lft', 'rgt'])
                ->first();

            if ($object && $object->lft && $object->rgt) {
                $descendants = DB::table('information_object')
                    ->where('lft', '>', $object->lft)
                    ->where('rgt', '<', $object->rgt)
                    ->pluck('id')
                    ->toArray();

                foreach ($descendants as $childId) {
                    try {
                        $childData = $data;
                        $childData['object_id'] = $childId;
                        $childEmbargoId = $this->createEmbargo($childData);
                        $results['created']++;
                        $results['ids'][] = $childEmbargoId;
                    } catch (\Exception $e) {
                        $results['failed']++;
                        Log::error("Failed to create embargo for child {$childId}: " . $e->getMessage());
                    }
                }
            }
        }

        return $results;
    }

    // =========================================================================
    // EXCEPTION METHODS
    // =========================================================================

    /**
     * Get all exceptions for an embargo.
     */
    public function getExceptions(int $embargoId): Collection
    {
        if (!Schema::hasTable('embargo_exception')) {
            return collect();
        }

        return DB::table('embargo_exception')
            ->where('embargo_id', $embargoId)
            ->orderBy('exception_type')
            ->orderBy('id')
            ->get();
    }

    /**
     * Add an exception to an embargo (user, IP range, or group).
     */
    public function addException(int $embargoId, array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $exceptionId = DB::table('embargo_exception')->insertGetId([
            'embargo_id' => $embargoId,
            'exception_type' => $data['exception_type'],       // 'user', 'ip_range', 'group'
            'exception_id' => $data['exception_id'] ?? null,   // user_id or group_id
            'ip_range_start' => $data['ip_range_start'] ?? null,
            'ip_range_end' => $data['ip_range_end'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'created_at' => $now,
        ]);

        $this->logAudit($embargoId, 'add_exception', $data['created_by'] ?? null, [], [
            'exception_id' => $exceptionId,
            'exception_type' => $data['exception_type'],
        ]);

        self::clearCache();

        return $exceptionId;
    }

    /**
     * Remove an exception from an embargo.
     */
    public function removeException(int $exceptionId): bool
    {
        if (!Schema::hasTable('embargo_exception')) {
            return false;
        }

        // Get exception details for audit before deleting
        $exception = DB::table('embargo_exception')->where('id', $exceptionId)->first();

        if (!$exception) {
            return false;
        }

        $deleted = DB::table('embargo_exception')
            ->where('id', $exceptionId)
            ->delete() > 0;

        if ($deleted) {
            $this->logAudit(
                (int) $exception->embargo_id,
                'remove_exception',
                null,
                (array) $exception,
                []
            );
        }

        self::clearCache();

        return $deleted;
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get embargo statistics for admin dashboard.
     */
    public function getStatistics(): array
    {
        if (!Schema::hasTable('rights_embargo')) {
            return [
                'total' => 0,
                'active' => 0,
                'by_type' => [
                    'full' => 0,
                    'metadata_only' => 0,
                    'digital_only' => 0,
                ],
                'expired_not_lifted' => 0,
            ];
        }

        $stats = DB::table('rights_embargo')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN embargo_type = 'full' AND status = 'active' THEN 1 ELSE 0 END) as full_active,
                SUM(CASE WHEN embargo_type = 'metadata_only' AND status = 'active' THEN 1 ELSE 0 END) as metadata_only_active,
                SUM(CASE WHEN embargo_type = 'digital_only' AND status = 'active' THEN 1 ELSE 0 END) as digital_only_active,
                SUM(CASE WHEN end_date IS NOT NULL AND end_date < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as expired_not_lifted
            ")
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'active' => (int) ($stats->active ?? 0),
            'by_type' => [
                'full' => (int) ($stats->full_active ?? 0),
                'metadata_only' => (int) ($stats->metadata_only_active ?? 0),
                'digital_only' => (int) ($stats->digital_only_active ?? 0),
            ],
            'expired_not_lifted' => (int) ($stats->expired_not_lifted ?? 0),
        ];
    }

    // =========================================================================
    // AUDIT LOGGING
    // =========================================================================

    /**
     * Log an audit entry for embargo actions.
     */
    public function logAudit(int $embargoId, string $action, ?int $userId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        if (!Schema::hasTable('embargo_audit')) {
            return;
        }

        try {
            $changedFields = [];

            if ($action === 'delete' && !empty($oldValues)) {
                $changedFields = array_keys($oldValues);
            } elseif (!empty($newValues)) {
                foreach ($newValues as $key => $val) {
                    if (($oldValues[$key] ?? null) !== $val) {
                        $changedFields[] = $key;
                    }
                }
            }

            // `embargo_audit` stores old_values/new_values as JSON; the changed
            // field names are derivable from the diff, so no separate column.
            DB::table('embargo_audit')->insert([
                'embargo_id' => $embargoId,
                'action' => $action,
                'user_id' => $userId,
                'old_values' => !empty($oldValues) ? json_encode($oldValues) : null,
                'new_values' => !empty($newValues) ? json_encode($newValues) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            Log::error("Embargo audit log error: " . $e->getMessage());
        }
    }

    // =========================================================================
    // HELPER / PRIVATE METHODS
    // =========================================================================

    /**
     * Check if an IP address falls within a range.
     */
    protected function isIpInRange(string $ip, ?string $start, ?string $end): bool
    {
        if (!$start || !$end) {
            return false;
        }

        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return false;
        }

        return $ipLong >= ip2long($start) && $ipLong <= ip2long($end);
    }

    /**
     * Check if the current user can bypass embargoes (admin or editor).
     */
    private function userCanBypassEmbargo(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        // Check the access cache
        $cacheKey = "bypass_{$userId}";
        if (isset(self::$accessCache[$cacheKey])) {
            return self::$accessCache[$cacheKey];
        }

        // Check if user is an administrator (group_id 100 = admin in AtoM).
        // Canonical snake_case tables: acl_user_group, acl_group.
        $isAdmin = DB::table('user')
            ->join('acl_user_group', 'user.id', '=', 'acl_user_group.user_id')
            ->join('acl_group', 'acl_user_group.group_id', '=', 'acl_group.id')
            ->where('user.id', $userId)
            ->where('acl_group.id', 100)
            ->exists();

        if ($isAdmin) {
            self::$accessCache[$cacheKey] = true;
            return true;
        }

        // Check if user has editor-level permissions (group_id 101 = editor)
        $isEditor = DB::table('user')
            ->join('acl_user_group', 'user.id', '=', 'acl_user_group.user_id')
            ->where('user.id', $userId)
            ->where('acl_user_group.group_id', 101)
            ->exists();

        self::$accessCache[$cacheKey] = $isEditor;

        return $isEditor;
    }

    /**
     * Map free-text reason to enum value.
     */
    protected function mapReasonToEnum(?string $reason): string
    {
        if (empty($reason)) {
            return 'other';
        }

        $reasonLower = strtolower($reason);

        $mappings = [
            'donor' => 'donor_restriction',
            'copyright' => 'copyright',
            'privacy' => 'privacy',
            'legal' => 'legal',
            'commercial' => 'commercial',
            'research' => 'research',
            'cultural' => 'cultural',
            'security' => 'security',
        ];

        foreach ($mappings as $keyword => $enum) {
            if (str_contains($reasonLower, $keyword)) {
                return $enum;
            }
        }

        return 'other';
    }

    /**
     * Clear per-request access cache.
     */
    public static function clearCache(): void
    {
        self::$accessCache = [];
    }
}
