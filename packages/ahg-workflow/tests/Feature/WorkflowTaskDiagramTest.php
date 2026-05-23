<?php

/**
 * WorkflowTaskDiagramTest - heratio#143 Phase 2 (task progress overlay) tests.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgWorkflow\Services\WorkflowDiagramService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkflowTaskDiagramTest extends TestCase
{
    use DatabaseTransactions;

    private WorkflowDiagramService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new WorkflowDiagramService();
    }

    public function test_render_for_task_returns_empty_state_for_nonexistent_task(): void
    {
        $payload = $this->svc->renderForTask(999999999);
        $this->assertNull($payload['task']);
        $this->assertNull($payload['workflowId']);
        $this->assertSame([], $payload['statusMap']);
        $this->assertStringContainsString('Task not found', $payload['svg']);
    }

    public function test_brand_new_task_marks_current_step_as_current_only(): void
    {
        $wfId = $this->makeWorkflow();
        $sId1 = $this->makeStep($wfId, 'Step 1', 1);
        $sId2 = $this->makeStep($wfId, 'Step 2', 2);
        $sId3 = $this->makeStep($wfId, 'Step 3', 3);

        $taskId = $this->makeTask($wfId, $sId1, objectId: 42, status: 'pending', decision: 'pending');

        $payload = $this->svc->renderForTask($taskId);
        $this->assertSame('current', $payload['statusMap'][$sId1]);
        $this->assertArrayNotHasKey($sId2, $payload['statusMap']);    // pending = absent
        $this->assertArrayNotHasKey($sId3, $payload['statusMap']);
    }

    public function test_completed_step_then_current_step_maps_correctly(): void
    {
        $wfId = $this->makeWorkflow();
        $sId1 = $this->makeStep($wfId, 'Step 1', 1);
        $sId2 = $this->makeStep($wfId, 'Step 2', 2);
        $sId3 = $this->makeStep($wfId, 'Step 3', 3);

        // Step 1 was approved, Step 2 is current.
        $this->makeTask($wfId, $sId1, objectId: 42, status: 'completed', decision: 'approved');
        $task2Id = $this->makeTask($wfId, $sId2, objectId: 42, status: 'claimed', decision: 'pending');

        $payload = $this->svc->renderForTask($task2Id);
        $this->assertSame('completed', $payload['statusMap'][$sId1]);
        $this->assertSame('current',   $payload['statusMap'][$sId2]);
        $this->assertArrayNotHasKey($sId3, $payload['statusMap']);

        $this->assertStringContainsString('wfdiag-status-completed', $payload['svg']);
        $this->assertStringContainsString('wfdiag-status-current',   $payload['svg']);
    }

    public function test_rejected_step_overrides_completed_on_same_step(): void
    {
        $wfId = $this->makeWorkflow();
        $sId1 = $this->makeStep($wfId, 'Step 1', 1);

        // Two tasks on the same step — one approved (resubmitted) then rejected.
        $this->makeTask($wfId, $sId1, objectId: 42, status: 'completed', decision: 'approved');
        $task2Id = $this->makeTask($wfId, $sId1, objectId: 42, status: 'completed', decision: 'rejected');

        $payload = $this->svc->renderForTask($task2Id);
        $this->assertSame('rejected', $payload['statusMap'][$sId1], 'rejected wins on the same step');
        $this->assertStringContainsString('wfdiag-status-rejected', $payload['svg']);
    }

    public function test_unrelated_object_tasks_do_not_pollute_status_map(): void
    {
        $wfId = $this->makeWorkflow();
        $sId1 = $this->makeStep($wfId, 'Step 1', 1);
        $sId2 = $this->makeStep($wfId, 'Step 2', 2);

        // Object 100 finished step 1 — should NOT mark step 1 completed when we view object 200.
        $this->makeTask($wfId, $sId1, objectId: 100, status: 'completed', decision: 'approved');

        $task200 = $this->makeTask($wfId, $sId1, objectId: 200, status: 'pending', decision: 'pending');

        $payload = $this->svc->renderForTask($task200);
        $this->assertSame('current', $payload['statusMap'][$sId1]);   // only this object's task counts
        $this->assertArrayNotHasKey($sId2, $payload['statusMap']);
    }

    public function test_unrelated_workflow_tasks_do_not_pollute_status_map(): void
    {
        $wfA = $this->makeWorkflow();
        $wfB = $this->makeWorkflow();
        $sA1 = $this->makeStep($wfA, 'A1', 1);
        $sB1 = $this->makeStep($wfB, 'B1', 1);

        $this->makeTask($wfB, $sB1, objectId: 42, status: 'completed', decision: 'approved');
        $taskA = $this->makeTask($wfA, $sA1, objectId: 42, status: 'pending', decision: 'pending');

        $payload = $this->svc->renderForTask($taskA);
        // Workflow A's step 1 is current — workflow B's history must not leak in.
        $this->assertSame('current', $payload['statusMap'][$sA1]);
        $this->assertArrayNotHasKey($sB1, $payload['statusMap']);
    }

    private function makeWorkflow(string $name = 'Test workflow'): int
    {
        return (int) DB::table('ahg_workflow')->insertGetId([
            'name'          => $name,
            'scope_type'    => 'global',
            'trigger_event' => 'submit',
            'applies_to'    => 'information_object',
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function makeStep(int $workflowId, string $name, int $order): int
    {
        return (int) DB::table('ahg_workflow_step')->insertGetId([
            'workflow_id'     => $workflowId,
            'name'            => $name,
            'step_order'      => $order,
            'step_type'       => 'review',
            'action_required' => 'approve_reject',
            'is_active'       => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function makeTask(
        int $workflowId,
        int $stepId,
        int $objectId = 1,
        string $status = 'pending',
        string $decision = 'pending',
        string $objectType = 'information_object',
    ): int {
        return (int) DB::table('ahg_workflow_task')->insertGetId([
            'workflow_id'      => $workflowId,
            'workflow_step_id' => $stepId,
            'object_id'        => $objectId,
            'object_type'      => $objectType,
            'status'           => $status,
            'priority'         => 'normal',
            'submitted_by'     => 1,
            'decision'         => $decision,
            'retry_count'      => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }
}
