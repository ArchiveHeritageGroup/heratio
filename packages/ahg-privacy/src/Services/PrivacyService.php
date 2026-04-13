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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PrivacyService
{
    // =====================================================================
    //  DSAR (Data Subject Access Requests)
    // =====================================================================

    /**
     * Return the filtered DSAR list for the admin browse view.
     *
     * Cloned from PSIS ahgPrivacyPlugin\Service\PrivacyService::getDsarList().
     * Joins `user` so the template can display `assigned_username`.
     *
     * Supported filters: status, jurisdiction, request_type, overdue,
     *                    assigned_to, limit.
     */
    public function getDsarList(array $filters = [])
    {
        $query = DB::table('privacy_dsar as d')
            ->leftJoin('user as u', 'u.id', '=', 'd.assigned_to')
            ->select([
                'd.*',
                'u.username as assigned_username',
            ]);

        if (!empty($filters['status'])) {
            $query->where('d.status', $filters['status']);
        }
        if (!empty($filters['jurisdiction'])) {
            $query->where('d.jurisdiction', $filters['jurisdiction']);
        }
        if (!empty($filters['request_type'])) {
            $query->where('d.request_type', $filters['request_type']);
        }
        if (!empty($filters['overdue'])) {
            $query->where('d.due_date', '<', date('Y-m-d'))
                  ->whereNotIn('d.status', ['completed', 'rejected', 'withdrawn']);
        }
        if (!empty($filters['assigned_to'])) {
            $query->where('d.assigned_to', $filters['assigned_to']);
        }

        $limit = (int) ($filters['limit'] ?? 500);
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->orderByDesc('d.received_date')->orderByDesc('d.created_at')->get();
    }

    /**
     * Jurisdiction-aware DSAR request type labels.
     *
     * Cloned verbatim from PSIS ahgPrivacyPlugin\Service\PrivacyJurisdictionService::getRequestTypes().
     */
    public static function getRequestTypes(string $jurisdiction = 'popia'): array
    {
        $common = [
            'access'           => 'Right of Access',
            'rectification'    => 'Right to Rectification',
            'erasure'          => 'Right to Erasure/Deletion',
            'restriction'      => 'Right to Restriction',
            'objection'        => 'Right to Object',
            'withdraw_consent' => 'Withdraw Consent',
        ];

        $jurisdiction_specific = [
            'popia' => [
                'access'        => 'Right of Access (POPIA S23 / PAIA S50)',
                'rectification' => 'Right to Rectification (POPIA S24)',
                'erasure'       => 'Right to Erasure (POPIA S24)',
                'objection'     => 'Right to Object (POPIA S11(3))',
                'paia_access'   => 'PAIA Access Request (PAIA S50)',
            ],
            'ndpa' => [
                'access'        => 'Right of Access (NDPA S34)',
                'rectification' => 'Right to Rectification (NDPA S35)',
                'erasure'       => 'Right to Erasure (NDPA S36)',
                'restriction'   => 'Right to Restriction (NDPA S37)',
                'portability'   => 'Right to Data Portability (NDPA S38)',
                'objection'     => 'Right to Object (NDPA S39)',
                'automated'     => 'Automated Decision Rights (NDPA S40)',
            ],
            'kenya_dpa' => [
                'access'        => 'Right of Access (Kenya DPA S26)',
                'rectification' => 'Right to Rectification (Kenya DPA S27)',
                'erasure'       => 'Right to Erasure (Kenya DPA S28)',
                'portability'   => 'Right to Data Portability (Kenya DPA S29)',
            ],
            'gdpr' => [
                'access'        => 'Right of Access (GDPR Art.15)',
                'rectification' => 'Right to Rectification (GDPR Art.16)',
                'erasure'       => 'Right to Erasure (GDPR Art.17)',
                'restriction'   => 'Right to Restriction (GDPR Art.18)',
                'portability'   => 'Right to Data Portability (GDPR Art.20)',
                'objection'     => 'Right to Object (GDPR Art.21)',
                'automated'     => 'Automated Decision Rights (GDPR Art.22)',
            ],
            'pipeda' => [
                'access'           => 'Right of Access (PIPEDA Principle 4.9)',
                'rectification'    => 'Right to Rectification (PIPEDA Principle 4.9.5)',
                'withdraw_consent' => 'Withdraw Consent (PIPEDA Principle 4.3.8)',
            ],
            'ccpa' => [
                'access'            => 'Right to Know (CCPA §1798.100)',
                'erasure'           => 'Right to Delete (CCPA §1798.105)',
                'opt_out'           => 'Right to Opt-Out of Sale (CCPA §1798.120)',
                'non_discrimination' => 'Right to Non-Discrimination (CCPA §1798.125)',
                'correct'           => 'Right to Correct (CPRA §1798.106)',
                'limit_use'         => 'Right to Limit Use (CPRA §1798.121)',
            ],
        ];

        return array_merge($common, $jurisdiction_specific[$jurisdiction] ?? []);
    }

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
            $this->sendApprovalEmail($assignedOfficerId, 'submitted', $activity);
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
            $this->sendApprovalEmail((int) $activity->created_by, 'approved', $activity, $comment);
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
            $this->sendApprovalEmail((int) $activity->created_by, 'rejected', $activity, $reason);
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

    // =====================================================================
    //  Email sending (Phase X.9 — cloned from PSIS `sendApprovalEmail`)
    // =====================================================================

    /**
     * Send ROPA approval workflow email. Mirrors PSIS PrivacyService::sendApprovalEmail.
     * Failures are swallowed (logged) so they never break the persistence path.
     * On success, flips `email_sent` + `email_sent_at` on the latest matching
     * `privacy_notification` row.
     */
    protected function sendApprovalEmail(int $userId, string $action, $activity, ?string $comment = null): void
    {
        $user = DB::table('user')->where('id', $userId)->first(['id', 'username', 'email']);
        if (!$user || empty($user->email)) {
            return;
        }

        $name = $activity->name ?? ('#' . ($activity->id ?? ''));
        $subjects = [
            'submitted' => 'ROPA Submitted for Review: ' . $name,
            'approved'  => 'ROPA Approved: ' . $name,
            'rejected'  => 'ROPA Requires Changes: ' . $name,
        ];
        $subject = $subjects[$action] ?? ('ROPA Update: ' . $name);

        try {
            $baseUrl = rtrim((string) config('app.url', ''), '/');
            $link = $baseUrl . '/admin/privacy/ropa-view?id=' . (int) ($activity->id ?? 0);
            $body = $this->buildApprovalEmailBody($action, $activity, $comment, $user, $link);

            Mail::html($body, function ($m) use ($user, $subject) {
                $m->to($user->email, $user->username ?? null)->subject($subject);
            });

            DB::table('privacy_notification')
                ->where('user_id', $userId)
                ->where('entity_type', 'ropa')
                ->where('entity_id', (int) ($activity->id ?? 0))
                ->where('notification_type', $action)
                ->orderByDesc('created_at')
                ->limit(1)
                ->update([
                    'email_sent'    => 1,
                    'email_sent_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Privacy ROPA email failed: ' . $e->getMessage(), [
                'user_id'     => $userId,
                'action'      => $action,
                'activity_id' => $activity->id ?? null,
            ]);
        }
    }

    /**
     * Build the HTML body for a ROPA approval-workflow email. Cloned from PSIS
     * `buildApprovalEmailBody` with jurisdiction-neutral framing.
     */
    protected function buildApprovalEmailBody(string $action, $activity, ?string $comment, $user, string $link): string
    {
        $title = match ($action) {
            'submitted' => 'A processing activity has been submitted for your review:',
            'approved'  => 'Your processing activity has been <strong style="color:#198754;">approved</strong>:',
            'rejected'  => 'Your processing activity requires <strong style="color:#dc3545;">changes</strong>:',
            default     => 'Processing activity update:',
        };

        $name    = htmlspecialchars((string) ($activity->name ?? ''), ENT_QUOTES, 'UTF-8');
        $purpose = htmlspecialchars(mb_substr((string) ($activity->purpose ?? ''), 0, 100), ENT_QUOTES, 'UTF-8');
        $who     = htmlspecialchars((string) ($user->username ?? 'User'), ENT_QUOTES, 'UTF-8');
        $href    = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        $commentBlock = '';
        if ($comment !== null && $comment !== '') {
            $commentBlock =
                '<p><strong>Comment:</strong><br>' .
                nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8')) .
                '</p>';
        }

        return '<html><body style="font-family:Arial,sans-serif;">'
            . '<h2>Processing Activity Update</h2>'
            . '<p>Dear ' . $who . ',</p>'
            . '<p>' . $title . '</p>'
            . '<div style="background:#f5f5f5;padding:15px;margin:15px 0;border-radius:5px;">'
            . '<strong>' . $name . '</strong><br>'
            . '<small>Purpose: ' . $purpose . '...</small>'
            . '</div>'
            . $commentBlock
            . '<p><a href="' . $href . '" style="display:inline-block;padding:10px 20px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:5px;">View Details</a></p>'
            . '<p style="color:#666;font-size:12px;">This is an automated message from the Privacy Management System.</p>'
            . '</body></html>';
    }
}
