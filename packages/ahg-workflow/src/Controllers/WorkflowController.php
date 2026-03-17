<?php

namespace AhgWorkflow\Controllers;

use AhgWorkflow\Services\WorkflowService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
    public function admin()
    {
        $workflows = $this->service->getWorkflows();

        return view('ahg-workflow::admin', [
            'workflows' => $workflows,
        ]);
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
                'applies_to', 'auto_archive_days',
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
}
