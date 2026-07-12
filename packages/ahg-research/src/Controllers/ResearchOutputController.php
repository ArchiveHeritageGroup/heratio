<?php

/**
 * ResearchOutputController - Heratio ahg-research
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
use AhgResearch\Services\ResearchOutputService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1222 - Research OS: Research Outputs register (CRIS / RIM).
 *
 * List / create / edit / show / delete the scholarly outputs a research project
 * produces, render each output's persistent identifier as a resolvable link,
 * show a per-project summary (counts by type), and export the project's outputs
 * as a machine-readable .json document.
 *
 * Self-contained: resolves project + researcher locally, mirroring
 * DmpController / GrantEngineController, never edits getSidebarData. Every action
 * is empty-state safe and degrades cleanly when the slice is not installed.
 */
class ResearchOutputController extends Controller
{
    use LogsResearchActivity;
    use AuthorizesProjectAccess;

    public function __construct(
        private ResearchOutputService $outputs,
        private ResearchService $research,
    ) {}

    /** Outputs on a project + per-project summary. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $outputs       = $this->outputs->listOutputs($projectId);
        $summary       = $this->outputs->summary($projectId);
        $typeOptions   = $this->outputs->typeOptions();
        $statusOptions = $this->outputs->statusOptions();

        // Pre-resolve each output's link for the list.
        $resolved = [];
        foreach ($outputs as $o) {
            $resolved[$o['id']] = $this->outputs->resolveUrl($o);
        }

        return view('research::outputs.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'outputs', 'summary', 'typeOptions', 'statusOptions', 'resolved')
        ));
    }

    /** New-output form. */
    public function create(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $output            = null;
        $typeOptions       = $this->outputs->typeOptions();
        $identifierOptions = $this->outputs->identifierTypeOptions();
        $statusOptions     = $this->outputs->statusOptions();
        $dmpOptions        = $this->outputs->dmpOptions($projectId);

        return view('research::outputs.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'output', 'typeOptions', 'identifierOptions', 'statusOptions', 'dmpOptions')
        ));
    }

    /** Persist a new output. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $this->validateOutput($request);

        $id = $this->outputs->createOutput($projectId, $researcher ? (int) $researcher->id : null, $data);

        if (! $id) {
            return redirect()->route('research.outputs.index', $projectId)
                ->with('error', 'Could not create the research output. Please try again.');
        }

        $this->logResearchActivity('create', 'output', (int) $id, $data['title'] ?? null, ['method' => 'ResearchOutputController@store'], $projectId);

        return redirect()->route('research.outputs.show', [$projectId, $id])
            ->with('success', 'Research output recorded.');
    }

    /** Edit-output form. */
    public function edit(int $projectId, int $outputId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $output = $this->outputs->getOutput($outputId, $projectId);
        if (! $output) {
            return redirect()->route('research.outputs.index', $projectId)
                ->with('error', 'Research output not found.');
        }

        $typeOptions       = $this->outputs->typeOptions();
        $identifierOptions = $this->outputs->identifierTypeOptions();
        $statusOptions     = $this->outputs->statusOptions();
        $dmpOptions        = $this->outputs->dmpOptions($projectId);

        return view('research::outputs.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'output', 'typeOptions', 'identifierOptions', 'statusOptions', 'dmpOptions')
        ));
    }

    /** Persist edits to an output. */
    public function update(Request $request, int $projectId, int $outputId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $existing = $this->outputs->getOutput($outputId, $projectId);
        if (! $existing) {
            return redirect()->route('research.outputs.index', $projectId)
                ->with('error', 'Research output not found.');
        }

        $data = $this->validateOutput($request);

        $ok = $this->outputs->updateOutput($outputId, $projectId, $data);

        if (! $ok) {
            return redirect()->route('research.outputs.index', $projectId)
                ->with('error', 'Could not save the research output.');
        }

        $this->logResearchActivity('update', 'output', $outputId, $data['title'] ?? null, ['method' => 'ResearchOutputController@update'], $projectId);

        return redirect()->route('research.outputs.show', [$projectId, $outputId])
            ->with('success', 'Research output saved.');
    }

    /** Read-only output detail with resolvable identifier link. */
    public function show(int $projectId, int $outputId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $output = $this->outputs->getOutput($outputId, $projectId);
        if (! $output) {
            return redirect()->route('research.outputs.index', $projectId)
                ->with('error', 'Research output not found.');
        }

        $typeOptions       = $this->outputs->typeOptions();
        $identifierOptions = $this->outputs->identifierTypeOptions();
        $statusOptions     = $this->outputs->statusOptions();
        $resolvedUrl       = $this->outputs->resolveUrl($output);
        $dmp               = $this->resolveDmp($output, $projectId);

        return view('research::outputs.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'output', 'typeOptions', 'identifierOptions', 'statusOptions', 'resolvedUrl', 'dmp')
        ));
    }

    /** Delete an output. */
    public function destroy(int $projectId, int $outputId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $ok = $this->outputs->deleteOutput($outputId, $projectId);

        if ($ok) {
            $this->logResearchActivity('delete', 'output', $outputId, null, ['method' => 'ResearchOutputController@destroy'], $projectId);
        }

        return redirect()->route('research.outputs.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Research output deleted.' : 'Could not delete the output.');
    }

    /**
     * Machine-readable export of the project's outputs. Returns a downloadable
     * JSON document - each output with its type, title, identifier, resolvable
     * URL and date.
     */
    public function exportJson(int $projectId)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }
        [$project] = $this->projectContext($projectId);

        $outputs = $this->outputs->listOutputs($projectId);
        $payload = $this->outputs->buildExport($outputs, $project);

        $filename = 'research-outputs-project-' . $projectId . '.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function validateOutput(Request $request): array
    {
        return $request->validate([
            'output_type'     => 'required|string|max:32',
            'title'           => 'required|string|max:512',
            'authors'         => 'nullable|string|max:1024',
            'venue'           => 'nullable|string|max:512',
            'identifier_type' => 'nullable|string|max:32',
            'identifier'      => 'nullable|string|max:512',
            'identifier_url'  => 'nullable|url|max:1024',
            'output_date'     => 'nullable|date',
            'status'          => 'required|string|max:32',
            'notes'           => 'nullable|string|max:65000',
            'dmp_id'          => 'nullable|integer|min:1',
        ]);
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

    /**
     * Resolve the linked DMP (sibling slice) for an output, scoped to the
     * project. Returns null when there is no link or the sibling slice is absent.
     *
     * @param  array<string,mixed>  $output
     * @return object|null
     */
    private function resolveDmp(array $output, int $projectId): ?object
    {
        $dmpId = $output['dmp_id'] ?? null;
        if (! $dmpId) {
            return null;
        }
        try {
            if (! Schema::hasTable('research_dmp')) {
                return null;
            }

            return DB::table('research_dmp')
                ->where('id', (int) $dmpId)
                ->where('project_id', $projectId)
                ->first();
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
