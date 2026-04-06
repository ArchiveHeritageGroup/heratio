<?php

namespace AhgRecordsManage\Controllers;

use AhgRecordsManage\Services\DisposalWorkflowService;
use AhgRecordsManage\Services\DisposalExecutionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DisposalController extends Controller
{
    protected DisposalWorkflowService $workflowService;
    protected DisposalExecutionService $executionService;

    public function __construct(DisposalWorkflowService $workflowService, DisposalExecutionService $executionService)
    {
        $this->workflowService = $workflowService;
        $this->executionService = $executionService;
    }

    /**
     * Browse disposal queue with filters.
     */
    public function queue(Request $request)
    {
        $filters = [
            'status' => $request->input('status'),
            'action_type' => $request->input('action_type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $result = $this->workflowService->getDisposalQueue($filters, $page);
        $stats = $this->workflowService->getStats();

        return view('ahg-records::disposal.queue', [
            'items' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    /**
     * Show initiation form for a specific IO.
     */
    public function initiate(int $ioId)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->where('information_object.id', $ioId)
            ->select('information_object.id', 'information_object_i18n.title')
            ->first();

        if (!$io) {
            abort(404, 'Information object not found.');
        }

        // Get disposal classes if available
        $disposalClasses = [];
        if (Schema::hasTable('integrity_retention_policy')) {
            $disposalClasses = DB::table('integrity_retention_policy')
                ->where('is_enabled', 1)
                ->orderBy('name')
                ->get()
                ->toArray();
        }

        // Check legal hold status
        $hasLegalHold = false;
        if (Schema::hasTable('integrity_legal_hold')) {
            $hasLegalHold = DB::table('integrity_legal_hold')
                ->where('information_object_id', $ioId)
                ->where('status', 'active')
                ->exists();
        }

        return view('ahg-records::disposal.initiate', [
            'io' => $io,
            'disposalClasses' => $disposalClasses,
            'hasLegalHold' => $hasLegalHold,
        ]);
    }

    /**
     * Store a new disposal initiation.
     */
    public function initiateStore(Request $request)
    {
        $request->validate([
            'information_object_id' => 'required|integer',
            'action_type' => 'required|string|in:destroy,transfer_archives,transfer_external,retain_permanent,review',
            'disposal_class_id' => 'nullable|integer',
            'reason' => 'nullable|string|max:2000',
            'transfer_destination' => 'nullable|string|max:255',
        ]);

        $userId = auth()->id();

        try {
            $actionId = $this->workflowService->initiateDisposal(
                $request->input('information_object_id'),
                $request->input('disposal_class_id', 0),
                $request->input('action_type'),
                $userId,
                $request->input('reason')
            );

            // If transfer type, store destination
            if (in_array($request->input('action_type'), ['transfer_archives', 'transfer_external']) && $request->input('transfer_destination')) {
                DB::table('rm_disposal_action')->where('id', $actionId)->update([
                    'transfer_destination' => $request->input('transfer_destination'),
                ]);
            }

            return redirect()->route('records.disposal.show', $actionId)
                ->with('success', 'Disposal action initiated successfully.');
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show disposal action detail with timeline.
     */
    public function show(int $id)
    {
        $action = $this->workflowService->getAction($id);
        if (!$action) {
            abort(404, 'Disposal action not found.');
        }

        $timeline = $this->workflowService->getActionTimeline($id);

        return view('ahg-records::disposal.show', [
            'action' => $action,
            'timeline' => $timeline,
        ]);
    }

    /**
     * Recommend a disposal action.
     */
    public function recommend(Request $request, int $id)
    {
        $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $result = $this->workflowService->recommend($id, auth()->id(), $request->input('comment'));

        if ($result) {
            return redirect()->route('records.disposal.show', $id)
                ->with('success', 'Disposal action recommended.');
        }

        return redirect()->route('records.disposal.show', $id)
            ->with('error', 'Cannot recommend this disposal action in its current status.');
    }

    /**
     * Approve a disposal action.
     */
    public function approve(Request $request, int $id)
    {
        $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $result = $this->workflowService->approve($id, auth()->id(), $request->input('comment'));

        if ($result) {
            return redirect()->route('records.disposal.show', $id)
                ->with('success', 'Disposal action approved.');
        }

        return redirect()->route('records.disposal.show', $id)
            ->with('error', 'Cannot approve this disposal action in its current status.');
    }

    /**
     * Clear legal hold check.
     */
    public function clearLegal(Request $request, int $id)
    {
        $result = $this->workflowService->clearLegal($id, auth()->id());

        if ($result) {
            return redirect()->route('records.disposal.show', $id)
                ->with('success', 'Legal clearance granted.');
        }

        return redirect()->route('records.disposal.show', $id)
            ->with('error', 'Cannot clear legal: either the action is not approved, or an active legal hold exists.');
    }

    /**
     * Reject a disposal action.
     */
    public function reject(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $result = $this->workflowService->reject($id, auth()->id(), $request->input('reason'));

        if ($result) {
            return redirect()->route('records.disposal.show', $id)
                ->with('success', 'Disposal action rejected.');
        }

        return redirect()->route('records.disposal.show', $id)
            ->with('error', 'Cannot reject this disposal action in its current status.');
    }

    /**
     * Execute a disposal action (destroy/transfer/retain).
     */
    public function execute(Request $request, int $id)
    {
        $action = $this->workflowService->getAction($id);
        if (!$action) {
            abort(404, 'Disposal action not found.');
        }

        $userId = auth()->id();

        if (in_array($action->action_type, ['destroy'])) {
            $result = $this->executionService->executeDestroy($id, $userId);
        } elseif (in_array($action->action_type, ['transfer_archives', 'transfer_external'])) {
            $destination = $action->transfer_destination ?? $request->input('transfer_destination', 'Not specified');
            $result = $this->executionService->executeTransfer($id, $destination, $userId);
        } elseif ($action->action_type === 'retain_permanent') {
            $reason = $request->input('reason', 'Permanent retention per disposal policy');
            $result = $this->executionService->executeRetain($id, $userId, $reason);
        } elseif ($action->action_type === 'review') {
            $reason = $request->input('reason', 'Review completed');
            $result = $this->executionService->executeRetain($id, $userId, $reason);
        } else {
            return redirect()->route('records.disposal.show', $id)
                ->with('error', 'Unknown action type: ' . $action->action_type);
        }

        if ($result['success'] ?? false) {
            return redirect()->route('records.disposal.show', $id)
                ->with('success', 'Disposal action executed successfully.');
        }

        return redirect()->route('records.disposal.show', $id)
            ->with('error', $result['error'] ?? 'Execution failed.');
    }

    /**
     * Run DoD 5015.2 verification on a disposal action.
     */
    public function verify(int $id)
    {
        $action = $this->workflowService->getAction($id);
        if (!$action) {
            abort(404, 'Disposal action not found.');
        }

        $verificationResult = $this->executionService->verifyDestruction($id);

        return view('ahg-records::disposal.verify', [
            'action' => $action,
            'result' => $verificationResult,
        ]);
    }

    /**
     * Browse completed/cancelled disposal actions.
     */
    public function history(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $result = $this->workflowService->getDisposalQueue([
            'status' => $request->input('status'),
        ], $page);

        // Filter to only show completed/cancelled/rejected/executed/retained
        $completedStatuses = ['executed', 'cancelled', 'rejected', 'retained'];

        // Re-query with completed statuses if no specific status filter
        if (!$request->input('status')) {
            $culture = app()->getLocale();
            $query = DB::table('rm_disposal_action as da')
                ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                    $join->on('da.information_object_id', '=', 'io_i18n.id')
                        ->where('io_i18n.culture', '=', $culture);
                })
                ->leftJoin('user as init_user', 'da.initiated_by', '=', 'init_user.id')
                ->leftJoin('actor_i18n as init_actor', function ($join) {
                    $join->on('init_user.id', '=', 'init_actor.id')
                        ->where('init_actor.culture', '=', 'en');
                })
                ->whereIn('da.status', $completedStatuses);

            $total = $query->count();
            $items = $query->select([
                    'da.*',
                    'io_i18n.title as io_title',
                    'init_actor.authorized_form_of_name as initiated_by_name',
                ])
                ->orderBy('da.updated_at', 'desc')
                ->offset(($page - 1) * 25)
                ->limit(25)
                ->get()
                ->toArray();

            $result = ['data' => $items, 'total' => $total, 'page' => $page, 'per_page' => 25];
        }

        return view('ahg-records::disposal.history', [
            'items' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
        ]);
    }
}
