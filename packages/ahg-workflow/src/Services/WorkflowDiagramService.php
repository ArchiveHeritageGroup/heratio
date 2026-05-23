<?php

/**
 * WorkflowDiagramService - heratio#143 Phase 1: read-only SVG diagram of a workflow.
 *
 * Renders an ahg_workflow + its ahg_workflow_step rows as an SVG flow chart.
 * Pure server-side — no JS, no client library, no CDN dependency. Works in print.
 * Phase 2 layers a `taskStatus` map on top to colour live progress.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgWorkflow\Services;

use Illuminate\Support\Facades\DB;

class WorkflowDiagramService
{
    // Layout constants (px).
    private const NODE_W = 220;
    private const NODE_H = 70;
    private const V_GAP  = 36;
    private const PAD    = 24;
    private const ARROW  = 8;

    /**
     * Render the full diagram for a workflow. Returns inline SVG markup.
     *
     * @param int  $workflowId
     * @param array $taskStatusByStepId Optional map of step_id => 'completed'|'current'|'pending'|'rejected'
     *                                  (Phase 2 uses this; Phase 1 always passes []).
     * @return string SVG markup, or empty-state markup if the workflow has no steps.
     */
    public function render(int $workflowId, array $taskStatusByStepId = []): string
    {
        $workflow = DB::table('ahg_workflow')->where('id', $workflowId)->first();
        if (!$workflow) {
            return $this->emptyState(__('Workflow not found.'));
        }

        $steps = DB::table('ahg_workflow_step')
            ->where('workflow_id', $workflowId)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get(['id', 'name', 'step_order', 'step_type', 'is_optional', 'is_active']);

        if ($steps->isEmpty()) {
            return $this->emptyState(__('This workflow has no steps yet. Add at least one step to see a diagram.'));
        }

        // Group by step_order — steps with the same order render as parallel siblings.
        $rows = [];
        foreach ($steps as $step) {
            $rows[(int) $step->step_order][] = $step;
        }
        ksort($rows);

        $maxParallel = max(array_map('count', $rows));
        $svgW = self::PAD * 2 + ($maxParallel * self::NODE_W) + (($maxParallel - 1) * self::V_GAP);
        $svgH = self::PAD * 2 + (count($rows) * self::NODE_H) + ((count($rows) - 1) * (self::V_GAP + self::ARROW * 2));

        $titleId = 'wf-diagram-title-'.$workflowId;
        $descId  = 'wf-diagram-desc-'.$workflowId;

        $out = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" role="img" aria-labelledby="%s %s" class="workflow-diagram" preserveAspectRatio="xMidYMin meet">',
            $svgW, $svgH, $titleId, $descId
        );
        $out .= sprintf('<title id="%s">%s</title>', $titleId, e($workflow->name));
        $out .= sprintf('<desc id="%s">%s</desc>', $descId, e(__('Visual diagram showing the steps of workflow ":name" and the order they execute.', ['name' => $workflow->name])));

        // Arrowhead marker.
        $out .= '<defs><marker id="wfdiag-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">'
              . '<path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor"/></marker></defs>';

        // Position each step.
        $positions = [];
        $rowIdx = 0;
        foreach ($rows as $order => $rowSteps) {
            $count = count($rowSteps);
            $rowWidth = $count * self::NODE_W + ($count - 1) * self::V_GAP;
            $startX = ($svgW - $rowWidth) / 2;
            $y = self::PAD + $rowIdx * (self::NODE_H + self::V_GAP + self::ARROW * 2);

            foreach ($rowSteps as $i => $step) {
                $x = $startX + $i * (self::NODE_W + self::V_GAP);
                $positions[$step->id] = ['x' => $x, 'y' => $y, 'row' => $rowIdx, 'step' => $step];
            }
            $rowIdx++;
        }

        // Render edges first (so nodes paint on top).
        $rowOrders = array_keys($rows);
        for ($i = 0; $i < count($rowOrders) - 1; $i++) {
            foreach ($rows[$rowOrders[$i]] as $from) {
                foreach ($rows[$rowOrders[$i + 1]] as $to) {
                    $fromPos = $positions[$from->id];
                    $toPos = $positions[$to->id];
                    $x1 = $fromPos['x'] + self::NODE_W / 2;
                    $y1 = $fromPos['y'] + self::NODE_H;
                    $x2 = $toPos['x']   + self::NODE_W / 2;
                    $y2 = $toPos['y'];
                    $out .= sprintf(
                        '<line x1="%d" y1="%d" x2="%d" y2="%d" class="wfdiag-edge" marker-end="url(#wfdiag-arrow)"/>',
                        $x1, $y1, $x2, $y2 - 2
                    );
                }
            }
        }

        // Render nodes.
        foreach ($positions as $pos) {
            $step = $pos['step'];
            $status = $taskStatusByStepId[(int) $step->id] ?? null;
            $cls = 'wfdiag-node';
            $cls .= $step->is_active ? '' : ' wfdiag-inactive';
            $cls .= $step->is_optional ? ' wfdiag-optional' : '';
            if ($status) {
                $cls .= ' wfdiag-status-'.$status;
            }
            $shape = $step->is_optional
                ? sprintf(
                    '<polygon points="%d,%d %d,%d %d,%d %d,%d" class="%s"/>',
                    $pos['x'] + self::NODE_W / 2, $pos['y'],
                    $pos['x'] + self::NODE_W,     $pos['y'] + self::NODE_H / 2,
                    $pos['x'] + self::NODE_W / 2, $pos['y'] + self::NODE_H,
                    $pos['x'],                    $pos['y'] + self::NODE_H / 2,
                    $cls
                )
                : sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" rx="10" ry="10" class="%s"/>',
                    $pos['x'], $pos['y'], self::NODE_W, self::NODE_H, $cls
                );
            $out .= $shape;

            // Order badge — small circle top-left.
            $out .= sprintf(
                '<circle cx="%d" cy="%d" r="11" class="wfdiag-badge"/><text x="%d" y="%d" class="wfdiag-badge-text" text-anchor="middle" dominant-baseline="central">%d</text>',
                $pos['x'] + 14, $pos['y'] + 14,
                $pos['x'] + 14, $pos['y'] + 14,
                (int) $step->step_order
            );

            // Step name (truncated if too long).
            $name = mb_strlen($step->name) > 28 ? mb_substr($step->name, 0, 26).'…' : $step->name;
            $out .= sprintf(
                '<text x="%d" y="%d" class="wfdiag-node-name" text-anchor="middle" dominant-baseline="central">%s</text>',
                $pos['x'] + self::NODE_W / 2,
                $pos['y'] + self::NODE_H / 2 - 6,
                e($name)
            );

            // Step type — smaller subtitle.
            $type = ucwords(str_replace('_', ' ', (string) $step->step_type));
            $out .= sprintf(
                '<text x="%d" y="%d" class="wfdiag-node-type" text-anchor="middle" dominant-baseline="central">%s</text>',
                $pos['x'] + self::NODE_W / 2,
                $pos['y'] + self::NODE_H / 2 + 12,
                e($type)
            );
        }

        $out .= '</svg>';

        return $out;
    }

    /**
     * Return a fallback ordered list — used by the diagram view as a screen-reader
     * alternative and as a graceful fallback if SVG is hidden.
     *
     * @return array<int,string> Ordered list of "1. Step name (type)" strings.
     */
    public function textFallback(int $workflowId): array
    {
        $steps = DB::table('ahg_workflow_step')
            ->where('workflow_id', $workflowId)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get(['name', 'step_order', 'step_type', 'is_optional']);

        $out = [];
        foreach ($steps as $s) {
            $marker = $s->is_optional ? ' '.__('(optional)') : '';
            $out[] = sprintf('%d. %s — %s%s', $s->step_order, $s->name, ucwords(str_replace('_', ' ', (string) $s->step_type)), $marker);
        }
        return $out;
    }

    private function emptyState(string $message): string
    {
        return '<div class="alert alert-info workflow-diagram-empty">'.e($message).'</div>';
    }
}
