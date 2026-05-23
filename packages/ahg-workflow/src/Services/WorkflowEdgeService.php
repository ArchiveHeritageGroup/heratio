<?php

/**
 * WorkflowEdgeService - heratio#143 Phase 3: edge CRUD + DAG validation.
 *
 * Edges in ahg_workflow_edge model branching/parallel workflows. When edges
 * exist for a workflow, the diagram renderer uses them; otherwise the
 * renderer falls back to ahg_workflow_step.step_order (linear).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgWorkflow\Services;

use Illuminate\Support\Facades\DB;

class WorkflowEdgeService
{
    /**
     * Fetch all edges for a workflow.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function getEdges(int $workflowId)
    {
        return DB::table('ahg_workflow_edge')
            ->where('workflow_id', $workflowId)
            ->orderBy('from_step_id')
            ->orderBy('to_step_id')
            ->get(['id', 'from_step_id', 'to_step_id', 'condition_expr']);
    }

    /**
     * Does this workflow have any edges defined? Determines whether the
     * diagram should use the graph or fall back to step_order layout.
     */
    public function hasEdges(int $workflowId): bool
    {
        return DB::table('ahg_workflow_edge')->where('workflow_id', $workflowId)->exists();
    }

    /**
     * Replace ALL edges for a workflow with the supplied set. Validates that:
     *   - all step ids reference steps in this workflow
     *   - the resulting graph is a DAG (no cycles)
     *   - no self-loops
     *
     * @param  int   $workflowId
     * @param  array $edges Array of ['from_step_id' => int, 'to_step_id' => int, 'condition_expr' => ?string]
     * @return array{ok:bool, errors:array<int,string>, written:int}
     */
    public function replaceEdges(int $workflowId, array $edges): array
    {
        $errors = $this->validate($workflowId, $edges);
        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors, 'written' => 0];
        }

        $written = 0;
        DB::transaction(function () use ($workflowId, $edges, &$written) {
            DB::table('ahg_workflow_edge')->where('workflow_id', $workflowId)->delete();
            foreach ($edges as $e) {
                DB::table('ahg_workflow_edge')->insert([
                    'workflow_id'    => $workflowId,
                    'from_step_id'   => (int) $e['from_step_id'],
                    'to_step_id'     => (int) $e['to_step_id'],
                    'condition_expr' => $e['condition_expr'] ?? null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $written++;
            }
        });

        return ['ok' => true, 'errors' => [], 'written' => $written];
    }

    /**
     * Validate proposed edges WITHOUT writing. Returns an array of error
     * messages (empty = valid).
     *
     * @return array<int,string>
     */
    public function validate(int $workflowId, array $edges): array
    {
        $errors = [];
        $stepIds = DB::table('ahg_workflow_step')
            ->where('workflow_id', $workflowId)
            ->pluck('id')
            ->map(fn ($i) => (int) $i)
            ->all();
        $stepSet = array_flip($stepIds);

        $seen = [];
        foreach ($edges as $i => $e) {
            $from = (int) ($e['from_step_id'] ?? 0);
            $to = (int) ($e['to_step_id'] ?? 0);

            if ($from <= 0 || $to <= 0) {
                $errors[] = "Edge #{$i}: from_step_id and to_step_id are required.";
                continue;
            }
            if ($from === $to) {
                $errors[] = "Edge #{$i}: self-loop is not allowed (step #{$from} → step #{$from}).";
                continue;
            }
            if (!isset($stepSet[$from])) {
                $errors[] = "Edge #{$i}: from_step_id {$from} does not belong to this workflow.";
                continue;
            }
            if (!isset($stepSet[$to])) {
                $errors[] = "Edge #{$i}: to_step_id {$to} does not belong to this workflow.";
                continue;
            }
            $key = $from.'->'.$to;
            if (isset($seen[$key])) {
                $errors[] = "Edge #{$i}: duplicate of an earlier edge (step #{$from} → step #{$to}).";
                continue;
            }
            $seen[$key] = true;
        }

        if (!empty($errors)) {
            return $errors;
        }

        // Cycle detection — DFS-based.
        $adj = [];
        foreach ($edges as $e) {
            $adj[(int) $e['from_step_id']][] = (int) $e['to_step_id'];
        }
        $visited = [];
        $onStack = [];
        foreach ($stepIds as $stepId) {
            if ($this->hasCycle($stepId, $adj, $visited, $onStack)) {
                $errors[] = "Graph contains a cycle. Workflows must be a DAG (no loops back to earlier steps).";
                break;
            }
        }

        return $errors;
    }

    /**
     * DFS-based cycle detection. Returns true if a cycle is reachable from $node.
     *
     * @param array<int,array<int,int>> $adj
     * @param array<int,bool> $visited
     * @param array<int,bool> $onStack
     */
    private function hasCycle(int $node, array $adj, array &$visited, array &$onStack): bool
    {
        if (isset($onStack[$node])) {
            return true;
        }
        if (isset($visited[$node])) {
            return false;
        }

        $visited[$node] = true;
        $onStack[$node] = true;

        foreach ($adj[$node] ?? [] as $next) {
            if ($this->hasCycle($next, $adj, $visited, $onStack)) {
                return true;
            }
        }

        unset($onStack[$node]);
        return false;
    }

    /**
     * Find the "rows" for diagram layout when edges are present.
     * Performs a topological ordering and groups nodes by their rank (longest
     * path from a root). Nodes in the same rank render in parallel.
     *
     * @param int $workflowId
     * @return array<int,array<int,int>> rank => [step_id, step_id, ...]
     */
    public function topologicalRows(int $workflowId): array
    {
        $stepIds = DB::table('ahg_workflow_step')
            ->where('workflow_id', $workflowId)
            ->orderBy('step_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($i) => (int) $i)
            ->all();

        $edges = $this->getEdges($workflowId);

        // Build adjacency + in-degree maps.
        $adj = [];
        $indeg = array_fill_keys($stepIds, 0);
        foreach ($edges as $e) {
            $from = (int) $e->from_step_id;
            $to = (int) $e->to_step_id;
            $adj[$from][] = $to;
            $indeg[$to] = ($indeg[$to] ?? 0) + 1;
        }

        // Rank = longest path from any root.
        $rank = array_fill_keys($stepIds, 0);
        $queue = [];
        foreach ($stepIds as $id) {
            if (($indeg[$id] ?? 0) === 0) {
                $queue[] = $id;
            }
        }

        while (!empty($queue)) {
            $node = array_shift($queue);
            foreach ($adj[$node] ?? [] as $next) {
                $rank[$next] = max($rank[$next], $rank[$node] + 1);
                $indeg[$next]--;
                if ($indeg[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        $rows = [];
        foreach ($rank as $stepId => $r) {
            $rows[$r][] = $stepId;
        }
        ksort($rows);

        return $rows;
    }
}
