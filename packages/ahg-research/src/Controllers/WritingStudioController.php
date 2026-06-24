<?php

/**
 * WritingStudioController - Controller for Heratio
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
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\ResearchService;
use AhgResearch\Services\WritingStudioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * WritingStudioController - Research OS Stage 13 (epic heratio#1222).
 *
 * Per-project write-as-you-go editor over the NEW research_writing_* tables,
 * grounded in the project's Claim Ledger (research_assertion) and bibliography
 * (read-only). Auth-gated like the rest of the portal; each action resolves the
 * researcher + project context defensively and degrades to an empty state
 * rather than 500.
 */
class WritingStudioController extends Controller
{
    use LogsResearchActivity;

    protected WritingStudioService $studio;
    protected ResearchService $research;

    public function __construct()
    {
        $this->studio = new WritingStudioService();
        $this->research = new ResearchService();
    }

    /** Resolve [project, researcher] for a project id. */
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

    // =====================================================================
    // DOCUMENTS
    // =====================================================================

    /** List a project's writing documents. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $docs = $this->studio->listDocs($projectId);

        return view('research::research.writing-studio.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'docs'),
            [
                'docTypes'     => WritingStudioService::DOC_TYPES,
                'statuses'     => WritingStudioService::STATUSES,
                'statusBadges' => WritingStudioService::STATUS_BADGES,
            ]
        ));
    }

    /** Create a document (POST). */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $validated = $request->validate([
            'title'    => 'required|string|max:500',
            'doc_type' => 'nullable|string|max:40',
            'status'   => 'nullable|string|max:40',
        ]);

        $id = $this->studio->createDoc($projectId, $validated, (int) Auth::id());
        if (! $id) {
            return redirect()->route('research.writing.index', $projectId)
                ->with('error', 'Could not create the document.');
        }
        $this->logResearchActivity('create', 'writing', (int) $id, $validated['title'] ?? null, ['method' => 'WritingStudioController@store', 'item' => 'document'], $projectId);
        return redirect()->route('research.writing.edit', [$projectId, $id])
            ->with('success', 'Document created. Start writing.');
    }

    /** The editor: sections + a claims/sources sidebar + version history link. */
    public function edit(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $doc = $this->studio->getDoc($projectId, $docId);
        if (! $doc) {
            return redirect()->route('research.writing.index', $projectId)
                ->with('error', 'Document not found.');
        }

        $sections = $this->studio->getSections($docId);
        $claims   = $this->studio->projectClaims($projectId);
        $sources  = $this->studio->projectSources($projectId);

        return view('research::research.writing-studio.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'doc', 'sections', 'claims', 'sources'),
            [
                'docTypes'     => WritingStudioService::DOC_TYPES,
                'statuses'     => WritingStudioService::STATUSES,
                'statusBadges' => WritingStudioService::STATUS_BADGES,
                'aiAvailable'  => $this->studio->aiAvailable(),
                'aiLabel'      => WritingStudioService::AI_LABEL,
            ]
        ));
    }

    /** Update a document's title/type/status (POST). */
    public function update(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'title'    => 'required|string|max:500',
            'doc_type' => 'nullable|string|max:40',
            'status'   => 'nullable|string|max:40',
        ]);

        $ok = $this->studio->updateDoc($projectId, $docId, $validated);
        if ($ok) {
            $this->logResearchActivity('update', 'writing', $docId, $validated['title'] ?? null, ['method' => 'WritingStudioController@update', 'item' => 'document'], $projectId);
        }
        return redirect()->route('research.writing.edit', [$projectId, $docId])
            ->with($ok ? 'success' : 'error', $ok ? 'Document updated.' : 'Could not update the document.');
    }

    /** Delete a document (POST). */
    public function destroy(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->studio->deleteDoc($projectId, $docId);
        if ($ok) {
            $this->logResearchActivity('delete', 'writing', $docId, null, ['method' => 'WritingStudioController@destroy', 'item' => 'document'], $projectId);
        }
        return redirect()->route('research.writing.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Document deleted.' : 'Could not delete the document.');
    }

    // =====================================================================
    // SECTIONS (write-as-you-go)
    // =====================================================================

    /** Add a section to a document (POST). */
    public function addSection(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);
        if (! $this->studio->getDoc($projectId, $docId)) {
            return redirect()->route('research.writing.index', $projectId)->with('error', 'Document not found.');
        }

        $validated = $request->validate([
            'heading' => 'nullable|string|max:500',
            'body'    => 'nullable|string',
        ]);

        $id = $this->studio->addSection($docId, $validated);
        if ($id) {
            $this->logResearchActivity('create', 'writing', (int) $id, $validated['heading'] ?? null, ['method' => 'WritingStudioController@addSection', 'item' => 'section', 'doc_id' => $docId], $projectId);
        }
        return redirect()->route('research.writing.edit', [$projectId, $docId])
            ->with($id ? 'success' : 'error', $id ? 'Section added.' : 'Could not add the section.');
    }

    /** Save a section's heading/body (POST). */
    public function saveSection(Request $request, int $projectId, int $docId, int $sectionId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);
        if (! $this->studio->getDoc($projectId, $docId)) {
            return redirect()->route('research.writing.index', $projectId)->with('error', 'Document not found.');
        }

        $validated = $request->validate([
            'heading' => 'nullable|string|max:500',
            'body'    => 'nullable|string',
        ]);

        $ok = $this->studio->saveSection($docId, $sectionId, $validated);
        if ($ok) {
            $this->logResearchActivity('update', 'writing', $sectionId, $validated['heading'] ?? null, ['method' => 'WritingStudioController@saveSection', 'item' => 'section', 'doc_id' => $docId], $projectId);
        }
        return redirect()->route('research.writing.edit', [$projectId, $docId])
            ->with($ok ? 'success' : 'error', $ok ? 'Section saved.' : 'Could not save the section.');
    }

    /** Delete a section (POST). */
    public function deleteSection(Request $request, int $projectId, int $docId, int $sectionId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->studio->deleteSection($docId, $sectionId);
        if ($ok) {
            $this->logResearchActivity('delete', 'writing', $sectionId, null, ['method' => 'WritingStudioController@deleteSection', 'item' => 'section', 'doc_id' => $docId], $projectId);
        }
        return redirect()->route('research.writing.edit', [$projectId, $docId])
            ->with($ok ? 'success' : 'error', $ok ? 'Section deleted.' : 'Could not delete the section.');
    }

    // =====================================================================
    // CITE A CLAIM / PULL A SOURCE (read-only over existing tables)
    // =====================================================================

    /** Insert a reference to a project claim into a chosen section (POST). */
    public function citeClaim(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'claim_id'   => 'required|integer',
            'section_id' => 'required|integer',
        ]);

        $reference = $this->studio->formatClaimReference($projectId, (int) $validated['claim_id']);
        if ($reference === null) {
            return redirect()->route('research.writing.edit', [$projectId, $docId])
                ->with('error', 'That claim could not be cited.');
        }

        $ok = $this->studio->appendToSection($docId, (int) $validated['section_id'], $reference);
        if ($ok) {
            $this->logResearchActivity('update', 'writing', (int) $validated['section_id'], null, ['method' => 'WritingStudioController@citeClaim', 'item' => 'section', 'doc_id' => $docId, 'claim_id' => (int) $validated['claim_id']], $projectId);
        }
        return redirect()->route('research.writing.edit', [$projectId, $docId])
            ->with($ok ? 'success' : 'error', $ok ? 'Claim cited into the section.' : 'Could not cite the claim.');
    }

    /** Pull a bibliography source reference into a chosen section (POST). */
    public function pullSource(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'source_id'  => 'required|integer',
            'section_id' => 'required|integer',
        ]);

        $reference = $this->studio->formatSourceReference($projectId, (int) $validated['source_id']);
        if ($reference === null) {
            return redirect()->route('research.writing.edit', [$projectId, $docId])
                ->with('error', 'That source could not be pulled in.');
        }

        $ok = $this->studio->appendToSection($docId, (int) $validated['section_id'], $reference);
        if ($ok) {
            $this->logResearchActivity('update', 'writing', (int) $validated['section_id'], null, ['method' => 'WritingStudioController@pullSource', 'item' => 'section', 'doc_id' => $docId, 'source_id' => (int) $validated['source_id']], $projectId);
        }
        return redirect()->route('research.writing.edit', [$projectId, $docId])
            ->with($ok ? 'success' : 'error', $ok ? 'Source pulled into the section.' : 'Could not pull the source.');
    }

    // =====================================================================
    // VERSIONS
    // =====================================================================

    /** Snapshot the whole document as a new version (POST). */
    public function saveVersion(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $request->validate(['note' => 'nullable|string|max:1000']);

        $v = $this->studio->saveVersion($projectId, $docId, $request->input('note'), (int) Auth::id());
        if ($v) {
            $this->logResearchActivity('create', 'writing', $docId, null, ['method' => 'WritingStudioController@saveVersion', 'item' => 'version', 'version' => $v], $projectId);
        }
        return redirect()->route('research.writing.versions', [$projectId, $docId])
            ->with($v ? 'success' : 'error', $v ? ('Version ' . $v . ' saved.') : 'Could not save a version.');
    }

    /** Version history for a document. */
    public function versions(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $doc = $this->studio->getDoc($projectId, $docId);
        if (! $doc) {
            return redirect()->route('research.writing.index', $projectId)
                ->with('error', 'Document not found.');
        }

        $versions = $this->studio->listVersions($docId);

        return view('research::research.writing-studio.versions', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'doc', 'versions')
        ));
    }

    /** Show one version's full snapshot (read-only). */
    public function showVersion(Request $request, int $projectId, int $docId, int $versionId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $doc = $this->studio->getDoc($projectId, $docId);
        $version = $this->studio->getVersion($projectId, $docId, $versionId);
        if (! $doc || ! $version) {
            return redirect()->route('research.writing.versions', [$projectId, $docId])
                ->with('error', 'Version not found.');
        }

        return view('research::research.writing-studio.version-show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'doc', 'version')
        ));
    }

    // =====================================================================
    // MARKDOWN EXPORT
    // =====================================================================

    /** Download the document as a Markdown file. */
    public function exportMarkdown(Request $request, int $projectId, int $docId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $doc = $this->studio->getDoc($projectId, $docId);
        $markdown = $this->studio->exportMarkdown($projectId, $docId);
        if (! $doc || $markdown === null) {
            return redirect()->route('research.writing.index', $projectId)
                ->with('error', 'Document not found.');
        }

        $filename = $this->studio->exportFilename($doc);
        return response($markdown, 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // =====================================================================
    // OPTIONAL AI DRAFTING (gateway-only, labelled, never auto-applied)
    // =====================================================================

    /**
     * Produce an AI draft for a section and show it for researcher approval.
     * The draft is NEVER written until the researcher confirms it via the
     * normal saveSection action - this endpoint only returns it labelled.
     */
    public function aiDraft(Request $request, int $projectId, int $docId, int $sectionId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $request->validate(['instruction' => 'nullable|string|max:2000']);

        $doc = $this->studio->getDoc($projectId, $docId);
        $section = $this->studio->getSection($docId, $sectionId);
        if (! $doc || ! $section) {
            return redirect()->route('research.writing.edit', [$projectId, $docId])
                ->with('error', 'Section not found.');
        }

        if (! $this->studio->aiAvailable()) {
            return redirect()->route('research.writing.edit', [$projectId, $docId])
                ->with('error', 'AI drafting is not available on this install. You can keep writing without it.');
        }

        $result = $this->studio->aiDraftSection(
            $projectId, $docId, $sectionId, (string) $request->input('instruction', '')
        );

        if (! $result['ok']) {
            return redirect()->route('research.writing.edit', [$projectId, $docId])
                ->with('error', 'The AI draft could not be generated. You can keep writing without it.');
        }

        // Show the labelled draft alongside the editor; nothing is saved yet.
        return view('research::research.writing-studio.edit', array_merge(
            $this->sidebar('projects'),
            [
                'project'      => $project,
                'researcher'   => $researcher,
                'doc'          => $doc,
                'sections'     => $this->studio->getSections($docId),
                'claims'       => $this->studio->projectClaims($projectId),
                'sources'      => $this->studio->projectSources($projectId),
                'docTypes'     => WritingStudioService::DOC_TYPES,
                'statuses'     => WritingStudioService::STATUSES,
                'statusBadges' => WritingStudioService::STATUS_BADGES,
                'aiAvailable'  => true,
                'aiLabel'      => WritingStudioService::AI_LABEL,
                'aiDraft'      => $result['text'],
                'aiDraftSectionId' => $sectionId,
            ]
        ));
    }
}
