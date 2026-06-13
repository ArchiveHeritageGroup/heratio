<?php

/**
 * PublicationStudioController - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1232 - Research OS #10: Publication Studio (ROS Stage 15, epic #1222).
 *
 * Per-project publication workflow on top of the target-journal directory:
 * journal matching, submissions, the compliance checklist, response-to-reviewers
 * and revision history, and status transitions with DOI / repository deposit.
 *
 * All routes resolve the project for the authenticated user first (mirroring the
 * QuestionBuilder slice's resolveProject) and abort 404 on a missing project so
 * the /{slug} catch-all is never reached. Empty-states are handled in the views.
 */

namespace AhgResearch\Controllers;

use AhgResearch\Services\PublicationStudioService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PublicationStudioController extends Controller
{
    public function __construct(
        private PublicationStudioService $service,
    ) {}

    /** Resolve the project row for the current user, or null. */
    private function resolveProject(int $projectId): ?object
    {
        if (! Auth::check()) {
            return null;
        }
        try {
            $project = DB::table('research_project')->where('id', $projectId)->first();
        } catch (\Throwable $e) {
            $project = null;
        }

        return $project ?: null;
    }

    // ── matching list + studio landing ───────────────────────────────────────

    /** Studio home for a project: existing submissions + venue matching panel. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $project = $this->resolveProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        $filters = [
            'open_access'     => $request->boolean('open_access'),
            'market'          => $request->input('market'),
            'reference_style' => $request->input('reference_style'),
            'scope_text'      => $request->input('scope_text'),
        ];

        return view('research::publication-studio.index', [
            'project'        => $project,
            'ready'          => $this->service->isReady(),
            'directoryReady' => $this->service->directoryReady(),
            'submissions'    => $this->service->submissionsForProject($projectId),
            'matches'        => $this->service->matchVenues($project, $filters),
            'markets'        => $this->service->directoryMarkets(),
            'filters'        => $filters,
        ]);
    }

    /** Create a submission against a matched (or free-text) venue. */
    public function storeSubmission(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $project = $this->resolveProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        $validated = $request->validate([
            'venue_ref'        => 'nullable|integer',
            'venue_name'       => 'nullable|string|max:300',
            'manuscript_title' => 'nullable|string|max:500',
            'status'           => 'nullable|string|max:40',
            'notes'            => 'nullable|string|max:5000',
        ]);

        $result = $this->service->createSubmission($projectId, $validated, Auth::id());
        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['error'] ?? 'Could not create the submission.');
        }

        return redirect()
            ->route('research.publication.submission', [$projectId, $result['id']])
            ->with('success', 'Submission created.');
    }

    // ── submission detail ────────────────────────────────────────────────────

    /** Submission detail: checklist + responses + status timeline + deposit. */
    public function submission(Request $request, int $projectId, int $submissionId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $project = $this->resolveProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }
        $submission = $this->service->getSubmission($projectId, $submissionId);
        if (! $submission) {
            abort(404, 'Submission not found');
        }

        [$met, $total] = $this->service->requirementProgress($submissionId);

        return view('research::publication-studio.submission', [
            'project'      => $project,
            'submission'   => $submission,
            'journal'      => $this->service->getJournal($submission['venue_ref'] ?? null),
            'requirements' => $this->service->requirements($submissionId),
            'reqMet'       => $met,
            'reqTotal'     => $total,
            'responses'    => $this->service->responses($submissionId),
            'statuses'     => PublicationStudioService::STATUSES,
            'nextStatuses' => $this->service->allowedNextStatuses($submission['status'] ?? 'drafting'),
            'aiAvailable'  => $this->service->aiAvailable(),
        ]);
    }

    /** Update deposit / metadata fields (DOI, repository, notes, titles). */
    public function updateSubmission(Request $request, int $projectId, int $submissionId)
    {
        $project = $this->guardSubmission($projectId, $submissionId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $validated = $request->validate([
            'venue_name'       => 'nullable|string|max:300',
            'manuscript_title' => 'nullable|string|max:500',
            'doi'              => 'nullable|string|max:255',
            'repository_url'   => 'nullable|string|max:1000',
            'notes'            => 'nullable|string|max:5000',
        ]);

        $this->service->updateSubmissionMeta($projectId, $submissionId, $validated);

        return $this->backToSubmission($projectId, $submissionId, 'Submission updated.');
    }

    /** Apply a status transition. */
    public function transition(Request $request, int $projectId, int $submissionId)
    {
        $project = $this->guardSubmission($projectId, $submissionId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $to = (string) $request->input('to', '');
        $result = $this->service->transition($projectId, $submissionId, $to);

        return $this->backToSubmission(
            $projectId,
            $submissionId,
            $result['ok'] ? 'Status updated to "' . $to . '".' : null,
            $result['ok'] ? null : ($result['error'] ?? 'Could not update the status.')
        );
    }

    // ── checklist actions ────────────────────────────────────────────────────

    public function addRequirement(Request $request, int $projectId, int $submissionId)
    {
        $project = $this->guardSubmission($projectId, $submissionId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }
        $validated = $request->validate(['label' => 'required|string|max:255']);
        $this->service->addRequirement($submissionId, $validated['label']);

        return $this->backToSubmission($projectId, $submissionId, 'Requirement added.');
    }

    public function updateRequirement(Request $request, int $projectId, int $submissionId, int $reqId)
    {
        $project = $this->guardSubmission($projectId, $submissionId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }
        $validated = $request->validate([
            'met'  => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
        ]);
        $this->service->updateRequirement(
            $submissionId,
            $reqId,
            $request->boolean('met'),
            $validated['note'] ?? null
        );

        return $this->backToSubmission($projectId, $submissionId, 'Checklist updated.');
    }

    public function deleteRequirement(Request $request, int $projectId, int $submissionId, int $reqId)
    {
        $project = $this->guardSubmission($projectId, $submissionId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }
        $this->service->deleteRequirement($submissionId, $reqId);

        return $this->backToSubmission($projectId, $submissionId, 'Requirement removed.');
    }

    // ── response-to-reviewers ────────────────────────────────────────────────

    public function addResponse(Request $request, int $projectId, int $submissionId)
    {
        $project = $this->guardSubmission($projectId, $submissionId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }
        $validated = $request->validate([
            'reviewer_label' => 'nullable|string|max:120',
            'point'          => 'nullable|string|max:8000',
            'response'       => 'nullable|string|max:8000',
            'revision_note'  => 'nullable|string|max:8000',
        ]);

        $ok = $this->service->addResponse($submissionId, $validated, Auth::id());

        return $this->backToSubmission(
            $projectId,
            $submissionId,
            $ok ? 'Response recorded.' : null,
            $ok ? null : 'Enter a reviewer point, a response, or a revision note.'
        );
    }

    // ── optional AI fit suggestion (gateway only) ────────────────────────────

    /** AJAX: short, labelled AI note on project-to-venue fit. JSON. */
    public function aiFit(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false], 401);
        }
        $project = $this->resolveProject($projectId);
        if (! $project) {
            return response()->json(['ok' => false], 404);
        }
        if (! $this->service->aiAvailable()) {
            return response()->json(['ok' => true, 'ai_available' => false, 'note' => null]);
        }

        $journal = $this->service->getJournal((int) $request->input('venue_ref', 0));
        if (! $journal) {
            return response()->json(['ok' => false, 'error' => 'Unknown venue.'], 422);
        }

        // #1252 - when the fit suggestion is requested against a specific
        // submission, pass its id so the service can stamp the AI marker on that
        // row. Validate ownership before trusting it; otherwise leave it null.
        $submissionId = (int) $request->input('submission_id', 0);
        if ($submissionId > 0 && ! $this->service->getSubmission($projectId, $submissionId)) {
            $submissionId = 0;
        }

        return response()->json([
            'ok'           => true,
            'ai_available' => true,
            'note'         => $this->service->aiFitSuggestion(
                $project,
                $journal,
                $projectId,
                $submissionId > 0 ? $submissionId : null
            ),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Guard: project + submission exist for this user, else a redirect. */
    private function guardSubmission(int $projectId, int $submissionId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $project = $this->resolveProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }
        if (! $this->service->getSubmission($projectId, $submissionId)) {
            abort(404, 'Submission not found');
        }

        return $project;
    }

    private function backToSubmission(int $projectId, int $submissionId, ?string $success = null, ?string $error = null)
    {
        $redirect = redirect()->route('research.publication.submission', [$projectId, $submissionId]);
        if ($success !== null) {
            $redirect->with('success', $success);
        }
        if ($error !== null) {
            $redirect->with('error', $error);
        }

        return $redirect;
    }
}
