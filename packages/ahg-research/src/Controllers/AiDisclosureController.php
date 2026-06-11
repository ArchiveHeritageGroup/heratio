<?php

/**
 * AiDisclosureController - Controller for Heratio
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
use AhgResearch\Services\AiDisclosureService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AiDisclosureController - Research OS Part IV "AI Containment" (heratio#1242).
 *
 * Per-project AI-use disclosure. The page aggregates the project's AI usage from
 * existing slices (read-only) plus a manual interaction log, and generates a
 * one-click AI Disclosure Statement suitable for a journal's AI-use statement.
 *
 * Auth-gated; every action resolves the researcher + project defensively and
 * never 500s - the page degrades to an empty state when AI tables are absent.
 */
class AiDisclosureController extends Controller
{
    protected AiDisclosureService $disclosure;
    protected ResearchService $research;

    public function __construct()
    {
        $this->disclosure = new AiDisclosureService();
        $this->research   = new ResearchService();
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

    /** AI disclosure page: detected + logged usage and the generated statement. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $lines     = $this->disclosure->gather($projectId);
        $statement = $this->disclosure->buildStatement($project, $lines);

        return view('research::research.ai-disclosure.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'lines', 'statement'),
            [
                'detectedCount' => count(array_filter($lines, fn ($l) => ($l['source'] ?? '') === 'detected')),
                'loggedCount'   => count(array_filter($lines, fn ($l) => ($l['source'] ?? '') === 'logged')),
                'aiAvailable'   => class_exists(\AhgAiServices\Services\LlmService::class),
            ]
        ));
    }

    /** Add a manual interaction-log entry (POST). The only write this slice makes. */
    public function logStore(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'tool'       => ['required', 'string', 'max:160'],
            'model'      => ['nullable', 'string', 'max:160'],
            'purpose'    => ['required', 'string', 'max:2000'],
            'output_ref' => ['nullable', 'string', 'max:500'],
        ]);

        $ok = $this->disclosure->addLogEntry($projectId, [
            'tool'       => $validated['tool'],
            'model'      => $validated['model'] ?? null,
            'purpose'    => $validated['purpose'],
            'output_ref' => $validated['output_ref'] ?? null,
        ], Auth::id());

        return redirect()->route('research.aidisclosure.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok
                ? 'AI interaction recorded.'
                : 'The interaction could not be recorded. Please try again.');
    }

    /** Delete a manual interaction-log entry (POST). */
    public function logDestroy(Request $request, int $projectId, int $entryId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->disclosure->deleteLogEntry($projectId, $entryId);

        return redirect()->route('research.aidisclosure.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok
                ? 'Log entry removed.'
                : 'The log entry could not be removed.');
    }

    /** Download the generated statement as a plain-text file. */
    public function statementDownload(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $lines     = $this->disclosure->gather($projectId);
        $statement  = $this->disclosure->buildStatement($project, $lines);

        $slug = preg_replace('/[^a-z0-9]+/i', '-', (string) ($project->title ?? 'project'));
        $slug = trim((string) $slug, '-');
        $slug = $slug !== '' ? strtolower($slug) : 'project';
        $filename = 'ai-disclosure-'.$slug.'.txt';

        return response($statement, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
