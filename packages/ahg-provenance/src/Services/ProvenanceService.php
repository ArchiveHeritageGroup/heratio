<?php

namespace AhgProvenance\Services;

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
                     ->where('slug.name', '!=', '');
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

        $provenance = DB::table('provenance')
            ->where('information_object_id', $io->id)
            ->orderBy('event_date')
            ->get();

        return [
            'resource' => $io,
            'provenance' => [
                'record' => $provenance->first(),
                'events' => $provenance,
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
                'description' => $event->description ?? '',
                'agent' => $event->agent_name ?? '',
            ];
        });

        return $data;
    }

    /**
     * List all information objects that have provenance data (browse).
     */
    public function browse(int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('provenance')
            ->join('information_object', 'provenance.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('information_object.id', '=', 'slug.object_id')
                     ->where('slug.name', '!=', '');
            })
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
                DB::raw('COUNT(provenance.id) as event_count'),
                DB::raw('MIN(provenance.event_date) as earliest_event'),
                DB::raw('MAX(provenance.event_date) as latest_event')
            )
            ->groupBy('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->orderBy('information_object_i18n.title')
            ->paginate($perPage);
    }
}
