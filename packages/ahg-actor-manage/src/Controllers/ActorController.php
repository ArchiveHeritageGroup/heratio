<?php

namespace AhgActorManage\Controllers;

use AhgActorManage\Services\ActorBrowseService;
use AhgActorManage\Services\ActorService;
use AhgActorManage\Services\AuthorityCompletenessService;
use AhgActorManage\Services\AuthorityDedupeService;
use AhgActorManage\Services\AuthorityFunctionService;
use AhgActorManage\Services\AuthorityGraphService;
use AhgActorManage\Services\AuthorityIdentifierService;
use AhgActorManage\Services\AuthorityLookupService;
use AhgActorManage\Services\AuthorityMergeService;
use AhgActorManage\Services\AuthorityNerPipelineService;
use AhgActorManage\Services\AuthorityOccupationService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActorController extends Controller
{
    protected ActorService $service;

    public function __construct()
    {
        $this->service = new ActorService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new ActorBrowseService($culture);

        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('query', $request->get('subquery', '')),
            'entityType' => $request->get('entityType', ''),
            'repository' => $request->get('repository', ''),
            'hasDigitalObject' => $request->get('hasDigitalObject', ''),
            'emptyField' => $request->get('emptyField', ''),
            'relatedType' => $request->get('relatedType', ''),
            'relatedAuthority' => $request->get('relatedAuthority', ''),
        ];

        // Collect advanced search criteria
        for ($i = 0; $i < 10; $i++) {
            $params["sq{$i}"] = $request->get("sq{$i}", '');
            $params["sf{$i}"] = $request->get("sf{$i}", '');
            $params["so{$i}"] = $request->get("so{$i}", 'and');
        }

        $hasAdvancedFilters = $params['entityType'] || $params['repository']
            || $params['hasDigitalObject'] || $params['emptyField']
            || $params['sq0'];

        $result = $hasAdvancedFilters
            ? $browseService->browseAdvanced($params)
            : $browseService->browse($params);

        $pager = new SimplePager($result);

        // Sidebar facets
        $entityTypeFacets = $browseService->getEntityTypeFacets();
        $totalCount = $browseService->getTotalCount();
        $repositories = $browseService->getRepositories();
        $maintainedByFacets = $browseService->getMaintainedByFacets();
        $occupationFacets = $browseService->getOccupationFacets();
        $placeFacets = $browseService->getPlaceFacets();
        $subjectFacets = $browseService->getSubjectFacets();
        $mediaTypeFacets = $browseService->getMediaTypeFacets();
        $languageFacets = $browseService->getLanguageFacets();

        // Build filter tags for active filters
        $filterTags = [];
        $baseUrl = url('/actor/browse');
        $allParams = $request->except(['page']);

        if (!empty($params['subquery'])) {
            $removeParams = $allParams;
            unset($removeParams['query'], $removeParams['subquery']);
            $filterTags[] = ['label' => 'Search: ' . $params['subquery'], 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if (!empty($params['entityType']) && isset($entityTypeFacets[$params['entityType']])) {
            $removeParams = $allParams;
            unset($removeParams['entityType']);
            $filterTags[] = ['label' => 'Entity type: ' . $entityTypeFacets[$params['entityType']]['name'], 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if (!empty($params['repository'])) {
            $repoName = collect($repositories)->firstWhere('id', $params['repository'])->name ?? $params['repository'];
            $removeParams = $allParams;
            unset($removeParams['repository']);
            $filterTags[] = ['label' => 'Repository: ' . $repoName, 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if (!empty($params['hasDigitalObject'])) {
            $removeParams = $allParams;
            unset($removeParams['hasDigitalObject']);
            $filterTags[] = ['label' => 'Has digital object', 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if (!empty($params['emptyField'])) {
            $removeParams = $allParams;
            unset($removeParams['emptyField']);
            $filterTags[] = ['label' => 'Empty field: ' . $params['emptyField'], 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if ($request->has('languages') && $request->get('languages') !== '') {
            $langCode = $request->get('languages');
            $langName = isset($languageFacets[$langCode]) ? $languageFacets[$langCode]['name'] : $langCode;
            $removeParams = $allParams;
            unset($removeParams['languages']);
            $filterTags[] = ['label' => 'Language: ' . $langName, 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if ($request->has('maintainedBy') && $request->get('maintainedBy') !== '') {
            $mbId = $request->get('maintainedBy');
            $mbName = isset($maintainedByFacets[$mbId]) ? $maintainedByFacets[$mbId]['name'] : $mbId;
            $removeParams = $allParams;
            unset($removeParams['maintainedBy']);
            $filterTags[] = ['label' => 'Maintained by: ' . $mbName, 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if ($request->has('occupation') && $request->get('occupation') !== '') {
            $occId = $request->get('occupation');
            $occName = isset($occupationFacets[$occId]) ? $occupationFacets[$occId]['name'] : $occId;
            $removeParams = $allParams;
            unset($removeParams['occupation']);
            $filterTags[] = ['label' => 'Occupation: ' . $occName, 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if ($request->has('place') && $request->get('place') !== '') {
            $placeId = $request->get('place');
            $placeName = isset($placeFacets[$placeId]) ? $placeFacets[$placeId]['name'] : $placeId;
            $removeParams = $allParams;
            unset($removeParams['place']);
            $filterTags[] = ['label' => 'Place: ' . $placeName, 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if ($request->has('subject') && $request->get('subject') !== '') {
            $subId = $request->get('subject');
            $subName = isset($subjectFacets[$subId]) ? $subjectFacets[$subId]['name'] : $subId;
            $removeParams = $allParams;
            unset($removeParams['subject']);
            $filterTags[] = ['label' => 'Subject: ' . $subName, 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }
        if ($request->has('mediaType') && $request->get('mediaType') !== '') {
            $mtId = $request->get('mediaType');
            $mtName = isset($mediaTypeFacets[$mtId]) ? $mediaTypeFacets[$mtId]['name'] : $mtId;
            $removeParams = $allParams;
            unset($removeParams['mediaType']);
            $filterTags[] = ['label' => 'Media type: ' . $mtName, 'removeUrl' => $baseUrl . '?' . http_build_query($removeParams)];
        }

        return view('ahg-actor-manage::browse', [
            'pager' => $pager,
            'entityTypeNames' => $result['entityTypeNames'] ?? [],
            'entityTypeFacets' => $entityTypeFacets,
            'totalCount' => $totalCount,
            'repositories' => $repositories,
            'maintainedByFacets' => $maintainedByFacets,
            'occupationFacets' => $occupationFacets,
            'placeFacets' => $placeFacets,
            'subjectFacets' => $subjectFacets,
            'mediaTypeFacets' => $mediaTypeFacets,
            'languageFacets' => $languageFacets,
            'filterTags' => $filterTags,
            'params' => $params,
            'sortOptions' => [
                'lastUpdated' => 'Date modified',
                'alphabetic' => 'Name',
                'identifier' => 'Identifier',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $actor = $this->service->getBySlug($slug);
        if (!$actor) {
            abort(404);
        }

        $entityTypeName = $this->service->getEntityTypeName($actor->entity_type_id);
        $otherNames = $this->service->getOtherNames($actor->id);
        $contacts = $this->service->getContacts($actor->id);
        $events = $this->service->getEvents($actor->id);
        $relatedActors = $this->service->getRelatedActors($actor->id);
        $relatedResources = $this->service->getRelatedResources($actor->id);
        $digitalObjects = $this->service->getDigitalObjects($actor->id);
        $maintenanceNotes = $this->service->getMaintenanceNotes($actor->id);
        $subjects = $this->service->getSubjectAccessPoints($actor->id);
        $places = $this->service->getPlaceAccessPoints($actor->id);
        $occupations = $this->service->getOccupations($actor->id);

        // Resolve description status/detail names
        $descriptionStatusName = $this->service->getEntityTypeName($actor->description_status_id);
        $descriptionDetailName = $this->service->getEntityTypeName($actor->description_detail_id);

        // Resolve name type names
        $nameTypeIds = $otherNames->pluck('type_id')->filter()->unique()->values()->toArray();
        $nameTypeNames = $this->service->getNameTypeNames($nameTypeIds);

        // Resolve relation type names and category names
        $relationTypeIds = collect($relatedActors)->pluck('type_id')->filter()->unique()->values()->toArray();
        $relationTypeNames = $this->service->getRelationTypeNames($relationTypeIds);
        $relationCategoryNames = $this->service->getRelationCategoryNames($relationTypeIds);
        $converseRelationTypeNames = $this->service->getConverseRelationTypeNames($relationTypeIds);

        // Language(s) and Script(s) from property table
        $languages = $this->service->getLanguages($actor->id);
        $scripts = $this->service->getScripts($actor->id);

        // Maintaining repository
        $maintainingRepository = $this->service->getMaintainingRepository($actor->id);

        // Related functions (may not exist in all installs)
        $relatedFunctions = collect();
        try {
            $relatedFunctions = $this->service->getRelatedFunctions($actor->id);
        } catch (\Exception $e) {
            // function_object table may not exist
        }

        // AHG extended: completeness, external identifiers, structured occupations
        $completeness = $this->service->getActorCompleteness($actor->id);
        $externalIdentifiers = $this->service->getActorIdentifiers($actor->id);
        $structuredOccupations = $this->service->getActorOccupations($actor->id);

        return view('ahg-actor-manage::show', [
            'actor' => $actor,
            'entityTypeName' => $entityTypeName,
            'otherNames' => $otherNames,
            'nameTypeNames' => $nameTypeNames,
            'contacts' => $contacts,
            'events' => $events,
            'relatedActors' => $relatedActors,
            'relationTypeNames' => $relationTypeNames,
            'relationCategoryNames' => $relationCategoryNames,
            'converseRelationTypeNames' => $converseRelationTypeNames,
            'relatedResources' => $relatedResources,
            'relatedFunctions' => $relatedFunctions,
            'digitalObjects' => $digitalObjects,
            'maintenanceNotes' => $maintenanceNotes,
            'descriptionStatusName' => $descriptionStatusName,
            'descriptionDetailName' => $descriptionDetailName,
            'subjects' => $subjects,
            'places' => $places,
            'occupations' => $occupations,
            'languages' => $languages,
            'scripts' => $scripts,
            'maintainingRepository' => $maintainingRepository,
            'completeness' => $completeness,
            'externalIdentifiers' => $externalIdentifiers,
            'structuredOccupations' => $structuredOccupations,
        ]);
    }

    /**
     * Print-friendly view for an authority record.
     */
    public function print(string $slug)
    {
        $actor = $this->service->getBySlug($slug);
        if (!$actor) {
            abort(404);
        }

        $entityTypeName = $this->service->getEntityTypeName($actor->entity_type_id);
        $otherNames = $this->service->getOtherNames($actor->id);
        $contacts = $this->service->getContacts($actor->id);
        $events = $this->service->getEvents($actor->id);
        $relatedActors = $this->service->getRelatedActors($actor->id);
        $relatedResources = $this->service->getRelatedResources($actor->id);
        $maintenanceNotes = $this->service->getMaintenanceNotes($actor->id);
        $subjects = $this->service->getSubjectAccessPoints($actor->id);
        $places = $this->service->getPlaceAccessPoints($actor->id);
        $occupations = $this->service->getOccupations($actor->id);

        $descriptionStatusName = $this->service->getEntityTypeName($actor->description_status_id);
        $descriptionDetailName = $this->service->getEntityTypeName($actor->description_detail_id);

        $nameTypeIds = $otherNames->pluck('type_id')->filter()->unique()->values()->toArray();
        $nameTypeNames = $this->service->getNameTypeNames($nameTypeIds);

        $relationTypeIds = collect($relatedActors)->pluck('type_id')->filter()->unique()->values()->toArray();
        $relationTypeNames = $this->service->getRelationTypeNames($relationTypeIds);
        $relationCategoryNames = $this->service->getRelationCategoryNames($relationTypeIds);
        $converseRelationTypeNames = $this->service->getConverseRelationTypeNames($relationTypeIds);

        $languages = $this->service->getLanguages($actor->id);
        $scripts = $this->service->getScripts($actor->id);
        $maintainingRepository = $this->service->getMaintainingRepository($actor->id);

        $relatedFunctions = collect();
        try {
            $relatedFunctions = $this->service->getRelatedFunctions($actor->id);
        } catch (\Exception $e) {}

        // AHG extended: completeness, external identifiers, structured occupations
        $completeness = $this->service->getActorCompleteness($actor->id);
        $externalIdentifiers = $this->service->getActorIdentifiers($actor->id);
        $structuredOccupations = $this->service->getActorOccupations($actor->id);

        return view('ahg-actor-manage::print', [
            'actor' => $actor,
            'entityTypeName' => $entityTypeName,
            'otherNames' => $otherNames,
            'nameTypeNames' => $nameTypeNames,
            'contacts' => $contacts,
            'events' => $events,
            'relatedActors' => $relatedActors,
            'relationTypeNames' => $relationTypeNames,
            'relationCategoryNames' => $relationCategoryNames,
            'converseRelationTypeNames' => $converseRelationTypeNames,
            'relatedResources' => $relatedResources,
            'relatedFunctions' => $relatedFunctions,
            'maintenanceNotes' => $maintenanceNotes,
            'descriptionStatusName' => $descriptionStatusName,
            'descriptionDetailName' => $descriptionDetailName,
            'subjects' => $subjects,
            'places' => $places,
            'occupations' => $occupations,
            'languages' => $languages,
            'scripts' => $scripts,
            'maintainingRepository' => $maintainingRepository,
            'completeness' => $completeness,
            'externalIdentifiers' => $externalIdentifiers,
            'structuredOccupations' => $structuredOccupations,
        ]);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        return view('ahg-actor-manage::edit', [
            'actor' => null,
            'contacts' => collect(),
            'otherNames' => collect(),
            'maintenanceNotes' => null,
            'formChoices' => $formChoices,
            'externalIdentifiers' => collect(),
            'structuredOccupations' => collect(),
        ]);
    }

    public function edit(string $slug)
    {
        $actor = $this->service->getBySlug($slug);
        if (!$actor) {
            abort(404);
        }

        $contacts = $this->service->getContacts($actor->id);
        $otherNames = $this->service->getOtherNames($actor->id);
        $maintenanceNotes = $this->service->getMaintenanceNotes($actor->id);
        $formChoices = $this->service->getFormChoices();
        $externalIdentifiers = $this->service->getActorIdentifiers($actor->id);
        $structuredOccupations = $this->service->getActorOccupations($actor->id);

        return view('ahg-actor-manage::edit', [
            'actor' => $actor,
            'contacts' => $contacts,
            'otherNames' => $otherNames,
            'maintenanceNotes' => $maintenanceNotes,
            'formChoices' => $formChoices,
            'externalIdentifiers' => $externalIdentifiers,
            'structuredOccupations' => $structuredOccupations,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'authorized_form_of_name' => 'required|string|max:1024',
            'entity_type_id' => 'required|integer|exists:term,id',
            'dates_of_existence' => 'nullable|string|max:1024',
            'description_identifier' => 'nullable|string|max:1024',
            'corporate_body_identifiers' => 'nullable|string|max:1024',
            'source_standard' => 'nullable|string|max:1024',
            'description_status_id' => 'nullable|integer',
            'description_detail_id' => 'nullable|integer',
            'institution_responsible_identifier' => 'nullable|string|max:1024',
            'history' => 'nullable|string',
            'places' => 'nullable|string',
            'legal_status' => 'nullable|string',
            'functions' => 'nullable|string',
            'mandates' => 'nullable|string',
            'internal_structures' => 'nullable|string',
            'general_context' => 'nullable|string',
            'rules' => 'nullable|string',
            'sources' => 'nullable|string',
            'revision_history' => 'nullable|string',
            'maintenance_notes' => 'nullable|string',
            'contacts' => 'nullable|array',
            'contacts.*.contact_person' => 'nullable|string|max:1024',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.telephone' => 'nullable|string|max:255',
            'contacts.*.website' => 'nullable|url|max:1024',
            // Extended contact fields
            'contacts.*.title' => 'nullable|string|max:100',
            'contacts.*.role' => 'nullable|string|max:255',
            'contacts.*.department' => 'nullable|string|max:255',
            'contacts.*.cell' => 'nullable|string|max:255',
            'contacts.*.id_number' => 'nullable|string|max:50',
            'contacts.*.alternative_email' => 'nullable|email|max:255',
            'contacts.*.alternative_phone' => 'nullable|string|max:255',
            'contacts.*.preferred_contact_method' => 'nullable|string|max:35',
            'contacts.*.language_preference' => 'nullable|string|max:16',
            'contacts.*.extended_notes' => 'nullable|string',
            'other_names' => 'nullable|array',
            'other_names.*.name' => 'nullable|string|max:1024',
            'other_names.*.type_id' => 'nullable|integer',
        ]);

        $data = $request->only([
            'authorized_form_of_name', 'entity_type_id', 'dates_of_existence',
            'description_identifier', 'corporate_body_identifiers', 'source_standard',
            'description_status_id', 'description_detail_id',
            'institution_responsible_identifier', 'history', 'places', 'legal_status',
            'functions', 'mandates', 'internal_structures', 'general_context',
            'rules', 'sources', 'revision_history', 'maintenance_notes',
            'contacts', 'other_names',
        ]);

        $id = $this->service->create($data);

        // Save external identifiers if provided
        if ($request->has('external_identifiers')) {
            $this->service->saveActorIdentifiers($id, $request->input('external_identifiers', []));
        }

        // Save structured occupations if provided
        if ($request->has('structured_occupations')) {
            $this->service->saveActorOccupations($id, $request->input('structured_occupations', []));
        }

        // Calculate and store completeness score
        $this->service->saveActorCompleteness($id);

        $slug = $this->service->getSlug($id);

        return redirect()
            ->route('actor.show', $slug)
            ->with('success', 'Authority record created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $actor = $this->service->getBySlug($slug);
        if (!$actor) {
            abort(404);
        }

        $request->validate([
            'authorized_form_of_name' => 'required|string|max:1024',
            'entity_type_id' => 'required|integer|exists:term,id',
            'dates_of_existence' => 'nullable|string|max:1024',
            'description_identifier' => 'nullable|string|max:1024',
            'corporate_body_identifiers' => 'nullable|string|max:1024',
            'source_standard' => 'nullable|string|max:1024',
            'description_status_id' => 'nullable|integer',
            'description_detail_id' => 'nullable|integer',
            'institution_responsible_identifier' => 'nullable|string|max:1024',
            'history' => 'nullable|string',
            'places' => 'nullable|string',
            'legal_status' => 'nullable|string',
            'functions' => 'nullable|string',
            'mandates' => 'nullable|string',
            'internal_structures' => 'nullable|string',
            'general_context' => 'nullable|string',
            'rules' => 'nullable|string',
            'sources' => 'nullable|string',
            'revision_history' => 'nullable|string',
            'maintenance_notes' => 'nullable|string',
            'contacts' => 'nullable|array',
            'contacts.*.contact_person' => 'nullable|string|max:1024',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.telephone' => 'nullable|string|max:255',
            'contacts.*.website' => 'nullable|url|max:1024',
            // Extended contact fields
            'contacts.*.title' => 'nullable|string|max:100',
            'contacts.*.role' => 'nullable|string|max:255',
            'contacts.*.department' => 'nullable|string|max:255',
            'contacts.*.cell' => 'nullable|string|max:255',
            'contacts.*.id_number' => 'nullable|string|max:50',
            'contacts.*.alternative_email' => 'nullable|email|max:255',
            'contacts.*.alternative_phone' => 'nullable|string|max:255',
            'contacts.*.preferred_contact_method' => 'nullable|string|max:35',
            'contacts.*.language_preference' => 'nullable|string|max:16',
            'contacts.*.extended_notes' => 'nullable|string',
            'other_names' => 'nullable|array',
            'other_names.*.name' => 'nullable|string|max:1024',
            'other_names.*.type_id' => 'nullable|integer',
        ]);

        $data = $request->only([
            'authorized_form_of_name', 'entity_type_id', 'dates_of_existence',
            'description_identifier', 'corporate_body_identifiers', 'source_standard',
            'description_status_id', 'description_detail_id',
            'institution_responsible_identifier', 'history', 'places', 'legal_status',
            'functions', 'mandates', 'internal_structures', 'general_context',
            'rules', 'sources', 'revision_history', 'maintenance_notes',
            'contacts', 'other_names',
        ]);

        $this->service->update($actor->id, $data);

        // Save external identifiers if provided
        if ($request->has('external_identifiers')) {
            $this->service->saveActorIdentifiers($actor->id, $request->input('external_identifiers', []));
        }

        // Save structured occupations if provided
        if ($request->has('structured_occupations')) {
            $this->service->saveActorOccupations($actor->id, $request->input('structured_occupations', []));
        }

        // Recalculate completeness score
        $this->service->saveActorCompleteness($actor->id);

        return redirect()
            ->route('actor.show', $slug)
            ->with('success', 'Authority record updated successfully.');
    }

    public function rename(string $slug)
    {
        $actor = $this->service->getBySlug($slug);
        if (!$actor) {
            abort(404);
        }

        // Get the first digital object linked to this actor (if any)
        $digitalObject = DB::table('digital_object')
            ->where('object_id', $actor->id)
            ->first();

        return view('ahg-actor-manage::rename', [
            'actor' => $actor,
            'digitalObject' => $digitalObject,
        ]);
    }

    public function processRename(Request $request, string $slug)
    {
        $actor = $this->service->getBySlug($slug);
        if (!$actor) {
            abort(404);
        }

        $request->validate([
            'authorized_form_of_name' => 'nullable|string|max:1024',
            'slug' => 'nullable|string|max:255',
            'filename' => 'nullable|string|max:1024',
        ]);

        $newSlug = $slug;

        DB::transaction(function () use ($request, $actor, &$newSlug) {
            $culture = app()->getLocale();

            // Update authorized form of name
            if ($request->has('update_name') && $request->filled('authorized_form_of_name')) {
                DB::table('actor_i18n')
                    ->where('id', $actor->id)
                    ->where('culture', $culture)
                    ->update(['authorized_form_of_name' => $request->input('authorized_form_of_name')]);
            }

            // Update slug
            if ($request->has('update_slug') && $request->filled('slug')) {
                $desiredSlug = \Illuminate\Support\Str::slug($request->input('slug'));
                if (empty($desiredSlug)) {
                    $desiredSlug = 'untitled';
                }

                // Check for duplicate slugs (excluding the current actor)
                $existingSlug = DB::table('slug')
                    ->where('slug', $desiredSlug)
                    ->where('object_id', '!=', $actor->id)
                    ->exists();

                if ($existingSlug) {
                    // Pad with a number to make unique
                    $counter = 2;
                    while (DB::table('slug')->where('slug', $desiredSlug . '-' . $counter)->exists()) {
                        $counter++;
                    }
                    $desiredSlug = $desiredSlug . '-' . $counter;
                }

                DB::table('slug')
                    ->where('object_id', $actor->id)
                    ->update(['slug' => $desiredSlug]);

                $newSlug = $desiredSlug;
            }

            // Update filename
            if ($request->has('update_filename') && $request->filled('filename')) {
                $filename = $request->input('filename');
                // Sanitize filename: keep only lowercase alphanumeric, dashes
                $filename = preg_replace('/[^a-z0-9\-]/', '', strtolower($filename));
                if (empty($filename)) {
                    $filename = 'untitled';
                }

                DB::table('digital_object')
                    ->where('object_id', $actor->id)
                    ->update(['name' => $filename]);
            }
        });

        return redirect()
            ->route('actor.show', $newSlug)
            ->with('success', 'Authority record renamed successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $actor = $this->service->getBySlug($slug);
        if (!$actor) {
            abort(404);
        }

        return view('ahg-actor-manage::delete', [
            'actor' => $actor,
        ]);
    }

    public function destroy(Request $request, string $slug)
    {
        $actor = $this->service->getBySlug($slug);
        if (!$actor) {
            abort(404);
        }

        $this->service->delete($actor->id);

        return redirect()
            ->route('actor.browse')
            ->with('success', 'Authority record deleted successfully.');
    }

    public function autocomplete(Request $request)
    {
        $query = $request->get('query', '');
        $culture = app()->getLocale();
        $limit = (int) $request->get('limit', 10);

        $results = DB::table('actor')
            ->join('actor_i18n', function ($j) use ($culture) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                  ->where('actor_i18n.culture', '=', $culture);
            })
            ->join('slug', 'slug.object_id', '=', 'actor.id')
            ->where('actor_i18n.authorized_form_of_name', 'LIKE', '%' . $query . '%')
            ->select(
                'actor.id',
                'actor_i18n.authorized_form_of_name as name',
                'slug.slug'
            )
            ->limit($limit)
            ->get();

        return response()->json($results);
    }

    // =========================================================================
    // DASHBOARD & WORKQUEUE
    // =========================================================================

    public function dashboard()
    {
        $completenessService = new AuthorityCompletenessService();
        $identifierService = new AuthorityIdentifierService();

        $stats = $completenessService->getDashboardStats();
        $identifierStats = $identifierService->getStats();

        return view('ahg-actor-manage::authority.dashboard', [
            'stats' => $stats,
            'identifierStats' => $identifierStats,
        ]);
    }

    public function workqueue(Request $request)
    {
        $completenessService = new AuthorityCompletenessService();

        $filters = [
            'level'       => $request->get('level', ''),
            'assigned_to' => $request->get('assigned_to', ''),
            'unassigned'  => $request->get('unassigned', ''),
            'min_score'   => $request->get('min_score', ''),
            'max_score'   => $request->get('max_score', ''),
            'sort'        => $request->get('sort', 'completeness_score'),
            'sortDir'     => $request->get('sortDir', 'asc'),
            'page'        => $request->get('page', 1),
            'limit'       => $request->get('limit', 50),
        ];

        $workqueue = $completenessService->getWorkqueue($filters);
        $levels = array_keys(AuthorityCompletenessService::LEVELS);

        $users = DB::table('user')
            ->join('actor_i18n', function ($j) {
                $j->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->select('user.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get()
            ->all();

        return view('ahg-actor-manage::authority.workqueue', [
            'workqueue' => $workqueue,
            'filters' => $filters,
            'levels' => $levels,
            'users' => $users,
        ]);
    }

    // =========================================================================
    // EXTERNAL IDENTIFIERS
    // =========================================================================

    public function identifiers(int $actorId)
    {
        $identifierService = new AuthorityIdentifierService();

        $actor = $this->getActorById($actorId);
        if (!$actor) {
            abort(404);
        }

        $identifiers = $identifierService->getIdentifiers($actorId);
        $uriPatterns = AuthorityIdentifierService::URI_PATTERNS;

        return view('ahg-actor-manage::authority.identifiers', [
            'actor' => $actor,
            'identifiers' => $identifiers,
            'uriPatterns' => $uriPatterns,
        ]);
    }

    public function apiIdentifierSave(Request $request)
    {
        $identifierService = new AuthorityIdentifierService();

        $actorId = (int) $request->input('actor_id');
        $data = [
            'identifier_type'  => $request->input('identifier_type', ''),
            'identifier_value' => $request->input('identifier_value', ''),
            'uri'              => $request->input('uri') ?: null,
            'label'            => $request->input('label') ?: null,
            'source'           => $request->input('source', 'manual'),
        ];

        $id = $identifierService->save($actorId, $data);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function apiIdentifierDelete(int $id)
    {
        $identifierService = new AuthorityIdentifierService();
        $result = $identifierService->delete($id);

        return response()->json(['success' => $result]);
    }

    public function apiIdentifierVerify(int $id)
    {
        $identifierService = new AuthorityIdentifierService();
        $userId = (int) auth()->id();
        $result = $identifierService->verify($id, $userId);

        return response()->json(['success' => $result]);
    }

    // =========================================================================
    // EXTERNAL AUTHORITY LOOKUP
    // =========================================================================

    public function apiWikidataSearch(Request $request)
    {
        $lookupService = new AuthorityLookupService();
        $result = $lookupService->searchWikidata($request->get('q', ''));

        return response()->json($result);
    }

    public function apiViafSearch(Request $request)
    {
        $lookupService = new AuthorityLookupService();
        $result = $lookupService->searchViaf($request->get('q', ''));

        return response()->json($result);
    }

    public function apiUlanSearch(Request $request)
    {
        $lookupService = new AuthorityLookupService();
        $result = $lookupService->searchUlan($request->get('q', ''));

        return response()->json($result);
    }

    public function apiLcnafSearch(Request $request)
    {
        $lookupService = new AuthorityLookupService();
        $result = $lookupService->searchLcnaf($request->get('q', ''));

        return response()->json($result);
    }

    // =========================================================================
    // COMPLETENESS
    // =========================================================================

    public function apiCompletenessRecalc(int $actorId)
    {
        $completenessService = new AuthorityCompletenessService();
        $result = $completenessService->calculateScore($actorId);

        return response()->json(['success' => true, 'result' => $result]);
    }

    public function apiCompletenessBatchAssign(Request $request)
    {
        $completenessService = new AuthorityCompletenessService();

        $actorIds = $request->input('actor_ids', []);
        $assigneeId = (int) $request->input('assignee_id');

        if (!is_array($actorIds)) {
            $actorIds = explode(',', $actorIds);
        }
        $actorIds = array_map('intval', $actorIds);

        $count = $completenessService->batchAssign($actorIds, $assigneeId);

        return response()->json(['success' => true, 'count' => $count]);
    }

    // =========================================================================
    // RELATIONSHIP GRAPH
    // =========================================================================

    public function apiGraphData(Request $request, int $actorId)
    {
        $graphService = new AuthorityGraphService();

        $depth = (int) $request->get('depth', 1);
        $depth = min($depth, 3);

        $data = $graphService->getGraphData($actorId, $depth);

        return response()->json($data);
    }

    // =========================================================================
    // MERGE / SPLIT
    // =========================================================================

    public function merge(int $id)
    {
        $mergeService = new AuthorityMergeService();

        $actor = $this->getActorById($id);
        if (!$actor) {
            abort(404);
        }

        $mergeHistory = $mergeService->getMergeHistory($id);

        return view('ahg-actor-manage::authority.merge', [
            'actor' => $actor,
            'mergeHistory' => $mergeHistory,
        ]);
    }

    public function split(int $id)
    {
        $actor = $this->getActorById($id);
        if (!$actor) {
            abort(404);
        }

        return view('ahg-actor-manage::authority.split', [
            'actor' => $actor,
        ]);
    }

    public function apiMergePreview(Request $request)
    {
        $mergeService = new AuthorityMergeService();

        $primaryId = (int) $request->input('primary_id');
        $secondaryId = (int) $request->input('secondary_id');

        $comparison = $mergeService->compareActors($primaryId, $secondaryId);

        return response()->json(['success' => true, 'comparison' => $comparison]);
    }

    public function apiMergeExecute(Request $request)
    {
        $mergeService = new AuthorityMergeService();

        $primaryId = (int) $request->input('primary_id');
        $secondaryIds = $request->input('secondary_ids', []);
        $fieldChoices = $request->input('field_choices', []);
        $notes = $request->input('notes', '');
        $userId = (int) auth()->id();

        if (!is_array($secondaryIds)) {
            $secondaryIds = explode(',', $secondaryIds);
        }
        $secondaryIds = array_map('intval', $secondaryIds);

        $mergeId = $mergeService->createMergeRequest(
            $primaryId,
            $secondaryIds,
            is_array($fieldChoices) ? $fieldChoices : [],
            $userId,
            $notes
        );

        return response()->json(['success' => true, 'merge_id' => $mergeId]);
    }

    public function apiSplitExecute(Request $request)
    {
        $mergeService = new AuthorityMergeService();

        $sourceId = (int) $request->input('source_id');
        $fieldsToMove = $request->input('fields_to_move', []);
        $relationsToMove = $request->input('relations_to_move', []);
        $notes = $request->input('notes', '');
        $userId = (int) auth()->id();

        $splitId = $mergeService->createSplitRequest(
            $sourceId,
            is_array($fieldsToMove) ? $fieldsToMove : [],
            is_array($relationsToMove) ? $relationsToMove : [],
            $userId,
            $notes
        );

        return response()->json(['success' => true, 'split_id' => $splitId]);
    }

    // =========================================================================
    // OCCUPATIONS
    // =========================================================================

    public function occupations(int $actorId)
    {
        $occupationService = new AuthorityOccupationService();

        $actor = $this->getActorById($actorId);
        if (!$actor) {
            abort(404);
        }

        $occupations = $occupationService->getOccupations($actorId);

        return view('ahg-actor-manage::authority.occupations', [
            'actor' => $actor,
            'occupations' => $occupations,
        ]);
    }

    public function apiOccupationSave(Request $request)
    {
        $occupationService = new AuthorityOccupationService();

        $actorId = (int) $request->input('actor_id');
        $occupationId = (int) $request->input('occupation_id', 0);

        $data = [
            'term_id'         => $request->input('term_id') ?: null,
            'occupation_text' => $request->input('occupation_text', ''),
            'date_from'       => $request->input('date_from') ?: null,
            'date_to'         => $request->input('date_to') ?: null,
            'notes'           => $request->input('notes', ''),
            'sort_order'      => (int) $request->input('sort_order', 0),
        ];

        $id = $occupationService->save($actorId, $data, $occupationId);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function apiOccupationDelete(int $id)
    {
        $occupationService = new AuthorityOccupationService();
        $result = $occupationService->delete($id);

        return response()->json(['success' => $result]);
    }

    // =========================================================================
    // FUNCTIONS
    // =========================================================================

    public function functions(int $actorId)
    {
        $functionService = new AuthorityFunctionService();

        $actor = $this->getActorById($actorId);
        if (!$actor) {
            abort(404);
        }

        $functionLinks = $functionService->getFunctionLinks($actorId);
        $relationTypes = AuthorityFunctionService::RELATION_TYPES;

        return view('ahg-actor-manage::authority.functions', [
            'actor' => $actor,
            'functionLinks' => $functionLinks,
            'relationTypes' => $relationTypes,
        ]);
    }

    public function functionBrowse()
    {
        $functionService = new AuthorityFunctionService();
        $functions = $functionService->browseFunctions();

        return view('ahg-actor-manage::authority.function-browse', [
            'functions' => $functions,
        ]);
    }

    public function apiFunctionSave(Request $request)
    {
        $functionService = new AuthorityFunctionService();

        $actorId = (int) $request->input('actor_id');
        $linkId = (int) $request->input('link_id', 0);

        $data = [
            'function_id'   => $request->input('function_id'),
            'relation_type' => $request->input('relation_type', 'responsible'),
            'date_from'     => $request->input('date_from') ?: null,
            'date_to'       => $request->input('date_to') ?: null,
            'notes'         => $request->input('notes', ''),
            'sort_order'    => (int) $request->input('sort_order', 0),
        ];

        $id = $functionService->save($actorId, $data, $linkId);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function apiFunctionDelete(int $id)
    {
        $functionService = new AuthorityFunctionService();
        $result = $functionService->delete($id);

        return response()->json(['success' => $result]);
    }

    // =========================================================================
    // DEDUPLICATION
    // =========================================================================

    public function dedupIndex()
    {
        $dedupeService = new AuthorityDedupeService();
        $stats = $dedupeService->getStats();

        return view('ahg-actor-manage::authority.dedup-index', [
            'stats' => $stats,
        ]);
    }

    public function dedupScan(Request $request)
    {
        $dedupeService = new AuthorityDedupeService();
        $pairs = [];

        if ($request->isMethod('post')) {
            $limit = (int) $request->input('limit', 500);
            $pairs = $dedupeService->scan($limit);
        }

        return view('ahg-actor-manage::authority.dedup-scan', [
            'pairs' => $pairs,
        ]);
    }

    public function dedupCompare(Request $request, int $id)
    {
        $mergeService = new AuthorityMergeService();

        $secondaryId = (int) $request->get('secondary_id');
        if (!$secondaryId) {
            abort(404);
        }

        $comparison = $mergeService->compareActors($id, $secondaryId);

        return view('ahg-actor-manage::authority.dedup-compare', [
            'comparison' => $comparison,
        ]);
    }

    public function apiDedupDismiss(int $id)
    {
        $mergeService = new AuthorityMergeService();
        $merge = $mergeService->getMerge($id);

        if ($merge) {
            DB::table('ahg_actor_merge')
                ->where('id', $id)
                ->update(['status' => 'rejected']);
        }

        return response()->json(['success' => true]);
    }

    public function apiDedupMerge(int $id)
    {
        $mergeService = new AuthorityMergeService();
        $userId = (int) auth()->id();
        $result = $mergeService->executeMerge($id, $userId);

        return response()->json(['success' => $result]);
    }

    // =========================================================================
    // NER PIPELINE
    // =========================================================================

    public function nerIndex(Request $request)
    {
        $nerService = new AuthorityNerPipelineService();
        $stats = $nerService->getStats();

        $filters = [
            'status'      => $request->get('status', 'stub'),
            'entity_type' => $request->get('entity_type', ''),
            'search'      => $request->get('search', ''),
            'sort'        => $request->get('sort', 's.created_at'),
            'sortDir'     => $request->get('sortDir', 'desc'),
            'page'        => $request->get('page', 1),
            'limit'       => $request->get('limit', 50),
        ];

        $stubs = [];
        try {
            $stubs = $nerService->getStubs($filters);
        } catch (\Exception $e) {
            // ahg_ner_authority_stub table may not exist yet
        }

        $pendingFilters = [
            'entity_type'    => $request->get('entity_type', ''),
            'min_confidence' => $request->get('min_confidence', ''),
            'search'         => $request->get('search', ''),
            'sort'           => 'ne.confidence',
            'sortDir'        => 'desc',
            'page'           => 1,
            'limit'          => 20,
        ];

        $pendingEntities = [];
        try {
            $pendingEntities = $nerService->getPendingEntities($pendingFilters);
        } catch (\Exception $e) {
            // ner_entity table may not exist yet
        }

        return view('ahg-actor-manage::authority.ner-index', [
            'stats' => $stats,
            'stubs' => $stubs,
            'pendingEntities' => $pendingEntities,
            'filters' => $filters,
        ]);
    }

    public function apiNerCreateStub(Request $request)
    {
        $nerService = new AuthorityNerPipelineService();
        $userId = (int) auth()->id();
        $nerEntityId = (int) $request->input('ner_entity_id');

        $actorId = $nerService->createStub($nerEntityId, $userId);

        if ($actorId) {
            return response()->json(['success' => true, 'actor_id' => $actorId]);
        }

        return response()->json(['success' => false, 'error' => 'Failed to create stub']);
    }

    public function apiNerPromote(int $id)
    {
        $nerService = new AuthorityNerPipelineService();
        $userId = (int) auth()->id();
        $result = $nerService->promoteStub($id, $userId);

        return response()->json(['success' => $result]);
    }

    public function apiNerReject(int $id)
    {
        $nerService = new AuthorityNerPipelineService();
        $userId = (int) auth()->id();
        $result = $nerService->rejectStub($id, $userId);

        return response()->json(['success' => $result]);
    }

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    public function config(Request $request)
    {
        if ($request->isMethod('post')) {
            $settings = $request->input('config', []);
            foreach ($settings as $key => $value) {
                DB::table('ahg_authority_config')
                    ->updateOrInsert(
                        ['config_key' => $key],
                        ['config_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
                    );
            }

            return redirect()->route('actor.config')->with('success', 'Configuration saved.');
        }

        $config = DB::table('ahg_authority_config')
            ->get()
            ->keyBy('config_key')
            ->all();

        return view('ahg-actor-manage::authority.config', [
            'config' => $config,
        ]);
    }

    // =========================================================================
    // HELPER
    // =========================================================================

    protected function getActorById(int $actorId): ?object
    {
        return DB::table('actor as a')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('a.id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', $actorId)
            ->select('a.id', 'a.entity_type_id', 'ai.authorized_form_of_name as name', 'slug.slug')
            ->first();
    }
}
