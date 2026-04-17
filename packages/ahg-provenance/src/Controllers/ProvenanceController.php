<?php

/**
 * ProvenanceController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgProvenance\Controllers;

use AhgProvenance\Services\ProvenanceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProvenanceController extends Controller
{
    public function __construct(
        protected ProvenanceService $service
    ) {}

    /**
     * Browse provenance records.
     */
    public function index()
    {
        $records = $this->service->browse();

        return view('ahg-provenance::index', compact('records'));
    }

    /**
     * View provenance for a specific IO.
     */
    public function view(string $slug)
    {
        $data = $this->service->getBySlug($slug);
        abort_unless($data['resource'], 404);

        return view('ahg-provenance::view', $data);
    }

    /**
     * Timeline view of provenance events.
     */
    public function timeline(string $slug)
    {
        $data = $this->service->getTimeline($slug);
        abort_unless($data['resource'], 404);

        return view('ahg-provenance::timeline', $data);
    }

    /**
     * Edit provenance for a specific IO.
     */
    public function edit(string $slug)
    {
        $data = $this->service->getBySlug($slug);
        abort_unless($data['resource'], 404);

        $data['eventTypes'] = $this->service->getEventTypes();
        $data['acquisitionTypes'] = $this->service->getAcquisitionTypes();
        $data['certaintyLevels'] = $this->service->getCertaintyLevels();

        return view('ahg-provenance::edit', $data);
    }

    /**
     * AJAX: Search agents (actors) for provenance autocomplete.
     * GET /provenance/searchAgents?term=...
     *
     * Returns JSON array of matching actors with id and name.
     */
    public function searchAgents(Request $request)
    {
        $term = $request->query('term', '');
        $culture = app()->getLocale();

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $results = \Illuminate\Support\Facades\DB::table('actor')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
            ->where('actor_i18n.authorized_form_of_name', 'LIKE', '%' . $term . '%')
            ->where('actor.id', '!=', 3) // Root actor ID
            ->select([
                'actor.id',
                'actor_i18n.authorized_form_of_name as name',
                'slug.slug',
            ])
            ->limit(20)
            ->get();

        return response()->json($results);
    }

    /**
     * Update provenance record.
     */
    public function update(Request $request, string $slug)
    {
        $data = $this->service->getBySlug($slug);
        abort_unless($data['resource'], 404);

        $this->service->update($slug, $request->all());

        return redirect('/' . $slug)->with('notice', 'Provenance updated.');
    }

    /**
     * Add a provenance event to an IO.
     */
    public function addEvent(Request $request, string $slug)
    {
        $request->validate([
            'event_type' => 'required|string',
            'date' => 'nullable|string',
            'agent_id' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $this->service->addEvent($slug, $request->all());

        return redirect()->route('provenance.edit', $slug)->with('notice', 'Event added.');
    }

    /**
     * Delete a provenance event.
     */
    public function deleteEvent(string $slug, int $eventId)
    {
        $this->service->deleteEvent($slug, $eventId);

        return redirect()->route('provenance.edit', $slug)->with('notice', 'Event deleted.');
    }

    /**
     * Delete a provenance document.
     */
    public function deleteDocument(string $slug, int $id)
    {
        $this->service->deleteDocument($id);

        return redirect()->route('provenance.edit', $slug)->with('notice', 'Document deleted.');
    }

    /**
     * Legacy addEvent (POST /provenance/addEvent with slug in body).
     */
    public function addEventLegacy(Request $request)
    {
        $slug = $request->input('slug');
        abort_unless($slug, 400, 'Missing slug parameter.');

        return $this->addEvent($request, $slug);
    }

    /**
     * Legacy deleteEvent (POST /provenance/deleteEvent with slug + eventId in body).
     */
    public function deleteEventLegacy(Request $request)
    {
        $slug = $request->input('slug');
        $eventId = (int) $request->input('event_id');
        abort_unless($slug && $eventId, 400, 'Missing slug or event_id parameter.');

        return $this->deleteEvent($slug, $eventId);
    }
}
