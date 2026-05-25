<?php

/**
 * WorkflowDesignerSaveTest - heratio#143 Phase 3 designer save endpoint tests.
 *
 * Covers the HTTP contract of POST /workflow/{id}/designer/save (without
 * exercising the JS canvas — those would need browser tests).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgWorkflow\Services\WorkflowEdgeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkflowDesignerSaveTest extends TestCase
{
    use DatabaseTransactions;

    private WorkflowEdgeService $edges;

    protected function setUp(): void
    {
        parent::setUp();
        $this->edges = new WorkflowEdgeService;
    }

    public function test_save_endpoint_persists_valid_edges_and_returns_ok(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);

        $resp = $this->withoutMiddleware()->postJson(route('workflow.designer.save', $wfId), [
            'edges' => [
                ['from_step_id' => $s1, 'to_step_id' => $s2],
            ],
        ]);

        $resp->assertOk()->assertJson(['ok' => true, 'written' => 1]);
        $this->assertCount(1, $this->edges->getEdges($wfId));
    }

    public function test_save_endpoint_rejects_cycle_with_422(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);

        $resp = $this->withoutMiddleware()->postJson(route('workflow.designer.save', $wfId), [
            'edges' => [
                ['from_step_id' => $s1, 'to_step_id' => $s2],
                ['from_step_id' => $s2, 'to_step_id' => $s1],
            ],
        ]);

        $resp->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertFalse($this->edges->hasEdges($wfId), 'failed save must not write anything');
    }

    public function test_save_endpoint_404_for_unknown_workflow(): void
    {
        $resp = $this->withoutMiddleware()->postJson(route('workflow.designer.save', 99999999), [
            'edges' => [],
        ]);

        $resp->assertStatus(404)->assertJson(['ok' => false]);
    }

    public function test_save_endpoint_replaces_not_appends(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);
        $s3 = $this->makeStep($wfId, 'C', 3);

        // First save: 2 edges
        $this->withoutMiddleware()->postJson(route('workflow.designer.save', $wfId), [
            'edges' => [
                ['from_step_id' => $s1, 'to_step_id' => $s2],
                ['from_step_id' => $s2, 'to_step_id' => $s3],
            ],
        ])->assertOk();
        $this->assertCount(2, $this->edges->getEdges($wfId));

        // Second save: 1 edge — old two must be gone
        $this->withoutMiddleware()->postJson(route('workflow.designer.save', $wfId), [
            'edges' => [
                ['from_step_id' => $s1, 'to_step_id' => $s3],
            ],
        ])->assertOk();
        $edges = $this->edges->getEdges($wfId);
        $this->assertCount(1, $edges);
        $this->assertSame($s1, (int) $edges->first()->from_step_id);
        $this->assertSame($s3, (int) $edges->first()->to_step_id);
    }

    public function test_save_endpoint_accepts_empty_edges_to_clear(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);
        $this->edges->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s2],
        ]);
        $this->assertTrue($this->edges->hasEdges($wfId));

        $resp = $this->withoutMiddleware()->postJson(route('workflow.designer.save', $wfId), [
            'edges' => [],
        ]);

        $resp->assertOk()->assertJson(['ok' => true, 'written' => 0]);
        $this->assertFalse($this->edges->hasEdges($wfId));
    }

    private function makeWorkflow(string $name = 'Designer Test'): int
    {
        return (int) DB::table('ahg_workflow')->insertGetId([
            'name' => $name,
            'scope_type' => 'global',
            'trigger_event' => 'submit',
            'applies_to' => 'information_object',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeStep(int $workflowId, string $name, int $order): int
    {
        return (int) DB::table('ahg_workflow_step')->insertGetId([
            'workflow_id' => $workflowId,
            'name' => $name,
            'step_order' => $order,
            'step_type' => 'review',
            'action_required' => 'approve_reject',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
