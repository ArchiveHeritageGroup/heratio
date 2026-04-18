<?php

/**
 * TermController - Controller for Heratio
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



namespace AhgTermTaxonomy\Controllers;

use AhgTermTaxonomy\Services\TermBrowseService;
use AhgTermTaxonomy\Services\TermService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
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
    /**
     * AtoM-compatible URL: /taxonomy/index/id/{id}
     */
    public function taxonomyIndexById(Request $request, int $id)
    {
        return redirect()->route('term.browse', array_merge($request->query(), ['taxonomy' => $id]));
    }

    public function taxonomyIndex(Request $request)
    {
        // If ?id= is provided, show terms for that taxonomy (matching AtoM URL pattern)
        $taxonomyId = $request->get('id');
        if ($taxonomyId) {
            return redirect()->route('term.browse', ['taxonomy' => $taxonomyId]);
        }

        $culture = app()->getLocale();
        $page = max(1, (int) $request->get('page', 1));
        $limit = (int) $request->get('limit', SettingHelper::hitsPerPage());

        $allTaxonomies = $this->termService->getTaxonomies($culture);
        $total = $allTaxonomies->count();
        $taxonomies = $allTaxonomies->slice(($page - 1) * $limit, $limit)->values();

        $pager = new \AhgCore\Pagination\SimplePager([
            'hits' => $taxonomies->map(fn ($t) => (array) $t)->toArray(),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);

        return view('ahg-term-taxonomy::taxonomy-index', [
            'taxonomies' => $taxonomies,
            'pager' => $pager,
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
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
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
        // AtoM terms use a root term (parent_id points to taxonomy root, not NULL)
        // Top-level = terms whose parent is NOT in the same taxonomy
        $treeTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->whereNotExists(function ($q) use ($taxonomyId) {
                $q->select(DB::raw(1))
                    ->from('term as parent')
                    ->whereColumn('parent.id', 'term.parent_id')
                    ->where('parent.taxonomy_id', $taxonomyId);
            })
            ->select('term.id', 'term_i18n.name', 'slug.slug')
            ->orderBy('term_i18n.name')
            ->get();

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

        // Narrower terms (children) with names, slugs, and child count for expand indicator
        $narrowerTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.parent_id', $term->id)
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term_i18n.name', 'slug.slug',
                DB::raw('(SELECT COUNT(*) FROM term WHERE parent_id = term.id) as child_count'))
            ->orderBy('term_i18n.name')->get();

        // Sibling terms (other children of the same parent) for treeview navigation
        $siblings = collect();
        if ($parentId) {
            $siblings = DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->join('slug', 'term.id', '=', 'slug.object_id')
                ->where('term.parent_id', $parentId)
                ->where('term.id', '!=', $term->id)
                ->where('term_i18n.culture', $culture)
                ->select('term.id', 'term_i18n.name', 'slug.slug',
                    DB::raw('(SELECT COUNT(*) FROM term WHERE parent_id = term.id) as child_count'))
                ->orderBy('term_i18n.name')->get();
        }

        // List tab: paginated alphabetical list of terms
        // For normal terms: list all in same taxonomy
        // For root/meta terms: list children (which span taxonomies)
        $listPage = max(1, (int) $request->get('listPage', 1));
        $listLimit = 25;
        $listQuery = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term_i18n.culture', $culture);

        if ($narrowerTerms->count() > 0 && $term->taxonomy_id == 30) {
            // Root term — show children
            $listQuery->where('term.parent_id', $term->id);
        } else {
            $listQuery->where('term.taxonomy_id', $term->taxonomy_id);
        }

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
        $prevTerm = null;
        $nextTerm = null;
        if ($term->name) {
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
        }

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
            'siblings' => $siblings ?? collect(),
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
     * Export taxonomy terms as SKOS RDF/XML.
     * Migrated from AtoM sfSkosPlugin export action.
     */
    public function exportSkos(Request $request)
    {
        $taxonomyId = (int) $request->input('taxonomy');
        if (!$taxonomyId) {
            abort(400, 'taxonomy parameter is required');
        }

        $culture = app()->getLocale();
        $taxonomyName = $this->termService->getTaxonomyName($taxonomyId, $culture);

        $terms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->leftJoin('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term.parent_id', 'term_i18n.name', 'slug.slug', 'term.code')
            ->orderBy('term_i18n.name')
            ->get();

        $baseUri = url('/term') . '/';
        $schemeUri = url('/taxonomy/' . $taxonomyId);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . "\n";
        $xml .= '         xmlns:skos="http://www.w3.org/2004/02/skos/core#"' . "\n";
        $xml .= '         xmlns:dct="http://purl.org/dc/terms/">' . "\n\n";

        // Concept scheme
        $xml .= '  <skos:ConceptScheme rdf:about="' . htmlspecialchars($schemeUri) . '">' . "\n";
        $xml .= '    <dct:title>' . htmlspecialchars($taxonomyName ?? 'Taxonomy') . '</dct:title>' . "\n";
        $xml .= '  </skos:ConceptScheme>' . "\n\n";

        // Concepts
        foreach ($terms as $t) {
            $uri = $baseUri . ($t->slug ?: $t->id);
            $xml .= '  <skos:Concept rdf:about="' . htmlspecialchars($uri) . '">' . "\n";
            $xml .= '    <skos:prefLabel xml:lang="' . $culture . '">' . htmlspecialchars($t->name) . '</skos:prefLabel>' . "\n";
            $xml .= '    <skos:inScheme rdf:resource="' . htmlspecialchars($schemeUri) . '"/>' . "\n";

            if ($t->parent_id) {
                $parent = $terms->firstWhere('id', $t->parent_id);
                if ($parent) {
                    $parentUri = $baseUri . ($parent->slug ?: $parent->id);
                    $xml .= '    <skos:broader rdf:resource="' . htmlspecialchars($parentUri) . '"/>' . "\n";
                }
            } else {
                $xml .= '    <skos:topConceptOf rdf:resource="' . htmlspecialchars($schemeUri) . '"/>' . "\n";
            }

            if ($t->code) {
                $xml .= '    <skos:notation>' . htmlspecialchars($t->code) . '</skos:notation>' . "\n";
            }

            $xml .= '  </skos:Concept>' . "\n";
        }

        $xml .= '</rdf:RDF>' . "\n";

        $filename = 'skos-taxonomy-' . $taxonomyId . '.rdf';
        return response($xml, 200, [
            'Content-Type' => 'application/rdf+xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Import SKOS RDF/XML into a taxonomy.
     */
    public function importSkos(Request $request)
    {
        $culture = app()->getLocale();

        if ($request->isMethod('post')) {
            $taxonomyId = (int) $request->input('taxonomy_id');
            if (!$taxonomyId) {
                return back()->with('error', 'Please select a taxonomy.');
            }

            $xmlContent = null;

            if ($request->hasFile('skos_file')) {
                $xmlContent = file_get_contents($request->file('skos_file')->getPathname());
            } elseif ($request->filled('skos_url')) {
                $url = $request->input('skos_url');
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return back()->with('error', 'Invalid URL.');
                }
                $ctx = stream_context_create(['http' => ['timeout' => 30, 'header' => 'Accept: application/rdf+xml,text/xml,*/*']]);
                $xmlContent = @file_get_contents($url, false, $ctx);
                if ($xmlContent === false) {
                    return back()->with('error', 'Failed to fetch SKOS file from URL: ' . $url);
                }
            } else {
                return back()->with('error', 'Please upload a file or provide a URL.');
            }

            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                return back()->with('error', 'Invalid XML content.');
            }

            $xml->registerXPathNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
            $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

            $concepts = $xml->xpath('//skos:Concept');
            $imported = 0;

            foreach ($concepts as $c) {
                $label = (string) $c->children('skos', true)->prefLabel;
                if (!$label) continue;

                // Check if exists
                $existing = DB::table('term')
                    ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                    ->where('term.taxonomy_id', $taxonomyId)
                    ->where('term_i18n.culture', $culture)
                    ->where('term_i18n.name', $label)
                    ->value('term.id');

                if ($existing) continue;

                // Create object + term + i18n + slug
                $objectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitTerm',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('term')->insert([
                    'id' => $objectId,
                    'taxonomy_id' => $taxonomyId,
                    'source_culture' => $culture,
                ]);

                DB::table('term_i18n')->insert([
                    'id' => $objectId,
                    'culture' => $culture,
                    'name' => $label,
                ]);

                $slug = \Illuminate\Support\Str::slug($label) . '-' . $objectId;
                DB::table('slug')->insert([
                    'object_id' => $objectId,
                    'slug' => $slug,
                ]);

                $imported++;
            }

            return redirect()->route('taxonomy.show', $taxonomyId)
                ->with('success', "Imported $imported terms from SKOS file.");
        }

        // GET — show upload form
        $taxonomyId = (int) $request->input('taxonomy');
        $taxonomies = $this->termService->getTaxonomies($culture);

        return view('ahg-term-taxonomy::import-skos', [
            'taxonomies' => $taxonomies,
            'preselectedTaxonomyId' => $taxonomyId,
        ]);
    }

    /**
     * Show the create form for a new term.
     */
    public function create(Request $request)
    {
        $culture = app()->getLocale();

        $taxonomies = $this->termService->getTaxonomies($culture);
        $selectedTaxonomyId = $request->get('taxonomy') ?? $request->get('taxonomy_id');
        $taxonomyName = $selectedTaxonomyId
            ? $this->termService->getTaxonomyName((int) $selectedTaxonomyId, $culture)
            : null;

        // Get all terms in same taxonomy for autocomplete (broad term, related, converse)
        $termsForAutocomplete = $selectedTaxonomyId
            ? DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', $selectedTaxonomyId)
                ->where('term_i18n.culture', $culture)
                ->whereNotNull('term_i18n.name')
                ->select('term.id', 'term_i18n.name')
                ->orderBy('term_i18n.name')->get()
            : collect();

        return view('ahg-term-taxonomy::edit', [
            'term' => null,
            'taxonomies' => $taxonomies,
            'taxonomyName' => $taxonomyName,
            'selectedTaxonomyId' => $selectedTaxonomyId,
            'termsForAutocomplete' => $termsForAutocomplete,
            'useFor' => '',
            'scopeNote' => null,
            'sourceNote' => null,
            'displayNote' => null,
            'parentTerm' => null,
            'converseTerm' => null,
            'relatedTerms' => '',
            'narrowerTerms' => '',
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
            'parent_id' => $request->input('parent_id'),
            'use_for' => $request->input('use_for'),
            'scope_note' => $request->input('scope_note'),
            'source_note' => $request->input('source_note'),
            'display_note' => $request->input('display_note'),
            'related_terms' => $request->input('related_terms'),
            'converse_term' => $request->input('converse_term'),
            'narrow_terms' => $request->input('narrow_terms'),
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

        // Existing data for the form
        $useFor = DB::table('other_name')
            ->join('other_name_i18n', 'other_name.id', '=', 'other_name_i18n.id')
            ->where('other_name.object_id', $term->id)->where('other_name_i18n.culture', $culture)
            ->pluck('other_name_i18n.name')->implode(', ');

        $scopeNote = DB::table('note')->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $term->id)->where('note.type_id', 122)->where('note_i18n.culture', $culture)
            ->value('note_i18n.content');
        $sourceNote = DB::table('note')->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $term->id)->where('note.type_id', 121)->where('note_i18n.culture', $culture)
            ->value('note_i18n.content');
        $displayNote = DB::table('note')->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $term->id)->where('note.type_id', 123)->where('note_i18n.culture', $culture)
            ->value('note_i18n.content');

        $parentTerm = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.id', DB::table('term')->where('id', $term->id)->value('parent_id'))
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term_i18n.name')->first();

        $termsForAutocomplete = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', $term->taxonomy_id)
            ->where('term_i18n.culture', $culture)->whereNotNull('term_i18n.name')
            ->select('term.id', 'term_i18n.name')->orderBy('term_i18n.name')->get();

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
                ->where('term.id', $converseId)->where('term_i18n.culture', $culture)
                ->select('term.id', 'term_i18n.name')->first();
        }

        // Related terms (relation type 157)
        $relatedTerms = DB::table('relation')
            ->where(function ($q) use ($term) {
                $q->where('subject_id', $term->id)->orWhere('object_id', $term->id);
            })
            ->where('type_id', 157)->get()
            ->map(function ($rel) use ($term, $culture) {
                $otherId = $rel->subject_id == $term->id ? $rel->object_id : $rel->subject_id;
                return DB::table('term')
                    ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                    ->where('term.id', $otherId)->where('term_i18n.culture', $culture)
                    ->value('term_i18n.name');
            })->filter()->implode(', ');

        // Narrower terms (children) — for display only, not pre-populated in the "add new" field
        $narrowerTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.parent_id', $term->id)
            ->where('term_i18n.culture', $culture)
            ->pluck('term_i18n.name')->implode(', ');

        return view('ahg-term-taxonomy::edit', [
            'term' => $term,
            'taxonomies' => $taxonomies,
            'taxonomyName' => $taxonomyName,
            'selectedTaxonomyId' => $term->taxonomy_id,
            'termsForAutocomplete' => $termsForAutocomplete,
            'useFor' => $useFor,
            'scopeNote' => $scopeNote,
            'sourceNote' => $sourceNote,
            'displayNote' => $displayNote,
            'parentTerm' => $parentTerm,
            'converseTerm' => $converseTerm,
            'relatedTerms' => $relatedTerms,
            'narrowerTerms' => $narrowerTerms,
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
            'parent_id' => $request->input('parent_id'),
            'use_for' => $request->input('use_for'),
            'related_terms' => $request->input('related_terms'),
            'converse_term' => $request->input('converse_term'),
            'self_reciprocal' => $request->input('self_reciprocal'),
            'narrow_terms' => $request->input('narrow_terms'),
            'scopeNotes' => $request->input('scopeNotes', []),
            'sourceNotes' => $request->input('sourceNotes', []),
            'displayNotes' => $request->input('displayNotes', []),
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

    /**
     * AJAX autocomplete endpoint for terms within a given taxonomy.
     *
     * GET /term/autocomplete?query=...&taxonomy_id=35
     * Returns JSON: [{id: ..., name: ...}, ...]
     */
    public function autocomplete(Request $request)
    {
        $query = $request->get('query', '');
        $taxonomyId = (int) $request->get('taxonomy_id', 0);
        $culture = app()->getLocale();
        $limit = (int) $request->get('limit', 20);

        if (!$taxonomyId) {
            return response()->json([]);
        }

        $results = DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) {
                $j->on('term.id', '=', 'term_i18n.id')
                  ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.name', 'LIKE', '%' . $query . '%')
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->limit($limit)
            ->get();

        return response()->json($results);
    }
}
