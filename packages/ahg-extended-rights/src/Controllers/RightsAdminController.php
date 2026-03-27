<?php

declare(strict_types=1);

namespace AhgExtendedRights\Controllers;

use AhgExtendedRights\Services\ExtendedRightsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * RightsAdminController
 *
 * Admin pages for managing rights, embargoes, orphan works, TK labels.
 * Migrated from AtoM ahgRightsPlugin rightsAdminActions.
 */
class RightsAdminController extends Controller
{
    protected ExtendedRightsService $service;

    public function __construct(ExtendedRightsService $service)
    {
        $this->service = $service;
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index()
    {
        $stats = $this->service->getStatistics();
        $expiringEmbargoes = $this->service->getExpiringEmbargoes(30);
        $reviewDue = $this->service->getEmbargoesForReview();
        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.index', compact('stats', 'expiringEmbargoes', 'reviewDue', 'formOptions'));
    }

    // =========================================================================
    // EMBARGOES
    // =========================================================================

    public function embargoes(Request $request)
    {
        $status = $request->input('status', 'active');
        $embargoes = $this->service->getActiveEmbargoes($status);
        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.embargoes', compact('embargoes', 'status', 'formOptions'));
    }

    public function embargoEdit(Request $request, ?int $id = null)
    {
        $embargo = null;
        $embargoLog = collect();

        if ($id) {
            $embargo = $this->service->getEmbargoById($id);
            if (!$embargo) {
                abort(404);
            }
            $embargoLog = $this->service->getEmbargoLog($id);
        }

        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.embargo-edit', compact('embargo', 'embargoLog', 'formOptions'));
    }

    public function embargoStore(Request $request, ?int $id = null)
    {
        $request->validate([
            'object_id' => 'required|integer',
            'embargo_type' => 'required|string',
            'reason' => 'required|string',
            'start_date' => 'required|date',
        ]);

        $data = $request->only([
            'object_id', 'embargo_type', 'reason', 'start_date', 'end_date',
            'auto_release', 'review_date', 'review_interval_months',
            'notify_before_days', 'notify_emails', 'reason_note', 'internal_note',
        ]);
        $data['auto_release'] = $request->has('auto_release') ? 1 : 0;

        if ($id) {
            $this->service->updateEmbargo($id, $data);
            return redirect()->route('ext-rights-admin.embargoes')->with('success', 'Embargo updated successfully.');
        } else {
            $this->service->createEmbargo($data);
            return redirect()->route('ext-rights-admin.embargoes')->with('success', 'Embargo created successfully.');
        }
    }

    public function embargoLift(Request $request, int $id)
    {
        $reason = $request->input('lift_reason');
        if ($this->service->liftEmbargo($id, $reason)) {
            return redirect()->route('ext-rights-admin.embargoes')->with('success', 'Embargo lifted successfully.');
        }
        return redirect()->route('ext-rights-admin.embargoes')->with('error', 'Failed to lift embargo.');
    }

    public function embargoExtend(Request $request, int $id)
    {
        $request->validate([
            'new_end_date' => 'required|date|after:today',
        ]);

        $reason = $request->input('extend_reason');
        if ($this->service->extendEmbargo($id, $request->input('new_end_date'), $reason)) {
            return redirect()->route('ext-rights-admin.embargoes')->with('success', 'Embargo extended successfully.');
        }
        return redirect()->route('ext-rights-admin.embargoes')->with('error', 'Failed to extend embargo.');
    }

    public function processExpired()
    {
        $count = $this->service->processExpiredEmbargoes();
        return redirect()->route('ext-rights-admin.embargoes')->with('success', "Processed {$count} expired embargoes.");
    }

    // =========================================================================
    // ORPHAN WORKS
    // =========================================================================

    public function orphanWorks(Request $request)
    {
        $status = $request->input('status', 'all');
        $orphanWorks = $this->service->getOrphanWorks($status);
        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.orphan-works', compact('orphanWorks', 'status', 'formOptions'));
    }

    public function orphanWorkEdit(Request $request, ?int $id = null)
    {
        $orphanWork = null;
        $searchSteps = collect();

        if ($id) {
            $orphanWork = $this->service->getOrphanWorkById($id);
            if (!$orphanWork) {
                abort(404);
            }
            $searchSteps = $this->service->getOrphanWorkSearchSteps($id);
        }

        $formOptions = $this->service->getFormOptions();

        return view('ahg-extended-rights::admin.orphan-work-edit', compact('orphanWork', 'searchSteps', 'formOptions'));
    }

    public function orphanWorkStore(Request $request, ?int $id = null)
    {
        $request->validate([
            'object_id' => 'required|integer',
            'work_type' => 'required|string',
        ]);

        $data = $request->only([
            'object_id', 'work_type', 'search_jurisdiction',
            'intended_use', 'proposed_fee', 'notes',
        ]);

        if ($id) {
            $this->service->updateOrphanWork($id, $data);
            return redirect()->route('ext-rights-admin.orphan-work-edit', $id)->with('success', 'Orphan work record updated.');
        } else {
            $newId = $this->service->createOrphanWork($data);
            return redirect()->route('ext-rights-admin.orphan-work-edit', $newId)->with('success', 'Orphan work record created.');
        }
    }

    public function addSearchStep(Request $request, int $orphanWorkId)
    {
        $request->validate([
            'source_type' => 'required|string',
            'source_name' => 'required|string',
            'search_date' => 'required|date',
        ]);

        $data = $request->only([
            'source_type', 'source_name', 'source_url', 'search_date',
            'search_terms', 'results_found', 'results_description',
        ]);
        $data['results_found'] = $request->has('results_found') ? 1 : 0;

        $this->service->addOrphanWorkSearchStep($orphanWorkId, $data);

        return redirect()->route('ext-rights-admin.orphan-work-edit', $orphanWorkId)->with('success', 'Search step added.');
    }

    public function completeOrphanSearch(Request $request, int $id)
    {
        $found = $request->boolean('rights_holder_found');
        $this->service->completeOrphanWorkSearch($id, $found);

        return redirect()->route('ext-rights-admin.orphan-works')->with('success', 'Search marked as complete.');
    }

    // =========================================================================
    // TK LABELS
    // =========================================================================

    public function tkLabels()
    {
        $tkLabels = $this->service->getTkLabels();
        $assignments = $this->service->getTkLabelAssignments();

        return view('ahg-extended-rights::admin.tk-labels', compact('tkLabels', 'assignments'));
    }

    public function assignTkLabel(Request $request)
    {
        $request->validate([
            'object_id' => 'required|integer',
            'tk_label_id' => 'required|integer',
        ]);

        $data = $request->only(['community_name', 'community_contact', 'custom_text']);

        $this->service->assignTkLabel(
            (int) $request->input('object_id'),
            (int) $request->input('tk_label_id'),
            $data
        );

        return redirect()->route('ext-rights-admin.tk-labels')->with('success', 'TK Label assigned successfully.');
    }

    public function removeTkLabel(Request $request)
    {
        $objectId = (int) $request->input('object_id');
        $labelId = (int) $request->input('label_id');

        if ($this->service->removeTkLabel($objectId, $labelId)) {
            return redirect()->route('ext-rights-admin.tk-labels')->with('success', 'TK Label removed.');
        }
        return redirect()->route('ext-rights-admin.tk-labels')->with('error', 'Failed to remove TK Label.');
    }

    // =========================================================================
    // RIGHTS STATEMENTS & CC LICENSES
    // =========================================================================

    public function statements()
    {
        $rightsStatements = $this->service->getRightsStatements();
        $ccLicenses = $this->service->getCcLicenses();

        return view('ahg-extended-rights::admin.statements', compact('rightsStatements', 'ccLicenses'));
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    public function report(Request $request)
    {
        $type = $request->input('type', 'summary');
        $data = $this->service->getReportData($type);

        if ($request->input('export') === 'csv' && $type !== 'summary') {
            $this->service->exportReportCsv($type, $data);
            return null;
        }

        return view('ahg-extended-rights::admin.report', compact('type', 'data'));
    }
}
