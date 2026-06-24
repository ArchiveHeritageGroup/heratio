<?php

/**
 * ResearchEthicsController - Heratio ahg-research
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

use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\ResearchEthicsService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1222 - Research OS: Research Ethics & Consent register.
 *
 * List / create / edit / show / delete a research project's ethics approvals and
 * the consent basis for its human-subject / sensitive data, show a per-project
 * summary (counts by status, with an expiring-soon flag), and export the
 * project's ethics records as a machine-readable .json document.
 *
 * Self-contained: resolves project + researcher locally, mirroring
 * ResearchOutputController / DmpController, never edits getSidebarData. Every
 * action is empty-state safe and degrades cleanly when the slice is not
 * installed. Jurisdiction-neutral throughout - consent terms are generic
 * governance concepts, never one country's regime.
 */
class ResearchEthicsController extends Controller
{
    use LogsResearchActivity;

    public function __construct(
        private ResearchEthicsService $ethics,
        private ResearchService $research,
    ) {}

    /** Ethics records on a project + per-project summary. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $records         = $this->ethics->listRecords($projectId);
        $summary         = $this->ethics->summary($projectId);
        $typeOptions     = $this->ethics->approvalTypeOptions();
        $statusOptions   = $this->ethics->statusOptions();
        $consentOptions  = $this->ethics->consentBasisOptions();
        $sensOptions     = $this->ethics->dataSensitivityOptions();

        // Pre-compute each record's expiry flag for the list.
        $flags = [];
        foreach ($records as $r) {
            $flags[$r['id']] = $this->ethics->expiryFlag($r);
        }

        return view('research::ethics.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'records', 'summary', 'typeOptions', 'statusOptions', 'consentOptions', 'sensOptions', 'flags')
        ));
    }

    /** New-record form. */
    public function create(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $record         = null;
        $typeOptions    = $this->ethics->approvalTypeOptions();
        $statusOptions  = $this->ethics->statusOptions();
        $consentOptions = $this->ethics->consentBasisOptions();
        $sensOptions    = $this->ethics->dataSensitivityOptions();
        $dmpOptions     = $this->ethics->dmpOptions($projectId);

        return view('research::ethics.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'record', 'typeOptions', 'statusOptions', 'consentOptions', 'sensOptions', 'dmpOptions')
        ));
    }

    /** Persist a new ethics record. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $this->validateRecord($request);

        $id = $this->ethics->createRecord($projectId, $researcher ? (int) $researcher->id : null, $data);

        if (! $id) {
            return redirect()->route('research.ethics.index', $projectId)
                ->with('error', 'Could not create the ethics record. Please try again.');
        }

        $this->logResearchActivity('create', 'ethics', (int) $id, $data['title'] ?? null, ['method' => 'ResearchEthicsController@store'], $projectId);

        return redirect()->route('research.ethics.show', [$projectId, $id])
            ->with('success', 'Ethics record saved.');
    }

    /** Edit-record form. */
    public function edit(int $projectId, int $ethicsId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $record = $this->ethics->getRecord($ethicsId, $projectId);
        if (! $record) {
            return redirect()->route('research.ethics.index', $projectId)
                ->with('error', 'Ethics record not found.');
        }

        $typeOptions    = $this->ethics->approvalTypeOptions();
        $statusOptions  = $this->ethics->statusOptions();
        $consentOptions = $this->ethics->consentBasisOptions();
        $sensOptions    = $this->ethics->dataSensitivityOptions();
        $dmpOptions     = $this->ethics->dmpOptions($projectId);

        return view('research::ethics.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'record', 'typeOptions', 'statusOptions', 'consentOptions', 'sensOptions', 'dmpOptions')
        ));
    }

    /** Persist edits to an ethics record. */
    public function update(Request $request, int $projectId, int $ethicsId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $existing = $this->ethics->getRecord($ethicsId, $projectId);
        if (! $existing) {
            return redirect()->route('research.ethics.index', $projectId)
                ->with('error', 'Ethics record not found.');
        }

        $data = $this->validateRecord($request);

        $ok = $this->ethics->updateRecord($ethicsId, $projectId, $data);

        if (! $ok) {
            return redirect()->route('research.ethics.index', $projectId)
                ->with('error', 'Could not save the ethics record.');
        }

        $this->logResearchActivity('update', 'ethics', (int) $ethicsId, $data['title'] ?? null, ['method' => 'ResearchEthicsController@update'], $projectId);

        return redirect()->route('research.ethics.show', [$projectId, $ethicsId])
            ->with('success', 'Ethics record saved.');
    }

    /** Read-only record detail. */
    public function show(int $projectId, int $ethicsId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $record = $this->ethics->getRecord($ethicsId, $projectId);
        if (! $record) {
            return redirect()->route('research.ethics.index', $projectId)
                ->with('error', 'Ethics record not found.');
        }

        $typeOptions    = $this->ethics->approvalTypeOptions();
        $statusOptions  = $this->ethics->statusOptions();
        $consentOptions = $this->ethics->consentBasisOptions();
        $sensOptions    = $this->ethics->dataSensitivityOptions();
        $expiryFlag     = $this->ethics->expiryFlag($record);
        $dmp            = $this->resolveDmp($record, $projectId);

        return view('research::ethics.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'record', 'typeOptions', 'statusOptions', 'consentOptions', 'sensOptions', 'expiryFlag', 'dmp')
        ));
    }

    /** Delete an ethics record. */
    public function destroy(int $projectId, int $ethicsId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $ok = $this->ethics->deleteRecord($ethicsId, $projectId);

        if ($ok) {
            $this->logResearchActivity('delete', 'ethics', (int) $ethicsId, null, ['method' => 'ResearchEthicsController@destroy'], $projectId);
        }

        return redirect()->route('research.ethics.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Ethics record deleted.' : 'Could not delete the record.');
    }

    /**
     * Machine-readable export of the project's ethics records. Returns a
     * downloadable JSON document - each record with its approval type, status,
     * committee, reference, dates, consent basis and data-sensitivity.
     */
    public function exportJson(int $projectId)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }
        [$project] = $this->projectContext($projectId);

        $records = $this->ethics->listRecords($projectId);
        $payload = $this->ethics->buildExport($records, $project);

        $filename = 'research-ethics-project-' . $projectId . '.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function validateRecord(Request $request): array
    {
        return $request->validate([
            'title'            => 'required|string|max:512',
            'approval_type'    => 'required|string|max:32',
            'reference_number' => 'nullable|string|max:128',
            'committee_name'   => 'nullable|string|max:512',
            'status'           => 'required|string|max:32',
            'decision_date'    => 'nullable|date',
            'expiry_date'      => 'nullable|date',
            'consent_basis'    => 'required|string|max:32',
            'data_sensitivity' => 'required|string|max:32',
            'notes'            => 'nullable|string|max:65000',
            'dmp_id'           => 'nullable|integer|min:1',
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
     * Resolve the linked DMP (sibling slice) for a record, scoped to the
     * project. Returns null when there is no link or the sibling slice is absent.
     *
     * @param  array<string,mixed>  $record
     * @return object|null
     */
    private function resolveDmp(array $record, int $projectId): ?object
    {
        $dmpId = $record['dmp_id'] ?? null;
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
