<?php

/**
 * DmpController - Heratio ahg-research
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

use AhgResearch\Concerns\AuthorizesProjectAccess;
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\DmpService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1222 - Research OS: Data Management Plan (DMP) Builder.
 *
 * List / create / edit / show DMPs scoped to a research project, fill the maDMP
 * sections, view a completeness indicator, and export a machine-readable,
 * RDA-aligned maDMP JSON document at a .json endpoint.
 *
 * Self-contained: resolves project + researcher locally, mirroring
 * GrantEngineController, never edits getSidebarData. Every action is empty-state
 * safe and degrades cleanly when the slice is not installed.
 */
class DmpController extends Controller
{
    use LogsResearchActivity;
    use AuthorizesProjectAccess;

    public function __construct(
        private DmpService $dmp,
        private ResearchService $research,
    ) {}

    /** DMPs on a project + a "new plan" form + completeness per plan. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $plans         = $this->dmp->listPlans($projectId);
        $statusOptions = $this->dmp->statusOptions();
        $funderOptions = $this->dmp->funderTemplateOptions();

        // Lightweight completeness per plan for the list badges.
        $completeness = [];
        foreach ($plans as $p) {
            $completeness[$p['id']] = $this->dmp->completeness($this->dmp->getSections($p['id']));
        }

        return view('research::dmp.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'plans', 'statusOptions', 'funderOptions', 'completeness')
        ));
    }

    /** Create a DMP, seed maDMP sections, then open the editor. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $request->validate([
            'title'           => 'nullable|string|max:255',
            'funder'          => 'nullable|string|max:255',
            'funder_template' => 'nullable|string|max:64',
            'language'        => 'nullable|string|max:12',
            'contact_name'    => 'nullable|string|max:255',
            'contact_email'   => 'nullable|email|max:255',
        ]);

        $id = $this->dmp->createPlan($projectId, $researcher ? (int) $researcher->id : null, $data);

        if (! $id) {
            return redirect()->route('research.dmp.index', $projectId)
                ->with('error', 'Could not create the data management plan. Please try again.');
        }

        $this->logResearchActivity('create', 'dmp', (int) $id, $data['title'] ?? null, ['method' => 'DmpController@store'], $projectId);

        return redirect()->route('research.dmp.edit', [$projectId, $id])
            ->with('success', 'Data management plan created. Fill in each maDMP section below.');
    }

    /** Section-by-section plan editor + completeness. */
    public function edit(int $projectId, int $dmpId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $plan = $this->dmp->getPlan($dmpId, $projectId);
        if (! $plan) {
            return redirect()->route('research.dmp.index', $projectId)
                ->with('error', 'Data management plan not found.');
        }

        $sections      = $this->dmp->getSections($dmpId);
        $statusOptions = $this->dmp->statusOptions();
        $funderOptions = $this->dmp->funderTemplateOptions();
        $completeness  = $this->dmp->completeness($sections);

        return view('research::dmp.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'plan', 'sections', 'statusOptions', 'funderOptions', 'completeness')
        ));
    }

    /** Persist editor changes (meta + per-section bodies). */
    public function update(Request $request, int $projectId, int $dmpId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $data = $request->validate([
            'title'           => 'nullable|string|max:255',
            'status'          => 'nullable|string|max:32',
            'funder'          => 'nullable|string|max:255',
            'funder_template' => 'nullable|string|max:64',
            'language'        => 'nullable|string|max:12',
            'contact_name'    => 'nullable|string|max:255',
            'contact_email'   => 'nullable|email|max:255',
            'sections'        => 'nullable|array',
            'sections.*'      => 'nullable|string|max:65000',
        ]);

        $this->dmp->savePlanMeta($dmpId, $projectId, [
            'title'           => $data['title'] ?? null,
            'status'          => $data['status'] ?? null,
            'funder'          => array_key_exists('funder', $data) ? $data['funder'] : null,
            'funder_template' => array_key_exists('funder_template', $data) ? $data['funder_template'] : null,
            'language'        => $data['language'] ?? null,
            'contact_name'    => array_key_exists('contact_name', $data) ? $data['contact_name'] : null,
            'contact_email'   => array_key_exists('contact_email', $data) ? $data['contact_email'] : null,
        ]);

        $bodies = [];
        foreach (($data['sections'] ?? []) as $sectionId => $text) {
            $bodies[(int) $sectionId] = is_string($text) ? $text : '';
        }
        $ok = $this->dmp->saveSections($dmpId, $projectId, $bodies);

        if (! $ok) {
            return redirect()->route('research.dmp.index', $projectId)
                ->with('error', 'Could not save the data management plan.');
        }

        $this->logResearchActivity('update', 'dmp', $dmpId, $data['title'] ?? null, ['method' => 'DmpController@update'], $projectId);

        return redirect()->route('research.dmp.edit', [$projectId, $dmpId])
            ->with('success', 'Data management plan saved.');
    }

    /** Read-only assembled plan (print / review view) + completeness. */
    public function show(int $projectId, int $dmpId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $plan = $this->dmp->getPlan($dmpId, $projectId);
        if (! $plan) {
            return redirect()->route('research.dmp.index', $projectId)
                ->with('error', 'Data management plan not found.');
        }

        $sections      = $this->dmp->getSections($dmpId);
        $statusOptions = $this->dmp->statusOptions();
        $funderOptions = $this->dmp->funderTemplateOptions();
        $completeness  = $this->dmp->completeness($sections);

        return view('research::dmp.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'plan', 'sections', 'statusOptions', 'funderOptions', 'completeness')
        ));
    }

    /** Delete a plan and its sections. */
    public function destroy(int $projectId, int $dmpId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $ok = $this->dmp->deletePlan($dmpId, $projectId);

        if ($ok) {
            $this->logResearchActivity('delete', 'dmp', $dmpId, null, ['method' => 'DmpController@destroy'], $projectId);
        }

        return redirect()->route('research.dmp.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Data management plan deleted.' : 'Could not delete the plan.');
    }

    /**
     * Machine-readable maDMP export (RDA / Science Europe aligned). Returns a
     * downloadable JSON document. 404 (as JSON) when the plan is missing.
     */
    public function exportJson(int $projectId, int $dmpId)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }
        [$project] = $this->projectContext($projectId);

        $plan = $this->dmp->getPlan($dmpId, $projectId);
        if (! $plan) {
            return response()->json(['error' => 'Data management plan not found.'], 404);
        }

        $sections = $this->dmp->getSections($dmpId);
        $madmp    = $this->dmp->buildMadmp($plan, $sections, $project);

        $filename = 'madmp-project-' . $projectId . '-plan-' . $dmpId . '.json';

        return response()->json($madmp, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
        // SECURITY (#1308-parity): authorize the caller against the resolved project.
        $this->assertProjectMember($projectId, (int) $researcher->id);

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
