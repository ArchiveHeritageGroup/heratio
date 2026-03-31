<?php

/**
 * SecurityClearanceService - Service for Heratio
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


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security Clearance Service.
 *
 * Comprehensive security classification and access control service.
 * Implements hierarchical clearance levels, compartmentalised access, 2FA verification,
 * declassification scheduling, and complete audit logging.
 *
 * Migrated from: ahgSecurityClearancePlugin/lib/Services/SecurityClearanceService.php
 */
class SecurityClearanceService
{
    /** @var array Cached classification levels */
    private static array $classificationCache = [];

    // =========================================================================
    // Classification Level Management
    // =========================================================================

    public function getClassificationLevels(): \Illuminate\Support\Collection
    {
        return DB::table('security_classification')
            ->where('active', 1)
            ->orderBy('level', 'asc')
            ->get();
    }

    public function getClassification(int $id): ?object
    {
        return DB::table('security_classification')
            ->where('id', $id)
            ->first();
    }

    public static function getAllClassifications(): array
    {
        if (empty(self::$classificationCache)) {
            self::$classificationCache = DB::table('security_classification')
                ->where('active', 1)
                ->orderBy('level', 'asc')
                ->get()
                ->toArray();
        }

        return self::$classificationCache;
    }

    // =========================================================================
    // User Clearance Management
    // =========================================================================

    public function getUserClearance(int $userId): ?object
    {
        return DB::table('user_security_clearance as usc')
            ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->where('usc.user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('usc.expires_at')
                    ->orWhere('usc.expires_at', '>=', now());
            })
            ->select(
                'usc.*',
                'sc.code',
                'sc.name',
                'sc.name as classification_name',
                'sc.level',
                'sc.color',
                'sc.requires_2fa',
                'sc.watermark_required',
                'sc.download_allowed',
                'sc.print_allowed',
                'sc.copy_allowed'
            )
            ->first();
    }

    /**
     * Get user clearance record (including expired) for admin view.
     */
    public function getUserClearanceRecord(int $userId): ?object
    {
        return DB::table('user_security_clearance as usc')
            ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->leftJoin('user as granter', 'usc.granted_by', '=', 'granter.id')
            ->where('usc.user_id', $userId)
            ->select(
                'usc.*',
                'sc.code',
                'sc.name as classification_name',
                'sc.code as classification_code',
                'sc.level as classification_level',
                'sc.color',
                'granter.username as granted_by_name'
            )
            ->first();
    }

    public function getUserClearanceLevel(int $userId): int
    {
        $clearance = $this->getUserClearance($userId);
        return $clearance ? $clearance->level : 0;
    }

    /**
     * Get all users with their clearances.
     */
    public function getAllUsersWithClearances(): array
    {
        return DB::table('user as u')
            ->leftJoin('user_security_clearance as usc', 'u.id', '=', 'usc.user_id')
            ->leftJoin('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->leftJoin('user as granter', 'usc.granted_by', '=', 'granter.id')
            ->select(
                'u.id',
                'u.username',
                'u.email',
                'u.active',
                'usc.id as clearance_id',
                'usc.classification_id',
                'usc.granted_at',
                'usc.expires_at',
                'usc.notes',
                'sc.name as classification_name',
                'sc.code as classification_code',
                'sc.level as classification_level',
                'sc.color',
                'granter.username as granted_by_name'
            )
            ->orderBy('u.username')
            ->get()
            ->toArray();
    }

    /**
     * Grant or update clearance.
     */
    public function grantClearance(int $userId, int $classificationId, int $grantedBy, ?string $expiresAt = null, ?string $notes = null): bool
    {
        try {
            DB::beginTransaction();

            $previous = DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->first();

            $data = [
                'user_id'           => $userId,
                'classification_id' => $classificationId,
                'granted_by'        => $grantedBy,
                'granted_at'        => now(),
                'expires_at'        => $expiresAt ?: null,
                'notes'             => $notes,
            ];

            if ($previous) {
                // Log change
                $this->logClearanceChange($userId, 'updated', $previous->classification_id, $classificationId, $grantedBy, $notes);

                DB::table('user_security_clearance')
                    ->where('user_id', $userId)
                    ->update($data);
            } else {
                $this->logClearanceChange($userId, 'granted', null, $classificationId, $grantedBy, $notes);

                DB::table('user_security_clearance')->insert($data);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Revoke user clearance.
     */
    public function revokeClearance(int $userId, int $revokedBy, ?string $notes = null): bool
    {
        try {
            DB::beginTransaction();

            $previous = DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->first();

            if ($previous) {
                $this->logClearanceChange($userId, 'revoked', $previous->classification_id, null, $revokedBy, $notes ?: 'Clearance revoked by administrator');

                DB::table('user_security_clearance')
                    ->where('user_id', $userId)
                    ->delete();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Bulk grant clearances to multiple users.
     */
    public function bulkGrant(array $userIds, int $classificationId, int $grantedBy, ?string $notes = null): int
    {
        $successCount = 0;
        foreach ($userIds as $userId) {
            if ($this->grantClearance((int) $userId, $classificationId, $grantedBy, null, $notes)) {
                $successCount++;
            }
        }
        return $successCount;
    }

    /**
     * Get clearance history for a user.
     */
    public function getClearanceHistory(int $userId): array
    {
        return DB::table('user_security_clearance_log as log')
            ->leftJoin('security_classification as sc', 'log.classification_id', '=', 'sc.id')
            ->leftJoin('security_classification as prev_sc', 'log.previous_classification_id', '=', 'prev_sc.id')
            ->leftJoin('user as actor', 'log.changed_by', '=', 'actor.id')
            ->where('log.user_id', $userId)
            ->select(
                'log.*',
                'sc.name as classification_name',
                'sc.code as classification_code',
                'prev_sc.name as previous_name',
                'actor.username as changed_by_name'
            )
            ->orderByDesc('log.created_at')
            ->get()
            ->toArray();
    }

    private function logClearanceChange(int $userId, string $action, ?int $previousClassificationId, ?int $classificationId, int $changedBy, ?string $notes): void
    {
        if (Schema::hasTable('user_security_clearance_log')) {
            DB::table('user_security_clearance_log')->insert([
                'user_id'                      => $userId,
                'action'                       => $action,
                'previous_classification_id'   => $previousClassificationId,
                'classification_id'            => $classificationId,
                'changed_by'                   => $changedBy,
                'notes'                        => $notes,
                'created_at'                   => now(),
            ]);
        }
    }

    // =========================================================================
    // Object Classification
    // =========================================================================

    public function getObjectClassification(int $objectId): ?object
    {
        return DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->where('osc.object_id', $objectId)
            ->where('osc.active', 1)
            ->select(
                'osc.*',
                'sc.code',
                'sc.name',
                'sc.level',
                'sc.color',
                'sc.requires_2fa',
                'sc.watermark_required'
            )
            ->first();
    }

    public function classifyObject(int $objectId, int $classificationId, int $userId, ?string $reason = null, ?array $compartmentIds = null): bool
    {
        try {
            DB::beginTransaction();

            // Deactivate existing classification
            DB::table('object_security_classification')
                ->where('object_id', $objectId)
                ->update(['active' => 0]);

            DB::table('object_security_classification')->insert([
                'object_id'         => $objectId,
                'classification_id' => $classificationId,
                'classified_by'     => $userId,
                'classified_at'     => now(),
                'reason'            => $reason,
                'active'            => 1,
                'created_at'        => now(),
            ]);

            // Assign compartments if provided
            if ($compartmentIds) {
                DB::table('object_compartment_access')
                    ->where('object_id', $objectId)
                    ->delete();

                foreach ($compartmentIds as $compId) {
                    DB::table('object_compartment_access')->insert([
                        'object_id'      => $objectId,
                        'compartment_id' => (int) $compId,
                        'granted_by'     => $userId,
                        'created_at'     => now(),
                    ]);
                }
            }

            // Log in audit
            $this->logSecurityAudit($userId, $objectId, 'classify', [
                'classification_id' => $classificationId,
                'reason' => $reason,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function declassifyObject(int $objectId, int $userId, ?int $newClassificationId = null, ?string $reason = null): bool
    {
        try {
            DB::beginTransaction();

            $current = $this->getObjectClassification($objectId);

            DB::table('object_security_classification')
                ->where('object_id', $objectId)
                ->update(['active' => 0]);

            if ($newClassificationId) {
                DB::table('object_security_classification')->insert([
                    'object_id'         => $objectId,
                    'classification_id' => $newClassificationId,
                    'classified_by'     => $userId,
                    'classified_at'     => now(),
                    'reason'            => $reason ?? 'Declassified',
                    'active'            => 1,
                    'created_at'        => now(),
                ]);
            }

            $this->logSecurityAudit($userId, $objectId, 'declassify', [
                'previous_classification_id' => $current->classification_id ?? null,
                'new_classification_id' => $newClassificationId,
                'reason' => $reason,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    // =========================================================================
    // Compartments
    // =========================================================================

    public function getCompartments(): \Illuminate\Support\Collection
    {
        return DB::table('security_compartment')
            ->orderBy('name')
            ->get();
    }

    public function getCompartmentUserCounts(): array
    {
        if (!Schema::hasTable('user_compartment_access')) {
            return [];
        }

        return DB::table('user_compartment_access')
            ->select('compartment_id', DB::raw('COUNT(*) as count'))
            ->groupBy('compartment_id')
            ->pluck('count', 'compartment_id')
            ->toArray();
    }

    public function getCompartmentAccessGrants(): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('user_compartment_access')) {
            return collect();
        }

        return DB::table('user_compartment_access as uca')
            ->join('security_compartment as sc', 'uca.compartment_id', '=', 'sc.id')
            ->join('user as u', 'uca.user_id', '=', 'u.id')
            ->leftJoin('user as granter', 'uca.granted_by', '=', 'granter.id')
            ->select(
                'uca.*',
                'sc.name as compartment_name',
                'sc.code as compartment_code',
                'u.username',
                'granter.username as granted_by_name'
            )
            ->orderBy('sc.name')
            ->orderBy('u.username')
            ->get();
    }

    // =========================================================================
    // Access Requests
    // =========================================================================

    public function getAccessRequests(?string $status = 'pending'): \Illuminate\Support\Collection
    {
        $query = DB::table('security_access_request as sar')
            ->join('user as u', 'sar.user_id', '=', 'u.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('sar.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'sar.object_id', '=', 's.object_id')
            ->select('sar.*', 'u.username', 'ioi.title as object_title', 's.slug');

        if ($status) {
            $query->where('sar.status', $status);
        }

        return $query->orderByDesc('sar.created_at')->get();
    }

    public function submitAccessRequest(int $userId, int $objectId, string $requestType, string $justification, string $priority = 'normal', ?int $durationHours = 24): bool
    {
        try {
            DB::table('security_access_request')->insert([
                'user_id'        => $userId,
                'object_id'      => $objectId,
                'request_type'   => $requestType,
                'justification'  => $justification,
                'priority'       => $priority,
                'duration_hours' => $durationHours,
                'status'         => 'pending',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function reviewAccessRequest(int $requestId, string $decision, int $reviewerId, ?string $notes = null, ?int $durationHours = null): bool
    {
        try {
            $data = [
                'status'       => $decision,
                'reviewed_by'  => $reviewerId,
                'reviewed_at'  => now(),
                'review_notes' => $notes,
                'updated_at'   => now(),
            ];

            if ($decision === 'approved' && $durationHours) {
                $data['access_granted_until'] = now()->addHours($durationHours);
            }

            DB::table('security_access_request')
                ->where('id', $requestId)
                ->update($data);

            // Log the action
            $req = DB::table('security_access_request')->where('id', $requestId)->first();
            if ($req && Schema::hasTable('security_access_log')) {
                DB::table('security_access_log')->insert([
                    'user_id'    => $req->user_id,
                    'object_id'  => $req->object_id,
                    'action'     => $decision === 'approved' ? 'access_granted' : 'access_denied',
                    'details'    => json_encode(['request_id' => $requestId, 'reviewer' => $reviewerId]),
                    'created_at' => now(),
                ]);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revoke an object access grant.
     */
    public function revokeObjectAccess(int $grantId, int $revokedBy): bool
    {
        try {
            if (Schema::hasTable('object_access_grant')) {
                DB::table('object_access_grant')
                    ->where('id', $grantId)
                    ->update([
                        'active'     => 0,
                        'revoked_by' => $revokedBy,
                        'revoked_at' => now(),
                    ]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get object access grants for a user.
     */
    public function getUserAccessGrants(int $userId): array
    {
        if (!Schema::hasTable('object_access_grant')) {
            return [];
        }

        $grants = DB::table('object_access_grant as oag')
            ->leftJoin('user as granter', 'oag.granted_by', '=', 'granter.id')
            ->where('oag.user_id', $userId)
            ->where('oag.active', 1)
            ->select('oag.*', 'granter.username as granted_by_name')
            ->orderByDesc('oag.granted_at')
            ->get()
            ->toArray();

        foreach ($grants as &$grant) {
            $grant->object_title = DB::table('information_object_i18n')
                ->where('id', $grant->object_id ?? 0)
                ->where('culture', 'en')
                ->value('title') ?? 'Unknown';
        }

        return $grants;
    }

    // =========================================================================
    // Dashboard Statistics
    // =========================================================================

    public function getDashboardStatistics(): array
    {
        $stats = [
            'pending_requests'   => 0,
            'expiring_clearances' => 0,
            'recent_denials'     => 0,
            'reviews_due'        => 0,
            'clearances_by_level' => [],
            'objects_by_level'    => [],
        ];

        try {
            $stats['pending_requests'] = DB::table('security_access_request')
                ->where('status', 'pending')->count();

            $stats['expiring_clearances'] = DB::table('user_security_clearance')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30))
                ->where('expires_at', '>', now())
                ->count();

            $stats['recent_denials'] = DB::table('security_access_request')
                ->where('status', 'denied')
                ->where('updated_at', '>=', now()->subDays(7))
                ->count();

            if (Schema::hasTable('object_declassification_schedule')) {
                $stats['reviews_due'] = DB::table('object_declassification_schedule')
                    ->where('scheduled_date', '<=', now()->addDays(30))
                    ->where('processed', 0)
                    ->count();
            }

            $stats['clearances_by_level'] = DB::table('user_security_clearance as usc')
                ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
                ->select('sc.name', 'sc.color', DB::raw('COUNT(*) as count'))
                ->groupBy('sc.id', 'sc.name', 'sc.color')
                ->orderBy('sc.level')
                ->get()
                ->toArray();

            if (Schema::hasTable('object_security_classification')) {
                $stats['objects_by_level'] = DB::table('object_security_classification as osc')
                    ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                    ->where('osc.active', 1)
                    ->select('sc.name', 'sc.color', DB::raw('COUNT(*) as count'))
                    ->groupBy('sc.id', 'sc.name', 'sc.color')
                    ->orderBy('sc.level')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            // Tables may not exist yet
        }

        return $stats;
    }

    public function getPendingRequests(int $limit = 10): array
    {
        try {
            return DB::table('security_access_request as sar')
                ->join('user as u', 'sar.user_id', '=', 'u.id')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('sar.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->where('sar.status', 'pending')
                ->select('sar.*', 'u.username', 'ioi.title as object_title', 'sar.id as request_id')
                ->orderByDesc('sar.created_at')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getExpiringClearances(int $limit = 10): array
    {
        try {
            return DB::table('user_security_clearance as usc')
                ->join('user as u', 'usc.user_id', '=', 'u.id')
                ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
                ->whereNotNull('usc.expires_at')
                ->where('usc.expires_at', '<=', now()->addDays(30))
                ->where('usc.expires_at', '>', now())
                ->select(
                    'usc.*',
                    'u.username',
                    'u.id as user_id',
                    'sc.name as clearance_name',
                    'sc.color',
                    DB::raw('DATEDIFF(usc.expires_at, CURDATE()) as days_remaining'),
                    DB::raw("'none' as renewal_status")
                )
                ->orderBy('usc.expires_at')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getDueDeclassifications(int $limit = 10): array
    {
        if (!Schema::hasTable('object_declassification_schedule')) {
            return [];
        }

        try {
            return DB::table('object_declassification_schedule as ods')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('ods.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->leftJoin('information_object as io', 'ods.object_id', '=', 'io.id')
                ->leftJoin('security_classification as sc_from', 'ods.from_classification_id', '=', 'sc_from.id')
                ->leftJoin('security_classification as sc_to', 'ods.to_classification_id', '=', 'sc_to.id')
                ->where('ods.scheduled_date', '<=', now()->addDays(30))
                ->where('ods.processed', 0)
                ->select(
                    'ods.*',
                    'ioi.title',
                    'io.identifier',
                    'sc_from.name as from_classification',
                    'sc_to.name as to_classification'
                )
                ->orderBy('ods.scheduled_date')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // Report Statistics
    // =========================================================================

    public function getReportStats(string $period = '30 days'): array
    {
        $since = now()->sub(\DateInterval::createFromDateString($period));

        $clearanceStats = [
            'total_users'       => DB::table('user')->where('active', 1)->count(),
            'with_clearance'    => DB::table('user_security_clearance')->distinct('user_id')->count('user_id'),
            'without_clearance' => DB::table('user')
                ->where('active', 1)
                ->whereNotIn('id', function ($q) {
                    $q->select('user_id')->from('user_security_clearance');
                })
                ->count(),
        ];

        $clearancesByLevel = DB::table('user_security_clearance as usc')
            ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->select('sc.name', 'sc.code', 'sc.color', 'sc.level', DB::raw('COUNT(*) as count'))
            ->groupBy('sc.id', 'sc.name', 'sc.code', 'sc.color', 'sc.level')
            ->orderBy('sc.level')
            ->get()
            ->toArray();

        $objectsByLevel = [];
        if (Schema::hasTable('object_security_classification')) {
            $objectsByLevel = DB::table('object_security_classification as osc')
                ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                ->where('osc.active', 1)
                ->select('sc.name', 'sc.code', 'sc.color', 'sc.level', DB::raw('COUNT(*) as count'))
                ->groupBy('sc.id', 'sc.name', 'sc.code', 'sc.color', 'sc.level')
                ->orderBy('sc.level')
                ->get()
                ->toArray();
        }

        $requestStats = [
            'pending'  => DB::table('security_access_request')->where('status', 'pending')->count(),
            'approved' => DB::table('security_access_request')
                ->where('status', 'approved')
                ->where('updated_at', '>=', $since)
                ->count(),
            'denied'   => DB::table('security_access_request')
                ->where('status', 'denied')
                ->where('updated_at', '>=', $since)
                ->count(),
        ];

        return compact('clearanceStats', 'clearancesByLevel', 'objectsByLevel', 'requestStats');
    }

    // =========================================================================
    // Security Compliance
    // =========================================================================

    public function getComplianceStats(): array
    {
        $stats = [
            'classified_objects' => 0,
            'pending_reviews'    => 0,
            'cleared_users'      => 0,
            'access_logs_today'  => 0,
        ];

        try {
            if (Schema::hasTable('object_security_classification')) {
                $stats['classified_objects'] = DB::table('object_security_classification')
                    ->where('active', 1)->count();
            }
            $stats['cleared_users'] = DB::table('user_security_clearance')->count();
            if (Schema::hasTable('user_security_clearance_log')) {
                $stats['access_logs_today'] = DB::table('user_security_clearance_log')
                    ->whereDate('created_at', today())->count();
            }
        } catch (\Exception $e) {
            // Tables may not exist
        }

        return $stats;
    }

    public function getRecentComplianceLogs(int $limit = 10): array
    {
        if (!Schema::hasTable('user_security_clearance_log')) {
            return [];
        }

        return DB::table('user_security_clearance_log')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // =========================================================================
    // Audit Logging
    // =========================================================================

    public function logSecurityAudit(int $userId, ?int $objectId, string $action, array $details = []): void
    {
        if (Schema::hasTable('security_audit_log')) {
            $username = DB::table('user')->where('id', $userId)->value('username');

            DB::table('security_audit_log')->insert([
                'object_id'       => $objectId,
                'object_type'     => 'information_object',
                'user_id'         => $userId,
                'user_name'       => $username,
                'action'          => $action,
                'action_category' => 'security',
                'details'         => json_encode($details),
                'ip_address'      => request()->ip(),
                'user_agent'      => request()->userAgent(),
                'created_at'      => now(),
            ]);
        }
    }

    /**
     * Get security audit log entries with filters.
     */
    public function getAuditLog(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table('security_audit_log as sal')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('sal.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'sal.object_id', '=', 'slug.object_id');

        if (!empty($filters['user_name'])) {
            $query->where('sal.user_name', 'LIKE', '%' . $filters['user_name'] . '%');
        }
        if (!empty($filters['action'])) {
            $query->where('sal.action', $filters['action']);
        }
        if (!empty($filters['category'])) {
            $query->where('sal.action_category', 'LIKE', '%' . $filters['category'] . '%');
        }
        if (!empty($filters['date_from'])) {
            $query->where('sal.created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $query->where('sal.created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $total = $query->count();

        $logs = $query->select(
            'sal.*',
            'ioi.title as object_title',
            'slug.slug as object_slug'
        )
            ->orderByDesc('sal.created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return compact('logs', 'total');
    }

    /**
     * Export audit log as CSV data array.
     */
    public function exportAuditLog(int $limit = 10000): \Illuminate\Support\Collection
    {
        return DB::table('security_audit_log as sal')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('sal.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->select(
                'sal.created_at',
                'sal.user_name',
                'sal.action',
                'sal.action_category',
                'ioi.title as object_title',
                'sal.ip_address'
            )
            ->orderByDesc('sal.created_at')
            ->limit($limit)
            ->get();
    }
}
