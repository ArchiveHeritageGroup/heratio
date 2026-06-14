<?php

/**
 * ResearchJournalController - journal builder UI + CRUD for the research portal (#1105).
 *
 * Institutional journal publication (journal -> issues -> articles -> TOC ->
 * publish) plus a manuscript workspace that formats an article toward an
 * external target journal (#1107). All routes are under the auth'd /research
 * group.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Services\ResearchJournalService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResearchJournalController extends Controller
{
    protected ResearchService $researchService;

    public function __construct(
        private ResearchJournalService $service,
        ResearchService $researchService
    ) {
        $this->researchService = $researchService;
    }

    // ── Journals ──────────────────────────────────────────────────────────

    public function index()
    {
        return view('research::journals.index', [
            'publications' => $this->service->listJournals(ResearchJournalService::KIND_PUBLICATION),
            'manuscripts'  => $this->service->listJournals(ResearchJournalService::KIND_MANUSCRIPT),
        ]);
    }

    public function create(Request $request)
    {
        $kind = $request->query('kind') === ResearchJournalService::KIND_MANUSCRIPT
            ? ResearchJournalService::KIND_MANUSCRIPT : ResearchJournalService::KIND_PUBLICATION;

        return view('research::journals.builder', [
            'journal'        => null,
            'kind'           => $kind,
            'targetJournals' => $this->targetJournalOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateJournal($request);
        $data['researcher_id'] = $this->researcherId();
        $id = $this->service->createJournal($data);

        return redirect()->route('research.journal-builder.show', $id)
            ->with('success', __('Journal created.'));
    }

    public function show(int $id)
    {
        $journal = $this->service->getJournal($id);
        abort_if(! $journal, 404);

        return view('research::journals.show', [
            'journal' => $journal,
            'toc'     => $this->service->tableOfContents($id),
        ]);
    }

    public function edit(int $id)
    {
        $journal = $this->service->getJournal($id);
        abort_if(! $journal, 404);

        return view('research::journals.builder', [
            'journal'        => $journal,
            'kind'           => $journal['kind'],
            'targetJournals' => $this->targetJournalOptions(),
        ]);
    }

    public function update(int $id, Request $request)
    {
        abort_if(! $this->service->getJournal($id), 404);
        $this->service->updateJournal($id, $this->validateJournal($request));

        return redirect()->route('research.journal-builder.show', $id)
            ->with('success', __('Journal updated.'));
    }

    public function destroy(int $id)
    {
        $this->service->deleteJournal($id);

        return redirect()->route('research.journal-builder.index')
            ->with('success', __('Journal deleted.'));
    }

    public function setStatus(int $id, Request $request)
    {
        $status = (string) $request->input('status', 'draft');
        abort_unless(in_array($status, ['draft', 'published', 'archived'], true), 422);
        $this->service->setJournalStatus($id, $status);

        return back()->with('success', __('Journal status updated to :s.', ['s' => $status]));
    }

    // ── Issues ────────────────────────────────────────────────────────────

    public function storeIssue(int $journalId, Request $request)
    {
        abort_if(! $this->service->getJournal($journalId), 404);
        $this->service->createIssue($journalId, $this->validateIssue($request));

        return back()->with('success', __('Issue added.'));
    }

    public function updateIssue(int $id, Request $request)
    {
        $issue = $this->service->getIssue($id);
        abort_if(! $issue, 404);
        $this->service->updateIssue($id, $this->validateIssue($request));

        return back()->with('success', __('Issue updated.'));
    }

    public function destroyIssue(int $id)
    {
        $issue = $this->service->getIssue($id);
        abort_if(! $issue, 404);
        $this->service->deleteIssue($id);

        return back()->with('success', __('Issue removed; its articles were unassigned.'));
    }

    // ── Articles / manuscript builder ───────────────────────────────────────

    public function createArticle(int $journalId)
    {
        $journal = $this->service->getJournal($journalId);
        abort_if(! $journal, 404);

        return view('research::journals.article-builder', [
            'journal'        => $journal,
            'article'        => null,
            'issues'         => $this->service->listIssues($journalId),
            'styles'         => ResearchJournalService::REFERENCE_STYLES,
            'targetJournals' => $this->targetJournalOptions(),
        ]);
    }

    public function storeArticle(int $journalId, Request $request)
    {
        $journal = $this->service->getJournal($journalId);
        abort_if(! $journal, 404);
        $id = $this->service->createArticle($journalId, $this->validateArticle($request));

        return redirect()->route('research.journal-builder.article-edit', $id)
            ->with('success', __('Article saved.'));
    }

    public function editArticle(int $id)
    {
        $article = $this->service->getArticle($id);
        abort_if(! $article, 404);
        $journal = $this->service->getJournal((int) $article['journal_id']);

        return view('research::journals.article-builder', [
            'journal'        => $journal,
            'article'        => $article,
            'issues'         => $this->service->listIssues((int) $article['journal_id']),
            'styles'         => ResearchJournalService::REFERENCE_STYLES,
            'targetJournals' => $this->targetJournalOptions(),
            'validation'     => $this->service->validateManuscript($article),
        ]);
    }

    public function updateArticle(int $id, Request $request)
    {
        $article = $this->service->getArticle($id);
        abort_if(! $article, 404);
        $this->service->updateArticle($id, $this->validateArticle($request));

        return redirect()->route('research.journal-builder.article-edit', $id)
            ->with('success', __('Article saved.'));
    }

    public function destroyArticle(int $id)
    {
        $article = $this->service->getArticle($id);
        abort_if(! $article, 404);
        $this->service->deleteArticle($id);

        return redirect()->route('research.journal-builder.show', (int) $article['journal_id'])
            ->with('success', __('Article deleted.'));
    }

    // ── Personal research journal / diary (research_journal_entry) ──────────
    // Extracted verbatim from ResearchController (issue #1269). Distinct feature
    // from the #1105 journal *builder* above: this is the researcher's personal
    // logbook (journal()/journalEntry()/createJournalEntry()/showJournalEntry()).
    // Uses the shared ResearchControllerHelpers trait (getSidebarData) and the
    // injected ResearchService ($this->researchService for getResearcherByUserId
    // + sanitizeHtml). No cross-calls to other ResearchController methods. Route
    // names preserved: research.journal / research.journalEntry / etc.

    public function journal(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->researchService->getResearcherByUserId(Auth::id());
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
            $content = $this->researchService->sanitizeHtml($request->input('content', ''));
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
        $researcher = $this->researchService->getResearcherByUserId(Auth::id());
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
                return redirect()->route('research.journal')->with('success', 'Entry deleted');
            }
            $content = $this->researchService->sanitizeHtml($request->input('content', ''));
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
        $researcher = $this->researchService->getResearcherByUserId(Auth::id());
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

    // ── validation + helpers ────────────────────────────────────────────────

    private function validateJournal(Request $request): array
    {
        return $request->validate([
            'kind'              => 'nullable|in:publication,manuscript',
            'title'             => 'required|string|max:255',
            'subtitle'          => 'nullable|string|max:255',
            'issn'              => 'nullable|string|max:20',
            'eissn'             => 'nullable|string|max:20',
            'publisher'         => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'aims_scope'        => 'nullable|string',
            'editor_name'       => 'nullable|string|max:255',
            'editor_email'      => 'nullable|email|max:255',
            'target_journal_id' => 'nullable|integer',
            'doi'               => 'nullable|string|max:128',
            'status'            => 'nullable|in:draft,published,archived',
        ]);
    }

    private function validateIssue(Request $request): array
    {
        return $request->validate([
            'volume'      => 'nullable|string|max:40',
            'number'      => 'nullable|string|max:40',
            'title'       => 'nullable|string|max:255',
            'issue_date'  => 'nullable|date',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:draft,published',
            'sort_order'  => 'nullable|integer',
        ]);
    }

    private function validateArticle(Request $request): array
    {
        return $request->validate([
            'issue_id'          => 'nullable|integer',
            'title'             => 'required|string|max:500',
            'authors'           => 'nullable|string',
            'abstract'          => 'nullable|string',
            'keywords'          => 'nullable|string|max:500',
            'body_markdown'     => 'nullable|string',
            'reference_style'   => 'nullable|string|max:40',
            'target_journal_id' => 'nullable|integer',
            'doi'               => 'nullable|string|max:128',
            'status'            => 'nullable|in:draft,submitted,published',
            'sort_order'        => 'nullable|integer',
        ]);
    }

    /** Options from the #1107 target-journal directory when it exists, else []. */
    private function targetJournalOptions(): array
    {
        if (! Schema::hasTable('research_target_journal')) {
            return [];
        }

        return DB::table('research_target_journal')->orderBy('title')
            ->get(['id', 'title'])->map(fn ($r) => (array) $r)->all();
    }

    private function researcherId(): ?int
    {
        if (! Auth::check() || ! Schema::hasTable('researcher')) {
            return null;
        }
        $r = DB::table('researcher')->where('user_id', Auth::id())->first();

        return $r ? (int) $r->id : null;
    }

    /**
     * Sidebar data shared with the research portal layout.
     *
     * Local copy of ResearchControllerHelpers::getSidebarData() — the trait
     * cannot be mixed in here because its CONTRACT requires `$this->service` to
     * be the canonical ResearchService, whereas this slice's `$service` is the
     * ResearchJournalService (journal builder). The personal-journal methods use
     * the separately injected $this->researchService instead.
     */
    private function getSidebarData(string $active): array
    {
        $unreadNotifications = 0;
        $experienceLevel = 'intermediate';
        if (Auth::check()) {
            $researcher = $this->researchService->getResearcherByUserId(Auth::id());
            if ($researcher) {
                try {
                    $unreadNotifications = (int) DB::table('research_notification')
                        ->where('researcher_id', $researcher->id)
                        ->where('is_read', 0)
                        ->count();
                } catch (\Exception $e) {
                    // Table may not exist yet
                }
                if (!empty($researcher->experience_level)) {
                    $experienceLevel = $researcher->experience_level;
                }
            }
        }
        return [
            'sidebarActive' => $active,
            'unreadNotifications' => $unreadNotifications,
            'experienceLevel' => $experienceLevel,
        ];
    }
}
