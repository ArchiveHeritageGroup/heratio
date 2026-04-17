<?php

/**
 * AccessRequestService - Service for Heratio
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



namespace AhgAccessRequest\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AccessRequestService
{
    /**
     * Browse all access requests (admin view).
     */
    public function getAllRequests(int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('access_request')
            ->leftJoin('user', 'access_request.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                     ->where('actor_i18n.culture', '=', 'en');
            })
            ->select(
                'access_request.*',
                'actor_i18n.authorized_form_of_name as user_name'
            )
            ->orderByDesc('access_request.created_at')
            ->paginate($perPage);
    }

    /**
     * Get pending access requests for admins/approvers.
     */
    public function getPendingRequests(int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('access_request')
            ->leftJoin('user', 'access_request.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                     ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('access_request.status', '=', 'pending')
            ->select(
                'access_request.*',
                'actor_i18n.authorized_form_of_name as user_name'
            )
            ->orderByDesc('access_request.created_at')
            ->paginate($perPage);
    }

    /**
     * Get requests for the current authenticated user.
     */
    public function getMyRequests(int $userId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('access_request')
            ->where('access_request.user_id', '=', $userId)
            ->orderByDesc('access_request.created_at')
            ->paginate($perPage);
    }

    /**
     * Get a single access request by ID.
     */
    public function getRequest(int $id): ?object
    {
        return DB::table('access_request')
            ->leftJoin('user', 'access_request.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                     ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('access_request.id', $id)
            ->select('access_request.*', 'actor_i18n.authorized_form_of_name as user_name')
            ->first();
    }

    /**
     * Get configured approvers.
     */
    public function getApprovers(): \Illuminate\Support\Collection
    {
        return DB::table('access_request_approver')
            ->join('user', 'access_request_approver.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                     ->where('actor_i18n.culture', '=', 'en');
            })
            ->select(
                'access_request_approver.*',
                'actor_i18n.authorized_form_of_name as user_name',
                'user.email'
            )
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();
    }

    /**
     * Create a new access request.
     *
     * Writes to `security_access_request` (the table the /security/my-requests
     * page reads from). Older callers used `access_request` — that table is now
     * legacy and should not be written to from new code.
     */
    public function createRequest(int $userId, array $data): int
    {
        // Combine subject + reason + justification into the single justification
        // column (the security_access_request schema is flatter than access_request).
        $justificationParts = [];
        if (!empty($data['reason'])) {
            $justificationParts[] = $data['reason'];
        }
        if (!empty($data['justification'])) {
            $justificationParts[] = "Justification: " . $data['justification'];
        }
        $justification = implode("\n\n", $justificationParts) ?: '(no details provided)';

        // Map urgency → priority
        $priority = $data['urgency'] ?? 'normal';

        return DB::table('security_access_request')->insertGetId([
            'user_id'           => $userId,
            'request_type'      => $data['request_type'] ?? 'clearance',
            'classification_id' => $data['object_id'] ?? null,
            'object_id'         => $data['target_object_id'] ?? null,
            'justification'     => $justification,
            'priority'          => $priority,
            'status'            => 'pending',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Approve an access request.
     */
    public function approveRequest(int $id, int $reviewerId, ?string $notes = null): bool
    {
        return DB::table('access_request')
            ->where('id', $id)
            ->update([
                'status'       => 'approved',
                'reviewed_by'  => $reviewerId,
                'reviewed_at'  => now(),
                'review_notes' => $notes,
                'updated_at'   => now(),
            ]) > 0;
    }

    public function cancelRequest(int $id, int $userId): bool
    {
        return DB::table('access_request')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->update([
                'status'     => 'cancelled',
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Deny an access request.
     */
    public function denyRequest(int $id, int $reviewerId, ?string $reason = null): bool
    {
        return DB::table('access_request')
            ->where('id', $id)
            ->update([
                'status'       => 'denied',
                'reviewed_by'  => $reviewerId,
                'reviewed_at'  => now(),
                'review_notes' => $reason,
                'updated_at'   => now(),
            ]) > 0;
    }

    /**
     * Add an approver.
     */
    public function addApprover(int $userId): int
    {
        return DB::table('access_request_approver')->insertGetId([
            'user_id'    => $userId,
            'active'     => 1,
            'created_at' => now(),
        ]);
    }

    /**
     * Remove an approver (deactivate).
     */
    public function removeApprover(int $id): bool
    {
        return DB::table('access_request_approver')
            ->where('id', $id)
            ->update(['active' => 0]) > 0;
    }
}
