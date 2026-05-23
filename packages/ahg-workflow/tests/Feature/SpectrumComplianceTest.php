<?php

/**
 * SpectrumComplianceTest - heratio Spectrum Phase C tests.
 *
 * Covers: status derivation, heatmap aggregation, chain rules, overdue scan.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgWorkflow\Services\SpectrumComplianceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpectrumComplianceTest extends TestCase
{
    use DatabaseTransactions;

    private SpectrumComplianceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SpectrumComplianceService();
    }

    public function test_compute_status_not_started_when_no_task(): void
    {
        $state = $this->svc->computeStatus(999999, 'object_entry');
        $this->assertSame(SpectrumComplianceService::STATUS_NOT_STARTED, $state['status']);
        $this->assertNull($state['started_at']);
        $this->assertNull($state['completed_at']);
        $this->assertNull($state['last_task_id']);
    }

    public function test_compute_status_in_progress_when_task_pending(): void
    {
        $wfId = $this->makeSpectrumWorkflow('object_entry');
        $sId = $this->makeStep($wfId, 'Step 1', 1);
        $this->makeTask($wfId, $sId, 42, 'pending', 'pending');

        $state = $this->svc->computeStatus(42, 'object_entry');
        $this->assertSame(SpectrumComplianceService::STATUS_IN_PROGRESS, $state['status']);
        $this->assertNotNull($state['started_at']);
        $this->assertNull($state['completed_at']);
    }

    public function test_compute_status_completed_when_task_approved(): void
    {
        $wfId = $this->makeSpectrumWorkflow('cataloguing');
        $sId = $this->makeStep($wfId, 'Step 1', 1);
        $this->makeTask($wfId, $sId, 42, 'completed', 'approved');

        $state = $this->svc->computeStatus(42, 'cataloguing');
        $this->assertSame(SpectrumComplianceService::STATUS_COMPLETED, $state['status']);
        $this->assertNotNull($state['completed_at']);
    }

    public function test_compute_status_rejected_overrides_other_statuses(): void
    {
        $wfId = $this->makeSpectrumWorkflow('audit');
        $sId = $this->makeStep($wfId, 'Step 1', 1);
        // First task approved, second rejected — rejection wins
        $this->makeTask($wfId, $sId, 42, 'completed', 'approved');
        $this->makeTask($wfId, $sId, 42, 'completed', 'rejected');

        $state = $this->svc->computeStatus(42, 'audit');
        $this->assertSame(SpectrumComplianceService::STATUS_REJECTED, $state['status']);
    }

    public function test_compute_status_overdue_with_threshold(): void
    {
        $wfId = $this->makeSpectrumWorkflow('inventory');
        $sId = $this->makeStep($wfId, 'Step 1', 1);
        // Task pending, created 60 days ago — should be overdue with threshold 30
        $now = now();
        DB::table('ahg_workflow_task')->insert([
            'workflow_id' => $wfId, 'workflow_step_id' => $sId, 'object_id' => 42,
            'object_type' => 'information_object', 'status' => 'pending', 'priority' => 'normal',
            'submitted_by' => 1, 'decision' => 'pending', 'retry_count' => 0,
            'created_at' => $now->copy()->subDays(60), 'updated_at' => $now,
        ]);

        $state = $this->svc->computeStatus(42, 'inventory', 'information_object', 30);
        $this->assertSame(SpectrumComplianceService::STATUS_OVERDUE, $state['status']);

        // With higher threshold it shouldn't be overdue
        $state = $this->svc->computeStatus(42, 'inventory', 'information_object', 90);
        $this->assertSame(SpectrumComplianceService::STATUS_IN_PROGRESS, $state['status']);
    }

    public function test_object_summary_returns_all_21_procedures(): void
    {
        $summary = $this->svc->objectSummary(42);
        $this->assertCount(21, $summary);
        $this->assertArrayHasKey('object_entry', $summary);
        $this->assertArrayHasKey('cataloguing', $summary);
        $this->assertSame(SpectrumComplianceService::STATUS_NOT_STARTED, $summary['object_entry']['status']);
    }

    public function test_heatmap_returns_all_21_procedures_with_status_totals(): void
    {
        $heatmap = $this->svc->heatmap();
        $this->assertCount(21, $heatmap);
        foreach ($heatmap as $code => $row) {
            $this->assertArrayHasKey('label', $row);
            $this->assertArrayHasKey('totals', $row);
            $this->assertArrayHasKey('not_started', $row['totals']);
            $this->assertArrayHasKey('completed', $row['totals']);
            $this->assertArrayHasKey('percent_completed', $row);
        }
    }

    public function test_recompute_object_writes_cache_rows(): void
    {
        $count = $this->svc->recomputeObject(42);
        $this->assertSame(21, $count);
        $this->assertSame(21, DB::table('ahg_spectrum_object_compliance')->where('object_id', 42)->count());
    }

    public function test_chain_rule_save_and_delete(): void
    {
        $id = $this->svc->saveChainRule([
            'from_procedure' => 'acquisition',
            'to_procedure'   => 'cataloguing',
            'trigger_event'  => 'on_complete',
            'is_active'      => true,
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, $this->svc->getChainRules()->count());

        $this->assertTrue($this->svc->deleteChainRule($id));
        $this->assertSame(0, $this->svc->getChainRules()->count());
    }

    public function test_chain_rule_rejects_same_from_and_to(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->saveChainRule([
            'from_procedure' => 'cataloguing',
            'to_procedure'   => 'cataloguing',
        ]);
    }

    public function test_chain_rule_rejects_unknown_procedure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->saveChainRule([
            'from_procedure' => 'parsecs',   // invalid
            'to_procedure'   => 'cataloguing',
        ]);
    }

    public function test_apply_chain_spawns_downstream_task(): void
    {
        // Two workflows: acquisition + cataloguing
        $acqId = $this->makeSpectrumWorkflow('acquisition');
        $acqStep = $this->makeStep($acqId, 'Acq step', 1);
        $catId = $this->makeSpectrumWorkflow('cataloguing');
        $catStep = $this->makeStep($catId, 'Cat step', 1);

        // Approved acquisition task
        $taskId = $this->makeTask($acqId, $acqStep, 42, 'completed', 'approved');

        // Chain rule
        $this->svc->saveChainRule([
            'from_procedure' => 'acquisition',
            'to_procedure'   => 'cataloguing',
            'trigger_event'  => 'on_complete',
            'is_active'      => true,
        ]);

        $before = DB::table('ahg_workflow_task')->where('workflow_id', $catId)->count();
        $result = $this->svc->applyChainOnTaskApproved($taskId);
        $after = DB::table('ahg_workflow_task')->where('workflow_id', $catId)->count();

        $this->assertSame(1, $result['spawned']);
        $this->assertSame($before + 1, $after);
    }

    public function test_apply_chain_does_not_double_spawn(): void
    {
        $acqId = $this->makeSpectrumWorkflow('acquisition');
        $acqStep = $this->makeStep($acqId, 'A', 1);
        $catId = $this->makeSpectrumWorkflow('cataloguing');
        $catStep = $this->makeStep($catId, 'C', 1);

        $taskId = $this->makeTask($acqId, $acqStep, 42, 'completed', 'approved');
        $this->svc->saveChainRule([
            'from_procedure' => 'acquisition', 'to_procedure' => 'cataloguing',
            'trigger_event' => 'on_complete', 'is_active' => true,
        ]);

        $this->svc->applyChainOnTaskApproved($taskId);
        $r2 = $this->svc->applyChainOnTaskApproved($taskId);
        $this->assertSame(0, $r2['spawned'], 'second apply must not double-spawn');
    }

    public function test_find_overdue_returns_old_pending_tasks(): void
    {
        $wfId = $this->makeSpectrumWorkflow('reproduction');
        $sId = $this->makeStep($wfId, 'Step 1', 1);
        $now = now();
        // Old pending — should be overdue
        DB::table('ahg_workflow_task')->insert([
            'workflow_id' => $wfId, 'workflow_step_id' => $sId, 'object_id' => 1001,
            'object_type' => 'information_object', 'status' => 'pending', 'priority' => 'normal',
            'submitted_by' => 1, 'decision' => 'pending', 'retry_count' => 0,
            'created_at' => $now->copy()->subDays(30), 'updated_at' => $now,
        ]);
        // Recent pending — should NOT be overdue
        DB::table('ahg_workflow_task')->insert([
            'workflow_id' => $wfId, 'workflow_step_id' => $sId, 'object_id' => 1002,
            'object_type' => 'information_object', 'status' => 'pending', 'priority' => 'normal',
            'submitted_by' => 1, 'decision' => 'pending', 'retry_count' => 0,
            'created_at' => $now->copy()->subDays(5), 'updated_at' => $now,
        ]);

        $overdue = $this->svc->findOverdue(14);
        $ids = array_map(fn ($r) => (int) $r->object_id, $overdue);
        $this->assertContains(1001, $ids);
        $this->assertNotContains(1002, $ids);
    }

    // -------- helpers --------

    private function makeSpectrumWorkflow(string $procedure): int
    {
        return (int) DB::table('ahg_workflow')->insertGetId([
            'name'               => 'Spectrum: '.$procedure,
            'scope_type'         => 'global',
            'trigger_event'      => 'submit',
            'applies_to'         => 'information_object',
            'is_active'          => 1,
            'spectrum_procedure' => $procedure,
            'created_at'         => now(),
            'updated_at'         => now(),
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

    private function makeTask(int $workflowId, int $stepId, int $objectId, string $status, string $decision): int
    {
        return (int) DB::table('ahg_workflow_task')->insertGetId([
            'workflow_id'      => $workflowId,
            'workflow_step_id' => $stepId,
            'object_id'        => $objectId,
            'object_type'      => 'information_object',
            'status'           => $status,
            'priority'         => 'normal',
            'submitted_by'     => 1,
            'decision'         => $decision,
            'decision_at'      => $decision === 'approved' || $decision === 'rejected' ? now() : null,
            'retry_count'      => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }
}
