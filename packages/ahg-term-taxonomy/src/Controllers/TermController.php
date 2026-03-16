<?php

namespace AhgTermTaxonomy\Controllers;

use AhgTermTaxonomy\Services\TermBrowseService;
use AhgTermTaxonomy\Services\TermService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TermController extends Controller
{
    protected TermService $termService;

    public function __construct(TermService $termService)
    {
        $this->termService = $termService;
    }

    /**
     * List all taxonomies.
     */
    public function taxonomyIndex(Request $request)
    {
        $culture = app()->getLocale();

        $taxonomies = $this->termService->getTaxonomies($culture);

        return view('ahg-term-taxonomy::taxonomy-index', [
            'taxonomies' => $taxonomies,
        ]);
    }

    /**
     * Browse terms filtered by taxonomy.
     */
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $taxonomyId = $request->get('taxonomy');
        $service = new TermBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
            'taxonomy_id' => $taxonomyId,
        ]);

        $pager = new SimplePager($result);

        // Get taxonomy name for the heading
        $taxonomyName = null;
        if ($taxonomyId) {
            $taxonomyName = $this->termService->getTaxonomyName((int) $taxonomyId, $culture);
        }

        return view('ahg-term-taxonomy::browse', [
            'pager' => $pager,
            'taxonomyId' => $taxonomyId,
            'taxonomyName' => $taxonomyName,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    /**
     * Show a single term.
     */
    public function show(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $term = $this->termService->getBySlug($slug, $culture);

        if (!$term) {
            abort(404);
        }

        $taxonomyName = $this->termService->getTaxonomyName($term->taxonomy_id, $culture);
        $scopeNote = $this->termService->getScopeNote($term->id, $culture);
        $relatedDescriptionsCount = $this->termService->getRelatedDescriptionCount($term->id);

        return view('ahg-term-taxonomy::show', [
            'term' => $term,
            'taxonomyName' => $taxonomyName,
            'scopeNote' => $scopeNote,
            'relatedDescriptionsCount' => $relatedDescriptionsCount,
        ]);
    }

    /**
     * Show the create form for a new term.
     */
    public function create(Request $request)
    {
        $culture = app()->getLocale();

        $taxonomies = $this->termService->getTaxonomies($culture);
        $selectedTaxonomyId = $request->get('taxonomy_id');

        return view('ahg-term-taxonomy::edit', [
            'term' => null,
            'taxonomies' => $taxonomies,
            'taxonomyName' => null,
            'selectedTaxonomyId' => $selectedTaxonomyId,
        ]);
    }

    /**
     * Store a new term.
     */
    public function store(Request $request)
    {
        $culture = app()->getLocale();

        $request->validate([
            'taxonomy_id' => 'required|integer',
            'name' => 'required|string|max:1024',
            'code' => 'nullable|string|max:1024',
        ]);

        $slug = $this->termService->create([
            'taxonomy_id' => $request->input('taxonomy_id'),
            'name' => $request->input('name'),
            'code' => $request->input('code'),
        ], $culture);

        return redirect()
            ->route('term.show', $slug)
            ->with('success', 'Term created successfully.');
    }

    /**
     * Show the edit form for a term.
     */
    public function edit(string $slug)
    {
        $culture = app()->getLocale();

        $term = $this->termService->getBySlug($slug, $culture);

        if (!$term) {
            abort(404);
        }

        $taxonomies = $this->termService->getTaxonomies($culture);
        $taxonomyName = $this->termService->getTaxonomyName($term->taxonomy_id, $culture);

        return view('ahg-term-taxonomy::edit', [
            'term' => $term,
            'taxonomies' => $taxonomies,
            'taxonomyName' => $taxonomyName,
            'selectedTaxonomyId' => $term->taxonomy_id,
        ]);
    }

    /**
     * Update a term.
     */
    public function update(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $request->validate([
            'name' => 'required|string|max:1024',
            'code' => 'nullable|string|max:1024',
        ]);

        $term = $this->termService->getBySlug($slug, $culture);

        if (!$term) {
            abort(404);
        }

        $this->termService->update($term->id, [
            'name' => $request->input('name'),
            'code' => $request->input('code'),
        ], $culture);

        return redirect()
            ->route('term.show', $slug)
            ->with('success', 'Term updated successfully.');
    }

    /**
     * Show the delete confirmation page.
     */
    public function confirmDelete(string $slug)
    {
        $culture = app()->getLocale();

        $term = $this->termService->getBySlug($slug, $culture);

        if (!$term) {
            abort(404);
        }

        $taxonomyName = $this->termService->getTaxonomyName($term->taxonomy_id, $culture);

        return view('ahg-term-taxonomy::delete', [
            'term' => $term,
            'taxonomyName' => $taxonomyName,
        ]);
    }

    /**
     * Delete a term.
     */
    public function destroy(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $term = $this->termService->getBySlug($slug, $culture);

        if (!$term) {
            abort(404);
        }

        $taxonomyId = $term->taxonomy_id;

        $this->termService->delete($term->id);

        return redirect()
            ->route('term.browse', ['taxonomy' => $taxonomyId])
            ->with('success', 'Term deleted successfully.');
    }
}
