<?php

/**
 * ProjectExportController - Heratio ahg-research
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
use AhgResearch\Services\ProjectExportService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * heratio#1237 - Research OS #15: Open-format project export.
 *
 * Founding principle: "no lock-in / the exit door is always open." Gives the
 * researcher a one-click export of a single project's whole intellectual record
 * to open, non-proprietary formats: a single ZIP bundle (Markdown + JSON +
 * BibTeX + RIS + CSL-JSON), or any one format on its own.
 *
 * Read-only over every existing research table. The only write is an optional
 * audit row in research_export_log (best-effort). Access is gated to the project
 * owner, its collaborators, and admins. Every action degrades to an empty state
 * or a clean error redirect - never a 500.
 */
class ProjectExportController extends Controller
{
    public function __construct(
        private ProjectExportService $export,
        private ResearchService $research,
    ) {}

    // =========================================================================
    // Export landing page (what is included, format buttons)
    // =========================================================================

    public function index(Request $request, int $projectId)
    {
        [$project, $researcher] = $this->guard($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        // Assemble once so the page can show exactly what the bundle will hold.
        $bundle   = $this->export->assemble($projectId);
        $manifest = $bundle['manifest'] ?? ['included' => [], 'omitted' => []];

        // Recent exports for this project (optional log table).
        $recent = [];
        try {
            if (Schema::hasTable('research_export_log')) {
                $recent = DB::table('research_export_log')
                    ->where('project_id', $projectId)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()->all();
            }
        } catch (\Throwable $e) {
            $recent = [];
        }

        return view('research::research.project-export', array_merge(
            $this->sidebar(),
            [
                'project'    => $project,
                'researcher' => $researcher,
                'manifest'   => $manifest,
                'recent'     => $recent,
            ]
        ));
    }

    // =========================================================================
    // ZIP bundle (one-click full export)
    // =========================================================================

    public function zip(Request $request, int $projectId)
    {
        [$project, $researcher] = $this->guard($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $bundle = $this->export->assemble($projectId);
        $path   = $this->export->buildZip($projectId, $bundle);

        if ($path === null) {
            return redirect()
                ->route('research.export.index', $projectId)
                ->with('error', __('The ZIP bundle could not be built (the ZIP extension may be unavailable, or the export folder is not writable). You can still download each format individually below.'));
        }

        $this->export->logExport($projectId, 'zip', $this->exporterName($researcher));

        $download = $this->export->formatFilename($bundle, 'markdown', $projectId);
        $download = preg_replace('/\.md$/', '.zip', $download);

        // Stream then delete the temp bundle - never leave it behind.
        return response()->download($path, $download, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    // =========================================================================
    // Single-format downloads
    // =========================================================================

    public function markdown(Request $request, int $projectId)
    {
        return $this->streamFormat($projectId, 'markdown', 'text/markdown; charset=UTF-8', fn ($b) => $this->export->toMarkdown($b));
    }

    public function json(Request $request, int $projectId)
    {
        return $this->streamFormat($projectId, 'json', 'application/json; charset=UTF-8', fn ($b) => $this->export->toJson($b));
    }

    public function bibtex(Request $request, int $projectId)
    {
        return $this->streamFormat($projectId, 'bibtex', 'application/x-bibtex; charset=UTF-8', function ($b) {
            return $this->export->toBibtex($this->export->flattenSources($b));
        });
    }

    public function ris(Request $request, int $projectId)
    {
        return $this->streamFormat($projectId, 'ris', 'application/x-research-info-systems; charset=UTF-8', function ($b) {
            return $this->export->toRis($this->export->flattenSources($b));
        });
    }

    public function csl(Request $request, int $projectId)
    {
        return $this->streamFormat($projectId, 'csl', 'application/vnd.citationstyles.csl+json; charset=UTF-8', function ($b) {
            return $this->export->toCslJson($this->export->flattenSources($b));
        });
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build the document for one format and stream it as a download. Never throws;
     * on error it bounces back to the export page with a message.
     */
    private function streamFormat(int $projectId, string $format, string $contentType, callable $render)
    {
        [$project, $researcher] = $this->guard($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        try {
            $bundle  = $this->export->assemble($projectId);
            $content = (string) $render($bundle);
            $name    = $this->export->formatFilename($bundle, $format, $projectId);

            $this->export->logExport($projectId, $format, $this->exporterName($researcher));

            return new StreamedResponse(function () use ($content) {
                echo $content;
            }, 200, [
                'Content-Type'        => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $name . '"',
                'Content-Length'      => (string) strlen($content),
                'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Throwable $e) {
            return redirect()
                ->route('research.export.index', $projectId)
                ->with('error', __('That export could not be produced. Please try again.'));
        }
    }

    /**
     * Auth + access gate. Returns [project, researcher] on success, or
     * [RedirectResponse, null] when the request must bounce.
     *
     * @return array{0:object|\Illuminate\Http\RedirectResponse,1:object|null}
     */
    private function guard(int $projectId): array
    {
        if (! Auth::check()) {
            return [redirect()->route('login'), null];
        }

        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return [redirect()->route('researcher.register'), null];
        }

        $project = $this->loadProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        if (! $this->canView($project, $researcher)) {
            abort(403, 'You do not have access to this project.');
        }

        return [$project, $researcher];
    }

    private function loadProject(int $projectId): ?object
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

    /** Owner, collaborator, or admin may export. Resilient: error => no access. */
    private function canView(object $project, object $researcher): bool
    {
        try {
            $isAdmin = Auth::check() && \AhgCore\Services\AclService::canAdmin(Auth::id());
        } catch (\Throwable $e) {
            $isAdmin = false;
        }

        $isOwner = (int) ($project->owner_id ?? 0) === (int) ($researcher->id ?? 0);

        $isCollaborator = false;
        try {
            if (Schema::hasTable('research_project_collaborator')) {
                $isCollaborator = DB::table('research_project_collaborator')
                    ->where('project_id', $project->id)
                    ->where('researcher_id', $researcher->id)
                    ->exists();
            }
        } catch (\Throwable $e) {
            $isCollaborator = false;
        }

        return $isAdmin || $isOwner || $isCollaborator;
    }

    private function exporterName(?object $researcher): ?string
    {
        if (! $researcher) {
            return null;
        }
        $name = trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? ''));
        return $name !== '' ? $name : ($researcher->email ?? null);
    }

    /** Sidebar data without touching ResearchController::getSidebarData. */
    private function sidebar(): array
    {
        $unread = 0;
        try {
            $researcher = $this->research->getResearcherByUserId(Auth::id());
            if ($researcher && Schema::hasTable('research_notification')) {
                $unread = (int) DB::table('research_notification')
                    ->where('researcher_id', $researcher->id)
                    ->where('is_read', 0)
                    ->count();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'sidebarActive'       => 'projects',
            'unreadNotifications' => $unread,
        ];
    }
}
