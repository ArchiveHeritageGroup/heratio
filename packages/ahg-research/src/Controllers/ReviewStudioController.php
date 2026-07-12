<?php

/**
 * ReviewStudioController - Controller for Heratio
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

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Concerns\AuthorizesProjectAccess;
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\ResearchService;
use AhgResearch\Services\ReviewStudioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ReviewStudioController - Research OS Stage 14 (heratio#1230, epic #1222).
 *
 * Per-project Review Studio with two halves:
 *   (1) supervisor / co-author comment threads anchored to a claim or the
 *       project (works fully without AI); and
 *   (2) an adversarial reviewer-twin simulation that calls the AHG gateway via
 *       LlmService (every AI output is labelled, and degrades gracefully when
 *       the gateway is unavailable so the comment half stays usable).
 *
 * Auth-gated like the rest of the portal. Mirrors loadProjectContext to resolve
 * the [project, researcher] pair without editing the shared controller.
 */
class ReviewStudioController extends Controller
{
    use LogsResearchActivity;
    use AuthorizesProjectAccess;

    protected ReviewStudioService $studio;
    protected ResearchService $research;

    public function __construct()
    {
        $this->studio = new ReviewStudioService();
        $this->research = new ResearchService();
    }

    /** Resolve [project, researcher] for a project id, mirroring loadProjectContext. */
    protected function context(int $projectId): array
    {
        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            abort(403);
        }
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (! $project) {
            abort(404, 'Project not found');
        }
        // SECURITY (#1308-parity): authorize the caller against the resolved project.
        $this->assertProjectMember($projectId, (int) $researcher->id);
        return [$project, $researcher];
    }

    /** Build the shared sidebar payload (matches the rest of the research portal). */
    protected function sidebar(string $active = 'projects'): array
    {
        $unread = 0;
        try {
            $researcher = $this->research->getResearcherByUserId(Auth::id());
            if ($researcher) {
                $unread = (int) DB::table('research_notification')
                    ->where('researcher_id', $researcher->id)
                    ->where('is_read', 0)->count();
            }
        } catch (\Throwable $e) {
            // table may not exist yet
        }
        return ['sidebarActive' => $active, 'unreadNotifications' => $unread];
    }

    /** Review Studio landing: comment panel + reviewer-twin panel + run history. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $assertionFilter = $request->filled('claim') ? (int) $request->input('claim') : null;
        $includeResolved = $request->input('resolved', '1') !== '0';

        $comments = $this->studio->listComments($projectId, $assertionFilter, $includeResolved);
        $claims   = $this->studio->claimAnchors($projectId);
        $runs     = $this->studio->listRuns($projectId);

        $activeClaim = $assertionFilter !== null
            ? $this->studio->getClaim($projectId, $assertionFilter)
            : null;

        return view('research::research.review-studio.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'comments', 'claims', 'runs', 'activeClaim', 'includeResolved'),
            [
                'personas'      => ReviewStudioService::PERSONAS,
                'findingGroups' => ReviewStudioService::FINDING_GROUPS,
                'aiLabel'       => ReviewStudioService::AI_LABEL,
                'assertionFilter' => $assertionFilter,
            ]
        ));
    }

    /** Add a root comment or a reply (POST). */
    public function storeComment(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'body'         => 'required|string|max:10000',
            'assertion_id' => 'nullable|integer',
            'thread_id'    => 'nullable|integer',
        ]);

        $id = $this->studio->addComment(
            $projectId,
            (int) Auth::id(),
            $validated['body'],
            isset($validated['assertion_id']) && $validated['assertion_id'] !== null ? (int) $validated['assertion_id'] : null,
            isset($validated['thread_id']) && $validated['thread_id'] !== null ? (int) $validated['thread_id'] : null,
        );

        if ($id) {
            $this->logResearchActivity('create', 'review', (int) $id, null, ['method' => 'ReviewStudioController@storeComment', 'item' => 'comment'], $projectId);
        }

        return redirect()->route('research.review.index', $projectId)
            ->with($id ? 'success' : 'error', $id ? 'Comment posted.' : 'Could not post the comment.');
    }

    /** Resolve / unresolve a comment thread (POST). */
    public function resolveComment(Request $request, int $projectId, int $commentId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $resolved = (bool) $request->input('resolved', 1);
        $ok = $this->studio->setResolved($projectId, $commentId, $resolved);

        if ($ok) {
            $this->logResearchActivity('update', 'review', $commentId, null, ['method' => 'ReviewStudioController@resolveComment', 'item' => 'comment', 'resolved' => $resolved], $projectId);
        }

        return redirect()->back()
            ->with($ok ? 'success' : 'error', $ok ? ($resolved ? 'Thread resolved.' : 'Thread reopened.') : 'Could not update the thread.');
    }

    /** Delete a comment / thread (POST). */
    public function destroyComment(Request $request, int $projectId, int $commentId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->studio->deleteComment($projectId, $commentId);
        if ($ok) {
            $this->logResearchActivity('delete', 'review', $commentId, null, ['method' => 'ReviewStudioController@destroyComment', 'item' => 'comment'], $projectId);
        }
        return redirect()->route('research.review.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Comment deleted.' : 'Could not delete the comment.');
    }

    /** Run the adversarial reviewer twin (POST). Degrades gracefully on AI failure. */
    public function runReviewer(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $validated = $request->validate([
            'persona' => 'required|string|max:60',
        ]);

        $result = $this->studio->runReviewerTwin($project, $validated['persona'], (int) Auth::id());

        $redirect = redirect()->route('research.review.index', $projectId);
        if ($result['ok']) {
            $this->logResearchActivity('create', 'review', null, null, ['method' => 'ReviewStudioController@runReviewer', 'item' => 'reviewer_run', 'persona' => $validated['persona']], $projectId);
            return $redirect->with('success', $result['message'] ?? 'Reviewer simulation complete.');
        }
        // Graceful degrade: clear message, comment half untouched.
        return $redirect->with('ai_warning', $result['message'] ?? 'The AI reviewer is currently unavailable.');
    }

    /** View one stored reviewer-twin run in detail. */
    public function showRun(Request $request, int $projectId, int $runId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $run = $this->studio->getRun($projectId, $runId);
        if (! $run) {
            return redirect()->route('research.review.index', $projectId)
                ->with('error', 'Reviewer run not found.');
        }

        return view('research::research.review-studio.run', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'run'),
            [
                'findingGroups' => ReviewStudioService::FINDING_GROUPS,
                'aiLabel'       => ReviewStudioService::AI_LABEL,
                'personas'      => ReviewStudioService::PERSONAS,
            ]
        ));
    }

    /** Delete a stored reviewer-twin run (POST). */
    public function destroyRun(Request $request, int $projectId, int $runId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->studio->deleteRun($projectId, $runId);
        if ($ok) {
            $this->logResearchActivity('delete', 'review', $runId, null, ['method' => 'ReviewStudioController@destroyRun', 'item' => 'reviewer_run'], $projectId);
        }
        return redirect()->route('research.review.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Reviewer run deleted.' : 'Could not delete the run.');
    }
}
