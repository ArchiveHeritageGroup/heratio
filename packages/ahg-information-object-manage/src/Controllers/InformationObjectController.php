<?php

namespace AhgInformationObjectManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\DigitalObjectService;
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
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ];

        // Optional repository filter
        $repositoryId = $request->get('repository');
        if ($repositoryId) {
            $params['filters']['repository_id'] = $repositoryId;
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

        return view('ahg-io-manage::browse', [
            'pager' => $pager,
            'levelNames' => $result['levelNames'] ?? [],
            'repositoryNames' => $result['repositoryNames'] ?? [],
            'repositories' => $repositories,
            'selectedRepository' => $repositoryId,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'lastUpdated' => 'Date modified',
                'identifier' => 'Identifier',
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
        $statusRow = DB::table('status')
            ->where('object_id', $io->id)
            ->where('type_id', 158)
            ->first();
        if ($statusRow && $statusRow->status_id) {
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
            'materialLanguages' => $materialLanguages,
            'materialScripts' => $materialScripts,
            'prevSibling' => $prevSibling,
            'nextSibling' => $nextSibling,
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

        return compact('levels', 'repositories', 'descriptionStatuses', 'descriptionDetails', 'displayStandards');
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

        return view('ahg-io-manage::edit', array_merge(
            ['io' => $io],
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
     * Delete an information object.
     */
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
}
