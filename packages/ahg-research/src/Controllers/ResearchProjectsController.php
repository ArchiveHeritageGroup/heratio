<?php

/**
 * ResearchProjectsController - Controller for Heratio
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
 * ResearchProjectsController - Researcher project management (project subsystem).
 *
 * Extracted from ResearchController as the heavyweight project-subsystem stage
 * of the monolith decomposition (issue #1269). Carries the project list/create/
 * store/show methods and the shared private helper loadProjectContext().
 *
 * The project analysis / visualization / research-output cluster
 * (knowledgeGraph, assertions, hypotheses, extractionJobs, snapshots,
 * viewSnapshot, assertionBatchReview, timelineBuilder, mapBuilder, networkGraph,
 * roCrate, reproducibilityPack, mintDoi, ethicsMilestones, complianceDashboard)
 * was moved to the sibling ResearchProjectOutputsController to keep each
 * controller under the size budget; that sibling carries its own verbatim copy
 * of loadProjectContext (the established precedent - duplication is acceptable,
 * never break a caller).
 *
 * All methods are auth-gated and operate on the current researcher's own (or
 * collaborated) projects. The only $this-> dependencies are the shared trait
 * helper getSidebarData() and the injected ResearchService, so the move is a
 * verbatim lift.
 */
class ResearchProjectsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function projects(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $status = $request->input('status');
        $projects = DB::table('research_project as p')
            ->where(function ($q) use ($researcher) {
                $q->where('p.owner_id', $researcher->id)
                  ->orWhereExists(function ($sub) use ($researcher) {
                      $sub->select(DB::raw(1))
                          ->from('research_project_collaborator')
                          ->whereColumn('research_project_collaborator.project_id', 'p.id')
                          ->where('research_project_collaborator.researcher_id', $researcher->id)
                          ->where('research_project_collaborator.status', 'accepted');
                  });
            });

        if ($status) $projects->where('p.status', $status);
        $projects = $projects->orderBy('p.created_at', 'desc')->get()->toArray();

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $projectId = DB::table('research_project')->insertGetId([
                'owner_id' => $researcher->id,
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'project_type' => $request->input('project_type', 'personal'),
                'institution' => $request->input('institution'),
                'start_date' => $request->input('start_date'),
                'expected_end_date' => $request->input('expected_end_date'),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Add creator as collaborator/owner
            DB::table('research_project_collaborator')->insert([
                'project_id' => $projectId,
                'researcher_id' => $researcher->id,
                'role' => 'owner',
                'status' => 'accepted',
                'invited_at' => date('Y-m-d H:i:s'),
                'accepted_at' => date('Y-m-d H:i:s'),
            ]);

            return redirect()->route('research.viewProject', $projectId)->with('success', 'Project created');
        }

        return view('research::research.projects', array_merge(
            $this->getSidebarData('projects'),
            compact('researcher', 'projects', 'status')
        ));
    }

    public function viewProject(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $project = DB::table('research_project')->where('id', $id)->first();
        if (!$project) abort(404, 'Project not found');

        $isOwner = ($project->owner_id ?? 0) == ($researcher->id ?? 0);

        // Handle POST form actions
        if ($request->isMethod('post') && $isOwner) {
            $action = $request->input('form_action');

            if ($action === 'add_milestone') {
                $maxSort = DB::table('research_project_milestone')->where('project_id', $id)->max('sort_order') ?? 0;
                DB::table('research_project_milestone')->insert([
                    'project_id'  => $id,
                    'title'       => $request->input('milestone_title'),
                    'description' => $request->input('milestone_description'),
                    'due_date'    => $request->input('milestone_due_date') ?: null,
                    'status'      => $request->input('milestone_status', 'pending'),
                    'sort_order'  => $maxSort + 1,
                    'created_at'  => now(),
                ]);
                return redirect()->route('research.viewProject', $id)->with('success', 'Milestone added.');
            }

            if ($action === 'complete_milestone') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->update(['status' => 'completed', 'updated_at' => now()]);
                return redirect()->route('research.viewProject', $id)->with('success', 'Milestone completed.');
            }

            if ($action === 'delete_milestone') {
                DB::table('research_project_milestone')
                    ->where('id', $request->input('milestone_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.viewProject', $id)->with('success', 'Milestone deleted.');
            }

            if ($action === 'add_resource') {
                DB::table('research_project_resource')->insert([
                    'project_id'    => $id,
                    'resource_type' => $request->input('resource_type', 'external_link'),
                    'title'         => $request->input('resource_title'),
                    'external_url'  => $request->input('external_url') ?: null,
                    'notes'         => $request->input('resource_notes') ?: null,
                    'added_at'      => now(),
                ]);
                return redirect()->route('research.viewProject', $id)->with('success', 'Resource linked.');
            }

            if ($action === 'remove_resource') {
                DB::table('research_project_resource')
                    ->where('id', $request->input('resource_id'))
                    ->where('project_id', $id)
                    ->delete();
                return redirect()->route('research.viewProject', $id)->with('success', 'Resource removed.');
            }

            if ($action === 'remove_collaborator') {
                DB::table('research_project_collaborator')
                    ->where('project_id', $id)
                    ->where('researcher_id', $request->input('collaborator_researcher_id'))
                    ->where('role', '!=', 'owner')
                    ->delete();
                return redirect()->route('research.viewProject', $id)->with('success', 'Collaborator removed.');
            }
        }

        $collaborators = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $id)
            ->select('pc.*', 'r.first_name', 'r.last_name', 'r.email')
            ->get()->toArray();

        $resources = DB::table('research_project_resource')
            ->where('project_id', $id)
            ->orderBy('added_at', 'desc')
            ->get()->toArray();

        $milestones = DB::table('research_project_milestone')
            ->where('project_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        $activities = DB::table('research_activity_log')
            ->where('project_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()->toArray();

        $reports = DB::table('research_report')
            ->where('project_id', $id)
            ->orderBy('updated_at', 'desc')
            ->get()->toArray();

        // Research OS #1225 - the per-project Command Centre journey (where am I /
        // what's next). Read-only + fully guarded; degrades to an empty journey
        // rather than breaking the project page.
        $journey = [];
        $journeyProgress = ['done' => 0, 'total' => 0, 'pct' => 0, 'next' => null];
        try {
            $cc = app(\AhgResearch\Services\CommandCentreService::class);
            $journey = $cc->journey((int) $id, (int) ($researcher->id ?? 0));
            $journeyProgress = $cc->progress($journey);
        } catch (\Throwable $e) {
            // leave the journey empty - the panel simply does not render
        }

        return view('research::research.view-project', array_merge(
            $this->getSidebarData('projects'),
            compact('researcher', 'project', 'collaborators', 'resources', 'milestones', 'activities', 'reports', 'journey', 'journeyProgress')
        ));
    }

    public function createProject()
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $status = null;
        $projects = DB::table('research_project as p')
            ->where(function ($q) use ($researcher) {
                $q->where('p.owner_id', $researcher->id)
                  ->orWhereExists(function ($sub) use ($researcher) {
                      $sub->select(DB::raw(1))
                          ->from('research_project_collaborator')
                          ->whereColumn('research_project_collaborator.project_id', 'p.id')
                          ->where('research_project_collaborator.researcher_id', $researcher->id)
                          ->where('research_project_collaborator.status', 'accepted');
                  });
            })
            ->orderBy('p.created_at', 'desc')->get()->toArray();

        return view('research::research.projects', array_merge(
            $this->getSidebarData('projects'),
            compact('researcher', 'projects', 'status'),
            ['showCreateForm' => true]
        ));
    }

    public function storeProject(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $projectId = DB::table('research_project')->insertGetId([
            'owner_id' => $researcher->id,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'project_type' => $request->input('project_type', 'personal'),
            'institution' => $request->input('institution'),
            'start_date' => $request->input('start_date'),
            'expected_end_date' => $request->input('expected_end_date'),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        DB::table('research_project_collaborator')->insert([
            'project_id' => $projectId,
            'researcher_id' => $researcher->id,
            'role' => 'owner',
            'status' => 'accepted',
            'invited_at' => date('Y-m-d H:i:s'),
            'accepted_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->route('research.viewProject', $projectId)->with('success', 'Project created');
    }

    /**
     * Resolve [project, researcher] for a project id. Copied verbatim from
     * ResearchController (issue #1269 project-subsystem extraction). The sibling
     * ResearchProjectOutputsController carries its own identical copy.
     */
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
