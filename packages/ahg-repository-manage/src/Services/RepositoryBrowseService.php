<?php

namespace AhgRepositoryManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class RepositoryBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'repository';
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
            'repository.id',
            'actor_i18n.authorized_form_of_name as name',
            'actor.description_identifier as identifier',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->leftJoin('actor', 'repository.id', '=', 'actor.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('repository.id', '!=', 6); // Exclude root repository
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'alphabetic':
                $query->orderBy('actor_i18n.authorized_form_of_name', $sortDir);
                break;
            case 'identifier':
                $query->orderBy('actor.description_identifier', $sortDir);
                $query->orderBy('actor_i18n.authorized_form_of_name', $sortDir);
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        return $query;
    }

    protected function applySearch($query, string $subquery)
    {
        if ($subquery !== '') {
            $query->where(function ($q) use ($subquery) {
                $q->where('actor_i18n.authorized_form_of_name', 'LIKE', "%{$subquery}%")
                  ->orWhere('actor.description_identifier', 'LIKE', "%{$subquery}%");
            });
        }

        return $query;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'identifier' => $row->identifier ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }

    /**
     * Get thematic area facets for sidebar.
     */
    public function getThematicAreaFacets(): array
    {
        $rows = DB::table('repository')
            ->join('object_term_relation', 'repository.id', '=', 'object_term_relation.object_id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                  ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('term.taxonomy_id', 72) // Thematic area taxonomy
            ->where('repository.id', '!=', 6)
            ->select('term.id', 'term_i18n.name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        $facets = [];
        foreach ($rows as $row) {
            $facets[$row->id] = ['name' => $row->name, 'count' => $row->cnt];
        }
        return $facets;
    }

    /**
     * Get region facets for advanced search.
     */
    public function getRegionFacets(): array
    {
        $rows = DB::table('repository')
            ->join('contact_information', 'repository.id', '=', 'contact_information.actor_id')
            ->join('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                  ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->where('repository.id', '!=', 6)
            ->whereNotNull('contact_information_i18n.region')
            ->where('contact_information_i18n.region', '!=', '')
            ->select('contact_information_i18n.region', DB::raw('COUNT(DISTINCT repository.id) as cnt'))
            ->groupBy('contact_information_i18n.region')
            ->orderBy('contact_information_i18n.region')
            ->get();

        return $rows->toArray();
    }

    /**
     * Apply advanced filters.
     */
    public function browseAdvanced(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?? 'alphabetic';
        $sortDir = !empty($params['sortDir']) ? $params['sortDir'] : (($sort === 'lastUpdated') ? 'desc' : 'asc');
        $subquery = trim($params['subquery'] ?? '');

        try {
            $query = DB::table($this->getTable());
            $query = $this->getBaseJoins($query);
            $query->select($this->getBaseSelect());

            $query = $this->applySearch($query, $subquery);

            // Thematic area filter
            if (!empty($params['thematicArea'])) {
                $query->whereExists(function ($sub) use ($params) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation')
                        ->whereColumn('object_term_relation.object_id', 'repository.id')
                        ->where('object_term_relation.term_id', (int) $params['thematicArea']);
                });
            }

            // Region filter
            if (!empty($params['region'])) {
                $query->whereExists(function ($sub) use ($params) {
                    $sub->select(DB::raw(1))
                        ->from('contact_information')
                        ->join('contact_information_i18n', function ($j) {
                            $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                              ->where('contact_information_i18n.culture', '=', $this->culture);
                        })
                        ->whereColumn('contact_information.actor_id', 'repository.id')
                        ->where('contact_information_i18n.region', $params['region']);
                });
            }

            // Locality filter
            if (!empty($params['locality'])) {
                $query->whereExists(function ($sub) use ($params) {
                    $sub->select(DB::raw(1))
                        ->from('contact_information')
                        ->join('contact_information_i18n', function ($j) {
                            $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                              ->where('contact_information_i18n.culture', '=', $this->culture);
                        })
                        ->whereColumn('contact_information.actor_id', 'repository.id')
                        ->where('contact_information_i18n.city', 'LIKE', '%' . $params['locality'] . '%');
                });
            }

            // Has digital object filter
            if (!empty($params['hasDigitalObject'])) {
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('digital_object')
                        ->whereColumn('digital_object.object_id', 'repository.id');
                });
            }

            $total = $query->count();
            $query = $this->applySort($query, $sort, $sortDir);
            $rows = $query->skip($skip)->take($limit)->get();

            $hits = [];
            foreach ($rows as $row) {
                $hits[] = $this->transformRow($row);
            }

            return ['hits' => $hits, 'total' => $total, 'page' => $page, 'limit' => $limit];
        } catch (\Exception $e) {
            \Log::error(static::class . ' browseAdvanced error: ' . $e->getMessage());
            return ['hits' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
        }
    }
}
