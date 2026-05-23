<?php

/**
 * WorkflowEdgeTest - heratio#143 Phase 3 (edge CRUD + DAG validation) tests.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgWorkflow\Services\WorkflowDiagramService;
use AhgWorkflow\Services\WorkflowEdgeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkflowEdgeTest extends TestCase
{
    use DatabaseTransactions;

    private WorkflowEdgeService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new WorkflowEdgeService();
    }

    public function test_workflow_without_edges_returns_false_for_has_edges(): void
    {
        $wfId = $this->makeWorkflow();
        $this->makeStep($wfId, 'Step 1', 1);

        $this->assertFalse($this->svc->hasEdges($wfId));
        $this->assertTrue($this->svc->getEdges($wfId)->isEmpty());
    }

    public function test_replace_edges_writes_valid_edges_and_can_round_trip(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);
        $s3 = $this->makeStep($wfId, 'C', 3);

        $result = $this->svc->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s2],
            ['from_step_id' => $s2, 'to_step_id' => $s3],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['written']);
        $this->assertTrue($this->svc->hasEdges($wfId));
        $this->assertCount(2, $this->svc->getEdges($wfId));
    }

    public function test_replace_edges_replaces_not_appends(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);
        $s3 = $this->makeStep($wfId, 'C', 3);

        $this->svc->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s2],
            ['from_step_id' => $s2, 'to_step_id' => $s3],
        ]);
        $this->assertCount(2, $this->svc->getEdges($wfId));

        // Now replace with just one — the old two must be gone.
        $this->svc->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s3],
        ]);
        $edges = $this->svc->getEdges($wfId);
        $this->assertCount(1, $edges);
        $this->assertSame($s1, (int) $edges->first()->from_step_id);
        $this->assertSame($s3, (int) $edges->first()->to_step_id);
    }

    public function test_self_loop_is_rejected(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);

        $result = $this->svc->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s1],
        ]);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('self-loop', strtolower($result['errors'][0]));
    }

    public function test_edge_to_foreign_step_is_rejected(): void
    {
        $wfA = $this->makeWorkflow('WA');
        $wfB = $this->makeWorkflow('WB');
        $a1 = $this->makeStep($wfA, 'A1', 1);
        $b1 = $this->makeStep($wfB, 'B1', 1);   // belongs to a different workflow

        $result = $this->svc->replaceEdges($wfA, [
            ['from_step_id' => $a1, 'to_step_id' => $b1],
        ]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('does not belong', strtolower(implode(' ', $result['errors'])));
    }

    public function test_cycle_is_rejected(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);
        $s3 = $this->makeStep($wfId, 'C', 3);

        $result = $this->svc->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s2],
            ['from_step_id' => $s2, 'to_step_id' => $s3],
            ['from_step_id' => $s3, 'to_step_id' => $s1],   // creates a cycle
        ]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('cycle', strtolower(implode(' ', $result['errors'])));
        $this->assertFalse($this->svc->hasEdges($wfId), 'failed replace must not write anything');
    }

    public function test_duplicate_edge_is_rejected(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);

        $result = $this->svc->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s2],
            ['from_step_id' => $s1, 'to_step_id' => $s2],
        ]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('duplicate', strtolower(implode(' ', $result['errors'])));
    }

    public function test_topological_rows_groups_parallel_siblings(): void
    {
        $wfId = $this->makeWorkflow();
        $s1 = $this->makeStep($wfId, 'Start',   1);
        $s2 = $this->makeStep($wfId, 'BranchA', 2);
        $s3 = $this->makeStep($wfId, 'BranchB', 2);
        $s4 = $this->makeStep($wfId, 'Merge',   3);

        $this->svc->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s2],
            ['from_step_id' => $s1, 'to_step_id' => $s3],
            ['from_step_id' => $s2, 'to_step_id' => $s4],
            ['from_step_id' => $s3, 'to_step_id' => $s4],
        ]);

        $rows = $this->svc->topologicalRows($wfId);
        $this->assertCount(3, $rows);
        $this->assertSame([$s1], $rows[0]);
        $this->assertEqualsCanonicalizing([$s2, $s3], $rows[1]);
        $this->assertSame([$s4], $rows[2]);
    }

    public function test_diagram_renderer_uses_explicit_edges_when_present(): void
    {
        $wfId = $this->makeWorkflow('Edge-driven');
        $s1 = $this->makeStep($wfId, 'A', 1);
        $s2 = $this->makeStep($wfId, 'B', 2);
        $s3 = $this->makeStep($wfId, 'C', 3);

        // Just A→C, skipping B entirely (step_order would draw 2 edges; explicit edges draw 1)
        $this->svc->replaceEdges($wfId, [
            ['from_step_id' => $s1, 'to_step_id' => $s3],
        ]);

        $diag = new WorkflowDiagramService();
        $svg = $diag->render($wfId);

        // 3 nodes still rendered (B exists as a step) but only ONE edge (A→C)
        $this->assertSame(3, substr_count($svg, '<circle'));
        $this->assertSame(1, substr_count($svg, 'wfdiag-edge'));
    }

    public function test_diagram_renderer_falls_back_to_step_order_when_no_edges(): void
    {
        $wfId = $this->makeWorkflow('Fallback');
        $this->makeStep($wfId, 'A', 1);
        $this->makeStep($wfId, 'B', 2);
        $this->makeStep($wfId, 'C', 3);

        $diag = new WorkflowDiagramService();
        $svg = $diag->render($wfId);

        $this->assertFalse($this->svc->hasEdges($wfId));
        $this->assertSame(3, substr_count($svg, '<circle'));
        $this->assertSame(2, substr_count($svg, 'wfdiag-edge'));   // linear A→B→C
    }

    private function makeWorkflow(string $name = 'Test'): int
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
}
