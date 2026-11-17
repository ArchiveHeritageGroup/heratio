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

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExhibitionService
{
    /**
     * Allowed workflow moves between exhibition_status codes. The codes and
     * their labels/colours live in ahg_dropdown (exhibition_status); this is
     * only the order an exhibition may move through them.
     */
    private const STATUS_TRANSITIONS = [
        'concept' => ['planning', 'canceled'],
        'planning' => ['concept', 'preparation', 'canceled'],
        'preparation' => ['planning', 'installation', 'canceled'],
        'installation' => ['preparation', 'open', 'canceled'],
        'open' => ['closing'],
        'closing' => ['open', 'closed'],
        'closed' => ['archived'],
        'archived' => [],
        'canceled' => ['concept'],
    ];

    /** Progress-bar position per workflow stage, shown on the exhibition page. */
    private const STATUS_PROGRESS = [
        'concept' => 10, 'planning' => 25, 'preparation' => 50, 'installation' => 75,
        'open' => 90, 'closing' => 95, 'closed' => 98, 'archived' => 100,
    ];

    /** digital_object.usage_id of a thumbnail derivative. */
    private const USAGE_THUMBNAIL = 141;

    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? app()->getLocale();
    }

    public function search(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query = DB::table('exhibition');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['exhibition_type'])) {
            $query->where('exhibition_type', $filters['exhibition_type']);
        }
        if (! empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', '%'.$filters['search'].'%')
                    ->orWhere('description', 'LIKE', '%'.$filters['search'].'%');
            });
        }

        $total = $query->count();
        $results = $query->orderByDesc('created_at')->offset($offset)->limit($limit)->get();

        return ['results' => $results, 'total' => $total];
    }

    /**
     * One exhibition. With $withDetails the page-level data is attached too:
     * status/type labels, timing, objects, sections, storylines, events,
     * checklists (with items and progress) and per-exhibition statistics.
     */
    public function get(int $id, bool $withDetails = false): ?object
    {
        $exhibition = DB::table('exhibition')->where('id', $id)->first();

        if (! $exhibition) {
            return null;
        }

        $statuses = $this->getStatusesWithColors();
        $types = $this->getTypes();
        $exhibition->status_info = $statuses[$exhibition->status] ?? null;
        $exhibition->type_label = $types[$exhibition->exhibition_type] ?? $exhibition->exhibition_type;
        $exhibition->timing = $this->calculateTiming($exhibition);
        $exhibition->progress = self::STATUS_PROGRESS[$exhibition->status] ?? 0;

        if ($withDetails) {
            $exhibition->objects = $this->getObjects($id);
            $exhibition->storylines = $this->getStorylines($id);
            $exhibition->sections = $this->getSections($id);
            $exhibition->events = $this->getEvents($id);
            $exhibition->checklists = $this->getChecklists($id);
            $exhibition->statistics = $this->getExhibitionStatistics($id, $exhibition->checklists);
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
            // slug is UNIQUE: a second exhibition with the same title gets -1, -2...
            $base = \Illuminate\Support\Str::slug($data['title']) ?: 'exhibition';
            $slug = $base;
            for ($n = 1; DB::table('exhibition')->where('slug', $slug)->exists(); $n++) {
                $slug = $base.'-'.$n;
            }
            $data['slug'] = $slug;
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

    /** exhibition_type codes => labels, from the Dropdown Manager. */
    public function getTypes(): array
    {
        return AhgSettingsService::getDropdownChoices('exhibition_type', false);
    }

    /** exhibition_status codes => labels, from the Dropdown Manager. */
    public function getStatuses(): array
    {
        return AhgSettingsService::getDropdownChoices('exhibition_status', false);
    }

    /** exhibition_status codes => ['label', 'color', 'order']. */
    public function getStatusesWithColors(): array
    {
        $out = [];
        foreach (AhgSettingsService::getDropdownChoicesWithAttributes('exhibition_status') as $code => $row) {
            $out[$code] = [
                'label' => $row->label,
                'color' => $row->color ?: '#6c757d',
                'order' => (int) ($row->sort_order ?? 0),
            ];
        }

        return $out;
    }

    /** exhibition_object_status codes => labels. */
    public function getObjectStatuses(): array
    {
        return AhgSettingsService::getDropdownChoices('exhibition_object_status', false);
    }

    /** exhibition_event_type codes => labels. */
    public function getEventTypes(): array
    {
        return AhgSettingsService::getDropdownChoices('exhibition_event_type', false);
    }

    /** exhibition_narrative_type codes => labels. */
    public function getNarrativeTypes(): array
    {
        return AhgSettingsService::getDropdownChoices('exhibition_narrative_type', false);
    }

    /** Currency codes => labels for budget and admission fields. */
    public function getCurrencies(): array
    {
        return AhgSettingsService::getDropdownChoices('research_currency', false);
    }

    /** Active venues for the venue picker. */
    public function getVenues(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('exhibition_venue')) {
            return collect();
        }

        return DB::table('exhibition_venue')->where('is_active', 1)->orderBy('name')->get();
    }

    /** Status codes the exhibition may move to next. */
    public function getValidTransitions(?string $status): array
    {
        return self::STATUS_TRANSITIONS[$status ?? ''] ?? [];
    }

    /**
     * Move an exhibition to the next workflow status and record the change
     * in exhibition_status_history.
     *
     * @throws \InvalidArgumentException when the move is not allowed
     */
    public function transitionStatus(int $id, string $newStatus, ?int $userId, ?string $reason = null): void
    {
        $current = DB::table('exhibition')->where('id', $id)->value('status');
        if ($current === null) {
            throw new \InvalidArgumentException(__('Exhibition not found'));
        }
        if (! in_array($newStatus, $this->getValidTransitions($current), true)) {
            throw new \InvalidArgumentException(__('Cannot move from :from to :to', ['from' => $current, 'to' => $newStatus]));
        }

        DB::transaction(function () use ($id, $current, $newStatus, $userId, $reason) {
            DB::table('exhibition')->where('id', $id)->update([
                'status' => $newStatus,
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);
            DB::table('exhibition_status_history')->insert([
                'exhibition_id' => $id,
                'from_status' => $current,
                'to_status' => $newStatus,
                'changed_by' => $userId,
                'change_reason' => $reason,
                'created_at' => now(),
            ]);
        });
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
        if (! DB::getSchemaBuilder()->hasTable('exhibition_checklist')) {
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
        if (! DB::getSchemaBuilder()->hasTable('exhibition_status_history')) {
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

    /**
     * Objects in the exhibition with identifier, section, slug, status label
     * and thumbnail, in section then display order.
     */
    public function getObjects(int $exhibitionId): \Illuminate\Support\Collection
    {
        $objectStatuses = $this->getObjectStatuses();

        $rows = DB::table('exhibition_object as eo')
            ->leftJoin('information_object as io', 'io.id', '=', 'eo.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('eo.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('exhibition_section as es', 'es.id', '=', 'eo.section_id')
            ->leftJoin('slug', 'eo.information_object_id', '=', 'slug.object_id')
            ->where('eo.exhibition_id', $exhibitionId)
            ->select('eo.*', 'io.identifier', 'ioi.title as object_title', 'slug.slug',
                'es.title as section_title', 'es.gallery_name')
            ->orderBy('eo.section_id')
            ->orderBy('eo.sequence_order')
            ->get();

        $thumbs = $this->thumbnailsFor($rows->pluck('information_object_id')->all());

        return $rows->map(function ($row) use ($objectStatuses, $thumbs) {
            $row->status_label = $objectStatuses[$row->status] ?? $row->status;
            $row->thumbnail_url = $thumbs[(int) $row->information_object_id] ?? null;

            return $row;
        });
    }

    /** Storylines with their stop count, primary storyline first. */
    public function getStorylines(int $exhibitionId): \Illuminate\Support\Collection
    {
        $narrativeTypes = $this->getNarrativeTypes();

        return DB::table('exhibition_storyline')
            ->where('exhibition_id', $exhibitionId)
            ->select('exhibition_storyline.*')
            ->selectSub(DB::table('exhibition_storyline_stop')
                ->whereColumn('exhibition_storyline_stop.storyline_id', 'exhibition_storyline.id')
                ->selectRaw('COUNT(*)'),
            'stop_count'
            )
            ->orderByDesc('is_primary')
            ->orderBy('sequence_order')
            ->get()
            ->map(function ($row) use ($narrativeTypes) {
                $row->narrative_type_label = $narrativeTypes[$row->narrative_type] ?? ucfirst(str_replace('_', ' ', (string) $row->narrative_type));

                return $row;
            });
    }

    /** Sections with the number of objects placed in each. */
    public function getSections(int $exhibitionId): \Illuminate\Support\Collection
    {
        return DB::table('exhibition_section')
            ->where('exhibition_id', $exhibitionId)
            ->select('exhibition_section.*')
            ->selectSub(DB::table('exhibition_object')
                ->whereColumn('exhibition_object.section_id', 'exhibition_section.id')
                ->selectRaw('COUNT(*)'),
            'object_count'
            )
            ->orderBy('sequence_order')
            ->get();
    }

    /** Events in date and start-time order, with a readable type label. */
    public function getEvents(int $exhibitionId): \Illuminate\Support\Collection
    {
        $eventTypes = $this->getEventTypes();

        return DB::table('exhibition_event')
            ->where('exhibition_id', $exhibitionId)
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($row) use ($eventTypes) {
                $row->event_type_label = $eventTypes[$row->event_type] ?? $row->event_type;

                return $row;
            });
    }

    /** Checklists with their items, item totals and percentage complete. */
    public function getChecklists(int $exhibitionId): \Illuminate\Support\Collection
    {
        // exhibition_checklist has no `category` - the column is
        // checklist_type. ORDER BY on a column that does not exist is a
        // hard SQL error, so this threw rather than sorting wrongly.
        $checklists = DB::table('exhibition_checklist')
            ->where('exhibition_id', $exhibitionId)
            ->orderBy('checklist_type')
            ->get();

        $items = $checklists->isEmpty() ? collect() : DB::table('exhibition_checklist_item')
            ->whereIn('checklist_id', $checklists->pluck('id'))
            ->orderBy('sequence_order')
            ->get()
            ->groupBy('checklist_id');

        return $checklists->map(function ($cl) use ($items) {
            $list = $items->get($cl->id, collect())->values();
            $cl->items = $list->all();
            $cl->total_items = $list->count();
            $cl->completed_items = $list->where('is_completed', 1)->count();
            $cl->progress = $cl->total_items > 0
                ? (int) round($cl->completed_items / $cl->total_items * 100)
                : 0;

            return $cl;
        });
    }

    /** Counts and totals for one exhibition's page. */
    public function getExhibitionStatistics(int $exhibitionId, ?\Illuminate\Support\Collection $checklists = null): array
    {
        $byStatus = DB::table('exhibition_object')
            ->where('exhibition_id', $exhibitionId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $checklists ??= $this->getChecklists($exhibitionId);

        return [
            'object_count' => array_sum($byStatus),
            'objects_by_status' => $byStatus,
            'section_count' => DB::table('exhibition_section')->where('exhibition_id', $exhibitionId)->count(),
            'storyline_count' => DB::table('exhibition_storyline')->where('exhibition_id', $exhibitionId)->count(),
            'event_count' => DB::table('exhibition_event')->where('exhibition_id', $exhibitionId)
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'canceled'))->count(),
            'total_insurance_value' => (float) DB::table('exhibition_object')
                ->where('exhibition_id', $exhibitionId)->sum('insurance_value'),
            'checklist_progress' => $checklists->pluck('progress', 'checklist_type')->all(),
        ];
    }

    /**
     * Object list report rows - objects in section order with the fields the
     * printed list and the CSV export need.
     */
    public function generateObjectList(int $exhibitionId): \Illuminate\Support\Collection
    {
        return DB::table('exhibition_object as eo')
            ->leftJoin('exhibition_section as es', 'es.id', '=', 'eo.section_id')
            ->leftJoin('information_object as io', 'io.id', '=', 'eo.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('eo.exhibition_id', $exhibitionId)
            ->orderBy('es.sequence_order')
            ->orderBy('eo.sequence_order')
            ->select(
                'eo.id', 'eo.information_object_id', 'slug.slug', 'io.identifier',
                'ioi.title as object_title', 'es.id as section_id', 'es.title as section_title',
                'es.gallery_name', 'eo.display_position', 'eo.status', 'eo.insurance_value',
                'eo.requires_loan', 'eo.lender_institution', 'eo.label_text', 'eo.installation_notes'
            )
            ->get();
    }

    /**
     * Thumbnail URL per information object, from its master's thumbnail
     * derivative. Same lookup as the recently-added strip.
     *
     * @return array<int,string>
     */
    private function thumbnailsFor(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }

        $out = [];
        $rows = DB::table('digital_object as m')
            ->join('digital_object as t', function ($j) {
                $j->on('t.parent_id', '=', 'm.id')->where('t.usage_id', '=', self::USAGE_THUMBNAIL);
            })
            ->whereIn('m.object_id', $ids)
            ->whereNull('m.parent_id')
            ->whereNotNull('t.name')
            ->select('m.object_id as id', 't.path', 't.name')
            ->get();

        foreach ($rows as $r) {
            $out[(int) $r->id] ??= url('/'.ltrim(rtrim((string) $r->path, '/').'/'.ltrim((string) $r->name, '/'), '/'));
        }

        return $out;
    }

    /** Days to/since opening and closing, and the run length. */
    private function calculateTiming(object $exhibition): array
    {
        $timing = [
            'days_until_opening' => null, 'days_since_opening' => null,
            'days_until_closing' => null, 'days_since_closing' => null,
            'duration_days' => null, 'is_current' => ($exhibition->status ?? '') === 'open',
        ];
        $today = now()->startOfDay();

        if (! empty($exhibition->opening_date)) {
            $open = \Illuminate\Support\Carbon::parse($exhibition->opening_date)->startOfDay();
            $timing[$open->gt($today) ? 'days_until_opening' : 'days_since_opening'] = (int) $today->diffInDays($open, true);
        }
        if (! empty($exhibition->closing_date)) {
            $close = \Illuminate\Support\Carbon::parse($exhibition->closing_date)->startOfDay();
            $timing[$close->gt($today) ? 'days_until_closing' : 'days_since_closing'] = (int) $today->diffInDays($close, true);
        }
        if (! empty($exhibition->opening_date) && ! empty($exhibition->closing_date)) {
            $timing['duration_days'] = (int) \Illuminate\Support\Carbon::parse($exhibition->opening_date)
                ->diffInDays(\Illuminate\Support\Carbon::parse($exhibition->closing_date), true);
        }

        return $timing;
    }

    /**
     * Stops belonging to a storyline, in route order - #1481.
     *
     * The storyline view reads $storyline->stops and nothing ever populated it,
     * so the stop list rendered empty on every storyline no matter how many
     * stops the table held.
     */
    public function getStorylineStops(int $storylineId): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('exhibition_storyline_stop')) {
            return collect();
        }

        return DB::table('exhibition_storyline_stop')
            ->where('storyline_id', $storylineId)
            ->orderBy('sequence_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Add a stop to a storyline - #1481.
     *
     * The "Add Stop" form has existed since the view was written and posted to
     * a GET-only route, so it raised a 405 rather than saving; nothing anywhere
     * wrote exhibition_storyline_stop. sequence_order defaults to the end of
     * the route when the caller does not choose a position.
     */
    public function createStorylineStop(int $storylineId, array $data): int
    {
        $order = $data['sequence_order'] ?? null;
        if ($order === null || $order === '') {
            $order = ((int) DB::table('exhibition_storyline_stop')
                ->where('storyline_id', $storylineId)
                ->max('sequence_order')) + 1;
        }

        return (int) DB::table('exhibition_storyline_stop')->insertGetId([
            'storyline_id'           => $storylineId,
            // NOT NULL with a foreign key onto exhibition_object: a stop is a
            // stop AT an object, which is why the form now has to choose one.
            'exhibition_object_id'   => (int) ($data['exhibition_object_id'] ?? 0),
            'title'                  => $data['title'] ?? '',
            'narrative_text'         => $data['narrative_text'] ?? null,
            'audio_duration_seconds' => ($data['audio_duration_seconds'] ?? null) ?: null,
            'sequence_order'         => (int) $order,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    public function getStoryline(int $id): ?object
    {
        return DB::table('exhibition_storyline')->where('id', $id)->first();
    }

    public function exportObjectListCsv(int $exhibitionId): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Object Number', 'Title', 'Section', 'Display Location', 'Insurance Value', 'Status', 'Loan', 'Lender', 'Notes']);

        $statuses = $this->getObjectStatuses();
        foreach ($this->generateObjectList($exhibitionId) as $obj) {
            fputcsv($output, [
                $obj->identifier ?? '',
                $obj->object_title ?? '',
                $obj->section_title ?? '',
                $obj->display_position ?? '',
                $obj->insurance_value ?? '',
                $statuses[$obj->status] ?? ($obj->status ?? ''),
                ! empty($obj->requires_loan) ? 'Yes' : '',
                $obj->lender_institution ?? '',
                $obj->installation_notes ?? ($obj->label_text ?? ''),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
