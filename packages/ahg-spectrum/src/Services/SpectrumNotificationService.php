<?php

/**
 * SpectrumNotificationService
 *
 * Handles task assignment notifications and user notification management
 * for the Spectrum workflow system.
 *
 * Migrated from ahgSpectrumNotificationService (ahgSpectrumPlugin).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpectrumNotificationService
{
    /**
     * Create an assignment notification for a user
     */
    public static function createAssignmentNotification(
        int $userId,
        int $objectId,
        string $procedureType,
        int $assignedBy,
        ?string $state = null
    ): ?int {
        if (!$userId || !$objectId || !$procedureType) {
            return null;
        }

        if (!Schema::hasTable('spectrum_notification')) {
            return null;
        }

        $object = DB::table('information_object as io')
            ->select(['io.id', 'io.identifier', 'ioi18n.title', 'slug.slug'])
            ->leftJoin('information_object_i18n as ioi18n', function ($join) {
                $join->on('io.id', '=', 'ioi18n.id')
                     ->where('ioi18n.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $objectId)
            ->first();

        if (!$object) {
            return null;
        }

        $assigner = DB::table('user')->where('id', $assignedBy)->first();
        $assignerName = $assigner ? $assigner->username : 'System';

        $procedureLabel = self::getProcedureLabel($procedureType);
        $stateLabel = $state ? self::getStateLabel($procedureType, $state) : '';

        $objectTitle = $object->title ?: $object->identifier ?: 'Untitled';
        $objectLink = url('/admin/spectrum/workflow?slug=' . $object->slug . '&procedure_type=' . $procedureType);

        $subject = "Task Assigned: {$procedureLabel}";
        $message = "You have been assigned a task by {$assignerName}.\n\n"
            . "Object: {$objectTitle}\n"
            . "Procedure: {$procedureLabel}\n";

        if ($stateLabel) {
            $message .= "State: {$stateLabel}\n";
        }

        $message .= "\nView task: {$objectLink}";

        return DB::table('spectrum_notification')->insertGetId([
            'user_id'           => $userId,
            'notification_type' => 'task_assignment',
            'subject'           => $subject,
            'message'           => $message,
            'created_at'        => now(),
        ]);
    }

    /**
     * Create a transition notification for relevant users
     */
    public static function createTransitionNotification(
        object $resource,
        string $procedureType,
        string $fromState,
        string $toState,
        string $transitionKey,
        int $actingUserId,
        ?int $assignedTo,
        ?string $note
    ): void {
        if (!Schema::hasTable('spectrum_notification')) {
            return;
        }

        $actingUser = DB::table('user')->where('id', $actingUserId)->first();
        $actingName = $actingUser ? $actingUser->username : 'System';

        $procedureLabel = self::getProcedureLabel($procedureType);
        $fromLabel = self::getStateLabel($procedureType, $fromState);
        $toLabel   = self::getStateLabel($procedureType, $toState);
        $transitionLabel = ucwords(str_replace('_', ' ', $transitionKey));

        $objectTitle = $resource->title ?: ($resource->slug ?? 'Untitled');
        $objectLink  = url('/admin/spectrum/workflow?slug=' . ($resource->slug ?? '') . '&procedure_type=' . $procedureType);

        $subject = "Spectrum: {$transitionLabel} — {$procedureLabel}";
        $message = "{$actingName} performed '{$transitionLabel}' on a task.\n\n"
            . "Object: {$objectTitle}\n"
            . "Procedure: {$procedureLabel}\n"
            . "State: {$fromLabel} → {$toLabel}\n";
        if ($note) {
            $message .= "Note: {$note}\n";
        }
        $message .= "\nView task: {$objectLink}";

        // Determine who to notify
        $notifyUserIds = [];

        // Always notify the acting user (they see their own transitions)
        $notifyUserIds[] = $actingUserId;

        // Notify the assigned user
        if ($assignedTo && !in_array($assignedTo, $notifyUserIds)) {
            $notifyUserIds[] = $assignedTo;
        }

        // Notify the previous assignee (if task was reassigned)
        $previousState = DB::table('spectrum_workflow_state')
            ->where('record_id', $resource->id)
            ->where('procedure_type', $procedureType)
            ->first();
        if ($previousState && $previousState->assigned_to
            && !in_array($previousState->assigned_to, $notifyUserIds)) {
            $notifyUserIds[] = $previousState->assigned_to;
        }

        // For certain transitions, also notify admins
        if (in_array($transitionKey, ['submit_for_review', 'complete', 'report'])) {
            $admins = DB::table('acl_user_group')
                ->where('group_id', 100)
                ->whereNotIn('user_id', $notifyUserIds)
                ->pluck('user_id')
                ->toArray();
            $notifyUserIds = array_merge($notifyUserIds, $admins);
        }

        $notifyUserIds = array_unique($notifyUserIds);

        foreach ($notifyUserIds as $notifyUserId) {
            DB::table('spectrum_notification')->insert([
                'user_id'           => $notifyUserId,
                'notification_type' => 'workflow_transition',
                'subject'           => $subject,
                'message'           => $message,
                'created_at'        => now(),
            ]);
        }
    }

    /**
     * Get unread notification count for a user
     */
    public static function getUnreadCount(int $userId): int
    {
        if (!$userId || !Schema::hasTable('spectrum_notification')) {
            return 0;
        }

        return DB::table('spectrum_notification')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get unread task assignment count for a user
     */
    public static function getUnreadTaskCount(int $userId): int
    {
        if (!$userId || !Schema::hasTable('spectrum_notification')) {
            return 0;
        }

        return DB::table('spectrum_notification')
            ->where('user_id', $userId)
            ->where('notification_type', 'task_assignment')
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Mark a notification as read
     */
    public static function markAsRead(int $notificationId, ?int $userId = null): bool
    {
        if (!$notificationId) {
            return false;
        }

        $query = DB::table('spectrum_notification')
            ->where('id', $notificationId);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->update(['read_at' => now()]) > 0;
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead(int $userId, ?string $type = null): int
    {
        if (!$userId) {
            return 0;
        }

        $query = DB::table('spectrum_notification')
            ->where('user_id', $userId)
            ->whereNull('read_at');

        if ($type) {
            $query->where('notification_type', $type);
        }

        return $query->update(['read_at' => now()]);
    }

    /**
     * Get notifications for a user
     */
    public static function getUserNotifications(int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        if (!$userId || !Schema::hasTable('spectrum_notification')) {
            return [];
        }

        $query = DB::table('spectrum_notification')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->get()->toArray();
    }

    /**
     * Delete a notification
     */
    public static function deleteNotification(int $notificationId, ?int $userId = null): bool
    {
        if (!$notificationId) {
            return false;
        }

        $query = DB::table('spectrum_notification')
            ->where('id', $notificationId);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->delete() > 0;
    }

    /**
     * Delete old notifications (cleanup)
     */
    public static function cleanupOldNotifications(int $daysOld = 90): int
    {
        if (!Schema::hasTable('spectrum_notification')) {
            return 0;
        }

        $cutoffDate = now()->subDays($daysOld);

        return DB::table('spectrum_notification')
            ->where('created_at', '<', $cutoffDate)
            ->whereNotNull('read_at')
            ->delete();
    }

    /**
     * Mark task notifications as read when task reaches final state
     */
    public static function markTaskNotificationsAsReadByObject(int $recordId, ?string $procedureType = null): int
    {
        if (!$recordId || !Schema::hasTable('spectrum_notification')) {
            return 0;
        }

        // Get the slug for this object
        $slug = DB::table('slug')->where('object_id', $recordId)->value('slug');
        if (!$slug) {
            return 0;
        }

        $pattern = 'slug=' . $slug;

        $query = DB::table('spectrum_notification')
            ->where('notification_type', 'task_assignment')
            ->where('message', 'like', '%' . $pattern . '%')
            ->whereNull('read_at');

        if ($procedureType) {
            $procedureLabel = self::getProcedureLabel($procedureType);
            $query->where('subject', 'like', '%' . $procedureLabel . '%');
        }

        return $query->update(['read_at' => now()]);
    }

    /**
     * Queue and optionally send email notification for a workflow event
     */
    public static function sendEmailNotification(int $userId, string $subject, string $message): bool
    {
        if (!Schema::hasTable('spectrum_workflow_notification')) {
            return false;
        }

        // Check if spectrum email notifications are enabled
        try {
            $enabled = DB::table('ahg_settings')
                ->where('setting_key', 'spectrum_email_notifications')
                ->value('setting_value');
            if ($enabled !== 'true' && $enabled !== '1') {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        $user = DB::table('user')->where('id', $userId)->first();
        if (!$user || empty($user->email)) {
            return false;
        }

        $siteTitle = config('app.name', 'Heratio');
        $htmlBody = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
            . '<h2 style="color: #2c3e50;">' . e($subject) . '</h2>'
            . '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">'
            . nl2br(e($message))
            . '</div>'
            . '<p style="color: #666; font-size: 12px;">Sent by ' . e($siteTitle) . '</p>'
            . '</div>';

        // Queue to spectrum_workflow_notification table
        DB::table('spectrum_workflow_notification')->insert([
            'procedure_type'     => 'email',
            'record_id'          => 0,
            'transition_key'     => 'notification',
            'recipient_user_id'  => $userId,
            'recipient_email'    => $user->email,
            'notification_type'  => 'email',
            'subject'            => $subject,
            'message'            => $htmlBody,
            'is_sent'            => 0,
            'created_at'         => now(),
        ]);

        return true;
    }

    /**
     * Get procedure label from procedure type
     */
    protected static function getProcedureLabel(string $procedureType): string
    {
        $labels = [
            'object_entry'                => 'Object Entry',
            'acquisition'                 => 'Acquisition',
            'location_movement'           => 'Location & Movement',
            'inventory_control'           => 'Inventory Control',
            'cataloguing'                 => 'Cataloguing',
            'condition_checking'          => 'Condition Checking',
            'conservation'                => 'Conservation',
            'risk_management'             => 'Risk Management',
            'insurance'                   => 'Insurance',
            'valuation'                   => 'Valuation',
            'audit'                       => 'Audit',
            'rights_management'           => 'Rights Management',
            'reproduction'                => 'Reproduction',
            'loans_in'                    => 'Loans In',
            'loans_out'                   => 'Loans Out',
            'loss_damage'                 => 'Loss & Damage',
            'deaccession'                 => 'Deaccession',
            'disposal'                    => 'Disposal',
            'documentation_planning'      => 'Documentation Planning',
            'object_exit'                 => 'Object Exit',
            'retrospective_documentation' => 'Retrospective Documentation',
        ];

        return $labels[$procedureType] ?? ucwords(str_replace('_', ' ', $procedureType));
    }

    /**
     * Get state label from workflow config
     */
    protected static function getStateLabel(string $procedureType, string $state): string
    {
        $config = DB::table('spectrum_workflow_config')
            ->where('procedure_type', $procedureType)
            ->where('is_active', 1)
            ->first();

        if ($config) {
            $configData = json_decode($config->config_json, true);
            if (isset($configData['state_labels'][$state])) {
                return $configData['state_labels'][$state];
            }
        }

        return ucwords(str_replace('_', ' ', $state));
    }

    /**
     * Get assigned task count for a user
     */
    public static function getAssignedTaskCount(int $userId): int
    {
        if (!$userId || !Schema::hasTable('spectrum_workflow_state')) {
            return 0;
        }

        return DB::table('spectrum_workflow_state')
            ->where('assigned_to', $userId)
            ->count();
    }

    /**
     * Get pending tasks for a user (tasks not in final states)
     */
    public static function getPendingTasks(int $userId): array
    {
        if (!$userId || !Schema::hasTable('spectrum_workflow_state')) {
            return [];
        }

        $allFinalStates = [];
        $configs = DB::table('spectrum_workflow_config')
            ->where('is_active', 1)
            ->get();
        foreach ($configs as $config) {
            $configData = json_decode($config->config_json, true);
            $finals = $configData['final_states'] ?? [];
            $allFinalStates = array_merge($allFinalStates, $finals);
        }
        $allFinalStates = array_unique($allFinalStates);

        $query = DB::table('spectrum_workflow_state as sws')
            ->select([
                'sws.*',
                'io.identifier',
                'ioi18n.title as object_title',
                'slug.slug',
            ])
            ->leftJoin('information_object as io', 'sws.record_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi18n', function ($join) {
                $join->on('io.id', '=', 'ioi18n.id')
                     ->where('ioi18n.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('sws.assigned_to', $userId);

        if (!empty($allFinalStates)) {
            $query->whereNotIn('sws.current_state', $allFinalStates);
        }

        return $query->orderBy('sws.assigned_at', 'desc')
            ->get()
            ->toArray();
    }
}
