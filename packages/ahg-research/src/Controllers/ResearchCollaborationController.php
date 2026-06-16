<?php

/**
 * ResearchCollaborationController - Controller for Heratio
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
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchCollaborationController - Project collaboration endpoints.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Two concerns live here:
 *
 *   1. Collaborator management (auth-gated, GET renders / POST mutates):
 *      inviteCollaborator, shareProject, projectCollaborators.
 *   2. Real-time collaboration polling fallback (AJAX/JSON; abort(401) when
 *      unauthenticated, NOT a /login redirect): collabPanel (HTML view),
 *      collabJoin, collabPoll, collabComment, collabCommentResolve.
 *
 * The realtime endpoints delegate to CollaborationRealtimeService (resolved via
 * the container, as in the original). The collaborator-management endpoints
 * read/write research_project_collaborator directly.
 *
 * Helper note: loadProjectContext() is copied verbatim from ResearchController
 * because three methods here depend on it. It is NOT exclusive to collaboration
 * (~18 ResearchController methods use it) - the integrator should hoist it into
 * the ResearchControllerHelpers trait and drop this private copy. Methods bodies
 * are otherwise lifted verbatim.
 */
class ResearchCollaborationController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    // =========================================================================
    // Collaborator management
    // =========================================================================

    public function inviteCollaborator(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->isMethod('post')) {
            $email = $request->input('email');
            $role = $request->input('role', 'contributor');

            $invitee = DB::table('research_researcher')->where('email', $email)->first();
            if (!$invitee) {
                return redirect()->route('research.inviteCollaborator', $id)->with('error', 'No researcher found with that email.');
            }

            $exists = DB::table('research_project_collaborator')
                ->where('project_id', $id)
                ->where('researcher_id', $invitee->id)
                ->first();
            if ($exists) {
                return redirect()->route('research.inviteCollaborator', $id)->with('error', 'Already a collaborator.');
            }

            DB::table('research_project_collaborator')->insert([
                'project_id'    => $id,
                'researcher_id' => $invitee->id,
                'role'          => $role,
                'status'        => 'invited',
                'invited_at'    => now(),
            ]);

            return redirect()->route('research.viewProject', $id)->with('success', 'Invitation sent to ' . $email);
        }

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('pc.*', 'r.first_name', 'r.last_name', 'r.email')
            ->get()->toArray();

        return view('research::research.invite-collaborator', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'collaborators')
        ));
    }

    public function shareProject(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        if ($request->isMethod('post') && $request->input('form_action') === 'generate_token') {
            $token = bin2hex(random_bytes(32));
            DB::table('research_project')->where('id', $id)->update([
                'share_token' => $token,
                'updated_at'  => now(),
            ]);
            return redirect()->route('research.shareProject', $id)->with('success', 'Share link generated.');
        }

        return view('research::research.share-project', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher')
        ));
    }

    public function projectCollaborators(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        [$project, $researcher] = $this->loadProjectContext($id);

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('pc.*', 'r.first_name', 'r.last_name', 'r.email')
            ->get()->toArray();

        return view('research::research.project-collaborators', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'collaborators')
        ));
    }

    // =========================================================================
    // Real-time collaboration (polling fallback)
    // =========================================================================

    public function collabJoin(Request $request, int $projectId)
    {
        if (!Auth::check()) abort(401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $svc = app(\AhgResearch\Services\CollaborationRealtimeService::class);
        $data = $svc->joinProject($projectId, (int) $researcher->id, $request->input('cursor_target'));

        return response()->json(array_merge($data, $svc->poll($projectId)));
    }

    public function collabPoll(Request $request, int $projectId)
    {
        if (!Auth::check()) abort(401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $svc = app(\AhgResearch\Services\CollaborationRealtimeService::class);
        $svc->heartbeat($projectId, (int) $researcher->id, $request->input('cursor_target'));

        $since = $request->input('since');
        return response()->json($svc->poll($projectId, $since !== null ? (int) $since : null));
    }

    public function collabComment(Request $request, int $projectId)
    {
        if (!Auth::check()) abort(401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $validated = $request->validate([
            'body'              => 'required|string|max:5000',
            'collection_id'     => 'nullable|integer',
            'item_id'           => 'nullable|integer',
            'parent_comment_id' => 'nullable|integer',
        ]);

        $svc = app(\AhgResearch\Services\CollaborationRealtimeService::class);
        $id = $svc->postComment($projectId, (int) $researcher->id, $validated);

        return response()->json(['id' => $id, 'status' => 'open']);
    }

    public function collabCommentResolve(Request $request, int $projectId, int $commentId)
    {
        if (!Auth::check()) abort(401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $svc = app(\AhgResearch\Services\CollaborationRealtimeService::class);
        $svc->resolveComment($commentId, (int) $researcher->id);

        return response()->json(['status' => 'resolved']);
    }

    public function collabPanel(Request $request, int $projectId)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (!$project) abort(404);

        $svc = app(\AhgResearch\Services\CollaborationRealtimeService::class);
        $comments = $svc->comments($projectId);
        $presence = $svc->presence($projectId);

        return view('research::research.collab-panel', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'comments', 'presence', 'researcher')
        ));
    }

    // =========================================================================
    // Private helpers (copied verbatim from ResearchController; see class
    // docblock - integrator should hoist loadProjectContext into the trait).
    // =========================================================================

    protected function loadProjectContext(int $id): array
    {
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $project = DB::table('research_project')->where('id', $id)->first();
        if (!$project) abort(404, 'Project not found');

        // SECURITY (#1308): authorize, do not just load. The owner is stored as a
        // collaborator row (role='owner', status='accepted'), so an accepted
        // membership check covers owner + collaborators and excludes pending
        // invites. Mirrors ProjectService::getProjects(). Admins bypass.
        $hasAccess = DB::table('research_project_collaborator')
            ->where('project_id', $id)
            ->where('researcher_id', $researcher->id)
            ->where('status', 'accepted')
            ->exists();
        if (!$hasAccess && !\AhgCore\Services\AclService::isAdministrator(Auth::user())) {
            abort(403, 'You do not have access to this project.');
        }

        return [$project, $researcher];
    }
}
