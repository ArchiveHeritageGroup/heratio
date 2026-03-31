<?php

/**
 * ProvenanceService - Service for Heratio
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


use Illuminate\Support\Facades\DB;

class ProvenanceService
{
    /**
     * Get provenance records for an information object by slug.
     */
    public function getBySlug(string $slug): array
    {
        $io = DB::table('information_object')
            ->leftJoin('slug', function ($join) {
                $join->on('information_object.id', '=', 'slug.object_id')
                     ->where('slug.slug', '!=', '');
            })
            ->where('slug.slug', $slug)
            ->select('information_object.*', 'slug.slug')
            ->first();

        if (!$io) {
            return ['resource' => null, 'provenance' => null];
        }

        $title = DB::table('information_object_i18n')
            ->where('id', $io->id)
            ->where('culture', 'en')
            ->value('title');

        $io->title = $title;

        $record = DB::table('provenance_record')
            ->where('information_object_id', $io->id)
            ->first();

        $events = collect();
        if ($record) {
            $events = DB::table('provenance_event')
                ->where('provenance_record_id', $record->id)
                ->orderBy('event_date')
                ->get();
        }

        return [
            'resource' => $io,
            'provenance' => [
                'record' => $record,
                'events' => $events,
            ],
        ];
    }

    /**
     * Get provenance records as timeline data.
     */
    public function getTimeline(string $slug): array
    {
        $data = $this->getBySlug($slug);
        if (!$data['resource']) {
            return $data;
        }

        $events = $data['provenance']['events'] ?? collect();
        $data['timeline'] = $events->map(function ($event) {
            return [
                'date' => $event->event_date ?? '',
                'title' => $event->event_type ?? '',
                'description' => $event->notes ?? '',
                'agent' => '',
            ];
        });

        return $data;
    }

    /**
     * List all information objects that have provenance data (browse).
     */
    public function browse(int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('provenance_record as pr')
            ->join('information_object', 'pr.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('information_object.id', '=', 'slug.object_id')
                     ->where('slug.slug', '!=', '');
            })
            ->leftJoin('provenance_event as pe', 'pe.provenance_record_id', '=', 'pr.id')
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
                DB::raw('COUNT(pe.id) as event_count'),
                DB::raw('MIN(pe.event_date) as earliest_event'),
                DB::raw('MAX(pe.event_date) as latest_event')
            )
            ->groupBy('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->orderBy('information_object_i18n.title')
            ->paginate($perPage);
    }
}
