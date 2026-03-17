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
        // If ?id= is provided, show terms for that taxonomy (matching AtoM URL pattern)
        $taxonomyId = $request->get('id');
        if ($taxonomyId) {
            return redirect()->route('term.browse', ['taxonomy' => $taxonomyId]);
        }

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

        // Enrich results with scope notes, use-for labels, descendant counts, IO counts
        $enriched = collect($pager->getResults())->map(function ($doc) use ($culture) {
            $termId = $doc['id'];
            $doc['scopeNotes'] = \Illuminate\Support\Facades\DB::table('note')
                ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
                ->where('note.object_id', $termId)
                ->where('note_i18n.culture', $culture)
                ->pluck('note_i18n.content')->toArray();
            $doc['useFor'] = \Illuminate\Support\Facades\DB::table('other_name')
                ->join('other_name_i18n', 'other_name.id', '=', 'other_name_i18n.id')
                ->where('other_name.object_id', $termId)
                ->where('other_name_i18n.culture', $culture)
                ->pluck('other_name_i18n.name')->toArray();
            $doc['descendantCount'] = \Illuminate\Support\Facades\DB::table('term')
                ->where('parent_id', $termId)->count();
            $doc['ioCount'] = \Illuminate\Support\Facades\DB::table('object_term_relation')
                ->join('object', 'object_term_relation.object_id', '=', 'object.id')
                ->where('object_term_relation.term_id', $termId)
                ->where('object.class_name', 'QubitInformationObject')->count();
            return $doc;
        })->toArray();

        $iconMap = [42 => 'fa-map-marker-alt', 35 => 'fa-tag', 78 => 'fa-theater-masks', 80 => 'fa-briefcase'];
        $icon = $iconMap[(int) $taxonomyId] ?? 'fa-tag';

        return view('ahg-term-taxonomy::browse', [
            'pager' => $pager,
            'enrichedResults' => $enriched,
            'taxonomyId' => $taxonomyId,
            'taxonomyName' => $taxonomyName,
            'icon' => $icon,
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

        // Use-for labels
        $useFor = \Illuminate\Support\Facades\DB::table('other_name')
            ->join('other_name_i18n', 'other_name.id', '=', 'other_name_i18n.id')
            ->where('other_name.object_id', $term->id)
            ->where('other_name_i18n.culture', $culture)
            ->pluck('other_name_i18n.name')->toArray();

        // Broader term (parent)
        $broaderTerm = null;
        $parentId = \Illuminate\Support\Facades\DB::table('term')->where('id', $term->id)->value('parent_id');
        if ($parentId) {
            $broaderTerm = \Illuminate\Support\Facades\DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->join('slug', 'term.id', '=', 'slug.object_id')
                ->where('term.id', $parentId)
                ->where('term_i18n.culture', $culture)
                ->select('term.id', 'term_i18n.name', 'slug.slug')
                ->first();
        }

        // Narrower terms count
        $narrowerCount = \Illuminate\Support\Facades\DB::table('term')->where('parent_id', $term->id)->count();

        // Related descriptions (paginated)
        $page = max(1, (int) $request->get('page', 1));
        $limit = 10;
        $sort = $request->get('sort', 'lastUpdated');
        $relatedQuery = \Illuminate\Support\Facades\DB::table('object_term_relation')
            ->join('information_object', 'object_term_relation.object_id', '=', 'information_object.id')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('object_term_relation.term_id', $term->id)
            ->where('object.class_name', 'QubitInformationObject')
            ->where('information_object_i18n.culture', $culture);

        $totalRelated = $relatedQuery->count();
        $orderCol = $sort === 'alphabetic' ? 'information_object_i18n.title' : 'object.updated_at';
        $orderDir = $sort === 'alphabetic' ? 'asc' : 'desc';

        $relatedDescriptions = $relatedQuery
            ->select('information_object.id', 'information_object.identifier',
                'information_object_i18n.title', 'slug.slug', 'object.updated_at')
            ->orderBy($orderCol, $orderDir)
            ->offset(($page - 1) * $limit)->limit($limit)->get();

        $iconMap = [42 => 'fa-map-marker-alt', 35 => 'fa-tag', 78 => 'fa-theater-masks', 80 => 'fa-briefcase'];
        $icon = $iconMap[$term->taxonomy_id] ?? 'fa-tag';

        return view('ahg-term-taxonomy::show', [
            'term' => $term,
            'taxonomyName' => $taxonomyName,
            'scopeNote' => $scopeNote,
            'relatedDescriptionsCount' => $relatedDescriptionsCount,
            'useFor' => $useFor,
            'broaderTerm' => $broaderTerm,
            'narrowerCount' => $narrowerCount,
            'relatedDescriptions' => $relatedDescriptions,
            'totalRelated' => $totalRelated,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($totalRelated / $limit)),
            'sort' => $sort,
            'icon' => $icon,
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
