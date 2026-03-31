<?php

/**
 * ExhibitionController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

        return view('ahg-exhibition::index', [
            'exhibitions' => $result['results'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => ceil($result['total'] / $limit),
            'filters' => $filters,
            'types' => $types,
            'statuses' => $statuses,
            'stats' => $stats,
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

        return view('ahg-exhibition::show', compact('exhibition'));
    }

    public function add(Request $request)
    {
        $types = $this->service->getTypes();
        $statuses = $this->service->getStatuses();

        if ($request->isMethod('post')) {
            $id = $this->service->create($request->only([
                'title', 'subtitle', 'exhibition_type', 'project_code',
                'description', 'theme', 'target_audience',
                'start_date', 'end_date', 'venue', 'status',
                'curator', 'designer', 'budget', 'budget_currency',
            ]) + ['created_by' => auth()->id()]);

            return redirect()->route('exhibition.show', $id)->with('notice', 'Exhibition created');
        }

        return view('ahg-exhibition::add', compact('types', 'statuses'));
    }

    public function edit(Request $request, int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404, 'Exhibition not found');

        $types = $this->service->getTypes();
        $statuses = $this->service->getStatuses();

        if ($request->isMethod('post')) {
            $this->service->update($id, $request->only([
                'title', 'subtitle', 'exhibition_type', 'project_code',
                'description', 'theme', 'target_audience',
                'start_date', 'end_date', 'venue', 'status',
                'curator', 'designer', 'budget', 'budget_currency',
            ]));

            return redirect()->route('exhibition.show', $id)->with('notice', 'Exhibition updated');
        }

        return view('ahg-exhibition::edit', compact('exhibition', 'types', 'statuses'));
    }

    public function dashboard()
    {
        $stats = $this->service->getStatistics();
        $result = $this->service->search(['status' => 'active'], 10, 0);

        return view('ahg-exhibition::dashboard', [
            'stats' => $stats,
            'activeExhibitions' => $result['results'],
        ]);
    }

    public function objects(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        return view('ahg-exhibition::objects', compact('exhibition'));
    }

    public function objectList(int $id)
    {
        $exhibition = $this->service->get($id, true);
        abort_unless($exhibition, 404);

        return view('ahg-exhibition::object-list', compact('exhibition'));
    }

    public function objectListCsv(int $id)
    {
        $exhibition = $this->service->get($id);
        abort_unless($exhibition, 404);

        $csv = $this->service->exportObjectListCsv($id);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="exhibition_objects_' . $id . '.csv"',
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

        return view('ahg-exhibition::storyline', compact('exhibition', 'storyline'));
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

            return redirect()->route('exhibition.index')->with('notice', 'Exhibition deleted.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }
}
