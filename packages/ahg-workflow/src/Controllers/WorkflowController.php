<?php

/**
 * WorkflowController - Controller for Heratio
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



namespace AhgWorkflow\Controllers;

use AhgWorkflow\Services\SpectrumComplianceService;
use AhgWorkflow\Services\SpectrumProcedureCatalog;
use AhgWorkflow\Services\WorkflowDiagramService;
use AhgWorkflow\Services\WorkflowEdgeService;
use AhgWorkflow\Services\WorkflowService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    protected WorkflowService $service;

    public function __construct(WorkflowService $service)
    {
        $this->service = $service;
    }

    /**
     * Workflow dashboard with stats, tasks, and recent activity.
     */
    public function dashboard()
    {
        $userId = auth()->id();
        $stats = $this->service->getStats($userId);
        $myTasks = $this->service->getMyTasks($userId);
        $poolTasks = $this->service->getPoolTasks($userId);
        $recentHistory = $this->service->getHistory(20);

        return view('ahg-workflow::dashboard', [
            'stats' => $stats,
            'myTasks' => $myTasks,
            'poolTasks' => $poolTasks,
            'recentHistory' => $recentHistory,
        ]);
    }

    /**
     * My tasks with optional status filter.
     */
    public function myTasks(Request $request)
    {
        $userId = auth()->id();
        $status = $request->get('status');
        $tasks = $this->service->getMyTasks($userId, $status);

        return view('ahg-workflow::my-tasks', [
            'tasks' => $tasks,
            'currentStatus' => $status,
        ]);
    }

    /**
     * Pool tasks available to claim.
     */
    public function pool()
    {
        $userId = auth()->id();
        $tasks = $this->service->getPoolTasks($userId);

        return view('ahg-workflow::pool', [
            'tasks' => $tasks,
        ]);
    }

    /**
     * View a single task with full details.
     */
    public function viewTask(int $id)
    {
        $task = $this->service->getTask($id);

        if (!$task) {
            abort(404, 'Task not found');
        }

        return view('ahg-workflow::view-task', [
            'task' => $task,
        ]);
    }

    /**
     * Claim a task.
     */
    public function claimTask(int $id)
    {
        $userId = auth()->id();
        $result = $this->service->claimTask($id, $userId);

        if ($result) {
            return redirect()->route('workflow.task', $id)->with('success', 'Task claimed successfully.');
        }

        return redirect()->route('workflow.pool')->with('error', 'Unable to claim task. It may have already been claimed.');
    }

    /**
     * Release a task back to the pool.
     */
    public function releaseTask(Request $request, int $id)
    {
        $userId = auth()->id();
        $comment = $request->input('comment');
        $result = $this->service->releaseTask($id, $userId, $comment);

        if ($result) {
            return redirect()->route('workflow.pool')->with('success', 'Task released back to pool.');
        }

        return redirect()->route('workflow.task', $id)->with('error', 'Unable to release task.');
    }

    /**
     * Approve a task.
     */
    public function approveTask(Request $request, int $id)
    {
        $request->validate([
            'comment' => 'nullable|string|max:5000',
        ]);

        $userId = auth()->id();
        $result = $this->service->approveTask($id, $userId, $request->input('comment'));

        if ($result) {
            // Spectrum Phase C2 — apply cross-procedure chain rules
            try {
                $svc = new SpectrumComplianceService();
                $chain = $svc->applyChainOnTaskApproved($id);
                if (($chain['spawned'] ?? 0) > 0) {
                    return redirect()->route('workflow.my-tasks')->with('success', "Task approved. Spectrum chain spawned {$chain['spawned']} downstream task(s).");
                }
            } catch (\Throwable $e) {
                // chain spawn is best-effort — don't block approval
            }
            return redirect()->route('workflow.my-tasks')->with('success', 'Task approved successfully.');
        }

        return redirect()->route('workflow.task', $id)->with('error', 'Unable to approve task.');
    }

    /**
     * Reject a task.
     */
    public function rejectTask(Request $request, int $id)
    {
        $request->validate([
            'comment' => 'required|string|max:5000',
        ]);

        $userId = auth()->id();
        $result = $this->service->rejectTask($id, $userId, $request->input('comment'));

        if ($result) {
            return redirect()->route('workflow.my-tasks')->with('success', 'Task rejected.');
        }

        return redirect()->route('workflow.task', $id)->with('error', 'Unable to reject task.');
    }

    /**
     * Activity log / history.
     */
    public function history()
    {
        $history = $this->service->getHistory(200);

        return view('ahg-workflow::history', [
            'history' => $history,
        ]);
    }

    /**
     * Queue overview.
     */
    public function queues()
    {
        $queues = $this->service->getQueues();

        return view('ahg-workflow::queues', [
            'queues' => $queues,
        ]);
    }

    /**
     * Overdue tasks.
     */
    public function overdue(Request $request)
    {
        $userId = $request->get('user_id');
        $queueId = $request->get('queue_id');
        $tasks = $this->service->getOverdueTasks($userId ? (int) $userId : null, $queueId ? (int) $queueId : null);

        return view('ahg-workflow::overdue', [
            'tasks' => $tasks,
            'filterUserId' => $userId,
            'filterQueueId' => $queueId,
        ]);
    }

    /**
     * Admin: list workflows.
     */
    public function admin(Request $request)
    {
        // Spectrum#A — optional filter to find workflows for a given procedure
        $spectrumFilter = $request->query('spectrum');
        if ($spectrumFilter !== null) {
            $spectrumFilter = SpectrumProcedureCatalog::normalize($spectrumFilter);   // null if invalid
        }

        $workflows = $this->service->getWorkflows($spectrumFilter);

        return view('ahg-workflow::admin', [
            'workflows' => $workflows,
            'workflowRequiredForPublish' => $this->service->isWorkflowRequiredForPublish(),
            'spectrumProcedures' => SpectrumProcedureCatalog::all(),
            'spectrumFilter' => $spectrumFilter,
        ]);
    }

    /**
     * Save workflow settings (e.g. require approval before publish).
     */
    public function saveSettings(Request $request)
    {
        $enabled = $request->has('workflow_required_for_publish') ? '1' : '0';

        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => 'workflow_required_for_publish'],
            ['setting_value' => $enabled, 'setting_group' => 'workflow']
        );

        return redirect()->route('workflow.admin')->with('success', 'Workflow settings saved.');
    }

    /**
     * Admin: create workflow (GET/POST).
     */
    public function createWorkflow(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'scope_type' => 'required|string|max:50',
                'trigger_event' => 'required|string|max:50',
                'applies_to' => 'required|string|max:50',
            ]);

            $data = $request->only([
                'name', 'description', 'scope_type', 'scope_id', 'trigger_event',
                'applies_to', 'is_active', 'is_default', 'require_all_steps',
                'allow_parallel', 'auto_archive_days', 'notification_enabled',
            ]);
            $data['is_active'] = $request->has('is_active') ? 1 : 0;
            $data['is_default'] = $request->has('is_default') ? 1 : 0;
            $data['require_all_steps'] = $request->has('require_all_steps') ? 1 : 0;
            $data['allow_parallel'] = $request->has('allow_parallel') ? 1 : 0;
            $data['notification_enabled'] = $request->has('notification_enabled') ? 1 : 0;
            $data['created_by'] = auth()->id();

            $id = $this->service->createWorkflow($data);

            return redirect()->route('workflow.admin.edit', $id)->with('success', 'Workflow created. Now add steps.');
        }

        return view('ahg-workflow::create-workflow');
    }

    /**
     * Admin: edit workflow (GET/POST) with steps.
     */
    public function editWorkflow(Request $request, int $id)
    {
        $workflow = $this->service->getWorkflow($id);

        if (!$workflow) {
            abort(404, 'Workflow not found');
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'scope_type' => 'required|string|max:50',
                'trigger_event' => 'required|string|max:50',
                'applies_to' => 'required|string|max:50',
            ]);

            $data = $request->only([
                'name', 'description', 'scope_type', 'scope_id', 'trigger_event',
                'applies_to', 'auto_archive_days', 'spectrum_procedure',
            ]);
            $data['is_active'] = $request->has('is_active') ? 1 : 0;
            $data['is_default'] = $request->has('is_default') ? 1 : 0;
            $data['require_all_steps'] = $request->has('require_all_steps') ? 1 : 0;
            $data['allow_parallel'] = $request->has('allow_parallel') ? 1 : 0;
            $data['notification_enabled'] = $request->has('notification_enabled') ? 1 : 0;

            $this->service->updateWorkflow($id, $data);

            return redirect()->route('workflow.admin.edit', $id)->with('success', 'Workflow updated.');
        }

        return view('ahg-workflow::edit-workflow', [
            'workflow' => $workflow,
            'spectrumProcedures' => SpectrumProcedureCatalog::all(),
        ]);
    }

    /**
     * heratio#143 Phase 1 — read-only visual diagram of a workflow.
     */
    public function diagram(int $id)
    {
        $workflow = $this->service->getWorkflow($id);
        if (!$workflow) {
            abort(404, 'Workflow not found');
        }

        $svc = new WorkflowDiagramService();
        return view('ahg-workflow::diagram', [
            'workflow' => $workflow,
            'svg'      => $svc->render((int) $id),
            'fallback' => $svc->textFallback((int) $id),
            'spectrumLabel' => SpectrumProcedureCatalog::label($workflow->spectrum_procedure ?? null),
        ]);
    }

    /**
     * Spectrum#B — install (or re-install) the Spectrum 5.1 procedure pack via
     * the Artisan command. Idempotent default; --overwrite is opt-in via
     * the form checkbox.
     */
    public function installSpectrumPack(Request $request)
    {
        $overwrite = $request->boolean('overwrite');
        $args = [];
        if ($overwrite) {
            $args['--overwrite'] = true;
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('workflow:seed-spectrum', $args);
            $output = \Illuminate\Support\Facades\Artisan::output();
            return redirect()->route('workflow.admin')->with('success', 'Spectrum procedure pack installed. ' . trim(strrchr($output, "\n") ?: $output));
        } catch (\Throwable $e) {
            return redirect()->route('workflow.admin')->with('error', 'Install failed: ' . $e->getMessage());
        }
    }

    /**
     * heratio#143 Phase 3 — drag-drop designer canvas.
     */
    public function designer(int $id)
    {
        $workflow = $this->service->getWorkflow($id);
        if (!$workflow) {
            abort(404, 'Workflow not found');
        }

        $steps = DB::table('ahg_workflow_step')
            ->where('workflow_id', $id)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get(['id', 'name', 'step_order', 'step_type', 'is_optional']);

        $edges = (new WorkflowEdgeService())->getEdges($id);

        return view('ahg-workflow::designer', [
            'workflow' => $workflow,
            'steps'    => $steps,
            'edges'    => $edges,
        ]);
    }

    /**
     * heratio#143 Phase 3 — AJAX save endpoint. Replaces ALL edges for a
     * workflow with the supplied set, after DAG validation.
     */
    public function designerSave(Request $request, int $id)
    {
        $workflow = $this->service->getWorkflow($id);
        if (!$workflow) {
            return response()->json(['ok' => false, 'errors' => ['Workflow not found.']], 404);
        }

        $raw = $request->input('edges', []);
        if (!is_array($raw)) {
            return response()->json(['ok' => false, 'errors' => ['edges must be an array.']], 422);
        }

        $edges = [];
        foreach ($raw as $e) {
            if (!is_array($e)) {
                continue;
            }
            $edges[] = [
                'from_step_id'   => (int) ($e['from_step_id'] ?? 0),
                'to_step_id'     => (int) ($e['to_step_id'] ?? 0),
                'condition_expr' => isset($e['condition_expr']) ? (string) $e['condition_expr'] : null,
            ];
        }

        $result = (new WorkflowEdgeService())->replaceEdges($id, $edges);
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * heratio#143 Phase 2 — task progress overlay on the workflow diagram.
     */
    public function taskDiagram(int $taskId)
    {
        $task = $this->service->getTask($taskId);
        if (!$task) {
            abort(404, 'Task not found');
        }

        $svc = new WorkflowDiagramService();
        $payload = $svc->renderForTask($taskId);
        $workflow = $this->service->getWorkflow((int) $task->workflow_id);

        return view('ahg-workflow::task-diagram', [
            'task'      => $task,
            'workflow'  => $workflow,
            'svg'       => $payload['svg'],
            'statusMap' => $payload['statusMap'],
            'fallback'  => $svc->textFallback((int) $task->workflow_id),
            'spectrumLabel' => SpectrumProcedureCatalog::label($workflow->spectrum_procedure ?? null),
        ]);
    }

    /**
     * Admin: delete workflow.
     */
    public function deleteWorkflow(int $id)
    {
        $this->service->deleteWorkflow($id);

        return redirect()->route('workflow.admin')->with('success', 'Workflow deleted.');
    }

    /**
     * Admin: add step to a workflow.
     */
    public function addStep(Request $request, int $workflowId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'step_type' => 'required|string|max:50',
            'action_required' => 'required|string|max:50',
        ]);

        $data = $request->only([
            'name', 'description', 'step_order', 'step_type', 'action_required',
            'required_role_id', 'required_clearance_level', 'allowed_group_ids',
            'allowed_user_ids', 'auto_assign_user_id', 'escalation_days',
            'escalation_user_id', 'notification_template', 'instructions',
            'checklist',
        ]);
        $data['pool_enabled'] = $request->has('pool_enabled') ? 1 : 0;
        $data['is_optional'] = $request->has('is_optional') ? 1 : 0;
        $data['is_active'] = $request->has('is_active') ? 1 : 0;

        $this->service->addStep($workflowId, $data);

        return redirect()->route('workflow.admin.edit', $workflowId)->with('success', 'Step added.');
    }

    /**
     * Admin: delete step.
     */
    public function deleteStep(int $id)
    {
        $step = \Illuminate\Support\Facades\DB::table('ahg_workflow_step')->where('id', $id)->first();

        if (!$step) {
            abort(404, 'Step not found');
        }

        $this->service->deleteStep($id);

        return redirect()->route('workflow.admin.edit', $step->workflow_id)->with('success', 'Step deleted.');
    }

    /**
     * Publish readiness: evaluate gates for an object.
     */
    public function publishReadiness(int $objectId)
    {
        $evaluation = $this->service->evaluateGates($objectId);

        if (!$evaluation['object']) {
            abort(404, 'Object not found');
        }

        return view('ahg-workflow::publish-readiness', [
            'evaluation' => $evaluation,
            'objectId' => $objectId,
        ]);
    }

    /**
     * Admin: list gate rules.
     */
    public function gateAdmin()
    {
        $rules = $this->service->getGateRules();

        return view('ahg-workflow::gate-admin', [
            'rules' => $rules,
        ]);
    }

    /**
     * Admin: edit/create gate rule (GET/POST).
     */
    public function gateRuleEdit(Request $request, ?int $id = null)
    {
        $rule = $id ? $this->service->getGateRule($id) : null;

        if ($request->isMethod('post')) {
            $request->validate([
                'name' => 'required|string|max:255',
                'rule_type' => 'required|string|max:100',
                'error_message' => 'required|string|max:500',
                'severity' => 'required|string|max:50',
            ]);

            $data = $request->only([
                'name', 'rule_type', 'entity_type', 'level_of_description_id',
                'material_type', 'repository_id', 'field_name', 'rule_config',
                'error_message', 'severity', 'sort_order',
            ]);
            $data['is_active'] = $request->has('is_active') ? 1 : 0;

            $savedId = $this->service->saveGateRule($data, $id);

            return redirect()->route('workflow.gates.admin')->with('success', $id ? 'Gate rule updated.' : 'Gate rule created.');
        }

        return view('ahg-workflow::gate-edit', [
            'rule' => $rule,
        ]);
    }

    /**
     * Admin: delete gate rule.
     */
    public function deleteGateRule(int $id)
    {
        $this->service->deleteGateRule($id);

        return redirect()->route('workflow.gates.admin')->with('success', 'Gate rule deleted.');
    }

    public function addStepForm(Request $request, int $workflowId) { return view('ahg-workflow::add-step', ['record' => (object)[]]); }

    public function editStepForm(Request $request, int $id) { return view('ahg-workflow::edit-step', ['record' => (object)['id'=>$id]]); }

    public function bulkPreview(Request $request) { return view('ahg-workflow::bulk-preview', ['rows' => collect()]); }

    public function myWork(Request $request) { return view('ahg-workflow::my-work', ['rows' => collect()]); }

    public function publishSimulate(int $objectId) { return view('ahg-workflow::publish-simulate', ['gates' => collect(), 'allPassed' => false]); }

    public function teamWork(Request $request) { return view('ahg-workflow::team-work', ['rows' => collect()]); }

    public function timeline(int $id) { return view('ahg-workflow::timeline', ['events' => collect()]); }

    // =========================================================================
    // Spectrum Phase C — compliance dashboard, chain rules, per-object, export
    // =========================================================================

    public function spectrumDashboard(Request $request)
    {
        $svc = new SpectrumComplianceService();
        $overdueDays = (int) $request->input('overdue_days', 30);
        return view('ahg-workflow::spectrum-dashboard', [
            'heatmap'     => $svc->heatmap('information_object', $overdueDays),
            'overdueDays' => $overdueDays,
            'statuses'    => SpectrumComplianceService::STATUSES,
        ]);
    }

    public function spectrumExportCsv(Request $request)
    {
        $svc = new SpectrumComplianceService();
        $overdueDays = (int) $request->input('overdue_days', 30);
        $heatmap = $svc->heatmap('information_object', $overdueDays);

        $filename = 'spectrum_compliance_'.date('Y-m-d').'.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($heatmap) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['procedure_code', 'procedure', 'total_objects', 'not_started', 'in_progress', 'completed', 'overdue', 'rejected', 'percent_completed']);
            foreach ($heatmap as $code => $row) {
                fputcsv($out, [
                    $code,
                    $row['label'],
                    $row['total_objects'],
                    $row['totals']['not_started'],
                    $row['totals']['in_progress'],
                    $row['totals']['completed'],
                    $row['totals']['overdue'],
                    $row['totals']['rejected'],
                    $row['percent_completed'],
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function spectrumChainRules()
    {
        $svc = new SpectrumComplianceService();
        return view('ahg-workflow::spectrum-chain-rules', [
            'rules'      => $svc->getChainRules(),
            'procedures' => SpectrumProcedureCatalog::all(),
        ]);
    }

    public function spectrumChainSave(Request $request)
    {
        $svc = new SpectrumComplianceService();
        try {
            $svc->saveChainRule([
                'id'             => $request->input('id'),
                'from_procedure' => $request->input('from_procedure'),
                'to_procedure'   => $request->input('to_procedure'),
                'trigger_event'  => $request->input('trigger_event', 'on_complete'),
                'is_active'      => $request->has('is_active'),
                'notes'          => $request->input('notes'),
            ]);
            return redirect()->route('workflow.spectrum.chain')->with('success', 'Chain rule saved.');
        } catch (\Throwable $e) {
            return redirect()->route('workflow.spectrum.chain')->with('error', $e->getMessage());
        }
    }

    public function spectrumChainDelete(Request $request, int $id)
    {
        $svc = new SpectrumComplianceService();
        $svc->deleteChainRule($id);
        return redirect()->route('workflow.spectrum.chain')->with('success', 'Chain rule deleted.');
    }
}
