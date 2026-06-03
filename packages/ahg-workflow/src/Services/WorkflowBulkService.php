<?php

/**
 * WorkflowBulkService - bulk operations over workflow tasks.
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk transition / assign / note / priority / move-to-queue operations
 * (PSIS parity: ahgWorkflowPlugin WorkflowBulkService). Each operation runs
 * per-task, isolates per-task failures, stamps a shared correlation_id across
 * the whole batch, and returns {success: [...], failed: [...], correlation_id}.
 * Single-task transitions delegate to WorkflowService (which already logs
 * history + sends mail); note/priority/queue write their own history rows.
 */
class WorkflowBulkService
{
    private WorkflowService $workflow;

    public function __construct(?WorkflowService $workflow = null)
    {
        $this->workflow = $workflow ?? new WorkflowService;
    }

    /**
     * Bulk transition: approve | reject | cancel.
     *
     * @param  array<int>  $taskIds
     * @return array{success: array, failed: array, correlation_id: string}
     */
    public function bulkTransition(array $taskIds, string $action, int $userId, ?string $comment = null): array
    {
        $cid = (string) Str::uuid();
        $result = ['success' => [], 'failed' => [], 'correlation_id' => $cid];

        foreach ($taskIds as $taskId) {
            $taskId = (int) $taskId;
            try {
                $task = DB::table('ahg_workflow_task')->where('id', $taskId)->first();
                if (! $task) {
                    $result['failed'][] = ['task_id' => $taskId, 'reason' => 'Task not found'];
                    continue;
                }
                if (in_array($task->status, ['approved', 'rejected', 'cancelled'], true)) {
                    $result['failed'][] = ['task_id' => $taskId, 'reason' => "Already {$task->status}"];
                    continue;
                }

                switch ($action) {
                    case 'approve':
                        $this->workflow->approveTask($taskId, $userId, $comment);
                        break;
                    case 'reject':
                        $this->workflow->rejectTask($taskId, $userId, $comment ?: 'Bulk rejected');
                        break;
                    case 'cancel':
                        DB::table('ahg_workflow_task')->where('id', $taskId)->update([
                            'status' => 'cancelled', 'updated_at' => now(),
                        ]);
                        $this->history($task, 'cancelled', $task->status, 'cancelled', $userId, $comment ?: 'Bulk cancelled', $cid);
                        break;
                    default:
                        $result['failed'][] = ['task_id' => $taskId, 'reason' => "Unknown action: {$action}"];
                        continue 2;
                }

                $result['success'][] = ['task_id' => $taskId, 'from_status' => $task->status, 'action' => $action];
            } catch (\Throwable $e) {
                $result['failed'][] = ['task_id' => $taskId, 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Bulk assign tasks to a user.
     *
     * @param  array<int>  $taskIds
     * @return array{success: array, failed: array, correlation_id: string}
     */
    public function bulkAssign(array $taskIds, int $targetUserId, int $performedBy, ?string $comment = null): array
    {
        $cid = (string) Str::uuid();
        $result = ['success' => [], 'failed' => [], 'correlation_id' => $cid];

        foreach ($taskIds as $taskId) {
            $taskId = (int) $taskId;
            try {
                $ok = $this->workflow->assignToUser($taskId, $targetUserId, $performedBy, $comment);
                if ($ok) {
                    $result['success'][] = ['task_id' => $taskId, 'assigned_to' => $targetUserId];
                } else {
                    $result['failed'][] = ['task_id' => $taskId, 'reason' => 'Assign failed (task missing or not assignable)'];
                }
            } catch (\Throwable $e) {
                $result['failed'][] = ['task_id' => $taskId, 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Bulk add a note to tasks (history-only; does not change status).
     *
     * @param  array<int>  $taskIds
     * @return array{success: array, failed: array, correlation_id: string}
     */
    public function bulkAddNote(array $taskIds, string $note, int $userId): array
    {
        $cid = (string) Str::uuid();
        $result = ['success' => [], 'failed' => [], 'correlation_id' => $cid];

        foreach ($taskIds as $taskId) {
            $taskId = (int) $taskId;
            try {
                $task = DB::table('ahg_workflow_task')->where('id', $taskId)->first();
                if (! $task) {
                    $result['failed'][] = ['task_id' => $taskId, 'reason' => 'Task not found'];
                    continue;
                }
                $this->history($task, 'note', $task->status, $task->status, $userId, $note, $cid);
                $result['success'][] = ['task_id' => $taskId, 'status' => 'note_added'];
            } catch (\Throwable $e) {
                $result['failed'][] = ['task_id' => $taskId, 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Bulk change priority.
     *
     * @param  array<int>  $taskIds
     * @return array{success: array, failed: array, correlation_id: string}
     */
    public function bulkChangePriority(array $taskIds, string $newPriority, int $userId): array
    {
        $cid = (string) Str::uuid();
        $result = ['success' => [], 'failed' => [], 'correlation_id' => $cid];
        $allowed = ['low', 'normal', 'high', 'urgent'];

        foreach ($taskIds as $taskId) {
            $taskId = (int) $taskId;
            try {
                if (! in_array($newPriority, $allowed, true)) {
                    $result['failed'][] = ['task_id' => $taskId, 'reason' => "Invalid priority: {$newPriority}"];
                    continue;
                }
                $task = DB::table('ahg_workflow_task')->where('id', $taskId)->first();
                if (! $task) {
                    $result['failed'][] = ['task_id' => $taskId, 'reason' => 'Task not found'];
                    continue;
                }
                DB::table('ahg_workflow_task')->where('id', $taskId)->update([
                    'priority' => $newPriority, 'updated_at' => now(),
                ]);
                $this->history($task, 'priority_changed', null, null, $userId, "Priority {$task->priority} → {$newPriority}", $cid);
                $result['success'][] = ['task_id' => $taskId, 'new_priority' => $newPriority];
            } catch (\Throwable $e) {
                $result['failed'][] = ['task_id' => $taskId, 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Bulk move tasks to a queue.
     *
     * @param  array<int>  $taskIds
     * @return array{success: array, failed: array, correlation_id: string}
     */
    public function bulkMoveToQueue(array $taskIds, int $queueId, int $userId): array
    {
        $cid = (string) Str::uuid();
        $result = ['success' => [], 'failed' => [], 'correlation_id' => $cid];

        foreach ($taskIds as $taskId) {
            $taskId = (int) $taskId;
            try {
                $task = DB::table('ahg_workflow_task')->where('id', $taskId)->first();
                if (! $task) {
                    $result['failed'][] = ['task_id' => $taskId, 'reason' => 'Task not found'];
                    continue;
                }
                DB::table('ahg_workflow_task')->where('id', $taskId)->update([
                    'queue_id' => $queueId, 'updated_at' => now(),
                ]);
                $this->history($task, 'moved_to_queue', null, null, $userId, "Moved to queue #{$queueId}", $cid);
                $result['success'][] = ['task_id' => $taskId, 'queue_id' => $queueId];
            } catch (\Throwable $e) {
                $result['failed'][] = ['task_id' => $taskId, 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /** Write a history row mirroring WorkflowService::logHistory (which is private). */
    private function history(object $task, string $action, ?string $from, ?string $to, int $userId, ?string $comment, string $cid): void
    {
        DB::table('ahg_workflow_history')->insert([
            'task_id' => $task->id,
            'workflow_id' => $task->workflow_id,
            'workflow_step_id' => $task->workflow_step_id ?? null,
            'object_id' => $task->object_id,
            'object_type' => $task->object_type,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'performed_by' => $userId,
            'performed_at' => now(),
            'comment' => $comment,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => $cid,
        ]);
    }
}
