<?php

/**
 * ExhibitionController - Controller for Heratio
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

namespace AhgExhibition\Controllers;

use AhgExhibition\Services\ExhibitionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExhibitionController extends Controller
{
    protected ExhibitionService $service;

    public function __construct()
    {
        $this->service = new ExhibitionService(app()->getLocale());
    }

    public function index(Request $request)
    {
        $filters = array_filter([
            'status' => $request->get('status'),
            'exhibition_type' => $request->get('type'),
            'year' => $request->get('year'),
            'search' => $request->get('search'),
        ]);

        $page = max(1, (int) $request->get('page', 1));
        $limit = 20;
        $result = $this->service->search($filters, $limit, ($page - 1) * $limit);

        $types = $this->service->getTypes();
        $statuses = $this->service->getStatuses();
        $stats = $this->service->getStatistics();

        // Dashboard widgets (PSIS parity: exhibition/dashboard)
        $currentExhibitions = $this->service->getCurrentExhibitions(5);
        $upcomingExhibitions = $this->service->getUpcomingExhibitions(5);
        $pendingChecklists = $this->service->getPendingChecklists(10);
        $recentActivity = $this->service->getRecentActivity(10);
        $calendarEvents = $this->service->getCalendarEvents(30, 10);

        return view('ahg-exhibition::index', [
            'exhibitions' => $result['results'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => ceil($result['total'] / $limit),
            'filters' => $filters,
            'types' => $types,
            'statuses' => $statuses,
            'stats' => $stats,
            'currentExhibitions' => $currentExhibitions,
            'upcomingExhibitions' => $upcomingExhibitions,
            'pendingChecklists' => $pendingChecklists,
            'recentActivity' => $recentActivity,
            'calendarEvents' => $calendarEvents,
        ]);
    }

    public function show(Request $request, $id)
    {
        if (is_numeric($id)) {
            $exhibition = $this->service->get((int) $id, true);
        } else {
            $ex = $this->service->getBySlug($id);
            $exhibition = $ex ? $this->service->get($ex->id, true) : null;
        }

        abort_unless($exhibition, 404, 'Exhibition not found');

        $validTransitions = $this->service->getValidTransitions($exhibition->status);
        $statuses = $this->service->getStatusesWithColors();

        return view('ahg-exhibition::show', compact('exhibition', 'validTransitions', 'statuses'));
    }

    /**
     * Move the exhibition to its next workflow status (AJAX from the
     * "Change Status" menu on the exhibition page).
     */
    public function transition(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|string|max:50',
            'reason' => 'nullable|string|max:65535',
        ]);

        try {
            $this->service->transitionStatus($id, $data['status'], auth()->id(), $data['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Form fields shared by add and edit, validated against the real
     * exhibition columns. The old forms posted start_date, end_date, venue,
     * curator, designer, budget and target_audience - none of which exist -
     * so every save failed with an SQL error.
     */
    private function formData(Request $request): array
    {
        $types = array_keys($this->service->getTypes());
        $statuses = array_keys($this->service->getStatuses());
        $currencies = array_keys($this->service->getCurrencies());

        $data = $request->validate([
            'title' => 'required|string|max:500',
            'subtitle' => 'nullable|string|max:500',
            'exhibition_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in($types)],
            'status' => ['nullable', 'string', \Illuminate\Validation\Rule::in($statuses)],
            'project_code' => 'nullable|string|max:50',
            'theme' => 'nullable|string|max:65535',
            'description' => 'nullable|string|max:65535',
            'opening_date' => 'nullable|date',
            'closing_date' => 'nullable|date|after_or_equal:opening_date',
            'venue_id' => 'nullable|integer|exists:exhibition_venue,id',
            'venue_name' => 'nullable|string|max:255',
            'curator_name' => 'nullable|string|max:255',
            'designer_name' => 'nullable|string|max:255',
            'organized_by' => 'nullable|string|max:255',
            'budget_amount' => 'nullable|numeric|min:0',
            'budget_currency' => ['nullable', 'string', 'size:3', \Illuminate\Validation\Rule::in($currencies)],
            'expected_visitors' => 'nullable|integer|min:0',
            'admission_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:65535',
        ]);
        $data['is_free_admission'] = $request->boolean('is_free_admission') ? 1 : 0;
        // The form has one currency; admission is charged in it too (the column
        // otherwise keeps its install default whatever the budget currency is).
        if (! empty($data['budget_currency'])) {
            $data['admission_currency'] = $data['budget_currency'];
        }

        // Leave the column defaults (temporary / concept) in place when unset.
        foreach (['exhibition_type', 'status', 'budget_currency'] as $k) {
            if (empty($data[$k])) {
                unset($data[$k]);
            }
        }

        return $data;
    }

    public function add(Request $request)
    {
        $types = $this->service->getTypes();
        $statuses = $this->service->getStatuses();

        $currencies = $this->service->getCurrencies();
        $venues = $this->service->getVenues();

        if ($request->isMethod('post')) {
            $id = $this->service->create($this->formData($request) + ['created_by' => auth()->id()]);

            return redirect()->route('exhibition.show', $id)->with('notice', __('Exhibition created'));
        }

        return view('ahg-exhibition::add', compact('types', 'statuses', 'currencies', 'venues'));
    }

    public function edit(Request $request, int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404, 'Exhibition not found');

        $types = $this->service->getTypes();
        $statuses = $this->service->getStatuses();

        $currencies = $this->service->getCurrencies();
        $venues = $this->service->getVenues();

        if ($request->isMethod('post')) {
            $this->service->update($id, $this->formData($request) + ['updated_by' => auth()->id()]);

            return redirect()->route('exhibition.show', $id)->with('notice', __('Exhibition updated'));
        }

        return view('ahg-exhibition::edit', compact('exhibition', 'types', 'statuses', 'currencies', 'venues'));
    }

    /**
     * The exhibition index already is the dashboard (stat cards, currently
     * open, upcoming, workflow, pending checklist items, activity, calendar),
     * so /exhibition/dashboard renders that page rather than a second copy.
     */
    public function dashboard(Request $request)
    {
        return $this->index($request);
    }

    public function objects(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        $objectStatuses = $this->service->getObjectStatuses();

        return view('ahg-exhibition::objects', compact('exhibition', 'objectStatuses'));
    }

    public function objectList(int $id)
    {
        $exhibition = $this->service->get($id);
        abort_unless($exhibition, 404);

        $objects = $this->service->generateObjectList($id);
        $sections = $this->service->getSections($id);

        return view('ahg-exhibition::object-list', compact('exhibition', 'objects', 'sections'));
    }

    public function objectListCsv(int $id)
    {
        $exhibition = $this->service->get($id);
        abort_unless($exhibition, 404);

        $csv = $this->service->exportObjectListCsv($id);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="exhibition_objects_'.$id.'.csv"',
        ]);
    }

    public function storylines(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        $narrativeTypes = $this->service->getNarrativeTypes();
        $audiences = $this->service->getTargetAudiences();

        return view('ahg-exhibition::storylines', compact('exhibition', 'narrativeTypes', 'audiences'));
    }

    public function storyline(int $exhibitionId, int $storylineId)
    {
        $exhibition = $this->service->get($exhibitionId);
        abort_unless($exhibition, 404);

        $storyline = $this->service->getStoryline($storylineId);
        abort_unless($storyline, 404);

        // #1481: the view reads $storyline->stops and nothing populated it, so
        // the stop list rendered empty on every storyline.
        $storyline->stops = $this->service->getStorylineStops($storylineId);

        // A stop must name an exhibition object (NOT NULL + FK), so the form
        // needs something to choose from.
        $objects = $this->service->get($exhibitionId, true)->objects ?? collect();

        return view('ahg-exhibition::storyline', compact('exhibition', 'storyline', 'objects'));
    }

    /**
     * Add a stop to a storyline - #1481.
     *
     * The form has existed since the view was written and posted to the GET
     * route above, so pressing "Add Stop" raised a 405 and nothing ever wrote
     * exhibition_storyline_stop.
     */
    public function storeStorylineStop(Request $request, int $exhibitionId, int $storylineId)
    {
        abort_unless($this->service->get($exhibitionId), 404);
        abort_unless($this->service->getStoryline($storylineId), 404);

        $data = $request->validate([
            'exhibition_object_id'   => 'required|integer|exists:exhibition_object,id',
            'title'                  => 'required|string|max:255',
            'narrative_text'         => 'nullable|string|max:65535',
            'audio_duration_seconds' => 'nullable|integer|min:0',
            'sequence_order'         => 'nullable|integer|min:1',
        ]);

        $this->service->createStorylineStop($storylineId, $data);

        return redirect()
            ->route('exhibition.storyline', ['exhibitionId' => $exhibitionId, 'storylineId' => $storylineId])
            ->with('success', __('Stop added.'));
    }

    public function sections(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        $sectionTypes = $this->service->getSectionTypes();

        return view('ahg-exhibition::sections', compact('exhibition', 'sectionTypes'));
    }

    public function events(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        $eventTypes = $this->service->getEventTypes();

        return view('ahg-exhibition::events', compact('exhibition', 'eventTypes'));
    }

    public function checklists(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        $templates = $this->service->getChecklistTemplates();

        return view('ahg-exhibition::checklists', compact('exhibition', 'templates'));
    }

    // Sub-page writes. The forms on the objects, sections, storylines, events
    // and checklists pages posted to their GET-only routes and raised a 405.

    private function backTo(string $route, int $id, string $message)
    {
        return redirect()->route($route, ['id' => $id])->with('notice', $message);
    }

    /** Ensure the exhibition exists; 404 otherwise. */
    private function requireExhibition(int $id): void
    {
        abort_unless($this->service->get($id), 404, __('Exhibition not found'));
    }

    /** Validation rule: a section id that belongs to this exhibition. */
    private function sectionRule(int $id): array
    {
        return ['nullable', 'integer', Rule::exists('exhibition_section', 'id')->where('exhibition_id', $id)];
    }

    // Objects

    /** JSON object picker for the Add Object dialog. */
    public function searchObjects(Request $request, int $id)
    {
        $this->requireExhibition($id);
        $q = trim((string) ($request->validate(['q' => 'nullable|string|max:255'])['q'] ?? ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['objects' => []]);
        }

        return response()->json(['objects' => $this->service->searchObjects($id, $q)]);
    }

    private function objectPlacementRules(int $id): array
    {
        return [
            'section_id' => $this->sectionRule($id),
            'display_position' => 'nullable|string|max:100',
            'installation_notes' => 'nullable|string|max:65535',
            'insurance_value' => 'nullable|numeric|min:0|max:9999999999999',
            'lender_institution' => 'nullable|string|max:255',
        ];
    }

    public function storeObject(Request $request, int $id)
    {
        $this->requireExhibition($id);

        $data = $request->validate([
            'information_object_id' => [
                'required', 'integer', 'not_in:1',
                Rule::exists('information_object', 'id'),
                Rule::unique('exhibition_object', 'information_object_id')->where('exhibition_id', $id),
            ],
        ] + $this->objectPlacementRules($id), [
            'information_object_id.required' => __('Choose an object from the search results.'),
            'information_object_id.unique' => __('That object is already in this exhibition.'),
        ]);
        $data['requires_loan'] = $request->boolean('requires_loan');

        $this->service->addObject($id, (int) $data['information_object_id'], $data);

        return $this->backTo('exhibition.objects', $id, __('Object added to exhibition'));
    }

    public function updateObject(Request $request, int $id, int $objectId)
    {
        $this->requireExhibition($id);

        $data = $request->validate($this->objectPlacementRules($id) + [
            'sequence_order' => 'nullable|integer|min:0',
            'status' => ['nullable', 'string', Rule::in(array_keys($this->service->getObjectStatuses()))],
            'status_notes' => 'nullable|string|max:65535',
        ]);
        $requiresLoan = $request->boolean('requires_loan');

        $this->service->updateObject($id, $objectId, [
            'section_id' => $data['section_id'] ?? null,
            'display_position' => $data['display_position'] ?? null,
            'sequence_order' => (int) ($data['sequence_order'] ?? 0),
            'installation_notes' => $data['installation_notes'] ?? null,
            'insurance_value' => $data['insurance_value'] ?? null,
            'requires_loan' => $requiresLoan ? 1 : 0,
            'lender_institution' => $requiresLoan ? ($data['lender_institution'] ?? null) : null,
        ]);

        if (! empty($data['status'])) {
            $this->service->updateObjectStatus($id, $objectId, $data['status'], auth()->id(), $data['status_notes'] ?? null);
        }

        return $this->backTo('exhibition.objects', $id, __('Object updated'));
    }

    public function removeObject(int $id, int $objectId)
    {
        $this->requireExhibition($id);
        $this->service->removeObject($id, $objectId);

        return $this->backTo('exhibition.objects', $id, __('Object removed from exhibition'));
    }

    /** Drag-and-drop order from the objects table (JSON). */
    public function reorderObjects(Request $request, int $id)
    {
        $this->requireExhibition($id);

        $data = $request->validate([
            'order' => 'required|array|max:5000',
            'order.*.id' => 'required|integer',
            'order.*.sequence_order' => 'required|integer|min:0',
        ]);
        $this->service->reorderObjects($id, $data['order']);

        return response()->json(['success' => true]);
    }

    // Sections

    private function sectionRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:65535',
            'narrative' => 'nullable|string|max:65535',
            'section_type' => ['nullable', 'string', Rule::in(array_keys($this->service->getSectionTypes()))],
            'gallery_name' => 'nullable|string|max:100',
            'floor_level' => 'nullable|string|max:20',
            'square_meters' => 'nullable|numeric|min:0|max:999999.99',
            'theme' => 'nullable|string|max:255',
            'color_scheme' => 'nullable|string|max:100',
            'sequence_order' => 'nullable|integer|min:0',
        ];
    }

    public function storeSection(Request $request, int $id)
    {
        $this->requireExhibition($id);
        $this->service->addSection($id, $request->validate($this->sectionRules()));

        return $this->backTo('exhibition.sections', $id, __('Section added'));
    }

    public function updateSection(Request $request, int $id, int $sectionId)
    {
        $this->requireExhibition($id);
        $data = $request->validate($this->sectionRules());
        $data['section_type'] = ($data['section_type'] ?? null) ?: 'gallery';
        $data['sequence_order'] = (int) ($data['sequence_order'] ?? 0);
        $this->service->updateSection($id, $sectionId, $data);

        return $this->backTo('exhibition.sections', $id, __('Section updated'));
    }

    public function deleteSection(int $id, int $sectionId)
    {
        $this->requireExhibition($id);
        $this->service->deleteSection($id, $sectionId);

        return $this->backTo('exhibition.sections', $id, __('Section deleted'));
    }

    /** Drag-and-drop order from the section cards (JSON). */
    public function reorderSections(Request $request, int $id)
    {
        $this->requireExhibition($id);
        $data = $request->validate(['order' => 'required|array|max:1000', 'order.*' => 'required|integer']);
        $this->service->reorderSections($id, $data['order']);

        return response()->json(['success' => true]);
    }

    // Storylines

    private function storylineRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'narrative_type' => ['nullable', 'string', Rule::in(array_keys($this->service->getNarrativeTypes()))],
            'introduction' => 'nullable|string|max:65535',
            'target_audience' => ['nullable', 'string', Rule::in(array_keys($this->service->getTargetAudiences()))],
            'estimated_duration_minutes' => 'nullable|integer|min:0|max:100000',
        ];
    }

    public function storeStoryline(Request $request, int $id)
    {
        $this->requireExhibition($id);
        $data = $request->validate($this->storylineRules());
        $data['is_primary'] = $request->boolean('is_primary');
        $this->service->createStoryline($id, $data, auth()->id());

        return $this->backTo('exhibition.storylines', $id, __('Storyline created'));
    }

    public function updateStoryline(Request $request, int $id, int $storylineId)
    {
        $this->requireExhibition($id);
        $data = $request->validate($this->storylineRules());
        $data['is_primary'] = $request->boolean('is_primary');
        $this->service->updateStoryline($id, $storylineId, $data);

        return $this->backTo('exhibition.storylines', $id, __('Storyline updated'));
    }

    public function deleteStoryline(int $id, int $storylineId)
    {
        $this->requireExhibition($id);
        $this->service->deleteStoryline($id, $storylineId);

        return $this->backTo('exhibition.storylines', $id, __('Storyline deleted'));
    }

    /** A storyline of this exhibition, or 404. */
    private function requireStoryline(int $exhibitionId, int $storylineId): void
    {
        $storyline = $this->service->getStoryline($storylineId);
        abort_unless($storyline && (int) $storyline->exhibition_id === $exhibitionId, 404);
    }

    public function updateStorylineStop(Request $request, int $exhibitionId, int $storylineId, int $stopId)
    {
        $this->requireStoryline($exhibitionId, $storylineId);

        $data = $request->validate([
            'exhibition_object_id' => ['required', 'integer', Rule::exists('exhibition_object', 'id')->where('exhibition_id', $exhibitionId)],
            'title' => 'required|string|max:255',
            'narrative_text' => 'nullable|string|max:65535',
            'audio_duration_seconds' => 'nullable|integer|min:0',
            'sequence_order' => 'nullable|integer|min:1',
        ]);
        $this->service->updateStorylineStop($storylineId, $stopId, $data);

        return redirect()
            ->route('exhibition.storyline', ['exhibitionId' => $exhibitionId, 'storylineId' => $storylineId])
            ->with('success', __('Stop updated.'));
    }

    public function deleteStorylineStop(int $exhibitionId, int $storylineId, int $stopId)
    {
        $this->requireStoryline($exhibitionId, $storylineId);
        $this->service->deleteStorylineStop($storylineId, $stopId);

        return redirect()
            ->route('exhibition.storyline', ['exhibitionId' => $exhibitionId, 'storylineId' => $storylineId])
            ->with('success', __('Stop deleted.'));
    }

    // Events

    private function eventData(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'event_type' => ['required', 'string', Rule::in(array_keys($this->service->getEventTypes()))],
            'description' => 'nullable|string|max:65535',
            'event_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location_notes' => 'nullable|string|max:255',
            'max_attendees' => 'nullable|integer|min:1',
            'ticket_price' => 'nullable|numeric|min:0|max:99999999.99',
            'presenter_name' => 'nullable|string|max:255',
        ]);
        $data['requires_registration'] = $request->boolean('requires_registration');
        $data['is_free'] = $request->boolean('is_free');

        return $data;
    }

    public function storeEvent(Request $request, int $id)
    {
        $this->requireExhibition($id);
        $this->service->createEvent($id, $this->eventData($request), auth()->id());

        return $this->backTo('exhibition.events', $id, __('Event created'));
    }

    public function updateEvent(Request $request, int $id, int $eventId)
    {
        $this->requireExhibition($id);
        $this->service->updateEvent($id, $eventId, $this->eventData($request));

        return $this->backTo('exhibition.events', $id, __('Event updated'));
    }

    public function deleteEvent(int $id, int $eventId)
    {
        $this->requireExhibition($id);
        $this->service->deleteEvent($id, $eventId);

        return $this->backTo('exhibition.events', $id, __('Event deleted'));
    }

    // Checklists

    public function storeChecklist(Request $request, int $id)
    {
        $this->requireExhibition($id);

        $data = $request->validate([
            'template_id' => 'required|integer|exists:exhibition_checklist_template,id',
            'assigned_to' => 'nullable|integer|exists:user,id',
        ]);
        $this->service->createChecklistFromTemplate($id, (int) $data['template_id'], $data['assigned_to'] ?? null);

        return $this->backTo('exhibition.checklists', $id, __('Checklist created'));
    }

    public function storeChecklistItem(Request $request, int $id, int $checklistId)
    {
        $this->requireExhibition($id);
        abort_unless($this->service->checklistBelongsTo($id, $checklistId), 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'category' => 'nullable|string|max:100',
        ]);
        $data['is_required'] = $request->boolean('is_required');
        $this->service->addChecklistItem($checklistId, $data);

        return $this->backTo('exhibition.checklists', $id, __('Item added to checklist'));
    }

    public function completeChecklistItem(Request $request, int $id, int $itemId)
    {
        $this->requireExhibition($id);
        $data = $request->validate(['notes' => 'nullable|string|max:65535']);
        $this->service->completeChecklistItem($id, $itemId, auth()->id(), $data['notes'] ?? null);

        return $this->backTo('exhibition.checklists', $id, __('Item completed'));
    }

    /**
     * Handle POST actions for exhibitions.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');
        $id = (int) $request->get('id');

        if ($action === 'delete' && $id) {
            $this->service->delete($id);

            return redirect()->route('exhibition.index')->with('notice', __('Exhibition deleted.'));
        }

        return redirect()->back()->with('error', __('Invalid action.'));
    }
}
