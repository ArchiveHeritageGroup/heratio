<?php

namespace AhgInformationObjectManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\SettingHelper;
use AhgInformationObjectManage\Services\InformationObjectBrowseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InformationObjectController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new InformationObjectBrowseService($culture);

        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ];

        // Apply filters from request
        $repositoryId = $request->get('repository');
        $levelsId = $request->get('levels');
        $mediaTypeId = $request->get('mediatypes');

        if ($repositoryId) {
            $params['filters']['repository_id'] = $repositoryId;
        }
        if ($levelsId) {
            $params['filters']['level_of_description_id'] = $levelsId;
        }
        if ($mediaTypeId) {
            $params['filters']['media_type_id'] = $mediaTypeId;
        }

        $result = $service->browse($params);

        $pager = new SimplePager($result);

        // Get list of repositories for filter dropdown
        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // ── Facet aggregation queries ──

        // Level of description facet
        $levelFacets = DB::table('information_object')
            ->join('term_i18n', 'information_object.level_of_description_id', '=', 'term_i18n.id')
            ->where('term_i18n.culture', $culture)
            ->where('information_object.id', '!=', 1)
            ->whereNotNull('information_object.level_of_description_id')
            ->select(
                'information_object.level_of_description_id as id',
                'term_i18n.name as label',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('information_object.level_of_description_id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // Repository facet
        $repoFacets = DB::table('information_object')
            ->join('repository', 'information_object.repository_id', '=', 'repository.id')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->where('information_object.id', '!=', 1)
            ->whereNotNull('information_object.repository_id')
            ->select(
                'information_object.repository_id as id',
                'actor_i18n.authorized_form_of_name as label',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('information_object.repository_id', 'actor_i18n.authorized_form_of_name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // Media type facet
        $mediaFacets = DB::table('digital_object')
            ->join('term_i18n', 'digital_object.media_type_id', '=', 'term_i18n.id')
            ->where('term_i18n.culture', $culture)
            ->whereNotNull('digital_object.media_type_id')
            ->select(
                'digital_object.media_type_id as id',
                'term_i18n.name as label',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('digital_object.media_type_id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $facets = [
            'levels' => [
                'label' => 'Level of description',
                'terms' => $levelFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'repository' => [
                'label' => 'Repository',
                'terms' => $repoFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'mediatypes' => [
                'label' => 'Media type',
                'terms' => $mediaFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
        ];

        // ── Filter tags (active filter pills) ──
        $filterTags = [];

        if ($levelsId) {
            $levelName = DB::table('term_i18n')->where('id', $levelsId)->where('culture', $culture)->value('name');
            if ($levelName) {
                $filterTags[] = [
                    'label' => 'Level: ' . $levelName,
                    'removeUrl' => route('informationobject.browse', $request->except(['levels', 'page'])),
                ];
            }
        }

        if ($repositoryId) {
            $repoName = DB::table('actor_i18n')->where('id', $repositoryId)->where('culture', $culture)->value('authorized_form_of_name');
            if ($repoName) {
                $filterTags[] = [
                    'label' => 'Repository: ' . $repoName,
                    'removeUrl' => route('informationobject.browse', $request->except(['repository', 'page'])),
                ];
            }
        }

        if ($mediaTypeId) {
            $mediaName = DB::table('term_i18n')->where('id', $mediaTypeId)->where('culture', $culture)->value('name');
            if ($mediaName) {
                $filterTags[] = [
                    'label' => 'Media type: ' . $mediaName,
                    'removeUrl' => route('informationobject.browse', $request->except(['mediatypes', 'page'])),
                ];
            }
        }

        return view('ahg-io-manage::browse', [
            'pager' => $pager,
            'levelNames' => $result['levelNames'] ?? [],
            'repositoryNames' => $result['repositoryNames'] ?? [],
            'repositories' => $repositories,
            'selectedRepository' => $repositoryId,
            'facets' => $facets,
            'filterTags' => $filterTags,
            'sortOptions' => [
                'lastUpdated' => 'Date modified',
                'alphabetic' => 'Title',
                'relevance' => 'Relevance',
                'identifier' => 'Identifier',
                'referenceCode' => 'Reference code',
                'startDate' => 'Start date',
                'endDate' => 'End date',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $culture = app()->getLocale();

        // Main information object
        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_standard',
                'information_object.display_standard_id',
                'information_object.collection_type_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.edition',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'information_object_i18n.institution_responsible_identifier',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$io) {
            // Slug may belong to a different entity — check object table and redirect
            $slugRow = DB::table('slug')->where('slug', $slug)->first();
            if ($slugRow) {
                $className = DB::table('object')->where('id', $slugRow->object_id)->value('class_name');
                $redirectMap = [
                    'QubitTerm' => '/term/' . $slug,
                    'QubitActor' => '/actor/' . $slug,
                    'QubitRepository' => '/repository/' . $slug,
                    'QubitDonor' => '/donor/' . $slug,
                    'QubitAccession' => '/accession/' . $slug,
                    'QubitRightsHolder' => '/rightsholder/' . $slug,
                    'QubitFunctionObject' => '/function/' . $slug,
                    'QubitPhysicalObject' => '/physicalobject/' . $slug,
                ];
                if (isset($redirectMap[$className])) {
                    return redirect($redirectMap[$className]);
                }
            }
            abort(404);
        }

        // Level of description name
        $levelName = null;
        if ($io->level_of_description_id) {
            $levelName = DB::table('term_i18n')
                ->where('id', $io->level_of_description_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Repository
        $repository = null;
        if ($io->repository_id) {
            $repository = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->where('repository.id', $io->repository_id)
                ->where('actor_i18n.culture', $culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
                ->first();
        }

        // Events (dates)
        $events = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event_i18n.culture', $culture)
            ->select(
                'event.id',
                'event.type_id',
                'event.actor_id',
                'event.start_date',
                'event.end_date',
                'event_i18n.date as date_display',
                'event_i18n.name as event_name'
            )
            ->get();

        // Resolve event type names
        $eventTypeIds = $events->pluck('type_id')->filter()->unique()->values()->toArray();
        $eventTypeNames = [];
        if (!empty($eventTypeIds)) {
            $eventTypeNames = DB::table('term_i18n')
                ->whereIn('id', $eventTypeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Creators (events where type_id = 111 = creation) — include history and entity type
        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('slug', 'event.actor_id', '=', 'slug.object_id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111) // Creation event
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select(
                'event.actor_id as id',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.history',
                'actor_i18n.dates_of_existence',
                'actor.entity_type_id',
                'slug.slug'
            )
            ->distinct()
            ->get();

        // Digital objects (organized by usage type: master, reference, thumbnail)
        $digitalObjects = DigitalObjectService::getForObject($io->id);

        // Notes
        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')
            ->get();

        // Resolve note type names
        $noteTypeIds = $notes->pluck('type_id')->filter()->unique()->values()->toArray();
        $noteTypeNames = [];
        if (!empty($noteTypeIds)) {
            $noteTypeNames = DB::table('term_i18n')
                ->whereIn('id', $noteTypeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Children (child information objects)
        $children = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.parent_id', $io->id)
            ->where('information_object_i18n.culture', $culture)
            ->orderBy('information_object.lft')
            ->select(
                'information_object.id',
                'information_object.level_of_description_id',
                'information_object_i18n.title',
                'slug.slug'
            )
            ->get();

        // Resolve child level names
        $childLevelIds = $children->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        $childLevelNames = [];
        if (!empty($childLevelIds)) {
            $childLevelNames = DB::table('term_i18n')
                ->whereIn('id', $childLevelIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Parent breadcrumb chain (walk up the tree)
        $breadcrumbs = [];
        $parentId = $io->parent_id;
        while ($parentId && $parentId != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $parentId)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')
                ->first();

            if (!$parent) {
                break;
            }

            array_unshift($breadcrumbs, $parent);
            $parentId = $parent->parent_id;
        }

        // Subject access points (taxonomy_id = 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Place access points (taxonomy_id = 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Name access points (via relation table — actors linked as name access points)
        $nameAccessPoints = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 161) // Name access point relation
            ->where('actor_i18n.culture', $culture)
            ->select('actor_i18n.authorized_form_of_name as name')
            ->get();

        // Genre access points (taxonomy_id = 78)
        $genres = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 78)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Language of material
        $languages = DB::table('information_object')
            ->join('object_term_relation', 'information_object.id', '=', 'object_term_relation.object_id')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('information_object.id', $io->id)
            ->where('term.taxonomy_id', 7) // Language taxonomy
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Publication status (from status table — type_id=158 is publication status)
        $publicationStatus = null;
        $publicationStatusId = null;
        $statusRow = DB::table('status')
            ->where('object_id', $io->id)
            ->where('type_id', 158)
            ->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
            $publicationStatus = DB::table('term_i18n')
                ->where('id', $statusRow->status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Function relations (function_object table may not exist in all installs)
        $functionRelations = collect();
        try {
            $functionRelations = DB::table('relation')
                ->join('function_object', 'relation.subject_id', '=', 'function_object.id')
                ->join('function_i18n', 'function_object.id', '=', 'function_i18n.id')
                ->join('slug', 'function_object.id', '=', 'slug.object_id')
                ->where('relation.object_id', $io->id)
                ->where('function_i18n.culture', $culture)
                ->select('function_object.id', 'function_i18n.authorized_form_of_name as name', 'slug.slug')
                ->get();
        } catch (\Exception $e) {
            // function_object table may not exist
        }

        // Alternative identifiers (from property table)
        $alternativeIdentifiers = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'alternativeIdentifiers')
            ->where('property_i18n.culture', $culture)
            ->select('property_i18n.value')
            ->get();

        // Physical storage (relation type 151 = HAS_PHYSICAL_OBJECT)
        $physicalObjects = DB::table('relation')
            ->join('physical_object', 'relation.object_id', '=', 'physical_object.id')
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 151)
            ->where('physical_object_i18n.culture', $culture)
            ->select('physical_object.id', 'physical_object_i18n.name', 'physical_object_i18n.location', 'physical_object.type_id')
            ->get();

        // Resolve physical object type names
        $physicalObjectTypeIds = $physicalObjects->pluck('type_id')->filter()->unique()->values()->toArray();
        $physicalObjectTypeNames = [];
        if (!empty($physicalObjectTypeIds)) {
            $physicalObjectTypeNames = DB::table('term_i18n')
                ->whereIn('id', $physicalObjectTypeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Rights (authenticated users only, linked via relation table)
        $rights = collect();
        $extendedRights = collect();
        $extendedRightsTkLabels = [];
        if (auth()->check()) {
            try {
                $rights = DB::table('relation')
                    ->join('rights', 'relation.object_id', '=', 'rights.id')
                    ->join('rights_i18n', 'rights.id', '=', 'rights_i18n.id')
                    ->where('relation.subject_id', $io->id)
                    ->where('relation.type_id', 168) // RIGHT relation type
                    ->where('rights_i18n.culture', $culture)
                    ->select('rights.*', 'rights_i18n.rights_note')
                    ->get();
            } catch (\Exception $e) {
                // rights table structure may vary
            }

            // Extended rights (from extended_rights + extended_rights_i18n tables)
            try {
                $erService = new \AhgInformationObjectManage\Services\ExtendedRightsService($culture);
                $extendedRights = $erService->getExtendedRights($io->id);
                foreach ($extendedRights as $er) {
                    $extendedRightsTkLabels[$er->id] = $erService->getTkLabelsForRights($er->id);
                }
            } catch (\Exception $e) {
                // extended_rights tables may not exist in all installs
            }
        }

        // Accessions (via relation table)
        $accessions = collect();
        try {
            $accessions = DB::table('relation')
                ->join('accession', 'relation.object_id', '=', 'accession.id')
                ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
                ->join('slug', 'accession.id', '=', 'slug.object_id')
                ->where('relation.subject_id', $io->id)
                ->where('accession_i18n.culture', $culture)
                ->select('accession.id', 'accession.identifier', 'accession_i18n.title', 'slug.slug')
                ->get();
        } catch (\Exception $e) {
            // accession table may not exist
        }

        // Description status name
        $descriptionStatusName = null;
        if ($io->description_status_id) {
            $descriptionStatusName = DB::table('term_i18n')
                ->where('id', $io->description_status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Description detail name
        $descriptionDetailName = null;
        if ($io->description_detail_id) {
            $descriptionDetailName = DB::table('term_i18n')
                ->where('id', $io->description_detail_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Languages of description (from property table — serialized PHP arrays)
        $languagesOfDescriptionRaw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'languageOfDescription')
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        $languagesOfDescription = collect();
        if ($languagesOfDescriptionRaw) {
            $decoded = @unserialize($languagesOfDescriptionRaw);
            if (is_array($decoded) && !empty($decoded)) {
                $languagesOfDescription = collect($decoded);
            }
        }

        // Scripts of description (from property table — serialized PHP arrays)
        $scriptsOfDescriptionRaw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'scriptOfDescription')
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        $scriptsOfDescription = collect();
        if ($scriptsOfDescriptionRaw) {
            $decoded = @unserialize($scriptsOfDescriptionRaw);
            if (is_array($decoded) && !empty($decoded)) {
                $scriptsOfDescription = collect($decoded);
            }
        }

        // Language of material (from property table — serialized PHP arrays of ISO codes)
        $materialLanguagesRaw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'language')
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        $materialLanguages = collect();
        if ($materialLanguagesRaw) {
            $decoded = @unserialize($materialLanguagesRaw);
            if (is_array($decoded) && !empty($decoded)) {
                $materialLanguages = collect($decoded);
            }
        }

        // Script of material (from property table)
        $materialScriptsRaw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'script')
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        $materialScripts = collect();
        if ($materialScriptsRaw) {
            $decoded = @unserialize($materialScriptsRaw);
            if (is_array($decoded) && !empty($decoded)) {
                $materialScripts = collect($decoded);
            }
        }

        // Finding aid link — check if a finding aid file exists for the collection root
        $findingAid = null;
        $collectionRootId = $io->id;
        $collectionRootSlug = $io->slug;
        if ($io->parent_id && $io->parent_id != 1) {
            // Walk up to the top-level description (collection root)
            $rootId = $io->parent_id;
            while ($rootId && $rootId != 1) {
                $rootParent = DB::table('information_object')
                    ->where('id', $rootId)
                    ->select('id', 'parent_id')
                    ->first();
                if (!$rootParent || $rootParent->parent_id == 1) {
                    $collectionRootId = $rootId;
                    break;
                }
                $rootId = $rootParent->parent_id;
            }
            $collectionRootSlug = DB::table('slug')
                ->where('object_id', $collectionRootId)
                ->value('slug') ?? $io->slug;
        }
        // Check findingAidStatus property for the collection root
        $findingAidStatusValue = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $collectionRootId)
            ->where('property.name', 'findingAidStatus')
            ->value('property_i18n.value');
        if ($findingAidStatusValue) {
            $faStatus = (int) $findingAidStatusValue;
            $findingAid = (object) [
                'status' => $faStatus,
                'label' => $faStatus === 2 ? 'Uploaded finding aid' : ($faStatus === 1 ? 'Generated finding aid' : 'Finding aid'),
                'slug' => $collectionRootSlug,
            ];
        }

        // Related material descriptions (relation type_id = 176)
        $relatedMaterialDescriptions = collect();
        // Relations where this IO is the subject
        $relatedBySubject = DB::table('relation')
            ->join('information_object', 'relation.object_id', '=', 'information_object.id')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 176)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->get();
        // Relations where this IO is the object
        $relatedByObject = DB::table('relation')
            ->join('information_object', 'relation.subject_id', '=', 'information_object.id')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.object_id', $io->id)
            ->where('relation.type_id', 176)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->get();
        $relatedMaterialDescriptions = $relatedBySubject->merge($relatedByObject)->unique('id');

        // Museum metadata (CCO fields) — present when this IO has a museum_metadata row
        $museumMetadata = [];
        try {
            $mmRow = DB::table('museum_metadata')
                ->where('object_id', $io->id)
                ->first();
            if ($mmRow) {
                $museumMetadata = (array) $mmRow;
            }
        } catch (\Exception $e) {
            // museum_metadata table may not exist in all installs
        }

        // Previous sibling
        $prevSibling = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.parent_id', $io->parent_id)
            ->where('information_object.lft', '<', $io->lft)
            ->where('information_object_i18n.culture', $culture)
            ->orderBy('information_object.lft', 'desc')
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        // Next sibling
        $nextSibling = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.parent_id', $io->parent_id)
            ->where('information_object.lft', '>', $io->lft)
            ->where('information_object_i18n.culture', $culture)
            ->orderBy('information_object.lft', 'asc')
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        // Display standard name (for Administration area)
        $displayStandardName = null;
        if ($io->display_standard_id) {
            $displayStandardName = DB::table('term_i18n')
                ->where('id', $io->display_standard_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Source language name (for Administration area)
        $sourceLanguageName = null;
        if ($io->source_culture) {
            $sourceLanguageName = \Locale::getDisplayLanguage($io->source_culture, $culture);
        }

        // Keymap entries (for Administration area — source name from imports)
        $keymapEntries = collect();
        try {
            $keymapEntries = DB::table('keymap')
                ->where('target_id', $io->id)
                ->whereNotNull('source_name')
                ->select('source_name')
                ->get();
        } catch (\Exception $e) {
            // keymap table may not exist
        }

        // Translation links: get available cultures for this IO (other than current)
        $translationLinks = [];
        $availableCultures = DB::table('information_object_i18n')
            ->where('id', $io->id)
            ->where('culture', '!=', $culture)
            ->pluck('title', 'culture');
        foreach ($availableCultures as $code => $title) {
            $langName = \Locale::getDisplayLanguage($code, $culture);
            $translationLinks[$code] = [
                'language' => ucfirst($langName),
                'name' => $title ?: ($io->title ?? '[Untitled]'),
            ];
        }

        // Digital object rights (rights linked to each digital object via relation)
        $digitalObjectRights = [];
        if (auth()->check() && isset($digitalObjects) && $digitalObjects['master']) {
            foreach (['master', 'reference', 'thumbnail'] as $usageKey) {
                $doObj = $digitalObjects[$usageKey] ?? null;
                if (!$doObj) {
                    continue;
                }
                try {
                    $doRights = DB::table('relation')
                        ->join('rights', 'relation.object_id', '=', 'rights.id')
                        ->join('rights_i18n', 'rights.id', '=', 'rights_i18n.id')
                        ->where('relation.subject_id', $doObj->id)
                        ->where('relation.type_id', 168)
                        ->where('rights_i18n.culture', $culture)
                        ->select('rights.*', 'rights_i18n.rights_note')
                        ->get();
                    if ($doRights->isNotEmpty()) {
                        // Get usage name from term_i18n
                        $usageName = DB::table('term_i18n')
                            ->where('id', $doObj->usage_id ?? 0)
                            ->where('culture', $culture)
                            ->value('name') ?? ucfirst($usageKey);
                        $digitalObjectRights[$usageKey] = [
                            'usageName' => $usageName,
                            'rights' => $doRights,
                        ];
                    }
                } catch (\Exception $e) {
                    // rights table structure may vary
                }
            }
        }

        return view('ahg-io-manage::show', [
            'io' => $io,
            'levelName' => $levelName,
            'repository' => $repository,
            'events' => $events,
            'eventTypeNames' => $eventTypeNames,
            'creators' => $creators,
            'digitalObjects' => $digitalObjects,
            'notes' => $notes,
            'noteTypeNames' => $noteTypeNames,
            'children' => $children,
            'childLevelNames' => $childLevelNames,
            'breadcrumbs' => $breadcrumbs,
            'subjects' => $subjects,
            'places' => $places,
            'nameAccessPoints' => $nameAccessPoints,
            'genres' => $genres,
            'languages' => $languages,
            'publicationStatus' => $publicationStatus,
            'publicationStatusId' => $publicationStatusId,
            'functionRelations' => $functionRelations,
            'alternativeIdentifiers' => $alternativeIdentifiers,
            'physicalObjects' => $physicalObjects,
            'physicalObjectTypeNames' => $physicalObjectTypeNames,
            'rights' => $rights,
            'extendedRights' => $extendedRights,
            'extendedRightsTkLabels' => $extendedRightsTkLabels,
            'accessions' => $accessions,
            'descriptionStatusName' => $descriptionStatusName,
            'descriptionDetailName' => $descriptionDetailName,
            'languagesOfDescription' => $languagesOfDescription,
            'scriptsOfDescription' => $scriptsOfDescription,
            'materialLanguages' => $materialLanguages,
            'materialScripts' => $materialScripts,
            'prevSibling' => $prevSibling,
            'nextSibling' => $nextSibling,
            'findingAid' => $findingAid,
            'relatedMaterialDescriptions' => $relatedMaterialDescriptions,
            'museumMetadata' => $museumMetadata,
            'collectionRootId' => $collectionRootId,
            'hasChildren' => ($io->rgt - $io->lft) > 1,
            'displayStandardName' => $displayStandardName,
            'sourceLanguageName' => $sourceLanguageName,
            'keymapEntries' => $keymapEntries,
            'translationLinks' => $translationLinks,
            'digitalObjectRights' => $digitalObjectRights,
        ]);
    }

    /**
     * Print-friendly view for an information object.
     */
    public function print(string $slug)
    {
        // Reuse show() data gathering via a helper, then render print view
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_standard',
                'information_object.display_standard_id',
                'information_object.collection_type_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.edition',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'information_object_i18n.institution_responsible_identifier',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$io) {
            abort(404);
        }

        $levelName = null;
        if ($io->level_of_description_id) {
            $levelName = DB::table('term_i18n')->where('id', $io->level_of_description_id)->where('culture', $culture)->value('name');
        }

        $repository = null;
        if ($io->repository_id) {
            $repository = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->where('repository.id', $io->repository_id)->where('actor_i18n.culture', $culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')->first();
        }

        $events = DB::table('event')->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)->where('event_i18n.culture', $culture)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display', 'event_i18n.name as event_name')->get();

        $eventTypeIds = $events->pluck('type_id')->filter()->unique()->values()->toArray();
        $eventTypeNames = [];
        if (!empty($eventTypeIds)) {
            $eventTypeNames = DB::table('term_i18n')->whereIn('id', $eventTypeIds)->where('culture', $culture)->pluck('name', 'id')->toArray();
        }

        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('slug', 'event.actor_id', '=', 'slug.object_id')
            ->where('event.object_id', $io->id)->where('event.type_id', 111)->where('actor_i18n.culture', $culture)->whereNotNull('event.actor_id')
            ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name', 'actor_i18n.history', 'actor_i18n.dates_of_existence', 'actor.entity_type_id', 'slug.slug')
            ->distinct()->get();

        $notes = DB::table('note')->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')->get();

        $children = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.parent_id', $io->id)->where('information_object_i18n.culture', $culture)
            ->orderBy('information_object.lft')
            ->select('information_object.id', 'information_object.level_of_description_id', 'information_object_i18n.title', 'slug.slug')->get();

        $childLevelIds = $children->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        $childLevelNames = [];
        if (!empty($childLevelIds)) {
            $childLevelNames = DB::table('term_i18n')->whereIn('id', $childLevelIds)->where('culture', $culture)->pluck('name', 'id')->toArray();
        }

        $breadcrumbs = [];
        $parentId = $io->parent_id;
        while ($parentId && $parentId != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $parentId)->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')->first();
            if (!$parent) break;
            array_unshift($breadcrumbs, $parent);
            $parentId = $parent->parent_id;
        }

        $subjects = DB::table('object_term_relation')->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)->where('term.taxonomy_id', 35)->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')->get();

        $places = DB::table('object_term_relation')->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)->where('term.taxonomy_id', 42)->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')->get();

        $nameAccessPoints = DB::table('relation')->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)->where('relation.type_id', 161)->where('actor_i18n.culture', $culture)
            ->select('actor_i18n.authorized_form_of_name as name')->get();

        $genres = DB::table('object_term_relation')->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)->where('term.taxonomy_id', 78)->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')->get();

        $languages = DB::table('information_object')
            ->join('object_term_relation', 'information_object.id', '=', 'object_term_relation.object_id')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('information_object.id', $io->id)->where('term.taxonomy_id', 7)->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')->get();

        $publicationStatus = null;
        $statusRow = DB::table('status')->where('object_id', $io->id)->where('type_id', 158)->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatus = DB::table('term_i18n')->where('id', $statusRow->status_id)->where('culture', $culture)->value('name');
        }

        $functionRelations = collect();
        try {
            $functionRelations = DB::table('relation')
                ->join('function_object', 'relation.subject_id', '=', 'function_object.id')
                ->join('function_i18n', 'function_object.id', '=', 'function_i18n.id')
                ->join('slug', 'function_object.id', '=', 'slug.object_id')
                ->where('relation.object_id', $io->id)->where('function_i18n.culture', $culture)
                ->select('function_object.id', 'function_i18n.authorized_form_of_name as name', 'slug.slug')->get();
        } catch (\Exception $e) {}

        $alternativeIdentifiers = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'alternativeIdentifiers')->where('property_i18n.culture', $culture)
            ->select('property_i18n.value')->get();

        $physicalObjects = DB::table('relation')
            ->join('physical_object', 'relation.object_id', '=', 'physical_object.id')
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->where('relation.subject_id', $io->id)->where('relation.type_id', 151)->where('physical_object_i18n.culture', $culture)
            ->select('physical_object.id', 'physical_object_i18n.name', 'physical_object_i18n.location', 'physical_object.type_id')->get();

        $physicalObjectTypeIds = $physicalObjects->pluck('type_id')->filter()->unique()->values()->toArray();
        $physicalObjectTypeNames = [];
        if (!empty($physicalObjectTypeIds)) {
            $physicalObjectTypeNames = DB::table('term_i18n')->whereIn('id', $physicalObjectTypeIds)->where('culture', $culture)->pluck('name', 'id')->toArray();
        }

        $rights = collect();
        if (auth()->check()) {
            try {
                $rights = DB::table('relation')->join('rights', 'relation.object_id', '=', 'rights.id')
                    ->join('rights_i18n', 'rights.id', '=', 'rights_i18n.id')
                    ->where('relation.subject_id', $io->id)->where('relation.type_id', 168)->where('rights_i18n.culture', $culture)
                    ->select('rights.*', 'rights_i18n.rights_note')->get();
            } catch (\Exception $e) {}
        }

        $accessions = collect();
        try {
            $accessions = DB::table('relation')
                ->join('accession', 'relation.object_id', '=', 'accession.id')
                ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
                ->join('slug', 'accession.id', '=', 'slug.object_id')
                ->where('relation.subject_id', $io->id)->where('accession_i18n.culture', $culture)
                ->select('accession.id', 'accession.identifier', 'accession_i18n.title', 'slug.slug')->get();
        } catch (\Exception $e) {}

        $descriptionStatusName = null;
        if ($io->description_status_id) {
            $descriptionStatusName = DB::table('term_i18n')->where('id', $io->description_status_id)->where('culture', $culture)->value('name');
        }
        $descriptionDetailName = null;
        if ($io->description_detail_id) {
            $descriptionDetailName = DB::table('term_i18n')->where('id', $io->description_detail_id)->where('culture', $culture)->value('name');
        }

        $languagesOfDescriptionRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'languageOfDescription')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $languagesOfDescription = collect();
        if ($languagesOfDescriptionRaw) { $decoded = @unserialize($languagesOfDescriptionRaw); if (is_array($decoded) && !empty($decoded)) { $languagesOfDescription = collect($decoded); } }

        $scriptsOfDescriptionRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'scriptOfDescription')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $scriptsOfDescription = collect();
        if ($scriptsOfDescriptionRaw) { $decoded = @unserialize($scriptsOfDescriptionRaw); if (is_array($decoded) && !empty($decoded)) { $scriptsOfDescription = collect($decoded); } }

        $materialScriptsRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'script')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $materialScripts = collect();
        if ($materialScriptsRaw) { $decoded = @unserialize($materialScriptsRaw); if (is_array($decoded) && !empty($decoded)) { $materialScripts = collect($decoded); } }

        return view('ahg-io-manage::print', [
            'io' => $io,
            'levelName' => $levelName,
            'repository' => $repository,
            'events' => $events,
            'eventTypeNames' => $eventTypeNames,
            'creators' => $creators,
            'notes' => $notes,
            'children' => $children,
            'childLevelNames' => $childLevelNames,
            'breadcrumbs' => $breadcrumbs,
            'subjects' => $subjects,
            'places' => $places,
            'nameAccessPoints' => $nameAccessPoints,
            'genres' => $genres,
            'languages' => $languages,
            'publicationStatus' => $publicationStatus,
            'functionRelations' => $functionRelations,
            'alternativeIdentifiers' => $alternativeIdentifiers,
            'physicalObjects' => $physicalObjects,
            'physicalObjectTypeNames' => $physicalObjectTypeNames,
            'rights' => $rights,
            'accessions' => $accessions,
            'descriptionStatusName' => $descriptionStatusName,
            'descriptionDetailName' => $descriptionDetailName,
            'languagesOfDescription' => $languagesOfDescription,
            'scriptsOfDescription' => $scriptsOfDescription,
            'materialScripts' => $materialScripts,
        ]);
    }

    /**
     * Fetch dropdown options used by edit and create forms.
     */
    private function getFormDropdowns(string $culture): array
    {
        // Level of description options (taxonomy_id = 34)
        $levels = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 34)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Repositories
        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // Description status options (taxonomy_id = 44)
        $descriptionStatuses = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 44)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Description detail options (taxonomy_id = 43)
        $descriptionDetails = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 43)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Display standard options (taxonomy_id = 52 — descriptive standards)
        $displayStandards = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 52)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Event type options (taxonomy_id = 40)
        $eventTypes = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 40)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Note types for general notes (ISAD)
        $noteTypes = collect([
            (object) ['id' => 125, 'name' => 'General note'],
            (object) ['id' => 174, 'name' => 'Language note'],
        ]);

        // Security classifications
        $securityClassifications = DB::table('security_classification')
            ->orderBy('level')
            ->select('id', 'name', 'level')
            ->get();

        // Watermark types
        $watermarkTypes = DB::table('watermark_type')
            ->orderBy('name')
            ->select('id', 'name', 'image_file')
            ->get();

        return compact('levels', 'repositories', 'descriptionStatuses', 'descriptionDetails', 'displayStandards', 'eventTypes', 'noteTypes', 'securityClassifications', 'watermarkTypes');
    }

    /**
     * Show the edit form for an information object.
     */
    public function edit(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.oai_local_identifier',
                'information_object.level_of_description_id',
                'information_object.collection_type_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_standard',
                'information_object.display_standard_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.edition',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.institution_responsible_identifier',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$io) {
            abort(404);
        }

        $dropdowns = $this->getFormDropdowns($culture);

        // Museum metadata (CCO fields) — present when this IO has a museum_metadata row
        $museumMetadata = [];
        try {
            $mmRow = DB::table('museum_metadata')
                ->where('object_id', $io->id)
                ->first();
            if ($mmRow) {
                $museumMetadata = (array) $mmRow;
            }
        } catch (\Exception $e) {
            // museum_metadata table may not exist in all installs
        }

        // Events (dates) — multi-row with type, date display, start/end, actor
        $events = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event_i18n.culture', $culture)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display', 'event_i18n.name as event_name')
            ->get();
        // Resolve actor names for events
        foreach ($events as $evt) {
            $evt->actor_name = null;
            if ($evt->actor_id) {
                $evt->actor_name = DB::table('actor_i18n')->where('id', $evt->actor_id)->where('culture', $culture)->value('authorized_form_of_name');
            }
        }

        // Creators (events where type_id = 111)
        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name')
            ->distinct()
            ->get();

        // Notes (all note types)
        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')
            ->get();

        // Separate publication notes (type_id = 220) and archivist notes (type_id = 174)
        $publicationNotes = $notes->where('type_id', 220)->values();
        $archivistNotes = $notes->where('type_id', 174)->values();
        $generalNotes = $notes->whereNotIn('type_id', [220, 174])->values();

        // Subject access points (taxonomy_id = 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Place access points (taxonomy_id = 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Genre access points (taxonomy_id = 78)
        $genres = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 78)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Name access points (via relation table, type_id = 161)
        $nameAccessPoints = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 161)
            ->where('actor_i18n.culture', $culture)
            ->select('relation.object_id as actor_id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // Alternative identifiers (from property table)
        $alternativeIdentifiers = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'alternativeIdentifiers')
            ->where('property_i18n.culture', $culture)
            ->select('property_i18n.value')
            ->get();

        // Publication status
        $publicationStatusId = null;
        $statusRow = DB::table('status')->where('object_id', $io->id)->where('type_id', 158)->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
        }

        // Languages/scripts of material (from property table, serialized)
        $materialLanguagesRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'language')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $materialLanguages = collect();
        if ($materialLanguagesRaw) { $decoded = @unserialize($materialLanguagesRaw); if (is_array($decoded)) { $materialLanguages = collect($decoded); } }

        $materialScriptsRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'script')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $materialScripts = collect();
        if ($materialScriptsRaw) { $decoded = @unserialize($materialScriptsRaw); if (is_array($decoded)) { $materialScripts = collect($decoded); } }

        // Languages/scripts of description (from property table, serialized)
        $languagesOfDescriptionRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'languageOfDescription')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $languagesOfDescription = collect();
        if ($languagesOfDescriptionRaw) { $decoded = @unserialize($languagesOfDescriptionRaw); if (is_array($decoded)) { $languagesOfDescription = collect($decoded); } }

        $scriptsOfDescriptionRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'scriptOfDescription')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $scriptsOfDescription = collect();
        if ($scriptsOfDescriptionRaw) { $decoded = @unserialize($scriptsOfDescriptionRaw); if (is_array($decoded)) { $scriptsOfDescription = collect($decoded); } }

        // Related material descriptions
        $relatedMaterialDescriptions = collect();
        try {
            $relatedMaterialDescriptions = DB::table('relation')
                ->join('information_object_i18n', 'relation.object_id', '=', 'information_object_i18n.id')
                ->join('slug', 'relation.object_id', '=', 'slug.object_id')
                ->where('relation.subject_id', $io->id)
                ->where('relation.type_id', 173) // Related material description relation
                ->where('information_object_i18n.culture', $culture)
                ->select('relation.object_id as id', 'information_object_i18n.title', 'slug.slug')
                ->get();
        } catch (\Exception $e) {}

        // Parent info for admin area
        $parentTitle = null;
        $parentSlug = null;
        if ($io->parent_id && $io->parent_id != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $io->parent_id)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object_i18n.title', 'slug.slug')
                ->first();
            if ($parent) {
                $parentTitle = $parent->title;
                $parentSlug = $parent->slug;
            }
        }

        // Form choices for security and other dropdowns
        $formChoices = [
            'securityLevels' => $dropdowns['securityClassifications'] ?? collect(),
            'displayStandards' => ($dropdowns['displayStandards'] ?? collect())->pluck('name', 'id'),
        ];

        return view('ahg-io-manage::edit', array_merge(
            [
                'io' => $io,
                'museumMetadata' => $museumMetadata,
                'events' => $events,
                'creators' => $creators,
                'notes' => $generalNotes,
                'publicationNotes' => $publicationNotes,
                'archivistNotes' => $archivistNotes,
                'subjects' => $subjects,
                'places' => $places,
                'genres' => $genres,
                'nameAccessPoints' => $nameAccessPoints,
                'alternativeIdentifiers' => $alternativeIdentifiers,
                'publicationStatusId' => $publicationStatusId,
                'materialLanguages' => $materialLanguages,
                'materialScripts' => $materialScripts,
                'languagesOfDescription' => $languagesOfDescription,
                'scriptsOfDescription' => $scriptsOfDescription,
                'relatedMaterialDescriptions' => $relatedMaterialDescriptions,
                'parentTitle' => $parentTitle,
                'parentSlug' => $parentSlug,
                'formChoices' => $formChoices,
            ],
            $dropdowns
        ));
    }

    /**
     * Show the create form for a new information object.
     */
    public function create(Request $request)
    {
        $culture = app()->getLocale();
        $parentId = $request->get('parent_id');

        // If parent_id provided, resolve parent title for display
        $parentTitle = null;
        if ($parentId) {
            $parentTitle = DB::table('information_object_i18n')
                ->where('id', $parentId)
                ->where('culture', $culture)
                ->value('title');
        }

        $dropdowns = $this->getFormDropdowns($culture);

        return view('ahg-io-manage::create', array_merge(
            [
                'parentId' => $parentId,
                'parentTitle' => $parentTitle,
            ],
            $dropdowns
        ));
    }

    /**
     * Update an existing information object.
     */
    public function update(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $request->validate([
            'title' => 'required|string|max:65535',
        ]);

        // Resolve the IO id from slug
        $io = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id')
            ->first();

        if (!$io) {
            abort(404);
        }

        $ioId = $io->id;

        // Update information_object table
        DB::table('information_object')
            ->where('id', $ioId)
            ->update([
                'identifier' => $request->input('identifier'),
                'level_of_description_id' => $request->input('level_of_description_id') ?: null,
                'repository_id' => $request->input('repository_id') ?: null,
                'description_status_id' => $request->input('description_status_id') ?: null,
                'description_detail_id' => $request->input('description_detail_id') ?: null,
                'description_identifier' => $request->input('description_identifier'),
                'source_standard' => $request->input('source_standard'),
                'display_standard_id' => $request->input('display_standard_id') ?: null,
            ]);

        // Update information_object_i18n table
        DB::table('information_object_i18n')
            ->where('id', $ioId)
            ->where('culture', $culture)
            ->update([
                'title' => $request->input('title'),
                'alternate_title' => $request->input('alternate_title'),
                'edition' => $request->input('edition'),
                'extent_and_medium' => $request->input('extent_and_medium'),
                'archival_history' => $request->input('archival_history'),
                'acquisition' => $request->input('acquisition'),
                'scope_and_content' => $request->input('scope_and_content'),
                'appraisal' => $request->input('appraisal'),
                'accruals' => $request->input('accruals'),
                'arrangement' => $request->input('arrangement'),
                'access_conditions' => $request->input('access_conditions'),
                'reproduction_conditions' => $request->input('reproduction_conditions'),
                'physical_characteristics' => $request->input('physical_characteristics'),
                'finding_aids' => $request->input('finding_aids'),
                'location_of_originals' => $request->input('location_of_originals'),
                'location_of_copies' => $request->input('location_of_copies'),
                'related_units_of_description' => $request->input('related_units_of_description'),
                'institution_responsible_identifier' => $request->input('institution_responsible_identifier'),
                'rules' => $request->input('rules'),
                'sources' => $request->input('sources'),
                'revision_history' => $request->input('revision_history'),
            ]);

        // Save museum_metadata if this IO has CCO fields submitted
        try {
            $museumFields = $request->only([
                'work_type', 'object_type', 'classification', 'materials', 'techniques',
                'measurements', 'dimensions', 'creation_date_earliest', 'creation_date_latest',
                'inscription', 'inscriptions', 'condition_notes', 'provenance', 'style_period',
                'cultural_context', 'current_location', 'edition_description', 'state_description',
                'state_identification', 'facture_description', 'technique_cco', 'technique_qualifier',
                'orientation', 'physical_appearance', 'color', 'shape', 'condition_term',
                'condition_date', 'condition_description', 'condition_agent', 'treatment_type',
                'treatment_date', 'treatment_agent', 'treatment_description',
                'inscription_transcription', 'inscription_type', 'inscription_location',
                'inscription_language', 'inscription_translation', 'mark_type', 'mark_description',
                'mark_location', 'related_work_type', 'related_work_relationship',
                'related_work_label', 'related_work_id', 'current_location_repository',
                'current_location_geography', 'current_location_coordinates',
                'current_location_ref_number', 'creation_place', 'creation_place_type',
                'discovery_place', 'discovery_place_type', 'provenance_text', 'ownership_history',
                'legal_status', 'rights_type', 'rights_holder', 'rights_date', 'rights_remarks',
                'cataloger_name', 'cataloging_date', 'cataloging_institution', 'cataloging_remarks',
                'record_type', 'record_level', 'creator_identity', 'creator_role', 'creator_extent',
                'creator_qualifier', 'creator_attribution', 'creation_date_display',
                'creation_date_qualifier', 'style', 'period', 'cultural_group', 'movement',
                'school', 'dynasty', 'subject_indexing_type', 'subject_display', 'subject_extent',
                'historical_context', 'architectural_context', 'archaeological_context',
                'object_class', 'object_category', 'object_sub_category', 'edition_number',
                'edition_size',
            ]);
            if (!empty($museumFields)) {
                $museumService = new \AhgMuseum\Services\MuseumService($culture);
                $museumService->saveMuseumMetadata($ioId, $museumFields);
            }
        } catch (\Exception $e) {
            // museum_metadata table may not exist in all installs
        }

        // Update object.updated_at
        DB::table('object')
            ->where('id', $ioId)
            ->update([
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('informationobject.show', $slug)
            ->with('success', 'Archival description updated successfully.');
    }

    /**
     * Store a new information object.
     */
    public function store(Request $request)
    {
        $culture = app()->getLocale();

        $request->validate([
            'title' => 'required|string|max:65535',
        ]);

        $parentId = $request->input('parent_id', 1); // Default to root (id=1)

        // Determine lft/rgt position: place as last child of parent
        $parent = DB::table('information_object')
            ->where('id', $parentId)
            ->select('lft', 'rgt')
            ->first();

        if (!$parent) {
            abort(422, 'Invalid parent information object.');
        }

        $newLft = $parent->rgt;
        $newRgt = $parent->rgt + 1;

        // Shift existing nested set values to make room
        DB::table('information_object')
            ->where('rgt', '>=', $parent->rgt)
            ->increment('rgt', 2);

        DB::table('information_object')
            ->where('lft', '>', $parent->rgt)
            ->increment('lft', 2);

        // Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert into information_object table
        DB::table('information_object')->insert([
            'id' => $objectId,
            'identifier' => $request->input('identifier'),
            'level_of_description_id' => $request->input('level_of_description_id') ?: null,
            'collection_type_id' => null,
            'repository_id' => $request->input('repository_id') ?: null,
            'parent_id' => $parentId,
            'description_status_id' => $request->input('description_status_id') ?: null,
            'description_detail_id' => $request->input('description_detail_id') ?: null,
            'description_identifier' => $request->input('description_identifier'),
            'source_standard' => $request->input('source_standard'),
            'display_standard_id' => $request->input('display_standard_id') ?: null,
            'lft' => $newLft,
            'rgt' => $newRgt,
            'source_culture' => $culture,
        ]);

        // Insert into information_object_i18n table
        DB::table('information_object_i18n')->insert([
            'id' => $objectId,
            'culture' => $culture,
            'title' => $request->input('title'),
            'alternate_title' => $request->input('alternate_title'),
            'edition' => $request->input('edition'),
            'extent_and_medium' => $request->input('extent_and_medium'),
            'archival_history' => $request->input('archival_history'),
            'acquisition' => $request->input('acquisition'),
            'scope_and_content' => $request->input('scope_and_content'),
            'appraisal' => $request->input('appraisal'),
            'accruals' => $request->input('accruals'),
            'arrangement' => $request->input('arrangement'),
            'access_conditions' => $request->input('access_conditions'),
            'reproduction_conditions' => $request->input('reproduction_conditions'),
            'physical_characteristics' => $request->input('physical_characteristics'),
            'finding_aids' => $request->input('finding_aids'),
            'location_of_originals' => $request->input('location_of_originals'),
            'location_of_copies' => $request->input('location_of_copies'),
            'related_units_of_description' => $request->input('related_units_of_description'),
            'institution_responsible_identifier' => $request->input('institution_responsible_identifier'),
            'rules' => $request->input('rules'),
            'sources' => $request->input('sources'),
            'revision_history' => $request->input('revision_history'),
        ]);

        // Generate slug
        $baseSlug = Str::slug($request->input('title') ?: 'untitled');
        $slug = $baseSlug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
        ]);

        return redirect()
            ->route('informationobject.show', $slug)
            ->with('success', 'Archival description created successfully.');
    }

    /**
     * Confirm deletion of an information object.
     */
    public function confirmDelete(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                  ->where('information_object_i18n.culture', '=', $culture);
            })
            ->join('slug', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::delete', [
            'io' => $io,
        ]);
    }

    /**
     * Delete an information object.
     */
    /**
     * Update publication status for an information object.
     * Status is stored in the `status` table with type_id=158.
     * 160 = Published, 159 = Draft.
     */
    public function updateStatus(Request $request, string $slug)
    {
        $statusId = (int) $request->input('publication_status');
        if (!in_array($statusId, [159, 160], true)) {
            return redirect()->back()->with('error', 'Invalid publication status.');
        }

        $io = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id')
            ->first();

        if (!$io) {
            abort(404);
        }

        // type_id 158 = publicationStatus
        $exists = DB::table('status')
            ->where('object_id', $io->id)
            ->where('type_id', 158)
            ->first();

        if ($exists) {
            DB::table('status')
                ->where('object_id', $io->id)
                ->where('type_id', 158)
                ->update(['status_id' => $statusId]);
        } else {
            DB::table('status')->insert([
                'object_id' => $io->id,
                'type_id' => 158,
                'status_id' => $statusId,
            ]);
        }

        $label = $statusId === 160 ? 'Published' : 'Draft';

        return redirect()->back()->with('success', "Publication status updated to {$label}.");
    }

    public function destroy(Request $request, string $slug)
    {
        // Resolve the IO id from slug
        $record = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt')
            ->first();

        if (!$record) {
            abort(404);
        }

        $ioId = $record->id;
        $width = $record->rgt - $record->lft + 1;

        // Collect all descendant IDs (nested set: lft between this node's lft and rgt)
        $descendantIds = DB::table('information_object')
            ->whereBetween('lft', [$record->lft, $record->rgt])
            ->pluck('id')
            ->toArray();

        // Delete i18n rows for all descendants
        DB::table('information_object_i18n')
            ->whereIn('id', $descendantIds)
            ->delete();

        // Delete information_object rows for all descendants
        DB::table('information_object')
            ->whereIn('id', $descendantIds)
            ->delete();

        // Delete slug rows for all descendants
        DB::table('slug')
            ->whereIn('object_id', $descendantIds)
            ->delete();

        // Delete object rows for all descendants
        DB::table('object')
            ->whereIn('id', $descendantIds)
            ->delete();

        // Close the gap in the nested set
        DB::table('information_object')
            ->where('lft', '>', $record->rgt)
            ->decrement('lft', $width);

        DB::table('information_object')
            ->where('rgt', '>', $record->rgt)
            ->decrement('rgt', $width);

        return redirect()
            ->route('informationobject.browse')
            ->with('success', 'Archival description deleted successfully.');
    }

    /**
     * Reports page for an information object.
     *
     * Shows per-record report options (box labels, file lists, item lists, storage locations).
     * Migrated from AtoM informationobject/reportsSuccess.php.
     */
    public function reports(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Check for existing reports (job-generated report files)
        $existingReports = [];
        $reportsDir = storage_path('app/reports/' . $io->id);
        if (is_dir($reportsDir)) {
            foreach (glob($reportsDir . '/*') as $file) {
                $basename = basename($file);
                $parts = explode('.', $basename);
                $format = end($parts);
                $type = str_replace('_', ' ', pathinfo($basename, PATHINFO_FILENAME));
                $existingReports[] = [
                    'type' => ucfirst($type),
                    'format' => strtoupper($format),
                    'path' => route('informationobject.show', $slug) . '/reports/download/' . $basename,
                ];
            }
        }

        // Report types available for this record
        $reportTypes = [
            'fileList' => 'File list',
            'itemList' => 'Item list',
            'storageLocations' => 'Physical storage locations',
            'boxLabel' => 'Box label',
        ];

        return view('ahg-io-manage::reports', [
            'io' => $io,
            'existingReports' => $existingReports,
            'reportTypes' => $reportTypes,
            'reportsAvailable' => !empty($reportTypes),
        ]);
    }

    /**
     * Rename form for an information object.
     *
     * Shows fields to update title, slug, and optionally digital object filename.
     * Migrated from AtoM informationobject/renameSuccess.php.
     */
    public function rename(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Check for digital object
        $digitalObject = DB::table('digital_object')
            ->where('object_id', $io->id)
            ->select('id', 'name')
            ->first();

        return view('ahg-io-manage::rename', [
            'io' => $io,
            'digitalObject' => $digitalObject,
        ]);
    }

    /**
     * Process the rename form submission.
     */
    public function renameUpdate(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $ioRow = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'slug.slug')
            ->first();

        if (!$ioRow) {
            abort(404);
        }

        $newTitle = $request->input('title');
        $newSlug = $request->input('slug');

        DB::transaction(function () use ($ioRow, $newTitle, $newSlug, $culture) {
            // Update title
            if ($newTitle !== null) {
                DB::table('information_object_i18n')
                    ->where('id', $ioRow->id)
                    ->where('culture', $culture)
                    ->update(['title' => $newTitle]);
            }

            // Update slug
            if ($newSlug !== null && $newSlug !== $ioRow->slug) {
                $cleanSlug = \Illuminate\Support\Str::slug($newSlug);
                if (empty($cleanSlug)) {
                    $cleanSlug = 'untitled';
                }
                // Ensure uniqueness
                $baseSlug = $cleanSlug;
                $counter = 1;
                while (DB::table('slug')->where('slug', $cleanSlug)->where('object_id', '!=', $ioRow->id)->exists()) {
                    $cleanSlug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                DB::table('slug')
                    ->where('object_id', $ioRow->id)
                    ->update(['slug' => $cleanSlug]);

                $newSlug = $cleanSlug;
            } else {
                $newSlug = $ioRow->slug;
            }

            // Touch the object
            DB::table('object')->where('id', $ioRow->id)->update(['updated_at' => now()]);
        });

        return redirect()
            ->route('informationobject.show', $newSlug ?? $slug)
            ->with('success', 'Record renamed successfully.');
    }

    /**
     * Inventory list for an information object.
     *
     * Lists children of the IO at specific levels of description.
     * Migrated from AtoM informationobject/inventorySuccess.php.
     */
    public function inventory(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Get inventory levels from settings (default: Item level = term_id for "Item")
        $inventoryLevelIds = DB::table('setting')
            ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
            ->where('setting.name', 'inventory_levels')
            ->where('setting_i18n.culture', $culture)
            ->value('setting_i18n.value');

        $levelIds = [];
        if ($inventoryLevelIds) {
            $decoded = @unserialize($inventoryLevelIds);
            if (is_array($decoded)) {
                $levelIds = $decoded;
            }
        }

        // Build inventory query: descendants at specified levels
        $query = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.lft', '>', $io->lft)
            ->where('information_object.rgt', '<', $io->rgt)
            ->where('information_object_i18n.culture', $culture);

        if (!empty($levelIds)) {
            $query->whereIn('information_object.level_of_description_id', $levelIds);
        }

        $perPage = 30;
        $page = max(1, (int) request('page', 1));
        $total = (clone $query)->count();

        $sortField = request('sort', 'identifier');
        $sortMap = [
            'identifier' => 'information_object.identifier',
            'title' => 'information_object_i18n.title',
            'level' => 'information_object.level_of_description_id',
            'lft' => 'information_object.lft',
        ];
        $orderBy = $sortMap[$sortField] ?? 'information_object.lft';

        $items = $query->orderBy($orderBy)
            ->select(
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object_i18n.title',
                'slug.slug'
            )
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Resolve level names
        $levelIds = $items->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        $levelNames = [];
        if (!empty($levelIds)) {
            $levelNames = DB::table('term_i18n')
                ->whereIn('id', $levelIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Check for digital objects
        $itemIds = $items->pluck('id')->toArray();
        $hasDigitalObject = [];
        if (!empty($itemIds)) {
            $hasDigitalObject = DB::table('digital_object')
                ->whereIn('object_id', $itemIds)
                ->pluck('object_id')
                ->flip()
                ->toArray();
        }

        // Get dates for items
        $itemDates = [];
        if (!empty($itemIds)) {
            $dates = DB::table('event')
                ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
                ->whereIn('event.object_id', $itemIds)
                ->where('event_i18n.culture', $culture)
                ->select('event.object_id', 'event_i18n.date as date_display', 'event.start_date', 'event.end_date')
                ->get();
            foreach ($dates as $d) {
                $itemDates[$d->object_id] = $d->date_display ?: trim(($d->start_date ?? '') . ' - ' . ($d->end_date ?? ''), ' -');
            }
        }

        // Build breadcrumbs
        $breadcrumbs = [];
        $parentId = $io->parent_id;
        while ($parentId && $parentId != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $parentId)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')
                ->first();
            if (!$parent) {
                break;
            }
            array_unshift($breadcrumbs, $parent);
            $parentId = $parent->parent_id;
        }

        return view('ahg-io-manage::inventory', [
            'io' => $io,
            'items' => $items,
            'levelNames' => $levelNames,
            'hasDigitalObject' => $hasDigitalObject,
            'itemDates' => $itemDates,
            'breadcrumbs' => $breadcrumbs,
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
        ]);
    }

    /**
     * Calculate dates for an information object.
     *
     * Walks descendant records to find the earliest start_date and latest end_date,
     * then updates the parent creation event.
     * Migrated from AtoM informationobject/calculateDatesAction.
     */
    public function calculateDates(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Get all descendant IDs
        $descendantIds = DB::table('information_object')
            ->where('lft', '>', $io->lft)
            ->where('rgt', '<', $io->rgt)
            ->pluck('id')
            ->toArray();

        if (empty($descendantIds)) {
            return redirect()
                ->route('informationobject.show', $slug)
                ->with('info', 'No child descriptions found to calculate dates from.');
        }

        // Find earliest start_date and latest end_date across all descendants
        $dateRange = DB::table('event')
            ->whereIn('object_id', $descendantIds)
            ->selectRaw('MIN(start_date) as earliest_start, MAX(end_date) as latest_end')
            ->first();

        if (!$dateRange || (!$dateRange->earliest_start && !$dateRange->latest_end)) {
            return redirect()
                ->route('informationobject.show', $slug)
                ->with('info', 'No date information found in child descriptions.');
        }

        // Update or create the creation event for this IO
        $event = DB::table('event')
            ->where('object_id', $io->id)
            ->where('type_id', 111) // Creation event
            ->first();

        if ($event) {
            DB::table('event')
                ->where('id', $event->id)
                ->update([
                    'start_date' => $dateRange->earliest_start,
                    'end_date' => $dateRange->latest_end,
                ]);

            // Update the event_i18n date display
            $displayDate = trim(($dateRange->earliest_start ?? '') . ' - ' . ($dateRange->latest_end ?? ''), ' -');
            DB::table('event_i18n')
                ->where('id', $event->id)
                ->where('culture', $culture)
                ->update(['date' => $displayDate]);
        }

        // Touch the object
        DB::table('object')->where('id', $io->id)->update(['updated_at' => now()]);

        return redirect()
            ->route('informationobject.show', $slug)
            ->with('success', 'Dates calculated successfully from child descriptions.');
    }

    public function autocomplete(Request $request)
    {
        $query = $request->get('query', '');
        $culture = app()->getLocale();
        $limit = (int) $request->get('limit', 10);

        $results = DB::table('information_object')
            ->join('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                  ->where('information_object_i18n.culture', '=', $culture);
            })
            ->join('slug', 'slug.object_id', '=', 'information_object.id')
            ->where('information_object_i18n.title', 'LIKE', '%' . $query . '%')
            ->select(
                'information_object.id',
                'information_object_i18n.title as name',
                'slug.slug'
            )
            ->limit($limit)
            ->get();

        return response()->json($results);
    }
}
