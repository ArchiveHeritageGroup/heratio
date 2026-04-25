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
use AhgDisplay\Services\DisplayService;
use AhgDisplay\Services\DisplayTypeDetector;
use AhgDisplay\Repositories\UserBrowseSettingsRepository;

class DisplayController extends Controller
{
    protected DisplayService $service;
    protected UserBrowseSettingsRepository $settingsRepo;

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
        $culture = app()->getLocale();
        $this->isAuthenticated = auth()->check();

        // Get all filter parameters
        $this->typeFilter = $request->input('type');
        $this->parentId = $request->input('parent');
        $this->topLevelOnly = $request->input('topLevel', '0');
        $page = max(1, (int) $request->input('page', 1));

        // Read limit: display browse has its own 10-100 range control
        $limit = (int) $request->input('limit', SettingHelper::hitsPerPage());
        if ($limit < 10) $limit = 10;
        if ($limit > 100) $limit = 100;

        $sort = $request->input('sort', 'date');
        $sortDir = $request->input('sortDir', $request->input('dir', 'desc'));
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

        // Semantic expansion - skipped (ahgSemanticSearchPlugin not yet migrated)
        $this->queryFilterTerms = null;

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
        $this->startDateFilter = $request->input('startDate');
        $this->endDateFilter = $request->input('endDate');
        $this->rangeTypeFilter = $request->input('rangeType', 'inclusive');

        // Load facets: live counts scoped to current filters when filters active,
        // otherwise use cached counts for performance
        $hasFilters = $this->typeFilter || $this->parentId || $this->creatorFilter
            || $this->subjectFilter || $this->placeFilter || $this->genreFilter
            || $this->levelFilter || $this->mediaFilter || $this->repoFilter
            || $this->queryFilter || $this->hasDigital;

        if ($hasFilters) {
            $types = $this->getLiveFacet('type');
            $levels = $this->getLiveFacet('level');
            $repositories = $this->getLiveFacet('repository');
            $creators = $this->getLiveFacet('creator');
            $subjects = $this->getLiveFacet('subject');
            $places = $this->getLiveFacet('place');
            $genres = $this->getLiveFacet('genre');
            $mediaTypes = $this->getLiveFacet('media_type');
        } else {
            $sfx = $this->isAuthenticated ? '_all' : '';
            $types = $this->getCachedFacet('glam_type' . $sfx, 'object_type');
            $levels = $this->getCachedFacet('level' . $sfx);
            $repositories = $this->getCachedFacet('repository' . $sfx);
            $creators = $this->getCachedFacet('creator' . $sfx);
            $subjects = $this->getCachedFacet('subject' . $sfx);
            $places = $this->getCachedFacet('place' . $sfx);
            $genres = $this->getCachedFacet('genre' . $sfx);
            $mediaTypes = $this->getCachedFacet('media_type' . $sfx, 'media_type');
        }

        // Discovery integration - skipped (ahgDiscoveryPlugin not yet migrated)
        $discoveryMode = false;
        $discoveryExpanded = null;
        $discoveryMeta = [];
        $esIds = null;

        // Classic path: SQL count
        $countQuery = DB::table('information_object as io')
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->where('io.id', '>', 1);

        $this->applyFilters($countQuery);
        $total = $countQuery->count();

        // ES fuzzy fallback - skipped (Elasticsearch not yet migrated)
        // Placeholder for future: if $total === 0 && $this->queryFilter, try ES fuzzy

        $totalPages = (int) ceil($total / $limit);

        // Build main query
        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'level.id')->where('level.culture', '=', $culture);
            })
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', '>', 1)
            ->select(
                'io.id', 'io.identifier', 'io.parent_id',
                'i18n.title', 'i18n.scope_and_content',
                'level.name as level_name',
                'doc.object_type', 'slug.slug'
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
        switch ($sort) {
            case 'identifier':
            case 'refcode':
                $query->orderBy('io.identifier', $safeSortDir);
                break;
            case 'date':
                $query->orderBy('io.id', $safeSortDir);
                break;
            case 'relevance':
                if ($this->queryFilter) {
                    $query->orderByRaw("CASE WHEN i18n.title LIKE ? THEN 0 WHEN i18n.title LIKE ? THEN 1 ELSE 2 END ASC", [
                        $this->queryFilter,
                        '%' . $this->queryFilter . '%',
                    ]);
                }
                $query->orderBy('io.id', 'desc');
                break;
            case 'startdate':
                $query->leftJoin('event as evt_sort', 'io.id', '=', 'evt_sort.object_id');
                $query->orderByRaw("MIN(evt_sort.start_date) $safeSortDir");
                $query->groupBy('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'level.name', 'doc.object_type', 'slug.slug');
                break;
            case 'enddate':
                $query->leftJoin('event as evt_sort', 'io.id', '=', 'evt_sort.object_id');
                $query->orderByRaw("MAX(evt_sort.end_date) $safeSortDir");
                $query->groupBy('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'level.name', 'doc.object_type', 'slug.slug');
                break;
            default:
                $query->orderBy('i18n.title', $safeSortDir);
        }

        // Paginate
        $objects = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->toArray();

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

                if ($thumb && $thumb->path && $thumb->name) {
                    $obj->thumbnail = rtrim($thumb->path, '/') . '/' . $thumb->name;
                }
                if ($ref && $ref->path && $ref->name) {
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
        $queryFilter = $this->queryFilter;

        return view('ahg-display::display.browse', compact(
            'objects', 'total', 'totalPages', 'page', 'limit', 'sort', 'sortDir',
            'viewMode', 'typeFilter', 'parentId', 'topLevelOnly', 'parent', 'breadcrumb',
            'digitalObjectCount', 'types', 'creators', 'subjects', 'places', 'genres',
            'levels', 'mediaTypes', 'repositories', 'hasDigital', 'creatorFilter',
            'subjectFilter', 'placeFilter', 'genreFilter', 'levelFilter', 'mediaFilter',
            'repoFilter', 'queryFilter', 'filterParams', 'discoveryMode',
            'discoveryExpanded', 'discoveryMeta', 'correctedQuery', 'didYouMean',
            'originalQuery', 'esAssistedSearch', 'brokenItems'
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

        $typeFilter = $request->input('type');
        $parentId = $request->input('parent');
        $topLevelOnly = $request->input('topLevel', '0');
        $sort = $request->input('sort', 'date');
        $sortDir = $request->input('sortDir', $request->input('dir', 'desc'));

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'level.id')->where('level.culture', '=', $culture);
            })
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', '>', 1)
            ->select('io.id', 'io.identifier', 'i18n.title', 'i18n.scope_and_content', 'level.name as level_name', 'doc.object_type', 'slug.slug');

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
                $query->orderBy('io.id', $safeSortDir);
                break;
            case 'startdate':
                $query->leftJoin('event as evt_sort', 'io.id', '=', 'evt_sort.object_id');
                $query->orderByRaw("MIN(evt_sort.start_date) $safeSortDir");
                $query->groupBy('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'level.name', 'doc.object_type', 'slug.slug');
                break;
            case 'enddate':
                $query->leftJoin('event as evt_sort', 'io.id', '=', 'evt_sort.object_id');
                $query->orderByRaw("MAX(evt_sort.end_date) $safeSortDir");
                $query->groupBy('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'level.name', 'doc.object_type', 'slug.slug');
                break;
            default:
                $query->orderBy('i18n.title', $safeSortDir);
        }

        $objects = $query->limit(500)->get()->toArray();
        $total = count($objects);

        return view('ahg-display::display.print', compact(
            'objects', 'total', 'typeFilter', 'parentId', 'topLevelOnly', 'parent', 'sort', 'sortDir'
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
        $topLevelOnly = $request->input('topLevel', '0');
        $sort = $request->input('sort', 'date');
        $sortDir = $request->input('sortDir', $request->input('dir', 'desc'));

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
            'startdate' => 'io.id',
            'enddate' => 'io.id',
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

        $this->service->setObjectType($objectId, $type);

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

        $fieldGroups = ['identity', 'description', 'context', 'access', 'technical', 'admin'];

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

        if ($this->parentId) {
            // Use MPTT lft/rgt range to include the record itself and all descendants
            $ancestor = DB::table('information_object')->where('id', $this->parentId)->select('lft', 'rgt')->first();
            if ($ancestor) {
                $query->where('io.lft', '>=', $ancestor->lft)
                      ->where('io.rgt', '<=', $ancestor->rgt);
            } else {
                $query->where('io.parent_id', $this->parentId);
            }
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

        if ($this->subjectFilter) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('object_term_relation')
                    ->whereRaw('object_term_relation.object_id = io.id')
                    ->where('object_term_relation.term_id', $this->subjectFilter);
            });
        }

        if ($this->placeFilter) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('object_term_relation')
                    ->whereRaw('object_term_relation.object_id = io.id')
                    ->where('object_term_relation.term_id', $this->placeFilter);
            });
        }

        if ($this->genreFilter) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('object_term_relation')
                    ->whereRaw('object_term_relation.object_id = io.id')
                    ->where('object_term_relation.term_id', $this->genreFilter);
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
        $culture = app()->getLocale();

        $query = DB::table('information_object as io')
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->where('io.id', '>', 1);

        $this->applyFilters($query);

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
                $query->join('object_term_relation as otr_s', 'otr_s.object_id', '=', 'io.id')
                    ->join('term as ts', function ($j) { $j->on('otr_s.term_id', '=', 'ts.id')->where('ts.taxonomy_id', '=', 35); })
                    ->join('term_i18n as tis', function ($j) use ($culture) { $j->on('ts.id', '=', 'tis.id')->where('tis.culture', '=', $culture); })
                    ->select('ts.id as facet_id', 'tis.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('ts.id', 'tis.name');
                break;

            case 'place':
                $query->join('object_term_relation as otr_p', 'otr_p.object_id', '=', 'io.id')
                    ->join('term as tp', function ($j) { $j->on('otr_p.term_id', '=', 'tp.id')->where('tp.taxonomy_id', '=', 42); })
                    ->join('term_i18n as tip', function ($j) use ($culture) { $j->on('tp.id', '=', 'tip.id')->where('tip.culture', '=', $culture); })
                    ->select('tp.id as facet_id', 'tip.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('tp.id', 'tip.name');
                break;

            case 'genre':
                $query->join('object_term_relation as otr_g', 'otr_g.object_id', '=', 'io.id')
                    ->join('term as tg', function ($j) { $j->on('otr_g.term_id', '=', 'tg.id')->where('tg.taxonomy_id', '=', 78); })
                    ->join('term_i18n as tig', function ($j) use ($culture) { $j->on('tg.id', '=', 'tig.id')->where('tig.culture', '=', $culture); })
                    ->select('tg.id as facet_id', 'tig.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('tg.id', 'tig.name');
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

        // Sanitize FULLTEXT-bound terms: strip boolean-mode operators and wildcards
        // that MySQL's NATURAL LANGUAGE parser rejects (e.g. bare '*').
        $ftSanitize = static function (string $s): string {
            return trim(preg_replace('/[\+\-><\(\)~\*"@]+/', ' ', $s));
        };

        if (is_array($searchTerms)) {
            // Semantic search: OR between all terms
            $query->where(function ($qb) use ($searchTerms, $useFulltext, $sectorTables, $ftSanitize) {
                foreach ($searchTerms as $term) {
                    $q = '%' . $term . '%';
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
            $q = '%' . $searchTerms . '%';
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
        $candidates = ['dam_iptc_metadata', 'museum_metadata', 'gallery_artist'];

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
