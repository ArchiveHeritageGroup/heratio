<?php

/**
 * WorkflowService - Service for Heratio
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



namespace AhgWorkflow\Services;

use AhgCore\Services\AhgSettingsService;
use AhgWorkflow\Mail\WorkflowTaskApprovedMail;
use AhgWorkflow\Mail\WorkflowTaskRejectedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WorkflowService
{
    /**
     * Get dashboard stats for a user.
     */
    public function getStats(int $userId): array
    {
        $myTasks = DB::table('ahg_workflow_task')
            ->where('assigned_to', $userId)
            ->whereIn('status', ['pending', 'claimed', 'in_progress'])
            ->count();

        $poolTasks = DB::table('ahg_workflow_task')
            ->join('ahg_workflow_step', 'ahg_workflow_task.workflow_step_id', '=', 'ahg_workflow_step.id')
            ->whereNull('ahg_workflow_task.assigned_to')
            ->where('ahg_workflow_step.pool_enabled', 1)
            ->whereIn('ahg_workflow_task.status', ['pending'])
            ->count();

        $completedToday = DB::table('ahg_workflow_task')
            ->where('decision_by', $userId)
            ->where('status', 'completed')
            ->whereDate('decision_at', now()->toDateString())
            ->count();

        $overdueTasks = DB::table('ahg_workflow_task')
            ->where('assigned_to', $userId)
            ->whereIn('status', ['pending', 'claimed', 'in_progress'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->count();

        return [
            'my_tasks' => $myTasks,
            'pool_tasks' => $poolTasks,
            'completed_today' => $completedToday,
            'overdue_tasks' => $overdueTasks,
        ];
    }

    /**
     * Get tasks assigned to a specific user, optionally filtered by status.
     */
    public function getMyTasks(int $userId, ?string $status = null): array
    {
        $query = DB::table('ahg_workflow_task')
            ->join('ahg_workflow_step', 'ahg_workflow_task.workflow_step_id', '=', 'ahg_workflow_step.id')
            ->join('ahg_workflow', 'ahg_workflow_task.workflow_id', '=', 'ahg_workflow.id')
            ->where('ahg_workflow_task.assigned_to', $userId)
            ->select(
                'ahg_workflow_task.*',
                'ahg_workflow_step.name as step_name',
                'ahg_workflow_step.step_type',
                'ahg_workflow_step.action_required',
                'ahg_workflow_step.instructions',
                'ahg_workflow.name as workflow_name'
            )
            ->orderBy('ahg_workflow_task.priority', 'desc')
            ->orderBy('ahg_workflow_task.due_date');

        if ($status) {
            $query->where('ahg_workflow_task.status', $status);
        } else {
            $query->whereIn('ahg_workflow_task.status', ['pending', 'claimed', 'in_progress']);
        }

        return $query->get()->toArray();
    }

    /**
     * Get pool tasks (unassigned, pool-enabled).
     */
    public function getPoolTasks(int $userId): array
    {
        return DB::table('ahg_workflow_task')
            ->join('ahg_workflow_step', 'ahg_workflow_task.workflow_step_id', '=', 'ahg_workflow_step.id')
            ->join('ahg_workflow', 'ahg_workflow_task.workflow_id', '=', 'ahg_workflow.id')
            ->whereNull('ahg_workflow_task.assigned_to')
            ->where('ahg_workflow_step.pool_enabled', 1)
            ->where('ahg_workflow_task.status', 'pending')
            ->select(
                'ahg_workflow_task.*',
                'ahg_workflow_step.name as step_name',
                'ahg_workflow_step.step_type',
                'ahg_workflow_step.instructions',
                'ahg_workflow.name as workflow_name'
            )
            ->orderBy('ahg_workflow_task.priority', 'desc')
            ->orderBy('ahg_workflow_task.due_date')
            ->get()
            ->toArray();
    }

    /**
     * Get a single task with step, workflow, and history.
     */
    public function getTask(int $id): ?object
    {
        $task = DB::table('ahg_workflow_task')
            ->join('ahg_workflow_step', 'ahg_workflow_task.workflow_step_id', '=', 'ahg_workflow_step.id')
            ->join('ahg_workflow', 'ahg_workflow_task.workflow_id', '=', 'ahg_workflow.id')
            ->leftJoin('user as assigned_user', 'ahg_workflow_task.assigned_to', '=', 'assigned_user.id')
            ->leftJoin('actor_i18n as assigned_actor', function ($join) {
                $join->on('assigned_user.id', '=', 'assigned_actor.id')
                    ->where('assigned_actor.culture', '=', 'en');
            })
            ->leftJoin('user as submitted_user', 'ahg_workflow_task.submitted_by', '=', 'submitted_user.id')
            ->leftJoin('actor_i18n as submitted_actor', function ($join) {
                $join->on('submitted_user.id', '=', 'submitted_actor.id')
                    ->where('submitted_actor.culture', '=', 'en');
            })
            ->where('ahg_workflow_task.id', $id)
            ->select(
                'ahg_workflow_task.*',
                'ahg_workflow_step.name as step_name',
                'ahg_workflow_step.step_type',
                'ahg_workflow_step.action_required',
                'ahg_workflow_step.instructions',
                'ahg_workflow_step.checklist as step_checklist',
                'ahg_workflow_step.pool_enabled',
                'ahg_workflow.name as workflow_name',
                'ahg_workflow.description as workflow_description',
                'assigned_user.username as assigned_username',
                'assigned_actor.authorized_form_of_name as assigned_name',
                'submitted_user.username as submitted_username',
                'submitted_actor.authorized_form_of_name as submitted_name'
            )
            ->first();

        if ($task) {
            $task->history = DB::table('ahg_workflow_history')
                ->leftJoin('user', 'ahg_workflow_history.performed_by', '=', 'user.id')
                ->leftJoin('actor_i18n', function ($join) {
                    $join->on('user.id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', 'en');
                })
                ->where('ahg_workflow_history.task_id', $id)
                ->select(
                    'ahg_workflow_history.*',
                    'user.username',
                    'actor_i18n.authorized_form_of_name as performer_name'
                )
                ->orderBy('ahg_workflow_history.performed_at', 'desc')
                ->get()
                ->toArray();
        }

        return $task;
    }

    /**
     * Claim a task for a user.
     */
    public function claimTask(int $taskId, int $userId): bool
    {
        return DB::transaction(function () use ($taskId, $userId) {
            $task = DB::table('ahg_workflow_task')->where('id', $taskId)->lockForUpdate()->first();
            if (!$task || $task->assigned_to !== null) {
                return false;
            }

            DB::table('ahg_workflow_task')->where('id', $taskId)->update([
                'assigned_to' => $userId,
                'status' => 'claimed',
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logHistory($task, 'claimed', 'pending', 'claimed', $userId);

            return true;
        });
    }

    /**
     * Release a task back to the pool.
     */
    public function releaseTask(int $taskId, int $userId, ?string $comment = null): bool
    {
        return DB::transaction(function () use ($taskId, $userId, $comment) {
            $task = DB::table('ahg_workflow_task')->where('id', $taskId)->first();
            if (!$task || (int) $task->assigned_to !== $userId) {
                return false;
            }

            $fromStatus = $task->status;

            DB::table('ahg_workflow_task')->where('id', $taskId)->update([
                'assigned_to' => null,
                'status' => 'pending',
                'claimed_at' => null,
                'updated_at' => now(),
            ]);

            $this->logHistory($task, 'released', $fromStatus, 'pending', $userId, $comment);

            return true;
        });
    }

    /**
     * Approve a task and create the next step task if applicable.
     */
    public function approveTask(int $taskId, int $userId, ?string $comment = null): bool
    {
        $hadNextStep = false;
        $result = DB::transaction(function () use ($taskId, $userId, $comment, &$hadNextStep) {
            $task = DB::table('ahg_workflow_task')->where('id', $taskId)->lockForUpdate()->first();
            if (!$task) {
                return false;
            }

            $fromStatus = $task->status;

            DB::table('ahg_workflow_task')->where('id', $taskId)->update([
                'decision' => 'approved',
                'status' => 'completed',
                'decision_comment' => $comment,
                'decision_at' => now(),
                'decision_by' => $userId,
                'updated_at' => now(),
            ]);

            $this->logHistory($task, 'approved', $fromStatus, 'completed', $userId, $comment);

            // Find and create next step task
            $currentStep = DB::table('ahg_workflow_step')->where('id', $task->workflow_step_id)->first();
            if ($currentStep) {
                $nextStep = DB::table('ahg_workflow_step')
                    ->where('workflow_id', $task->workflow_id)
                    ->where('step_order', '>', $currentStep->step_order)
                    ->where('is_active', 1)
                    ->orderBy('step_order')
                    ->first();

                if ($nextStep) {
                    $hadNextStep = true;
                    $newTaskId = DB::table('ahg_workflow_task')->insertGetId([
                        'workflow_id' => $task->workflow_id,
                        'workflow_step_id' => $nextStep->id,
                        'object_id' => $task->object_id,
                        'object_type' => $task->object_type,
                        'status' => $nextStep->auto_assign_user_id ? 'claimed' : 'pending',
                        'priority' => $task->priority,
                        'submitted_by' => $task->submitted_by,
                        'assigned_to' => $nextStep->auto_assign_user_id,
                        'claimed_at' => $nextStep->auto_assign_user_id ? now() : null,
                        'due_date' => $nextStep->escalation_days ? now()->addDays($nextStep->escalation_days)->toDateString() : $task->due_date,
                        'decision' => 'pending',
                        'previous_task_id' => $taskId,
                        'queue_id' => $task->queue_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->logHistory(
                        (object) ['id' => $newTaskId, 'workflow_id' => $task->workflow_id, 'workflow_step_id' => $nextStep->id, 'object_id' => $task->object_id, 'object_type' => $task->object_type],
                        'created',
                        null,
                        'pending',
                        $userId,
                        'Auto-created from approval of task #' . $taskId
                    );
                }
            }

            return true;
        });

        if ($result) {
            $this->sendTaskDecisionMail($taskId, $userId, $comment, true, $hadNextStep);
        }

        return $result;
    }

    /**
     * Reject a task.
     */
    public function rejectTask(int $taskId, int $userId, string $comment): bool
    {
        $result = DB::transaction(function () use ($taskId, $userId, $comment) {
            $task = DB::table('ahg_workflow_task')->where('id', $taskId)->lockForUpdate()->first();
            if (!$task) {
                return false;
            }

            $fromStatus = $task->status;

            DB::table('ahg_workflow_task')->where('id', $taskId)->update([
                'decision' => 'rejected',
                'status' => 'completed',
                'decision_comment' => $comment,
                'decision_at' => now(),
                'decision_by' => $userId,
                'updated_at' => now(),
            ]);

            $this->logHistory($task, 'rejected', $fromStatus, 'completed', $userId, $comment);

            return true;
        });

        if ($result) {
            $this->sendTaskDecisionMail($taskId, $userId, $comment, false, false);
        }

        return $result;
    }

    /**
     * Dispatch an approve/reject email to the task submitter, gated on the
     * workflow_email_notifications setting. Mail-delivery failures are logged
     * but never propagated - the workflow decision must still succeed.
     */
    protected function sendTaskDecisionMail(
        int $taskId,
        int $decisionByUserId,
        ?string $comment,
        bool $approved,
        bool $hadNextStep,
    ): void {
        if (!AhgSettingsService::getBool('workflow_email_notifications', true)) {
            return;
        }

        try {
            $row = DB::table('ahg_workflow_task as t')
                ->leftJoin('ahg_workflow as w', 't.workflow_id', '=', 'w.id')
                ->leftJoin('ahg_workflow_step as s', 't.workflow_step_id', '=', 's.id')
                ->leftJoin('user as u_sub', 't.submitted_by', '=', 'u_sub.id')
                ->where('t.id', $taskId)
                ->select(
                    't.id as task_id',
                    't.object_id',
                    't.object_type',
                    't.decision_at',
                    'w.name as workflow_name',
                    's.name as step_name',
                    'u_sub.email as recipient_email',
                    'u_sub.username as recipient_name',
                )
                ->first();

            $decisionByName = DB::table('user')->where('id', $decisionByUserId)->value('username');

            if (!$row || empty($row->recipient_email)) {
                return;
            }

            $context = [
                'task_id' => $row->task_id,
                'workflow_name' => $row->workflow_name,
                'step_name' => $row->step_name,
                'object_type' => $row->object_type,
                'object_id' => $row->object_id,
                'decision_at' => $row->decision_at,
                'recipient_name' => $row->recipient_name,
                'decision_by_name' => $decisionByName,
                'comment' => $comment,
                'has_next_step' => $hadNextStep,
            ];

            $mailable = $approved
                ? new WorkflowTaskApprovedMail($context)
                : new WorkflowTaskRejectedMail($context);

            Mail::to($row->recipient_email)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('[workflow] task-decision mail failed', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Start a new workflow for an object.
     */
    public function startWorkflow(int $workflowId, int $objectId, string $objectType, int $userId): ?int
    {
        return DB::transaction(function () use ($workflowId, $objectId, $objectType, $userId) {
            $workflow = DB::table('ahg_workflow')->where('id', $workflowId)->where('is_active', 1)->first();
            if (!$workflow) {
                return null;
            }

            $firstStep = DB::table('ahg_workflow_step')
                ->where('workflow_id', $workflowId)
                ->where('is_active', 1)
                ->orderBy('step_order')
                ->first();

            if (!$firstStep) {
                return null;
            }

            $taskId = DB::table('ahg_workflow_task')->insertGetId([
                'workflow_id' => $workflowId,
                'workflow_step_id' => $firstStep->id,
                'object_id' => $objectId,
                'object_type' => $objectType,
                'status' => $firstStep->auto_assign_user_id ? 'claimed' : 'pending',
                'priority' => 'normal',
                'submitted_by' => $userId,
                'assigned_to' => $firstStep->auto_assign_user_id,
                'claimed_at' => $firstStep->auto_assign_user_id ? now() : null,
                'due_date' => $firstStep->escalation_days ? now()->addDays($firstStep->escalation_days)->toDateString() : null,
                'decision' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logHistory(
                (object) ['id' => $taskId, 'workflow_id' => $workflowId, 'workflow_step_id' => $firstStep->id, 'object_id' => $objectId, 'object_type' => $objectType],
                'started',
                null,
                'pending',
                $userId,
                'Workflow started: ' . $workflow->name
            );

            return $taskId;
        });
    }

    /**
     * Get all workflows.
     */
    public function getWorkflows(): array
    {
        return DB::table('ahg_workflow')
            ->leftJoin(DB::raw('(SELECT workflow_id, COUNT(*) as step_count FROM ahg_workflow_step GROUP BY workflow_id) sc'), 'ahg_workflow.id', '=', 'sc.workflow_id')
            ->leftJoin(DB::raw('(SELECT workflow_id, COUNT(*) as active_task_count FROM ahg_workflow_task WHERE status IN (\'pending\',\'claimed\',\'in_progress\') GROUP BY workflow_id) tc'), 'ahg_workflow.id', '=', 'tc.workflow_id')
            ->select(
                'ahg_workflow.*',
                DB::raw('COALESCE(sc.step_count, 0) as step_count'),
                DB::raw('COALESCE(tc.active_task_count, 0) as active_task_count')
            )
            ->orderBy('ahg_workflow.name')
            ->get()
            ->toArray();
    }

    /**
     * Get a single workflow with its steps.
     */
    public function getWorkflow(int $id): ?object
    {
        $workflow = DB::table('ahg_workflow')->where('id', $id)->first();

        if ($workflow) {
            $workflow->steps = DB::table('ahg_workflow_step')
                ->where('workflow_id', $id)
                ->orderBy('step_order')
                ->get()
                ->toArray();
        }

        return $workflow;
    }

    /**
     * Create a workflow.
     */
    public function createWorkflow(array $data): int
    {
        return DB::table('ahg_workflow')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'scope_type' => $data['scope_type'] ?? 'global',
            'scope_id' => $data['scope_id'] ?? null,
            'trigger_event' => $data['trigger_event'] ?? 'submit',
            'applies_to' => $data['applies_to'] ?? 'information_object',
            'is_active' => $data['is_active'] ?? 1,
            'is_default' => $data['is_default'] ?? 0,
            'require_all_steps' => $data['require_all_steps'] ?? 1,
            'allow_parallel' => $data['allow_parallel'] ?? 0,
            'auto_archive_days' => $data['auto_archive_days'] ?? null,
            'notification_enabled' => $data['notification_enabled'] ?? 1,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update a workflow.
     */
    public function updateWorkflow(int $id, array $data): bool
    {
        return (bool) DB::table('ahg_workflow')->where('id', $id)->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'scope_type' => $data['scope_type'] ?? 'global',
            'scope_id' => $data['scope_id'] ?? null,
            'trigger_event' => $data['trigger_event'] ?? 'submit',
            'applies_to' => $data['applies_to'] ?? 'information_object',
            'is_active' => $data['is_active'] ?? 1,
            'is_default' => $data['is_default'] ?? 0,
            'require_all_steps' => $data['require_all_steps'] ?? 1,
            'allow_parallel' => $data['allow_parallel'] ?? 0,
            'auto_archive_days' => $data['auto_archive_days'] ?? null,
            'notification_enabled' => $data['notification_enabled'] ?? 1,
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete a workflow and associated steps/tasks.
     */
    public function deleteWorkflow(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            // Delete history for tasks in this workflow
            DB::table('ahg_workflow_history')->where('workflow_id', $id)->delete();
            // Delete notifications for tasks in this workflow
            $taskIds = DB::table('ahg_workflow_task')->where('workflow_id', $id)->pluck('id');
            if ($taskIds->isNotEmpty()) {
                DB::table('ahg_workflow_notification')->whereIn('task_id', $taskIds)->delete();
            }
            // Delete tasks
            DB::table('ahg_workflow_task')->where('workflow_id', $id)->delete();
            // Delete steps
            DB::table('ahg_workflow_step')->where('workflow_id', $id)->delete();
            // Delete workflow
            return (bool) DB::table('ahg_workflow')->where('id', $id)->delete();
        });
    }

    /**
     * Add a step to a workflow.
     */
    public function addStep(int $workflowId, array $data): int
    {
        $maxOrder = DB::table('ahg_workflow_step')
            ->where('workflow_id', $workflowId)
            ->max('step_order') ?? 0;

        return DB::table('ahg_workflow_step')->insertGetId([
            'workflow_id' => $workflowId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'step_order' => $data['step_order'] ?? ($maxOrder + 1),
            'step_type' => $data['step_type'] ?? 'review',
            'action_required' => $data['action_required'] ?? 'approve_reject',
            'required_role_id' => $data['required_role_id'] ?? null,
            'required_clearance_level' => $data['required_clearance_level'] ?? null,
            'allowed_group_ids' => $data['allowed_group_ids'] ?? null,
            'allowed_user_ids' => $data['allowed_user_ids'] ?? null,
            'pool_enabled' => $data['pool_enabled'] ?? 1,
            'auto_assign_user_id' => $data['auto_assign_user_id'] ?? null,
            'escalation_days' => $data['escalation_days'] ?? null,
            'escalation_user_id' => $data['escalation_user_id'] ?? null,
            'notification_template' => $data['notification_template'] ?? 'default',
            'instructions' => $data['instructions'] ?? null,
            'checklist' => $data['checklist'] ?? null,
            'is_optional' => $data['is_optional'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update a step.
     */
    public function updateStep(int $id, array $data): bool
    {
        $update = [
            'updated_at' => now(),
        ];

        $fields = ['name', 'description', 'step_order', 'step_type', 'action_required',
            'required_role_id', 'required_clearance_level', 'allowed_group_ids', 'allowed_user_ids',
            'pool_enabled', 'auto_assign_user_id', 'escalation_days', 'escalation_user_id',
            'notification_template', 'instructions', 'checklist', 'is_optional', 'is_active'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        return (bool) DB::table('ahg_workflow_step')->where('id', $id)->update($update);
    }

    /**
     * Delete a step.
     */
    public function deleteStep(int $id): bool
    {
        return (bool) DB::table('ahg_workflow_step')->where('id', $id)->delete();
    }

    /**
     * Get recent workflow history.
     */
    public function getHistory(int $limit = 100): array
    {
        return DB::table('ahg_workflow_history')
            ->leftJoin('user', 'ahg_workflow_history.performed_by', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->leftJoin('ahg_workflow', 'ahg_workflow_history.workflow_id', '=', 'ahg_workflow.id')
            ->select(
                'ahg_workflow_history.*',
                'user.username',
                'actor_i18n.authorized_form_of_name as performer_name',
                'ahg_workflow.name as workflow_name'
            )
            ->orderBy('ahg_workflow_history.performed_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get all history for a specific object.
     */
    public function getObjectHistory(int $objectId): array
    {
        return DB::table('ahg_workflow_history')
            ->leftJoin('user', 'ahg_workflow_history.performed_by', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->leftJoin('ahg_workflow', 'ahg_workflow_history.workflow_id', '=', 'ahg_workflow.id')
            ->where('ahg_workflow_history.object_id', $objectId)
            ->select(
                'ahg_workflow_history.*',
                'user.username',
                'actor_i18n.authorized_form_of_name as performer_name',
                'ahg_workflow.name as workflow_name'
            )
            ->orderBy('ahg_workflow_history.performed_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get all workflow queues with task counts.
     */
    public function getQueues(): array
    {
        return DB::table('ahg_workflow_queue')
            ->leftJoin(DB::raw('(SELECT queue_id, COUNT(*) as task_count FROM ahg_workflow_task WHERE status IN (\'pending\',\'claimed\',\'in_progress\') GROUP BY queue_id) tc'), 'ahg_workflow_queue.id', '=', 'tc.queue_id')
            ->leftJoin(DB::raw('(SELECT queue_id, COUNT(*) as overdue_count FROM ahg_workflow_task WHERE status IN (\'pending\',\'claimed\',\'in_progress\') AND due_date < CURDATE() GROUP BY queue_id) oc'), 'ahg_workflow_queue.id', '=', 'oc.queue_id')
            ->leftJoin('ahg_workflow_sla_policy', 'ahg_workflow_queue.id', '=', 'ahg_workflow_sla_policy.queue_id')
            ->select(
                'ahg_workflow_queue.*',
                DB::raw('COALESCE(tc.task_count, 0) as task_count'),
                DB::raw('COALESCE(oc.overdue_count, 0) as overdue_count'),
                'ahg_workflow_sla_policy.warning_days',
                'ahg_workflow_sla_policy.due_days',
                'ahg_workflow_sla_policy.escalation_days as sla_escalation_days'
            )
            ->orderBy('ahg_workflow_queue.sort_order')
            ->get()
            ->toArray();
    }

    /**
     * Get overdue tasks, optionally filtered by user or queue.
     */
    public function getOverdueTasks(?int $userId = null, ?int $queueId = null): array
    {
        $query = DB::table('ahg_workflow_task')
            ->join('ahg_workflow_step', 'ahg_workflow_task.workflow_step_id', '=', 'ahg_workflow_step.id')
            ->join('ahg_workflow', 'ahg_workflow_task.workflow_id', '=', 'ahg_workflow.id')
            ->leftJoin('user', 'ahg_workflow_task.assigned_to', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->whereIn('ahg_workflow_task.status', ['pending', 'claimed', 'in_progress'])
            ->whereNotNull('ahg_workflow_task.due_date')
            ->where('ahg_workflow_task.due_date', '<', now()->toDateString())
            ->select(
                'ahg_workflow_task.*',
                'ahg_workflow_step.name as step_name',
                'ahg_workflow.name as workflow_name',
                'user.username as assigned_username',
                'actor_i18n.authorized_form_of_name as assigned_name'
            )
            ->orderBy('ahg_workflow_task.due_date');

        if ($userId) {
            $query->where('ahg_workflow_task.assigned_to', $userId);
        }
        if ($queueId) {
            $query->where('ahg_workflow_task.queue_id', $queueId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get all publish gate rules.
     */
    public function getGateRules(): array
    {
        return DB::table('ahg_publish_gate_rule')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get a single gate rule.
     */
    public function getGateRule(int $id): ?object
    {
        return DB::table('ahg_publish_gate_rule')->where('id', $id)->first();
    }

    /**
     * Create or update a gate rule.
     */
    public function saveGateRule(array $data, ?int $id = null): int
    {
        $row = [
            'name' => $data['name'],
            'rule_type' => $data['rule_type'],
            'entity_type' => $data['entity_type'] ?? 'information_object',
            'level_of_description_id' => $data['level_of_description_id'] ?: null,
            'material_type' => $data['material_type'] ?: null,
            'repository_id' => $data['repository_id'] ?: null,
            'field_name' => $data['field_name'] ?? null,
            'rule_config' => $data['rule_config'] ?? null,
            'error_message' => $data['error_message'],
            'severity' => $data['severity'] ?? 'blocker',
            'is_active' => $data['is_active'] ?? 1,
            'sort_order' => $data['sort_order'] ?? 0,
            'updated_at' => now(),
        ];

        if ($id) {
            DB::table('ahg_publish_gate_rule')->where('id', $id)->update($row);
            return $id;
        }

        $row['created_at'] = now();
        return DB::table('ahg_publish_gate_rule')->insertGetId($row);
    }

    /**
     * Delete a gate rule.
     */
    public function deleteGateRule(int $id): bool
    {
        DB::table('ahg_publish_gate_result')->where('rule_id', $id)->delete();
        return (bool) DB::table('ahg_publish_gate_rule')->where('id', $id)->delete();
    }

    /**
     * Evaluate all applicable gate rules for an object.
     */
    public function evaluateGates(int $objectId): array
    {
        // Get the object info
        $io = DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->where('information_object.id', $objectId)
            ->select('information_object.*', 'information_object_i18n.title', 'information_object_i18n.scope_and_content')
            ->first();

        if (!$io) {
            return ['object' => null, 'results' => [], 'summary' => ['pass' => 0, 'fail' => 0, 'warning' => 0]];
        }

        $rules = DB::table('ahg_publish_gate_rule')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();

        $results = [];
        $summary = ['pass' => 0, 'fail' => 0, 'warning' => 0];

        foreach ($rules as $rule) {
            // Filter by entity_type
            if ($rule->entity_type && $rule->entity_type !== 'information_object') {
                continue;
            }

            // Filter by level_of_description_id
            if ($rule->level_of_description_id && $io->level_of_description_id != $rule->level_of_description_id) {
                continue;
            }

            // Filter by repository_id
            if ($rule->repository_id && $io->repository_id != $rule->repository_id) {
                continue;
            }

            $status = $this->evaluateSingleGate($rule, $io);
            $details = null;

            if ($status === 'fail' || $status === 'warning') {
                $details = $rule->error_message;
            }

            // Store the result
            DB::table('ahg_publish_gate_result')->updateOrInsert(
                ['object_id' => $objectId, 'rule_id' => $rule->id],
                [
                    'status' => $status,
                    'details' => $details,
                    'evaluated_at' => now(),
                    'evaluated_by' => auth()->id(),
                ]
            );

            $results[] = (object) [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'rule_type' => $rule->rule_type,
                'severity' => $rule->severity,
                'status' => $status,
                'details' => $details,
            ];

            $summary[$status] = ($summary[$status] ?? 0) + 1;
        }

        return [
            'object' => $io,
            'results' => $results,
            'summary' => $summary,
        ];
    }

    /**
     * Evaluate a single gate rule against an object.
     */
    private function evaluateSingleGate(object $rule, object $io): string
    {
        switch ($rule->rule_type) {
            case 'required_field':
                $fieldName = $rule->field_name;
                if (!$fieldName) {
                    return 'pass';
                }
                $value = $io->$fieldName ?? null;
                return (!empty($value) && trim((string) $value) !== '') ? 'pass' : ($rule->severity === 'warning' ? 'warning' : 'fail');

            case 'workflow_completed':
                // Check if all workflow tasks for this object are completed
                $pending = DB::table('ahg_workflow_task')
                    ->where('object_id', $io->id)
                    ->whereIn('status', ['pending', 'claimed', 'in_progress'])
                    ->count();
                return $pending === 0 ? 'pass' : ($rule->severity === 'warning' ? 'warning' : 'fail');

            case 'digital_object_required':
                $hasDo = DB::table('digital_object')
                    ->where('information_object_id', $io->id)
                    ->exists();
                return $hasDo ? 'pass' : ($rule->severity === 'warning' ? 'warning' : 'fail');

            case 'min_description_length':
                $config = json_decode($rule->rule_config, true);
                $minLength = $config['min_length'] ?? 100;
                $fieldName = $rule->field_name ?? 'scope_and_content';
                $value = $io->$fieldName ?? '';
                return (mb_strlen((string) $value) >= $minLength) ? 'pass' : ($rule->severity === 'warning' ? 'warning' : 'fail');

            case 'custom_sql':
                $config = json_decode($rule->rule_config, true);
                if (!empty($config['sql'])) {
                    try {
                        $result = DB::select($config['sql'], ['object_id' => $io->id]);
                        return (!empty($result) && ($result[0]->result ?? 0)) ? 'pass' : ($rule->severity === 'warning' ? 'warning' : 'fail');
                    } catch (\Exception $e) {
                        return 'fail';
                    }
                }
                return 'pass';

            default:
                return 'pass';
        }
    }

    /**
     * Check if workflow approval is required before publishing.
     */
    public function isWorkflowRequiredForPublish(): bool
    {
        try {
            $val = DB::table('ahg_settings')
                ->where('setting_key', 'workflow_required_for_publish')
                ->value('setting_value');

            return $val === '1' || $val === 'true';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if an object has completed all workflow steps (approved for publish).
     * Returns true if:
     *   - A workflow was started AND all steps are completed with 'approved' decision
     *   - OR no workflow was ever started (no tasks exist)
     *     BUT when workflow is required, having no tasks = NOT approved
     */
    public function isWorkflowApprovedForPublish(int $objectId): bool
    {
        // Check if any workflow tasks exist for this object
        $totalTasks = DB::table('ahg_workflow_task')
            ->where('object_id', $objectId)
            ->where('object_type', 'information_object')
            ->count();

        if ($totalTasks === 0) {
            // No workflow started — not approved
            return false;
        }

        // Check if any tasks are still pending/in-progress
        $pendingTasks = DB::table('ahg_workflow_task')
            ->where('object_id', $objectId)
            ->where('object_type', 'information_object')
            ->whereIn('status', ['pending', 'claimed', 'in_progress'])
            ->count();

        if ($pendingTasks > 0) {
            return false;
        }

        // Check the most recent task was approved (not rejected)
        $lastTask = DB::table('ahg_workflow_task')
            ->where('object_id', $objectId)
            ->where('object_type', 'information_object')
            ->orderByDesc('updated_at')
            ->first();

        return $lastTask && $lastTask->decision === 'approved';
    }

    /**
     * Log a workflow history entry.
     */
    private function logHistory(object $task, string $action, ?string $fromStatus, ?string $toStatus, int $userId, ?string $comment = null): void
    {
        DB::table('ahg_workflow_history')->insert([
            'task_id' => $task->id,
            'workflow_id' => $task->workflow_id,
            'workflow_step_id' => $task->workflow_step_id ?? null,
            'object_id' => $task->object_id,
            'object_type' => $task->object_type,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => $userId,
            'performed_at' => now(),
            'comment' => $comment,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => Str::uuid()->toString(),
        ]);
    }
}
