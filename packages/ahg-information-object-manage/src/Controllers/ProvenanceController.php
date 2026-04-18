<?php

/**
 * ProvenanceController - Controller for Heratio
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



namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use AhgInformationObjectManage\Services\ProvenanceService;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgProvenancePlugin/
 */
class ProvenanceController extends Controller
{
    private ProvenanceService $service;

    public function __construct(ProvenanceService $service)
    {
        $this->service = $service;
    }

    /**
     * Show provenance chain + edit form for an IO.
     */
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $events       = $this->service->getChain($io->id);
        $timelineData = json_decode($this->service->getTimelineData($io->id), true);

        return view('ahg-io-manage::provenance.index', [
            'io'           => $io,
            'events'       => $events,
            'timelineData' => $timelineData,
        ]);
    }

    /**
     * Timeline visualization of the provenance chain.
     */
    public function timeline(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $events       = $this->service->getChain($io->id);
        $timelineData = $this->service->getTimelineData($io->id);

        return view('ahg-io-manage::provenance.timeline', [
            'io'           => $io,
            'events'       => $events,
            'timelineData' => $timelineData,
        ]);
    }

    /**
     * Validate and create a new provenance entry.
     */
    public function store(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $validated = $request->validate([
            'owner_name'           => 'required|string|max:500',
            'owner_type'           => 'nullable|string|max:97',
            'owner_actor_id'       => 'nullable|integer',
            'owner_location'       => 'nullable|string|max:255',
            'owner_location_tgn'   => 'nullable|string|max:100',
            'start_date'           => 'nullable|string|max:50',
            'start_date_qualifier' => 'nullable|string|max:31',
            'end_date'             => 'nullable|string|max:50',
            'end_date_qualifier'   => 'nullable|string|max:31',
            'transfer_type'        => 'nullable|string|max:123',
            'transfer_details'     => 'nullable|string',
            'sale_price'           => 'nullable|numeric',
            'sale_currency'        => 'nullable|string|max:10',
            'auction_house'        => 'nullable|string|max:255',
            'auction_lot'          => 'nullable|string|max:50',
            'certainty'            => 'nullable|string|max:53',
            'sources'              => 'nullable|string',
            'notes'                => 'nullable|string',
            'is_gap'               => 'nullable|boolean',
            'gap_explanation'      => 'nullable|string',
        ]);

        $validated['information_object_id'] = $io->id;

        $this->service->createEntry($validated);

        return redirect()
            ->route('io.provenance', $slug)
            ->with('success', 'Provenance entry added.');
    }

    /**
     * Update an existing provenance entry.
     */
    public function update(Request $request, int $id)
    {
        $entry = $this->service->getEntry($id);
        if (!$entry) {
            abort(404);
        }

        $validated = $request->validate([
            'owner_name'           => 'required|string|max:500',
            'owner_type'           => 'nullable|string|max:97',
            'owner_actor_id'       => 'nullable|integer',
            'owner_location'       => 'nullable|string|max:255',
            'owner_location_tgn'   => 'nullable|string|max:100',
            'start_date'           => 'nullable|string|max:50',
            'start_date_qualifier' => 'nullable|string|max:31',
            'end_date'             => 'nullable|string|max:50',
            'end_date_qualifier'   => 'nullable|string|max:31',
            'transfer_type'        => 'nullable|string|max:123',
            'transfer_details'     => 'nullable|string',
            'sale_price'           => 'nullable|numeric',
            'sale_currency'        => 'nullable|string|max:10',
            'auction_house'        => 'nullable|string|max:255',
            'auction_lot'          => 'nullable|string|max:50',
            'certainty'            => 'nullable|string|max:53',
            'sources'              => 'nullable|string',
            'notes'                => 'nullable|string',
            'is_gap'               => 'nullable|boolean',
            'gap_explanation'      => 'nullable|string',
            'sequence'             => 'nullable|integer',
        ]);

        $this->service->updateEntry($id, $validated);

        // Resolve slug for redirect
        $io = DB::table('slug')
            ->where('object_id', $entry->information_object_id)
            ->first();

        $slug = $io->slug ?? $entry->information_object_id;

        return redirect()
            ->route('io.provenance', $slug)
            ->with('success', 'Provenance entry updated.');
    }

    /**
     * Delete a provenance entry and resequence.
     */
    public function destroy(int $id)
    {
        $entry = $this->service->getEntry($id);
        if (!$entry) {
            abort(404);
        }

        $this->service->deleteEntry($id);

        // Resolve slug for redirect
        $io = DB::table('slug')
            ->where('object_id', $entry->information_object_id)
            ->first();

        $slug = $io->slug ?? $entry->information_object_id;

        return redirect()
            ->route('io.provenance', $slug)
            ->with('success', 'Provenance entry deleted.');
    }

    /**
     * Export provenance chain as CSV.
     */
    public function exportCsv(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $entries = $this->service->getChain($io->id);

        $filename = ($io->identifier ?? $slug) . '_provenance.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($entries, $io) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['#', 'Owner', 'Owner Type', 'Location', 'TGN', 'Start Date', 'End Date', 'Transfer', 'Certainty', 'Sale Price', 'Currency', 'Auction House', 'Lot #', 'Sources', 'Notes', 'Gap']);
            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->sequence,
                    $entry->owner_name,
                    $entry->owner_type,
                    $entry->owner_location,
                    $entry->owner_location_tgn,
                    $entry->start_date,
                    $entry->end_date,
                    $entry->transfer_type,
                    $entry->certainty,
                    $entry->sale_price,
                    $entry->sale_currency,
                    $entry->auction_house,
                    $entry->auction_lot,
                    $entry->sources,
                    $entry->notes,
                    $entry->is_gap ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Look up an information object by slug.
     */
    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();
    }
}
