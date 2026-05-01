<?php

/**
 * RepositoryBrowseService - Service for Heratio
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



namespace AhgRepositoryManage\Services;

use AhgCore\Services\BrowseService;
use AhgCore\Traits\WithCultureFallback;
use Illuminate\Support\Facades\DB;

class RepositoryBrowseService extends BrowseService
{
    use WithCultureFallback;

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
        // i18n columns via COALESCE(cur, fb) — see WithCultureFallback.
        return [
            'repository.id',
            DB::raw('COALESCE(actor_cur.authorized_form_of_name, actor_fb.authorized_form_of_name) AS name'),
            'actor.description_identifier as identifier',
            'object.updated_at',
            'slug.slug',
            DB::raw('COALESCE(ci_cur.region, ci_fb.region) AS region'),
            DB::raw('COALESCE(ci_cur.city, ci_fb.city) AS locality'),
        ];
    }

    protected function getBaseJoins($query)
    {
        // Culture-fallback on actor_i18n (Repository extends Actor in CTI).
        $this->joinI18nWithFallback($query, 'actor_i18n', 'repository', aliasPrefix: 'actor');

        $query
            ->leftJoin('actor', 'repository.id', '=', 'actor.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->leftJoin('contact_information as ci', 'repository.id', '=', 'ci.actor_id');

        // Culture-fallback on contact_information_i18n too. Different join
        // shape because contact_information rows are linked via ci.id, not
        // repository.id, so we hand it the right parent table + column.
        $this->joinI18nWithFallback(
            $query, 'contact_information_i18n', 'ci',
            parentColumn: 'id', i18nForeign: 'id', aliasPrefix: 'ci',
        );

        return $query
            // Show the row if EITHER cur OR fb has a non-empty name.
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('actor_cur.authorized_form_of_name')
                       ->where('actor_cur.authorized_form_of_name', '!=', '');
                })->orWhere(function ($qq) {
                    $qq->whereNotNull('actor_fb.authorized_form_of_name')
                       ->where('actor_fb.authorized_form_of_name', '!=', '');
                });
            })
            ->where('repository.id', '!=', 6); // Exclude root repository
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        $name   = "COALESCE(actor_cur.authorized_form_of_name, actor_fb.authorized_form_of_name)";
        $region = "COALESCE(ci_cur.region, ci_fb.region)";
        $city   = "COALESCE(ci_cur.city, ci_fb.city)";
        switch ($sort) {
            case 'alphabetic':
                $query->orderByRaw("{$name} {$sortDir}");
                break;
            case 'identifier':
                $query->orderBy('actor.description_identifier', $sortDir);
                $query->orderByRaw("{$name} {$sortDir}");
                break;
            case 'region':
                $query->orderByRaw("{$region} {$sortDir}");
                $query->orderByRaw("{$name} asc");
                break;
            case 'locality':
                $query->orderByRaw("{$city} {$sortDir}");
                $query->orderByRaw("{$name} asc");
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
            $name = "COALESCE(actor_cur.authorized_form_of_name, actor_fb.authorized_form_of_name)";
            $query->where(function ($q) use ($subquery, $name) {
                $q->whereRaw("{$name} LIKE ?", ["%{$subquery}%"])
                  ->orWhere('actor.description_identifier', 'LIKE', "%{$subquery}%");
            });
        }
        return $query;
    }

    protected function transformRow($row): array
    {
        // Resolve thematic area name
        $thematicArea = '';
        if ($row->id) {
            $ta = DB::table('object_term_relation')
                ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                ->join('term_i18n', function ($j) {
                    $j->on('term.id', '=', 'term_i18n.id')
                      ->where('term_i18n.culture', '=', $this->culture);
                })
                ->where('object_term_relation.object_id', $row->id)
                ->where('term.taxonomy_id', 72)
                ->value('term_i18n.name');
            $thematicArea = $ta ?: '';
        }

        // Check for logo
        $logoPath = '/uploads/r/' . ($row->slug ?? '') . '/conf/logo.png';
        $hasLogo = $row->slug && file_exists('/usr/share/nginx/archive/uploads/r/' . $row->slug . '/conf/logo.png');

        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'identifier' => $row->identifier ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
            'region' => $row->region ?? '',
            'locality' => $row->locality ?? '',
            'thematic_area' => $thematicArea,
            'logo' => $hasLogo ? $logoPath : null,
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
     * Get archive type facets.
     */
    public function getArchiveTypeFacets(): array
    {
        $rows = DB::table('repository')
            ->join('object_term_relation', 'repository.id', '=', 'object_term_relation.object_id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                  ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('term.taxonomy_id', 38) // Repository type taxonomy
            ->where('repository.id', '!=', 6)
            ->select('term.id', 'term_i18n.name', DB::raw('COUNT(DISTINCT repository.id) as cnt'))
            ->groupBy('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        $facets = [];
        foreach ($rows as $r) {
            if ($r->name) {
                $facets[$r->id] = ['name' => $r->name, 'count' => $r->cnt];
            }
        }
        return $facets;
    }

    /**
     * Get geographic subregion facets.
     */
    public function getSubregionFacets(): array
    {
        $rows = DB::table('repository')
            ->join('object_term_relation', 'repository.id', '=', 'object_term_relation.object_id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                  ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('term.taxonomy_id', 73) // Geographic subregion taxonomy
            ->where('repository.id', '!=', 6)
            ->select('term.id', 'term_i18n.name', DB::raw('COUNT(DISTINCT repository.id) as cnt'))
            ->groupBy('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        $facets = [];
        foreach ($rows as $r) {
            if ($r->name) {
                $facets[$r->id] = ['name' => $r->name, 'count' => $r->cnt];
            }
        }
        return $facets;
    }

    /**
     * Get language facets for sidebar.
     */
    public function getLanguageFacets(): array
    {
        $rows = DB::table('repository')
            ->join('repository_i18n', function ($j) {
                $j->on('repository.id', '=', 'repository_i18n.id');
            })
            ->where('repository.id', '!=', 6)
            ->whereNotNull('repository_i18n.culture')
            ->where('repository_i18n.culture', '!=', '')
            ->select('repository_i18n.culture', DB::raw('COUNT(DISTINCT repository.id) as cnt'))
            ->groupBy('repository_i18n.culture')
            ->orderBy('repository_i18n.culture')
            ->get();

        $facets = [];
        foreach ($rows as $r) {
            $langName = locale_get_display_language($r->culture, 'en') ?: $r->culture;
            $facets[$r->culture] = ['name' => ucfirst($langName), 'count' => $r->cnt];
        }
        return $facets;
    }

    /**
     * Get locality facets for sidebar.
     */
    public function getLocalityFacets(): array
    {
        $rows = DB::table('repository')
            ->join('contact_information', 'repository.id', '=', 'contact_information.actor_id')
            ->join('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                  ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->where('repository.id', '!=', 6)
            ->whereNotNull('contact_information_i18n.city')
            ->where('contact_information_i18n.city', '!=', '')
            ->select('contact_information_i18n.city as locality', DB::raw('COUNT(DISTINCT repository.id) as cnt'))
            ->groupBy('contact_information_i18n.city')
            ->orderBy('contact_information_i18n.city')
            ->get();

        $facets = [];
        foreach ($rows as $r) {
            $facets[$r->locality] = ['name' => $r->locality, 'count' => $r->cnt];
        }
        return $facets;
    }

    /**
     * Apply advanced filters.
     */
    public function browseAdvanced(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?: 'alphabetic';
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

            // Archive type filter
            if (!empty($params['archiveType'])) {
                $query->whereExists(function ($sub) use ($params) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation')
                        ->whereColumn('object_term_relation.object_id', 'repository.id')
                        ->where('object_term_relation.term_id', (int) $params['archiveType']);
                });
            }

            // Geographic subregion filter
            if (!empty($params['subregion'])) {
                $query->whereExists(function ($sub) use ($params) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation')
                        ->whereColumn('object_term_relation.object_id', 'repository.id')
                        ->where('object_term_relation.term_id', (int) $params['subregion']);
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
