<?php

namespace AhgInformationObjectManage\Services;

use AhgCore\Constants\TermId;
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

        // Top-level filter: only show top-level descriptions (parent_id=1)
        if (!empty($this->activeFilters['top_level'])) {
            $query->where('information_object.parent_id', 1);
        }

        // Publication status filter: only show published records
        if (!empty($this->activeFilters['publication_status'])) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', 'information_object.id')
                    ->where('status.type_id', TermId::STATUS_TYPE_PUBLICATION)
                    ->where('status.status_id', TermId::PUBLICATION_STATUS_PUBLISHED);
            });
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
                  ->orWhere('information_object.identifier', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.alternate_title', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.scope_and_content', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.archival_history', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.extent_and_medium', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.acquisition', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.appraisal', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.accruals', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.arrangement', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.access_conditions', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.reproduction_conditions', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.physical_characteristics', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.finding_aids', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.location_of_originals', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.location_of_copies', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.related_units_of_description', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.rules', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.sources', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.revision_history', 'LIKE', "%{$subquery}%")
                  ->orWhere('information_object_i18n.institution_responsible_identifier', 'LIKE', "%{$subquery}%");
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
