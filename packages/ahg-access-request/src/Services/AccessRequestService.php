<?php

namespace AhgAccessRequest\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AccessRequestService
{
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
}
