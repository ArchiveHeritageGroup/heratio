<?php

namespace AhgInformationObjectManage\Services;

use AhgCore\Constants\TermId;
use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class InformationObjectBrowseService extends BrowseService
{
    protected array $activeFilters = [];
    protected array $advancedCriteria = [];

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

        // Has digital object filter
        if (isset($this->activeFilters['has_digital'])) {
            if ($this->activeFilters['has_digital']) {
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('digital_object')
                        ->whereColumn('digital_object.information_object_id', 'information_object.id');
                });
            } else {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('digital_object')
                        ->whereColumn('digital_object.information_object_id', 'information_object.id');
                });
            }
        }

        // Date range filter via event table
        if (!empty($this->activeFilters['start_date']) || !empty($this->activeFilters['end_date'])) {
            $startDate = $this->activeFilters['start_date'] ?? null;
            $endDate = $this->activeFilters['end_date'] ?? null;
            $rangeType = $this->activeFilters['range_type'] ?? 'inclusive';

            $query->whereExists(function ($sub) use ($startDate, $endDate, $rangeType) {
                $sub->select(DB::raw(1))
                    ->from('event')
                    ->whereColumn('event.information_object_id', 'information_object.id');

                if ($rangeType === 'exact') {
                    if ($startDate) $sub->where('event.start_date', '>=', $startDate);
                    if ($endDate) $sub->where('event.end_date', '<=', $endDate);
                } else {
                    // Inclusive/overlapping: event overlaps with the given range
                    if ($startDate) $sub->where('event.end_date', '>=', $startDate);
                    if ($endDate) $sub->where('event.start_date', '<=', $endDate);
                }
            });
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

        // Store advanced criteria for applyAdvancedCriteria
        $this->advancedCriteria = $params['advancedCriteria'] ?? [];

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
            $like = '%' . addcslashes($subquery, '%_') . '%';
            $query->where(function ($q) use ($like) {
                // Information object i18n fields (all text columns)
                $q->where('information_object_i18n.title', 'LIKE', $like)
                  ->orWhere('information_object.identifier', 'LIKE', $like)
                  ->orWhere('information_object_i18n.alternate_title', 'LIKE', $like)
                  ->orWhere('information_object_i18n.edition', 'LIKE', $like)
                  ->orWhere('information_object_i18n.scope_and_content', 'LIKE', $like)
                  ->orWhere('information_object_i18n.archival_history', 'LIKE', $like)
                  ->orWhere('information_object_i18n.extent_and_medium', 'LIKE', $like)
                  ->orWhere('information_object_i18n.acquisition', 'LIKE', $like)
                  ->orWhere('information_object_i18n.appraisal', 'LIKE', $like)
                  ->orWhere('information_object_i18n.accruals', 'LIKE', $like)
                  ->orWhere('information_object_i18n.arrangement', 'LIKE', $like)
                  ->orWhere('information_object_i18n.access_conditions', 'LIKE', $like)
                  ->orWhere('information_object_i18n.reproduction_conditions', 'LIKE', $like)
                  ->orWhere('information_object_i18n.physical_characteristics', 'LIKE', $like)
                  ->orWhere('information_object_i18n.finding_aids', 'LIKE', $like)
                  ->orWhere('information_object_i18n.location_of_originals', 'LIKE', $like)
                  ->orWhere('information_object_i18n.location_of_copies', 'LIKE', $like)
                  ->orWhere('information_object_i18n.related_units_of_description', 'LIKE', $like)
                  ->orWhere('information_object_i18n.rules', 'LIKE', $like)
                  ->orWhere('information_object_i18n.sources', 'LIKE', $like)
                  ->orWhere('information_object_i18n.revision_history', 'LIKE', $like)
                  ->orWhere('information_object_i18n.institution_responsible_identifier', 'LIKE', $like);

                // Notes (note_i18n.content via note.object_id)
                $q->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('note')
                        ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
                        ->whereColumn('note.object_id', 'information_object.id')
                        ->where('note_i18n.content', 'LIKE', $like);
                });

                // Creator names (actor_i18n via event table, type_id = creation)
                $q->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('event')
                        ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
                        ->whereColumn('event.information_object_id', 'information_object.id')
                        ->where('actor_i18n.authorized_form_of_name', 'LIKE', $like);
                });

                // Subject access points (taxonomy 35)
                $q->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation')
                        ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                        ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                        ->whereColumn('object_term_relation.object_id', 'information_object.id')
                        ->where('term.taxonomy_id', 35)
                        ->where('term_i18n.name', 'LIKE', $like);
                });

                // Place access points (taxonomy 42)
                $q->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation')
                        ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                        ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                        ->whereColumn('object_term_relation.object_id', 'information_object.id')
                        ->where('term.taxonomy_id', 42)
                        ->where('term_i18n.name', 'LIKE', $like);
                });

                // Genre access points (taxonomy 78)
                $q->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation')
                        ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                        ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                        ->whereColumn('object_term_relation.object_id', 'information_object.id')
                        ->where('term.taxonomy_id', 78)
                        ->where('term_i18n.name', 'LIKE', $like);
                });

                // Name access points (taxonomy 90)
                $q->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation')
                        ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                        ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                        ->whereColumn('object_term_relation.object_id', 'information_object.id')
                        ->where('term.taxonomy_id', 90)
                        ->where('term_i18n.name', 'LIKE', $like);
                });
            });
        }

        // Apply advanced search criteria (sq/sf/so rows from advanced search form)
        if (!empty($this->advancedCriteria)) {
            $query = $this->applyAdvancedCriteria($query, $this->advancedCriteria);
        }

        return $query;
    }

    protected function applyAdvancedCriteria($query, array $criteria)
    {
        $fieldMap = [
            'title' => 'information_object_i18n.title',
            'scopeAndContent' => 'information_object_i18n.scope_and_content',
            'archivalHistory' => 'information_object_i18n.archival_history',
            'extentAndMedium' => 'information_object_i18n.extent_and_medium',
            'arrangement' => 'information_object_i18n.arrangement',
            'accessConditions' => 'information_object_i18n.access_conditions',
            'reproductionConditions' => 'information_object_i18n.reproduction_conditions',
            'physicalCharacteristics' => 'information_object_i18n.physical_characteristics',
            'findingAids' => 'information_object_i18n.finding_aids',
            'locationOfOriginals' => 'information_object_i18n.location_of_originals',
            'locationOfCopies' => 'information_object_i18n.location_of_copies',
            'relatedUnits' => 'information_object_i18n.related_units_of_description',
            'rules' => 'information_object_i18n.rules',
            'sources' => 'information_object_i18n.sources',
            'appraisal' => 'information_object_i18n.appraisal',
            'accruals' => 'information_object_i18n.accruals',
            'alternateTitle' => 'information_object_i18n.alternate_title',
            'edition' => 'information_object_i18n.edition',
            'identifier' => 'information_object.identifier',
            'referenceCode' => 'information_object.identifier',
            'acquisition' => 'information_object_i18n.acquisition',
        ];

        foreach ($criteria as $i => $row) {
            $term = trim($row['query']);
            if ($term === '') continue;

            $like = '%' . addcslashes($term, '%_') . '%';
            $field = $row['field'] ?? '';
            $operator = ($i === 0) ? 'and' : ($row['operator'] ?? 'and');

            $applyWhere = function ($q) use ($like, $field, $fieldMap) {
                if ($field && isset($fieldMap[$field])) {
                    // Specific i18n/identifier field
                    $q->where($fieldMap[$field], 'LIKE', $like);
                } elseif ($field === 'creatorSearch') {
                    $q->whereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('event')
                            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
                            ->whereColumn('event.information_object_id', 'information_object.id')
                            ->where('actor_i18n.authorized_form_of_name', 'LIKE', $like);
                    });
                } elseif ($field === 'subjectSearch') {
                    $q->whereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('object_term_relation')
                            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                            ->whereColumn('object_term_relation.object_id', 'information_object.id')
                            ->where('term.taxonomy_id', 35)->where('term_i18n.name', 'LIKE', $like);
                    });
                } elseif ($field === 'placeSearch') {
                    $q->whereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('object_term_relation')
                            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                            ->whereColumn('object_term_relation.object_id', 'information_object.id')
                            ->where('term.taxonomy_id', 42)->where('term_i18n.name', 'LIKE', $like);
                    });
                } elseif ($field === 'genreSearch') {
                    $q->whereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('object_term_relation')
                            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                            ->whereColumn('object_term_relation.object_id', 'information_object.id')
                            ->where('term.taxonomy_id', 78)->where('term_i18n.name', 'LIKE', $like);
                    });
                } elseif ($field === 'noteContent') {
                    $q->whereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('note')
                            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
                            ->whereColumn('note.object_id', 'information_object.id')
                            ->where('note_i18n.content', 'LIKE', $like);
                    });
                } else {
                    // "Any field" — search all columns + related tables
                    $q->where(function ($inner) use ($like, $fieldMap) {
                        foreach ($fieldMap as $col) {
                            $inner->orWhere($col, 'LIKE', $like);
                        }
                        $inner->orWhereExists(function ($sub) use ($like) {
                            $sub->select(DB::raw(1))->from('note')
                                ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
                                ->whereColumn('note.object_id', 'information_object.id')
                                ->where('note_i18n.content', 'LIKE', $like);
                        });
                        $inner->orWhereExists(function ($sub) use ($like) {
                            $sub->select(DB::raw(1))->from('event')
                                ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
                                ->whereColumn('event.information_object_id', 'information_object.id')
                                ->where('actor_i18n.authorized_form_of_name', 'LIKE', $like);
                        });
                    });
                }
            };

            if ($operator === 'not') {
                $query->where(function ($q) use ($applyWhere) {
                    $q->whereNot(function ($inner) use ($applyWhere) {
                        $applyWhere($inner);
                    });
                });
            } elseif ($operator === 'or') {
                $query->orWhere(function ($q) use ($applyWhere) {
                    $applyWhere($q);
                });
            } else {
                $query->where(function ($q) use ($applyWhere) {
                    $applyWhere($q);
                });
            }
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
            case 'referenceCode':
                $query->orderBy('information_object.identifier', $sortDir);
                break;
            case 'startDate':
                $query->orderByRaw("(SELECT MIN(e.start_date) FROM event e WHERE e.information_object_id = information_object.id) {$sortDir}");
                break;
            case 'endDate':
                $query->orderByRaw("(SELECT MAX(e.end_date) FROM event e WHERE e.information_object_id = information_object.id) {$sortDir}");
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
