<?php

/**
 * ContradictionEngineController - Controller for Heratio
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
use AhgResearch\Services\ContradictionEngineService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ContradictionEngineController - Research OS moonshot 17 (heratio#1236).
 *
 * Per-project Contradiction Engine over the Claim Ledger. The report lists every
 * detected contradiction with the two claims linked back to the ledger, and lets
 * the user run a fresh heuristic scan, trigger an optional AI deepening pass (via
 * the gateway only, never automatic), and dismiss/resolve individual findings.
 *
 * Auth-gated; every action resolves the researcher + project defensively and never
 * 500s - the report falls back to an empty state when tables are missing.
 */
class ContradictionEngineController extends Controller
{
    protected ContradictionEngineService $engine;
    protected ResearchService $research;

    public function __construct()
    {
        $this->engine = new ContradictionEngineService();
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

    /** Contradictions report for a project. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $statusFilter = (string) $request->input('status', 'open');
        $findings     = $this->engine->listFindings($projectId, $statusFilter === 'all' ? 'all' : $statusFilter);
        $statusCounts = $this->engine->statusCounts($projectId);

        return view('research::research.contradictions.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'findings', 'statusCounts', 'statusFilter'),
            [
                'kinds'          => ContradictionEngineService::KINDS,
                'severityBadges' => ContradictionEngineService::SEVERITY_BADGES,
                'statusBadges'   => ContradictionEngineService::STATUS_BADGES,
                'aiAvailable'    => class_exists(\AhgAiServices\Services\LlmService::class),
            ]
        ));
    }

    /** Run a fresh heuristic scan (POST). */
    public function scan(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $result = $this->engine->scan($projectId);

        if (! $result['ok']) {
            return redirect()->route('research.contradictions.index', $projectId)
                ->with('error', $result['error'] ?? 'The scan could not complete.');
        }

        $msg = 'Scan complete. '.$result['scanned'].' claim(s) reviewed, '
            .$result['found'].' contradiction(s) detected'
            .($result['persisted'] > 0 ? ' ('.$result['persisted'].' recorded or refreshed).' : '.');

        return redirect()->route('research.contradictions.index', $projectId)
            ->with('success', $msg);
    }

    /** Optional AI deepening pass via the gateway (POST, user-triggered only). */
    public function aiScan(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $result = $this->engine->aiDeepen($projectId);

        return redirect()->route('research.contradictions.index', $projectId)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /** Dismiss a finding (POST). */
    public function dismiss(Request $request, int $projectId, int $findingId)
    {
        return $this->transition($projectId, $findingId, 'dismissed', 'Contradiction dismissed.');
    }

    /** Resolve a finding (POST). */
    public function resolve(Request $request, int $projectId, int $findingId)
    {
        return $this->transition($projectId, $findingId, 'resolved', 'Contradiction marked resolved.');
    }

    /** Reopen a finding (POST). */
    public function reopen(Request $request, int $projectId, int $findingId)
    {
        return $this->transition($projectId, $findingId, 'open', 'Contradiction reopened.');
    }

    /** Shared status transition helper. */
    protected function transition(int $projectId, int $findingId, string $status, string $okMessage)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->engine->setStatus($projectId, $findingId, $status);

        return redirect()->route('research.contradictions.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? $okMessage : 'Could not update the finding.');
    }
}
