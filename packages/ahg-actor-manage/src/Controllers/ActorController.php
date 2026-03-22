<?php

namespace AhgActorManage\Controllers;

use AhgActorManage\Services\ActorBrowseService;
use AhgActorManage\Services\ActorService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
            'subquery' => $request->get('subquery', ''),
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
            'params' => $params,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
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
}
