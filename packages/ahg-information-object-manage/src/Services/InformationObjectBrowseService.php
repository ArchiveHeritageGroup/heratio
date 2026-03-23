<?php

namespace AhgInformationObjectManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class InformationObjectBrowseService extends BrowseService
{
    protected array $activeFilters = [];

    protected function getTable(): string
    {
        return 'information_object';
    }

    protected function getI18nTable(): string
    {
        return 'information_object_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'title';
    }

    protected function getBaseSelect(): array
    {
        return [
            'information_object.id',
            'information_object_i18n.title as name',
            'information_object.level_of_description_id',
            'information_object.repository_id',
            'information_object.identifier',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        $query = $query
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object_i18n.culture', $this->culture)
            ->where('information_object.id', '!=', 1); // Exclude root

        // Apply active filters
        if (!empty($this->activeFilters['repository_id'])) {
            $query->where('information_object.repository_id', $this->activeFilters['repository_id']);
        }
        if (!empty($this->activeFilters['level_of_description_id'])) {
            $query->where('information_object.level_of_description_id', $this->activeFilters['level_of_description_id']);
        }
        if (!empty($this->activeFilters['media_type_id'])) {
            $query->join('digital_object', 'information_object.id', '=', 'digital_object.information_object_id')
                  ->where('digital_object.media_type_id', $this->activeFilters['media_type_id']);
        }

        return $query;
    }

    public function browse(array $params): array
    {
        // Store filters so getBaseJoins can apply them
        $this->activeFilters = $params['filters'] ?? [];

        $result = parent::browse($params);

        if (!empty($result['hits'])) {
            // Batch resolve level of description names
            $levelIds = array_filter(array_unique(array_column($result['hits'], 'level_of_description_id')));
            $levelNames = [];
            if (!empty($levelIds)) {
                $levelNames = DB::table('term_i18n')
                    ->whereIn('id', $levelIds)
                    ->where('culture', $this->culture)
                    ->pluck('name', 'id')
                    ->toArray();
            }
            $result['levelNames'] = $levelNames;

            // Batch resolve repository names
            $repoIds = array_filter(array_unique(array_column($result['hits'], 'repository_id')));
            $repositoryNames = [];
            if (!empty($repoIds)) {
                $repositoryNames = DB::table('repository')
                    ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                    ->whereIn('repository.id', $repoIds)
                    ->where('actor_i18n.culture', $this->culture)
                    ->pluck('actor_i18n.authorized_form_of_name', 'repository.id')
                    ->toArray();
            }
            $result['repositoryNames'] = $repositoryNames;
        }

        return $result;
    }

    protected function applySearch($query, string $subquery)
    {
        if ($subquery !== '') {
            $query->where(function ($q) use ($subquery) {
                $q->where('information_object_i18n.title', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object.identifier', 'LIKE', "%{$subquery}%");
            });
        }

        return $query;
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'alphabetic':
                $query->orderBy('information_object_i18n.title', $sortDir);
                break;
            case 'identifier':
                $query->orderBy('information_object.identifier', $sortDir);
                $query->orderBy('information_object_i18n.title', $sortDir);
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        return $query;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'level_of_description_id' => $row->level_of_description_id ?? null,
            'repository_id' => $row->repository_id ?? null,
            'identifier' => $row->identifier ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
