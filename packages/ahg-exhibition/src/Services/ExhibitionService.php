<?php

/**
 * ExhibitionService - Service for Heratio
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



namespace AhgExhibition\Services;

use Illuminate\Support\Facades\DB;

class ExhibitionService
{
    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? app()->getLocale();
    }

    public function search(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query = DB::table('exhibition');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['exhibition_type'])) {
            $query->where('exhibition_type', $filters['exhibition_type']);
        }
        if (!empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', '%' . $filters['search'] . '%')
                    ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        $total = $query->count();
        $results = $query->orderByDesc('created_at')->offset($offset)->limit($limit)->get();

        return ['results' => $results, 'total' => $total];
    }

    public function get(int $id, bool $withObjects = false): ?object
    {
        $exhibition = DB::table('exhibition')->where('id', $id)->first();

        if ($exhibition && $withObjects) {
            $exhibition->objects = DB::table('exhibition_object as eo')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('eo.information_object_id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', $this->culture);
                })
                ->leftJoin('slug', 'eo.information_object_id', '=', 'slug.object_id')
                ->where('eo.exhibition_id', $id)
                ->select('eo.*', 'ioi.title as object_title', 'slug.slug')
                ->orderBy('eo.sort_order')
                ->get();

            $exhibition->storylines = DB::table('exhibition_storyline')
                ->where('exhibition_id', $id)
                ->orderBy('sort_order')
                ->get();

            $exhibition->sections = DB::table('exhibition_section')
                ->where('exhibition_id', $id)
                ->orderBy('sort_order')
                ->get();

            $exhibition->events = DB::table('exhibition_event')
                ->where('exhibition_id', $id)
                ->orderBy('event_date')
                ->get();

            $exhibition->checklists = DB::table('exhibition_checklist')
                ->where('exhibition_id', $id)
                ->orderBy('category')
                ->get();
        }

        return $exhibition;
    }

    public function getBySlug(string $slug): ?object
    {
        return DB::table('exhibition')->where('slug', $slug)->first();
    }

    public function create(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title']);
        }

        return DB::table('exhibition')->insertGetId($data);
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = now();
        DB::table('exhibition')->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        DB::table('exhibition_object')->where('exhibition_id', $id)->delete();
        DB::table('exhibition_storyline')->where('exhibition_id', $id)->delete();
        DB::table('exhibition_section')->where('exhibition_id', $id)->delete();
        DB::table('exhibition_event')->where('exhibition_id', $id)->delete();
        DB::table('exhibition_checklist')->where('exhibition_id', $id)->delete();
        DB::table('exhibition')->where('id', $id)->delete();
    }

    public function getTypes(): array
    {
        return [
            'temporary' => 'Temporary',
            'permanent' => 'Permanent',
            'travelling' => 'Travelling',
            'virtual' => 'Virtual',
            'pop_up' => 'Pop-up',
        ];
    }

    public function getStatuses(): array
    {
        return [
            'planning' => 'Planning',
            'preparation' => 'In Preparation',
            'active' => 'Active',
            'completed' => 'Completed',
            'archived' => 'Archived',
        ];
    }

    public function getStatistics(): array
    {
        $today = now()->toDateString();

        $totalExhibitions = DB::table('exhibition')->count();
        $currentExhibitions = DB::table('exhibition')
            ->where('opening_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('closing_date')->orWhere('closing_date', '>=', $today);
            })
            ->whereIn('status', ['open', 'active', 'on_display'])
            ->count();
        $upcomingExhibitions = DB::table('exhibition')
            ->where('opening_date', '>', $today)
            ->count();
        $totalObjectsOnDisplay = DB::table('exhibition_object as eo')
            ->join('exhibition as e', 'eo.exhibition_id', '=', 'e.id')
            ->whereIn('e.status', ['open', 'active', 'on_display'])
            ->count();

        $byStatus = DB::table('exhibition')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'total_exhibitions' => $totalExhibitions,
            'current_exhibitions' => $currentExhibitions,
            'upcoming_exhibitions' => $upcomingExhibitions,
            'total_objects_on_display' => $totalObjectsOnDisplay,
            'by_status' => $byStatus,
            // Legacy keys for back-compat with older views
            'total' => $totalExhibitions,
            'active' => $currentExhibitions,
            'planning' => DB::table('exhibition')->where('status', 'planning')->count(),
            'completed' => DB::table('exhibition')->where('status', 'completed')->count(),
        ];
    }

    /**
     * Exhibitions currently open (opening_date <= today <= closing_date).
     */
    public function getCurrentExhibitions(int $limit = 5): array
    {
        $today = now()->toDateString();

        $rows = DB::table('exhibition as e')
            ->leftJoin('exhibition_object as eo', 'eo.exhibition_id', '=', 'e.id')
            ->where('e.opening_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('e.closing_date')->orWhere('e.closing_date', '>=', $today);
            })
            ->select('e.id', 'e.title', 'e.venue_name', 'e.closing_date', 'e.status', DB::raw('COUNT(eo.id) as object_count'))
            ->groupBy('e.id', 'e.title', 'e.venue_name', 'e.closing_date', 'e.status')
            ->orderBy('e.closing_date')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        return $rows;
    }

    /**
     * Upcoming exhibitions (opening_date in the future).
     */
    public function getUpcomingExhibitions(int $limit = 5): array
    {
        $today = now()->toDateString();

        return DB::table('exhibition')
            ->where('opening_date', '>', $today)
            ->select('id', 'title', 'venue_name', 'opening_date', 'status')
            ->orderBy('opening_date')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    /**
     * Pending checklist items across all exhibitions.
     */
    public function getPendingChecklists(int $limit = 10): array
    {
        if (!DB::getSchemaBuilder()->hasTable('exhibition_checklist')) {
            return [];
        }

        return DB::table('exhibition_checklist as ec')
            ->join('exhibition as e', 'ec.exhibition_id', '=', 'e.id')
            ->where('ec.status', '!=', 'completed')
            ->select(
                'ec.name as task_name',
                'ec.due_date',
                'ec.status',
                'e.title as exhibition_title',
                'e.id as exhibition_id'
            )
            ->orderBy('ec.due_date')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    /**
     * Recent status-transition activity across exhibitions.
     */
    public function getRecentActivity(int $limit = 10): array
    {
        if (!DB::getSchemaBuilder()->hasTable('exhibition_status_history')) {
            return [];
        }

        return DB::table('exhibition_status_history as h')
            ->join('exhibition as e', 'h.exhibition_id', '=', 'e.id')
            ->select(
                'e.title as exhibition_title',
                'h.from_status',
                'h.to_status',
                'h.created_at',
                DB::raw("CONCAT(COALESCE(h.from_status,'new'), ' to ', h.to_status) as transition")
            )
            ->orderByDesc('h.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    /**
     * Calendar events in the next N days (exhibition events + openings/closings).
     */
    public function getCalendarEvents(int $days = 30, int $limit = 10): array
    {
        $today = now()->toDateString();
        $horizon = now()->addDays($days)->toDateString();

        $events = [];

        if (DB::getSchemaBuilder()->hasTable('exhibition_event')) {
            $rows = DB::table('exhibition_event')
                ->whereBetween('event_date', [$today, $horizon])
                ->select('title', 'event_date', 'event_type')
                ->orderBy('event_date')
                ->limit($limit)
                ->get();

            foreach ($rows as $r) {
                $events[] = (array) $r;
            }
        }

        // Fold in upcoming openings and closings
        $openings = DB::table('exhibition')
            ->whereBetween('opening_date', [$today, $horizon])
            ->select(DB::raw("CONCAT('Opening: ', title) as title"), 'opening_date as event_date', DB::raw("'opening' as event_type"))
            ->get();

        $closings = DB::table('exhibition')
            ->whereBetween('closing_date', [$today, $horizon])
            ->select(DB::raw("CONCAT('Closing: ', title) as title"), 'closing_date as event_date', DB::raw("'closing' as event_type"))
            ->get();

        foreach ($openings as $r) {
            $events[] = (array) $r;
        }
        foreach ($closings as $r) {
            $events[] = (array) $r;
        }

        usort($events, fn ($a, $b) => strcmp($a['event_date'] ?? '', $b['event_date'] ?? ''));

        return array_slice($events, 0, $limit);
    }

    public function getObjects(int $exhibitionId): \Illuminate\Support\Collection
    {
        return DB::table('exhibition_object as eo')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('eo.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'eo.information_object_id', '=', 'slug.object_id')
            ->where('eo.exhibition_id', $exhibitionId)
            ->select('eo.*', 'ioi.title as object_title', 'slug.slug')
            ->orderBy('eo.sort_order')
            ->get();
    }

    public function getStorylines(int $exhibitionId): \Illuminate\Support\Collection
    {
        return DB::table('exhibition_storyline')
            ->where('exhibition_id', $exhibitionId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getStoryline(int $id): ?object
    {
        return DB::table('exhibition_storyline')->where('id', $id)->first();
    }

    public function exportObjectListCsv(int $exhibitionId): string
    {
        $objects = $this->getObjects($exhibitionId);
        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['#', 'Title', 'Identifier', 'Section', 'Status', 'Notes']);

        foreach ($objects as $i => $obj) {
            fputcsv($output, [
                $i + 1,
                $obj->object_title ?? 'Untitled',
                $obj->identifier ?? '',
                $obj->section ?? '',
                $obj->status ?? '',
                $obj->notes ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
