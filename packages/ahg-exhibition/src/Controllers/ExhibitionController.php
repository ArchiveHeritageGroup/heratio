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

        return view('ahg-exhibition::objects', compact('exhibition'));
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

        return view('ahg-exhibition::storylines', compact('exhibition'));
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

        return view('ahg-exhibition::sections', compact('exhibition'));
    }

    public function events(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        return view('ahg-exhibition::events', compact('exhibition'));
    }

    public function checklists(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        return view('ahg-exhibition::checklists', compact('exhibition'));
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
