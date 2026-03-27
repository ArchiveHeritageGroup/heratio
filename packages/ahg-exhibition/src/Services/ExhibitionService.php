<?php

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
        return [
            'total' => DB::table('exhibition')->count(),
            'active' => DB::table('exhibition')->where('status', 'active')->count(),
            'planning' => DB::table('exhibition')->where('status', 'planning')->count(),
            'completed' => DB::table('exhibition')->where('status', 'completed')->count(),
        ];
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
