<?php

/**
 * AssignmentService - Service for Heratio
 *
 * Assign / Workflow feature of the AHG Authority Resolution Engine. Lets an
 * archivist hand a mention (single, or a whole filtered batch) to another
 * archivist. Assignment is routed through the ahg-workflow plugin so the
 * assignee sees the task in their normal workflow dashboard.
 *
 * Flow per mention:
 *   - If the mention already carries a workflow_task_id, the existing
 *     ahg_workflow_task is re-assigned (WorkflowService::assignToUser) -
 *     we never spawn a second task for the same mention.
 *   - Otherwise a new task is started on the "Authority Resolution Review"
 *     workflow (object_type='ahg_mention') and then assigned.
 *   - Either way ahg_mention's assignment columns (assigned_to_user_id,
 *     assigned_by_user_id, assigned_at, workflow_task_id) are written.
 *
 * Graceful degrade: if the ahg-workflow plugin is not installed
 * (WorkflowService class missing), the ahg_mention assignment columns are
 * still written and the call returns ok with workflow_task_id = null.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignmentService
{
    /**
     * Name of the workflow seeded by database/seed_workflow.sql. We resolve
     * the workflow id by name so it survives auto-increment differences
     * between installs.
     */
    private const WORKFLOW_NAME = 'Authority Resolution Review';

    /**
     * Polymorphic object_type used on ahg_workflow_task / ahg_workflow_history
     * rows for authority-resolution mentions.
     */
    private const OBJECT_TYPE = 'ahg_mention';

    /**
     * Assign a single mention to an archivist, routing through the workflow
     * plugin. Idempotent in the sense that re-assigning an already-assigned
     * mention re-targets the existing task instead of creating a new one.
     *
     * @param  string|null  $reason  Optional archivist reason / message. Stored
     *                               as the workflow task's assignment comment -
     *                               a ahg_workflow_history row written by
     *                               WorkflowService::assignToUser(). No schema
     *                               change: the history row already exists for
     *                               every assignment, the reason just replaces
     *                               its default "Assigned to user #N" note.
     * @return array{ok:bool, workflow_task_id:?int, error:?string}
     */
    public function assign(int $mentionId, int $archivistUserId, int $byUserId, ?string $reason = null): array
    {
        $mention = DB::table('ahg_mention')->where('id', $mentionId)->first();
        if (! $mention) {
            return ['ok' => false, 'workflow_task_id' => null, 'error' => "Mention #{$mentionId} not found."];
        }
        if ($archivistUserId <= 0) {
            return ['ok' => false, 'workflow_task_id' => null, 'error' => 'No archivist selected.'];
        }

        $workflowAvailable = $this->workflowAvailable();
        $reason = ($reason !== null && trim($reason) !== '') ? trim($reason) : null;

        try {
            return DB::transaction(function () use ($mention, $mentionId, $archivistUserId, $byUserId, $workflowAvailable, $reason) {
                $taskId = $mention->workflow_task_id ? (int) $mention->workflow_task_id : null;

                if ($workflowAvailable) {
                    $workflow = $this->workflowService();

                    if ($taskId) {
                        // Re-assign the existing task to the new archivist.
                        $ok = $workflow->assignToUser($taskId, $archivistUserId, $byUserId, $reason);
                        if (! $ok) {
                            // The task row vanished (deleted workflow?); fall
                            // through and start a fresh one.
                            $taskId = null;
                        }
                    }

                    if (! $taskId) {
                        $workflowId = $this->resolveWorkflowId();
                        if ($workflowId === null) {
                            return [
                                'ok' => false,
                                'workflow_task_id' => null,
                                'error' => 'Authority Resolution Review workflow is not seeded.',
                            ];
                        }
                        $newTaskId = $workflow->startWorkflow(
                            $workflowId,
                            $mentionId,
                            self::OBJECT_TYPE,
                            $byUserId
                        );
                        if ($newTaskId === null) {
                            return [
                                'ok' => false,
                                'workflow_task_id' => null,
                                'error' => 'Could not start workflow task (workflow inactive or has no steps).',
                            ];
                        }
                        $taskId = (int) $newTaskId;
                        $workflow->assignToUser($taskId, $archivistUserId, $byUserId, $reason);
                    }
                }

                DB::table('ahg_mention')->where('id', $mentionId)->update([
                    'assigned_to_user_id' => $archivistUserId,
                    'assigned_by_user_id' => $byUserId,
                    'assigned_at' => now(),
                    'workflow_task_id' => $taskId,
                    'updated_at' => now(),
                ]);

                return ['ok' => true, 'workflow_task_id' => $taskId, 'error' => null];
            });
        } catch (\Throwable $e) {
            Log::warning('[auth-res] assignment failed', [
                'mention_id' => $mentionId,
                'archivist_user_id' => $archivistUserId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'workflow_task_id' => null, 'error' => 'Assignment failed: '.$e->getMessage()];
        }
    }

    /**
     * Assign a batch of mentions to one archivist. Each mention is assigned
     * independently - one failure does not abort the rest.
     *
     * @param  list<int>  $mentionIds
     * @param  string|null  $reason  Optional reason / message applied to every
     *                               mention in the batch.
     * @return array{assigned:int, failed:int, errors:list<string>}
     */
    public function assignBatch(array $mentionIds, int $archivistUserId, int $byUserId, ?string $reason = null): array
    {
        $assigned = 0;
        $failed = 0;
        $errors = [];

        foreach (array_unique(array_map('intval', $mentionIds)) as $mentionId) {
            if ($mentionId <= 0) {
                continue;
            }
            $result = $this->assign($mentionId, $archivistUserId, $byUserId, $reason);
            if ($result['ok']) {
                $assigned++;
            } else {
                $failed++;
                if ($result['error']) {
                    $errors[] = "Mention #{$mentionId}: ".$result['error'];
                }
            }
        }

        return ['assigned' => $assigned, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * List users eligible to be assignees (admins / editors). Reuses
     * AhgCore\Services\AclService when it can answer the role question;
     * otherwise falls back to every active user. Display name prefers the
     * actor_i18n authorized form, then username, then a "User #id" stub.
     *
     * @return list<array{id:int, name:string, username:?string}>
     */
    public function archivists(): array
    {
        $eligibleIds = $this->eligibleIdsViaAcl();

        $q = DB::table('user')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.active', 1)
            ->select(
                'user.id',
                'user.username',
                'actor_i18n.authorized_form_of_name as display_name'
            )
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->orderBy('user.username');

        if ($eligibleIds !== null && ! empty($eligibleIds)) {
            $q->whereIn('user.id', $eligibleIds);
        }

        return $q->get()->map(function ($row) {
            $name = trim((string) ($row->display_name ?? ''));
            if ($name === '') {
                $name = trim((string) ($row->username ?? ''));
            }
            if ($name === '') {
                $name = 'User #'.(int) $row->id;
            }

            return [
                'id' => (int) $row->id,
                'name' => $name,
                'username' => $row->username !== null ? (string) $row->username : null,
            ];
        })->all();
    }

    /**
     * Restrict the archivist list to admin / editor group members when the
     * ACL group tables are present. Returns null to mean "no filter -
     * caller should list all active users".
     *
     * @return ?list<int>
     */
    private function eligibleIdsViaAcl(): ?array
    {
        try {
            // The Heratio ACL stores group membership in acl_user_group +
            // acl_group (group names in acl_group_i18n). Admins / editors
            // are the assignment-eligible roles.
            $hasTables = \Illuminate\Support\Facades\Schema::hasTable('acl_user_group')
                && \Illuminate\Support\Facades\Schema::hasTable('acl_group_i18n');
            if (! $hasTables) {
                return null;
            }

            $ids = DB::table('acl_user_group as ug')
                ->join('acl_group_i18n as gi', 'gi.id', '=', 'ug.group_id')
                ->where('gi.culture', 'en')
                ->where(function ($w) {
                    $w->whereRaw('LOWER(gi.name) LIKE ?', ['%admin%'])
                        ->orWhereRaw('LOWER(gi.name) LIKE ?', ['%editor%'])
                        ->orWhereRaw('LOWER(gi.name) LIKE ?', ['%archivist%']);
                })
                ->distinct()
                ->pluck('ug.user_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            // If the ACL groups exist but nobody matched (unusual install),
            // fall back to all users rather than returning an empty picker.
            return ! empty($ids) ? $ids : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the seeded workflow id by name.
     */
    private function resolveWorkflowId(): ?int
    {
        $id = DB::table('ahg_workflow')
            ->where('name', self::WORKFLOW_NAME)
            ->where('is_active', 1)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * True when the ahg-workflow plugin is installed and its tables exist.
     */
    private function workflowAvailable(): bool
    {
        if (! class_exists(\AhgWorkflow\Services\WorkflowService::class)) {
            return false;
        }
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('ahg_workflow_task')
                && \Illuminate\Support\Facades\Schema::hasTable('ahg_workflow');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve a WorkflowService instance from the container.
     */
    private function workflowService(): \AhgWorkflow\Services\WorkflowService
    {
        return app(\AhgWorkflow\Services\WorkflowService::class);
    }
}
