<?php

/**
 * DisplayController - Controller for Heratio
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



namespace AhgDisplay\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use AhgCore\Services\SettingHelper;
use AhgCore\Support\TenantScope;
use AhgDisplay\Services\DisplayService;
use AhgDisplay\Services\DisplayTypeDetector;
use AhgDisplay\Services\TitleSortService;
use AhgDisplay\Repositories\UserBrowseSettingsRepository;

class DisplayController extends Controller
{
    protected DisplayService $service;
    protected UserBrowseSettingsRepository $settingsRepo;

    /**
     * Default for the topLevel browse filter when the request omits it.
     * Operator setting `browse_default_top_level`; '0' (show all descriptions)
     * preserves the historical behaviour when the setting is unset.
     *
     * AtoM defaults browse to top-level (informationobject/browseAction sets
     * topLod = true) and offers a removable filter to show everything.
     */
    protected static function defaultTopLevel(): string
    {
        $v = trim((string) \AhgCore\Services\AhgSettingsService::get('browse_default_top_level', '0'));

        return filter_var($v, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
    }

    // Browse state properties (shared between browse/applyFilters/helper methods)
    protected ?string $typeFilter = null;
    protected ?string $parentId = null;
    protected string $topLevelOnly = '0';
    protected ?string $hasDigital = null;
    protected ?string $creatorFilter = null;
    protected ?string $subjectFilter = null;
    protected ?string $placeFilter = null;
    protected ?string $genreFilter = null;
    protected ?string $levelFilter = null;
    protected ?string $mediaFilter = null;
    protected ?string $repoFilter = null;
    // Library-only facets (only meaningful when result set includes library_item rows)
    protected ?string $materialTypeFilter = null;
    protected ?string $conditionGradeFilter = null;
    protected ?string $acquisitionMethodFilter = null;
    protected ?string $circulationStatusFilter = null;
    protected ?string $queryFilter = null;
    protected ?array $queryFilterTerms = null;
    protected ?string $titleFilter = null;
    protected ?string $identifierFilter = null;
    protected ?string $referenceCodeFilter = null;
    protected ?string $scopeAndContentFilter = null;
    protected ?string $extentAndMediumFilter = null;
    protected ?string $archivalHistoryFilter = null;
    protected ?string $acquisitionFilter = null;
    protected ?string $creatorSearchFilter = null;
    protected ?string $subjectSearchFilter = null;
    protected ?string $placeSearchFilter = null;
    protected ?string $genreSearchFilter = null;
    protected ?string $startDateFilter = null;
    protected ?string $endDateFilter = null;
    protected string $rangeTypeFilter = 'inclusive';
    protected bool $isAuthenticated = false;

    // Fulltext / sector search caches (per-request)
    protected static ?bool $fulltextAvailable = null;
    protected static ?array $sectorSearchTables = null;

    /**
     * Per-request memo for resolveTextMatchIds(), keyed by search terms. The
     * browse page applies the same text filter eight times (page, count, and
     * six facets); without this the resolve itself would simply repeat.
     * Values are int[] on success, or null meaning "use the correlated path".
     *
     * @var array<string, array<int,int>|null>
     */
    protected static array $textMatchIdCache = [];

    /**
     * Widest match set still worth inlining as an id list. Beyond this the
     * placeholder count gets unreasonable (MySQL caps a prepared statement at
     * 65,535) and a scan is the better plan, so the query falls back.
     */
    protected const TEXT_MATCH_ID_CAP = 20000;

    public function __construct()
    {
        $this->service = new DisplayService();
        $this->settingsRepo = new UserBrowseSettingsRepository();
    }

    // =========================================================================
    // 1. INDEX - Admin dashboard
    // =========================================================================

    public function index()
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $culture = app()->getLocale();

        $profiles = DB::table('display_profile as dp')
            ->leftJoin('display_profile_i18n as dpi', function ($j) use ($culture) {
                $j->on('dp.id', '=', 'dpi.id')->where('dpi.culture', '=', $culture);
            })
            ->select('dp.*', 'dpi.name')
            ->orderBy('dp.domain')->orderBy('dp.sort_order')
            ->get()->toArray();

        $levels = $this->service->getLevels();
        $collectionTypes = $this->service->getCollectionTypes();

        $stats = [
            'total_objects' => DB::table('information_object')->where('id', '>', 1)->count(),
            'configured_objects' => DB::table('display_object_config')->count(),
            'by_type' => DB::table('display_object_config')
                ->select('object_type', DB::raw('COUNT(*) as count'))
                ->groupBy('object_type')
                ->get()->toArray(),
        ];

        return view('ahg-display::display.index', compact(
            'profiles', 'levels', 'collectionTypes', 'stats'
        ));
    }

    // =========================================================================
    // 2. BROWSE - Main GLAM browse with all filters, facets, pagination, sorting
    // =========================================================================

    public function browse(Request $request)
    {
        // Master kill-switch (issue #93). Disabled by /admin/ahgSettings/themes
        // -> "Enable GLAM browse" toggle. When off, the route 404s so the
        // sector show pages and other entry points become the only browse
        // surfaces. Default is on.
        if (!\AhgCore\Services\AhgSettingsService::getBool('enable_glam_browse', true)) {
            abort(404);
        }

        $culture = app()->getLocale();
        $this->isAuthenticated = auth()->check();

        // Get all filter parameters
        $this->typeFilter = $request->input('type');
        // When the user lands on /glam/browse without an explicit type filter
        // and no other narrowing parameter, fall back to the operator's
        // default_sector (issue #93). Lets a museum-only deployment skip the
        // archive default. Empty string in the setting means "no default".
        if ($this->typeFilter === null && !$request->hasAny(['parent', 'collection', 'ancestor', 'creator', 'subject', 'place', 'genre', 'level', 'media', 'repo', 'query', 'topLevel', 'topLod', 'hasDigital', 'onlyMedia', 'materialType', 'conditionGrade', 'acquisitionMethod', 'circulationStatus'])) {
            $defaultSector = trim((string) \AhgCore\Services\AhgSettingsService::get('default_sector', ''));
            if ($defaultSector !== '') {
                $this->typeFilter = $defaultSector;
            }
        }
        $this->parentId = $request->input('parent');
        $this->topLevelOnly = $request->input('topLevel', self::defaultTopLevel());
        $page = max(1, (int) $request->input('page', 1));

        // Read limit: display browse has its own 10-100 range control
        $limit = (int) $request->input('limit', SettingHelper::hitsPerPage());
        if ($limit < 10) $limit = 10;
        if ($limit > 100) $limit = 100;

        // settings.sort_browser_anonymous / sort_browser_user (#80): defaults
        // for browse sort when ?sort= isn't in the request. Anonymous + auth'd
        // users get separate defaults. Unrecognised tokens fall through the
        // switch below to the default title sort, so an operator-typed value
        // can never break the page.
        $defaultSort = auth()->check()
            ? \AhgCore\Support\GlobalSettings::sortBrowserUser()
            : \AhgCore\Support\GlobalSettings::sortBrowserAnonymous();
        $sort = $request->input('sort', $defaultSort !== '' ? $defaultSort : 'date');
        $defaultSortDir = auth()->check()
            ? \AhgCore\Support\GlobalSettings::sortBrowserDirectionUser()
            : \AhgCore\Support\GlobalSettings::sortBrowserDirectionAnonymous();
        $sortDir = $request->input('sortDir', $request->input('dir', $defaultSortDir));
        $viewMode = $request->input('view', 'card');
        $this->hasDigital = $request->input('hasDigital');

        // Map standard AtoM parameters to display plugin native names
        if (!$this->hasDigital && $request->input('onlyMedia')) {
            $this->hasDigital = '1';
        }
        if ($request->input('topLod') !== null) {
            $this->topLevelOnly = filter_var($request->input('topLod'), FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }
        if (!$this->parentId && $request->input('ancestor')) {
            $this->parentId = $request->input('ancestor');
        }
        // AtoM compat: collection=X means filter to descendants of collection root X
        if (!$this->parentId && $request->input('collection')) {
            $this->parentId = $request->input('collection');
        }

        // Facet filters
        $this->creatorFilter = $request->input('creator');
        $this->subjectFilter = $request->input('subject');
        $this->placeFilter = $request->input('place');
        $this->genreFilter = $request->input('genre');
        $this->levelFilter = $request->input('level');
        $this->mediaFilter = $request->input('media');
        $this->materialTypeFilter = $request->input('materialType') ?: null;
        $this->conditionGradeFilter = $request->input('conditionGrade') ?: null;
        $this->acquisitionMethodFilter = $request->input('acquisitionMethod') ?: null;
        $this->circulationStatusFilter = $request->input('circulationStatus') ?: null;

        // Text search filters
        $this->queryFilter = $request->input('query');
        if ($this->queryFilter !== null) {
            // Strip wildcard-only input: MySQL FULLTEXT rejects bare '*' with
            // "unexpected $end, expecting FTS_TERM". Treat '*', '***', etc. as "no filter".
            $trimmed = trim($this->queryFilter);
            if ($trimmed === '' || preg_match('/^[\*\s]+$/', $trimmed)) {
                $this->queryFilter = null;
            } else {
                $this->queryFilter = $trimmed;
            }
        }
        $semanticEnabled = $request->input('semantic') == '1';

        // Fuzzy search correction - skipped (FuzzySearchService not yet migrated)
        $didYouMean = null;
        $correctedQuery = null;
        $originalQuery = $this->queryFilter;
        $esAssistedSearch = false;

        // Semantic expansion (P5.a) — when ?semantic=1 is on, ask the thesaurus
        // (ahg-semantic-search) to expand the query into related terms; the search
        // builder then runs them as an OR-match in the FULLTEXT branch and as
        // additional should-match clauses in the ES branch.
        $this->queryFilterTerms = null;
        if ($semanticEnabled
            && $this->queryFilter
            && class_exists(\AhgSemanticSearch\Services\SemanticSearchService::class)
        ) {
            try {
                $svc = app(\AhgSemanticSearch\Services\SemanticSearchService::class);
                $expansion = $svc->expandQuery((string) $this->queryFilter, app()->getLocale());
                if (! empty($expansion['expanded_terms'])) {
                    $bag = [(string) $this->queryFilter];
                    foreach ($expansion['expanded_terms'] as $orig => $syns) {
                        foreach ((array) $syns as $s) {
                            $bag[] = (string) $s;
                        }
                    }
                    // Deduplicate case-insensitively while preserving order.
                    $seen = [];
                    $uniq = [];
                    foreach ($bag as $t) {
                        $k = mb_strtolower(trim($t));
                        if ($k === '' || isset($seen[$k])) {
                            continue;
                        }
                        $seen[$k] = true;
                        $uniq[] = $t;
                    }
                    if (count($uniq) > 1) {
                        $this->queryFilterTerms = $uniq;
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info('semantic expansion skipped: ' . $e->getMessage());
            }
        }

        // Advanced text filters
        $this->titleFilter = $request->input('title');
        $this->identifierFilter = $request->input('identifier');
        $this->referenceCodeFilter = $request->input('referenceCode');
        $this->scopeAndContentFilter = $request->input('scopeAndContent');
        $this->extentAndMediumFilter = $request->input('extentAndMedium');
        $this->archivalHistoryFilter = $request->input('archivalHistory');
        $this->acquisitionFilter = $request->input('acquisition');
        $this->creatorSearchFilter = $request->input('creatorSearch');
        $this->subjectSearchFilter = $request->input('subjectSearch');
        $this->placeSearchFilter = $request->input('placeSearch');
        $this->genreSearchFilter = $request->input('genreSearch');
        $this->repoFilter = $request->input('repo');

        // #114 multi_repository: when off, force the repo filter to the
        // operator-pinned single_repo_id regardless of any user-supplied
        // ?repo= override. This makes the entire browse surface behave as
        // a single-institution catalogue (the alternate UX the audit was
        // describing) without needing per-route hide-or-show plumbing.
        if (!\AhgCore\Support\GlobalSettings::multiRepository()) {
            $forced = \AhgCore\Support\GlobalSettings::singleRepoId();
            if ($forced) {
                $this->repoFilter = (string) $forced;
            }
        }

        $this->startDateFilter = $request->input('startDate');
        $this->endDateFilter = $request->input('endDate');
        $this->rangeTypeFilter = $request->input('rangeType', 'inclusive');

        // Load facets: live counts scoped to current filters when filters active,
        // otherwise use cached counts for performance
        $hasFilters = $this->typeFilter || $this->parentId || $this->creatorFilter
            || $this->subjectFilter || $this->placeFilter || $this->genreFilter
            || $this->levelFilter || $this->mediaFilter || $this->repoFilter
            || $this->queryFilter || $this->hasDigital
            || $this->materialTypeFilter || $this->conditionGradeFilter
            || $this->acquisitionMethodFilter || $this->circulationStatusFilter;

        // #114 multi_repository: when off, the repository facet is meaningless
        // (browse is locked to a single repo). Skip the facet build entirely
        // and pass an empty collection to the view so the sidebar card
        // hides itself - the facet partial only renders when the collection
        // is non-empty.
        $singleRepoMode = !\AhgCore\Support\GlobalSettings::multiRepository();

        if ($hasFilters) {
            $types = $this->getLiveFacet('type');
            $levels = $this->getLiveFacet('level');
            $repositories = $singleRepoMode ? collect() : $this->getLiveFacet('repository');
            $creators = $this->getLiveFacet('creator');
            $subjects = $this->getLiveFacet('subject');
            $places = $this->getLiveFacet('place');
            $genres = $this->getLiveFacet('genre');
            $mediaTypes = $this->getLiveFacet('media_type');
        } else {
            $sfx = $this->isAuthenticated ? '_all' : '';
            $types = $this->getCachedFacet('glam_type' . $sfx, 'object_type');
            $levels = $this->getCachedFacet('level' . $sfx);
            $repositories = $singleRepoMode ? collect() : $this->getCachedFacet('repository' . $sfx);
            $creators = $this->getCachedFacet('creator' . $sfx);
            $subjects = $this->getCachedFacet('subject' . $sfx);
            $places = $this->getCachedFacet('place' . $sfx);
            $genres = $this->getCachedFacet('genre' . $sfx);
            $mediaTypes = $this->getCachedFacet('media_type' . $sfx, 'media_type');
        }

        // Library-only facets: always live (no display_facet_cache row), cheap
        // queries because they hit a small table. The view skips empty results,
        // so on archive-only browses these blocks don't render.
        $materialTypes      = $this->getLiveFacet('material_type');
        $conditionGrades    = $this->getLiveFacet('condition_grade');
        $acquisitionMethods = $this->getLiveFacet('acquisition_method');
        $circulationStatuses = $this->getLiveFacet('circulation_status');

        // Discovery integration - skipped (ahgDiscoveryPlugin not yet migrated)
        $discoveryMode = false;
        $discoveryExpanded = null;
        $discoveryMeta = [];
        $esIds = null;

        // Classic path: SQL count.
        //
        // display_object_config is joined ONLY when something actually filters
        // on it. applyFilters() touches doc.object_type for the sector/type
        // filter and nothing else, so with no type filter the join contributes
        // no rows and no predicate - it just makes the count walk a second
        // table: 3,063ms with it versus 1,894ms without on atom.theahg.co.za,
        // for the identical answer (454,392).
        $countQuery = DB::table('information_object as io')->where('io.id', '>', 1);
        if ($this->typeFilter) {
            $countQuery->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id');
        }

        $this->applyFilters($countQuery);
        $total = $countQuery->count();

        // ES fuzzy fallback - skipped (Elasticsearch not yet migrated)
        // Placeholder for future: if $total === 0 && $this->queryFilter, try ES fuzzy

        $totalPages = (int) ceil($total / $limit);

        // Build main query — culture-fallback to lang/{fallback_locale} so a
        // record that only has English doesn't render as "[Untitled]" when
        // browsed in af/zu/etc. Pattern mirrors AhgCore\Traits\WithCultureFallback.
        $fallback = config('app.fallback_locale', 'en');
        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as i18n_fb', function ($j) use ($fallback) {
                $j->on('io.id', '=', 'i18n_fb.id')->where('i18n_fb.culture', '=', $fallback);
            })
            ->leftJoin('term_i18n as level', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'level.id')->where('level.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level_fb', function ($j) use ($fallback) {
                $j->on('io.level_of_description_id', '=', 'level_fb.id')->where('level_fb.culture', '=', $fallback);
            })
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            // #763 FRBR work-set clustering: pull library_item.work_key into the
            // hit-list so the renderer can collapse manifestations of the same
            // Work into a single row with a 'View all editions' expander.
            ->leftJoin('library_item as li_wk', 'io.id', '=', 'li_wk.information_object_id')
            ->where('io.id', '>', 1)
            ->select(
                'io.id', 'io.identifier', 'io.parent_id',
                DB::raw("COALESCE(NULLIF(i18n.title, ''), i18n_fb.title) AS title"),
                DB::raw("COALESCE(NULLIF(i18n.scope_and_content, ''), i18n_fb.scope_and_content) AS scope_and_content"),
                DB::raw("COALESCE(NULLIF(level.name, ''), level_fb.name) AS level_name"),
                'doc.object_type', 'slug.slug', 'li_wk.work_key'
            );

        // Apply all filters to main query
        $this->applyFilters($query);

        // Handle parent/breadcrumb
        if ($this->parentId) {
            $parent = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                ->where('io.id', $this->parentId)
                ->select('io.id', 'io.parent_id', 'i18n.title', 'slug.slug')
                ->first();
            $breadcrumb = $this->buildBreadcrumb((int) $this->parentId);
        } else {
            $parent = null;
            $breadcrumb = [];
        }

        $digitalObjectCount = DB::table('information_object as io')
            ->join('digital_object as dobj', function ($j) {
                $j->on('io.id', '=', 'dobj.object_id')->whereNull('dobj.parent_id');
            })
            ->where('io.id', '>', 1)
            ->count();

        // Sort
        $safeSortDir = $sortDir === 'desc' ? 'desc' : 'asc';

        // Resolved before the switch: when the sidecar answers, the page order
        // is already decided and applying an ORDER BY to $query as well would
        // leave FIELD() as a secondary key and silently reorder the page.
        $sortColumn = $this->sidecarSortColumn($sort);
        // relevance pins io.id to DESC whatever ?dir= says - mirror that here,
        // or the fast path would silently reverse a page the old path did not.
        $sidecarDir = $sort === 'relevance' ? 'desc' : $safeSortDir;
        $orderedIds = $sortColumn
            ? $this->alphabeticIdPage($culture, $sidecarDir, $page, $limit, $sortColumn)
            : null;

        switch ($sort) {
            case 'identifier':
            case 'refcode':
                if ($orderedIds === null) {
                    // identifier is varchar(1024) and entirely unindexed, so this
                    // filesorts the table; the sidecar path above avoids it.
                    $query->orderBy('io.identifier', $safeSortDir)->orderBy('io.id', $safeSortDir);
                }
                break;
            case 'date':
            case 'lastUpdated':          // settings vocabulary: "Most recent"
                if ($orderedIds === null) {
                    $query->orderBy('io.id', $safeSortDir);
                }
                break;
            case 'relevance':
                if ($orderedIds === null) {
                    if ($this->queryFilter) {
                        $query->orderByRaw("CASE WHEN i18n.title LIKE ? THEN 0 WHEN i18n.title LIKE ? THEN 1 ELSE 2 END ASC", [
                            $this->queryFilter,
                            '%' . $this->queryFilter . '%',
                        ]);
                    }
                    $query->orderBy('io.id', 'desc');
                }
                break;
            case 'startdate':
                // The join and GROUP BY exist ONLY to compute the aggregate to
                // order by. When the sidecar supplies the order they are pure
                // cost - and the GROUP BY would also collapse the FIELD()
                // ordering the id-first page depends on - so both are skipped.
                if ($orderedIds === null) {
                    $query->leftJoin('event as evt_sort', 'io.id', '=', 'evt_sort.object_id');
                    $query->orderByRaw("MIN(evt_sort.start_date) $safeSortDir");
                    $query->groupBy('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'i18n_fb.title', 'i18n_fb.scope_and_content', 'level.name', 'level_fb.name', 'doc.object_type', 'slug.slug', 'li_wk.work_key');
                }
                break;
            case 'enddate':
                if ($orderedIds === null) {
                    $query->leftJoin('event as evt_sort', 'io.id', '=', 'evt_sort.object_id');
                    $query->orderByRaw("MAX(evt_sort.end_date) $safeSortDir");
                    $query->groupBy('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'i18n_fb.title', 'i18n_fb.scope_and_content', 'level.name', 'level_fb.name', 'doc.object_type', 'slug.slug', 'li_wk.work_key');
                }
                break;
            case 'alphabetic':           // settings vocabulary: "Alphabetic"
            default:
                if ($orderedIds === null) {
                    $this->applyAlphabeticSort($query, $safeSortDir, $culture);
                }
        }

        // Paginate.
        //
        // Alphabetical browse picks its page of ids off the sort sidecar first
        // (an index-ordered scan) and then fetches only those rows, because
        // ORDER BY on the joined query cannot use the sidecar index at all -
        // see alphabeticIdPage(). Every other sort paginates normally.
        if ($orderedIds !== null) {
            if (empty($orderedIds)) {
                $objects = [];
            } else {
                // Re-impose the sidecar's order: whereIn does not preserve it.
                $ordered = implode(',', $orderedIds);
                $objects = $query
                    ->whereIn('io.id', $orderedIds)
                    ->orderByRaw("FIELD(io.id, $ordered)")
                    ->get()
                    ->toArray();
            }
        } else {
            $objects = $query
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get()
                ->toArray();
        }

        // #763 FRBR cluster collapse: when two rows on this page share a work_key,
        // keep the first as the representative + record the sibling count. The
        // expander link (/library/work-cluster/{workKey}) lists every edition
        // including ones on other pages.
        $seenWorkKeys = [];
        $clusteredObjects = [];
        $workKeysOnPage = [];
        foreach ($objects as $obj) {
            $key = $obj->work_key ?? null;
            if (!$key) {
                $obj->cluster_count = 1;
                $clusteredObjects[] = $obj;
                continue;
            }
            if (isset($seenWorkKeys[$key])) {
                // non-representative - suppress this row, increment the rep's local count
                $seenWorkKeys[$key]->__local_dupes = ($seenWorkKeys[$key]->__local_dupes ?? 0) + 1;
                continue;
            }
            $seenWorkKeys[$key] = $obj;
            $workKeysOnPage[] = $key;
            $clusteredObjects[] = $obj;
        }
        // Cross-page sibling count - one query for all keys on this page.
        if (!empty($workKeysOnPage)) {
            $totalsByKey = DB::table('library_item')
                ->whereIn('work_key', $workKeysOnPage)
                ->select('work_key', DB::raw('COUNT(*) as total'))
                ->groupBy('work_key')
                ->pluck('total', 'work_key');
            foreach ($clusteredObjects as $obj) {
                if (!empty($obj->work_key) && $totalsByKey->has($obj->work_key)) {
                    $obj->cluster_count = (int) $totalsByKey[$obj->work_key];
                } else {
                    $obj->cluster_count = 1;
                }
            }
        }
        $objects = $clusteredObjects;

        // Enrich results
        foreach ($objects as &$obj) {
            $obj->child_count = DB::table('information_object')->where('parent_id', $obj->id)->count();

            if (!$obj->object_type) {
                $obj->object_type = DisplayTypeDetector::detect($obj->id);
            }

            $obj->thumbnail = null;
            $obj->reference = null;

            $digitalObject = DB::table('digital_object')
                ->where('object_id', $obj->id)
                ->whereNull('parent_id')
                ->select('id')
                ->first();

            $obj->has_digital = !empty($digitalObject);

            if ($digitalObject) {
                $thumb = DB::table('digital_object')
                    ->where('parent_id', $digitalObject->id)
                    ->where('usage_id', 142)
                    ->select('path', 'name')
                    ->first();
                $ref = DB::table('digital_object')
                    ->where('parent_id', $digitalObject->id)
                    ->where('usage_id', 141)
                    ->select('path', 'name')
                    ->first();

                // Only use a derivative as a browse thumbnail when it is actually an IMAGE.
                // Audio/video/PDF masters often have no image derivative, so usage 142/141
                // can resolve to the media file itself (e.g. an .mp3); rendering that as an
                // <img> shows a blank/broken cell. Leaving the thumbnail null lets the card
                // fall back to the type icon. (A jpg/png poster frame still passes through.)
                $isImageName = static fn (?string $name): bool => is_string($name)
                    && (bool) preg_match('/\.(jpe?g|png|gif|webp|avif|svg|tiff?|bmp)$/i', $name);

                if ($thumb && $thumb->path && $thumb->name && $isImageName($thumb->name)) {
                    $obj->thumbnail = rtrim($thumb->path, '/') . '/' . $thumb->name;
                }
                if ($ref && $ref->path && $ref->name && $isImageName($ref->name)) {
                    $obj->reference = rtrim($ref->path, '/') . '/' . $ref->name;
                }
                // If no separate thumbnail, fall back to reference for the small slot too
                if (!$obj->thumbnail && $obj->reference) {
                    $obj->thumbnail = $obj->reference;
                }
            }

            // Fallback to library_item cover_url for library items
            if (!$obj->thumbnail) {
                try {
                    $libraryItem = DB::table('library_item')
                        ->where('information_object_id', $obj->id)
                        ->select('cover_url')
                        ->first();
                    if ($libraryItem && $libraryItem->cover_url) {
                        $obj->thumbnail = $libraryItem->cover_url;
                        $obj->has_digital = true;
                    }
                } catch (\Exception $e) {
                    // table does not exist - ahgLibraryPlugin not installed
                }
            }
        }
        unset($obj);

        // Separate broken records (no slug) — admin-only diagnostics
        $brokenItems = [];
        if (auth()->check() && \AhgCore\Services\AclService::isAdministrator()) {
            $validObjects = [];
            foreach ($objects as $obj) {
                if (empty($obj->slug)) {
                    $brokenItems[] = $obj;
                } else {
                    $validObjects[] = $obj;
                }
            }
            $objects = $validObjects;
        }

        // Build filter params for template
        $filterParams = [
            'type' => $this->typeFilter,
            'parent' => $this->parentId,
            'topLevel' => $this->topLevelOnly,
            'creator' => $this->creatorFilter,
            'subject' => $this->subjectFilter,
            'place' => $this->placeFilter,
            'genre' => $this->genreFilter,
            'level' => $this->levelFilter,
            'media' => $this->mediaFilter,
            'repo' => $this->repoFilter,
            'materialType' => $this->materialTypeFilter,
            'conditionGrade' => $this->conditionGradeFilter,
            'acquisitionMethod' => $this->acquisitionMethodFilter,
            'circulationStatus' => $this->circulationStatusFilter,
            'hasDigital' => $this->hasDigital,
            'view' => $viewMode,
            'limit' => $limit,
            'sort' => $sort,
            'dir' => $sortDir,
        ];

        // Alias local variables for view
        $typeFilter = $this->typeFilter;
        $parentId = $this->parentId;
        $topLevelOnly = $this->topLevelOnly;
        $hasDigital = $this->hasDigital;
        $creatorFilter = $this->creatorFilter;
        $subjectFilter = $this->subjectFilter;
        $placeFilter = $this->placeFilter;
        $genreFilter = $this->genreFilter;
        $levelFilter = $this->levelFilter;
        $mediaFilter = $this->mediaFilter;
        $repoFilter = $this->repoFilter;
        $materialTypeFilter = $this->materialTypeFilter;
        $conditionGradeFilter = $this->conditionGradeFilter;
        $acquisitionMethodFilter = $this->acquisitionMethodFilter;
        $circulationStatusFilter = $this->circulationStatusFilter;
        $queryFilter = $this->queryFilter;

        return view('ahg-display::display.browse', compact(
            'objects', 'total', 'totalPages', 'page', 'limit', 'sort', 'sortDir',
            'viewMode', 'typeFilter', 'parentId', 'topLevelOnly', 'parent', 'breadcrumb',
            'digitalObjectCount', 'types', 'creators', 'subjects', 'places', 'genres',
            'levels', 'mediaTypes', 'repositories', 'hasDigital', 'creatorFilter',
            'subjectFilter', 'placeFilter', 'genreFilter', 'levelFilter', 'mediaFilter',
            'repoFilter', 'queryFilter', 'filterParams', 'discoveryMode',
            'discoveryExpanded', 'discoveryMeta', 'correctedQuery', 'didYouMean',
            'originalQuery', 'esAssistedSearch', 'brokenItems',
            'materialTypes', 'conditionGrades', 'acquisitionMethods', 'circulationStatuses',
            'materialTypeFilter', 'conditionGradeFilter', 'acquisitionMethodFilter', 'circulationStatusFilter'
        ));
    }

    // =========================================================================
    // 3. BROWSE AJAX - AJAX version for embedded use
    // =========================================================================

    public function browseAjax(Request $request)
    {
        // Reuse browse logic to populate all variables
        $browseResponse = $this->browse($request);

        // Extract data from the browse view response
        $viewData = $browseResponse->getData();
        $viewData['showSidebar'] = $request->input('showSidebar', '1') === '1';
        $viewData['embedded'] = true;

        return view('ahg-display::display.browse', $viewData);
    }

    // =========================================================================
    // 4. PRINT VIEW - Print preview of browse results
    // =========================================================================

    public function printView(Request $request)
    {
        $culture = app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');

        $typeFilter = $request->input('type');
        $parentId = $request->input('parent');
        $topLevelOnly = $request->input('topLevel', self::defaultTopLevel());
        // settings.sort_browser_anonymous / sort_browser_user (#80): defaults
        // for browse sort when ?sort= isn't in the request. Anonymous + auth'd
        // users get separate defaults. Unrecognised tokens fall through the
        // switch below to the default title sort, so an operator-typed value
        // can never break the page.
        $defaultSort = auth()->check()
            ? \AhgCore\Support\GlobalSettings::sortBrowserUser()
            : \AhgCore\Support\GlobalSettings::sortBrowserAnonymous();
        $sort = $request->input('sort', $defaultSort !== '' ? $defaultSort : 'date');
        $defaultSortDir = auth()->check()
            ? \AhgCore\Support\GlobalSettings::sortBrowserDirectionUser()
            : \AhgCore\Support\GlobalSettings::sortBrowserDirectionAnonymous();
        $sortDir = $request->input('sortDir', $request->input('dir', $defaultSortDir));

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as i18n_fb', function ($j) use ($fallback) {
                $j->on('io.id', '=', 'i18n_fb.id')->where('i18n_fb.culture', '=', $fallback);
            })
            ->leftJoin('term_i18n as level', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'level.id')->where('level.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level_fb', function ($j) use ($fallback) {
                $j->on('io.level_of_description_id', '=', 'level_fb.id')->where('level_fb.culture', '=', $fallback);
            })
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', '>', 1)
            ->select(
                'io.id', 'io.identifier',
                DB::raw("COALESCE(NULLIF(i18n.title, ''), i18n_fb.title) AS title"),
                DB::raw("COALESCE(NULLIF(i18n.scope_and_content, ''), i18n_fb.scope_and_content) AS scope_and_content"),
                DB::raw("COALESCE(NULLIF(level.name, ''), level_fb.name) AS level_name"),
                'doc.object_type', 'slug.slug'
            );

        // Guests see only published records (status_id 160) — same gate as
        // browse()/applyFilters() so this public print view can't leak drafts (#1353).
        if (! auth()->check()) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status as pub_st')
                    ->whereRaw('pub_st.object_id = io.id')
                    ->where('pub_st.type_id', '=', 158)
                    ->where('pub_st.status_id', '=', 160);
            });
        }
        \AhgCore\Services\TermProtocolGate::excludeRestrictedRecords($query, 'io.id'); // #1388 export gate

        if ($parentId) {
            $query->where('io.parent_id', $parentId);
            $parent = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->where('io.id', $parentId)
                ->select('io.id', 'i18n.title')
                ->first();
        } elseif ($topLevelOnly === '1') {
            $query->where('io.parent_id', 1);
            $parent = null;
        } else {
            $parent = null;
        }

        if ($typeFilter) {
            $query->where('doc.object_type', $typeFilter);
        }

        // Handle sorting
        $safeSortDir = $sortDir === 'desc' ? 'desc' : 'asc';
        switch ($sort) {
            case 'identifier':
            case 'refcode':
                $query->orderBy('io.identifier', $safeSortDir);
                break;
            case 'date':
            case 'lastUpdated':          // settings vocabulary: "Most recent"
                $query->orderBy('io.id', $safeSortDir);
                break;
            case 'startdate':
                $query->leftJoin('event as evt_sort', 'io.id', '=', 'evt_sort.object_id');
                $query->orderByRaw("MIN(evt_sort.start_date) $safeSortDir");
                $query->groupBy('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'i18n_fb.title', 'i18n_fb.scope_and_content', 'level.name', 'level_fb.name', 'doc.object_type', 'slug.slug');
                break;
            case 'enddate':
                $query->leftJoin('event as evt_sort', 'io.id', '=', 'evt_sort.object_id');
                $query->orderByRaw("MAX(evt_sort.end_date) $safeSortDir");
                $query->groupBy('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'i18n_fb.title', 'i18n_fb.scope_and_content', 'level.name', 'level_fb.name', 'doc.object_type', 'slug.slug');
                break;
            case 'alphabetic':           // settings vocabulary: "Alphabetic"
            default:
                $this->applyAlphabeticSort($query, $safeSortDir, $culture);
        }

        $printCap = 500;
        $total    = (clone $query)->count('io.id');
        $objects  = $query->limit($printCap)->get()->toArray();
        $shown    = count($objects);

        return view('ahg-display::display.print', compact(
            'objects', 'total', 'shown', 'printCap', 'typeFilter', 'parentId', 'topLevelOnly', 'parent', 'sort', 'sortDir'
        ));
    }

    // =========================================================================
    // 5. EXPORT CSV - CSV export of browse results
    // =========================================================================

    public function exportCsv(Request $request)
    {
        $culture = app()->getLocale();

        $typeFilter = $request->input('type');
        $parentId = $request->input('parent');
        $topLevelOnly = $request->input('topLevel', self::defaultTopLevel());
        // settings.sort_browser_anonymous / sort_browser_user (#80): defaults
        // for browse sort when ?sort= isn't in the request. Anonymous + auth'd
        // users get separate defaults. Unrecognised tokens fall through the
        // switch below to the default title sort, so an operator-typed value
        // can never break the page.
        $defaultSort = auth()->check()
            ? \AhgCore\Support\GlobalSettings::sortBrowserUser()
            : \AhgCore\Support\GlobalSettings::sortBrowserAnonymous();
        $sort = $request->input('sort', $defaultSort !== '' ? $defaultSort : 'date');
        $defaultSortDir = auth()->check()
            ? \AhgCore\Support\GlobalSettings::sortBrowserDirectionUser()
            : \AhgCore\Support\GlobalSettings::sortBrowserDirectionAnonymous();
        $sortDir = $request->input('sortDir', $request->input('dir', $defaultSortDir));

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'level.id')->where('level.culture', '=', $culture);
            })
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->leftJoin('repository as r', 'io.repository_id', '=', 'r.id')
            ->leftJoin('actor_i18n as repo_name', function ($j) use ($culture) {
                $j->on('r.id', '=', 'repo_name.id')->where('repo_name.culture', '=', $culture);
            })
            ->where('io.id', '>', 1)
            ->select(
                'io.id',
                'io.identifier',
                'i18n.title',
                'i18n.scope_and_content',
                'i18n.extent_and_medium',
                'level.name as level_name',
                'doc.object_type',
                'repo_name.authorized_form_of_name as repository'
            );

        // Guests see only published records (status_id 160) — same gate as
        // browse()/applyFilters() so this public export can't leak drafts (#1353).
        if (! auth()->check()) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status as pub_st')
                    ->whereRaw('pub_st.object_id = io.id')
                    ->where('pub_st.type_id', '=', 158)
                    ->where('pub_st.status_id', '=', 160);
            });
        }
        \AhgCore\Services\TermProtocolGate::excludeRestrictedRecords($query, 'io.id'); // #1388 export gate

        if ($parentId) {
            $query->where('io.parent_id', $parentId);
        } elseif ($topLevelOnly === '1') {
            $query->where('io.parent_id', 1);
        }

        if ($typeFilter) {
            $query->where('doc.object_type', $typeFilter);
        }

        $sortColumn = match ($sort) {
            'identifier' => 'io.identifier',
            'refcode' => 'io.identifier',
            'date' => 'io.id',
            'lastUpdated' => 'io.id',       // settings vocabulary: "Most recent"
            'startdate' => 'io.id',
            'enddate' => 'io.id',
            'alphabetic' => 'i18n.title',   // settings vocabulary: "Alphabetic"
            default => 'i18n.title'
        };
        $query->orderBy($sortColumn, $sortDir === 'desc' ? 'desc' : 'asc');

        $objects = $query->limit(5000)->get()->toArray();
        $filename = 'glam_export_' . date('Y-m-d_His') . '.csv';

        $callback = function () use ($objects) {
            $output = fopen('php://output', 'w');
            // BOM for UTF-8
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($output, ['ID', 'Identifier', 'Title', 'Level', 'GLAM Type', 'Repository', 'Scope and Content', 'Extent']);

            foreach ($objects as $obj) {
                fputcsv($output, [
                    $obj->id,
                    $obj->identifier,
                    $obj->title,
                    $obj->level_name,
                    $obj->object_type,
                    $obj->repository,
                    $obj->scope_and_content,
                    $obj->extent_and_medium,
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    // =========================================================================
    // 6. CHANGE TYPE - Change object type (admin)
    // =========================================================================

    public function changeType(Request $request)
    {
        if (!auth()->check()) {
            abort(403);
        }

        $objectId = (int) $request->input('id');
        $newType = $request->input('type');
        $recursive = $request->input('recursive');

        $validTypes = ['archive', 'museum', 'gallery', 'library', 'dam', 'universal'];
        if (!in_array($newType, $validTypes)) {
            session()->flash('error', 'Invalid type');
            return redirect($request->headers->get('referer', route('glam.browse')));
        }

        DB::table('display_object_config')->updateOrInsert(
            ['object_id' => $objectId],
            ['object_type' => $newType, 'updated_at' => date('Y-m-d H:i:s')]
        );

        $count = 1;
        if ($recursive) {
            $count += $this->applyTypeRecursive($objectId, $newType);
        }

        session()->flash('success', "Type changed to '$newType' for $count object(s)");
        return redirect($request->headers->get('referer', route('glam.browse')));
    }

    // =========================================================================
    // 7. SET TYPE - Set object type (admin)
    // =========================================================================

    public function setType(Request $request)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $objectId = (int) $request->input('object_id');
        $type = $request->input('type');
        $recursive = $request->input('recursive');

        // This is an action endpoint; a bare GET (or missing params) should not
        // crash. Redirect back with a notice instead of passing null/0 downstream.
        if ($objectId <= 0 || $type === null || $type === '') {
            session()->flash('error', 'Object ID and type are required to set the object type.');

            return redirect($request->headers->get('referer', route('glam.index')));
        }

        $this->service->setObjectType($objectId, (string) $type);

        if ($recursive) {
            $count = $this->service->setObjectTypeRecursive($objectId, $type);
            session()->flash('success', 'Set type for ' . ($count + 1) . ' objects');
        } else {
            session()->flash('success', 'Object type set');
        }

        return redirect($request->headers->get('referer', route('glam.index')));
    }

    // =========================================================================
    // 8. ASSIGN PROFILE - Assign display profile (admin)
    // =========================================================================

    public function assignProfile(Request $request)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $objectId = (int) $request->input('object_id');
        $profileId = (int) $request->input('profile_id');
        $context = $request->input('context', 'default');
        $primary = $request->input('primary') ? true : false;

        // Action endpoint: a bare GET (or missing params) must not hit the DB with
        // zero ids and trigger a foreign-key violation. Redirect back gracefully.
        if ($objectId <= 0 || $profileId <= 0) {
            session()->flash('error', 'Object ID and profile ID are required to assign a profile.');

            return redirect($request->headers->get('referer', route('glam.index')));
        }

        $this->service->assignProfile($objectId, $profileId, $context, $primary);

        session()->flash('success', 'Profile assigned');
        return redirect($request->headers->get('referer', route('glam.index')));
    }

    // =========================================================================
    // 9. PROFILES - List display profiles (admin)
    // =========================================================================

    public function profiles()
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $culture = app()->getLocale();

        $profiles = DB::table('display_profile as dp')
            ->leftJoin('display_profile_i18n as dpi', function ($j) use ($culture) {
                $j->on('dp.id', '=', 'dpi.id')->where('dpi.culture', '=', $culture);
            })
            ->select('dp.*', 'dpi.name', 'dpi.description')
            ->orderBy('dp.domain')->orderBy('dp.sort_order')
            ->get()->toArray();

        return view('ahg-display::display.profiles', compact('profiles'));
    }

    // =========================================================================
    // 10. LEVELS - List levels of description (admin)
    // =========================================================================

    public function levels(Request $request)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $domain = $request->input('domain');
        $levels = $domain ? $this->service->getLevels($domain) : $this->service->getLevels();
        $currentDomain = $domain;
        $domains = ['archive', 'museum', 'gallery', 'library', 'dam', 'universal'];

        return view('ahg-display::display.levels', compact('levels', 'currentDomain', 'domains'));
    }

    // =========================================================================
    // 11. FIELDS - List field mappings (admin)
    // =========================================================================

    public function fields()
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $culture = app()->getLocale();

        $fields = DB::table('display_field as df')
            ->leftJoin('display_field_i18n as dfi', function ($j) use ($culture) {
                $j->on('df.id', '=', 'dfi.id')->where('dfi.culture', '=', $culture);
            })
            ->select('df.*', 'dfi.name', 'dfi.help_text')
            ->orderBy('df.field_group')->orderBy('df.sort_order')
            ->get()->toArray();

        // The view iterates $fieldGroups as [groupName => [field, ...]] and calls
        // count() on the inner value, so it must be an associative map of group
        // name to the list of fields in that group (not a flat list of names).
        $fieldGroups = [];
        foreach ($fields as $field) {
            $group = $field->field_group ?? 'other';
            $fieldGroups[$group][] = $field;
        }

        return view('ahg-display::display.fields', compact('fields', 'fieldGroups'));
    }

    // =========================================================================
    // 12. BULK SET TYPE - Bulk set object types (admin)
    // =========================================================================

    public function bulkSetType(Request $request)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $culture = app()->getLocale();

        if ($request->isMethod('post')) {
            $parentId = (int) $request->input('parent_id');
            $type = $request->input('type');
            $this->service->setObjectType($parentId, $type);
            $count = $this->service->setObjectTypeRecursive($parentId, $type);
            session()->flash('success', 'Updated ' . ($count + 1) . ' objects to type: ' . $type);
            return redirect(route('glam.index'));
        }

        $collections = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('io.parent_id', 1)
            ->select('io.id', 'io.identifier', 'i18n.title')
            ->orderBy('i18n.title')
            ->get()->toArray();

        $collectionTypes = $this->service->getCollectionTypes();

        return view('ahg-display::display.bulk-set-type', compact('collections', 'collectionTypes'));
    }

    // =========================================================================
    // 13. BROWSE SETTINGS - User browse settings page
    // =========================================================================

    public function browseSettings(Request $request)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $userId = auth()->id();
        $settings = $this->settingsRepo->getSettings($userId);

        if ($request->isMethod('post')) {
            $data = [
                'use_glam_browse' => $request->input('use_glam_browse') ? 1 : 0,
                'default_sort_field' => $request->input('default_sort_field', 'updated_at'),
                'default_sort_direction' => $request->input('default_sort_direction', 'desc'),
                'default_view' => $request->input('default_view', 'list'),
                'items_per_page' => max(10, min(100, (int) $request->input('items_per_page', 30))),
                'show_facets' => $request->input('show_facets') ? 1 : 0,
                'remember_filters' => $request->input('remember_filters') ? 1 : 0,
            ];

            if ($this->settingsRepo->saveSettings($userId, $data)) {
                session()->flash('success', 'Browse settings saved');
            } else {
                session()->flash('error', 'Failed to save settings');
            }

            return redirect(route('glam.get.settings'));
        }

        return view('ahg-display::display.browse-settings', compact('settings'));
    }

    // =========================================================================
    // 14. TOGGLE GLAM BROWSE - AJAX toggle GLAM browse
    // =========================================================================

    public function toggleGlamBrowse(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'error' => 'Not authenticated']);
        }

        $enabled = $request->input('enabled') === '1';
        $success = $this->settingsRepo->setGlamBrowse(auth()->id(), $enabled);

        return response()->json([
            'success' => $success,
            'enabled' => $enabled,
        ]);
    }

    // =========================================================================
    // 15. SAVE BROWSE SETTINGS - AJAX save browse settings
    // =========================================================================

    public function saveBrowseSettings(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'error' => 'Not authenticated']);
        }

        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'error' => 'Invalid method']);
        }

        $data = $request->json()->all() ?: $request->all();
        $success = $this->settingsRepo->saveSettings(auth()->id(), $data);

        return response()->json(['success' => $success]);
    }

    // =========================================================================
    // 16. GET BROWSE SETTINGS - AJAX get browse settings
    // =========================================================================

    public function getBrowseSettings(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'error' => 'Not authenticated']);
        }

        $settings = $this->settingsRepo->getSettings(auth()->id());

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    // =========================================================================
    // 17. RESET BROWSE SETTINGS - AJAX reset browse settings
    // =========================================================================

    public function resetBrowseSettings(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'error' => 'Not authenticated']);
        }

        $success = $this->settingsRepo->resetSettings(auth()->id());

        return response()->json(['success' => $success]);
    }

    // =========================================================================
    // PROTECTED METHODS
    // =========================================================================

    /**
     * Apply all active filters to a query builder instance.
     * Used by both browse count query and main result query.
     */
    protected function applyFilters($query): void
    {
        // Filter by publication status - only show Published items (status_id = 160) for guests
        // Authenticated users (editors/admins) can see all items
        if (!$this->isAuthenticated) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status as pub_st')
                    ->whereRaw('pub_st.object_id = io.id')
                    ->where('pub_st.type_id', '=', 158)
                    ->where('pub_st.status_id', '=', 160);
            });
        }

        // #1388: hide records tagged with a restricted-protocol term from
        // guests/non-editors (editors/admins bypass inside the gate).
        \AhgCore\Services\TermProtocolGate::excludeRestrictedRecords($query, 'io.id');

        if ($this->parentId) {
            // #1333 read-swap: the record itself + all descendants via the closure
            // table. scopeDescendants uses the closure when built, and falls back
            // to the lft/rgt range, then to direct children when there are no
            // nested-set bounds - so this is identical to the old MPTT logic
            // pre-build and a drift-correcting superset once the closure exists.
            app(\AhgCore\Services\HierarchyQueryService::class)
                ->scopeDescendants($query, 'information_object', (int) $this->parentId, 'io.id', true);
        } elseif ($this->topLevelOnly === '1') {
            $query->where('io.parent_id', 1);
        }

        if ($this->typeFilter) {
            $query->where('doc.object_type', $this->typeFilter);
        }

        if ($this->hasDigital) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('digital_object')
                    ->whereRaw('digital_object.object_id = io.id')
                    ->whereNull('digital_object.parent_id');
            });
        }

        if ($this->creatorFilter) {
            // For DAM type, use dam_iptc_metadata.creator field (stores actor name as string)
            // For other types, use event.actor_id (stores actor ID)
            if ($this->typeFilter === 'dam') {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('dam_iptc_metadata')
                        ->whereColumn('dam_iptc_metadata.object_id', 'io.id')
                        ->where('dam_iptc_metadata.creator', $this->creatorFilter);
                });
            } else {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('event')
                        ->whereRaw('event.object_id = io.id')
                        ->where('event.actor_id', $this->creatorFilter);
                });
            }
        }

        $useDenorm = $this->useDenormFacets();
        $relTable  = $useDenorm ? 'ahg_io_facet_denorm' : 'object_term_relation';
        $relIoCol  = $useDenorm ? 'io_id' : 'object_id';

        if ($this->subjectFilter) {
            $query->whereExists(function ($q) use ($relTable, $relIoCol, $useDenorm) {
                $q->select(DB::raw(1))
                    ->from($relTable)
                    ->whereRaw("{$relTable}.{$relIoCol} = io.id")
                    ->where("{$relTable}.term_id", $this->subjectFilter);
                if ($useDenorm) {
                    $q->where("{$relTable}.taxonomy_id", 35);
                }
            });
        }

        if ($this->placeFilter) {
            $query->whereExists(function ($q) use ($relTable, $relIoCol, $useDenorm) {
                $q->select(DB::raw(1))
                    ->from($relTable)
                    ->whereRaw("{$relTable}.{$relIoCol} = io.id")
                    ->where("{$relTable}.term_id", $this->placeFilter);
                if ($useDenorm) {
                    $q->where("{$relTable}.taxonomy_id", 42);
                }
            });
        }

        if ($this->genreFilter) {
            $query->whereExists(function ($q) use ($relTable, $relIoCol, $useDenorm) {
                $q->select(DB::raw(1))
                    ->from($relTable)
                    ->whereRaw("{$relTable}.{$relIoCol} = io.id")
                    ->where("{$relTable}.term_id", $this->genreFilter);
                if ($useDenorm) {
                    $q->where("{$relTable}.taxonomy_id", 78);
                }
            });
        }

        if ($this->levelFilter) {
            $query->where('io.level_of_description_id', $this->levelFilter);
        }

        if ($this->mediaFilter) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('digital_object')
                    ->whereRaw('digital_object.object_id = io.id')
                    ->whereNull('digital_object.parent_id')
                    ->whereRaw('digital_object.mime_type LIKE ?', [$this->mediaFilter . '/%']);
            });
        }

        if ($this->repoFilter) {
            $query->where('io.repository_id', $this->repoFilter);
        }

        // Library-only filters: each scopes the result set to IOs whose paired
        // library_item row matches the value (and therefore excludes non-library
        // records entirely when any of these is set).
        $libFilters = [
            'material_type'      => $this->materialTypeFilter,
            'condition_grade'    => $this->conditionGradeFilter,
            'acquisition_method' => $this->acquisitionMethodFilter,
            'circulation_status' => $this->circulationStatusFilter,
        ];
        foreach ($libFilters as $col => $val) {
            if ($val === null || $val === '') continue;
            $query->whereExists(function ($q) use ($col, $val) {
                $q->select(DB::raw(1))
                    ->from('library_item as li_w')
                    ->whereRaw('li_w.information_object_id = io.id')
                    ->where("li_w.{$col}", $val);
            });
        }

        // Multi-tenant scope. Applied after the user-driven repoFilter so a
        // tenant user can't widen their scope via URL param: both ANDed, the
        // narrower wins. No-op when multi-tenancy is disabled or the user is
        // a Heratio admin.
        TenantScope::apply($query, 'io.repository_id');

        // Text search filters - use OR logic for semantic search
        if ($this->queryFilter) {
            if ($this->queryFilterTerms) {
                // Semantic search: search for ANY of the expanded terms (OR logic)
                $this->applyTextSearchFilter($query, $this->queryFilterTerms);
            } else {
                // Normal search: single term
                $this->applyTextSearchFilter($query, $this->queryFilter);
            }
        }

        if ($this->titleFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('information_object_i18n as ioi')
                    ->whereRaw('ioi.id = io.id')
                    ->where('ioi.title', 'like', '%' . $this->titleFilter . '%');
            });
        }

        if ($this->identifierFilter) {
            $query->where('io.identifier', 'like', '%' . $this->identifierFilter . '%');
        }

        if ($this->scopeAndContentFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('information_object_i18n as ioi')
                    ->whereRaw('ioi.id = io.id')
                    ->where('ioi.scope_and_content', 'like', '%' . $this->scopeAndContentFilter . '%');
            });
        }

        if ($this->creatorSearchFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('event as e')
                    ->join('actor_i18n as ai', function ($j) {
                        $j->on('e.actor_id', '=', 'ai.id')->where('ai.culture', '=', app()->getLocale());
                    })
                    ->whereRaw('e.object_id = io.id')
                    ->where('ai.authorized_form_of_name', 'like', '%' . $this->creatorSearchFilter . '%');
            });
        }

        if ($this->subjectSearchFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($j) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', app()->getLocale());
                    })
                    ->whereRaw('otr.object_id = io.id')
                    ->where('t.taxonomy_id', 35)
                    ->where('ti.name', 'like', '%' . $this->subjectSearchFilter . '%');
            });
        }

        if ($this->placeSearchFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($j) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', app()->getLocale());
                    })
                    ->whereRaw('otr.object_id = io.id')
                    ->where('t.taxonomy_id', 42)
                    ->where('ti.name', 'like', '%' . $this->placeSearchFilter . '%');
            });
        }

        if ($this->genreSearchFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($j) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', app()->getLocale());
                    })
                    ->whereRaw('otr.object_id = io.id')
                    ->where('t.taxonomy_id', 78)
                    ->where('ti.name', 'like', '%' . $this->genreSearchFilter . '%');
            });
        }

        if ($this->referenceCodeFilter) {
            $query->where('io.identifier', 'like', '%' . $this->referenceCodeFilter . '%');
        }

        if ($this->extentAndMediumFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('information_object_i18n as ioi')
                    ->whereRaw('ioi.id = io.id')
                    ->where('ioi.extent_and_medium', 'like', '%' . $this->extentAndMediumFilter . '%');
            });
        }

        if ($this->archivalHistoryFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('information_object_i18n as ioi')
                    ->whereRaw('ioi.id = io.id')
                    ->where('ioi.archival_history', 'like', '%' . $this->archivalHistoryFilter . '%');
            });
        }

        if ($this->acquisitionFilter) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('information_object_i18n as ioi')
                    ->whereRaw('ioi.id = io.id')
                    ->where('ioi.acquisition', 'like', '%' . $this->acquisitionFilter . '%');
            });
        }

        if ($this->startDateFilter || $this->endDateFilter) {
            $startDate = $this->startDateFilter;
            $endDate = $this->endDateFilter;
            $rangeType = $this->rangeTypeFilter ?? 'inclusive';

            $query->whereExists(function ($sub) use ($startDate, $endDate, $rangeType) {
                $sub->select(DB::raw(1))
                    ->from('event as evt_date')
                    ->whereRaw('evt_date.object_id = io.id');

                if ($rangeType === 'exact') {
                    if ($startDate) {
                        $sub->where('evt_date.start_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $sub->where('evt_date.end_date', '<=', $endDate);
                    }
                } else {
                    // Inclusive/overlapping: event overlaps with search range
                    if ($startDate) {
                        $sub->where(function ($q) use ($startDate) {
                            $q->where('evt_date.end_date', '>=', $startDate)
                                ->orWhereNull('evt_date.end_date');
                        });
                    }
                    if ($endDate) {
                        $sub->where(function ($q) use ($endDate) {
                            $q->where('evt_date.start_date', '<=', $endDate)
                                ->orWhereNull('evt_date.start_date');
                        });
                    }
                }
            });
        }
    }

    /**
     * Build breadcrumb trail from an object ID up to root.
     */
    protected function buildBreadcrumb(int $objectId): array
    {
        $breadcrumb = [];
        $currentId = $objectId;
        $maxDepth = 20;
        $culture = app()->getLocale();

        while ($currentId > 1 && $maxDepth-- > 0) {
            $item = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                ->where('io.id', $currentId)
                ->select('io.id', 'io.parent_id', 'i18n.title', 'slug.slug')
                ->first();
            if (!$item) break;
            array_unshift($breadcrumb, $item);
            $currentId = $item->parent_id;
        }
        return $breadcrumb;
    }

    /**
     * Recursively apply a type to all children of a parent object.
     */
    protected function applyTypeRecursive(int $parentId, string $type): int
    {
        $children = DB::table('information_object')->where('parent_id', $parentId)->pluck('id')->toArray();
        $count = 0;
        foreach ($children as $childId) {
            DB::table('display_object_config')->updateOrInsert(
                ['object_id' => $childId],
                ['object_type' => $type, 'updated_at' => date('Y-m-d H:i:s')]
            );
            $count++;
            $count += $this->applyTypeRecursive($childId, $type);
        }
        return $count;
    }

    /**
     * Pattern C — read facet term relations from ahg_io_facet_denorm sidecar
     * instead of joining object_term_relation → term, when the flag is on.
     * See docs/adr/0001-atom-base-schema-readonly-sidecar-pattern.md.
     */
    protected function useDenormFacets(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        return $cached = SettingHelper::get('ahg_display_use_facet_denorm', '0') === '1';
    }

    /**
     * Get cached facet data from display_facet_cache table.
     *
     * @param string $facetType The facet type, e.g. 'subject', 'subject_all', 'glam_type', 'glam_type_all'
     * @param string|null $nameField Override for the name field in returned objects (default: 'name')
     * @return array Array of facet objects with id, name, and count
     */
    protected function getCachedFacet(string $facetType, ?string $nameField = null): array
    {
        $results = DB::table('display_facet_cache')
            ->where('facet_type', $facetType)
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        if ($results->isEmpty()) {
            return [];
        }

        // Strip _all suffix to determine base type for field mapping
        $baseType = str_replace('_all', '', $facetType);
        $nameField = $nameField ?? 'name';

        return $results->map(function ($row) use ($nameField, $baseType) {
            $obj = new \stdClass();
            // For glam_type and media_type, use term_name as the primary field (no id)
            if (in_array($baseType, ['glam_type', 'media_type'])) {
                $obj->$nameField = $row->term_name;
            } else {
                $obj->id = $row->term_id;
                $obj->$nameField = $row->term_name;
            }
            $obj->count = $row->count;
            return $obj;
        })->toArray();
    }

    /**
     * Compute live facet counts scoped to the current browse filters.
     * Returns array of stdClass with id, name, count (matching getCachedFacet format).
     */
    protected function getLiveFacet(string $dimension): array
    {
        // #errorlog — a facet is a sidebar convenience; a DB hiccup (transient
        // 're-prepare' / table-cache eviction during nightly maintenance) must
        // drop just this facet, never 500 the whole browse page.
        try {
        $culture = app()->getLocale();

        // The type facet must always expose every sibling bucket so the user
        // can pivot between sectors. If we let applyFilters() pin the query
        // to the currently-selected type (eg. default_sector='archive'), the
        // GROUP BY collapses to a single row and the sidebar reads as a
        // single locked option. Drop the type filter for this dimension only.
        //
        // Library-only dimensions (material_type / condition_grade /
        // acquisition_method / circulation_status) need the same treatment:
        // when default_sector='archive', honoring the type filter excludes
        // every library_item from the count and the facet vanishes, even
        // though the user *would* want to pivot to library by clicking it.
        $libraryDimensions = ['material_type', 'condition_grade', 'acquisition_method', 'circulation_status'];
        $excludeTypeFilter = $dimension === 'type' || in_array($dimension, $libraryDimensions, true);
        $savedTypeFilter = $this->typeFilter;
        if ($excludeTypeFilter) {
            $this->typeFilter = null;
        }

        $query = DB::table('information_object as io')
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->where('io.id', '>', 1);

        $this->applyFilters($query);

        if ($excludeTypeFilter) {
            $this->typeFilter = $savedTypeFilter;
        }

        switch ($dimension) {
            case 'type':
                $query->select('doc.object_type as facet_id', DB::raw('doc.object_type as facet_name'), DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->whereNotNull('doc.object_type')
                    ->groupBy('doc.object_type');
                return $query->orderByDesc('cnt')->limit(10)->get()->map(function ($r) {
                    $obj = new \stdClass();
                    $obj->object_type = $r->facet_name;
                    $obj->count = $r->cnt;
                    return $obj;
                })->toArray();

            case 'creator':
                $query->join('event as ef', 'ef.object_id', '=', 'io.id')
                    ->join('actor_i18n as ai', function ($j) use ($culture) {
                        $j->on('ef.actor_id', '=', 'ai.id')->where('ai.culture', '=', $culture);
                    })
                    ->select('ef.actor_id as facet_id', 'ai.authorized_form_of_name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('ef.actor_id', 'ai.authorized_form_of_name');
                break;

            case 'subject':
                if ($this->useDenormFacets()) {
                    $query->join('ahg_io_facet_denorm as fd_s', function ($j) {
                            $j->on('fd_s.io_id', '=', 'io.id')->where('fd_s.taxonomy_id', '=', 35);
                        })
                        ->join('term_i18n as tis', function ($j) use ($culture) { $j->on('fd_s.term_id', '=', 'tis.id')->where('tis.culture', '=', $culture); })
                        ->select('fd_s.term_id as facet_id', 'tis.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                        ->groupBy('fd_s.term_id', 'tis.name');
                } else {
                    $query->join('object_term_relation as otr_s', 'otr_s.object_id', '=', 'io.id')
                        ->join('term as ts', function ($j) { $j->on('otr_s.term_id', '=', 'ts.id')->where('ts.taxonomy_id', '=', 35); })
                        ->join('term_i18n as tis', function ($j) use ($culture) { $j->on('ts.id', '=', 'tis.id')->where('tis.culture', '=', $culture); })
                        ->select('ts.id as facet_id', 'tis.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                        ->groupBy('ts.id', 'tis.name');
                }
                break;

            case 'place':
                if ($this->useDenormFacets()) {
                    $query->join('ahg_io_facet_denorm as fd_p', function ($j) {
                            $j->on('fd_p.io_id', '=', 'io.id')->where('fd_p.taxonomy_id', '=', 42);
                        })
                        ->join('term_i18n as tip', function ($j) use ($culture) { $j->on('fd_p.term_id', '=', 'tip.id')->where('tip.culture', '=', $culture); })
                        ->select('fd_p.term_id as facet_id', 'tip.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                        ->groupBy('fd_p.term_id', 'tip.name');
                } else {
                    $query->join('object_term_relation as otr_p', 'otr_p.object_id', '=', 'io.id')
                        ->join('term as tp', function ($j) { $j->on('otr_p.term_id', '=', 'tp.id')->where('tp.taxonomy_id', '=', 42); })
                        ->join('term_i18n as tip', function ($j) use ($culture) { $j->on('tp.id', '=', 'tip.id')->where('tip.culture', '=', $culture); })
                        ->select('tp.id as facet_id', 'tip.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                        ->groupBy('tp.id', 'tip.name');
                }
                break;

            case 'genre':
                if ($this->useDenormFacets()) {
                    $query->join('ahg_io_facet_denorm as fd_g', function ($j) {
                            $j->on('fd_g.io_id', '=', 'io.id')->where('fd_g.taxonomy_id', '=', 78);
                        })
                        ->join('term_i18n as tig', function ($j) use ($culture) { $j->on('fd_g.term_id', '=', 'tig.id')->where('tig.culture', '=', $culture); })
                        ->select('fd_g.term_id as facet_id', 'tig.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                        ->groupBy('fd_g.term_id', 'tig.name');
                } else {
                    $query->join('object_term_relation as otr_g', 'otr_g.object_id', '=', 'io.id')
                        ->join('term as tg', function ($j) { $j->on('otr_g.term_id', '=', 'tg.id')->where('tg.taxonomy_id', '=', 78); })
                        ->join('term_i18n as tig', function ($j) use ($culture) { $j->on('tg.id', '=', 'tig.id')->where('tig.culture', '=', $culture); })
                        ->select('tg.id as facet_id', 'tig.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                        ->groupBy('tg.id', 'tig.name');
                }
                break;

            case 'level':
                $query->join('term_i18n as til', function ($j) use ($culture) {
                        $j->on('io.level_of_description_id', '=', 'til.id')->where('til.culture', '=', $culture);
                    })
                    ->whereNotNull('io.level_of_description_id')
                    ->select('io.level_of_description_id as facet_id', 'til.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('io.level_of_description_id', 'til.name');
                break;

            case 'repository':
                $query->join('actor_i18n as rai', function ($j) use ($culture) {
                        $j->on('io.repository_id', '=', 'rai.id')->where('rai.culture', '=', $culture);
                    })
                    ->whereNotNull('io.repository_id')
                    ->select('io.repository_id as facet_id', 'rai.authorized_form_of_name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('io.repository_id', 'rai.authorized_form_of_name');
                break;

            case 'material_type':
            case 'condition_grade':
            case 'acquisition_method':
            case 'circulation_status':
                $libCol = $dimension;
                $query->join('library_item as li_f', 'li_f.information_object_id', '=', 'io.id')
                    ->whereNotNull("li_f.{$libCol}")
                    ->where("li_f.{$libCol}", '!=', '')
                    ->select("li_f.{$libCol} as facet_id", DB::raw("li_f.{$libCol} as facet_name"), DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy("li_f.{$libCol}");
                return $query->orderByDesc('cnt')->limit(30)->get()->map(function ($r) use ($libCol) {
                    $obj = new \stdClass();
                    $obj->id = $r->facet_id;
                    $obj->name = $r->facet_name;
                    $obj->count = $r->cnt;
                    return $obj;
                })->toArray();

            case 'media_type':
                $query->join('digital_object as dof', function ($j) {
                        $j->on('dof.object_id', '=', 'io.id')->whereNull('dof.parent_id');
                    })
                    ->select(DB::raw("SUBSTRING_INDEX(dof.mime_type, '/', 1) as facet_id"), DB::raw("SUBSTRING_INDEX(dof.mime_type, '/', 1) as facet_name"), DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy(DB::raw("SUBSTRING_INDEX(dof.mime_type, '/', 1)"));
                return $query->orderByDesc('cnt')->limit(10)->get()->map(function ($r) {
                    $obj = new \stdClass();
                    $obj->media_type = $r->facet_name;
                    $obj->count = $r->cnt;
                    return $obj;
                })->toArray();

            default:
                return [];
        }

        return $query->orderByDesc('cnt')->limit(10)->get()->map(function ($r) {
            $obj = new \stdClass();
            $obj->id = $r->facet_id;
            $obj->name = $r->facet_name;
            $obj->count = $r->cnt;
            return $obj;
        })->toArray();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[browse] facet "'.$dimension.'" dropped: '.$e->getMessage());
            return [];
        }
    }

    /**
     * Check if FULLTEXT indexes are available on information_object_i18n.
     * Cached per request (static property).
     */
    protected function isFulltextAvailable(): bool
    {
        if (self::$fulltextAvailable !== null) {
            return self::$fulltextAvailable;
        }

        try {
            $result = DB::select("SHOW INDEX FROM information_object_i18n WHERE Key_name = 'ft_ioi_title'");
            self::$fulltextAvailable = !empty($result);
        } catch (\Exception $e) {
            self::$fulltextAvailable = false;
        }

        return self::$fulltextAvailable;
    }

    /**
     * Order a browse query alphabetically by title.
     *
     * information_object_i18n.title is varchar(1024) and its only covering
     * index stores a 191-char PREFIX; MySQL cannot use a prefix index to
     * satisfy an ORDER BY, so sorting off the base column filesorts the whole
     * 454,393-row table on every page - about 5-10s, essentially the entire
     * cost of browse. The sidecar holds the same value already resolved for
     * culture and truncated to a width that IS fully indexable, so ordering by
     * it is an index scan.
     *
     * Falls back to the base column whenever the sidecar is missing or not yet
     * populated, so behaviour is always correct - only the speed differs.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function applyAlphabeticSort($query, string $safeSortDir, string $culture): void
    {
        // io.id breaks ties so paging stays stable. Duplicate titles are common
        // in real catalogues - atom.theahg.co.za has long runs of identically
        // named scans such as "1006_49.pdf" - and without a tiebreaker their
        // relative order is whatever the plan happens to emit, so a LIMIT/OFFSET
        // boundary landing inside a tied run can show a record on two
        // consecutive pages or skip it entirely.
        $query->orderBy('i18n.title', $safeSortDir)->orderBy('io.id', $safeSortDir);
    }

    /**
     * Which sidecar column, if any, can serve this sort token?
     *
     * Only the two text sorts need it - both order by a varchar(1024) that
     * cannot be ordered by index (title has a prefix-only index, identifier has
     * none at all). date/lastUpdated/relevance already sort on io.id, and
     * startdate/enddate aggregate over `event`, so all of those stay on the
     * ordinary path. Anything unrecognised falls through the switch's
     * `default:` to the title sort, so it maps to title_sort here too.
     */
    protected function sidecarSortColumn(string $sort): ?string
    {
        return match ($sort) {
            'identifier', 'refcode' => 'identifier_sort',
            // date/lastUpdated order by io.id, which is indexed - but the
            // JOINed query still cannot use it, because the published EXISTS
            // becomes a semi-join that drives from `status` and forces a sort
            // anyway (~8.5s on atom.theahg.co.za). Routing it through the same
            // single-table id-first query answers it off the sidecar's primary
            // key instead: 2ms. ts.object_id IS io.id, so the order is the same.
            'date', 'lastUpdated' => 'object_id',
            // The date sorts order by MIN(event.start_date) / MAX(event.end_date).
            // The sidecar stores those aggregates precomputed, so the join to
            // `event` and the GROUP BY over every selected column both go away.
            'startdate' => 'start_date_sort',
            'enddate' => 'end_date_sort',
            // relevance layers a CASE expression over the matched text, which
            // does not reduce to a sidecar column - but that CASE only exists
            // when there IS a query. With no query relevance is nothing more
            // than io.id desc, identical to lastUpdated, so it can take the
            // fast path (17.5s -> ~7s) whenever no text filter is set.
            'relevance' => $this->queryFilter ? null : 'object_id',
            default => 'title_sort',
        };
    }

    /**
     * Fetch one page of ids in title order, driving off the sort sidecar.
     *
     * WHY IT IS SHAPED LIKE THIS. The sidecar's index is only usable for
     * ordering when the sidecar is the ONLY table in the FROM clause. Join
     * information_object to it - even INNER, even with JOIN_PREFIX /
     * NO_SEMIJOIN hints or semijoin=off - and MySQL converts the published
     * EXISTS into a semi-join, drives from `status`, and falls back to
     * "Using temporary; Using filesort", which is exactly the ~9.2s cost the
     * sidecar was meant to remove. Measured on atom.theahg.co.za: joined
     * 9,751ms vs base column 9,234ms, i.e. no gain at all.
     *
     * Keeping information_object inside a correlated EXISTS instead leaves the
     * outer query single-table, so the plan becomes "Backward index scan; Using
     * index" and stops as soon as it has $limit matches: 2ms for page 1 and
     * 60ms at offset 5000. applyFilters() only ever adds where-clauses against
     * io.*, never joins, so the whole filter set travels into the subquery
     * unchanged and the page is identical to the one the old ORDER BY produced.
     *
     * Returns null to mean "fall back to the ordinary path" - never an empty
     * array, which is a legitimate no-results answer.
     *
     * @return array<int,int>|null
     */
    protected function alphabeticIdPage(string $culture, string $safeSortDir, int $page, int $limit, string $column = 'title_sort'): ?array
    {
        if (! TitleSortService::available($column)) {
            return null;
        }

        try {
            return DB::table('information_object_title_sort as ts')
                ->where('ts.culture', $culture)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('information_object as io')
                        // applyFilters() filters on doc.object_type for the
                        // sector/type filter, so the subquery has to carry the
                        // same join the main and count queries do. Without it
                        // the filter throws, the catch below swallows it, and
                        // the page silently reverts to the slow path - which is
                        // exactly the sort of invisible regression this join
                        // prevents. Any future alias applyFilters() reaches for
                        // must be added here too.
                        ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
                        ->whereColumn('io.id', 'ts.object_id')
                        ->where('io.id', '>', 1);
                    // Same filters as the main query - including the published
                    // gate and the #1388 cultural-protocol exclusions.
                    $this->applyFilters($sub);
                })
                // Both keys in the same direction: a mixed DESC/ASC pair cannot
                // be served by a single-direction index and reintroduces the sort.
                // When object_id IS the sort key there is nothing left to tie
                // on, so it is not repeated.
                ->orderBy('ts.'.$column, $safeSortDir)
                ->when($column !== 'object_id', fn ($q) => $q->orderBy('ts.object_id', $safeSortDir))
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->pluck('ts.object_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve a text search to a concrete set of information_object ids.
     *
     * WHY THIS EXISTS. The LIKE branch of applyTextSearchFilter() attaches ~9
     * correlated EXISTS subqueries to the browse query. Correlated means they
     * are re-evaluated per candidate row of a 454,393-row information_object,
     * and the browse page runs that same predicate EIGHT times over - once for
     * the result page, once for the count, and once per sidebar facet. On
     * atom.theahg.co.za `?query=apartheid` cost ~13-15s per execution, ~100s
     * total, and 502'd at nginx's proxy timeout.
     *
     * Run uncorrelated instead and the same work is trivial: each branch is one
     * flat scan of its own (much smaller) table, and the union is the answer.
     * Measured on that query: 1,126ms to resolve, yielding 155 ids - after
     * which all eight downstream queries filter on an indexed primary key.
     *
     * Returns null when the caller should keep the old correlated path: either
     * the match set is too wide to inline safely (MySQL caps a prepared
     * statement at 65,535 placeholders, and past a certain width the id list
     * stops being cheaper than the scan), or FULLTEXT is in play and has its
     * own semantics. Null means "fall back", NOT "no matches" - an empty array
     * is the real no-matches answer and must still filter everything out.
     *
     * @param  array|string  $searchTerms
     * @return array<int,int>|null
     */
    protected function resolveTextMatchIds($searchTerms): ?array
    {
        $terms = array_filter(array_map('strval', (array) $searchTerms), fn ($t) => trim($t) !== '');
        if (empty($terms)) {
            return null;
        }

        $cacheKey = implode("\x00", $terms);
        if (array_key_exists($cacheKey, self::$textMatchIdCache)) {
            return self::$textMatchIdCache[$cacheKey];
        }

        $sectorTables = $this->getSectorSearchTables();
        $ids = [];

        try {
            foreach ($terms as $term) {
                $like = '%'.\AhgCore\Support\EscapeQueriesHelper::escapeForLike($term).'%';

                $collect = function ($rows) use (&$ids) {
                    foreach ($rows as $r) {
                        if ($r->id !== null) {
                            $ids[(int) $r->id] = true;
                        }
                    }
                    return count($ids) > self::TEXT_MATCH_ID_CAP;
                };

                // Mirrors applyTextSearchFilter()'s own title/scope/identifier pair.
                if ($collect(DB::table('information_object_i18n')->select('id')
                    ->where(fn ($w) => $w->where('title', 'like', $like)
                        ->orWhere('scope_and_content', 'like', $like))->get())) {
                    return self::$textMatchIdCache[$cacheKey] = null;
                }
                if ($collect(DB::table('information_object')->select('id')
                    ->where('identifier', 'like', $like)->get())) {
                    return self::$textMatchIdCache[$cacheKey] = null;
                }

                // Mirrors applySectorSearchClauses(), table-for-table and
                // column-for-column, gated on the same existence check.
                if (in_array('dam_iptc_metadata', $sectorTables)) {
                    if ($collect(DB::table('dam_iptc_metadata')->select('object_id as id')
                        ->where(fn ($w) => $w->where('creator', 'like', $like)
                            ->orWhere('headline', 'like', $like)
                            ->orWhere('caption', 'like', $like)
                            ->orWhere('keywords', 'like', $like))->get())) {
                        return self::$textMatchIdCache[$cacheKey] = null;
                    }
                }
                if (in_array('museum_metadata', $sectorTables)) {
                    if ($collect(DB::table('museum_metadata')->select('object_id as id')
                        ->where(fn ($w) => $w->where('creator_identity', 'like', $like)
                            ->orWhere('materials', 'like', $like)
                            ->orWhere('techniques', 'like', $like)
                            ->orWhere('classification', 'like', $like)
                            ->orWhere('inscription', 'like', $like))->get())) {
                        return self::$textMatchIdCache[$cacheKey] = null;
                    }
                }
                if (in_array('gallery_artist', $sectorTables)) {
                    if ($collect(DB::table('event')
                        ->join('gallery_artist', 'gallery_artist.actor_id', '=', 'event.actor_id')
                        ->select('event.object_id as id')
                        ->where(fn ($w) => $w->where('gallery_artist.display_name', 'like', $like)
                            ->orWhere('gallery_artist.medium_specialty', 'like', $like)
                            ->orWhere('gallery_artist.movement_style', 'like', $like))->get())) {
                        return self::$textMatchIdCache[$cacheKey] = null;
                    }
                }
                if (in_array('library_item', $sectorTables)) {
                    if ($collect(DB::table('library_item')->select('information_object_id as id')
                        ->where(fn ($w) => $w->where('isbn', 'like', $like)
                            ->orWhere('call_number', 'like', $like)
                            ->orWhere('series_title', 'like', $like)
                            ->orWhere('summary', 'like', $like)
                            ->orWhere('contents_note', 'like', $like))->get())) {
                        return self::$textMatchIdCache[$cacheKey] = null;
                    }
                }
                if (in_array('library_item_creator', $sectorTables)) {
                    if ($collect(DB::table('library_item_creator')
                        ->join('library_item', 'library_item.id', '=', 'library_item_creator.library_item_id')
                        ->select('library_item.information_object_id as id')
                        ->where('library_item_creator.name', 'like', $like)->get())) {
                        return self::$textMatchIdCache[$cacheKey] = null;
                    }
                }
                // Authority records - unconditional, matching the clause builder.
                if ($collect(DB::table('event')
                    ->join('actor_i18n', 'actor_i18n.id', '=', 'event.actor_id')
                    ->select('event.object_id as id')
                    ->where('actor_i18n.authorized_form_of_name', 'like', $like)->get())) {
                    return self::$textMatchIdCache[$cacheKey] = null;
                }
            }
        } catch (\Exception $e) {
            // Any hiccup (missing column on an older schema, transient re-prepare)
            // falls back to the correlated path rather than silently under-matching.
            return self::$textMatchIdCache[$cacheKey] = null;
        }

        return self::$textMatchIdCache[$cacheKey] = array_map('intval', array_keys($ids));
    }

    /**
     * Apply text search filter with FULLTEXT (if available) or LIKE fallback.
     * Supports both single-term and semantic (multi-term OR) search.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array|string $searchTerms Search terms (array for semantic, string for normal)
     */
    protected function applyTextSearchFilter($query, $searchTerms): void
    {
        $useFulltext = $this->isFulltextAvailable();
        $sectorTables = $this->getSectorSearchTables();

        // Pre-resolved id set: same matches, but the ~9 correlated subqueries
        // run once as flat scans instead of once per row per facet. Only for
        // the LIKE path - FULLTEXT relevance is left exactly as it was.
        if (! $useFulltext) {
            $matchIds = $this->resolveTextMatchIds($searchTerms);
            if ($matchIds !== null) {
                // An empty set is a real answer - it must exclude everything.
                $query->whereIn('io.id', $matchIds);

                return;
            }
        }

        // Sanitize FULLTEXT-bound terms: strip boolean-mode operators and wildcards
        // that MySQL's NATURAL LANGUAGE parser rejects (e.g. bare '*').
        $ftSanitize = static function (string $s): string {
            return trim(preg_replace('/[\+\-><\(\)~\*"@]+/', ' ', $s));
        };

        // #111: escape LIKE wildcards (% _) when escape_queries is on so a
        // pasted value like "100% complete" matches literally instead of
        // collapsing into a match-all-after-100 wildcard. When the operator
        // turns escape_queries off, the helper passes the raw term through
        // and power users can wildcard-search via LIKE.
        $likeEscape = static fn (string $s): string => \AhgCore\Support\EscapeQueriesHelper::escapeForLike($s);

        if (is_array($searchTerms)) {
            // Semantic search: OR between all terms
            $query->where(function ($qb) use ($searchTerms, $useFulltext, $sectorTables, $ftSanitize, $likeEscape) {
                foreach ($searchTerms as $term) {
                    $q = '%' . $likeEscape((string) $term) . '%';
                    $ftTerm = $ftSanitize((string) $term);
                    $qb->orWhere(function ($inner) use ($q, $term, $ftTerm, $useFulltext, $sectorTables) {
                        if ($useFulltext && $ftTerm !== '') {
                            $inner->whereExists(function ($sub) use ($ftTerm, $q) {
                                $sub->select(DB::raw(1))
                                    ->from('information_object_i18n as ioi')
                                    ->whereRaw('ioi.id = io.id')
                                    ->where(function ($w) use ($ftTerm, $q) {
                                        $w->whereRaw('MATCH(ioi.title) AGAINST(? IN NATURAL LANGUAGE MODE)', [$ftTerm])
                                            ->orWhereRaw('MATCH(ioi.scope_and_content) AGAINST(? IN NATURAL LANGUAGE MODE)', [$ftTerm])
                                            ->orWhere('ioi.title', 'like', $q)
                                            ->orWhere('ioi.scope_and_content', 'like', $q);
                                    });
                            })->orWhere('io.identifier', 'like', $q);
                        } else {
                            $inner->whereExists(function ($sub) use ($q) {
                                $sub->select(DB::raw(1))
                                    ->from('information_object_i18n as ioi')
                                    ->whereRaw('ioi.id = io.id')
                                    ->where(function ($w) use ($q) {
                                        $w->where('ioi.title', 'like', $q)
                                            ->orWhere('ioi.scope_and_content', 'like', $q);
                                    });
                            })->orWhere('io.identifier', 'like', $q);
                        }
                        $this->applySectorSearchClauses($inner, $q, $sectorTables);
                    });
                }
            });
        } else {
            // Normal search: single term
            $q = '%' . $likeEscape((string) $searchTerms) . '%';
            $ftTerm = $ftSanitize((string) $searchTerms);
            if ($useFulltext && $ftTerm !== '') {
                $query->where(function ($qb) use ($q, $ftTerm, $sectorTables) {
                    $qb->whereExists(function ($sub) use ($ftTerm, $q) {
                        $sub->select(DB::raw(1))
                            ->from('information_object_i18n as ioi')
                            ->whereRaw('ioi.id = io.id')
                            ->where(function ($w) use ($ftTerm, $q) {
                                $w->whereRaw('MATCH(ioi.title) AGAINST(? IN NATURAL LANGUAGE MODE)', [$ftTerm])
                                    ->orWhereRaw('MATCH(ioi.scope_and_content) AGAINST(? IN NATURAL LANGUAGE MODE)', [$ftTerm])
                                    ->orWhere('ioi.title', 'like', $q)
                                    ->orWhere('ioi.scope_and_content', 'like', $q);
                            });
                    })->orWhere('io.identifier', 'like', $q);
                    $this->applySectorSearchClauses($qb, $q, $sectorTables);
                });
            } else {
                $query->where(function ($qb) use ($q, $sectorTables) {
                    $qb->whereExists(function ($sub) use ($q) {
                        $sub->select(DB::raw(1))
                            ->from('information_object_i18n as ioi')
                            ->whereRaw('ioi.id = io.id')
                            ->where(function ($w) use ($q) {
                                $w->where('ioi.title', 'like', $q)
                                    ->orWhere('ioi.scope_and_content', 'like', $q);
                            });
                    })->orWhere('io.identifier', 'like', $q);
                    $this->applySectorSearchClauses($qb, $q, $sectorTables);
                });
            }
        }
    }

    /**
     * Get list of sector-specific tables that exist in the database.
     * Cached per request.
     */
    protected function getSectorSearchTables(): array
    {
        if (self::$sectorSearchTables !== null) {
            return self::$sectorSearchTables;
        }

        self::$sectorSearchTables = [];
        $candidates = ['dam_iptc_metadata', 'museum_metadata', 'gallery_artist', 'library_item', 'library_item_creator'];

        foreach ($candidates as $table) {
            try {
                DB::select("SELECT 1 FROM `{$table}` LIMIT 1");
                self::$sectorSearchTables[] = $table;
            } catch (\Exception $e) {
                // Table doesn't exist - skip
            }
        }

        return self::$sectorSearchTables;
    }

    /**
     * Add sector-specific search clauses (DAM, Museum, Gallery).
     * Only adds clauses for sector tables that exist in the database.
     */
    protected function applySectorSearchClauses($qb, string $likePattern, array $sectorTables): void
    {
        // DAM: search creator, headline, caption, keywords
        if (in_array('dam_iptc_metadata', $sectorTables)) {
            $qb->orWhereExists(function ($sub) use ($likePattern) {
                $sub->select(DB::raw(1))
                    ->from('dam_iptc_metadata as dim')
                    ->whereRaw('dim.object_id = io.id')
                    ->where(function ($w) use ($likePattern) {
                        $w->where('dim.creator', 'like', $likePattern)
                            ->orWhere('dim.headline', 'like', $likePattern)
                            ->orWhere('dim.caption', 'like', $likePattern)
                            ->orWhere('dim.keywords', 'like', $likePattern);
                    });
            });
        }

        // Museum: search creator_identity, materials, techniques, classification, inscription
        if (in_array('museum_metadata', $sectorTables)) {
            $qb->orWhereExists(function ($sub) use ($likePattern) {
                $sub->select(DB::raw(1))
                    ->from('museum_metadata as mm')
                    ->whereRaw('mm.object_id = io.id')
                    ->where(function ($w) use ($likePattern) {
                        $w->where('mm.creator_identity', 'like', $likePattern)
                            ->orWhere('mm.materials', 'like', $likePattern)
                            ->orWhere('mm.techniques', 'like', $likePattern)
                            ->orWhere('mm.classification', 'like', $likePattern)
                            ->orWhere('mm.inscription', 'like', $likePattern);
                    });
            });
        }

        // Gallery: search artist display_name, medium_specialty, movement_style via event->actor
        if (in_array('gallery_artist', $sectorTables)) {
            $qb->orWhereExists(function ($sub) use ($likePattern) {
                $sub->select(DB::raw(1))
                    ->from('event as ev_ga')
                    ->join('gallery_artist as ga', 'ga.actor_id', '=', 'ev_ga.actor_id')
                    ->whereRaw('ev_ga.object_id = io.id')
                    ->where(function ($w) use ($likePattern) {
                        $w->where('ga.display_name', 'like', $likePattern)
                            ->orWhere('ga.medium_specialty', 'like', $likePattern)
                            ->orWhere('ga.movement_style', 'like', $likePattern);
                    });
            });
        }

        // Library: search ISBN, call_number, series_title, summary, contents_note
        // on the per-IO library_item row.
        if (in_array('library_item', $sectorTables)) {
            $qb->orWhereExists(function ($sub) use ($likePattern) {
                $sub->select(DB::raw(1))
                    ->from('library_item as li')
                    ->whereRaw('li.information_object_id = io.id')
                    ->where(function ($w) use ($likePattern) {
                        $w->where('li.isbn', 'like', $likePattern)
                            ->orWhere('li.call_number', 'like', $likePattern)
                            ->orWhere('li.series_title', 'like', $likePattern)
                            ->orWhere('li.summary', 'like', $likePattern)
                            ->orWhere('li.contents_note', 'like', $likePattern);
                    });
            });
        }

        // Library creators: search the raw author name (covers rows where
        // resolveOrCreateActor has not run yet so actor_id is still NULL).
        if (in_array('library_item_creator', $sectorTables)) {
            $qb->orWhereExists(function ($sub) use ($likePattern) {
                $sub->select(DB::raw(1))
                    ->from('library_item_creator as lic')
                    ->join('library_item as li2', 'li2.id', '=', 'lic.library_item_id')
                    ->whereRaw('li2.information_object_id = io.id')
                    ->where('lic.name', 'like', $likePattern);
            });
        }

        // Authority records: search actor_i18n.authorized_form_of_name for any
        // IO whose `event` row links to a matching actor. Catches author/creator
        // searches across all sectors (not just library), so "Nelson Mandela"
        // finds the autobiography even though no library_item.title contains it.
        $qb->orWhereExists(function ($sub) use ($likePattern) {
            $sub->select(DB::raw(1))
                ->from('event as ev_act')
                ->join('actor_i18n as ai_act', 'ai_act.id', '=', 'ev_act.actor_id')
                ->whereRaw('ev_act.object_id = io.id')
                ->where('ai_act.authorized_form_of_name', 'like', $likePattern);
        });
    }

    public function browseEmbedded(Request $request)
    {
        // Reuse browse logic to populate all variables
        $browseResponse = $this->browse($request);
        $viewData = $browseResponse->getData();
        $viewData['showSidebar'] = $request->input('showSidebar', '1') !== '0';

        return view('ahg-display::browse-embedded', $viewData);
    }

    public function reindex(Request $request) { return view('ahg-display::reindex'); }

    public function glamSearch(Request $request) { return view('ahg-display::search', ['rows' => collect()]); }

    public function treeviewPage(Request $request) { return view('ahg-display::treeview-page'); }
}
