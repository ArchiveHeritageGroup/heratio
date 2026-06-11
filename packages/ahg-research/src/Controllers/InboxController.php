<?php

/**
 * InboxController - Controller for Heratio
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
use AhgResearch\Services\InboxService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * InboxController - Quick Capture Inbox (heratio#1228, ROS Stage 0).
 *
 * The front door of the research mind: a frictionless capture surface plus a
 * lightweight triage workflow (mark-triaged / archive / move-to-project). Every
 * action is scoped to the signed-in researcher; nothing here touches another
 * researcher's items, existing tables, or runs an ALTER.
 *
 * Voice and email-in / clipper are deliberately lightweight:
 *  - voice captures store the transcription text in `body` (a transcription
 *    SLOT - the front-end / a future STT job fills it);
 *  - email-in and the web clipper post to the same generic capture endpoint
 *    with origin=email-in|clipper. The full mail-server / browser-extension
 *    plumbing is out of scope; this controller is the documented integration
 *    point those channels POST into.
 */
class InboxController extends Controller
{
    protected InboxService $inbox;
    protected ResearchService $research;

    public function __construct()
    {
        $this->inbox    = new InboxService();
        $this->research = new ResearchService();
    }

    /** Resolve the signed-in researcher or send them somewhere sensible. */
    protected function researcherOrRedirect()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return redirect()->route('researcher.register');
        }
        return $researcher;
    }

    // =========================================================================
    // LIST
    // =========================================================================

    public function index(Request $request)
    {
        $researcher = $this->researcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher; // redirect response
        }

        $kind   = $request->input('kind');
        $status = $request->input('status');

        $filters = [];
        if ($kind) {
            $filters['kind'] = $kind;
        }
        // Pass status through explicitly so the service can default to "live
        // inbox" (non-archived) only when the caller did not choose a status.
        if ($status !== null && $status !== '') {
            $filters['status'] = $status;
        }

        $items        = $this->inbox->listForResearcher($researcher->id, $filters);
        $counts       = $this->inbox->statusCounts($researcher->id);
        $kindOptions  = $this->inbox->dropdownOptions('research_inbox_kind', InboxService::KINDS);
        $originOptions = $this->inbox->dropdownOptions('research_inbox_origin', InboxService::ORIGINS);
        $projects     = $this->inbox->projectsForPicker($researcher->id);

        return view('research::research.inbox', [
            'sidebarActive' => 'inbox',
            'researcher'    => $researcher,
            'items'         => $items,
            'counts'        => $counts,
            'kind'          => $kind,
            'status'        => $status,
            'kindOptions'   => $kindOptions,
            'originOptions' => $originOptions,
            'projects'      => $projects,
        ]);
    }

    // =========================================================================
    // CAPTURE
    // =========================================================================

    /**
     * Generic capture endpoint. Accepts title/body/kind/origin/source_url and an
     * optional file upload. Used by the one-tap quick-note form, the mobile
     * capture surface, and - via origin=email-in|clipper - the email-in and
     * web-clipper integration points.
     *
     * Honours an AJAX/JSON request (returns the new id) so a browser extension
     * or mobile client can POST without a full page reload.
     */
    public function capture(Request $request)
    {
        $researcher = $this->researcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher;
        }

        $validated = $request->validate([
            'title'      => 'nullable|string|max:500',
            'body'       => 'nullable|string|max:65535',
            'kind'       => 'nullable|string|in:' . implode(',', InboxService::KINDS),
            'origin'     => 'nullable|string|in:' . implode(',', InboxService::ORIGINS),
            'source_url' => 'nullable|string|max:1000|url',
            'project_id' => 'nullable|integer',
            'attachment' => 'nullable|file|max:51200', // 50 MB ceiling
        ]);

        // A capture must carry at least one of: title, body, source_url, or a file.
        $hasContent = ! empty($validated['title'])
            || ! empty($validated['body'])
            || ! empty($validated['source_url'])
            || $request->hasFile('attachment');

        if (! $hasContent) {
            $msg = 'Nothing to capture - add a note, a link, or a file.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $msg], 422);
            }
            return redirect()->route('research.inbox.index')->with('error', $msg);
        }

        $file = $request->hasFile('attachment') ? $request->file('attachment') : null;

        // Infer a sensible kind when none was supplied.
        if (empty($validated['kind'])) {
            if ($file) {
                $mime = (string) $file->getMimeType();
                $validated['kind'] = str_starts_with($mime, 'image/') ? 'photo' : 'file';
            } elseif (! empty($validated['source_url'])) {
                $validated['kind'] = 'clip';
            } else {
                $validated['kind'] = 'note';
            }
        }

        $id = $this->inbox->capture($researcher->id, $validated, $file);

        if ($id <= 0) {
            $msg = 'Could not capture the item. Please try again.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $msg], 500);
            }
            return redirect()->route('research.inbox.index')->with('error', $msg);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $id]);
        }

        return redirect()->route('research.inbox.index')->with('success', 'Captured to your inbox.');
    }

    // =========================================================================
    // TRIAGE
    // =========================================================================

    public function triage(Request $request, int $id)
    {
        $researcher = $this->researcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher;
        }
        $ok = $this->inbox->markTriaged($id, $researcher->id);
        return redirect()->route('research.inbox.index')
            ->with($ok ? 'success' : 'error', $ok ? 'Marked as triaged.' : 'Item not found.');
    }

    public function archive(Request $request, int $id)
    {
        $researcher = $this->researcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher;
        }
        $ok = $this->inbox->archive($id, $researcher->id);
        return redirect()->route('research.inbox.index')
            ->with($ok ? 'success' : 'error', $ok ? 'Archived.' : 'Item not found.');
    }

    public function restore(Request $request, int $id)
    {
        $researcher = $this->researcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher;
        }
        $ok = $this->inbox->restore($id, $researcher->id);
        return redirect()->route('research.inbox.index')
            ->with($ok ? 'success' : 'error', $ok ? 'Restored to inbox.' : 'Item not found.');
    }

    /** Link an inbox item to one of the researcher's projects (status -> triaged). */
    public function moveToProject(Request $request, int $id)
    {
        $researcher = $this->researcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher;
        }

        $validated = $request->validate([
            'project_id' => 'required|integer',
        ]);

        $ok = $this->inbox->moveToProject($id, $researcher->id, (int) $validated['project_id']);

        if (! $ok) {
            return redirect()->route('research.inbox.index')
                ->with('error', 'Could not move item - check the project is one of yours.');
        }
        return redirect()->route('research.inbox.index')->with('success', 'Moved to project.');
    }
}
