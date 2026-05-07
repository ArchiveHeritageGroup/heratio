<?php

/**
 * AccessRequestService - Service for Heratio
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



namespace AhgAccessRequest\Services;

use AhgAccessRequest\Mail\AccessRequestApprovedMail;
use AhgAccessRequest\Mail\AccessRequestDeniedMail;
use AhgAccessRequest\Mail\AccessRequestPendingMail;
use AhgAccessRequest\Mail\AccessRequestSubmittedMail;
use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccessRequestService
{
    /**
     * #95: master gate for the four access-request mailers. Mirrors the
     * pattern in ResearchService::sendBookingMail / WorkflowService - when
     * the operator turns the toggle off on /admin/ahgSettings/email, the
     * mailers no-op silently. Default is on (true) so a fresh install gets
     * notifications without the operator having to toggle anything.
     */
    private function notificationsEnabled(): bool
    {
        return AhgSettingsService::getBool('access_request_email_notifications', true);
    }

    /**
     * Wraps Mail::to(...)->send(...) in a try/catch that logs but never
     * throws - mail-delivery failure must not roll back the surrounding
     * approval / denial / submission action that triggered it.
     */
    private function trySend(string $email, $mailable, string $context): void
    {
        if (!$this->notificationsEnabled() || empty($email)) {
            return;
        }
        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('[access-request] mail send failed', [
                'context' => $context,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve a user_id to their email address, or null if missing.
     */
    private function emailFor(int $userId): ?string
    {
        return DB::table('user')->where('id', $userId)->value('email') ?: null;
    }

    /**
     * Resolve a user_id to their display name (actor_i18n.authorized_form_of_name)
     * with email fallback when the actor row is missing. Used as the
     * "requester" label in the pending-approver email.
     */
    private function displayNameFor(int $userId): string
    {
        $row = DB::table('user as u')
            ->leftJoin('actor_i18n as a', function ($j) {
                $j->on('u.id', '=', 'a.id')->where('a.culture', '=', 'en');
            })
            ->where('u.id', $userId)
            ->select('u.email', 'a.authorized_form_of_name as name')
            ->first();
        return $row->name ?? $row->email ?? ('User #' . $userId);
    }


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

        $newId = DB::table('security_access_request')->insertGetId([
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

        // #95: notify requester (acknowledgement) + every active approver
        // (review queue heads-up). Both gated on access_request_email_notifications
        // via trySend(); failure is logged but never raised so the SQL
        // insert above can't be rolled back by a mail glitch.
        if ($this->notificationsEnabled()) {
            $created = (object) [
                'id' => $newId,
                'justification' => $justification,
                'priority' => $priority,
                'created_at' => now(),
            ];

            $requesterEmail = $this->emailFor($userId);
            if ($requesterEmail) {
                $this->trySend($requesterEmail, new AccessRequestSubmittedMail($created), 'submitted');
            }

            $requesterName = $this->displayNameFor($userId);
            foreach ($this->getApprovers() as $approver) {
                if (!empty($approver->email)) {
                    $this->trySend(
                        (string) $approver->email,
                        new AccessRequestPendingMail($created, $requesterName),
                        'pending'
                    );
                }
            }
        }

        return $newId;
    }

    /**
     * Approve an access request.
     */
    public function approveRequest(int $id, int $reviewerId, ?string $notes = null): bool
    {
        $reviewedAt = now();
        $affected = DB::table('access_request')
            ->where('id', $id)
            ->update([
                'status'       => 'approved',
                'reviewed_by'  => $reviewerId,
                'reviewed_at'  => $reviewedAt,
                'review_notes' => $notes,
                'updated_at'   => $reviewedAt,
            ]) > 0;

        // #95: notify the requester their request was approved. Look up the
        // user's email from the access_request row's user_id (rather than
        // re-reading the whole row) since the update above already happened.
        if ($affected && $this->notificationsEnabled()) {
            $userId = (int) DB::table('access_request')->where('id', $id)->value('user_id');
            $email = $userId ? $this->emailFor($userId) : null;
            if ($email) {
                $payload = (object) ['id' => $id, 'reviewed_at' => $reviewedAt];
                $this->trySend($email, new AccessRequestApprovedMail($payload, $notes), 'approved');
            }
        }

        return $affected;
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
        $reviewedAt = now();
        $affected = DB::table('access_request')
            ->where('id', $id)
            ->update([
                'status'       => 'denied',
                'reviewed_by'  => $reviewerId,
                'reviewed_at'  => $reviewedAt,
                'review_notes' => $reason,
                'updated_at'   => $reviewedAt,
            ]) > 0;

        // #95: notify the requester their request was denied + the reason.
        if ($affected && $this->notificationsEnabled()) {
            $userId = (int) DB::table('access_request')->where('id', $id)->value('user_id');
            $email = $userId ? $this->emailFor($userId) : null;
            if ($email) {
                $payload = (object) ['id' => $id, 'reviewed_at' => $reviewedAt];
                $this->trySend($email, new AccessRequestDeniedMail($payload, $reason), 'denied');
            }
        }

        return $affected;
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
