<?php

namespace AhgActorManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class ActorBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'actor';
    }

    protected function getI18nTable(): string
    {
        return 'actor_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'authorized_form_of_name';
    }

    protected function getBaseSelect(): array
    {
        return [
            'actor.id',
            'actor_i18n.authorized_form_of_name as name',
            'actor.entity_type_id',
            'actor.description_identifier as identifier',
            'actor_i18n.dates_of_existence',
            'actor_i18n.history',
            'object.updated_at',
            'slug.slug',
            DB::raw('(SELECT do_thumb.path FROM digital_object do_master JOIN digital_object do_thumb ON do_thumb.parent_id = do_master.id AND do_thumb.usage_id = 113 WHERE do_master.object_id = actor.id LIMIT 1) as thumbnail_path'),
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('object.class_name', 'QubitActor')
            ->where('actor.id', '!=', 3)  // Exclude root actor
            ->where('actor.id', '!=', 4)  // Exclude default actor
            ->where(function ($q) {
                $q->whereNotNull('actor_i18n.authorized_form_of_name')
                  ->where('actor_i18n.authorized_form_of_name', '!=', '');
            });
    }

    public function browse(array $params): array
    {
        $result = parent::browse($params);

        // Batch resolve entity type names
        if (!empty($result['hits'])) {
            $entityTypeIds = array_filter(array_unique(array_column($result['hits'], 'entity_type_id')));
            $entityTypeNames = [];
            if (!empty($entityTypeIds)) {
                $names = DB::table('term_i18n')
                    ->whereIn('id', $entityTypeIds)
                    ->where('culture', $this->culture)
                    ->pluck('name', 'id');
                $entityTypeNames = $names->toArray();
            }
            $result['entityTypeNames'] = $entityTypeNames;
        }

        return $result;
    }

    /**
     * Get language facets for sidebar.
     * Returns array of [lang_code => ['name' => ..., 'count' => ...]]
     */
    public function getLanguageFacets(): array
    {
        $rows = DB::table('actor_i18n')
            ->join('actor', 'actor_i18n.id', '=', 'actor.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->where('object.class_name', 'QubitActor')
            ->where('actor.id', '!=', 3)
            ->where('actor.id', '!=', 4)
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->where('actor_i18n.authorized_form_of_name', '!=', '')
            ->select('actor_i18n.culture', DB::raw('COUNT(*) as cnt'))
            ->groupBy('actor_i18n.culture')
            ->orderBy('actor_i18n.culture')
            ->get();

        $facets = [];
        foreach ($rows as $r) {
            $langName = locale_get_display_language($r->culture, 'en') ?: $r->culture;
            $facets[$r->culture] = [
                'name' => ucfirst($langName),
                'count' => $r->cnt,
            ];
        }
        return $facets;
    }

    /**
     * Get entity type facet counts for sidebar.
     * Returns array of [term_id => ['name' => ..., 'count' => ...]]
     */
    public function getEntityTypeFacets(): array
    {
        // Entity type term IDs: 131=Corporate body, 132=Person, 133=Family (taxonomy_id=32)
        $rows = DB::table('actor')
            ->select('actor.entity_type_id', DB::raw('COUNT(*) as cnt'))
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('object.class_name', 'QubitActor')
            ->where('actor.id', '!=', 3)
            ->where('actor.id', '!=', 4)
            ->whereNotNull('actor.entity_type_id')
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->where('actor_i18n.authorized_form_of_name', '!=', '')
            ->groupBy('actor.entity_type_id')
            ->get();

        $facets = [];
        foreach ($rows as $row) {
            $name = DB::table('term_i18n')
                ->where('id', $row->entity_type_id)
                ->where('culture', $this->culture)
                ->value('name');

            if ($name) {
                $facets[$row->entity_type_id] = [
                    'name' => $name,
                    'count' => $row->cnt,
                ];
            }
        }

        return $facets;
    }

    /**
     * Get "Maintained by" (repository) facets.
     */
    public function getMaintainedByFacets(): array
    {
        $rows = DB::table('relation')
            ->join('actor_i18n as repo_name', function ($j) {
                $j->on('relation.object_id', '=', 'repo_name.id')
                  ->where('repo_name.culture', '=', $this->culture);
            })
            ->where('relation.type_id', 161) // isMaintenanceAgencyOf
            ->select('relation.object_id as id', 'repo_name.authorized_form_of_name as name', DB::raw('COUNT(DISTINCT relation.subject_id) as cnt'))
            ->groupBy('relation.object_id', 'repo_name.authorized_form_of_name')
            ->orderBy('repo_name.authorized_form_of_name')
            ->get();
        $facets = [];
        foreach ($rows as $r) { if ($r->name) $facets[$r->id] = ['name' => $r->name, 'count' => $r->cnt]; }
        return $facets;
    }

    /**
     * Get occupation facets from object_term_relation.
     */
    public function getOccupationFacets(): array
    {
        // Occupation taxonomy ID = 313 in AtoM
        $rows = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', $this->culture);
            })
            ->join('actor', 'object_term_relation.object_id', '=', 'actor.id')
            ->where('term.taxonomy_id', 313)
            ->where('actor.id', '!=', 3)->where('actor.id', '!=', 4)
            ->select('term.id', 'term_i18n.name', DB::raw('COUNT(DISTINCT object_term_relation.object_id) as cnt'))
            ->groupBy('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();
        $facets = [];
        foreach ($rows as $r) { if ($r->name) $facets[$r->id] = ['name' => $r->name, 'count' => $r->cnt]; }
        return $facets;
    }

    /**
     * Get place facets.
     */
    public function getPlaceFacets(): array
    {
        $rows = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', $this->culture);
            })
            ->join('actor', 'object_term_relation.object_id', '=', 'actor.id')
            ->where('term.taxonomy_id', 42)
            ->where('actor.id', '!=', 3)->where('actor.id', '!=', 4)
            ->select('term.id', 'term_i18n.name', DB::raw('COUNT(DISTINCT object_term_relation.object_id) as cnt'))
            ->groupBy('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->limit(20)
            ->get();
        $facets = [];
        foreach ($rows as $r) { if ($r->name) $facets[$r->id] = ['name' => $r->name, 'count' => $r->cnt]; }
        return $facets;
    }

    /**
     * Get subject facets.
     */
    public function getSubjectFacets(): array
    {
        $rows = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', $this->culture);
            })
            ->join('actor', 'object_term_relation.object_id', '=', 'actor.id')
            ->where('term.taxonomy_id', 35)
            ->where('actor.id', '!=', 3)->where('actor.id', '!=', 4)
            ->select('term.id', 'term_i18n.name', DB::raw('COUNT(DISTINCT object_term_relation.object_id) as cnt'))
            ->groupBy('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->limit(20)
            ->get();
        $facets = [];
        foreach ($rows as $r) { if ($r->name) $facets[$r->id] = ['name' => $r->name, 'count' => $r->cnt]; }
        return $facets;
    }

    /**
     * Get media type facets (actors with digital objects).
     */
    public function getMediaTypeFacets(): array
    {
        $rows = DB::table('digital_object')
            ->join('actor', 'digital_object.object_id', '=', 'actor.id')
            ->join('term_i18n', function ($j) {
                $j->on('digital_object.media_type_id', '=', 'term_i18n.id')
                  ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('actor.id', '!=', 3)->where('actor.id', '!=', 4)
            ->select('digital_object.media_type_id as id', 'term_i18n.name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('digital_object.media_type_id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();
        $facets = [];
        foreach ($rows as $r) { if ($r->name) $facets[$r->id] = ['name' => $r->name, 'count' => $r->cnt]; }
        return $facets;
    }

    /**
     * Get total actor count (excluding root/default).
     */
    public function getTotalCount(): int
    {
        return DB::table('actor')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('object.class_name', 'QubitActor')
            ->where('actor.id', '!=', 3)
            ->where('actor.id', '!=', 4)
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->where('actor_i18n.authorized_form_of_name', '!=', '')
            ->count();
    }

    /**
     * Get repositories for the advanced search dropdown.
     */
    public function getRepositories(): array
    {
        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture)
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->where('actor_i18n.authorized_form_of_name', '!=', '')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get()
            ->toArray();
    }

    /**
     * Apply advanced filters to the browse query.
     */
    protected function applyFilters($query, array $params)
    {
        // Entity type filter
        if (!empty($params['entityType'])) {
            $query->where('actor.entity_type_id', (int) $params['entityType']);
        }

        // Repository filter — actors related to a specific repository via event table
        if (!empty($params['repository'])) {
            $repoId = (int) $params['repository'];
            $query->whereExists(function ($sub) use ($repoId) {
                $sub->select(DB::raw(1))
                    ->from('event')
                    ->join('information_object', 'event.object_id', '=', 'information_object.id')
                    ->whereColumn('event.actor_id', 'actor.id')
                    ->where('information_object.repository_id', $repoId);
            });
        }

        // Has digital object
        if (!empty($params['hasDigitalObject'])) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('digital_object')
                    ->whereColumn('digital_object.object_id', 'actor.id');
            });
        }

        // Empty field filter
        if (!empty($params['emptyField'])) {
            $field = $params['emptyField'];
            $allowed = ['authorized_form_of_name', 'dates_of_existence', 'history', 'places',
                        'legal_status', 'general_context', 'description_identifier'];
            if (in_array($field, $allowed)) {
                if ($field === 'description_identifier') {
                    $query->where(function ($q) {
                        $q->whereNull('actor.description_identifier')
                          ->orWhere('actor.description_identifier', '');
                    });
                } else {
                    $query->where(function ($q) use ($field) {
                        $q->whereNull("actor_i18n.{$field}")
                          ->orWhere("actor_i18n.{$field}", '');
                    });
                }
            }
        }

        // Advanced search criteria (sq0, sf0, so0, sq1, sf1, so1, ...)
        for ($i = 0; $i < 10; $i++) {
            $term = trim($params["sq{$i}"] ?? '');
            if ($term === '') continue;

            $field = $params["sf{$i}"] ?? '';
            $bool = $params["so{$i}"] ?? 'and';

            $method = ($bool === 'not') ? 'whereNot' : (($bool === 'or') ? 'orWhere' : 'where');

            if ($field === '' || $field === 'any') {
                // Search all text fields
                $query->{$method}(function ($q) use ($term) {
                    $q->where('actor_i18n.authorized_form_of_name', 'LIKE', "%{$term}%")
                      ->orWhere('actor_i18n.history', 'LIKE', "%{$term}%")
                      ->orWhere('actor_i18n.places', 'LIKE', "%{$term}%")
                      ->orWhere('actor_i18n.legal_status', 'LIKE', "%{$term}%")
                      ->orWhere('actor_i18n.general_context', 'LIKE', "%{$term}%")
                      ->orWhere('actor_i18n.sources', 'LIKE', "%{$term}%");
                });
            } else {
                $colMap = [
                    'authorizedFormOfName' => 'actor_i18n.authorized_form_of_name',
                    'parallelNames' => 'actor_i18n.parallel_forms_of_name',
                    'otherNames' => 'actor_i18n.standardized_forms_of_name',
                    'datesOfExistence' => 'actor_i18n.dates_of_existence',
                    'history' => 'actor_i18n.history',
                    'places' => 'actor_i18n.places',
                    'legalStatus' => 'actor_i18n.legal_status',
                    'generalContext' => 'actor_i18n.general_context',
                    'descriptionIdentifier' => 'actor.description_identifier',
                    'institutionResponsibleIdentifier' => 'actor_i18n.institution_responsible_identifier',
                    'sources' => 'actor_i18n.sources',
                ];
                $col = $colMap[$field] ?? null;
                if ($col) {
                    $query->{$method}($col, 'LIKE', "%{$term}%");
                }
            }
        }

        return $query;
    }

    /**
     * Override parent browse to support advanced filters.
     */
    public function browseAdvanced(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?? 'alphabetic';
        $sortDir = $params['sortDir'] ?? (($sort === 'lastUpdated') ? 'desc' : 'asc');
        $subquery = trim($params['subquery'] ?? '');

        try {
            $query = DB::table($this->getTable());
            $query = $this->getBaseJoins($query);
            $query->select($this->getBaseSelect());

            $query = $this->applySearch($query, $subquery);
            $query = $this->applyFilters($query, $params);

            $total = $query->count();
            $query = $this->applySort($query, $sort, $sortDir);
            $rows = $query->skip($skip)->take($limit)->get();

            $hits = [];
            foreach ($rows as $row) {
                $hits[] = $this->transformRow($row);
            }

            $result = [
                'hits' => $hits,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];

            // Batch resolve entity type names
            $entityTypeIds = array_filter(array_unique(array_column($hits, 'entity_type_id')));
            $entityTypeNames = [];
            if (!empty($entityTypeIds)) {
                $names = DB::table('term_i18n')
                    ->whereIn('id', $entityTypeIds)
                    ->where('culture', $this->culture)
                    ->pluck('name', 'id');
                $entityTypeNames = $names->toArray();
            }
            $result['entityTypeNames'] = $entityTypeNames;

            return $result;
        } catch (\Exception $e) {
            \Log::error(static::class . ' browseAdvanced error: ' . $e->getMessage());
            return ['hits' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'entityTypeNames' => []];
        }
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'entity_type_id' => $row->entity_type_id ?? null,
            'identifier' => $row->identifier ?? '',
            'dates_of_existence' => $row->dates_of_existence ?? '',
            'history' => $row->history ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
            'thumbnail_path' => $row->thumbnail_path ?? '',
        ];
    }
}
