<?php

/**
 * PrivacyService — Service layer for ahg-privacy
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 *
 * This file is part of Heratio. AGPL-3.0-or-later.
 *
 * International framing: Heratio's privacy module is jurisdiction-neutral.
 * Jurisdiction-specific rules (POPIA, GDPR, LGPD, PDPA, CCPA, etc) are applied
 * via the pluggable `privacy_config` table — never hardcoded.
 *
 * Cloned from PSIS ahgPrivacyPlugin/lib/Service/PrivacyService.php methods:
 *   updateDsar, updateBreach, withdrawConsent,
 *   submitRopaForApproval, approveRopa, rejectRopa, isPrivacyOfficer,
 *   plus helpers: getRopa, createNotification, logApprovalAction, logDsarActivity.
 */

namespace AhgPrivacy\Services;

use Illuminate\Support\Facades\DB;

class PrivacyService
{
    // =====================================================================
    //  DSAR (Data Subject Access Requests)
    // =====================================================================

    public function updateDsar(int $id, array $data, ?int $userId = null): bool
    {
        $updates = array_filter([
            'status'         => $data['status']         ?? null,
            'priority'       => $data['priority']       ?? null,
            'assigned_to'    => $data['assigned_to']    ?? null,
            'outcome'        => $data['outcome']        ?? null,
            'refusal_reason' => $data['refusal_reason'] ?? null,
            'is_verified'    => $data['is_verified']    ?? null,
            'fee_required'   => $data['fee_required']   ?? null,
            'fee_paid'       => $data['fee_paid']       ?? null,
        ], fn ($v) => $v !== null);

        $updates['updated_at'] = now();

        if (isset($data['status']) && $data['status'] === 'completed') {
            $updates['completed_date'] = now()->toDateString();
        }

        if (!empty($data['is_verified'])) {
            $updates['verified_at'] = now();
            $updates['verified_by'] = $userId;
        }

        $result = DB::table('privacy_dsar')->where('id', $id)->update($updates);

        if (!empty($data['notes']) || !empty($data['response_summary'])) {
            DB::table('privacy_dsar_i18n')->updateOrInsert(
                ['id' => $id, 'culture' => app()->getLocale()],
                array_filter([
                    'notes'            => $data['notes']            ?? null,
                    'response_summary' => $data['response_summary'] ?? null,
                ], fn ($v) => $v !== null)
            );
        }

        if (isset($data['status'])) {
            $this->logDsarActivity($id, 'status_changed', "Status changed to {$data['status']}", $userId);
        }

        return $result >= 0;
    }

    public function logDsarActivity(int $dsarId, string $action, string $details, ?int $userId = null): void
    {
        DB::table('privacy_dsar_log')->insert([
            'dsar_id'    => $dsarId,
            'action'     => $action,
            'details'    => $details,
            'user_id'    => $userId,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    // =====================================================================
    //  Breach Management
    // =====================================================================

    public function updateBreach(int $id, array $data, ?int $userId = null): bool
    {
        $updates = ['updated_at' => now()];

        foreach (['breach_type', 'severity', 'status', 'data_categories_affected'] as $k) {
            if (isset($data[$k])) {
                $updates[$k] = $data[$k];
            }
        }
        foreach (['risk_to_rights', 'data_subjects_affected', 'assigned_to'] as $k) {
            if (isset($data[$k])) {
                $updates[$k] = $data[$k] ?: null;
            }
        }

        // Notification flags are checkboxes — coerce to 0/1.
        $updates['notification_required'] = isset($data['notification_required']) ? 1 : 0;
        $updates['regulator_notified']    = isset($data['regulator_notified'])    ? 1 : 0;
        $updates['subjects_notified']     = isset($data['subjects_notified'])     ? 1 : 0;

        foreach ([
            'occurred_date',
            'contained_date',
            'resolved_date',
            'regulator_notified_date',
            'subjects_notified_date',
        ] as $k) {
            if (isset($data[$k])) {
                $updates[$k] = $data[$k] ?: null;
            }
        }

        $result = DB::table('privacy_breach')->where('id', $id)->update($updates);

        $i18nUpdates = [];
        foreach (['title', 'description', 'cause', 'impact_assessment', 'remedial_actions', 'lessons_learned'] as $k) {
            if (isset($data[$k])) {
                $i18nUpdates[$k] = $data[$k];
            }
        }
        if (!empty($i18nUpdates)) {
            DB::table('privacy_breach_i18n')->updateOrInsert(
                ['id' => $id, 'culture' => app()->getLocale()],
                $i18nUpdates
            );
        }

        return $result >= 0;
    }

    // =====================================================================
    //  Consent
    // =====================================================================

    public function withdrawConsent(int $id, ?string $reason = null, ?int $userId = null): bool
    {
        return DB::table('privacy_consent_record')
            ->where('id', $id)
            ->update([
                'status'            => 'withdrawn',
                'withdrawn_date'    => now()->toDateString(),
                'withdrawal_reason' => $reason,
                'updated_at'        => now(),
            ]) > 0;
    }

    // =====================================================================
    //  ROPA (Record of Processing Activity) — approval workflow
    // =====================================================================

    public function getRopa(int $id): ?object
    {
        return DB::table('privacy_processing_activity')->where('id', $id)->first();
    }

    public function submitRopaForApproval(int $id, int $userId, ?int $assignedOfficerId = null): bool
    {
        $activity = $this->getRopa($id);
        if (!$activity || $activity->status !== 'draft') {
            return false;
        }

        if (!$assignedOfficerId) {
            $officer = DB::table('privacy_officer')
                ->where('is_active', 1)
                ->where('is_primary', 1)
                ->first();
            $assignedOfficerId = $officer->user_id ?? null;
        }

        DB::table('privacy_processing_activity')
            ->where('id', $id)
            ->update([
                'status'              => 'pending_review',
                'submitted_at'        => now(),
                'submitted_by'        => $userId,
                'assigned_officer_id' => $assignedOfficerId,
                'updated_at'          => now(),
            ]);

        $this->logApprovalAction($id, 'ropa', 'submitted', 'draft', 'pending_review', null, $userId);

        if ($assignedOfficerId) {
            $this->createNotification(
                $assignedOfficerId,
                'ropa',
                $id,
                'submitted',
                'ROPA Submitted for Review: ' . ($activity->name ?? '#' . $id),
                'A processing activity has been submitted for your review.',
                '/admin/privacy/ropa-view?id=' . $id,
                $userId
            );
        }

        return true;
    }

    public function approveRopa(int $id, int $userId, ?string $comment = null): bool
    {
        $activity = $this->getRopa($id);
        if (!$activity || $activity->status !== 'pending_review') {
            return false;
        }

        DB::table('privacy_processing_activity')
            ->where('id', $id)
            ->update([
                'status'      => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
                'updated_at'  => now(),
            ]);

        $this->logApprovalAction($id, 'ropa', 'approved', 'pending_review', 'approved', $comment, $userId);

        if (!empty($activity->created_by)) {
            $this->createNotification(
                (int) $activity->created_by,
                'ropa',
                $id,
                'approved',
                'ROPA Approved: ' . ($activity->name ?? '#' . $id),
                $comment ?: 'Your processing activity has been approved.',
                '/admin/privacy/ropa-view?id=' . $id,
                $userId
            );
        }

        return true;
    }

    public function rejectRopa(int $id, int $userId, string $reason): bool
    {
        $activity = $this->getRopa($id);
        if (!$activity || $activity->status !== 'pending_review') {
            return false;
        }

        DB::table('privacy_processing_activity')
            ->where('id', $id)
            ->update([
                'status'           => 'draft',
                'rejected_at'      => now(),
                'rejected_by'      => $userId,
                'rejection_reason' => $reason,
                'updated_at'       => now(),
            ]);

        $this->logApprovalAction($id, 'ropa', 'rejected', 'pending_review', 'draft', $reason, $userId);

        if (!empty($activity->created_by)) {
            $this->createNotification(
                (int) $activity->created_by,
                'ropa',
                $id,
                'rejected',
                'ROPA Requires Changes: ' . ($activity->name ?? '#' . $id),
                'Reason: ' . $reason,
                '/admin/privacy/ropa-edit?id=' . $id,
                $userId
            );
        }

        return true;
    }

    // =====================================================================
    //  Access control
    // =====================================================================

    public function isPrivacyOfficer(int $userId): bool
    {
        return DB::table('privacy_officer')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->exists();
    }

    // =====================================================================
    //  Helpers (approval log + notifications)
    // =====================================================================

    protected function logApprovalAction(
        int $entityId,
        string $entityType,
        string $action,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $comment,
        int $userId
    ): int {
        return (int) DB::table('privacy_approval_log')->insertGetId([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'action'      => $action,
            'old_status'  => $oldStatus,
            'new_status'  => $newStatus,
            'comment'     => $comment,
            'user_id'     => $userId,
            'created_at'  => now(),
        ]);
    }

    public function createNotification(
        int $userId,
        string $entityType,
        int $entityId,
        string $type,
        string $subject,
        ?string $message = null,
        ?string $link = null,
        ?int $createdBy = null
    ): int {
        return (int) DB::table('privacy_notification')->insertGetId([
            'user_id'           => $userId,
            'entity_type'       => $entityType,
            'entity_id'         => $entityId,
            'notification_type' => $type,
            'subject'           => $subject,
            'message'           => $message,
            'link'              => $link,
            'created_by'        => $createdBy,
            'created_at'        => now(),
        ]);
    }
}
