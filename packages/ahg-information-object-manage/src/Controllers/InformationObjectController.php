<?php

/**
 * InformationObjectController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgInformationObjectManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\SettingHelper;
use AhgInformationObjectManage\Services\InformationObjectBrowseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InformationObjectController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new InformationObjectBrowseService($culture);

        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('query', $request->get('subquery', '')),
        ];

        // Publication status filter: non-admin users only see published records
        if (!auth()->check() || !auth()->user()->is_admin) {
            $params['filters']['publication_status'] = 'published';
        }

        // Top-level filter: default browse shows only top-level descriptions (matching AtoM)
        // Disabled when a search query is present (AtoM searches all levels)
        $hasQuery = !empty($params['subquery']);
        $topLevel = $request->get('topLevelDescription', $request->get('topLevel', $hasQuery ? '0' : '1'));
        if (($topLevel === '1' || $topLevel === 'true') && !$hasQuery) {
            $params['filters']['top_level'] = true;
        }

        // Apply filters from request
        $repositoryId = $request->get('repository');
        $levelsId = $request->get('levels');
        $mediaTypeId = $request->get('mediatypes');

        $languageFilter = $request->get('languages');
        $collectionId = $request->get('collection');

        if ($repositoryId) {
            $params['filters']['repository_id'] = $repositoryId;
        }
        if ($levelsId) {
            $params['filters']['level_of_description_id'] = $levelsId;
        }
        if ($mediaTypeId) {
            $params['filters']['media_type_id'] = $mediaTypeId;
        }
        if ($languageFilter) {
            $params['filters']['language'] = $languageFilter;
        }
        if ($collectionId) {
            $params['filters']['collection_id'] = $collectionId;
        }

        // Parse advanced search criteria (sq0/sf0/so0, sq1/sf1/so1, ...)
        $advancedCriteria = [];
        for ($i = 0; $i < 10; $i++) {
            $sq = $request->get("sq{$i}");
            if ($sq !== null && trim($sq) !== '') {
                $advancedCriteria[] = [
                    'query'    => $sq,
                    'field'    => $request->get("sf{$i}", ''),
                    'operator' => $request->get("so{$i}", 'and'),
                ];
            }
        }
        if (!empty($advancedCriteria)) {
            $params['advancedCriteria'] = $advancedCriteria;
            $params['subquery'] = '';
        }

        // Parse advanced search filters
        if ($request->get('repo')) {
            $params['filters']['repository_id'] = $request->get('repo');
        }
        // Support both 'levels' (sidebar facet) and 'level' (advanced search form)
        if ($request->get('levels')) {
            $params['filters']['level_of_description_id'] = $request->get('levels');
        }
        if ($request->get('level')) {
            $params['filters']['level_of_description_id'] = $request->get('level');
        }
        if ($request->filled('hasDigital')) {
            $params['filters']['has_digital'] = $request->get('hasDigital');
        }
        if ($request->get('startDate')) {
            $params['filters']['start_date'] = $request->get('startDate');
        }
        if ($request->get('endDate')) {
            $params['filters']['end_date'] = $request->get('endDate');
        }
        if ($request->get('rangeType')) {
            $params['filters']['range_type'] = $request->get('rangeType');
        }

        // Top-level description autocomplete (collection filter from advanced search)
        if ($request->get('collection')) {
            $params['filters']['collection_id'] = $request->get('collection');
        }

        // General material designation filter (taxonomy 50)
        if ($request->get('materialDesignation')) {
            $params['filters']['general_material_designation_id'] = $request->get('materialDesignation');
        }

        // Copyright status filter (taxonomy 69)
        if ($request->get('copyrightStatus')) {
            $params['filters']['copyright_status_id'] = $request->get('copyrightStatus');
        }

        // Finding aid status filter
        if ($request->get('findingAidStatus')) {
            $params['filters']['finding_aid_status'] = $request->get('findingAidStatus');
        }

        $result = $service->browse($params);

        // Batch-resolve creators, dates, and publication statuses for result IDs
        $creators = [];
        $dates = [];
        $pubStatuses = [];
        if (!empty($result['hits'])) {
            $resultIds = array_column($result['hits'], 'id');

            // Creators: actors linked via event table (type_id = 111 = creation)
            $creatorRows = DB::table('event')
                ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
                ->where('actor_i18n.culture', $culture)
                ->where('event.type_id', 111)
                ->whereIn('event.object_id', $resultIds)
                ->select('event.object_id', 'actor_i18n.authorized_form_of_name')
                ->get();
            foreach ($creatorRows as $row) {
                $creators[$row->object_id][] = $row->authorized_form_of_name;
            }

            // Dates: event start_date/end_date per IO
            $dateRows = DB::table('event')
                ->whereIn('event.object_id', $resultIds)
                ->whereNotNull('event.start_date')
                ->select('event.object_id', 'event.start_date', 'event.end_date')
                ->get();
            foreach ($dateRows as $row) {
                if (!isset($dates[$row->object_id])) {
                    $dates[$row->object_id] = [];
                }
                $start = $row->start_date ? substr($row->start_date, 0, 10) : null;
                $end = $row->end_date ? substr($row->end_date, 0, 10) : null;
                if ($start && $end && $start !== $end) {
                    $dates[$row->object_id][] = $start . ' - ' . $end;
                } elseif ($start) {
                    $dates[$row->object_id][] = $start;
                }
            }

            // Publication statuses: status table (type_id=158)
            if (auth()->check() && auth()->user()->is_admin) {
                $statusRows = DB::table('status')
                    ->whereIn('status.object_id', $resultIds)
                    ->where('status.type_id', 158)
                    ->select('status.object_id', 'status.status_id')
                    ->get();
                foreach ($statusRows as $row) {
                    $pubStatuses[$row->object_id] = $row->status_id;
                }
            }
        }

        $pager = new SimplePager($result);

        // Get list of repositories for filter dropdown
        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // ── Facet aggregation queries ──

        // Level of description facet
        $levelFacets = DB::table('information_object')
            ->join('term_i18n', 'information_object.level_of_description_id', '=', 'term_i18n.id')
            ->where('term_i18n.culture', $culture)
            ->where('information_object.id', '!=', 1)
            ->whereNotNull('information_object.level_of_description_id')
            ->select(
                'information_object.level_of_description_id as id',
                'term_i18n.name as label',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('information_object.level_of_description_id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // Repository facet
        $repoFacets = DB::table('information_object')
            ->join('repository', 'information_object.repository_id', '=', 'repository.id')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->where('information_object.id', '!=', 1)
            ->whereNotNull('information_object.repository_id')
            ->select(
                'information_object.repository_id as id',
                'actor_i18n.authorized_form_of_name as label',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('information_object.repository_id', 'actor_i18n.authorized_form_of_name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // Media type facet
        $mediaFacets = DB::table('digital_object')
            ->join('term_i18n', 'digital_object.media_type_id', '=', 'term_i18n.id')
            ->where('term_i18n.culture', $culture)
            ->whereNotNull('digital_object.media_type_id')
            ->select(
                'digital_object.media_type_id as id',
                'term_i18n.name as label',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('digital_object.media_type_id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // Creator facet (actors linked as creators via event table, event_type_id = 111 = creation)
        $creatorFacets = DB::table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->where('event.type_id', 111)
            ->whereNotNull('event.actor_id')
            ->select(
                'event.actor_id as id',
                'actor_i18n.authorized_form_of_name as label',
                DB::raw('COUNT(DISTINCT event.object_id) as count')
            )
            ->groupBy('event.actor_id', 'actor_i18n.authorized_form_of_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Subject facet (terms linked via object_term_relation, taxonomy_id = 35)
        $subjectFacets = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term_i18n.culture', $culture)
            ->where('term.taxonomy_id', 35)
            ->select(
                'term.id',
                'term_i18n.name as label',
                DB::raw('COUNT(DISTINCT object_term_relation.object_id) as count')
            )
            ->groupBy('term.id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Place facet (terms linked via object_term_relation, taxonomy_id = 42)
        $placeFacets = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term_i18n.culture', $culture)
            ->where('term.taxonomy_id', 42)
            ->select(
                'term.id',
                'term_i18n.name as label',
                DB::raw('COUNT(DISTINCT object_term_relation.object_id) as count')
            )
            ->groupBy('term.id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Genre facet (terms linked via object_term_relation, taxonomy_id = 78)
        $genreFacets = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term_i18n.culture', $culture)
            ->where('term.taxonomy_id', 78)
            ->select(
                'term.id',
                'term_i18n.name as label',
                DB::raw('COUNT(DISTINCT object_term_relation.object_id) as count')
            )
            ->groupBy('term.id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Copyright status facet (taxonomy 69 via relation + rights tables)
        $copyrightFacets = DB::table('relation')
            ->join('rights', 'relation.object_id', '=', 'rights.id')
            ->join('term', 'rights.copyright_status_id', '=', 'term.id')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('information_object', 'relation.subject_id', '=', 'information_object.id')
            ->where('term_i18n.culture', $culture)
            ->where('term.taxonomy_id', 69)
            ->where('information_object.id', '!=', 1)
            ->whereNotNull('rights.copyright_status_id')
            ->select(
                'rights.copyright_status_id as id',
                'term_i18n.name as label',
                DB::raw('COUNT(DISTINCT relation.subject_id) as count')
            )
            ->groupBy('rights.copyright_status_id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // General material designation facet (taxonomy 50 via object_term_relation)
        $materialDesignationFacets = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('information_object', 'object_term_relation.object_id', '=', 'information_object.id')
            ->where('term_i18n.culture', $culture)
            ->where('term.taxonomy_id', 50)
            ->where('information_object.id', '!=', 1)
            ->select(
                'term.id',
                'term_i18n.name as label',
                DB::raw('COUNT(DISTINCT object_term_relation.object_id) as count')
            )
            ->groupBy('term.id', 'term_i18n.name')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // Finding aid status facet (yes/no based on information_object_i18n.finding_aids)
        $findingAidYes = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->where('information_object_i18n.culture', $culture)
            ->where('information_object.id', '!=', 1)
            ->whereNotNull('information_object_i18n.finding_aids')
            ->where('information_object_i18n.finding_aids', '!=', '')
            ->count();
        $findingAidNo = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->where('information_object_i18n.culture', $culture)
            ->where('information_object.id', '!=', 1)
            ->where(function ($q) {
                $q->whereNull('information_object_i18n.finding_aids')
                  ->orWhere('information_object_i18n.finding_aids', '=', '');
            })
            ->count();
        $findingAidFacetTerms = [];
        if ($findingAidYes > 0) {
            $findingAidFacetTerms[] = ['value' => 'yes', 'label' => 'Yes', 'count' => $findingAidYes];
        }
        if ($findingAidNo > 0) {
            $findingAidFacetTerms[] = ['value' => 'no', 'label' => 'No', 'count' => $findingAidNo];
        }

        // Language facet (count information_object_i18n rows grouped by culture)
        $languageRows = DB::table('information_object_i18n')
            ->join('information_object', 'information_object_i18n.id', '=', 'information_object.id')
            ->where('information_object.id', '!=', 1)
            ->whereNotNull('information_object_i18n.title')
            ->where('information_object_i18n.title', '!=', '')
            ->select('information_object_i18n.culture', DB::raw('COUNT(*) as cnt'))
            ->groupBy('information_object_i18n.culture')
            ->orderBy('information_object_i18n.culture')
            ->get();

        $languageFacets = [];
        foreach ($languageRows as $r) {
            $langName = locale_get_display_language($r->culture, 'en') ?: $r->culture;
            $languageFacets[$r->culture] = [
                'name' => ucfirst($langName),
                'count' => $r->cnt,
            ];
        }

        // Collection ("Part of") facet — top-level descriptions that have children
        $collectionFacets = DB::table('information_object as parent')
            ->join('information_object_i18n as parent_i18n', 'parent.id', '=', 'parent_i18n.id')
            ->join('slug as parent_slug', 'parent.id', '=', 'parent_slug.object_id')
            ->where('parent_i18n.culture', $culture)
            ->where('parent.parent_id', 1)
            ->where('parent.id', '!=', 1)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('information_object as child')
                    ->whereColumn('child.parent_id', 'parent.id');
            })
            ->select(
                'parent.id',
                'parent_i18n.title as label',
                'parent_slug.slug',
                DB::raw('(SELECT COUNT(*) FROM information_object child WHERE child.lft > parent.lft AND child.rgt < parent.rgt) as count')
            )
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM information_object child WHERE child.lft > parent.lft AND child.rgt < parent.rgt)'))
            ->limit(10)
            ->get();

        // Digital objects count (for "X results with digital objects" banner)
        $digitalObjectsCount = DB::table('digital_object')
            ->join('information_object', 'digital_object.object_id', '=', 'information_object.id')
            ->where('information_object.id', '!=', 1)
            ->count();

        // Levels of description for advanced search dropdown
        $levelsOfDescription = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 34)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Copyright status terms for advanced search dropdown (taxonomy 69)
        $copyrightStatuses = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 69)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        $facets = [
            'levels' => [
                'label' => 'Level of description',
                'terms' => $levelFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'repository' => [
                'label' => 'Repository',
                'terms' => $repoFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'creators' => [
                'label' => 'Creator',
                'terms' => $creatorFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'subjects' => [
                'label' => 'Subject',
                'terms' => $subjectFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'places' => [
                'label' => 'Place',
                'terms' => $placeFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'genres' => [
                'label' => 'Genre',
                'terms' => $genreFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'mediatypes' => [
                'label' => 'Media type',
                'terms' => $mediaFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'copyrightStatus' => [
                'label' => 'Copyright status',
                'terms' => $copyrightFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'materialDesignation' => [
                'label' => 'General material designation',
                'terms' => $materialDesignationFacets->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'count' => $t->count])->toArray(),
            ],
            'findingAidStatus' => [
                'label' => 'Finding aid',
                'terms' => $findingAidFacetTerms,
            ],
        ];

        // ── Filter tags (active filter pills) ──
        $filterTags = [];

        if ($levelsId) {
            $levelName = DB::table('term_i18n')->where('id', $levelsId)->where('culture', $culture)->value('name');
            if ($levelName) {
                $filterTags[] = [
                    'label' => 'Level: ' . $levelName,
                    'removeUrl' => route('informationobject.browse', $request->except(['levels', 'page'])),
                ];
            }
        }

        if ($repositoryId) {
            $repoName = DB::table('actor_i18n')->where('id', $repositoryId)->where('culture', $culture)->value('authorized_form_of_name');
            if ($repoName) {
                $filterTags[] = [
                    'label' => 'Repository: ' . $repoName,
                    'removeUrl' => route('informationobject.browse', $request->except(['repository', 'page'])),
                ];
            }
        }

        if ($mediaTypeId) {
            $mediaName = DB::table('term_i18n')->where('id', $mediaTypeId)->where('culture', $culture)->value('name');
            if ($mediaName) {
                $filterTags[] = [
                    'label' => 'Media type: ' . $mediaName,
                    'removeUrl' => route('informationobject.browse', $request->except(['mediatypes', 'page'])),
                ];
            }
        }

        // Additional filter tags for new facets
        $creatorId = $request->get('creators');
        if ($creatorId) {
            $creatorName = DB::table('actor_i18n')->where('id', $creatorId)->where('culture', $culture)->value('authorized_form_of_name');
            if ($creatorName) {
                $filterTags[] = [
                    'label' => 'Creator: ' . $creatorName,
                    'removeUrl' => route('informationobject.browse', $request->except(['creators', 'page'])),
                ];
            }
        }

        $subjectId = $request->get('subjects');
        if ($subjectId) {
            $subjectName = DB::table('term_i18n')->where('id', $subjectId)->where('culture', $culture)->value('name');
            if ($subjectName) {
                $filterTags[] = [
                    'label' => 'Subject: ' . $subjectName,
                    'removeUrl' => route('informationobject.browse', $request->except(['subjects', 'page'])),
                ];
            }
        }

        $placeId = $request->get('places');
        if ($placeId) {
            $placeName = DB::table('term_i18n')->where('id', $placeId)->where('culture', $culture)->value('name');
            if ($placeName) {
                $filterTags[] = [
                    'label' => 'Place: ' . $placeName,
                    'removeUrl' => route('informationobject.browse', $request->except(['places', 'page'])),
                ];
            }
        }

        $genreId = $request->get('genres');
        if ($genreId) {
            $genreName = DB::table('term_i18n')->where('id', $genreId)->where('culture', $culture)->value('name');
            if ($genreName) {
                $filterTags[] = [
                    'label' => 'Genre: ' . $genreName,
                    'removeUrl' => route('informationobject.browse', $request->except(['genres', 'page'])),
                ];
            }
        }

        $onlyMedia = $request->get('onlyMedia');
        if ($onlyMedia === '1' || $onlyMedia === 'true') {
            $filterTags[] = [
                'label' => 'With digital objects',
                'removeUrl' => route('informationobject.browse', $request->except(['onlyMedia', 'page'])),
            ];
        }

        if ($languageFilter) {
            $langDisplayName = locale_get_display_language($languageFilter, 'en') ?: $languageFilter;
            $filterTags[] = [
                'label' => 'Language: ' . ucfirst($langDisplayName),
                'removeUrl' => route('informationobject.browse', $request->except(['languages', 'page'])),
            ];
        }

        if ($collectionId) {
            $collectionName = DB::table('information_object_i18n')
                ->where('id', $collectionId)->where('culture', $culture)->value('title');
            if ($collectionName) {
                $filterTags[] = [
                    'label' => 'Part of: ' . $collectionName,
                    'removeUrl' => route('informationobject.browse', $request->except(['collection', 'page'])),
                ];
            }
        }

        // General material designation filter tag
        $materialDesignationFilter = $request->get('materialDesignation');
        if ($materialDesignationFilter) {
            $mdName = DB::table('term_i18n')->where('id', $materialDesignationFilter)->where('culture', $culture)->value('name');
            if ($mdName) {
                $filterTags[] = [
                    'label' => 'Material: ' . $mdName,
                    'removeUrl' => route('informationobject.browse', $request->except(['materialDesignation', 'page'])),
                ];
            }
        }

        // Copyright status filter tag
        $copyrightStatusFilter = $request->get('copyrightStatus');
        if ($copyrightStatusFilter) {
            $csName = DB::table('term_i18n')->where('id', $copyrightStatusFilter)->where('culture', $culture)->value('name');
            if ($csName) {
                $filterTags[] = [
                    'label' => 'Copyright: ' . $csName,
                    'removeUrl' => route('informationobject.browse', $request->except(['copyrightStatus', 'page'])),
                ];
            }
        }

        // Finding aid status filter tag
        $findingAidFilter = $request->get('findingAidStatus');
        if ($findingAidFilter) {
            $filterTags[] = [
                'label' => 'Finding aid: ' . ($findingAidFilter === 'yes' ? 'Yes' : 'No'),
                'removeUrl' => route('informationobject.browse', $request->except(['findingAidStatus', 'page'])),
            ];
        }

        // Level of description filter tag (from advanced search 'level' param)
        $levelFilter = $request->get('level');
        if ($levelFilter && !$levelsId) {
            $levelName = DB::table('term_i18n')->where('id', $levelFilter)->where('culture', $culture)->value('name');
            if ($levelName) {
                $filterTags[] = [
                    'label' => 'Level: ' . $levelName,
                    'removeUrl' => route('informationobject.browse', $request->except(['level', 'page'])),
                ];
            }
        }

        // Top-level description filter state
        $isTopLevel = ($topLevel === '1' || $topLevel === 'true') && !$hasQuery;

        // Resolve collection names for results that have a parent
        $collectionNames = [];
        if (!empty($result['hits'])) {
            $parentIds = array_filter(array_unique(array_column($result['hits'], 'parent_id')));
            // Remove root (id=1)
            $parentIds = array_filter($parentIds, fn($id) => $id != 1);
            if (!empty($parentIds)) {
                // For each parent, walk up to find the top-level ancestor title
                $collectionNames = DB::table('information_object')
                    ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', $culture)
                    ->where('information_object.parent_id', 1)
                    ->whereIn('information_object.id', function ($sub) use ($parentIds) {
                        // Get top-level ancestors: either the parent itself is top-level, or we find the ancestor
                        $sub->select('information_object.id')
                            ->from('information_object')
                            ->where('information_object.parent_id', 1)
                            ->whereIn('information_object.id', $parentIds);
                    })
                    ->pluck('information_object_i18n.title', 'information_object.id')
                    ->toArray();
            }
        }

        return view('ahg-io-manage::browse', [
            'pager' => $pager,
            'levelNames' => $result['levelNames'] ?? [],
            'repositoryNames' => $result['repositoryNames'] ?? [],
            'repositories' => $repositories,
            'selectedRepository' => $repositoryId,
            'facets' => $facets,
            'filterTags' => $filterTags,
            'digitalObjectsCount' => $digitalObjectsCount,
            'levelsOfDescription' => $levelsOfDescription,
            'copyrightStatuses' => $copyrightStatuses,
            'isTopLevel' => $isTopLevel,
            'languageFacets' => $languageFacets,
            'collectionFacets' => $collectionFacets,
            'collectionNames' => $collectionNames,
            'parentInfo' => $result['parentInfo'] ?? [],
            'creators' => $creators,
            'dates' => $dates,
            'pubStatuses' => $pubStatuses,
            'sortOptions' => [
                'lastUpdated' => 'Date modified',
                'alphabetic' => 'Title',
                'relevance' => 'Relevance',
                'identifier' => 'Identifier',
                'referenceCode' => 'Reference code',
                'startDate' => 'Start date',
                'endDate' => 'End date',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $culture = app()->getLocale();

        // Main information object
        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_standard',
                'information_object.display_standard_id',
                'information_object.collection_type_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.edition',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'information_object_i18n.institution_responsible_identifier',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$io) {
            // Slug may belong to a different entity — check object table and redirect
            $slugRow = DB::table('slug')->where('slug', $slug)->first();
            if ($slugRow) {
                $className = DB::table('object')->where('id', $slugRow->object_id)->value('class_name');
                $redirectMap = [
                    'QubitTerm' => '/term/' . $slug,
                    'QubitActor' => '/actor/' . $slug,
                    'QubitRepository' => '/repository/' . $slug,
                    'QubitDonor' => '/donor/' . $slug,
                    'QubitAccession' => '/accession/' . $slug,
                    'QubitRightsHolder' => '/rightsholder/' . $slug,
                    'QubitFunctionObject' => '/function/' . $slug,
                    'QubitPhysicalObject' => '/physicalobject/' . $slug,
                ];
                if (isset($redirectMap[$className])) {
                    return redirect($redirectMap[$className]);
                }
            }

            // Admin: show detailed diagnostic instead of generic 404
            if (auth()->check()) {
                $diagnostics = ['slug' => $slug, 'issues' => []];

                // Check if an IO exists without a slug matching this URL
                $orphan = DB::table('information_object as io')
                    ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                        $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                    })
                    ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                    ->whereNull('s.slug')
                    ->where('io.id', '>', 1)
                    ->select('io.id', 'i18n.title', 'io.parent_id', 'io.lft', 'io.rgt', 'io.source_culture')
                    ->limit(20)
                    ->get();

                if ($orphan->isNotEmpty()) {
                    $diagnostics['issues'][] = [
                        'type' => 'missing_slugs',
                        'message' => $orphan->count() . ' information object(s) have no slug and cannot be accessed via URL.',
                        'records' => $orphan->map(fn($r) => [
                            'id' => $r->id,
                            'title' => $r->title ?? '[Untitled]',
                            'parent_id' => $r->parent_id,
                            'has_nested_set' => $r->lft !== null,
                        ])->toArray(),
                    ];
                }

                if ($slugRow && !isset($redirectMap[$className ?? ''])) {
                    $diagnostics['issues'][] = [
                        'type' => 'unknown_class',
                        'message' => "Slug '{$slug}' exists (object_id={$slugRow->object_id}) but class '{$className}' has no route mapping.",
                    ];
                }

                if (!$slugRow && $orphan->isEmpty()) {
                    $diagnostics['issues'][] = [
                        'type' => 'not_found',
                        'message' => "No slug '{$slug}' found in the database and no orphan records detected.",
                    ];
                }

                return response()->view('ahg-io-manage::errors.admin-404', [
                    'diagnostics' => $diagnostics,
                ], 404);
            }

            abort(404);
        }

        // Level of description name
        $levelName = null;
        if ($io->level_of_description_id) {
            $levelName = DB::table('term_i18n')
                ->where('id', $io->level_of_description_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Repository (direct or inherited from nearest ancestor — matching AtoM behaviour)
        $repository = null;
        $repoId = $io->repository_id;
        if (!$repoId && $io->parent_id && $io->parent_id != 1) {
            // Walk up the tree to find the nearest ancestor with a repository_id
            $ancestorId = $io->parent_id;
            $maxDepth = 50; // safety limit
            while ($ancestorId && $ancestorId != 1 && $maxDepth-- > 0) {
                $ancestor = DB::table('information_object')
                    ->where('id', $ancestorId)
                    ->select('repository_id', 'parent_id')
                    ->first();
                if (!$ancestor) {
                    break;
                }
                if ($ancestor->repository_id) {
                    $repoId = $ancestor->repository_id;
                    break;
                }
                $ancestorId = $ancestor->parent_id;
            }
        }
        if ($repoId) {
            $repository = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->where('repository.id', $repoId)
                ->where('actor_i18n.culture', $culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
                ->first();
        }

        // Events (dates)
        $events = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event_i18n.culture', $culture)
            ->select(
                'event.id',
                'event.type_id',
                'event.actor_id',
                'event.start_date',
                'event.end_date',
                'event_i18n.date as date_display',
                'event_i18n.name as event_name'
            )
            ->get();

        // Resolve event type names
        $eventTypeIds = $events->pluck('type_id')->filter()->unique()->values()->toArray();
        $eventTypeNames = [];
        if (!empty($eventTypeIds)) {
            $eventTypeNames = DB::table('term_i18n')
                ->whereIn('id', $eventTypeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Creators (events where type_id = 111 = creation) — include history and entity type
        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('slug', 'event.actor_id', '=', 'slug.object_id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111) // Creation event
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select(
                'event.actor_id as id',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.history',
                'actor_i18n.dates_of_existence',
                'actor.entity_type_id',
                'slug.slug'
            )
            ->distinct()
            ->get();

        // Digital objects (organized by usage type: master, reference, thumbnail)
        $digitalObjects = DigitalObjectService::getForObject($io->id);

        // Notes
        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')
            ->get();

        // Resolve note type names
        $noteTypeIds = $notes->pluck('type_id')->filter()->unique()->values()->toArray();
        $noteTypeNames = [];
        if (!empty($noteTypeIds)) {
            $noteTypeNames = DB::table('term_i18n')
                ->whereIn('id', $noteTypeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Children (child information objects)
        $children = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.parent_id', $io->id)
            ->where('information_object_i18n.culture', $culture)
            ->orderBy('information_object.lft')
            ->select(
                'information_object.id',
                'information_object.level_of_description_id',
                'information_object_i18n.title',
                'slug.slug'
            )
            ->get();

        // Resolve child level names
        $childLevelIds = $children->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        $childLevelNames = [];
        if (!empty($childLevelIds)) {
            $childLevelNames = DB::table('term_i18n')
                ->whereIn('id', $childLevelIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Child thumbnails for image carousel (matching AtoM imageflow component)
        $childThumbnails = collect();
        $childIds = $children->pluck('id')->toArray();
        if (!empty($childIds)) {
            $childThumbnails = DB::table('digital_object')
                ->join('slug', 'digital_object.object_id', '=', 'slug.object_id')
                ->join('information_object_i18n', function ($join) use ($culture) {
                    $join->on('digital_object.object_id', '=', 'information_object_i18n.id')
                         ->where('information_object_i18n.culture', '=', $culture);
                })
                ->whereIn('digital_object.object_id', $childIds)
                ->where('digital_object.usage_id', 142) // Thumbnail usage
                ->select(
                    'digital_object.id',
                    'digital_object.object_id',
                    'digital_object.name',
                    'digital_object.path',
                    'digital_object.mime_type',
                    'digital_object.byte_size',
                    'slug.slug',
                    'information_object_i18n.title'
                )
                ->limit(10) // Limit carousel items like AtoM
                ->get();
        }
        $childThumbnailTotal = !empty($childIds) ? DB::table('digital_object')
            ->whereIn('digital_object.object_id', $childIds)
            ->where('digital_object.usage_id', 142)
            ->count() : 0;

        // Parent breadcrumb chain (walk up the tree)
        $breadcrumbs = [];
        $parentId = $io->parent_id;
        while ($parentId && $parentId != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $parentId)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')
                ->first();

            if (!$parent) {
                break;
            }

            array_unshift($breadcrumbs, $parent);
            $parentId = $parent->parent_id;
        }

        // Subject access points (taxonomy_id = 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name', 'slug.slug')
            ->get();

        // Place access points (taxonomy_id = 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name', 'slug.slug')
            ->get();

        // Name access points (via relation table — actors linked as name access points)
        $nameAccessPoints = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->leftJoin('slug', 'relation.object_id', '=', 'slug.object_id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 161) // Name access point relation
            ->where('actor_i18n.culture', $culture)
            ->select('actor_i18n.authorized_form_of_name as name', 'slug.slug')
            ->get();

        // Genre access points (taxonomy_id = 78)
        $genres = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 78)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name', 'slug.slug')
            ->get();

        // Language of material
        $languages = DB::table('information_object')
            ->join('object_term_relation', 'information_object.id', '=', 'object_term_relation.object_id')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('information_object.id', $io->id)
            ->where('term.taxonomy_id', 7) // Language taxonomy
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Publication status (from status table — type_id=158 is publication status)
        $publicationStatus = null;
        $publicationStatusId = null;
        $statusRow = DB::table('status')
            ->where('object_id', $io->id)
            ->where('type_id', 158)
            ->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
            $publicationStatus = DB::table('term_i18n')
                ->where('id', $statusRow->status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Function relations (function_object table may not exist in all installs)
        $functionRelations = collect();
        try {
            $functionRelations = DB::table('relation')
                ->join('function_object', 'relation.subject_id', '=', 'function_object.id')
                ->join('function_i18n', 'function_object.id', '=', 'function_i18n.id')
                ->join('slug', 'function_object.id', '=', 'slug.object_id')
                ->where('relation.object_id', $io->id)
                ->where('function_i18n.culture', $culture)
                ->select('function_object.id', 'function_i18n.authorized_form_of_name as name', 'slug.slug')
                ->get();
        } catch (\Exception $e) {
            // function_object table may not exist
        }

        // Alternative identifiers (from property table)
        $alternativeIdentifiers = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'alternativeIdentifiers')
            ->where('property_i18n.culture', $culture)
            ->select('property_i18n.value')
            ->get();

        // Physical storage (relation type 151 = HAS_PHYSICAL_OBJECT)
        $physicalObjects = DB::table('relation')
            ->join('physical_object', 'relation.object_id', '=', 'physical_object.id')
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->leftJoin('slug', 'physical_object.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 151)
            ->where('physical_object_i18n.culture', $culture)
            ->select('physical_object.id', 'physical_object_i18n.name', 'physical_object_i18n.description', 'physical_object_i18n.location', 'physical_object.type_id', 'slug.slug')
            ->get();

        // Resolve physical object type names
        $physicalObjectTypeIds = $physicalObjects->pluck('type_id')->filter()->unique()->values()->toArray();
        $physicalObjectTypeNames = [];
        if (!empty($physicalObjectTypeIds)) {
            $physicalObjectTypeNames = DB::table('term_i18n')
                ->whereIn('id', $physicalObjectTypeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Rights (authenticated users only, linked via relation table)
        $rights = collect();
        $extendedRights = collect();
        $extendedRightsTkLabels = [];
        $activeEmbargo = null;
        if (auth()->check()) {
            try {
                $rights = DB::table('relation')
                    ->join('rights', 'relation.object_id', '=', 'rights.id')
                    ->join('rights_i18n', 'rights.id', '=', 'rights_i18n.id')
                    ->where('relation.subject_id', $io->id)
                    ->where('relation.type_id', 168) // RIGHT relation type
                    ->where('rights_i18n.culture', $culture)
                    ->select('rights.*', 'rights_i18n.rights_note')
                    ->get();
            } catch (\Exception $e) {
                // rights table structure may vary
            }

            // Extended rights (from extended_rights + extended_rights_i18n tables)
            $erService = new \AhgInformationObjectManage\Services\ExtendedRightsService($culture);
            try {
                $extendedRights = $erService->getExtendedRights($io->id);
                foreach ($extendedRights as $er) {
                    $extendedRightsTkLabels[$er->id] = $erService->getTkLabelsForRights($er->id);
                }
            } catch (\Exception $e) {
                // extended_rights tables may not exist in all installs
            }

            // Active embargo for sidebar display
            try {
                $activeEmbargo = $erService->getActiveEmbargo($io->id);
            } catch (\Exception $e) {
                // embargo table may not exist
            }
        }

        // Approved NER entity count (for PDF Entity Overlay link in sidebar)
        $nerEntityCount = 0;
        try {
            $nerEntityCount = DB::table('ahg_ner_entity')
                ->where('object_id', $io->id)
                ->where('status', 'approved')
                ->count();
        } catch (\Exception $e) {
            // ahg_ner_entity table may not exist in all installs
        }

        // Accessions (via relation table)
        $accessions = collect();
        try {
            $accessions = DB::table('relation')
                ->join('accession', 'relation.object_id', '=', 'accession.id')
                ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
                ->join('slug', 'accession.id', '=', 'slug.object_id')
                ->where('relation.subject_id', $io->id)
                ->where('accession_i18n.culture', $culture)
                ->select('accession.id', 'accession.identifier', 'accession_i18n.title', 'slug.slug')
                ->get();
        } catch (\Exception $e) {
            // accession table may not exist
        }

        // Description status name
        $descriptionStatusName = null;
        if ($io->description_status_id) {
            $descriptionStatusName = DB::table('term_i18n')
                ->where('id', $io->description_status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Description detail name
        $descriptionDetailName = null;
        if ($io->description_detail_id) {
            $descriptionDetailName = DB::table('term_i18n')
                ->where('id', $io->description_detail_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Collection type name
        $collectionTypeName = null;
        if ($io->collection_type_id) {
            $collectionTypeName = DB::table('term_i18n')
                ->where('id', $io->collection_type_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Languages of description (from property table — serialized PHP arrays)
        $languagesOfDescriptionRaw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'languageOfDescription')
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        $languagesOfDescription = collect();
        if ($languagesOfDescriptionRaw) {
            $decoded = @unserialize($languagesOfDescriptionRaw);
            if (is_array($decoded) && !empty($decoded)) {
                $languagesOfDescription = collect($decoded);
            }
        }

        // Scripts of description (from property table — serialized PHP arrays)
        $scriptsOfDescriptionRaw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'scriptOfDescription')
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        $scriptsOfDescription = collect();
        if ($scriptsOfDescriptionRaw) {
            $decoded = @unserialize($scriptsOfDescriptionRaw);
            if (is_array($decoded) && !empty($decoded)) {
                $scriptsOfDescription = collect($decoded);
            }
        }

        // Language of material (from property table — serialized PHP arrays of ISO codes)
        $materialLanguagesRaw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'language')
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        $materialLanguages = collect();
        if ($materialLanguagesRaw) {
            $decoded = @unserialize($materialLanguagesRaw);
            if (is_array($decoded) && !empty($decoded)) {
                $materialLanguages = collect($decoded);
            }
        }

        // Script of material (from property table)
        $materialScriptsRaw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'script')
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        $materialScripts = collect();
        if ($materialScriptsRaw) {
            $decoded = @unserialize($materialScriptsRaw);
            if (is_array($decoded) && !empty($decoded)) {
                $materialScripts = collect($decoded);
            }
        }

        // Finding aid link — check if a finding aid file exists for the collection root
        $findingAid = null;
        $collectionRootId = $io->id;
        $collectionRootSlug = $io->slug;
        if ($io->parent_id && $io->parent_id != 1) {
            // Walk up to the top-level description (collection root)
            $rootId = $io->parent_id;
            while ($rootId && $rootId != 1) {
                $rootParent = DB::table('information_object')
                    ->where('id', $rootId)
                    ->select('id', 'parent_id')
                    ->first();
                if (!$rootParent || $rootParent->parent_id == 1) {
                    $collectionRootId = $rootId;
                    break;
                }
                $rootId = $rootParent->parent_id;
            }
            $collectionRootSlug = DB::table('slug')
                ->where('object_id', $collectionRootId)
                ->value('slug') ?? $io->slug;
        }
        // Check findingAidStatus property for the collection root
        $findingAidStatusValue = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $collectionRootId)
            ->where('property.name', 'findingAidStatus')
            ->value('property_i18n.value');
        if ($findingAidStatusValue) {
            $faStatus = (int) $findingAidStatusValue;
            $findingAid = (object) [
                'status' => $faStatus,
                'label' => $faStatus === 2 ? 'Uploaded finding aid' : ($faStatus === 1 ? 'Generated finding aid' : 'Finding aid'),
                'slug' => $collectionRootSlug,
            ];
        }

        // Related material descriptions (relation type_id = 176)
        $relatedMaterialDescriptions = collect();
        // Relations where this IO is the subject
        $relatedBySubject = DB::table('relation')
            ->join('information_object', 'relation.object_id', '=', 'information_object.id')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 176)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->get();
        // Relations where this IO is the object
        $relatedByObject = DB::table('relation')
            ->join('information_object', 'relation.subject_id', '=', 'information_object.id')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.object_id', $io->id)
            ->where('relation.type_id', 176)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->get();
        $relatedMaterialDescriptions = $relatedBySubject->merge($relatedByObject)->unique('id');

        // Museum metadata (CCO fields) — present when this IO has a museum_metadata row
        $museumMetadata = [];
        try {
            $mmRow = DB::table('museum_metadata')
                ->where('object_id', $io->id)
                ->first();
            if ($mmRow) {
                $museumMetadata = (array) $mmRow;
            }
        } catch (\Exception $e) {
            // museum_metadata table may not exist in all installs
        }

        // Provenance chain (from provenance_entry table — CCO custody history)
        $provenanceEntries = collect();
        try {
            $provenanceEntries = DB::table('provenance_entry')
                ->where('information_object_id', $io->id)
                ->orderBy('sequence', 'asc')
                ->get();
        } catch (\Exception $e) {
            // provenance_entry table may not exist in all installs
        }

        // Previous sibling (only if lft is set — records without nested set values have no siblings)
        $prevSibling = null;
        $nextSibling = null;
        if ($io->lft !== null) {
            $prevSibling = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.parent_id', $io->parent_id)
                ->where('information_object.lft', '<', $io->lft)
                ->where('information_object_i18n.culture', $culture)
                ->orderBy('information_object.lft', 'desc')
                ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
                ->first();

            // Next sibling
            $nextSibling = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.parent_id', $io->parent_id)
                ->where('information_object.lft', '>', $io->lft)
                ->where('information_object_i18n.culture', $culture)
                ->orderBy('information_object.lft', 'asc')
                ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
                ->first();
        }

        // Display standard name (for Administration area)
        $displayStandardName = null;
        if ($io->display_standard_id) {
            $displayStandardName = DB::table('term_i18n')
                ->where('id', $io->display_standard_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Display standard options (taxonomy_id for display standards)
        // AtoM uses taxonomy_id = 67 for "Display Standard" terms
        $displayStandardOptions = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 67)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();
        // If taxonomy 67 yields nothing, fallback — check if terms exist under another id
        if ($displayStandardOptions->isEmpty()) {
            $displayStandardOptions = collect([
                (object) ['id' => null, 'name' => 'ISAD(G)'],
            ]);
        }

        // Audit log enabled setting
        $auditLogEnabled = \AhgCore\Services\SettingHelper::isAuditLogEnabled();

        // Source language name (for Administration area)
        $sourceLanguageName = null;
        if ($io->source_culture) {
            $sourceLanguageName = \Locale::getDisplayLanguage($io->source_culture, $culture);
        }

        // Keymap entries (for Administration area — source name from imports)
        $keymapEntries = collect();
        try {
            $keymapEntries = DB::table('keymap')
                ->where('target_id', $io->id)
                ->whereNotNull('source_name')
                ->select('source_name')
                ->get();
        } catch (\Exception $e) {
            // keymap table may not exist
        }

        // Translation links: get available cultures for this IO (other than current)
        $translationLinks = [];
        $availableCultures = DB::table('information_object_i18n')
            ->where('id', $io->id)
            ->where('culture', '!=', $culture)
            ->pluck('title', 'culture');
        foreach ($availableCultures as $code => $title) {
            $langName = \Locale::getDisplayLanguage($code, $culture);
            $translationLinks[$code] = [
                'language' => ucfirst($langName),
                'name' => $title ?: ($io->title ?? '[Untitled]'),
            ];
        }

        // Digital object rights (rights linked to each digital object via relation)
        $digitalObjectRights = [];
        if (auth()->check() && isset($digitalObjects) && $digitalObjects['master']) {
            foreach (['master', 'reference', 'thumbnail'] as $usageKey) {
                $doObj = $digitalObjects[$usageKey] ?? null;
                if (!$doObj) {
                    continue;
                }
                try {
                    $doRights = DB::table('relation')
                        ->join('rights', 'relation.object_id', '=', 'rights.id')
                        ->join('rights_i18n', 'rights.id', '=', 'rights_i18n.id')
                        ->where('relation.subject_id', $doObj->id)
                        ->where('relation.type_id', 168)
                        ->where('rights_i18n.culture', $culture)
                        ->select('rights.*', 'rights_i18n.rights_note')
                        ->get();
                    if ($doRights->isNotEmpty()) {
                        // Get usage name from term_i18n
                        $usageName = DB::table('term_i18n')
                            ->where('id', $doObj->usage_id ?? 0)
                            ->where('culture', $culture)
                            ->value('name') ?? ucfirst($usageKey);
                        $digitalObjectRights[$usageKey] = [
                            'usageName' => $usageName,
                            'rights' => $doRights,
                        ];
                    }
                } catch (\Exception $e) {
                    // rights table structure may vary
                }
            }
        }

        // GLAM sector routing: redirect to sector-specific show page if not archive
        if ($io->level_of_description_id && Schema::hasTable('level_of_description_sector')) {
            $sector = DB::table('level_of_description_sector')
                ->where('term_id', $io->level_of_description_id)
                ->whereNotIn('sector', ['archive'])
                ->orderBy('display_order')
                ->value('sector');

            $sectorRoutes = [
                'library' => 'library.show',
                'museum'  => 'museum.show',
                'gallery' => 'gallery.show',
                'dam'     => 'dam.show',
            ];

            if ($sector && isset($sectorRoutes[$sector])) {
                try {
                    $targetRoute = $sectorRoutes[$sector];
                    if (\Illuminate\Support\Facades\Route::has($targetRoute)) {
                        return redirect()->route($targetRoute, $slug);
                    }
                } catch (\Exception $e) {
                    // Sector route not available — fall through to ISAD view
                }
            }
        }

        return view('ahg-io-manage::show', [
            'io' => $io,
            'levelName' => $levelName,
            'repository' => $repository,
            'events' => $events,
            'eventTypeNames' => $eventTypeNames,
            'creators' => $creators,
            'digitalObjects' => $digitalObjects,
            'notes' => $notes,
            'noteTypeNames' => $noteTypeNames,
            'children' => $children,
            'childLevelNames' => $childLevelNames,
            'breadcrumbs' => $breadcrumbs,
            'subjects' => $subjects,
            'places' => $places,
            'nameAccessPoints' => $nameAccessPoints,
            'genres' => $genres,
            'languages' => $languages,
            'publicationStatus' => $publicationStatus,
            'publicationStatusId' => $publicationStatusId,
            'functionRelations' => $functionRelations,
            'alternativeIdentifiers' => $alternativeIdentifiers,
            'physicalObjects' => $physicalObjects,
            'physicalObjectTypeNames' => $physicalObjectTypeNames,
            'rights' => $rights,
            'extendedRights' => $extendedRights,
            'extendedRightsTkLabels' => $extendedRightsTkLabels,
            'accessions' => $accessions,
            'descriptionStatusName' => $descriptionStatusName,
            'descriptionDetailName' => $descriptionDetailName,
            'collectionTypeName' => $collectionTypeName,
            'languagesOfDescription' => $languagesOfDescription,
            'scriptsOfDescription' => $scriptsOfDescription,
            'materialLanguages' => $materialLanguages,
            'materialScripts' => $materialScripts,
            'prevSibling' => $prevSibling,
            'nextSibling' => $nextSibling,
            'findingAid' => $findingAid,
            'relatedMaterialDescriptions' => $relatedMaterialDescriptions,
            'museumMetadata' => $museumMetadata,
            'collectionRootId' => $collectionRootId,
            'hasChildren' => ($io->rgt - $io->lft) > 1,
            'displayStandardName' => $displayStandardName,
            'sourceLanguageName' => $sourceLanguageName,
            'keymapEntries' => $keymapEntries,
            'translationLinks' => $translationLinks,
            'digitalObjectRights' => $digitalObjectRights,
            'activeEmbargo' => $activeEmbargo,
            'childThumbnails' => $childThumbnails,
            'childThumbnailTotal' => $childThumbnailTotal,
            'nerEntityCount' => $nerEntityCount,
            'displayStandardOptions' => $displayStandardOptions,
            'auditLogEnabled' => $auditLogEnabled,
            'provenanceEntries' => $provenanceEntries,
        ]);
    }

    /**
     * Update the display standard for an information object (Administration area).
     */
    public function updateDisplayStandard(Request $request, string $slug)
    {
        $io = DB::table('information_object')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt')
            ->first();

        if (!$io) {
            abort(404);
        }

        $displayStandardId = $request->input('display_standard_id');
        $updateDescendants = $request->boolean('update_descendants', false);

        // Update this IO
        DB::table('information_object')
            ->where('id', $io->id)
            ->update(['display_standard_id' => $displayStandardId ?: null]);

        // Optionally update all descendants
        if ($updateDescendants) {
            DB::table('information_object')
                ->where('lft', '>', $io->lft)
                ->where('rgt', '<', $io->rgt)
                ->update(['display_standard_id' => $displayStandardId ?: null]);
        }

        return redirect()->route('informationobject.show', $slug)
            ->with('success', 'Display standard updated.' . ($updateDescendants ? ' Descendants updated.' : ''));
    }

    /**
     * Print-friendly view for an information object.
     */
    public function print(string $slug)
    {
        // Reuse show() data gathering via a helper, then render print view
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_standard',
                'information_object.display_standard_id',
                'information_object.collection_type_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.edition',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'information_object_i18n.institution_responsible_identifier',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$io) {
            abort(404);
        }

        $levelName = null;
        if ($io->level_of_description_id) {
            $levelName = DB::table('term_i18n')->where('id', $io->level_of_description_id)->where('culture', $culture)->value('name');
        }

        $repository = null;
        if ($io->repository_id) {
            $repository = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->where('repository.id', $io->repository_id)->where('actor_i18n.culture', $culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')->first();
        }

        $events = DB::table('event')->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)->where('event_i18n.culture', $culture)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display', 'event_i18n.name as event_name')->get();

        $eventTypeIds = $events->pluck('type_id')->filter()->unique()->values()->toArray();
        $eventTypeNames = [];
        if (!empty($eventTypeIds)) {
            $eventTypeNames = DB::table('term_i18n')->whereIn('id', $eventTypeIds)->where('culture', $culture)->pluck('name', 'id')->toArray();
        }

        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('slug', 'event.actor_id', '=', 'slug.object_id')
            ->where('event.object_id', $io->id)->where('event.type_id', 111)->where('actor_i18n.culture', $culture)->whereNotNull('event.actor_id')
            ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name', 'actor_i18n.history', 'actor_i18n.dates_of_existence', 'actor.entity_type_id', 'slug.slug')
            ->distinct()->get();

        $notes = DB::table('note')->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')->get();

        $children = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.parent_id', $io->id)->where('information_object_i18n.culture', $culture)
            ->orderBy('information_object.lft')
            ->select('information_object.id', 'information_object.level_of_description_id', 'information_object_i18n.title', 'slug.slug')->get();

        $childLevelIds = $children->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        $childLevelNames = [];
        if (!empty($childLevelIds)) {
            $childLevelNames = DB::table('term_i18n')->whereIn('id', $childLevelIds)->where('culture', $culture)->pluck('name', 'id')->toArray();
        }

        $breadcrumbs = [];
        $parentId = $io->parent_id;
        while ($parentId && $parentId != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $parentId)->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')->first();
            if (!$parent) break;
            array_unshift($breadcrumbs, $parent);
            $parentId = $parent->parent_id;
        }

        $subjects = DB::table('object_term_relation')->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)->where('term.taxonomy_id', 35)->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')->get();

        $places = DB::table('object_term_relation')->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)->where('term.taxonomy_id', 42)->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')->get();

        $nameAccessPoints = DB::table('relation')->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)->where('relation.type_id', 161)->where('actor_i18n.culture', $culture)
            ->select('actor_i18n.authorized_form_of_name as name')->get();

        $genres = DB::table('object_term_relation')->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)->where('term.taxonomy_id', 78)->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')->get();

        $languages = DB::table('information_object')
            ->join('object_term_relation', 'information_object.id', '=', 'object_term_relation.object_id')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('information_object.id', $io->id)->where('term.taxonomy_id', 7)->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')->get();

        $publicationStatus = null;
        $statusRow = DB::table('status')->where('object_id', $io->id)->where('type_id', 158)->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatus = DB::table('term_i18n')->where('id', $statusRow->status_id)->where('culture', $culture)->value('name');
        }

        $functionRelations = collect();
        try {
            $functionRelations = DB::table('relation')
                ->join('function_object', 'relation.subject_id', '=', 'function_object.id')
                ->join('function_i18n', 'function_object.id', '=', 'function_i18n.id')
                ->join('slug', 'function_object.id', '=', 'slug.object_id')
                ->where('relation.object_id', $io->id)->where('function_i18n.culture', $culture)
                ->select('function_object.id', 'function_i18n.authorized_form_of_name as name', 'slug.slug')->get();
        } catch (\Exception $e) {}

        $alternativeIdentifiers = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'alternativeIdentifiers')->where('property_i18n.culture', $culture)
            ->select('property_i18n.value')->get();

        $physicalObjects = DB::table('relation')
            ->join('physical_object', 'relation.object_id', '=', 'physical_object.id')
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->where('relation.subject_id', $io->id)->where('relation.type_id', 151)->where('physical_object_i18n.culture', $culture)
            ->select('physical_object.id', 'physical_object_i18n.name', 'physical_object_i18n.description', 'physical_object_i18n.location', 'physical_object.type_id')->get();

        $physicalObjectTypeIds = $physicalObjects->pluck('type_id')->filter()->unique()->values()->toArray();
        $physicalObjectTypeNames = [];
        if (!empty($physicalObjectTypeIds)) {
            $physicalObjectTypeNames = DB::table('term_i18n')->whereIn('id', $physicalObjectTypeIds)->where('culture', $culture)->pluck('name', 'id')->toArray();
        }

        $rights = collect();
        if (auth()->check()) {
            try {
                $rights = DB::table('relation')->join('rights', 'relation.object_id', '=', 'rights.id')
                    ->join('rights_i18n', 'rights.id', '=', 'rights_i18n.id')
                    ->where('relation.subject_id', $io->id)->where('relation.type_id', 168)->where('rights_i18n.culture', $culture)
                    ->select('rights.*', 'rights_i18n.rights_note')->get();
            } catch (\Exception $e) {}
        }

        $accessions = collect();
        try {
            $accessions = DB::table('relation')
                ->join('accession', 'relation.object_id', '=', 'accession.id')
                ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
                ->join('slug', 'accession.id', '=', 'slug.object_id')
                ->where('relation.subject_id', $io->id)->where('accession_i18n.culture', $culture)
                ->select('accession.id', 'accession.identifier', 'accession_i18n.title', 'slug.slug')->get();
        } catch (\Exception $e) {}

        $descriptionStatusName = null;
        if ($io->description_status_id) {
            $descriptionStatusName = DB::table('term_i18n')->where('id', $io->description_status_id)->where('culture', $culture)->value('name');
        }
        $descriptionDetailName = null;
        if ($io->description_detail_id) {
            $descriptionDetailName = DB::table('term_i18n')->where('id', $io->description_detail_id)->where('culture', $culture)->value('name');
        }

        $languagesOfDescriptionRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'languageOfDescription')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $languagesOfDescription = collect();
        if ($languagesOfDescriptionRaw) { $decoded = @unserialize($languagesOfDescriptionRaw); if (is_array($decoded) && !empty($decoded)) { $languagesOfDescription = collect($decoded); } }

        $scriptsOfDescriptionRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'scriptOfDescription')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $scriptsOfDescription = collect();
        if ($scriptsOfDescriptionRaw) { $decoded = @unserialize($scriptsOfDescriptionRaw); if (is_array($decoded) && !empty($decoded)) { $scriptsOfDescription = collect($decoded); } }

        $materialScriptsRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'script')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $materialScripts = collect();
        if ($materialScriptsRaw) { $decoded = @unserialize($materialScriptsRaw); if (is_array($decoded) && !empty($decoded)) { $materialScripts = collect($decoded); } }

        return view('ahg-io-manage::print', [
            'io' => $io,
            'levelName' => $levelName,
            'repository' => $repository,
            'events' => $events,
            'eventTypeNames' => $eventTypeNames,
            'creators' => $creators,
            'notes' => $notes,
            'children' => $children,
            'childLevelNames' => $childLevelNames,
            'breadcrumbs' => $breadcrumbs,
            'subjects' => $subjects,
            'places' => $places,
            'nameAccessPoints' => $nameAccessPoints,
            'genres' => $genres,
            'languages' => $languages,
            'publicationStatus' => $publicationStatus,
            'functionRelations' => $functionRelations,
            'alternativeIdentifiers' => $alternativeIdentifiers,
            'physicalObjects' => $physicalObjects,
            'physicalObjectTypeNames' => $physicalObjectTypeNames,
            'rights' => $rights,
            'accessions' => $accessions,
            'descriptionStatusName' => $descriptionStatusName,
            'descriptionDetailName' => $descriptionDetailName,
            'languagesOfDescription' => $languagesOfDescription,
            'scriptsOfDescription' => $scriptsOfDescription,
            'materialScripts' => $materialScripts,
        ]);
    }

    /**
     * Fetch dropdown options used by edit and create forms.
     */
    private function getFormDropdowns(string $culture): array
    {
        // Level of description options (taxonomy_id = 34)
        $levels = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 34)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Repositories
        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // Description status options (taxonomy_id = 44)
        $descriptionStatuses = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 44)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Description detail options (taxonomy_id = 43)
        $descriptionDetails = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 43)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Display standard options (taxonomy_id = 52 — descriptive standards)
        $displayStandards = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 52)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Event type options (taxonomy_id = 40)
        $eventTypes = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 40)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Note types for general notes (ISAD)
        $noteTypes = collect([
            (object) ['id' => 125, 'name' => 'General note'],
            (object) ['id' => 174, 'name' => 'Language note'],
        ]);

        // Security classifications
        $securityClassifications = DB::table('security_classification')
            ->orderBy('level')
            ->select('id', 'name', 'level')
            ->get();

        // Watermark types
        $watermarkTypes = DB::table('watermark_type')
            ->orderBy('name')
            ->select('id', 'name', 'image_file')
            ->get();

        // Collection type options (taxonomy_id = 12)
        $collectionTypes = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 12)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        return compact('levels', 'repositories', 'descriptionStatuses', 'descriptionDetails', 'displayStandards', 'eventTypes', 'noteTypes', 'securityClassifications', 'watermarkTypes', 'collectionTypes');
    }

    /**
     * Show the edit form for an information object.
     */
    public function edit(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.oai_local_identifier',
                'information_object.level_of_description_id',
                'information_object.collection_type_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_standard',
                'information_object.display_standard_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.edition',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.institution_responsible_identifier',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$io) {
            abort(404);
        }

        $dropdowns = $this->getFormDropdowns($culture);

        // Museum metadata (CCO fields) — present when this IO has a museum_metadata row
        $museumMetadata = [];
        try {
            $mmRow = DB::table('museum_metadata')
                ->where('object_id', $io->id)
                ->first();
            if ($mmRow) {
                $museumMetadata = (array) $mmRow;
            }
        } catch (\Exception $e) {
            // museum_metadata table may not exist in all installs
        }

        // Events (dates) — multi-row with type, date display, start/end, actor
        $events = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event_i18n.culture', $culture)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display', 'event_i18n.name as event_name')
            ->get();
        // Resolve actor names for events
        foreach ($events as $evt) {
            $evt->actor_name = null;
            if ($evt->actor_id) {
                $evt->actor_name = DB::table('actor_i18n')->where('id', $evt->actor_id)->where('culture', $culture)->value('authorized_form_of_name');
            }
        }

        // Creators (events where type_id = 111)
        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name')
            ->distinct()
            ->get();

        // Notes (all note types)
        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.id', 'note.type_id', 'note_i18n.content')
            ->get();

        // Separate publication notes (type_id = 220) and archivist notes (type_id = 174)
        $publicationNotes = $notes->where('type_id', 220)->values();
        $archivistNotes = $notes->where('type_id', 174)->values();
        $generalNotes = $notes->whereNotIn('type_id', [220, 174])->values();

        // Subject access points (taxonomy_id = 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Place access points (taxonomy_id = 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Genre access points (taxonomy_id = 78)
        $genres = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 78)
            ->where('term_i18n.culture', $culture)
            ->select('term.id as term_id', 'term_i18n.name')
            ->get();

        // Name access points (via relation table, type_id = 161)
        $nameAccessPoints = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 161)
            ->where('actor_i18n.culture', $culture)
            ->select('relation.object_id as actor_id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // Alternative identifiers (from property table)
        $alternativeIdentifiers = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)
            ->where('property.name', 'alternativeIdentifiers')
            ->where('property_i18n.culture', $culture)
            ->select('property_i18n.value')
            ->get();

        // Publication status
        $publicationStatusId = null;
        $statusRow = DB::table('status')->where('object_id', $io->id)->where('type_id', 158)->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
        }

        // Languages/scripts of material (from property table, serialized)
        $materialLanguagesRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'language')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $materialLanguages = collect();
        if ($materialLanguagesRaw) { $decoded = @unserialize($materialLanguagesRaw); if (is_array($decoded)) { $materialLanguages = collect($decoded); } }

        $materialScriptsRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'script')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $materialScripts = collect();
        if ($materialScriptsRaw) { $decoded = @unserialize($materialScriptsRaw); if (is_array($decoded)) { $materialScripts = collect($decoded); } }

        // Languages/scripts of description (from property table, serialized)
        $languagesOfDescriptionRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'languageOfDescription')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $languagesOfDescription = collect();
        if ($languagesOfDescriptionRaw) { $decoded = @unserialize($languagesOfDescriptionRaw); if (is_array($decoded)) { $languagesOfDescription = collect($decoded); } }

        $scriptsOfDescriptionRaw = DB::table('property')->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $io->id)->where('property.name', 'scriptOfDescription')->where('property_i18n.culture', $culture)->value('property_i18n.value');
        $scriptsOfDescription = collect();
        if ($scriptsOfDescriptionRaw) { $decoded = @unserialize($scriptsOfDescriptionRaw); if (is_array($decoded)) { $scriptsOfDescription = collect($decoded); } }

        // Related material descriptions
        $relatedMaterialDescriptions = collect();
        try {
            $relatedMaterialDescriptions = DB::table('relation')
                ->join('information_object_i18n', 'relation.object_id', '=', 'information_object_i18n.id')
                ->join('slug', 'relation.object_id', '=', 'slug.object_id')
                ->where('relation.subject_id', $io->id)
                ->where('relation.type_id', 173) // Related material description relation
                ->where('information_object_i18n.culture', $culture)
                ->select('relation.object_id as id', 'information_object_i18n.title', 'slug.slug')
                ->get();
        } catch (\Exception $e) {}

        // Parent info for admin area
        $parentTitle = null;
        $parentSlug = null;
        if ($io->parent_id && $io->parent_id != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $io->parent_id)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object_i18n.title', 'slug.slug')
                ->first();
            if ($parent) {
                $parentTitle = $parent->title;
                $parentSlug = $parent->slug;
            }
        }

        // Form choices for security and other dropdowns
        $formChoices = [
            'securityLevels' => $dropdowns['securityClassifications'] ?? collect(),
            'displayStandards' => ($dropdowns['displayStandards'] ?? collect())->pluck('name', 'id'),
        ];

        return view('ahg-io-manage::edit', array_merge(
            [
                'io' => $io,
                'museumMetadata' => $museumMetadata,
                'events' => $events,
                'creators' => $creators,
                'notes' => $generalNotes,
                'publicationNotes' => $publicationNotes,
                'archivistNotes' => $archivistNotes,
                'subjects' => $subjects,
                'places' => $places,
                'genres' => $genres,
                'nameAccessPoints' => $nameAccessPoints,
                'alternativeIdentifiers' => $alternativeIdentifiers,
                'publicationStatusId' => $publicationStatusId,
                'materialLanguages' => $materialLanguages,
                'materialScripts' => $materialScripts,
                'languagesOfDescription' => $languagesOfDescription,
                'scriptsOfDescription' => $scriptsOfDescription,
                'relatedMaterialDescriptions' => $relatedMaterialDescriptions,
                'parentTitle' => $parentTitle,
                'parentSlug' => $parentSlug,
                'formChoices' => $formChoices,
            ],
            $dropdowns
        ));
    }

    /**
     * Show the create form for a new information object.
     */
    public function create(Request $request)
    {
        $culture = app()->getLocale();
        $parentId = $request->get('parent_id');

        // If parent_id provided, resolve parent title for display
        $parentTitle = null;
        if ($parentId) {
            $parentTitle = DB::table('information_object_i18n')
                ->where('id', $parentId)
                ->where('culture', $culture)
                ->value('title');
        }

        $dropdowns = $this->getFormDropdowns($culture);

        return view('ahg-io-manage::create', array_merge(
            [
                'parentId' => $parentId,
                'parentTitle' => $parentTitle,
            ],
            $dropdowns
        ));
    }

    /**
     * Update an existing information object.
     */
    public function update(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $request->validate([
            'title' => 'required|string|max:65535',
        ]);

        // Resolve the IO id from slug
        $io = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id')
            ->first();

        if (!$io) {
            abort(404);
        }

        $ioId = $io->id;

        // Update information_object table
        $ioUpdate = [
            'identifier' => $request->input('identifier'),
            'level_of_description_id' => $request->input('level_of_description_id') ?: null,
            'collection_type_id' => $request->input('collection_type_id') ?: null,
            'repository_id' => $request->input('repository_id') ?: null,
            'description_status_id' => $request->input('description_status_id') ?: null,
            'description_detail_id' => $request->input('description_detail_id') ?: null,
            'description_identifier' => $request->input('description_identifier'),
        ];

        // Only update source_standard and display_standard_id when explicitly submitted
        // (AtoM only sets these conditionally — writing them unconditionally causes DB delta mismatches)
        if ($request->has('source_standard')) {
            $ioUpdate['source_standard'] = $request->input('source_standard');
        }
        if ($request->has('display_standard_id')) {
            $ioUpdate['display_standard_id'] = $request->input('display_standard_id') ?: null;
        }

        DB::table('information_object')
            ->where('id', $ioId)
            ->update($ioUpdate);

        // Update information_object_i18n table
        DB::table('information_object_i18n')
            ->where('id', $ioId)
            ->where('culture', $culture)
            ->update([
                'title' => $request->input('title'),
                'alternate_title' => $request->input('alternate_title'),
                'edition' => $request->input('edition'),
                'extent_and_medium' => $request->input('extent_and_medium'),
                'archival_history' => $request->input('archival_history'),
                'acquisition' => $request->input('acquisition'),
                'scope_and_content' => $request->input('scope_and_content'),
                'appraisal' => $request->input('appraisal'),
                'accruals' => $request->input('accruals'),
                'arrangement' => $request->input('arrangement'),
                'access_conditions' => $request->input('access_conditions'),
                'reproduction_conditions' => $request->input('reproduction_conditions'),
                'physical_characteristics' => $request->input('physical_characteristics'),
                'finding_aids' => $request->input('finding_aids'),
                'location_of_originals' => $request->input('location_of_originals'),
                'location_of_copies' => $request->input('location_of_copies'),
                'related_units_of_description' => $request->input('related_units_of_description'),
                'institution_responsible_identifier' => $request->input('institution_responsible_identifier'),
                'rules' => $request->input('rules'),
                'sources' => $request->input('sources'),
                'revision_history' => $request->input('revision_history'),
            ]);

        // Save museum_metadata if this IO has CCO fields submitted
        try {
            $museumFields = $request->only([
                'work_type', 'object_type', 'classification', 'materials', 'techniques',
                'measurements', 'dimensions', 'creation_date_earliest', 'creation_date_latest',
                'inscription', 'inscriptions', 'condition_notes', 'provenance', 'style_period',
                'cultural_context', 'current_location', 'edition_description', 'state_description',
                'state_identification', 'facture_description', 'technique_cco', 'technique_qualifier',
                'orientation', 'physical_appearance', 'color', 'shape', 'condition_term',
                'condition_date', 'condition_description', 'condition_agent', 'treatment_type',
                'treatment_date', 'treatment_agent', 'treatment_description',
                'inscription_transcription', 'inscription_type', 'inscription_location',
                'inscription_language', 'inscription_translation', 'mark_type', 'mark_description',
                'mark_location', 'related_work_type', 'related_work_relationship',
                'related_work_label', 'related_work_id', 'current_location_repository',
                'current_location_geography', 'current_location_coordinates',
                'current_location_ref_number', 'creation_place', 'creation_place_type',
                'discovery_place', 'discovery_place_type', 'provenance_text', 'ownership_history',
                'legal_status', 'rights_type', 'rights_holder', 'rights_date', 'rights_remarks',
                'cataloger_name', 'cataloging_date', 'cataloging_institution', 'cataloging_remarks',
                'record_type', 'record_level', 'creator_identity', 'creator_role', 'creator_extent',
                'creator_qualifier', 'creator_attribution', 'creation_date_display',
                'creation_date_qualifier', 'style', 'period', 'cultural_group', 'movement',
                'school', 'dynasty', 'subject_indexing_type', 'subject_display', 'subject_extent',
                'historical_context', 'architectural_context', 'archaeological_context',
                'object_class', 'object_category', 'object_sub_category', 'edition_number',
                'edition_size',
            ]);
            if (!empty($museumFields)) {
                $museumService = new \AhgMuseum\Services\MuseumService($culture);
                $museumService->saveMuseumMetadata($ioId, $museumFields);
            }
        } catch (\Exception $e) {
            // museum_metadata table may not exist in all installs
        }

        // ---- Creators (event type 111) ----
        if ($request->has('_creatorsIncluded')) {
            $creatorIds = array_filter((array) $request->input('creatorIds', []));
            // Remove existing creator events for this IO
            DB::table('event')
                ->where('object_id', $ioId)
                ->where('type_id', 111)
                ->delete();
            foreach ($creatorIds as $actorId) {
                $actorId = (int) $actorId;
                if ($actorId <= 0) continue;
                $eventObjectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitEvent',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('event')->insert([
                    'id'        => $eventObjectId,
                    'object_id' => $ioId,
                    'actor_id'  => $actorId,
                    'type_id'   => 111,
                    'source_culture' => $culture,
                ]);
                DB::table('event_i18n')->insert([
                    'id'      => $eventObjectId,
                    'culture' => $culture,
                ]);
            }
        }

        // ---- Subject access points (taxonomy 35) ----
        if ($request->has('subjectAccessPointIds')) {
            DB::table('object_term_relation')
                ->where('object_id', $ioId)
                ->whereIn('term_id', function ($q) {
                    $q->select('id')->from('term')->where('taxonomy_id', 35);
                })
                ->delete();
            foreach (array_filter((array) $request->input('subjectAccessPointIds', [])) as $termId) {
                DB::table('object_term_relation')->insert([
                    'object_id' => $ioId,
                    'term_id'   => (int) $termId,
                ]);
            }
        }

        // ---- Place access points (taxonomy 42) ----
        if ($request->has('placeAccessPointIds')) {
            DB::table('object_term_relation')
                ->where('object_id', $ioId)
                ->whereIn('term_id', function ($q) {
                    $q->select('id')->from('term')->where('taxonomy_id', 42);
                })
                ->delete();
            foreach (array_filter((array) $request->input('placeAccessPointIds', [])) as $termId) {
                DB::table('object_term_relation')->insert([
                    'object_id' => $ioId,
                    'term_id'   => (int) $termId,
                ]);
            }
        }

        // ---- Genre access points (taxonomy 78) ----
        if ($request->has('genreAccessPointIds')) {
            DB::table('object_term_relation')
                ->where('object_id', $ioId)
                ->whereIn('term_id', function ($q) {
                    $q->select('id')->from('term')->where('taxonomy_id', 78);
                })
                ->delete();
            foreach (array_filter((array) $request->input('genreAccessPointIds', [])) as $termId) {
                DB::table('object_term_relation')->insert([
                    'object_id' => $ioId,
                    'term_id'   => (int) $termId,
                ]);
            }
        }

        // ---- Name access points (relation type 161) ----
        if ($request->has('nameAccessPointIds')) {
            DB::table('relation')
                ->where('subject_id', $ioId)
                ->where('type_id', 161)
                ->delete();
            foreach (array_filter((array) $request->input('nameAccessPointIds', [])) as $actorId) {
                $relObjectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitRelation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('relation')->insert([
                    'id'             => $relObjectId,
                    'subject_id'     => $ioId,
                    'object_id'      => (int) $actorId,
                    'type_id'        => 161,
                    'source_culture' => $culture,
                ]);
            }
        }

        // Update object.updated_at
        DB::table('object')
            ->where('id', $ioId)
            ->update([
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('informationobject.show', $slug)
            ->with('success', 'Archival description updated successfully.');
    }

    /**
     * Store a new information object.
     */
    public function store(Request $request)
    {
        $culture = app()->getLocale();

        $request->validate([
            'title' => 'required|string|max:65535',
        ]);

        $parentId = $request->input('parent_id', 1); // Default to root (id=1)

        // Determine lft/rgt position: place as last child of parent
        $parent = DB::table('information_object')
            ->where('id', $parentId)
            ->select('lft', 'rgt')
            ->first();

        if (!$parent) {
            abort(422, 'Invalid parent information object.');
        }

        $newLft = $parent->rgt;
        $newRgt = $parent->rgt + 1;

        // Shift existing nested set values to make room
        DB::table('information_object')
            ->where('rgt', '>=', $parent->rgt)
            ->increment('rgt', 2);

        DB::table('information_object')
            ->where('lft', '>', $parent->rgt)
            ->increment('lft', 2);

        // Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert into information_object table
        DB::table('information_object')->insert([
            'id' => $objectId,
            'identifier' => $request->input('identifier'),
            'level_of_description_id' => $request->input('level_of_description_id') ?: null,
            'collection_type_id' => null,
            'repository_id' => $request->input('repository_id') ?: null,
            'parent_id' => $parentId,
            'description_status_id' => $request->input('description_status_id') ?: null,
            'description_detail_id' => $request->input('description_detail_id') ?: null,
            'description_identifier' => $request->input('description_identifier'),
            'source_standard' => $request->input('source_standard'),
            'display_standard_id' => $request->input('display_standard_id') ?: null,
            'lft' => $newLft,
            'rgt' => $newRgt,
            'source_culture' => $culture,
        ]);

        // Insert into information_object_i18n table
        DB::table('information_object_i18n')->insert([
            'id' => $objectId,
            'culture' => $culture,
            'title' => $request->input('title'),
            'alternate_title' => $request->input('alternate_title'),
            'edition' => $request->input('edition'),
            'extent_and_medium' => $request->input('extent_and_medium'),
            'archival_history' => $request->input('archival_history'),
            'acquisition' => $request->input('acquisition'),
            'scope_and_content' => $request->input('scope_and_content'),
            'appraisal' => $request->input('appraisal'),
            'accruals' => $request->input('accruals'),
            'arrangement' => $request->input('arrangement'),
            'access_conditions' => $request->input('access_conditions'),
            'reproduction_conditions' => $request->input('reproduction_conditions'),
            'physical_characteristics' => $request->input('physical_characteristics'),
            'finding_aids' => $request->input('finding_aids'),
            'location_of_originals' => $request->input('location_of_originals'),
            'location_of_copies' => $request->input('location_of_copies'),
            'related_units_of_description' => $request->input('related_units_of_description'),
            'institution_responsible_identifier' => $request->input('institution_responsible_identifier'),
            'rules' => $request->input('rules'),
            'sources' => $request->input('sources'),
            'revision_history' => $request->input('revision_history'),
        ]);

        // Generate slug
        $baseSlug = Str::slug($request->input('title') ?: 'untitled');
        $slug = $baseSlug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
        ]);

        return redirect()
            ->route('informationobject.show', $slug)
            ->with('success', 'Archival description created successfully.');
    }

    /**
     * Generate a missing slug for an information object.
     */
    public function fixMissingSlug(Request $request)
    {
        $objectId = (int) $request->input('object_id');
        if (!$objectId) {
            return redirect()->back()->with('error', 'No object ID provided.');
        }

        // Check if slug already exists
        $existing = DB::table('slug')->where('object_id', $objectId)->value('slug');
        if ($existing) {
            return redirect('/' . $existing)->with('info', 'Slug already exists.');
        }

        // Get the title for slug generation
        $title = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', app()->getLocale())
            ->value('title');

        if (!$title) {
            $title = 'untitled-' . $objectId;
        }

        // Generate slug from title
        $baseSlug = \Illuminate\Support\Str::slug($title);
        if (!$baseSlug) {
            $baseSlug = 'record-' . $objectId;
        }

        // Ensure uniqueness
        $slug = $baseSlug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
            'serial_number' => 0,
        ]);

        return redirect('/' . $slug)->with('success', "Slug '{$slug}' generated for object #{$objectId}.");
    }

    /**
     * Confirm deletion of an information object.
     */
    public function confirmDelete(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                  ->where('information_object_i18n.culture', '=', $culture);
            })
            ->join('slug', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::delete', [
            'io' => $io,
        ]);
    }

    /**
     * Delete an information object.
     */
    /**
     * Show the "Update publication status" form page.
     * Mirrors AtoM's informationobject/updatePublicationStatus action.
     */
    public function showUpdateStatus(string $slug)
    {
        $culture = app()->getLocale();

        $resource = DB::table('information_object')
            ->join('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                  ->where('information_object_i18n.culture', '=', $culture);
            })
            ->join('slug', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt',
                     'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$resource) {
            abort(404);
        }

        // Current publication status
        $currentStatus = DB::table('status')
            ->where('object_id', $resource->id)
            ->where('type_id', 158)
            ->value('status_id');

        // Publication status options from taxonomy 175 (Publication status)
        $publicationStatuses = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 175)
            ->where('term_i18n.culture', $culture)
            ->pluck('term_i18n.name', 'term.id')
            ->toArray();

        // Fallback if taxonomy lookup is empty
        if (empty($publicationStatuses)) {
            $publicationStatuses = [160 => 'Published', 159 => 'Draft'];
        }

        return view('ahg-io-manage::update-publication-status', [
            'resource' => $resource,
            'currentStatus' => $currentStatus,
            'publicationStatuses' => $publicationStatuses,
        ]);
    }

    /**
     * Update publication status for an information object.
     * Status is stored in the `status` table with type_id=158.
     * 160 = Published, 159 = Draft.
     * Supports updating descendants when the "updateDescendants" checkbox is ticked.
     */
    public function updateStatus(Request $request, string $slug)
    {
        // Accept both field names: "publicationStatus" (from dedicated page) and "publication_status" (legacy inline)
        $statusId = (int) ($request->input('publicationStatus') ?: $request->input('publication_status'));
        if (!in_array($statusId, [159, 160], true)) {
            return redirect()->back()->with('error', 'Invalid publication status.');
        }

        $io = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Block publish if workflow approval is required but not completed
        if ($statusId === 160) {
            try {
                $workflowService = app(\AhgWorkflow\Services\WorkflowService::class);
                if ($workflowService->isWorkflowRequiredForPublish()
                    && !$workflowService->isWorkflowApprovedForPublish($io->id)) {
                    return redirect()->back()->withErrors([
                        'workflow' => 'This item requires workflow approval before publishing. Please start or complete a workflow first.',
                    ])->with('error', 'Workflow approval required before publishing.')
                      ->with('workflow_start_url', route('workflow.dashboard'));
                }
            } catch (\Exception $e) {
                // Workflow package not available — allow publish
            }
        }

        // type_id 158 = publicationStatus
        $exists = DB::table('status')
            ->where('object_id', $io->id)
            ->where('type_id', 158)
            ->first();

        if ($exists) {
            DB::table('status')
                ->where('object_id', $io->id)
                ->where('type_id', 158)
                ->update(['status_id' => $statusId]);
        } else {
            DB::table('status')->insert([
                'object_id' => $io->id,
                'type_id' => 158,
                'status_id' => $statusId,
            ]);
        }

        $label = $statusId === 160 ? 'Published' : 'Draft';

        // Update descendants if requested
        if ($request->boolean('updateDescendants')) {
            $descendantIds = DB::table('information_object')
                ->where('lft', '>', $io->lft)
                ->where('rgt', '<', $io->rgt)
                ->pluck('id');

            $updatedCount = 0;
            foreach ($descendantIds as $descId) {
                $descExists = DB::table('status')
                    ->where('object_id', $descId)
                    ->where('type_id', 158)
                    ->first();

                if ($descExists) {
                    DB::table('status')
                        ->where('object_id', $descId)
                        ->where('type_id', 158)
                        ->update(['status_id' => $statusId]);
                } else {
                    DB::table('status')->insert([
                        'object_id' => $descId,
                        'type_id' => 158,
                        'status_id' => $statusId,
                    ]);
                }
                $updatedCount++;
            }

            return redirect()
                ->route('informationobject.show', $slug)
                ->with('success', "Publication status updated to {$label}. {$updatedCount} descendant(s) also updated.");
        }

        return redirect()
            ->route('informationobject.show', $slug)
            ->with('success', "Publication status updated to {$label}.");
    }

    public function destroy(Request $request, string $slug)
    {
        // Resolve the IO id from slug
        $record = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt')
            ->first();

        if (!$record) {
            abort(404);
        }

        $ioId = $record->id;
        $width = $record->rgt - $record->lft + 1;

        // Collect all descendant IDs (nested set: lft between this node's lft and rgt)
        $descendantIds = DB::table('information_object')
            ->whereBetween('lft', [$record->lft, $record->rgt])
            ->pluck('id')
            ->toArray();

        // Delete i18n rows for all descendants
        DB::table('information_object_i18n')
            ->whereIn('id', $descendantIds)
            ->delete();

        // Delete information_object rows for all descendants
        DB::table('information_object')
            ->whereIn('id', $descendantIds)
            ->delete();

        // Delete slug rows for all descendants
        DB::table('slug')
            ->whereIn('object_id', $descendantIds)
            ->delete();

        // Delete object rows for all descendants
        DB::table('object')
            ->whereIn('id', $descendantIds)
            ->delete();

        // Close the gap in the nested set
        DB::table('information_object')
            ->where('lft', '>', $record->rgt)
            ->decrement('lft', $width);

        DB::table('information_object')
            ->where('rgt', '>', $record->rgt)
            ->decrement('rgt', $width);

        return redirect()
            ->to('/glam/browse')
            ->with('success', 'Archival description deleted successfully.');
    }

    /**
     * Reports page for an information object.
     *
     * Shows per-record report options (box labels, file lists, item lists, storage locations).
     * Migrated from AtoM informationobject/reportsSuccess.php.
     */
    public function reports(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Check for existing reports (job-generated report files)
        $existingReports = [];
        $reportsDir = storage_path('app/reports/' . $io->id);
        if (is_dir($reportsDir)) {
            foreach (glob($reportsDir . '/*') as $file) {
                $basename = basename($file);
                $parts = explode('.', $basename);
                $format = end($parts);
                $type = str_replace('_', ' ', pathinfo($basename, PATHINFO_FILENAME));
                $existingReports[] = [
                    'type' => ucfirst($type),
                    'format' => strtoupper($format),
                    'path' => route('informationobject.show', $slug) . '/reports/download/' . $basename,
                ];
            }
        }

        // Report types available for this record
        $reportTypes = [
            'fileList' => 'File list',
            'itemList' => 'Item list',
            'storageLocations' => 'Physical storage locations',
            'boxLabel' => 'Box label',
        ];

        return view('ahg-io-manage::reports', [
            'io' => $io,
            'existingReports' => $existingReports,
            'reportTypes' => $reportTypes,
            'reportsAvailable' => !empty($reportTypes),
        ]);
    }

    /**
     * Rename form for an information object.
     *
     * Shows fields to update title, slug, and optionally digital object filename.
     * Migrated from AtoM informationobject/renameSuccess.php.
     */
    public function rename(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Check for digital object
        $digitalObject = DB::table('digital_object')
            ->where('object_id', $io->id)
            ->select('id', 'name')
            ->first();

        return view('ahg-io-manage::rename', [
            'io' => $io,
            'digitalObject' => $digitalObject,
        ]);
    }

    /**
     * Process the rename form submission.
     */
    public function renameUpdate(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $ioRow = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'slug.slug')
            ->first();

        if (!$ioRow) {
            abort(404);
        }

        $newTitle = $request->input('title');
        $newSlug = $request->input('slug');

        DB::transaction(function () use ($ioRow, $newTitle, $newSlug, $culture) {
            // Update title
            if ($newTitle !== null) {
                DB::table('information_object_i18n')
                    ->where('id', $ioRow->id)
                    ->where('culture', $culture)
                    ->update(['title' => $newTitle]);
            }

            // Update slug
            if ($newSlug !== null && $newSlug !== $ioRow->slug) {
                $cleanSlug = \Illuminate\Support\Str::slug($newSlug);
                if (empty($cleanSlug)) {
                    $cleanSlug = 'untitled';
                }
                // Ensure uniqueness
                $baseSlug = $cleanSlug;
                $counter = 1;
                while (DB::table('slug')->where('slug', $cleanSlug)->where('object_id', '!=', $ioRow->id)->exists()) {
                    $cleanSlug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                DB::table('slug')
                    ->where('object_id', $ioRow->id)
                    ->update(['slug' => $cleanSlug]);

                $newSlug = $cleanSlug;
            } else {
                $newSlug = $ioRow->slug;
            }

            // Touch the object
            DB::table('object')->where('id', $ioRow->id)->update(['updated_at' => now()]);
        });

        return redirect()
            ->route('informationobject.show', $newSlug ?? $slug)
            ->with('success', 'Record renamed successfully.');
    }

    /**
     * Inventory list for an information object.
     *
     * Lists children of the IO at specific levels of description.
     * Migrated from AtoM informationobject/inventorySuccess.php.
     */
    public function inventory(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Get inventory levels from settings (default: Item level = term_id for "Item")
        $inventoryLevelIds = DB::table('setting')
            ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
            ->where('setting.name', 'inventory_levels')
            ->where('setting_i18n.culture', $culture)
            ->value('setting_i18n.value');

        $levelIds = [];
        if ($inventoryLevelIds) {
            $decoded = @unserialize($inventoryLevelIds);
            if (is_array($decoded)) {
                $levelIds = $decoded;
            }
        }

        // Build inventory query: descendants at specified levels
        $query = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.lft', '>', $io->lft)
            ->where('information_object.rgt', '<', $io->rgt)
            ->where('information_object_i18n.culture', $culture);

        if (!empty($levelIds)) {
            $query->whereIn('information_object.level_of_description_id', $levelIds);
        }

        $perPage = 30;
        $page = max(1, (int) request('page', 1));
        $total = (clone $query)->count();

        $sortField = request('sort', 'identifier');
        $sortMap = [
            'identifier' => 'information_object.identifier',
            'title' => 'information_object_i18n.title',
            'level' => 'information_object.level_of_description_id',
            'lft' => 'information_object.lft',
        ];
        $orderBy = $sortMap[$sortField] ?? 'information_object.lft';

        $items = $query->orderBy($orderBy)
            ->select(
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object_i18n.title',
                'slug.slug'
            )
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Resolve level names
        $levelIds = $items->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        $levelNames = [];
        if (!empty($levelIds)) {
            $levelNames = DB::table('term_i18n')
                ->whereIn('id', $levelIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Check for digital objects
        $itemIds = $items->pluck('id')->toArray();
        $hasDigitalObject = [];
        if (!empty($itemIds)) {
            $hasDigitalObject = DB::table('digital_object')
                ->whereIn('object_id', $itemIds)
                ->pluck('object_id')
                ->flip()
                ->toArray();
        }

        // Get dates for items
        $itemDates = [];
        if (!empty($itemIds)) {
            $dates = DB::table('event')
                ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
                ->whereIn('event.object_id', $itemIds)
                ->where('event_i18n.culture', $culture)
                ->select('event.object_id', 'event_i18n.date as date_display', 'event.start_date', 'event.end_date')
                ->get();
            foreach ($dates as $d) {
                $itemDates[$d->object_id] = $d->date_display ?: trim(($d->start_date ?? '') . ' - ' . ($d->end_date ?? ''), ' -');
            }
        }

        // Build breadcrumbs
        $breadcrumbs = [];
        $parentId = $io->parent_id;
        while ($parentId && $parentId != 1) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $parentId)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')
                ->first();
            if (!$parent) {
                break;
            }
            array_unshift($breadcrumbs, $parent);
            $parentId = $parent->parent_id;
        }

        return view('ahg-io-manage::inventory', [
            'io' => $io,
            'items' => $items,
            'levelNames' => $levelNames,
            'hasDigitalObject' => $hasDigitalObject,
            'itemDates' => $itemDates,
            'breadcrumbs' => $breadcrumbs,
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
        ]);
    }

    /**
     * Calculate dates for an information object.
     *
     * Walks descendant records to find the earliest start_date and latest end_date,
     * then updates the parent creation event.
     * Migrated from AtoM informationobject/calculateDatesAction.
     */
    public function calculateDates(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object.lft', 'information_object.rgt', 'slug.slug')
            ->first();

        if (!$io) {
            abort(404);
        }

        // Get all descendant IDs
        $descendantIds = DB::table('information_object')
            ->where('lft', '>', $io->lft)
            ->where('rgt', '<', $io->rgt)
            ->pluck('id')
            ->toArray();

        if (empty($descendantIds)) {
            return redirect()
                ->route('informationobject.show', $slug)
                ->with('info', 'No child descriptions found to calculate dates from.');
        }

        // Find earliest start_date and latest end_date across all descendants
        $dateRange = DB::table('event')
            ->whereIn('object_id', $descendantIds)
            ->selectRaw('MIN(start_date) as earliest_start, MAX(end_date) as latest_end')
            ->first();

        if (!$dateRange || (!$dateRange->earliest_start && !$dateRange->latest_end)) {
            return redirect()
                ->route('informationobject.show', $slug)
                ->with('info', 'No date information found in child descriptions.');
        }

        // Update or create the creation event for this IO
        $event = DB::table('event')
            ->where('object_id', $io->id)
            ->where('type_id', 111) // Creation event
            ->first();

        if ($event) {
            DB::table('event')
                ->where('id', $event->id)
                ->update([
                    'start_date' => $dateRange->earliest_start,
                    'end_date' => $dateRange->latest_end,
                ]);

            // Update the event_i18n date display
            $displayDate = trim(($dateRange->earliest_start ?? '') . ' - ' . ($dateRange->latest_end ?? ''), ' -');
            DB::table('event_i18n')
                ->where('id', $event->id)
                ->where('culture', $culture)
                ->update(['date' => $displayDate]);
        }

        // Touch the object
        DB::table('object')->where('id', $io->id)->update(['updated_at' => now()]);

        return redirect()
            ->route('informationobject.show', $slug)
            ->with('success', 'Dates calculated successfully from child descriptions.');
    }

    /**
     * Show the move form for an information object.
     */
    public function move(string $slug)
    {
        $culture = app()->getLocale();

        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select(
                'information_object.id',
                'information_object.parent_id',
                'information_object.level_of_description_id',
                'information_object_i18n.title',
                'slug.slug'
            )
            ->first();

        if (!$io) {
            abort(404);
        }

        // Build breadcrumb of current parent hierarchy
        $breadcrumb = [];
        $currentParentId = $io->parent_id;
        while ($currentParentId) {
            $parent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $currentParentId)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object.parent_id', 'information_object_i18n.title', 'slug.slug')
                ->first();
            if (!$parent) break;
            array_unshift($breadcrumb, $parent);
            $currentParentId = $parent->parent_id;
        }

        // Current parent name
        $currentParent = null;
        if ($io->parent_id) {
            $currentParent = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->where('information_object.id', $io->parent_id)
                ->where('information_object_i18n.culture', $culture)
                ->select('information_object.id', 'information_object_i18n.title')
                ->first();
        }

        return view('ahg-io-manage::move', [
            'io' => $io,
            'breadcrumb' => $breadcrumb,
            'currentParent' => $currentParent,
        ]);
    }

    /**
     * Process the move form: update parent_id for the information object.
     */
    public function moveStore(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $ioRow = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object.level_of_description_id', 'slug.slug')
            ->first();

        if (!$ioRow) {
            abort(404);
        }

        $request->validate([
            'new_parent_id' => 'required|integer|exists:information_object,id',
        ]);

        $newParentId = (int) $request->input('new_parent_id');

        // Prevent moving to self or to a descendant
        if ($newParentId === $ioRow->id) {
            return back()->withErrors(['new_parent_id' => 'Cannot move a record to itself.']);
        }

        // Check that new parent is not a descendant of the record being moved
        $checkId = $newParentId;
        while ($checkId) {
            $ancestor = DB::table('information_object')->where('id', $checkId)->value('parent_id');
            if ($ancestor === $ioRow->id) {
                return back()->withErrors(['new_parent_id' => 'Cannot move a record to one of its own descendants.']);
            }
            $checkId = $ancestor;
        }

        DB::table('information_object')
            ->where('id', $ioRow->id)
            ->update([
                'parent_id' => $newParentId,
            ]);

        // Determine sector-aware redirect
        $redirectRoute = 'informationobject.show';
        if ($ioRow->level_of_description_id && Schema::hasTable('level_of_description_sector')) {
            $sector = DB::table('level_of_description_sector')
                ->where('term_id', $ioRow->level_of_description_id)
                ->whereNotIn('sector', ['archive'])
                ->orderBy('display_order')
                ->value('sector');

            $sectorRoutes = [
                'library' => 'library.show',
                'museum'  => 'museum.show',
                'gallery' => 'gallery.show',
                'dam'     => 'dam.show',
            ];

            if ($sector && isset($sectorRoutes[$sector])) {
                try {
                    if (\Illuminate\Support\Facades\Route::has($sectorRoutes[$sector])) {
                        $redirectRoute = $sectorRoutes[$sector];
                    }
                } catch (\Exception $e) {
                    // fall through to default
                }
            }
        }

        return redirect()
            ->route($redirectRoute, $slug)
            ->with('success', 'Record moved successfully.');
    }

    public function autocomplete(Request $request)
    {
        $query = $request->get('query', '');
        $culture = app()->getLocale();
        $limit = (int) $request->get('limit', 10);

        $results = DB::table('information_object')
            ->join('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                  ->where('information_object_i18n.culture', '=', $culture);
            })
            ->join('slug', 'slug.object_id', '=', 'information_object.id')
            ->where('information_object_i18n.title', 'LIKE', '%' . $query . '%')
            ->select(
                'information_object.id',
                'information_object_i18n.title as name',
                'slug.slug'
            )
            ->limit($limit)
            ->get();

        return response()->json($results);
    }
}
