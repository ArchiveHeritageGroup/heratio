<?php

/**
 * ResearchTeamController - Heratio ahg-research
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

use AhgResearch\Services\ResearchService;
use AhgResearch\Services\ResearchTeamService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * heratio#1222 - Research OS: Research Team & Collaborators register.
 *
 * List / create / edit / show / delete the people on a research project (name,
 * role, affiliation, email, ORCID), show a per-project summary (counts by role,
 * leads highlighted), and export the project's team as a machine-readable .json
 * document. The ORCID is rendered as a link to https://orcid.org/{orcid} - never
 * fetched. Role is informed by the international CRediT taxonomy; the detailed
 * contribution stays free text.
 *
 * Self-contained: resolves project + researcher locally, mirroring
 * ResearchFundingController, never edits getSidebarData. Every action is
 * empty-state safe and degrades cleanly when the slice is not installed. This is
 * the broader contributor register, NOT a replacement for the project owner.
 */
class ResearchTeamController extends Controller
{
    public function __construct(
        private ResearchTeamService $team,
        private ResearchService $research,
    ) {}

    /** Team members on a project + per-project summary. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $members       = $this->team->listMembers($projectId);
        $summary       = $this->team->summary($projectId);
        $roleOptions   = $this->team->roleOptions();
        $statusOptions = $this->team->statusOptions();

        // Pre-compute each member's ORCID resolver URL for the list.
        $orcidUrls = [];
        foreach ($members as $m) {
            $orcidUrls[$m['id']] = $m['orcid'] !== '' ? $this->team->orcidUrl($m['orcid']) : null;
        }

        return view('research::team.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'members', 'summary', 'roleOptions', 'statusOptions', 'orcidUrls')
        ));
    }

    /** New-member form. */
    public function create(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $member        = null;
        $roleOptions   = $this->team->roleOptions();
        $statusOptions = $this->team->statusOptions();

        return view('research::team.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'member', 'roleOptions', 'statusOptions')
        ));
    }

    /** Persist a new team member. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $this->validateMember($request);

        $id = $this->team->createMember($projectId, $researcher ? (int) $researcher->id : null, $data);

        if (! $id) {
            return redirect()->route('research.team.index', $projectId)
                ->with('error', 'Could not add the team member. Please try again.');
        }

        return redirect()->route('research.team.show', [$projectId, $id])
            ->with('success', 'Team member saved.');
    }

    /** Edit-member form. */
    public function edit(int $projectId, int $memberId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $member = $this->team->getMember($memberId, $projectId);
        if (! $member) {
            return redirect()->route('research.team.index', $projectId)
                ->with('error', 'Team member not found.');
        }

        $roleOptions   = $this->team->roleOptions();
        $statusOptions = $this->team->statusOptions();

        return view('research::team.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'member', 'roleOptions', 'statusOptions')
        ));
    }

    /** Persist edits to a team member. */
    public function update(Request $request, int $projectId, int $memberId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $existing = $this->team->getMember($memberId, $projectId);
        if (! $existing) {
            return redirect()->route('research.team.index', $projectId)
                ->with('error', 'Team member not found.');
        }

        $data = $this->validateMember($request);

        $ok = $this->team->updateMember($memberId, $projectId, $data);

        if (! $ok) {
            return redirect()->route('research.team.index', $projectId)
                ->with('error', 'Could not save the team member.');
        }

        return redirect()->route('research.team.show', [$projectId, $memberId])
            ->with('success', 'Team member saved.');
    }

    /** Read-only member detail. */
    public function show(int $projectId, int $memberId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $member = $this->team->getMember($memberId, $projectId);
        if (! $member) {
            return redirect()->route('research.team.index', $projectId)
                ->with('error', 'Team member not found.');
        }

        $roleOptions   = $this->team->roleOptions();
        $statusOptions = $this->team->statusOptions();
        $orcidUrl      = $member['orcid'] !== '' ? $this->team->orcidUrl($member['orcid']) : null;

        return view('research::team.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'member', 'roleOptions', 'statusOptions', 'orcidUrl')
        ));
    }

    /** Delete a team member. */
    public function destroy(int $projectId, int $memberId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $ok = $this->team->deleteMember($memberId, $projectId);

        return redirect()->route('research.team.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Team member removed.' : 'Could not remove the member.');
    }

    /**
     * Machine-readable export of the project's team. Returns a downloadable JSON
     * document - each member with role, affiliation, ORCID (bare iD + resolvable
     * URL) and contribution, plus a summary with counts by role and the leads.
     */
    public function exportJson(int $projectId)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }
        [$project] = $this->projectContext($projectId);

        $members = $this->team->listMembers($projectId);
        $payload = $this->team->buildExport($members, $project);

        $filename = 'research-team-project-' . $projectId . '.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function validateMember(Request $request): array
    {
        $data = $request->validate([
            'person_name'       => 'required|string|max:512',
            'role'              => 'required|string|max:32',
            'affiliation'       => 'nullable|string|max:512',
            'email'             => 'nullable|email|max:255',
            'orcid'             => 'nullable|string|max:64',
            'is_lead'           => 'nullable|boolean',
            'contribution_note' => 'nullable|string|max:65000',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'status'            => 'required|string|max:32',
        ]);

        // ORCID is validated by format only - never by an external lookup. A blank
        // value is allowed; a non-blank value must normalise to a canonical iD.
        if (! $this->team->isValidOrcid($data['orcid'] ?? null)) {
            throw ValidationException::withMessages([
                'orcid' => __('Enter a valid ORCID iD in the form 0000-0000-0000-0000 (the last character may be X), or leave it blank.'),
            ]);
        }

        $data['is_lead'] = $request->boolean('is_lead');

        return $data;
    }

    // ---------------------------------------------------------------------
    // Helpers (self-contained; getSidebarData is NOT used or edited)
    // ---------------------------------------------------------------------

    /**
     * Resolve project + current researcher. Aborts 403 if the user is not a
     * registered researcher, 404 if the project is missing.
     *
     * @return array{0:?object,1:?object}
     */
    private function projectContext(int $projectId): array
    {
        $researcher = Auth::check() ? $this->research->getResearcherByUserId(Auth::id()) : null;
        if (! $researcher) {
            abort(403);
        }
        $project = $this->findProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        return [$project, $researcher];
    }

    /** Project row, or null. Schema-guarded so a partial install never 500s. */
    private function findProject(int $projectId): ?object
    {
        try {
            if (! Schema::hasTable('research_project')) {
                return null;
            }

            return DB::table('research_project')->where('id', $projectId)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Sidebar payload matching the package convention, without touching getSidebarData. */
    private function sidebar(string $active): array
    {
        $unread = 0;
        try {
            if (Auth::check() && Schema::hasTable('research_notification')) {
                $researcher = $this->research->getResearcherByUserId(Auth::id());
                if ($researcher) {
                    $unread = (int) DB::table('research_notification')
                        ->where('researcher_id', $researcher->id)
                        ->where('is_read', 0)
                        ->count();
                }
            }
        } catch (\Throwable $e) {
            // table may not exist yet - leave unread at 0
        }

        return ['sidebarActive' => $active, 'unreadNotifications' => $unread];
    }
}
