<?php

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DisposalWorkflowService
{
    /**
     * Initiate a disposal action for an information object.
     *
     * @throws \RuntimeException if the IO is under legal hold
     */
    public function initiateDisposal(int $ioId, int $disposalClassId, string $actionType, int $userId, ?string $reason = null): int
    {
        // Check legal hold
        if (Schema::hasTable('integrity_legal_hold')) {
            $activeHold = DB::table('integrity_legal_hold')
                ->where('information_object_id', $ioId)
                ->where('status', 'active')
                ->exists();

            if ($activeHold) {
                throw new \RuntimeException('Cannot initiate disposal: information object is under active legal hold.');
            }
        }

        $actionId = DB::table('rm_disposal_action')->insertGetId([
            'information_object_id' => $ioId,
            'disposal_class_id' => $disposalClassId ?: null,
            'action_type' => $actionType,
            'status' => 'pending',
            'reason' => $reason,
            'initiated_by' => $userId,
            'initiated_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Try to start a workflow if one is configured for disposal
        if (Schema::hasTable('ahg_workflow')) {
            $workflow = DB::table('ahg_workflow')
                ->where('is_active', 1)
                ->where(function ($q) {
                    $q->where('name', 'LIKE', '%disposal%')
                      ->orWhere('trigger_event', 'disposal');
                })
                ->first();

            if ($workflow) {
                try {
                    $workflowService = app(\AhgWorkflow\Services\WorkflowService::class);
                    $taskId = $workflowService->startWorkflow($workflow->id, $ioId, 'disposal_action', $userId);

                    if ($taskId) {
                        DB::table('rm_disposal_action')
                            ->where('id', $actionId)
                            ->update(['workflow_task_id' => $taskId, 'updated_at' => Carbon::now()]);
                    }
                } catch (\Throwable $e) {
                    // Workflow integration is optional; log but don't block
                    \Log::warning('Disposal workflow start failed: ' . $e->getMessage());
                }
            }
        }

        // Log to audit
        $this->auditLog($userId, 'create', 'rm_disposal_action', $actionId, 'Disposal initiated', [
            'action_type' => $actionType,
            'information_object_id' => $ioId,
        ]);

        return $actionId;
    }

    /**
     * Get paginated disposal queue with filters.
     */
    public function getDisposalQueue(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (!Schema::hasTable('rm_disposal_action')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $culture = app()->getLocale();

        $query = DB::table('rm_disposal_action as da')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('da.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('user as init_user', 'da.initiated_by', '=', 'init_user.id')
            ->leftJoin('actor_i18n as init_actor', function ($join) {
                $join->on('init_user.id', '=', 'init_actor.id')
                    ->where('init_actor.culture', '=', 'en');
            });

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('da.status', $filters['status']);
        }

        if (!empty($filters['action_type'])) {
            $query->where('da.action_type', $filters['action_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('da.initiated_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('da.initiated_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $total = $query->count();

        $data = $query->select([
                'da.*',
                'io_i18n.title as io_title',
                'init_actor.authorized_form_of_name as initiated_by_name',
            ])
            ->orderBy('da.initiated_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Recommend a disposal action.
     */
    public function recommend(int $disposalActionId, int $userId, ?string $comment = null): bool
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action || $action->status !== 'pending') {
            return false;
        }

        DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
            'recommended_by' => $userId,
            'recommended_at' => Carbon::now(),
            'status' => 'recommended',
            'notes' => $comment ? ($action->notes ? $action->notes . "\n" . $comment : $comment) : $action->notes,
            'updated_at' => Carbon::now(),
        ]);

        $this->auditLog($userId, 'update', 'rm_disposal_action', $disposalActionId, 'Disposal recommended', [
            'comment' => $comment,
        ]);

        return true;
    }

    /**
     * Approve a disposal action.
     */
    public function approve(int $disposalActionId, int $userId, ?string $comment = null): bool
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action || !in_array($action->status, ['pending', 'recommended'])) {
            return false;
        }

        DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
            'approved_by' => $userId,
            'approved_at' => Carbon::now(),
            'status' => 'approved',
            'notes' => $comment ? ($action->notes ? $action->notes . "\n" . $comment : $comment) : $action->notes,
            'updated_at' => Carbon::now(),
        ]);

        // If workflow task is linked, approve it
        if ($action->workflow_task_id && Schema::hasTable('ahg_workflow_task')) {
            try {
                $workflowService = app(\AhgWorkflow\Services\WorkflowService::class);
                $workflowService->approveTask($action->workflow_task_id, $userId, $comment);
            } catch (\Throwable $e) {
                \Log::warning('Disposal workflow approve failed: ' . $e->getMessage());
            }
        }

        $this->auditLog($userId, 'update', 'rm_disposal_action', $disposalActionId, 'Disposal approved', [
            'comment' => $comment,
        ]);

        return true;
    }

    /**
     * Clear legal hold check for a disposal action.
     */
    public function clearLegal(int $disposalActionId, int $userId): bool
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action || !in_array($action->status, ['approved'])) {
            return false;
        }

        // Check if IO still has active legal hold
        if (Schema::hasTable('integrity_legal_hold')) {
            $activeHold = DB::table('integrity_legal_hold')
                ->where('information_object_id', $action->information_object_id)
                ->where('status', 'active')
                ->exists();

            if ($activeHold) {
                return false;
            }
        }

        DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
            'legal_cleared' => 1,
            'legal_cleared_by' => $userId,
            'legal_cleared_at' => Carbon::now(),
            'status' => 'cleared',
            'updated_at' => Carbon::now(),
        ]);

        $this->auditLog($userId, 'update', 'rm_disposal_action', $disposalActionId, 'Disposal legal cleared');

        return true;
    }

    /**
     * Reject a disposal action.
     */
    public function reject(int $disposalActionId, int $userId, string $reason): bool
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action || in_array($action->status, ['executed', 'cancelled'])) {
            return false;
        }

        DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
            'status' => 'rejected',
            'notes' => $action->notes ? $action->notes . "\nRejected: " . $reason : "Rejected: " . $reason,
            'updated_at' => Carbon::now(),
        ]);

        // If workflow task is linked, reject it
        if ($action->workflow_task_id && Schema::hasTable('ahg_workflow_task')) {
            try {
                $workflowService = app(\AhgWorkflow\Services\WorkflowService::class);
                $workflowService->rejectTask($action->workflow_task_id, $userId, $reason);
            } catch (\Throwable $e) {
                \Log::warning('Disposal workflow reject failed: ' . $e->getMessage());
            }
        }

        $this->auditLog($userId, 'update', 'rm_disposal_action', $disposalActionId, 'Disposal rejected', [
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * Cancel a disposal action (only if pending or recommended).
     */
    public function cancel(int $disposalActionId, int $userId, string $reason): bool
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action || !in_array($action->status, ['pending', 'recommended'])) {
            return false;
        }

        DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
            'status' => 'cancelled',
            'notes' => $action->notes ? $action->notes . "\nCancelled: " . $reason : "Cancelled: " . $reason,
            'updated_at' => Carbon::now(),
        ]);

        $this->auditLog($userId, 'update', 'rm_disposal_action', $disposalActionId, 'Disposal cancelled', [
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * Get a single disposal action with full detail.
     */
    public function getAction(int $id): ?object
    {
        $culture = app()->getLocale();

        $action = DB::table('rm_disposal_action as da')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('da.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('user as init_user', 'da.initiated_by', '=', 'init_user.id')
            ->leftJoin('actor_i18n as init_actor', function ($join) {
                $join->on('init_user.id', '=', 'init_actor.id')
                    ->where('init_actor.culture', '=', 'en');
            })
            ->leftJoin('user as rec_user', 'da.recommended_by', '=', 'rec_user.id')
            ->leftJoin('actor_i18n as rec_actor', function ($join) {
                $join->on('rec_user.id', '=', 'rec_actor.id')
                    ->where('rec_actor.culture', '=', 'en');
            })
            ->leftJoin('user as app_user', 'da.approved_by', '=', 'app_user.id')
            ->leftJoin('actor_i18n as app_actor', function ($join) {
                $join->on('app_user.id', '=', 'app_actor.id')
                    ->where('app_actor.culture', '=', 'en');
            })
            ->leftJoin('user as legal_user', 'da.legal_cleared_by', '=', 'legal_user.id')
            ->leftJoin('actor_i18n as legal_actor', function ($join) {
                $join->on('legal_user.id', '=', 'legal_actor.id')
                    ->where('legal_actor.culture', '=', 'en');
            })
            ->leftJoin('user as exec_user', 'da.executed_by', '=', 'exec_user.id')
            ->leftJoin('actor_i18n as exec_actor', function ($join) {
                $join->on('exec_user.id', '=', 'exec_actor.id')
                    ->where('exec_actor.culture', '=', 'en');
            })
            ->where('da.id', $id)
            ->select([
                'da.*',
                'io_i18n.title as io_title',
                'init_actor.authorized_form_of_name as initiated_by_name',
                'rec_actor.authorized_form_of_name as recommended_by_name',
                'app_actor.authorized_form_of_name as approved_by_name',
                'legal_actor.authorized_form_of_name as legal_cleared_by_name',
                'exec_actor.authorized_form_of_name as executed_by_name',
            ])
            ->first();

        if ($action) {
            // Check if IO is under legal hold
            $action->has_active_hold = false;
            if (Schema::hasTable('integrity_legal_hold')) {
                $action->has_active_hold = DB::table('integrity_legal_hold')
                    ->where('information_object_id', $action->information_object_id)
                    ->where('status', 'active')
                    ->exists();
            }

            // Get certificate if linked
            $action->certificate = null;
            if ($action->certificate_id && Schema::hasTable('destruction_certificate')) {
                $action->certificate = DB::table('destruction_certificate')
                    ->where('id', $action->certificate_id)
                    ->first();
            }
        }

        return $action;
    }

    /**
     * Get the action timeline (audit log + workflow history).
     */
    public function getActionTimeline(int $disposalActionId): array
    {
        $timeline = [];

        // Audit log entries
        if (Schema::hasTable('ahg_audit_log')) {
            $auditEntries = DB::table('ahg_audit_log')
                ->where('entity_type', 'rm_disposal_action')
                ->where('entity_id', $disposalActionId)
                ->orderBy('created_at', 'asc')
                ->get()
                ->toArray();

            foreach ($auditEntries as $entry) {
                $timeline[] = (object) [
                    'type' => 'audit',
                    'action' => $entry->action,
                    'description' => $entry->entity_title,
                    'user_id' => $entry->user_id,
                    'username' => $entry->username,
                    'timestamp' => $entry->created_at,
                ];
            }
        }

        // Workflow history if linked
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if ($action && $action->workflow_task_id && Schema::hasTable('ahg_workflow_history')) {
            $workflowEntries = DB::table('ahg_workflow_history')
                ->leftJoin('user', 'ahg_workflow_history.performed_by', '=', 'user.id')
                ->leftJoin('actor_i18n', function ($join) {
                    $join->on('user.id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', 'en');
                })
                ->where('ahg_workflow_history.task_id', $action->workflow_task_id)
                ->orderBy('ahg_workflow_history.performed_at', 'asc')
                ->select([
                    'ahg_workflow_history.*',
                    'user.username',
                    'actor_i18n.authorized_form_of_name as performer_name',
                ])
                ->get()
                ->toArray();

            foreach ($workflowEntries as $entry) {
                $timeline[] = (object) [
                    'type' => 'workflow',
                    'action' => $entry->action ?? 'workflow_step',
                    'description' => $entry->comment ?? '',
                    'user_id' => $entry->performed_by ?? null,
                    'username' => $entry->username ?? '',
                    'performer_name' => $entry->performer_name ?? '',
                    'timestamp' => $entry->performed_at,
                ];
            }
        }

        // Sort by timestamp
        usort($timeline, function ($a, $b) {
            return strcmp($a->timestamp ?? '', $b->timestamp ?? '');
        });

        return $timeline;
    }

    /**
     * Get disposal statistics.
     */
    public function getStats(): array
    {
        if (!Schema::hasTable('rm_disposal_action')) {
            return [
                'by_status' => [],
                'by_action_type' => [],
            ];
        }

        $byStatus = DB::table('rm_disposal_action')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byActionType = DB::table('rm_disposal_action')
            ->select('action_type', DB::raw('COUNT(*) as count'))
            ->groupBy('action_type')
            ->pluck('count', 'action_type')
            ->toArray();

        return [
            'by_status' => $byStatus,
            'by_action_type' => $byActionType,
        ];
    }

    /**
     * Write to ahg_audit_log if the table exists.
     */
    private function auditLog(int $userId, string $action, string $entityType, int $entityId, string $title, array $metadata = []): void
    {
        if (!Schema::hasTable('ahg_audit_log')) {
            return;
        }

        $user = DB::table('user')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.id', $userId)
            ->select('user.username', 'user.email', 'actor_i18n.authorized_form_of_name')
            ->first();

        DB::table('ahg_audit_log')->insert([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $userId,
            'username' => $user->username ?? null,
            'user_email' => $user->email ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_title' => $title,
            'module' => 'records-manage',
            'action_name' => $action,
            'request_method' => request()->method(),
            'request_uri' => request()->getRequestUri(),
            'metadata' => !empty($metadata) ? json_encode($metadata) : null,
            'status' => 'success',
            'created_at' => Carbon::now(),
        ]);
    }
}
