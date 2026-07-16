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

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SecretCrypto;
use AhgCore\Services\SettingHelper;
use AhgTermTaxonomy\Services\CrossMatchService;
use AhgTermTaxonomy\Services\TermBrowseService;
use AhgTermTaxonomy\Services\TermService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TermController extends Controller
{
    protected TermService $termService;

    protected CrossMatchService $crossMatchService;

    public function __construct(TermService $termService, CrossMatchService $crossMatchService)
    {
        $this->termService = $termService;
        $this->crossMatchService = $crossMatchService;
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

        // #743 browseTerm filters: parent term + scope-note-only toggle.
        // Empty string from the form posts is treated as "filter disabled".
        $parentFilter = $request->get('parent', '');
        $scopeNoteOnly = $request->boolean('scopeNoteOnly');

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
            'taxonomy_id' => $taxonomyId,
            'parent' => $parentFilter,
            'scopeNoteOnly' => $scopeNoteOnly,
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
            // #743 browseTerm filter state for sticky form rendering.
            'parentFilter' => $parentFilter,
            'scopeNoteOnly' => $scopeNoteOnly,
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

        // Fallback: a term with no slug row is still reachable by numeric id (the tree
        // view links such terms by id to avoid an empty-slug 500).
        if (! $term && ctype_digit($slug)) {
            $term = $this->termService->getById((int) $slug, $culture);
        }

        if (! $term) {
            abort(404);
        }

        // #51 ACL enforcement: read-side gate. Admin bypass built in.
        if (! \AhgCore\Services\AclService::hasPermission(\Illuminate\Support\Facades\Auth::id(), 'read', (int) $term->id)) {
            abort(403, 'You do not have permission to view this term.');
        }

        // #1388: a term carrying a restricted community protocol must not leak to
        // the public (the ACL 'read' gate is a no-op for anonymous). Editors bypass.
        if (! \AhgCore\Services\TermProtocolGate::allowsTerm((int) $term->id)) {
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
        if (! empty($narrowerIds)) {
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
        if (! $onlyDirect && ! empty($narrowerIds)) {
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
                        $desc->thumbnail = ($thumb->path ?? '').($thumb->name ?? '');
                    } else {
                        // No thumbnail — use generic icon image from AtoM
                        $desc->thumbnail = '/generic-icons/'.match ((int) ($master->media_type_id ?? 0)) {
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
            if (! $ancestor) {
                break;
            }
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
        $mapApiKey = ($term->taxonomy_id == 42 && ! empty($term->code))
            ? (SecretCrypto::reveal((string) DB::table('setting') // #1395(D) decrypt-at-rest
                ->leftJoin('setting_i18n', function ($j) {
                    $j->on('setting.id', '=', 'setting_i18n.id')->where('setting_i18n.culture', '=', 'en');
                })
                ->where('setting.name', 'google_maps_api_key')->whereNull('setting.scope')
                ->value('setting_i18n.value')) ?: null)
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
            // #1388 P1.5 - community-protocol provenance badge (TK/BC label + owner).
            'termProtocol' => \AhgCore\Services\TermProtocolService::protocolsForTerm((int) $term->id)->first(),
        ]);
    }

    /**
     * Export taxonomy terms as SKOS in one of four serialisations.
     *
     * Format dispatch happens here: the data walk is shared, only the
     * serialisation layer differs. Supported formats:
     *   - rdfxml    (.rdf,     application/rdf+xml)        — original endpoint
     *   - turtle    (.ttl,     text/turtle)                — #661 Phase 2
     *   - ntriples  (.nt,      application/n-triples)      — #661 Phase 2
     *   - jsonld    (.jsonld,  application/ld+json)        — #661 Phase 2
     *
     * Format is selected via the `format` route default OR ?format=… query
     * param. Defaults to rdfxml so the legacy `/term/export/skos` URL keeps
     * working byte-for-byte.
     *
     * Migrated from AtoM sfSkosPlugin export action.
     */
    public function exportSkos(Request $request)
    {
        $taxonomyId = (int) $request->input('taxonomy');
        if (! $taxonomyId) {
            abort(400, 'taxonomy parameter is required');
        }

        $format = strtolower((string) ($request->route('format') ?? $request->input('format', 'rdfxml')));
        $allowed = ['rdfxml', 'turtle', 'ntriples', 'jsonld'];
        if (! in_array($format, $allowed, true)) {
            $format = 'rdfxml';
        }

        // #661 Phase 3: opt-in SKOS-XL emission alongside the plain literals.
        $skosXl = (bool) $request->input('skos_xl', false);

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

        $baseUri = url('/term').'/';
        $schemeUri = url('/taxonomy/'.$taxonomyId);

        // -------- #661 Phase 1: SKOS label/note types completeness ---------
        // Pre-fetch the per-term additional labels + notes in 2 batched
        // queries so the per-term loop below stays O(N) — avoids N+1.
        $termIds = $terms->pluck('id')->all();
        $altLabelsByTerm = [];
        $hiddenLabelsByTerm = [];
        $scopeNotesByTerm = [];
        $historyNotesByTerm = [];

        if (! empty($termIds)) {
            // other_name → skos:altLabel. Match the same culture filter as
            // prefLabel above so we don't emit one altLabel per supported
            // locale when only the requested culture is wanted. Phase 2
            // can drop the filter when SKOS-XL labels per locale are added.
            $otherNames = DB::table('other_name')
                ->join('other_name_i18n', 'other_name.id', '=', 'other_name_i18n.id')
                ->whereIn('other_name.object_id', $termIds)
                ->where('other_name_i18n.culture', $culture)
                ->select('other_name.object_id', 'other_name_i18n.name')
                ->get();
            foreach ($otherNames as $on) {
                $name = trim((string) $on->name);
                if ($name === '') {
                    continue;
                }
                $altLabelsByTerm[$on->object_id][] = ['lang' => $culture, 'name' => $name];
            }

            // note → skos:scopeNote. Filtered to the export culture to
            // avoid N-cultures × N-notes output explosion. Phase 2 can
            // distinguish scopeNote / historyNote / changeNote based on
            // note.type_id once that mapping is documented.
            $notes = DB::table('note')
                ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
                ->whereIn('note.object_id', $termIds)
                ->where('note_i18n.culture', $culture)
                ->select('note.object_id', 'note_i18n.content')
                ->get();
            foreach ($notes as $n) {
                $content = trim((string) $n->content);
                if ($content === '') {
                    continue;
                }
                $scopeNotesByTerm[$n->object_id][] = ['lang' => $culture, 'text' => $content];
            }
        }

        // #661 Phase 3: cross-vocabulary mapping links per term. Single batched
        // lookup avoids N+1 across the concept walk; collapses to empty list
        // if the table hasn't been installed yet on legacy DBs.
        $crossMatchesByTerm = [];
        if (! empty($termIds) && Schema::hasTable('ahg_term_cross_match')) {
            $crossMatchesByTerm = $this->crossMatchService->forTerms($termIds);
        }

        // Build a normalised concept list ONCE - used by all serialisers.
        $concepts = [];
        foreach ($terms as $t) {
            $uri = $baseUri.($t->slug ?: $t->id);
            $parentUri = null;
            if ($t->parent_id) {
                $parent = $terms->firstWhere('id', $t->parent_id);
                if ($parent) {
                    $parentUri = $baseUri.($parent->slug ?: $parent->id);
                }
            }
            // #661 Phase 3 - normalise the cross-match rows into a simple
            // shape for the serialisers (we don't ship internal db ids out).
            $crossMatches = [];
            foreach (($crossMatchesByTerm[$t->id] ?? []) as $cm) {
                $crossMatches[] = [
                    'match_type' => (string) $cm->match_type,
                    'target_uri' => (string) $cm->target_uri,
                    'target_label' => $cm->target_label !== null ? (string) $cm->target_label : null,
                    'target_vocab' => $cm->target_vocab !== null ? (string) $cm->target_vocab : null,
                ];
            }

            $concepts[] = [
                'id' => (int) $t->id,
                'uri' => $uri,
                'prefLabel' => (string) $t->name,
                'broader' => $parentUri,
                'topConcept' => ($parentUri === null),
                'notation' => $t->code ? (string) $t->code : null,
                'altLabels' => $altLabelsByTerm[$t->id] ?? [],
                'hiddenLabels' => $hiddenLabelsByTerm[$t->id] ?? [],
                'scopeNotes' => $scopeNotesByTerm[$t->id] ?? [],
                'historyNotes' => $historyNotesByTerm[$t->id] ?? [],
                'crossMatches' => $crossMatches,
            ];
        }

        $scheme = [
            'uri' => $schemeUri,
            'title' => $taxonomyName ?? 'Taxonomy',
            'culture' => $culture,
            'skos_xl' => $skosXl,
            'base_uri' => $baseUri,
        ];

        switch ($format) {
            case 'turtle':
                $body = $this->serialiseSkosTurtle($scheme, $concepts);
                $contentType = 'text/turtle; charset=utf-8';
                $ext = 'ttl';
                break;
            case 'ntriples':
                $body = $this->serialiseSkosNTriples($scheme, $concepts);
                $contentType = 'application/n-triples; charset=utf-8';
                $ext = 'nt';
                break;
            case 'jsonld':
                $body = $this->serialiseSkosJsonLd($scheme, $concepts);
                $contentType = 'application/ld+json; charset=utf-8';
                $ext = 'jsonld';
                break;
            case 'rdfxml':
            default:
                $body = $this->serialiseSkosRdfXml($scheme, $concepts);
                $contentType = 'application/rdf+xml; charset=utf-8';
                $ext = 'rdf';
                break;
        }

        $filename = 'skos-taxonomy-'.$taxonomyId.'.'.$ext;

        return response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Serialise concept scheme + concepts as SKOS RDF/XML.
     * Byte-for-byte identical to the original exportSkos() output.
     */
    private function serialiseSkosRdfXml(array $scheme, array $concepts): string
    {
        $culture = $scheme['culture'];
        $schemeUri = $scheme['uri'];
        $skosXl = (bool) ($scheme['skos_xl'] ?? false);
        $baseUri = (string) ($scheme['base_uri'] ?? '');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"'."\n";
        $xml .= '         xmlns:skos="http://www.w3.org/2004/02/skos/core#"';
        if ($skosXl) {
            $xml .= "\n".'         xmlns:skosxl="http://www.w3.org/2008/05/skos-xl#"';
        }
        $xml .= "\n".'         xmlns:dct="http://purl.org/dc/terms/">'."\n\n";

        $xml .= '  <skos:ConceptScheme rdf:about="'.htmlspecialchars($schemeUri).'">'."\n";
        $xml .= '    <dct:title>'.htmlspecialchars($scheme['title']).'</dct:title>'."\n";
        $xml .= '  </skos:ConceptScheme>'."\n\n";

        foreach ($concepts as $c) {
            $xml .= '  <skos:Concept rdf:about="'.htmlspecialchars($c['uri']).'">'."\n";
            $xml .= '    <skos:prefLabel xml:lang="'.$culture.'">'.htmlspecialchars($c['prefLabel']).'</skos:prefLabel>'."\n";
            $xml .= '    <skos:inScheme rdf:resource="'.htmlspecialchars($schemeUri).'"/>'."\n";

            if ($c['broader']) {
                $xml .= '    <skos:broader rdf:resource="'.htmlspecialchars($c['broader']).'"/>'."\n";
            } else {
                $xml .= '    <skos:topConceptOf rdf:resource="'.htmlspecialchars($schemeUri).'"/>'."\n";
            }

            if ($c['notation']) {
                $xml .= '    <skos:notation>'.htmlspecialchars($c['notation']).'</skos:notation>'."\n";
            }

            // #661 Phase 1 additions - altLabel / hiddenLabel / scopeNote / historyNote
            foreach (($c['altLabels'] ?? []) as $alt) {
                $xml .= '    <skos:altLabel xml:lang="'.htmlspecialchars($alt['lang']).'">'.htmlspecialchars($alt['name']).'</skos:altLabel>'."\n";
            }
            foreach (($c['hiddenLabels'] ?? []) as $hid) {
                $xml .= '    <skos:hiddenLabel xml:lang="'.htmlspecialchars($hid['lang']).'">'.htmlspecialchars($hid['name']).'</skos:hiddenLabel>'."\n";
            }
            foreach (($c['scopeNotes'] ?? []) as $sn) {
                $xml .= '    <skos:scopeNote xml:lang="'.htmlspecialchars($sn['lang']).'">'.htmlspecialchars($sn['text']).'</skos:scopeNote>'."\n";
            }
            foreach (($c['historyNotes'] ?? []) as $hn) {
                $xml .= '    <skos:historyNote xml:lang="'.htmlspecialchars($hn['lang']).'">'.htmlspecialchars($hn['text']).'</skos:historyNote>'."\n";
            }

            // #661 Phase 3 - cross-vocab mapping links.
            foreach (($c['crossMatches'] ?? []) as $cm) {
                $pred = $this->mapPredicate($cm['match_type']);
                $xml .= '    <skos:'.$pred.' rdf:resource="'.htmlspecialchars($cm['target_uri']).'"/>'."\n";
            }

            // #661 Phase 3 - SKOS-XL label references on the concept itself.
            if ($skosXl) {
                $xlPreUri = $this->xlLabelUri($baseUri, $c, $culture, 'pref', $c['prefLabel']);
                $xml .= '    <skosxl:prefLabel rdf:resource="'.htmlspecialchars($xlPreUri).'"/>'."\n";
                foreach (($c['altLabels'] ?? []) as $alt) {
                    $xlUri = $this->xlLabelUri($baseUri, $c, $alt['lang'], 'alt', $alt['name']);
                    $xml .= '    <skosxl:altLabel rdf:resource="'.htmlspecialchars($xlUri).'"/>'."\n";
                }
                foreach (($c['hiddenLabels'] ?? []) as $hid) {
                    $xlUri = $this->xlLabelUri($baseUri, $c, $hid['lang'], 'hidden', $hid['name']);
                    $xml .= '    <skosxl:hiddenLabel rdf:resource="'.htmlspecialchars($xlUri).'"/>'."\n";
                }
            }

            $xml .= '  </skos:Concept>'."\n";

            // #661 Phase 3 - emit a full <skosxl:Label> resource for every
            // referenced label URI. The concept block above only points at
            // these by URI so we describe them once each here.
            if ($skosXl) {
                $xlPreUri = $this->xlLabelUri($baseUri, $c, $culture, 'pref', $c['prefLabel']);
                $xml .= $this->rdfXmlXlLabel($xlPreUri, $c['prefLabel'], $culture);
                foreach (($c['altLabels'] ?? []) as $alt) {
                    $xlUri = $this->xlLabelUri($baseUri, $c, $alt['lang'], 'alt', $alt['name']);
                    $xml .= $this->rdfXmlXlLabel($xlUri, $alt['name'], $alt['lang']);
                }
                foreach (($c['hiddenLabels'] ?? []) as $hid) {
                    $xlUri = $this->xlLabelUri($baseUri, $c, $hid['lang'], 'hidden', $hid['name']);
                    $xml .= $this->rdfXmlXlLabel($xlUri, $hid['name'], $hid['lang']);
                }
            }
        }

        $xml .= '</rdf:RDF>'."\n";

        return $xml;
    }

    /**
     * Emit a single <skosxl:Label> resource for RDF/XML.
     */
    private function rdfXmlXlLabel(string $uri, string $literal, string $lang): string
    {
        $now = date('c');
        $out = '  <skosxl:Label rdf:about="'.htmlspecialchars($uri).'">'."\n";
        $out .= '    <skosxl:literalForm xml:lang="'.htmlspecialchars($lang).'">'.htmlspecialchars($literal).'</skosxl:literalForm>'."\n";
        $out .= '    <dct:created>'.$now.'</dct:created>'."\n";
        $out .= '    <dct:creator>heratio</dct:creator>'."\n";
        $out .= '  </skosxl:Label>'."\n";

        return $out;
    }

    /**
     * Serialise concept scheme + concepts as Turtle (.ttl).
     * Spec: https://www.w3.org/TR/turtle/
     */
    private function serialiseSkosTurtle(array $scheme, array $concepts): string
    {
        $culture = $scheme['culture'];
        $schemeUri = $scheme['uri'];
        $skosXl = (bool) ($scheme['skos_xl'] ?? false);
        $baseUri = (string) ($scheme['base_uri'] ?? '');

        $ttl = "@prefix rdf:    <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
        $ttl .= "@prefix skos:   <http://www.w3.org/2004/02/skos/core#> .\n";
        if ($skosXl) {
            $ttl .= "@prefix skosxl: <http://www.w3.org/2008/05/skos-xl#> .\n";
        }
        $ttl .= "@prefix dct:    <http://purl.org/dc/terms/> .\n";
        $ttl .= "@prefix xsd:    <http://www.w3.org/2001/XMLSchema#> .\n\n";

        $ttl .= '<'.$schemeUri.'> a skos:ConceptScheme ;'."\n";
        $ttl .= '    dct:title '.$this->ttlString($scheme['title']).' .'."\n\n";

        foreach ($concepts as $c) {
            $ttl .= '<'.$c['uri'].'> a skos:Concept ;'."\n";
            $ttl .= '    skos:prefLabel '.$this->ttlLangString($c['prefLabel'], $culture).' ;'."\n";
            $ttl .= '    skos:inScheme <'.$schemeUri.'> ;'."\n";
            if ($c['broader']) {
                $ttl .= '    skos:broader <'.$c['broader'].'> ;'."\n";
            } else {
                $ttl .= '    skos:topConceptOf <'.$schemeUri.'> ;'."\n";
            }
            if ($c['notation']) {
                $ttl .= '    skos:notation '.$this->ttlString($c['notation']).' ;'."\n";
            }
            // #661 Phase 1 additions
            foreach (($c['altLabels'] ?? []) as $alt) {
                $ttl .= '    skos:altLabel '.$this->ttlLangString($alt['name'], $alt['lang']).' ;'."\n";
            }
            foreach (($c['hiddenLabels'] ?? []) as $hid) {
                $ttl .= '    skos:hiddenLabel '.$this->ttlLangString($hid['name'], $hid['lang']).' ;'."\n";
            }
            foreach (($c['scopeNotes'] ?? []) as $sn) {
                $ttl .= '    skos:scopeNote '.$this->ttlLangString($sn['text'], $sn['lang']).' ;'."\n";
            }
            foreach (($c['historyNotes'] ?? []) as $hn) {
                $ttl .= '    skos:historyNote '.$this->ttlLangString($hn['text'], $hn['lang']).' ;'."\n";
            }
            // #661 Phase 3 - cross-vocab mapping links
            foreach (($c['crossMatches'] ?? []) as $cm) {
                $pred = $this->mapPredicate($cm['match_type']);
                $ttl .= '    skos:'.$pred.' <'.$cm['target_uri'].'> ;'."\n";
            }
            // #661 Phase 3 - SKOS-XL label references on the concept
            if ($skosXl) {
                $xlPreUri = $this->xlLabelUri($baseUri, $c, $culture, 'pref', $c['prefLabel']);
                $ttl .= '    skosxl:prefLabel <'.$xlPreUri.'> ;'."\n";
                foreach (($c['altLabels'] ?? []) as $alt) {
                    $xlUri = $this->xlLabelUri($baseUri, $c, $alt['lang'], 'alt', $alt['name']);
                    $ttl .= '    skosxl:altLabel <'.$xlUri.'> ;'."\n";
                }
                foreach (($c['hiddenLabels'] ?? []) as $hid) {
                    $xlUri = $this->xlLabelUri($baseUri, $c, $hid['lang'], 'hidden', $hid['name']);
                    $ttl .= '    skosxl:hiddenLabel <'.$xlUri.'> ;'."\n";
                }
            }
            // Replace trailing ' ;' with ' .'
            $ttl = preg_replace('/ ;\n$/', " .\n", $ttl);
            $ttl .= "\n";

            // #661 Phase 3 - skosxl:Label resources for every referenced URI.
            if ($skosXl) {
                $now = date('c');
                $writeLabel = function (string $uri, string $literal, string $lang) use (&$ttl, $now) {
                    $ttl .= '<'.$uri.'> a skosxl:Label ;'."\n";
                    $ttl .= '    skosxl:literalForm '.$this->ttlLangString($literal, $lang).' ;'."\n";
                    $ttl .= '    dct:created "'.$now.'"^^xsd:dateTime ;'."\n";
                    $ttl .= '    dct:creator '.$this->ttlString('heratio').' .'."\n\n";
                };
                $writeLabel(
                    $this->xlLabelUri($baseUri, $c, $culture, 'pref', $c['prefLabel']),
                    $c['prefLabel'],
                    $culture
                );
                foreach (($c['altLabels'] ?? []) as $alt) {
                    $writeLabel(
                        $this->xlLabelUri($baseUri, $c, $alt['lang'], 'alt', $alt['name']),
                        $alt['name'],
                        $alt['lang']
                    );
                }
                foreach (($c['hiddenLabels'] ?? []) as $hid) {
                    $writeLabel(
                        $this->xlLabelUri($baseUri, $c, $hid['lang'], 'hidden', $hid['name']),
                        $hid['name'],
                        $hid['lang']
                    );
                }
            }
        }

        return $ttl;
    }

    /**
     * Serialise concept scheme + concepts as N-Triples (.nt).
     * Spec: https://www.w3.org/TR/n-triples/
     * Every line: <s> <p> <o> .
     */
    private function serialiseSkosNTriples(array $scheme, array $concepts): string
    {
        $culture = $scheme['culture'];
        $schemeUri = $scheme['uri'];
        $skosXl = (bool) ($scheme['skos_xl'] ?? false);
        $baseUri = (string) ($scheme['base_uri'] ?? '');

        $rdfType = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>';
        $skos = 'http://www.w3.org/2004/02/skos/core#';
        $skosxl = 'http://www.w3.org/2008/05/skos-xl#';
        $dct = 'http://purl.org/dc/terms/';

        $nt = '';
        $nt .= '<'.$schemeUri.'> '.$rdfType.' <'.$skos.'ConceptScheme> .'."\n";
        $nt .= '<'.$schemeUri.'> <'.$dct.'title> '.$this->ntString($scheme['title']).' .'."\n";

        foreach ($concepts as $c) {
            $nt .= '<'.$c['uri'].'> '.$rdfType.' <'.$skos.'Concept> .'."\n";
            $nt .= '<'.$c['uri'].'> <'.$skos.'prefLabel> '.$this->ntLangString($c['prefLabel'], $culture).' .'."\n";
            $nt .= '<'.$c['uri'].'> <'.$skos.'inScheme> <'.$schemeUri.'> .'."\n";
            if ($c['broader']) {
                $nt .= '<'.$c['uri'].'> <'.$skos.'broader> <'.$c['broader'].'> .'."\n";
            } else {
                $nt .= '<'.$c['uri'].'> <'.$skos.'topConceptOf> <'.$schemeUri.'> .'."\n";
            }
            if ($c['notation']) {
                $nt .= '<'.$c['uri'].'> <'.$skos.'notation> '.$this->ntString($c['notation']).' .'."\n";
            }
            // #661 Phase 1 additions
            foreach (($c['altLabels'] ?? []) as $alt) {
                $nt .= '<'.$c['uri'].'> <'.$skos.'altLabel> '.$this->ntLangString($alt['name'], $alt['lang']).' .'."\n";
            }
            foreach (($c['hiddenLabels'] ?? []) as $hid) {
                $nt .= '<'.$c['uri'].'> <'.$skos.'hiddenLabel> '.$this->ntLangString($hid['name'], $hid['lang']).' .'."\n";
            }
            foreach (($c['scopeNotes'] ?? []) as $sn) {
                $nt .= '<'.$c['uri'].'> <'.$skos.'scopeNote> '.$this->ntLangString($sn['text'], $sn['lang']).' .'."\n";
            }
            foreach (($c['historyNotes'] ?? []) as $hn) {
                $nt .= '<'.$c['uri'].'> <'.$skos.'historyNote> '.$this->ntLangString($hn['text'], $hn['lang']).' .'."\n";
            }
            // #661 Phase 3 - cross-vocab mapping links
            foreach (($c['crossMatches'] ?? []) as $cm) {
                $pred = $this->mapPredicate($cm['match_type']);
                $nt .= '<'.$c['uri'].'> <'.$skos.$pred.'> <'.$cm['target_uri'].'> .'."\n";
            }
            // #661 Phase 3 - SKOS-XL references + label resources
            if ($skosXl) {
                $now = date('c');
                $emitXl = function (string $uri, string $literal, string $lang, string $predicate) use (&$nt, $now, $c, $skosxl, $dct, $rdfType) {
                    $nt .= '<'.$c['uri'].'> <'.$skosxl.$predicate.'> <'.$uri.'> .'."\n";
                    $nt .= '<'.$uri.'> '.$rdfType.' <'.$skosxl.'Label> .'."\n";
                    $nt .= '<'.$uri.'> <'.$skosxl.'literalForm> '.$this->ntLangString($literal, $lang).' .'."\n";
                    $nt .= '<'.$uri.'> <'.$dct.'created> "'.$now.'"^^<http://www.w3.org/2001/XMLSchema#dateTime> .'."\n";
                    $nt .= '<'.$uri.'> <'.$dct.'creator> '.$this->ntString('heratio').' .'."\n";
                };
                $emitXl(
                    $this->xlLabelUri($baseUri, $c, $culture, 'pref', $c['prefLabel']),
                    $c['prefLabel'],
                    $culture,
                    'prefLabel'
                );
                foreach (($c['altLabels'] ?? []) as $alt) {
                    $emitXl(
                        $this->xlLabelUri($baseUri, $c, $alt['lang'], 'alt', $alt['name']),
                        $alt['name'],
                        $alt['lang'],
                        'altLabel'
                    );
                }
                foreach (($c['hiddenLabels'] ?? []) as $hid) {
                    $emitXl(
                        $this->xlLabelUri($baseUri, $c, $hid['lang'], 'hidden', $hid['name']),
                        $hid['name'],
                        $hid['lang'],
                        'hiddenLabel'
                    );
                }
            }
        }

        return $nt;
    }

    /**
     * Serialise concept scheme + concepts as JSON-LD (.jsonld).
     * Spec: https://www.w3.org/TR/json-ld11/
     * Compact form with @context + @graph array of nodes.
     */
    private function serialiseSkosJsonLd(array $scheme, array $concepts): string
    {
        $culture = $scheme['culture'];
        $skosXl = (bool) ($scheme['skos_xl'] ?? false);
        $baseUri = (string) ($scheme['base_uri'] ?? '');

        $graph = [];
        $graph[] = [
            '@id' => $scheme['uri'],
            '@type' => 'skos:ConceptScheme',
            'dct:title' => $scheme['title'],
        ];

        $xlNodes = [];

        foreach ($concepts as $c) {
            $node = [
                '@id' => $c['uri'],
                '@type' => 'skos:Concept',
                'skos:prefLabel' => ['@value' => $c['prefLabel'], '@language' => $culture],
                'skos:inScheme' => ['@id' => $scheme['uri']],
            ];
            if ($c['broader']) {
                $node['skos:broader'] = ['@id' => $c['broader']];
            } else {
                $node['skos:topConceptOf'] = ['@id' => $scheme['uri']];
            }
            if ($c['notation']) {
                $node['skos:notation'] = $c['notation'];
            }
            // #661 Phase 1 additions - emit as arrays when >=1 entry
            $mapTo = function (array $entries, string $textKey): array {
                $out = [];
                foreach ($entries as $e) {
                    $out[] = ['@value' => $e[$textKey], '@language' => $e['lang']];
                }

                return $out;
            };
            if (! empty($c['altLabels'])) {
                $node['skos:altLabel'] = $mapTo($c['altLabels'], 'name');
            }
            if (! empty($c['hiddenLabels'])) {
                $node['skos:hiddenLabel'] = $mapTo($c['hiddenLabels'], 'name');
            }
            if (! empty($c['scopeNotes'])) {
                $node['skos:scopeNote'] = $mapTo($c['scopeNotes'], 'text');
            }
            if (! empty($c['historyNotes'])) {
                $node['skos:historyNote'] = $mapTo($c['historyNotes'], 'text');
            }
            // #661 Phase 3 - cross-vocab mapping links (grouped by predicate)
            $byPred = [];
            foreach (($c['crossMatches'] ?? []) as $cm) {
                $pred = $this->mapPredicate($cm['match_type']);
                $byPred[$pred][] = ['@id' => $cm['target_uri']];
            }
            foreach ($byPred as $pred => $ids) {
                $node['skos:'.$pred] = $ids;
            }
            // #661 Phase 3 - SKOS-XL emission alongside plain literals
            if ($skosXl) {
                $now = date('c');
                $registerXl = function (string $uri, string $literal, string $lang) use (&$xlNodes, $now) {
                    $xlNodes[] = [
                        '@id' => $uri,
                        '@type' => 'skosxl:Label',
                        'skosxl:literalForm' => ['@value' => $literal, '@language' => $lang],
                        'dct:created' => ['@value' => $now, '@type' => 'xsd:dateTime'],
                        'dct:creator' => 'heratio',
                    ];
                };
                $xlPreUri = $this->xlLabelUri($baseUri, $c, $culture, 'pref', $c['prefLabel']);
                $node['skosxl:prefLabel'] = ['@id' => $xlPreUri];
                $registerXl($xlPreUri, $c['prefLabel'], $culture);

                $xlAlts = [];
                foreach (($c['altLabels'] ?? []) as $alt) {
                    $u = $this->xlLabelUri($baseUri, $c, $alt['lang'], 'alt', $alt['name']);
                    $xlAlts[] = ['@id' => $u];
                    $registerXl($u, $alt['name'], $alt['lang']);
                }
                if (! empty($xlAlts)) {
                    $node['skosxl:altLabel'] = $xlAlts;
                }

                $xlHidden = [];
                foreach (($c['hiddenLabels'] ?? []) as $hid) {
                    $u = $this->xlLabelUri($baseUri, $c, $hid['lang'], 'hidden', $hid['name']);
                    $xlHidden[] = ['@id' => $u];
                    $registerXl($u, $hid['name'], $hid['lang']);
                }
                if (! empty($xlHidden)) {
                    $node['skosxl:hiddenLabel'] = $xlHidden;
                }
            }
            $graph[] = $node;
        }

        // Append the standalone skosxl:Label resources so they have full identity.
        foreach ($xlNodes as $xn) {
            $graph[] = $xn;
        }

        $context = [
            'skos' => 'http://www.w3.org/2004/02/skos/core#',
            'dct' => 'http://purl.org/dc/terms/',
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        ];
        if ($skosXl) {
            $context['skosxl'] = 'http://www.w3.org/2008/05/skos-xl#';
            $context['xsd'] = 'http://www.w3.org/2001/XMLSchema#';
        }

        $doc = [
            '@context' => $context,
            '@graph' => $graph,
        ];

        return json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * Escape a string for a Turtle/N-Triples literal.
     * Per spec: \\, \", \n, \r, \t are escaped.
     */
    private function escapeRdfLiteral(string $s): string
    {
        return strtr($s, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]);
    }

    private function ttlString(string $s): string
    {
        return '"'.$this->escapeRdfLiteral($s).'"';
    }

    private function ttlLangString(string $s, string $lang): string
    {
        return '"'.$this->escapeRdfLiteral($s).'"@'.$lang;
    }

    private function ntString(string $s): string
    {
        return '"'.$this->escapeRdfLiteral($s).'"';
    }

    private function ntLangString(string $s, string $lang): string
    {
        return '"'.$this->escapeRdfLiteral($s).'"@'.$lang;
    }

    /**
     * Normalise a cross-match type string to a bare SKOS predicate
     * (e.g. "exactMatch", "closeMatch"). Falls back to closeMatch for
     * anything outside the SKOS-defined set.
     */
    private function mapPredicate(string $matchType): string
    {
        $allowed = ['exactMatch', 'closeMatch', 'broadMatch', 'narrowMatch', 'relatedMatch'];
        if (in_array($matchType, $allowed, true)) {
            return $matchType;
        }

        return 'closeMatch';
    }

    /**
     * Build a stable URI for a skosxl:Label resource.
     * Shape: <base>/term/{id}/label/{lang}-{type}/{slug-of-literal}
     */
    private function xlLabelUri(string $baseUri, array $concept, string $lang, string $type, string $literal): string
    {
        $slug = Str::slug($literal, '-');
        if ($slug === '') {
            $slug = 'label';
        }
        $base = rtrim($baseUri, '/');

        return $base.'/'.$concept['id'].'/label/'.$lang.'-'.$type.'/'.$slug;
    }

    /**
     * Import SKOS RDF/XML into a taxonomy.
     */
    public function importSkos(Request $request)
    {
        $culture = app()->getLocale();

        if ($request->isMethod('post')) {
            $taxonomyId = (int) $request->input('taxonomy_id');
            if (! $taxonomyId) {
                return back()->with('error', 'Please select a taxonomy.');
            }

            $xmlContent = null;

            if ($request->hasFile('skos_file')) {
                $xmlContent = file_get_contents($request->file('skos_file')->getPathname());
            } elseif ($request->filled('skos_url')) {
                $url = $request->input('skos_url');
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    return back()->with('error', 'Invalid URL.');
                }
                // SECURITY: block SSRF - reject non-http(s) schemes and private/reserved hosts.
                if (! \AhgCore\Support\UrlGuard::isAllowed($url)) {
                    return back()->with('error', 'URL not allowed.');
                }
                // #1395(C) - never follow redirects: UrlGuard already vetted $url, but a
                // 30x to an internal host would otherwise re-open the SSRF hole.
                $ctx = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 0, 'max_redirects' => 0, 'header' => 'Accept: application/rdf+xml,text/xml,*/*']]);
                $xmlContent = @file_get_contents($url, false, $ctx);
                if ($xmlContent === false) {
                    return back()->with('error', 'Failed to fetch SKOS file from URL: '.$url);
                }
            } else {
                return back()->with('error', 'Please upload a file or provide a URL.');
            }

            $xml = simplexml_load_string($xmlContent);
            if (! $xml) {
                return back()->with('error', 'Invalid XML content.');
            }

            $xml->registerXPathNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
            $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

            $concepts = $xml->xpath('//skos:Concept');
            $imported = 0;

            foreach ($concepts as $c) {
                $label = (string) $c->children('skos', true)->prefLabel;
                if (! $label) {
                    continue;
                }

                // Check if exists
                $existing = DB::table('term')
                    ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                    ->where('term.taxonomy_id', $taxonomyId)
                    ->where('term_i18n.culture', $culture)
                    ->where('term_i18n.name', $label)
                    ->value('term.id');

                if ($existing) {
                    continue;
                }

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

                $slug = \Illuminate\Support\Str::slug($label).'-'.$objectId;
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

        // #1388 - persist the community protocol on create too (mirror update()).
        $newTerm = $this->termService->getBySlug($slug, $culture);
        if ($newTerm) {
            \AhgCore\Services\TermProtocolService::set(
                (int) $newTerm->id,
                $request->input('protocol_label_family'),
                $request->input('protocol_label_code'),
                (string) $request->input('protocol_access_condition', 'open'),
                $request->filled('protocol_owner_actor_id') ? (int) $request->input('protocol_owner_actor_id') : null,
                $request->input('protocol_region_module'),
                (int) auth()->id() ?: null
            );
        }

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

        if (! $term) {
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

        // Narrower terms (children) - for display only, not pre-populated in the "add new" field
        $narrowerTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.parent_id', $term->id)
            ->where('term_i18n.culture', $culture)
            ->pluck('term_i18n.name')->implode(', ');

        // #661 Phase 3 - cross-vocab matches for the panel on the edit form.
        $crossMatches = Schema::hasTable('ahg_term_cross_match')
            ? $this->crossMatchService->forTerm((int) $term->id)
            : [];

        return view('ahg-term-taxonomy::edit', [
            'term' => $term,
            'termProtocol' => \AhgCore\Services\TermProtocolService::protocolsForTerm((int) $term->id)->first(),
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
            'crossMatches' => $crossMatches,
            'crossMatchTypes' => CrossMatchService::MATCH_TYPES,
            'crossMatchSources' => CrossMatchService::SOURCES,
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

        if (! $term) {
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

        // #1388 - community protocol (TK/BC label + access condition + owner).
        \AhgCore\Services\TermProtocolService::set(
            (int) $term->id,
            $request->input('protocol_label_family'),
            $request->input('protocol_label_code'),
            (string) $request->input('protocol_access_condition', 'open'),
            $request->filled('protocol_owner_actor_id') ? (int) $request->input('protocol_owner_actor_id') : null,
            $request->input('protocol_region_module'),
            (int) auth()->id() ?: null
        );

        // #661 Phase 3 - persist the cross-vocab matches panel. Empty rows
        // (no URI) drop on the floor; the service does per-row validation.
        if (Schema::hasTable('ahg_term_cross_match')) {
            $rows = (array) $request->input('crossMatches', []);
            $this->crossMatchService->replaceAll((int) $term->id, $rows);
        }

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

        if (! $term) {
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

        if (! $term) {
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

        if (! $taxonomyId) {
            return response()->json([]);
        }

        $results = DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.name', 'LIKE', '%'.$query.'%')
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->limit($limit)
            ->get();

        return response()->json($results);
    }

    /**
     * Autocomplete over the taxonomies themselves (used by the ACL editor's
     * Taxonomy tab to add per-taxonomy permission scopes).
     * Shape matches actor/repository/IO autocomplete: [{id, name, slug}].
     */
    public function taxonomyAutocomplete(Request $request)
    {
        $term = $request->get('term', $request->get('query', ''));
        $culture = app()->getLocale();
        $limit = (int) $request->get('limit', 20);

        $rows = DB::table('taxonomy as t')
            ->leftJoin('taxonomy_i18n as ti', function ($j) use ($culture) {
                $j->on('ti.id', '=', 't.id')->where('ti.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 't.id')
            ->when($term !== '', function ($q) use ($term) {
                $q->where('ti.name', 'LIKE', '%'.$term.'%');
            })
            ->whereNotNull('ti.name')
            ->select('t.id', 'ti.name', 's.slug')
            ->orderBy('ti.name')
            ->limit($limit)
            ->get();

        return response()->json($rows);
    }

    /**
     * Related authorities sidebar for a term.
     *
     * Lists every actor and repository linked to the term via the generic
     * relation table (relation.subject_id|object_id == term.id) plus the
     * indexed object_term_relation table used by AtoM for facet/sidebar work.
     *
     * Migrated from PSIS TermRelatedAuthoritiesAction (#743).
     */
    public function relatedAuthorities(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $term = $this->termService->getBySlug($slug, $culture);
        if (! $term) {
            abort(404);
        }

        if (! \AhgCore\Services\AclService::hasPermission(\Illuminate\Support\Facades\Auth::id(), 'read', (int) $term->id)) {
            abort(403, 'You do not have permission to view this term.');
        }

        $onlyDirect = $request->has('onlyDirect');
        $page = max(1, (int) $request->get('page', 1));
        $limit = (int) $request->get('limit', SettingHelper::hitsPerPage());
        $sort = $request->get('sort', 'lastUpdated');

        // Walk narrower terms unless the curator restricts to direct only.
        $termIds = [$term->id];
        if (! $onlyDirect) {
            $narrowerIds = DB::table('term')->where('parent_id', $term->id)->pluck('id')->toArray();
            if (! empty($narrowerIds)) {
                $termIds = array_merge($termIds, $narrowerIds);
            }
        }

        // Actors linked via object_term_relation (the index table AtoM
        // populates when an actor is tagged with subject/place/occupation/etc.)
        $actorQuery = DB::table('object_term_relation')
            ->join('actor', 'object_term_relation.object_id', '=', 'actor.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($j) use ($culture) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
            ->whereIn('object_term_relation.term_id', $termIds)
            ->where('object.class_name', 'QubitActor');

        // Repositories: stored as actors with their own description_identifier
        // namespace but indexed the same way via object_term_relation.
        $repositoryQuery = DB::table('object_term_relation')
            ->join('actor', 'object_term_relation.object_id', '=', 'actor.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($j) use ($culture) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
            ->whereIn('object_term_relation.term_id', $termIds)
            ->where('object.class_name', 'QubitRepository');

        $totalActors = (clone $actorQuery)->distinct()->count('actor.id');
        $totalRepositories = (clone $repositoryQuery)->distinct()->count('actor.id');
        $total = $totalActors + $totalRepositories;

        // Order: alphabetic by name, lastUpdated by object.updated_at, identifier by description_identifier.
        $orderMap = [
            'alphabetic' => ['actor_i18n.authorized_form_of_name', 'asc'],
            'identifier' => ['actor.description_identifier', 'asc'],
            'lastUpdated' => ['object.updated_at', 'desc'],
        ];
        [$orderCol, $orderDir] = $orderMap[$sort] ?? $orderMap['lastUpdated'];

        $actors = $actorQuery
            ->select(
                'actor.id',
                'actor.description_identifier',
                'actor.entity_type_id',
                'actor_i18n.authorized_form_of_name',
                'actor_i18n.dates_of_existence',
                'slug.slug',
                'object.updated_at',
            )
            ->distinct()
            ->orderBy($orderCol, $orderDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $repositories = $repositoryQuery
            ->select(
                'actor.id',
                'actor.description_identifier',
                'actor_i18n.authorized_form_of_name',
                'actor_i18n.dates_of_existence',
                'slug.slug',
                'object.updated_at',
            )
            ->distinct()
            ->orderBy($orderCol, $orderDir)
            ->get();

        $taxonomyName = $this->termService->getTaxonomyName($term->taxonomy_id, $culture);

        return view('ahg-term-taxonomy::related-authorities', [
            'term' => $term,
            'taxonomyName' => $taxonomyName,
            'actors' => $actors,
            'repositories' => $repositories,
            'total' => $total,
            'totalActors' => $totalActors,
            'totalRepositories' => $totalRepositories,
            'page' => $page,
            'limit' => $limit,
            'lastPage' => max(1, (int) ceil($totalActors / $limit)),
            'sort' => $sort,
            'onlyDirect' => $onlyDirect,
        ]);
    }

    /**
     * Tree-view JSON endpoint for the per-taxonomy expand/collapse tree.
     *
     * Shape: [{id, text, slug, children: bool}]. The blade view consumes
     * the matching `/term/taxonomy/{id}/tree` route for the full HTML page;
     * AJAX clients hit `/term/taxonomy/{id}/tree.json` for lazy loading.
     *
     * Migrated from PSIS TermTreeViewAction (#743).
     */
    public function treeView(Request $request, int $taxonomyId)
    {
        $culture = app()->getLocale();
        $parentId = $request->get('parent');

        $query = DB::table('term')
            ->leftJoin('term_i18n', function ($j) use ($culture) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $taxonomyId);

        if ($parentId) {
            // Children of an explicit parent term.
            $query->where('term.parent_id', $parentId);
        } else {
            // Top-level terms (parent is the taxonomy root, not another term
            // in the same taxonomy). Use the same NOT-EXISTS pattern that
            // browse() already uses so the two stay in sync.
            $query->whereNotExists(function ($q) use ($taxonomyId) {
                $q->select(DB::raw(1))
                    ->from('term as parent')
                    ->whereColumn('parent.id', 'term.parent_id')
                    ->where('parent.taxonomy_id', $taxonomyId);
            });
        }

        $rows = $query
            ->select(
                'term.id',
                'term.parent_id',
                'term_i18n.name',
                'slug.slug',
                DB::raw('(SELECT COUNT(*) FROM term tc WHERE tc.parent_id = term.id) as child_count'),
            )
            ->orderBy('term_i18n.name')
            ->get();

        $nodes = $rows->map(fn ($t) => [
            'id' => (int) $t->id,
            'parent_id' => (int) $t->parent_id,
            'text' => (string) ($t->name ?? ''),
            // Fall back to the id when a term has no slug row, so the tree links never
            // render an empty slug (which throws UrlGenerationException on term.show).
            'slug' => (string) ($t->slug ?? '') ?: (string) $t->id,
            'children' => ((int) $t->child_count) > 0,
        ])->values();

        if ($request->wantsJson() || $request->get('format') === 'json') {
            return response()->json([
                'taxonomy_id' => $taxonomyId,
                'parent_id' => $parentId ? (int) $parentId : null,
                'nodes' => $nodes,
            ]);
        }

        $taxonomyName = $this->termService->getTaxonomyName($taxonomyId, $culture);

        return view('ahg-term-taxonomy::tree-view', [
            'taxonomyId' => $taxonomyId,
            'taxonomyName' => $taxonomyName,
            'nodes' => $nodes,
        ]);
    }
}
