<?php

/**
 * WorkflowDiagramTest - heratio#143 Phase 1 service tests.
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

class WorkflowDiagramTest extends TestCase
{
    use DatabaseTransactions;

    private WorkflowDiagramService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new WorkflowDiagramService();
    }

    public function test_render_returns_empty_state_for_nonexistent_workflow(): void
    {
        $html = $this->svc->render(999999999);
        $this->assertStringContainsString('alert-info', $html);
        $this->assertStringContainsString('Workflow not found', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_render_returns_empty_state_when_workflow_has_no_steps(): void
    {
        $wfId = $this->makeWorkflow('Empty workflow');

        $html = $this->svc->render($wfId);
        $this->assertStringContainsString('no steps yet', strtolower($html));
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_render_produces_svg_with_one_node_for_one_step(): void
    {
        $wfId = $this->makeWorkflow('Single-step workflow');
        $this->makeStep($wfId, 'Solo step', 1);

        $html = $this->svc->render($wfId);
        $this->assertStringContainsString('<svg', $html);
        $this->assertSame(1, substr_count($html, '<circle'));   // exactly one node
        $this->assertSame(0, substr_count($html, 'wfdiag-edge'));   // zero edges
        $this->assertStringContainsString('Solo step', $html);
    }

    public function test_render_produces_three_nodes_and_two_edges_for_three_sequential_steps(): void
    {
        $wfId = $this->makeWorkflow('3-step sequential');
        $this->makeStep($wfId, 'Step A', 1);
        $this->makeStep($wfId, 'Step B', 2);
        $this->makeStep($wfId, 'Step C', 3);

        $html = $this->svc->render($wfId);
        $this->assertSame(3, substr_count($html, '<circle'));
        $this->assertSame(2, substr_count($html, 'wfdiag-edge'));
        $this->assertStringContainsString('Step A', $html);
        $this->assertStringContainsString('Step B', $html);
        $this->assertStringContainsString('Step C', $html);
    }

    public function test_optional_step_renders_as_diamond_polygon(): void
    {
        $wfId = $this->makeWorkflow('With optional step');
        $this->makeStep($wfId, 'Required', 1, false);
        $this->makeStep($wfId, 'Maybe',    2, true);   // optional

        $html = $this->svc->render($wfId);
        $this->assertStringContainsString('wfdiag-optional', $html);
        $this->assertStringContainsString('<polygon', $html);   // diamond for optional
        $this->assertStringContainsString('<rect', $html);      // square for required
    }

    public function test_parallel_steps_share_a_row_and_fan_in_fan_out(): void
    {
        $wfId = $this->makeWorkflow('Parallel');
        $this->makeStep($wfId, 'Start',   1);
        $this->makeStep($wfId, 'Branch1', 2);
        $this->makeStep($wfId, 'Branch2', 2);   // same step_order = parallel
        $this->makeStep($wfId, 'Merge',   3);

        $html = $this->svc->render($wfId);
        $this->assertSame(4, substr_count($html, '<circle'));
        // Start -> Branch1, Start -> Branch2, Branch1 -> Merge, Branch2 -> Merge = 4 edges
        $this->assertSame(4, substr_count($html, 'wfdiag-edge'));
    }

    public function test_status_overlay_applies_status_classes_to_nodes(): void
    {
        $wfId = $this->makeWorkflow('Status overlay');
        $sId1 = $this->makeStep($wfId, 'Step 1', 1);
        $sId2 = $this->makeStep($wfId, 'Step 2', 2);
        $sId3 = $this->makeStep($wfId, 'Step 3', 3);

        $html = $this->svc->render($wfId, [
            $sId1 => 'completed',
            $sId2 => 'current',
            $sId3 => 'pending',
        ]);

        $this->assertStringContainsString('wfdiag-status-completed', $html);
        $this->assertStringContainsString('wfdiag-status-current', $html);
        $this->assertStringContainsString('wfdiag-status-pending', $html);
    }

    public function test_text_fallback_lists_steps_in_order(): void
    {
        $wfId = $this->makeWorkflow('Fallback');
        $this->makeStep($wfId, 'First',  1);
        $this->makeStep($wfId, 'Second', 2, true);
        $this->makeStep($wfId, 'Third',  3);

        $lines = $this->svc->textFallback($wfId);
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('First', $lines[0]);
        $this->assertStringContainsString('Second', $lines[1]);
        $this->assertStringContainsString('(optional)', $lines[1]);
        $this->assertStringContainsString('Third', $lines[2]);
    }

    public function test_svg_includes_accessibility_title_and_desc(): void
    {
        $wfId = $this->makeWorkflow('Accessible workflow');
        $this->makeStep($wfId, 'Step', 1);

        $html = $this->svc->render($wfId);
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString('<title', $html);
        $this->assertStringContainsString('<desc', $html);
        $this->assertStringContainsString('Accessible workflow', $html);
    }

    private function makeWorkflow(string $name): int
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

    private function makeStep(int $workflowId, string $name, int $order, bool $optional = false): int
    {
        return (int) DB::table('ahg_workflow_step')->insertGetId([
            'workflow_id'     => $workflowId,
            'name'            => $name,
            'step_order'      => $order,
            'step_type'       => 'review',
            'action_required' => 'approve_reject',
            'is_optional'     => $optional ? 1 : 0,
            'is_active'       => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
