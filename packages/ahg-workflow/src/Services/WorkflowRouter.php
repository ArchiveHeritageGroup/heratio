<?php

/**
 * WorkflowRouter - Heratio ahg-workflow
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgWorkflow\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * heratio#1773: route a request (an access request today; any object type a
 * workflow names in applies_to) into the active workflow for it, and tell the
 * owning module when the workflow finishes.
 *
 * The finish is the string event 'workflow.finished' with
 * (string $objectType, int $objectId, string $decision, int $userId, ?string $comment);
 * decision is 'approved' after the last step's approval, or 'rejected'.
 */
class WorkflowRouter
{
    public const FINISHED = 'workflow.finished';

    /** The active submit workflow for this type (default first), or null. */
    public static function workflowFor(string $objectType): ?object
    {
        if (! Schema::hasTable('ahg_workflow')) {
            return null;
        }

        return DB::table('ahg_workflow')
            ->where('applies_to', $objectType)->where('trigger_event', 'submit')->where('is_active', 1)
            ->orderByDesc('is_default')->orderBy('id')
            ->first();
    }

    /** Start the type's workflow for the object; returns the first task id, or null when none applies. */
    public static function start(string $objectType, int $objectId, int $userId): ?int
    {
        $wf = self::workflowFor($objectType);

        return $wf ? app(WorkflowService::class)->startWorkflow((int) $wf->id, $objectId, $objectType, $userId) : null;
    }

    /** The open (pending or claimed) task for the object, with its step name. */
    public static function openTask(string $objectType, int $objectId): ?object
    {
        if (! Schema::hasTable('ahg_workflow_task')) {
            return null;
        }

        return DB::table('ahg_workflow_task as t')
            ->leftJoin('ahg_workflow_step as s', 's.id', '=', 't.workflow_step_id')
            ->where('t.object_type', $objectType)->where('t.object_id', $objectId)
            ->whereIn('t.status', ['pending', 'claimed', 'in_progress'])
            ->select('t.*', 's.name as step_name')
            ->orderByDesc('t.id')
            ->first();
    }

    /**
     * The object was decided outside the workflow (from its own screen):
     * close any open task so it leaves the queues, and say why in its history.
     */
    public static function close(string $objectType, int $objectId, int $userId, string $decision, ?string $comment = null): void
    {
        if (! Schema::hasTable('ahg_workflow_task')) {
            return;
        }
        $tasks = DB::table('ahg_workflow_task')
            ->where('object_type', $objectType)->where('object_id', $objectId)
            ->whereIn('status', ['pending', 'claimed', 'in_progress'])
            ->get();
        foreach ($tasks as $t) {
            DB::table('ahg_workflow_task')->where('id', $t->id)->update([
                'status' => 'completed', 'decision' => $decision, 'decision_comment' => $comment,
                'decision_at' => now(), 'decision_by' => $userId, 'updated_at' => now(),
            ]);
            DB::table('ahg_workflow_history')->insert([
                'task_id' => $t->id, 'workflow_id' => $t->workflow_id, 'workflow_step_id' => $t->workflow_step_id,
                'object_id' => $objectId, 'object_type' => $objectType, 'action' => $decision,
                'from_status' => $t->status, 'to_status' => 'completed', 'performed_by' => $userId,
                'performed_at' => now(), 'comment' => $comment ?? 'Decided on the request itself',
                'ip_address' => request()->ip(), 'correlation_id' => Str::uuid()->toString(),
            ]);
        }
    }

    /** Workflow history rows for the object, oldest first, with step and user names. */
    public static function history(string $objectType, int $objectId): array
    {
        if (! Schema::hasTable('ahg_workflow_history')) {
            return [];
        }

        return DB::table('ahg_workflow_history as h')
            ->leftJoin('ahg_workflow_step as s', 's.id', '=', 'h.workflow_step_id')
            ->leftJoin('user as u', 'u.id', '=', 'h.performed_by')
            ->where('h.object_type', $objectType)->where('h.object_id', $objectId)
            ->orderBy('h.performed_at')->orderBy('h.id')
            ->get(['h.action', 'h.comment', 'h.performed_at', 's.name as step_name', 'u.username'])
            ->all();
    }
}
