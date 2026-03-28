<?php

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\SemanticSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SemanticSearchController extends Controller
{
    protected SemanticSearchService $service;

    public function __construct()
    {
        $this->service = new SemanticSearchService();
    }

    // Admin Dashboard
    public function index()
    {
        $stats = $this->service->getDashboardStats();
        $config = $this->service->getAllConfig();

        return view('ahg-semantic-search::index', compact('stats', 'config'));
    }

    public function config(Request $request)
    {
        if ($request->isMethod('post')) {
            foreach ($request->except('_token') as $key => $value) {
                $this->service->setConfig($key, $value);
            }

            return redirect()->route('semantic-search.config')->with('notice', 'Configuration saved');
        }

        $config = $this->service->getAllConfig();

        return view('ahg-semantic-search::config', compact('config'));
    }

    // Terms
    public function terms(Request $request)
    {
        $terms = $this->service->getTerms([
            'search' => $request->get('q'),
            'is_active' => $request->has('active') ? (int) $request->get('active') : null,
        ]);

        return view('ahg-semantic-search::terms', compact('terms'));
    }

    public function termAdd(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->service->createTerm($request->only([
                'term', 'synonyms', 'related_terms', 'definition',
                'source', 'language', 'is_active',
            ]));

            return redirect()->route('semantic-search.terms')->with('notice', 'Term added');
        }

        return view('ahg-semantic-search::term-add');
    }

    public function termView(int $id)
    {
        $term = $this->service->getTerm($id);
        abort_unless($term, 404, 'Term not found');

        return view('ahg-semantic-search::term-view', compact('term'));
    }

    // Search Logs
    public function searchLogs(Request $request)
    {
        $logs = $this->service->getSearchLogs([
            'start_date' => $request->get('start'),
            'end_date' => $request->get('end'),
        ]);

        return view('ahg-semantic-search::search-logs', compact('logs'));
    }

    // Sync Logs
    public function syncLogs()
    {
        $logs = $this->service->getSyncLogs();

        return view('ahg-semantic-search::sync-logs', compact('logs'));
    }

    // Search Templates
    public function adminTemplates()
    {
        $templates = $this->service->getTemplates();

        return view('ahg-semantic-search::admin-templates', compact('templates'));
    }

    public function adminTemplateEdit(Request $request, ?int $id = null)
    {
        $template = $id ? $this->service->getTemplate($id) : null;
        $isNew = !$template;

        if ($request->isMethod('post')) {
            $data = $request->only(['name', 'slug', 'description', 'template_type', 'config', 'is_active']);

            if ($id) {
                $this->service->updateTemplate($id, $data);
            } else {
                $id = $this->service->createTemplate($data);
            }

            return redirect()->route('semantic-search.admin.templates')->with('notice', 'Template saved');
        }

        return view('ahg-semantic-search::admin-template-edit', compact('template', 'isNew'));
    }

    // Saved Searches
    public function savedSearches()
    {
        $searches = $this->service->getSavedSearches(auth()->id());

        return view('ahg-semantic-search::saved-searches', compact('searches'));
    }

    // History
    public function history()
    {
        $history = $this->service->getSearchHistory(auth()->id());

        return view('ahg-semantic-search::history', compact('history'));
    }

    /**
     * Handle POST actions for semantic search.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');

        if ($action === 'delete_term') {
            $id = (int) $request->get('id');
            $this->service->deleteTerm($id);

            return redirect()->route('semantic-search.terms')->with('notice', 'Term deleted.');
        }

        if ($action === 'sync') {
            $this->service->syncTerms();

            return redirect()->route('semantic-search.index')->with('notice', 'Terms synced.');
        }

        if ($action === 'clear_history') {
            $this->service->clearSearchHistory(auth()->id());

            return redirect()->route('semantic-search.history')->with('notice', 'Search history cleared.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }

    /**
     * AJAX: Run synonym/term sync.
     */
    public function runSync()
    {
        $result = $this->service->syncTerms();

        return response()->json([
            'success' => true,
            'message' => 'Sync completed.',
            'result' => $result,
        ]);
    }

    /**
     * AJAX: Test term expansion for a given query.
     */
    public function testExpand(Request $request)
    {
        $query = $request->input('query', '');
        $expanded = $this->service->expandQuery($query);

        return response()->json([
            'original' => $query,
            'expanded' => $expanded,
        ]);
    }
}
