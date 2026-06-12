<?php

/**
 * PreservationSelfAssessmentController - Heratio ahg-core
 *
 * heratio#1244 (maturity self-assessment slice): the admin surface for the
 * HUMAN-ENTERED digital-preservation maturity self-assessment. This is the
 * organisational counterpart to the read-only, evidence-COMPUTED
 * /admin/preservation-maturity dashboard (PreservationMaturityController). It does
 * NOT touch or duplicate that computed surface - it adds a sibling workflow where an
 * institution rates ITSELF, section by section, against a recognised international
 * maturity model (NDSA Levels of Digital Preservation, or the DPC Rapid Assessment
 * Model / DPC RAM), records the ratings + evidence, and tracks a maturity profile
 * over time.
 *
 * Routes (all admin-gated by the route group's `auth` middleware, matching the rest
 * of the /admin/* ahg-core surface, and all MULTI-SEGMENT so they can never collide
 * with the single-segment /{slug} archival-record catch-all):
 *
 *   GET  /admin/preservation-self-assessment              list runs + start form + history
 *   POST /admin/preservation-self-assessment/start        create a run for a chosen model
 *   GET  /admin/preservation-self-assessment/{id}         rate each section (the form)
 *   POST /admin/preservation-self-assessment/{id}         save the ratings
 *   GET  /admin/preservation-self-assessment/{id}/profile the maturity profile (radar/bars)
 *   GET  /admin/preservation-self-assessment/{id}/export  download the run as .json
 *   POST /admin/preservation-self-assessment/{id}/delete  delete a run
 *
 * All writes are confined to the two NEW side tables via PreservationSelfAssessmentService;
 * no AtoM/Qubit base table is written, no ALTER, no AI call. Enumerated values (model,
 * level labels) come from the Dropdown Manager. Every action is resilient: a fresh /
 * mid-migration install (tables absent) degrades to a calm empty state, never a 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\PreservationSelfAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PreservationSelfAssessmentController extends Controller
{
    public function __construct(private PreservationSelfAssessmentService $service) {}

    /**
     * Landing page: the list of past assessment runs (history / progress over time),
     * the maturity history series, and the "start a new assessment" form (model
     * picker). Honest empty state when nothing has been assessed yet.
     */
    public function index()
    {
        return view('ahg-core::preservation-self-assessment.index', [
            'available' => $this->service->isAvailable(),
            'runs' => $this->service->listRuns(),
            'history' => $this->service->history(),
            'modelOptions' => $this->service->modelOptions(),
            'levelLabels' => $this->service->levelLabels(),
            'maxLevel' => PreservationSelfAssessmentService::MAX_LEVEL,
        ]);
    }

    /**
     * Create a new assessment run for the chosen model and jump straight to its
     * rating form. Validates the model against the configured options.
     */
    public function start(Request $request)
    {
        $validModels = array_map(fn ($m) => $m['code'], $this->service->modelOptions());

        $data = $request->validate([
            'model' => ['required', 'string', 'max:32', 'in:'.implode(',', $validModels ?: ['dpc_ram'])],
            'title' => ['nullable', 'string', 'max:255'],
            'assessor' => ['nullable', 'string', 'max:255'],
            'assessment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        if (! $this->service->isAvailable()) {
            return redirect()->route('preservation-self-assessment.index')
                ->with('error', __('The self-assessment tables are not installed yet. Please try again shortly.'));
        }

        $assessor = $data['assessor'] ?? null;
        if (($assessor === null || trim($assessor) === '') && Auth::check()) {
            $u = Auth::user();
            $assessor = $u->name ?? ($u->username ?? ($u->email ?? null));
        }

        $runId = $this->service->createRun($data['model'], [
            'title' => $data['title'] ?? null,
            'assessor' => $assessor,
            'assessor_user_id' => Auth::id(),
            'assessment_date' => $data['assessment_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($runId === null) {
            return redirect()->route('preservation-self-assessment.index')
                ->with('error', __('Could not start the assessment. Please try again.'));
        }

        return redirect()->route('preservation-self-assessment.edit', ['id' => $runId])
            ->with('success', __('Assessment started. Rate each section below.'));
    }

    /** The rating form for one run: rate each section against the chosen model. */
    public function edit(int $id)
    {
        $run = $this->service->getRun($id);
        if ($run === null) {
            return redirect()->route('preservation-self-assessment.index')
                ->with('error', __('That assessment could not be found.'));
        }

        return view('ahg-core::preservation-self-assessment.edit', [
            'run' => $run,
            'levelLabels' => $this->service->levelLabels(),
            'maxLevel' => PreservationSelfAssessmentService::MAX_LEVEL,
        ]);
    }

    /**
     * Save the ratings + metadata for a run. Each section level is validated 0..4.
     * Writes are confined to the two new tables via the service.
     */
    public function update(Request $request, int $id)
    {
        $run = $this->service->getRun($id);
        if ($run === null) {
            return redirect()->route('preservation-self-assessment.index')
                ->with('error', __('That assessment could not be found.'));
        }

        $min = PreservationSelfAssessmentService::MIN_LEVEL;
        $max = PreservationSelfAssessmentService::MAX_LEVEL;

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'assessor' => ['nullable', 'string', 'max:255'],
            'assessment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'status' => ['nullable', 'string', 'in:draft,complete'],
            'ratings' => ['nullable', 'array'],
            'ratings.*.level' => ['nullable', 'integer', 'between:'.$min.','.$max],
            'ratings.*.evidence' => ['nullable', 'string', 'max:10000'],
        ]);

        $ratings = [];
        foreach (($data['ratings'] ?? []) as $sectionKey => $val) {
            $ratings[(string) $sectionKey] = [
                'level' => (int) ($val['level'] ?? 0),
                'evidence' => $val['evidence'] ?? null,
            ];
        }

        $ok = $this->service->saveRun($id, $ratings, [
            'title' => $data['title'] ?? null,
            'assessor' => $data['assessor'] ?? null,
            'assessment_date' => $data['assessment_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        if (! $ok) {
            return redirect()->route('preservation-self-assessment.edit', ['id' => $id])
                ->with('error', __('Could not save the assessment. Please try again.'));
        }

        return redirect()->route('preservation-self-assessment.profile', ['id' => $id])
            ->with('success', __('Assessment saved.'));
    }

    /** The maturity profile (per-section bars + overall) for one saved run. */
    public function profile(int $id)
    {
        $run = $this->service->getRun($id);
        if ($run === null) {
            return redirect()->route('preservation-self-assessment.index')
                ->with('error', __('That assessment could not be found.'));
        }

        return view('ahg-core::preservation-self-assessment.profile', [
            'run' => $run,
            'levelLabels' => $this->service->levelLabels(),
            'maxLevel' => PreservationSelfAssessmentService::MAX_LEVEL,
        ]);
    }

    /** Download one run as a self-contained .json snapshot. */
    public function export(int $id)
    {
        $export = $this->service->exportRun($id);
        if ($export === null) {
            return redirect()->route('preservation-self-assessment.index')
                ->with('error', __('That assessment could not be found.'));
        }

        $filename = 'preservation-self-assessment-'.$id.'-'.now()->format('Ymd').'.json';

        return response()->json($export, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Delete a run (and its ratings). */
    public function destroy(int $id)
    {
        $this->service->deleteRun($id);

        return redirect()->route('preservation-self-assessment.index')
            ->with('success', __('Assessment deleted.'));
    }
}
