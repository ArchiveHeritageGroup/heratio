<?php

/**
 * ResearchFundingController - Heratio ahg-research
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

use AhgResearch\Services\ResearchFundingService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1222 - Research OS: Research Funding tracker.
 *
 * List / create / edit / show / delete a research project's funding lines (the
 * AWARDED-FUNDING ledger - sources, amounts, currencies, award periods), show a
 * per-project summary (awarded totals PER CURRENCY - never cross-summed - plus
 * counts by status and an active-now count), and export the project's funding as
 * a machine-readable .json document.
 *
 * Self-contained: resolves project + researcher locally, mirroring
 * ResearchEthicsController / DmpController, never edits getSidebarData. Every
 * action is empty-state safe and degrades cleanly when the slice is not
 * installed. Jurisdiction-neutral throughout - no currency or funder country is
 * assumed or defaulted. Distinct from the grant-DRAFTING slice.
 */
class ResearchFundingController extends Controller
{
    public function __construct(
        private ResearchFundingService $funding,
        private ResearchService $research,
    ) {}

    /** Funding records on a project + per-project summary. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $records         = $this->funding->listRecords($projectId);
        $summary         = $this->funding->summary($projectId);
        $typeOptions     = $this->funding->funderTypeOptions();
        $statusOptions   = $this->funding->statusOptions();
        $currencyOptions = $this->funding->currencyOptions();

        // Pre-compute each record's active-now flag for the list.
        $active = [];
        foreach ($records as $r) {
            $active[$r['id']] = $this->funding->isActiveNow($r);
        }

        return view('research::funding.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'records', 'summary', 'typeOptions', 'statusOptions', 'currencyOptions', 'active')
        ));
    }

    /** New-record form. */
    public function create(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $record          = null;
        $typeOptions     = $this->funding->funderTypeOptions();
        $statusOptions   = $this->funding->statusOptions();
        $currencyOptions = $this->funding->currencyOptions();
        $dmpOptions      = $this->funding->dmpOptions($projectId);

        return view('research::funding.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'record', 'typeOptions', 'statusOptions', 'currencyOptions', 'dmpOptions')
        ));
    }

    /** Persist a new funding record. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $this->validateRecord($request);

        $id = $this->funding->createRecord($projectId, $researcher ? (int) $researcher->id : null, $data);

        if (! $id) {
            return redirect()->route('research.funding.index', $projectId)
                ->with('error', 'Could not create the funding record. Please try again.');
        }

        return redirect()->route('research.funding.show', [$projectId, $id])
            ->with('success', 'Funding record saved.');
    }

    /** Edit-record form. */
    public function edit(int $projectId, int $fundingId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $record = $this->funding->getRecord($fundingId, $projectId);
        if (! $record) {
            return redirect()->route('research.funding.index', $projectId)
                ->with('error', 'Funding record not found.');
        }

        $typeOptions     = $this->funding->funderTypeOptions();
        $statusOptions   = $this->funding->statusOptions();
        $currencyOptions = $this->funding->currencyOptions();
        $dmpOptions      = $this->funding->dmpOptions($projectId);

        return view('research::funding.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'record', 'typeOptions', 'statusOptions', 'currencyOptions', 'dmpOptions')
        ));
    }

    /** Persist edits to a funding record. */
    public function update(Request $request, int $projectId, int $fundingId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $existing = $this->funding->getRecord($fundingId, $projectId);
        if (! $existing) {
            return redirect()->route('research.funding.index', $projectId)
                ->with('error', 'Funding record not found.');
        }

        $data = $this->validateRecord($request);

        $ok = $this->funding->updateRecord($fundingId, $projectId, $data);

        if (! $ok) {
            return redirect()->route('research.funding.index', $projectId)
                ->with('error', 'Could not save the funding record.');
        }

        return redirect()->route('research.funding.show', [$projectId, $fundingId])
            ->with('success', 'Funding record saved.');
    }

    /** Read-only record detail. */
    public function show(int $projectId, int $fundingId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $record = $this->funding->getRecord($fundingId, $projectId);
        if (! $record) {
            return redirect()->route('research.funding.index', $projectId)
                ->with('error', 'Funding record not found.');
        }

        $typeOptions     = $this->funding->funderTypeOptions();
        $statusOptions   = $this->funding->statusOptions();
        $currencyOptions = $this->funding->currencyOptions();
        $isActive        = $this->funding->isActiveNow($record);
        $dmp             = $this->resolveDmp($record, $projectId);

        return view('research::funding.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'record', 'typeOptions', 'statusOptions', 'currencyOptions', 'isActive', 'dmp')
        ));
    }

    /** Delete a funding record. */
    public function destroy(int $projectId, int $fundingId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $ok = $this->funding->deleteRecord($fundingId, $projectId);

        return redirect()->route('research.funding.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Funding record deleted.' : 'Could not delete the record.');
    }

    /**
     * Machine-readable export of the project's funding records. Returns a
     * downloadable JSON document - each record with its funder, amount, currency,
     * status and award period, plus awarded totals grouped PER CURRENCY (never
     * cross-summed).
     */
    public function exportJson(int $projectId)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }
        [$project] = $this->projectContext($projectId);

        $records = $this->funding->listRecords($projectId);
        $summary = $this->funding->summary($projectId);
        $payload = $this->funding->buildExport($records, $project, $summary);

        $filename = 'research-funding-project-' . $projectId . '.json';

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
        // Currency must be one of the configured ISO 4217 dropdown codes - no
        // hardcoded country default; the rule is built from the live taxonomy.
        $currencyCodes = array_keys($this->funding->currencyOptions());

        return $request->validate([
            'title'           => 'required|string|max:512',
            'funder_name'     => 'required|string|max:512',
            'funder_type'     => 'required|string|max:32',
            'award_reference' => 'nullable|string|max:128',
            'amount'          => 'nullable|numeric|min:0|max:999999999999.99',
            'currency'        => 'required|string|in:' . implode(',', $currencyCodes),
            'status'          => 'required|string|max:32',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
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
