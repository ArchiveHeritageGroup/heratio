<?php

namespace AhgTermTaxonomy\Controllers;

use AhgTermTaxonomy\Services\TermBrowseService;
use AhgTermTaxonomy\Services\TermService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $doc['actorCount'] = \Illuminate\Support\Facades\DB::table('object_term_relation')
                ->join('object', 'object_term_relation.object_id', '=', 'object.id')
                ->where('object_term_relation.term_id', $termId)
                ->where('object.class_name', 'QubitActor')->count();
            $doc['isProtected'] = \Illuminate\Support\Facades\DB::table('term')
                ->where('id', $termId)->value('parent_id') === null;
            return $doc;
        })->toArray();

        $iconMap = [42 => 'fa-map-marker-alt', 35 => 'fa-tag', 78 => 'fa-theater-masks', 80 => 'fa-briefcase'];
        $icon = $iconMap[(int) $taxonomyId] ?? 'fa-tag';

        // Sidebar: top-level terms for treeview
        $treeTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->whereNull('term.parent_id')
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term_i18n.name', 'slug.slug')
            ->orderBy('term_i18n.name')
            ->limit(50)->get();

        return view('ahg-term-taxonomy::browse', [
            'pager' => $pager,
            'enrichedResults' => $enriched,
            'taxonomyId' => $taxonomyId,
            'taxonomyName' => $taxonomyName,
            'icon' => $icon,
            'treeTerms' => $treeTerms,
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

        // Related actor count (for navigate tabs)
        $relatedActorCount = DB::table('object_term_relation')
            ->join('object', 'object_term_relation.object_id', '=', 'object.id')
            ->where('object_term_relation.term_id', $term->id)
            ->where('object.class_name', 'QubitActor')->count();

        // Direct-only count (exclude narrower terms)
        $directCount = $relatedDescriptionsCount; // same if no narrower terms
        $narrowerIds = DB::table('term')->where('parent_id', $term->id)->pluck('id')->toArray();
        if (!empty($narrowerIds)) {
            $directCount = DB::table('object_term_relation')
                ->join('object', 'object_term_relation.object_id', '=', 'object.id')
                ->where('object_term_relation.term_id', $term->id)
                ->where('object.class_name', 'QubitInformationObject')->count();
        }
        $onlyDirect = $request->has('onlyDirect');

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

        // Related descriptions (paginated) — include narrower terms unless onlyDirect
        $page = max(1, (int) $request->get('page', 1));
        $limit = 10;
        $sort = $request->get('sort', 'lastUpdated');

        $termIds = [$term->id];
        if (!$onlyDirect && !empty($narrowerIds)) {
            $termIds = array_merge($termIds, $narrowerIds);
        }

        $relatedQuery = DB::table('object_term_relation')
            ->join('information_object', 'object_term_relation.object_id', '=', 'information_object.id')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->whereIn('object_term_relation.term_id', $termIds)
            ->where('object.class_name', 'QubitInformationObject')
            ->where('information_object_i18n.culture', $culture);

        $totalRelated = $relatedQuery->count();
        $orderCol = $sort === 'alphabetic' ? 'information_object_i18n.title' : 'object.updated_at';
        $orderDir = $sort === 'alphabetic' ? 'asc' : 'desc';

        $relatedDescriptions = $relatedQuery
            ->select('information_object.id', 'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object_i18n.title',
                'information_object_i18n.scope_and_content',
                'slug.slug', 'object.updated_at')
            ->orderBy($orderCol, $orderDir)
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(function ($desc) use ($culture) {
                // Level of description name
                $desc->levelName = $desc->level_of_description_id
                    ? DB::table('term_i18n')->where('id', $desc->level_of_description_id)->where('culture', $culture)->value('name')
                    : null;

                // Creator (from event type 111 = creation)
                $creator = DB::table('event')
                    ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
                    ->where('event.object_id', $desc->id)
                    ->where('event.type_id', 111)
                    ->where('actor_i18n.culture', $culture)
                    ->whereNotNull('event.actor_id')
                    ->select('actor_i18n.authorized_form_of_name')
                    ->first();
                $desc->creatorName = $creator->authorized_form_of_name ?? null;

                // Dates from events
                $dates = DB::table('event')
                    ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
                    ->where('event.object_id', $desc->id)
                    ->where('event_i18n.culture', $culture)
                    ->select('event_i18n.date', 'event.start_date', 'event.end_date')
                    ->first();
                $desc->dateDisplay = $dates->date ?? $dates->start_date ?? null;

                // Get master digital object
                $master = DB::table('digital_object')
                    ->where('object_id', $desc->id)
                    ->whereNull('parent_id')
                    ->first();

                $desc->thumbnail = null;
                $desc->mediaIcon = null;

                if ($master) {
                    // Get thumbnail derivative (usage_id=142, child of master)
                    $thumb = DB::table('digital_object')
                        ->where('parent_id', $master->id)
                        ->where('usage_id', 142)
                        ->select('path', 'name')
                        ->first();

                    if ($thumb) {
                        // Path starts with /uploads/ which nginx aliases to AtoM's uploads dir
                        $desc->thumbnail = ($thumb->path ?? '') . ($thumb->name ?? '');
                    } else {
                        // No thumbnail — use generic icon image from AtoM
                        $desc->thumbnail = '/generic-icons/' . match ((int) ($master->media_type_id ?? 0)) {
                            135 => 'audio.png',
                            136 => 'image.png',
                            137 => 'video.png',
                            138 => 'text.png',
                            default => 'unknown.png',
                        };
                    }
                }
                return $desc;
            });

        // Source notes (type 121)
        $sourceNotes = DB::table('note')->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $term->id)->where('note.type_id', 121)
            ->where('note_i18n.culture', $culture)->pluck('note_i18n.content')->toArray();

        // Display notes (type 123)
        $displayNotes = DB::table('note')->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $term->id)->where('note.type_id', 123)
            ->where('note_i18n.culture', $culture)->pluck('note_i18n.content')->toArray();

        // Narrower terms (children) with names and slugs
        $narrowerTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.parent_id', $term->id)
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term_i18n.name', 'slug.slug')
            ->orderBy('term_i18n.name')->get();

        // List tab: paginated alphabetical list of all terms in same taxonomy
        $listPage = max(1, (int) $request->get('listPage', 1));
        $listLimit = 25;
        $listQuery = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $term->taxonomy_id)
            ->where('term_i18n.culture', $culture);
        $listTotal = $listQuery->count();
        $listTerms = $listQuery
            ->select('term.id', 'term_i18n.name', 'slug.slug')
            ->orderBy('term_i18n.name')
            ->offset(($listPage - 1) * $listLimit)->limit($listLimit)->get();

        // Converse term (relation type 177)
        $converseTerm = null;
        $converseRel = DB::table('relation')
            ->where(function ($q) use ($term) {
                $q->where('subject_id', $term->id)->orWhere('object_id', $term->id);
            })
            ->where('type_id', 177)->first();
        if ($converseRel) {
            $converseId = $converseRel->subject_id == $term->id ? $converseRel->object_id : $converseRel->subject_id;
            $converseTerm = DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->join('slug', 'term.id', '=', 'slug.object_id')
                ->where('term.id', $converseId)->where('term_i18n.culture', $culture)
                ->select('term.id', 'term_i18n.name', 'slug.slug')->first();
        }

        // Associated terms (relation type 157)
        $associatedTerms = DB::table('relation')
            ->where(function ($q) use ($term) {
                $q->where('subject_id', $term->id)->orWhere('object_id', $term->id);
            })
            ->where('type_id', 157)->get()
            ->map(function ($rel) use ($term, $culture) {
                $otherId = $rel->subject_id == $term->id ? $rel->object_id : $rel->subject_id;
                return DB::table('term')
                    ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                    ->join('slug', 'term.id', '=', 'slug.object_id')
                    ->where('term.id', $otherId)->where('term_i18n.culture', $culture)
                    ->select('term.id', 'term_i18n.name', 'slug.slug')->first();
            })->filter()->values();

        // Breadcrumb (ancestors)
        $breadcrumb = collect();
        $currentParentId = $parentId;
        while ($currentParentId && $currentParentId != \Illuminate\Support\Facades\DB::table('term')->where('taxonomy_id', $term->taxonomy_id)->whereNull('parent_id')->value('id')) {
            $ancestor = DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->join('slug', 'term.id', '=', 'slug.object_id')
                ->where('term.id', $currentParentId)->where('term_i18n.culture', $culture)
                ->select('term.id', 'term_i18n.name', 'slug.slug', 'term.parent_id')->first();
            if (!$ancestor) break;
            $breadcrumb->prepend($ancestor);
            $currentParentId = $ancestor->parent_id;
        }

        // Prev/next terms in same taxonomy (for navigation)
        $prevTerm = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $term->taxonomy_id)
            ->where('term_i18n.culture', $culture)
            ->where('term_i18n.name', '<', $term->name)
            ->orderByDesc('term_i18n.name')
            ->select('term_i18n.name', 'slug.slug')->first();

        $nextTerm = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $term->taxonomy_id)
            ->where('term_i18n.culture', $culture)
            ->where('term_i18n.name', '>', $term->name)
            ->orderBy('term_i18n.name')
            ->select('term_i18n.name', 'slug.slug')->first();

        // Google Maps API key for Place terms
        $mapApiKey = ($term->taxonomy_id == 42 && !empty($term->code))
            ? DB::table('setting')
                ->leftJoin('setting_i18n', function ($j) { $j->on('setting.id', '=', 'setting_i18n.id')->where('setting_i18n.culture', '=', 'en'); })
                ->where('setting.name', 'google_maps_api_key')->whereNull('setting.scope')
                ->value('setting_i18n.value')
            : null;

        // Sort with additional options matching AtoM
        $orderMap = [
            'lastUpdated' => ['object.updated_at', 'desc'],
            'alphabetic' => ['information_object_i18n.title', 'asc'],
            'referenceCode' => ['information_object.identifier', 'asc'],
            'date' => ['information_object.id', 'asc'], // Start date would need event join
        ];
        // Re-sort uses the already-built $relatedQuery which respects $termIds and onlyDirect

        $iconMap = [42 => 'fa-map-marker-alt', 35 => 'fa-tag', 78 => 'fa-theater-masks', 80 => 'fa-briefcase'];
        $icon = $iconMap[$term->taxonomy_id] ?? 'fa-tag';

        return view('ahg-term-taxonomy::show', [
            'term' => $term,
            'taxonomyName' => $taxonomyName,
            'scopeNote' => $scopeNote,
            'sourceNotes' => $sourceNotes,
            'displayNotes' => $displayNotes,
            'relatedDescriptionsCount' => $relatedDescriptionsCount,
            'useFor' => $useFor,
            'broaderTerm' => $broaderTerm,
            'narrowerCount' => $narrowerCount,
            'narrowerTerms' => $narrowerTerms,
            'listTerms' => $listTerms,
            'listTotal' => $listTotal,
            'listPage' => $listPage,
            'listLastPage' => max(1, (int) ceil($listTotal / $listLimit)),
            'converseTerm' => $converseTerm,
            'associatedTerms' => $associatedTerms,
            'breadcrumb' => $breadcrumb,
            'prevTerm' => $prevTerm,
            'nextTerm' => $nextTerm,
            'mapApiKey' => $mapApiKey,
            'relatedDescriptions' => $relatedDescriptions,
            'totalRelated' => $totalRelated,
            'relatedActorCount' => $relatedActorCount,
            'directCount' => $directCount,
            'onlyDirect' => $onlyDirect,
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
