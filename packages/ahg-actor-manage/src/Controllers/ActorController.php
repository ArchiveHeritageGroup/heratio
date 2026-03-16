<?php

namespace AhgActorManage\Controllers;

use AhgActorManage\Services\ActorBrowseService;
use AhgActorManage\Services\ActorService;
use AhgCore\Pagination\SimplePager;
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

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-actor-manage::browse', [
            'pager' => $pager,
            'entityTypeNames' => $result['entityTypeNames'] ?? [],
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

        // Resolve relation type names
        $relationTypeIds = collect($relatedActors)->pluck('type_id')->filter()->unique()->values()->toArray();
        $relationTypeNames = $this->service->getRelationTypeNames($relationTypeIds);

        // Related functions (may not exist in all installs)
        $relatedFunctions = collect();
        try {
            $relatedFunctions = $this->service->getRelatedFunctions($actor->id);
        } catch (\Exception $e) {
            // function_object table may not exist
        }

        return view('ahg-actor-manage::show', [
            'actor' => $actor,
            'entityTypeName' => $entityTypeName,
            'otherNames' => $otherNames,
            'nameTypeNames' => $nameTypeNames,
            'contacts' => $contacts,
            'events' => $events,
            'relatedActors' => $relatedActors,
            'relationTypeNames' => $relationTypeNames,
            'relatedResources' => $relatedResources,
            'relatedFunctions' => $relatedFunctions,
            'digitalObjects' => $digitalObjects,
            'maintenanceNotes' => $maintenanceNotes,
            'descriptionStatusName' => $descriptionStatusName,
            'descriptionDetailName' => $descriptionDetailName,
            'subjects' => $subjects,
            'places' => $places,
            'occupations' => $occupations,
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

        return view('ahg-actor-manage::edit', [
            'actor' => $actor,
            'contacts' => $contacts,
            'otherNames' => $otherNames,
            'maintenanceNotes' => $maintenanceNotes,
            'formChoices' => $formChoices,
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
