<?php

/**
 * ResearchJournalEntryController - Controller for Heratio
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
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchJournalEntryController - the researcher's personal research journal /
 * diary (research_journal_entry).
 *
 * Split out of ResearchJournalController (issue #1270). Distinct feature from
 * the #1105 journal *builder* (journals/issues/articles/TOC) that remains in
 * ResearchJournalController: this is the researcher's personal logbook
 * (journal()/journalEntry()/createJournalEntry()/showJournalEntry()). Uses the
 * shared ResearchControllerHelpers trait (getSidebarData) and the injected
 * ResearchService ($this->service for getResearcherByUserId + sanitizeHtml).
 */
class ResearchJournalEntryController extends Controller
{
    use LogsResearchActivity;
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function journal(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $filters = [
            'project_id' => $request->input('project_id'),
            'entry_type' => $request->input('entry_type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('q'),
        ];

        $query = DB::table('research_journal_entry')
            ->where('researcher_id', $researcher->id);

        if ($filters['project_id']) $query->where('project_id', $filters['project_id']);
        if ($filters['entry_type']) $query->where('entry_type', $filters['entry_type']);
        if ($filters['date_from']) $query->where('entry_date', '>=', $filters['date_from']);
        if ($filters['date_to']) $query->where('entry_date', '<=', $filters['date_to']);
        if ($filters['search']) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('content', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        $entries = $query->orderBy('entry_date', 'desc')->orderBy('created_at', 'desc')->get()->toArray();

        $projects = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcher->id)
            ->where('pc.status', 'accepted')
            ->select('p.id', 'p.title')
            ->orderBy('p.title')->get()->toArray();

        if ($request->isMethod('post') && $request->input('do') === 'create') {
            $content = $this->service->sanitizeHtml($request->input('content', ''));
            if ($content) {
                DB::table('research_journal_entry')->insert([
                    'researcher_id' => $researcher->id,
                    'title' => $request->input('title'),
                    'content' => $content,
                    'content_format' => 'html',
                    'project_id' => $request->input('project_id') ?: null,
                    'entry_type' => $request->input('entry_type') ?: 'manual',
                    'time_spent_minutes' => $request->input('time_spent_minutes') ?: null,
                    'tags' => $request->input('tags'),
                    'entry_date' => $request->input('entry_date') ?: date('Y-m-d'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->logResearchActivity('create', 'journal_entry', null, $request->input('title'), ['method' => 'ResearchJournalEntryController@journal'], $request->input('project_id') ?: null);
                return redirect()->route('research.journal')->with('success', 'Journal entry created');
            }
        }

        $journals = DB::table('research_journal')
            ->where('researcher_id', $researcher->id)
            ->orderByDesc('updated_at')->get()->toArray();

        return view('research::research.journal', array_merge(
            $this->getSidebarData('journal'),
            compact('researcher', 'entries', 'projects', 'filters', 'journals')
        ));
    }

    public function journalEntry(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $entry = DB::table('research_journal_entry')->where('id', $id)->first();
        if (!$entry || $entry->researcher_id != $researcher->id) abort(404);

        $projects = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcher->id)
            ->where('pc.status', 'accepted')
            ->select('p.id', 'p.title')->orderBy('p.title')->get()->toArray();

        if ($request->isMethod('post')) {
            if ($request->input('form_action') === 'delete') {
                DB::table('research_journal_entry')
                    ->where('id', $id)
                    ->where('researcher_id', $researcher->id)
                    ->delete();
                $this->logResearchActivity('delete', 'journal_entry', $id, $entry->title ?? null, ['method' => 'ResearchJournalEntryController@journalEntry']);
                return redirect()->route('research.journal')->with('success', 'Entry deleted');
            }
            $content = $this->service->sanitizeHtml($request->input('content', ''));
            DB::table('research_journal_entry')->where('id', $id)->where('researcher_id', $researcher->id)->update([
                'title' => $request->input('title'),
                'content' => $content,
                'content_format' => 'html',
                'project_id' => $request->input('project_id') ?: null,
                'entry_type' => $request->input('entry_type', $entry->entry_type),
                'time_spent_minutes' => $request->input('time_spent_minutes') ?: null,
                'tags' => $request->input('tags'),
                'is_private' => $request->has('is_private') ? 1 : 0,
                'entry_date' => $request->input('entry_date') ?: $entry->entry_date,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logResearchActivity('update', 'journal_entry', $id, $request->input('title', $entry->title ?? null), ['method' => 'ResearchJournalEntryController@journalEntry'], $request->input('project_id') ?: null);
            return redirect()->route('research.journalEntry', $id)->with('success', 'Entry updated');
        }

        return view('research::research.journal-entry', array_merge(
            $this->getSidebarData('journal'),
            compact('researcher', 'entry', 'projects')
        ));
    }

    public function createJournalEntry()
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $projects = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcher->id)
            ->where('pc.status', 'accepted')
            ->select('p.id', 'p.title')
            ->orderBy('p.title')->get()->toArray();

        $filters = ['project_id' => null, 'entry_type' => null, 'date_from' => null, 'date_to' => null, 'search' => null];
        $entries = [];

        $journals = DB::table('research_journal')
            ->where('researcher_id', $researcher->id)
            ->orderByDesc('updated_at')->get()->toArray();

        return view('research::research.journal', array_merge(
            $this->getSidebarData('journal'),
            compact('researcher', 'entries', 'projects', 'filters', 'journals'),
            ['showCreateForm' => true]
        ));
    }

    public function showJournalEntry(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        return redirect()->route('research.journalEntry', $id);
    }
}
