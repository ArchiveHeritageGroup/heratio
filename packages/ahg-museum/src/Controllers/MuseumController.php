<?php

/**
 * MuseumController - Controller for Heratio
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



namespace AhgMuseum\Controllers;

use AhgCore\Support\CcoFields;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\SettingHelper;
use AhgMuseum\Services\MuseumService;
use AhgRic\Crm\CrmSerializer;
use AhgRic\Crm\RicToCrmMapper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MuseumController extends Controller
{
    protected MuseumService $service;

    public function __construct()
    {
        $this->service = new MuseumService(app()->getLocale());
    }

    /**
     * Browse museum objects with filtering and pagination.
     */
    public function browse(Request $request)
    {
        $culture = app()->getLocale();

        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
        ];

        // Single-value facets pulled from museum_metadata. Empty values are
        // skipped so the URL stays clean when "All …" is selected.
        $facetKeys = [
            'work_type', 'classification', 'materials', 'techniques',
            'period', 'style', 'school', 'dynasty',
            'cultural_context', 'creator_identity',
        ];
        $selected = [];
        foreach ($facetKeys as $k) {
            $v = trim((string) $request->get($k, ''));
            if ($v !== '') {
                $params['filters'][$k] = $v;
                $selected[$k] = $v;
            }
        }

        // Creation date range (creation_date_earliest / creation_date_latest).
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo   = trim((string) $request->get('date_to', ''));
        if ($dateFrom !== '') $params['filters']['date_from'] = $dateFrom;
        if ($dateTo   !== '') $params['filters']['date_to']   = $dateTo;

        // Identifier search across accession_number / barcode / object_number
        // (museum_object table). Distinct from the keyword $subquery so users
        // can paste a barcode and not get false positives from a similar word
        // appearing in a description.
        $idSearch = trim((string) $request->get('id_search', ''));
        if ($idSearch !== '') $params['filters']['id_search'] = $idSearch;

        $result = $this->service->browse($params);
        $pager = new SimplePager($result);

        return view('ahg-museum::museum.browse', [
            'pager' => $pager,
            // Distinct-values lists for each facet dropdown
            'workTypes'         => $result['workTypes']         ?? [],
            'classifications'   => $result['classifications']   ?? [],
            'materialsList'     => $result['materials']         ?? [],
            'techniquesList'    => $result['techniques']        ?? [],
            'periods'           => $result['periods']           ?? [],
            'styles'            => $result['styles']            ?? [],
            'schools'           => $result['schools']           ?? [],
            'dynasties'         => $result['dynasties']         ?? [],
            'culturalContexts'  => $result['cultural_contexts'] ?? [],
            'creators'          => $result['creators']          ?? [],
            // Currently-applied selections (drive the dropdown labels)
            'selectedWorkType'         => $selected['work_type']         ?? null,
            'selectedClassification'   => $selected['classification']    ?? null,
            'selectedMaterials'        => $selected['materials']         ?? null,
            'selectedTechniques'       => $selected['techniques']        ?? null,
            'selectedPeriod'           => $selected['period']            ?? null,
            'selectedStyle'            => $selected['style']             ?? null,
            'selectedSchool'           => $selected['school']            ?? null,
            'selectedDynasty'          => $selected['dynasty']           ?? null,
            'selectedCulturalContext'  => $selected['cultural_context']  ?? null,
            'selectedCreator'          => $selected['creator_identity']  ?? null,
            'selectedDateFrom'         => $dateFrom,
            'selectedDateTo'           => $dateTo,
            'selectedIdSearch'         => $idSearch,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'lastUpdated' => 'Date modified',
                'identifier' => 'Identifier',
                'workType' => 'Work type',
                'creator' => 'Creator',
            ],
        ]);
    }

    /**
     * Show a museum object.
     */
    public function show(string $slug)
    {
        $culture = app()->getLocale();
        $museum = $this->service->getBySlug($slug);

        if (!$museum) {
            abort(404);
        }

        // Draft records must never leak to anonymous visitors (guests see
        // published only; the ACL 'read' gate is a no-op for anonymous).
        if (\Illuminate\Support\Facades\Auth::guest()
            && ! app(\AhgCore\Services\MultilingualRecordService::class)->isPublished((int) $museum->id)) {
            abort(404);
        }

        // #1464: community-protocol gate. A record carrying a restricting
        // TK/BC label was already withheld from browse, exports and OAI but
        // still served on its direct URL. 404 like the draft gate above -
        // a restricted record is not confirmed to exist. Staff bypass is
        // handled inside allowsRecord().
        if (! \AhgCore\Services\TermProtocolGate::allowsRecord((int) $museum->id)) {
            abort(404);
        }

        // Digital objects
        $digitalObjects = DigitalObjectService::getForObject($museum->id);

        // Repository
        $repository = null;
        if ($museum->repository_id) {
            $repository = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->where('repository.id', $museum->repository_id)
                ->where('actor_i18n.culture', $culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
                ->first();
        }

        // Level of description name
        $levelName = null;
        if ($museum->level_of_description_id) {
            $levelName = DB::table('term_i18n')
                ->where('id', $museum->level_of_description_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Subject access points (taxonomy_id = 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $museum->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Place access points (taxonomy_id = 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $museum->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();

        // Publication status
        $publicationStatus = null;
        $publicationStatusId = null;
        $statusRow = DB::table('status')
            ->where('object_id', $museum->id)
            ->where('type_id', 158)
            ->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatusId = (int) $statusRow->status_id;
            $publicationStatus = DB::table('term_i18n')
                ->where('id', $statusRow->status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Parent breadcrumb chain
        $breadcrumbs = [];
        $parentId = $museum->parent_id;
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

        // Child image carousel (imageflow) - thumbnails from the WHOLE descendant
        // subtree via the closure, so a museum collection whose images live below
        // the first level still shows the carousel. Feeds the shared
        // _digital-object-viewer partial (its strip is gated on $childThumbnails).
        // Mirrors the archival + DAM show fix.
        $childThumbnails = collect();
        $carouselIds = [];
        if (class_exists(\AhgCore\Services\HierarchyQueryService::class)) {
            $carouselIds = app(\AhgCore\Services\HierarchyQueryService::class)
                ->descendantIds('information_object', (int) $museum->id, false);
        }
        if (count($carouselIds) > 20000) {
            $carouselIds = array_slice($carouselIds, 0, 20000);
        }
        if (!empty($carouselIds)) {
            $childThumbnails = DB::table('digital_object')
                ->join('slug', 'digital_object.object_id', '=', 'slug.object_id')
                ->join('information_object_i18n', function ($join) use ($culture) {
                    $join->on('digital_object.object_id', '=', 'information_object_i18n.id')
                         ->where('information_object_i18n.culture', '=', $culture);
                })
                ->whereIn('digital_object.object_id', $carouselIds)
                ->where('digital_object.usage_id', 142)
                ->select(
                    'digital_object.id', 'digital_object.object_id', 'digital_object.name',
                    'digital_object.path', 'digital_object.mime_type', 'digital_object.byte_size',
                    'slug.slug', 'information_object_i18n.title'
                )
                ->orderBy('digital_object.object_id')
                ->limit(10)
                ->get();
        }
        $childThumbnailTotal = !empty($carouselIds) ? DB::table('digital_object')
            ->whereIn('digital_object.object_id', $carouselIds)
            ->where('digital_object.usage_id', 142)
            ->count() : 0;

        return view('ahg-museum::museum.show', [
            'museum' => $museum,
            'digitalObjects' => $digitalObjects,
            'repository' => $repository,
            'childThumbnails' => $childThumbnails,
            'childThumbnailTotal' => $childThumbnailTotal,
            'levelName' => $levelName,
            'subjects' => $subjects,
            'places' => $places,
            'publicationStatus' => $publicationStatus,
            'publicationStatusId' => $publicationStatusId,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $culture = app()->getLocale();
        $formChoices = $this->service->getFormChoices($culture);
        $editExtras = $this->service->getEditExtras(null, $culture);

        return view('ahg-museum::museum.edit', array_merge(
            ['museum' => null, 'isNew' => true],
            $formChoices,
            $editExtras
        ));
    }

    /**
     * Store a new museum object.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:1024',
            'work_type' => 'nullable|string|max:50',
            'identifier' => 'nullable|string|max:1024',
            'creation_date_earliest' => 'nullable|date',
            'creation_date_latest' => 'nullable|date',
            'condition_date' => 'nullable|date',
            'treatment_date' => 'nullable|date',
            'cataloging_date' => 'nullable|date',
        ] + CcoFields::rules());

        $slug = $this->service->create($request->all());

        return redirect()
            ->route('museum.show', $slug)
            ->with('success', 'Museum object created successfully.');
    }

    /**
     * Show edit form.
     */
    public function edit(string $slug)
    {
        $culture = app()->getLocale();
        $museum = $this->service->getBySlug($slug);

        if (!$museum) {
            abort(404);
        }

        $formChoices = $this->service->getFormChoices($culture);
        $editExtras = $this->service->getEditExtras($museum->id ?? null, $culture);

        return view('ahg-museum::museum.edit', array_merge(
            ['museum' => $museum, 'isNew' => false],
            $formChoices,
            $editExtras
        ));
    }

    /**
     * Update an existing museum object.
     */
    public function update(Request $request, string $slug)
    {
        $request->validate([
            'title' => 'required|string|max:1024',
            'work_type' => 'nullable|string|max:50',
            'identifier' => 'nullable|string|max:1024',
            'creation_date_earliest' => 'nullable|date',
            'creation_date_latest' => 'nullable|date',
            'condition_date' => 'nullable|date',
            'treatment_date' => 'nullable|date',
            'cataloging_date' => 'nullable|date',
        ] + CcoFields::rules());

        $this->service->update($slug, $request->all());

        return redirect()
            ->route('museum.show', $slug)
            ->with('success', 'Museum object updated successfully.');
    }

    /**
     * Delete a museum object.
     */
    public function destroy(string $slug)
    {
        $this->service->delete($slug);

        return redirect()
            ->route('museum.browse')
            ->with('success', 'Museum object deleted successfully.');
    }

    // ── Dashboard & Reports ────────────────────────────────────────

    public function dashboard()
    {
        $totalItems = 0;
        $itemsWithMedia = 0;
        $itemsWithCondition = 0;
        $itemsWithProvenance = 0;
        $workTypeStats = collect();
        $recentItems = collect();

        try {
            if (\Schema::hasTable('museum_metadata')) {
                $totalItems = DB::table('museum_metadata')->count();

                $itemsWithMedia = DB::table('museum_metadata as mm')
                    ->join('digital_object as do', 'mm.object_id', '=', 'do.object_id')
                    ->distinct()->count('mm.object_id');

                $itemsWithCondition = DB::table('museum_metadata')
                    ->where(function ($q) {
                        $q->whereNotNull('condition_term')->where('condition_term', '!=', '');
                    })
                    ->orWhere(function ($q) {
                        $q->whereNotNull('condition_notes')->where('condition_notes', '!=', '');
                    })
                    ->count();

                $itemsWithProvenance = DB::table('museum_metadata')
                    ->where(function ($q) {
                        $q->whereNotNull('provenance')->where('provenance', '!=', '')
                          ->orWhere(function ($q2) {
                              $q2->whereNotNull('provenance_text')->where('provenance_text', '!=', '');
                          });
                    })
                    ->count();

                $workTypeStats = DB::table('museum_metadata')
                    ->whereNotNull('work_type')->where('work_type', '!=', '')
                    ->select('work_type', DB::raw('COUNT(*) as count'))
                    ->groupBy('work_type')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get();

                $recentItems = DB::table('museum_metadata as mm')
                    ->join('information_object as io', 'mm.object_id', '=', 'io.id')
                    ->leftJoin('information_object_i18n as i18n', function ($j) {
                        $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                    })
                    ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                    ->leftJoin('digital_object as do', 'io.id', '=', 'do.object_id')
                    ->select(
                        'io.id',
                        'io.identifier',
                        'i18n.title',
                        'slug.slug',
                        'do.id as digital_object_id'
                    )
                    ->orderByDesc('io.created_at')
                    ->limit(10)
                    ->get();
            }
        } catch (\Throwable $e) {
            // graceful degrade
        }

        return view('ahg-museum::museum.dashboard', compact(
            'totalItems',
            'itemsWithMedia',
            'itemsWithCondition',
            'itemsWithProvenance',
            'workTypeStats',
            'recentItems'
        ));
    }

    public function reports()
    {
        $stats = [
            'totalObjects' => 0, 'withProvenance' => 0,
            'byCondition' => collect(), 'byWorkType' => collect(),
        ];
        try {
            if (\Schema::hasTable('museum_metadata')) {
                $stats['totalObjects'] = DB::table('museum_metadata')->count();
                $stats['withProvenance'] = DB::table('museum_metadata')->whereNotNull('provenance_text')->where('provenance_text', '!=', '')->count();
                $stats['byCondition'] = DB::table('museum_metadata')->whereNotNull('condition_term')
                    ->select('condition_term', DB::raw('COUNT(*) as count'))->groupBy('condition_term')->orderByDesc('count')->get();
                $stats['byWorkType'] = DB::table('museum_metadata')->whereNotNull('work_type')
                    ->select('work_type', DB::raw('COUNT(*) as count'))->groupBy('work_type')->orderByDesc('count')->limit(10)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-museum::reports.index', compact('stats'));
    }

    public function reportObjects()
    {
        $objects = collect();
        try {
            if (\Schema::hasTable('museum_metadata')) {
                $objects = DB::table('museum_metadata as mm')
                    ->leftJoin('information_object_i18n as io_i18n', function ($j) { $j->on('mm.information_object_id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', 'en'); })
                    ->select('mm.*', 'io_i18n.title')
                    ->orderByDesc('mm.created_at')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-museum::reports.objects', compact('objects'));
    }

    public function reportCreators()
    {
        $creators = collect();
        try {
            if (\Schema::hasTable('museum_metadata')) {
                $creators = DB::table('museum_metadata')
                    ->whereNotNull('creator_name')->where('creator_name', '!=', '')
                    ->select('creator_name', 'creator_role', 'attribution', 'school', DB::raw('COUNT(*) as object_count'))
                    ->groupBy('creator_name', 'creator_role', 'attribution', 'school')
                    ->orderByDesc('object_count')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-museum::reports.creators', compact('creators'));
    }

    public function reportCondition()
    {
        $records = collect();
        try {
            if (\Schema::hasTable('museum_metadata')) {
                $records = DB::table('museum_metadata as mm')
                    ->leftJoin('information_object_i18n as io_i18n', function ($j) { $j->on('mm.information_object_id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', 'en'); })
                    ->whereNotNull('mm.condition_term')
                    ->select('mm.*', 'io_i18n.title')
                    ->orderByDesc('mm.condition_date')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-museum::reports.condition', compact('records'));
    }

    public function reportProvenance()
    {
        $records = collect();
        try {
            if (\Schema::hasTable('museum_metadata')) {
                $records = DB::table('museum_metadata as mm')
                    ->leftJoin('information_object_i18n as io_i18n', function ($j) { $j->on('mm.information_object_id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', 'en'); })
                    ->whereNotNull('mm.provenance_text')->where('mm.provenance_text', '!=', '')
                    ->select('mm.*', 'io_i18n.title')
                    ->orderBy('io_i18n.title')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-museum::reports.provenance', compact('records'));
    }

    public function reportStylePeriod()
    {
        $byStyle = collect(); $byPeriod = collect();
        try {
            if (\Schema::hasTable('museum_metadata')) {
                $byStyle = DB::table('museum_metadata')->whereNotNull('style')->where('style', '!=', '')
                    ->select('style', DB::raw('COUNT(*) as count'))->groupBy('style')->orderByDesc('count')->get();
                $byPeriod = DB::table('museum_metadata')->whereNotNull('period')->where('period', '!=', '')
                    ->select('period', DB::raw('COUNT(*) as count'))->groupBy('period')->orderByDesc('count')->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-museum::reports.style-period', compact('byStyle', 'byPeriod'));
    }

    public function reportMaterials()
    {
        $records = collect();
        try {
            if (\Schema::hasTable('museum_metadata')) {
                $records = DB::table('museum_metadata as mm')
                    ->leftJoin('information_object_i18n as io_i18n', function ($j) { $j->on('mm.information_object_id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', 'en'); })
                    ->where(function ($q) { $q->whereNotNull('mm.materials')->orWhereNotNull('mm.techniques'); })
                    ->select('mm.*', 'io_i18n.title')
                    ->orderBy('io_i18n.title')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-museum::reports.materials', compact('records'));
    }

    public function conditionReport(string $slug)
    {
        $museum = $this->service->getBySlug($slug);
        if (!$museum) abort(404);
        $resource = $museum;
        $currentCondition = null;
        $conditionReports = collect();
        return view('ahg-museum::museum.condition-report', compact('resource', 'currentCondition', 'conditionReports'));
    }

    public function gettyLinks(string $slug)
    {
        $statistics = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'suggested' => 0];
        $links = collect();
        return view('ahg-museum::museum.getty-links', compact('statistics', 'links'));
    }

    public function grapDashboard(string $slug)
    {
        $stats = ['total_assets' => 0, 'valued_assets' => 0, 'unvalued_assets' => 0, 'total_value' => 0];
        return view('ahg-museum::museum.grap-dashboard', compact('stats'));
    }

    public function loanDashboard(string $slug)
    {
        $stats = ['total_loans' => 0, 'active_loans_out' => 0, 'active_loans_in' => 0, 'overdue' => 0, 'due_this_month' => 0, 'total_insurance_value' => 0];
        $loans = collect();
        return view('ahg-museum::museum.loan-dashboard', compact('stats', 'loans'));
    }

    public function multiFileUpload(string $slug)
    {
        $museum = $this->service->getBySlug($slug);
        if (!$museum) abort(404);
        return view('ahg-museum::museum.multi-file-upload', ['resource' => $museum]);
    }

    public function multiUploadStore(Request $request, string $slug)
    {
        return redirect()->route('museum.show', $slug)->with('success', 'Files uploaded.');
    }

    /**
     * Provenance and custody history for a museum object.
     *
     * This used to be `$provenanceChain = collect();` - a hardcoded empty
     * collection, so the page rendered "No provenance data." for every object
     * forever, while `provenance_entry` held real chains against those very
     * objects (53 rows on dev at the time of writing, one object with 7).
     *
     * A museum record IS an information object - MuseumService::getBySlug()
     * queries `information_object` and filters on the presence of
     * museum_metadata - so its id is the information_object_id these tables key
     * on, and the data was always reachable.
     *
     * Reads through ahg-io-manage's ProvenanceService rather than requerying:
     * that service already owns the chain, overview, documents and timeline,
     * and a second implementation of one lookup is how the two drift apart.
     * Resolution is class_exists-guarded because ahg-museum has no declared
     * dependency on that package; where it is absent the page degrades to the
     * old empty state instead of 500ing.
     *
     * Gated with the same ACL check the information-object provenance page
     * uses: if you may READ the object, you may see its provenance. Provenance
     * carries acquisition prices, Nazi-era due-diligence findings and cultural
     * property status - it must not be looser than the record it describes.
     */
    public function provenance(string $slug)
    {
        $museum = $this->service->getBySlug($slug);
        if (!$museum) abort(404);

        abort_unless(
            \AhgCore\Services\AclService::hasPermission(
                \Illuminate\Support\Facades\Auth::id(),
                'read',
                (int) $museum->id
            ),
            403
        );

        $svc = class_exists(\AhgInformationObjectManage\Services\ProvenanceService::class)
            ? app(\AhgInformationObjectManage\Services\ProvenanceService::class)
            : null;

        $objectId = (int) $museum->id;

        return view('ahg-museum::museum.provenance', [
            'resource'        => $museum,
            'provenanceChain' => $svc ? $svc->getChain($objectId)     : collect(),
            'overview'        => $svc ? $svc->getOverview($objectId)  : null,
            'documents'       => $svc ? $svc->getDocuments($objectId) : collect(),
        ]);
    }

    /**
     * Side-by-side comparison of museum objects.
     *
     * Was `$objects = collect();` - a hardcoded empty collection, so the page
     * rendered an empty row for every request and no comparison was possible.
     *
     * Nothing in the codebase linked to this route, so the parameter contract
     * was undefined; it is now `?with=slug,slug`. The base object is always
     * first, and the view divides a 12-column grid by the object count, so the
     * total is capped at FOUR - beyond that each card is a 2-column sliver and
     * the comparison stops being readable.
     *
     * Every object is ACL-gated individually. Comparison must not become a way
     * to read a record you could not open on its own.
     */
    public function objectComparison(Request $request, string $slug)
    {
        $slugs = collect([$slug])
            ->merge(explode(',', (string) $request->query('with', '')))
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->take(4);

        $objects = $slugs->map(function (string $s) {
                $obj = $this->service->getBySlug($s);
                if (! $obj) {
                    return null;
                }

                if (! \AhgCore\Services\AclService::hasPermission(
                    \Illuminate\Support\Facades\Auth::id(), 'read', (int) $obj->id
                )) {
                    return null;
                }

                $meta = $this->service->getMuseumMetadata((int) $obj->id);

                // The view wants a single date string; the schema stores a range.
                $from = $meta['creation_date_earliest'] ?? null;
                $to   = $meta['creation_date_latest'] ?? null;
                $obj->creation_date_display = $meta['creation_date_display']
                    ?? (($from && $to && $from !== $to) ? $from . '-' . $to : ($from ?: $to));

                $obj->work_type        = $meta['work_type'] ?? ($obj->work_type ?? null);
                $obj->creator_identity = $meta['creator_identity'] ?? null;

                return $obj;
            })
            ->filter()
            ->values();

        if ($objects->isEmpty()) {
            abort(404);
        }

        return view('ahg-museum::museum.object-comparison', compact('objects'));
    }

    /** Core CCO fields the completeness score is measured against. */
    private const QUALITY_FIELDS = [
        'work_type'         => 'Work type',
        'classification'    => 'Classification',
        'materials'         => 'Materials',
        'techniques'        => 'Techniques',
        'measurements'      => 'Measurements',
        'creator_identity'  => 'Creator',
        'creation_place'    => 'Creation place',
        'cultural_context'  => 'Cultural context',
        'current_location'  => 'Current location',
    ];

    /**
     * Cataloguing-completeness dashboard for museum records.
     *
     * Was a hardcoded `$overallScore = 0; $analyzedRecords = 0;` with an 'N/A'
     * grade - so it reported a permanent zero regardless of the catalogue, which
     * is worse than showing nothing: it asserts a measurement that was never
     * taken.
     *
     * A field counts as present if it is populated on museum_metadata OR on any
     * museum_metadata_i18n row. The translatable fields live in both - the parent
     * carries the base value and i18n the translations - so counting only the
     * parent would under-report a record catalogued in another language, and a
     * completeness metric that penalises translation would be actively harmful.
     */
    public function qualityDashboard()
    {
        $total = (int) DB::table('museum_metadata')->count();

        $missingFieldCounts = [];
        $populatedCells     = 0;

        // museum_metadata_i18n is created by a migration and is NOT in
        // database/core/*.sql, which is what CI builds its test database from
        // (#1471) - so joining it unconditionally passes locally and throws
        // "Base table or view not found" in CI and on any instance that has not
        // run migrations. Guarded, the count degrades to the parent table:
        // slightly under-reported for records catalogued only in translation,
        // which is far better than a 500.
        $hasI18n = \Illuminate\Support\Facades\Schema::hasTable('museum_metadata_i18n');

        foreach (self::QUALITY_FIELDS as $column => $label) {
            $query = DB::table('museum_metadata as mm');

            if ($hasI18n) {
                $query->leftJoin('museum_metadata_i18n as mmi', 'mmi.id', '=', 'mm.id')
                    ->where(function ($q) use ($column) {
                        $q->where(function ($p) use ($column) {
                            $p->whereNotNull('mm.' . $column)->where('mm.' . $column, '<>', '');
                        })->orWhere(function ($p) use ($column) {
                            $p->whereNotNull('mmi.' . $column)->where('mmi.' . $column, '<>', '');
                        });
                    });
            } else {
                $query->whereNotNull('mm.' . $column)->where('mm.' . $column, '<>', '');
            }

            $present = (int) $query->distinct()->count('mm.id');

            $missingFieldCounts[$label] = max(0, $total - $present);
            $populatedCells            += $present;
        }

        $cells        = $total * count(self::QUALITY_FIELDS);
        $overallScore = $cells > 0 ? (int) round($populatedCells / $cells * 100) : 0;

        $overallGrade = match (true) {
            $total === 0        => ['grade' => 'N/A', 'label' => 'No museum records to assess'],
            $overallScore >= 90 => ['grade' => 'A', 'label' => 'Excellent'],
            $overallScore >= 75 => ['grade' => 'B', 'label' => 'Good'],
            $overallScore >= 60 => ['grade' => 'C', 'label' => 'Adequate'],
            $overallScore >= 40 => ['grade' => 'D', 'label' => 'Needs attention'],
            default             => ['grade' => 'E', 'label' => 'Substantially incomplete'],
        };

        arsort($missingFieldCounts);

        return view('ahg-museum::dashboard.index', [
            'overallScore'       => $overallScore,
            'analyzedRecords'    => $total,
            'overallGrade'       => $overallGrade,
            'missingFieldCounts' => $missingFieldCounts,
        ]);
    }

    public function missingField(string $field)
    {
        $fieldName = $field; $records = collect();
        return view('ahg-museum::dashboard.missing-field', compact('fieldName', 'records'));
    }

    /**
     * Map the export-form format id to the CrmSerializer format constant
     * plus the file extension and MIME type used on the download.
     */
    protected const CIDOC_FORMATS = [
        'rdf'    => ['label' => 'RDF/XML',  'extension' => 'rdf',    'serializer' => CrmSerializer::FORMAT_RDFXML, 'mime' => 'application/rdf+xml'],
        'turtle' => ['label' => 'Turtle',   'extension' => 'ttl',    'serializer' => CrmSerializer::FORMAT_TURTLE, 'mime' => 'text/turtle'],
        'jsonld' => ['label' => 'JSON-LD',  'extension' => 'jsonld', 'serializer' => CrmSerializer::FORMAT_JSONLD, 'mime' => 'application/ld+json'],
    ];

    public function cidocExport()
    {
        $formats = self::CIDOC_FORMATS;
        $includeLinkedData = false;
        return view('ahg-museum::cidoc.export', compact('formats', 'includeLinkedData'));
    }

    /**
     * Serialise museum object(s) to the requested CIDOC-CRM format and
     * return the document as a file download. When a `slug` (or
     * `object_id`) is supplied only that object is exported; otherwise
     * every museum object is merged into a single CRM graph.
     *
     * Museum objects are physical artefacts, so the central node is
     * typed crm:E22_Human-Made_Object (rico:Item) via the serializer's
     * record-class override, not the archival E73 default.
     */
    public function cidocExportDownload(Request $request)
    {
        $formatId = (string) $request->input('format', 'rdf');
        if (! isset(self::CIDOC_FORMATS[$formatId])) {
            $formatId = 'rdf';
        }
        $fmt = self::CIDOC_FORMATS[$formatId];
        $culture = app()->getLocale() ?: 'en';
        $serializerFormat = $fmt['serializer'];
        $recordClass = RicToCrmMapper::classFor('rico:Item'); // crm:E22_Human-Made_Object

        // Resolve target object id(s). A single slug/object_id exports one
        // record; absent that, export the whole museum collection.
        $objectIds = [];
        $single = false;
        $slug = trim((string) $request->input('slug', ''));
        $objectId = (int) $request->input('object_id', 0);
        if ($slug !== '') {
            $id = (int) DB::table('slug')->where('slug', $slug)->value('object_id');
            if ($id) {
                $objectIds = [$id];
                $single = true;
            }
        } elseif ($objectId > 0) {
            $objectIds = [$objectId];
            $single = true;
        }

        if (empty($objectIds)) {
            $objectIds = DB::table('museum_metadata')
                ->whereNotNull('object_id')
                ->orderBy('object_id')
                ->pluck('object_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $serializer = new CrmSerializer();
        $docs = [];
        foreach ($objectIds as $id) {
            $body = $serializer->serializeRecord($id, $culture, $serializerFormat, $recordClass);
            if ($body !== '') {
                $docs[] = $body;
            }
        }

        if (empty($docs)) {
            return redirect()
                ->route('museum.cidoc-export')
                ->with('error', 'No museum objects available to export in culture ' . $culture . '.');
        }

        $body = $this->mergeCrmDocuments($docs, $serializerFormat);

        $stamp = date('Ymd-His');
        $base = $single ? 'cidoc-crm-' . $objectIds[0] : 'cidoc-crm-museum-' . $stamp;
        $filename = $base . '.' . $fmt['extension'];

        return response($body, 200, [
            'Content-Type'        => $fmt['mime'] . '; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-CRM-Version'       => '7.1.3',
        ]);
    }

    /**
     * Merge per-record CRM documents into one document of the same
     * format. Each input is a complete standalone serialisation; merging
     * concatenates the inner statements so the result is a single valid
     * graph (one rdf:RDF root / one @graph array / one Turtle body).
     */
    protected function mergeCrmDocuments(array $docs, string $format): string
    {
        if (count($docs) === 1) {
            return $docs[0];
        }

        if ($format === CrmSerializer::FORMAT_JSONLD) {
            $context = null;
            $graph = [];
            foreach ($docs as $doc) {
                $decoded = json_decode($doc, true);
                if (! is_array($decoded)) {
                    continue;
                }
                $context = $context ?? ($decoded['@context'] ?? null);
                foreach ($decoded['@graph'] ?? [] as $node) {
                    $graph[] = $node;
                }
            }
            return json_encode([
                '@context' => $context ?? new \stdClass(),
                '@graph'   => $graph,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($format === CrmSerializer::FORMAT_TURTLE) {
            // Keep the @prefix block from the first doc, then strip the
            // prefix lines from the rest and concatenate the bodies.
            $out = rtrim($docs[0]) . "\n\n";
            foreach (array_slice($docs, 1) as $doc) {
                $lines = preg_split('/\r\n|\r|\n/', $doc);
                $stmt = array_filter($lines, fn ($l) => ! preg_match('/^\s*@prefix\b/', $l));
                $out .= trim(implode("\n", $stmt)) . "\n\n";
            }
            return $out;
        }

        // RDF/XML: keep the <rdf:RDF ...> envelope from the first doc and
        // inject every other doc's inner <rdf:Description> nodes before
        // the closing tag.
        $first = $docs[0];
        $closePos = strrpos($first, '</rdf:RDF>');
        if ($closePos === false) {
            return implode("\n", $docs); // defensive; shouldn't happen
        }
        $head = substr($first, 0, $closePos);
        $inner = '';
        foreach (array_slice($docs, 1) as $doc) {
            if (preg_match('#<rdf:RDF\b[^>]*>(.*)</rdf:RDF>#s', $doc, $m)) {
                $inner .= $m[1];
            }
        }
        return $head . $inner . "</rdf:RDF>\n";
    }

    public function authorityLink(string $slug)
    {
        $actor = DB::table('actor')->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')->join('slug', 'actor.id', '=', 'slug.object_id')->where('slug.slug', $slug)->where('actor_i18n.culture', app()->getLocale())->select('actor.*', 'actor_i18n.authorized_form_of_name', 'slug.slug')->first();
        $linkedAuthorities = [];
        $sources = [];
        return view('ahg-museum::authority.link', compact('actor', 'linkedAuthorities', 'sources'));
    }

    public function authorityLinkStore(Request $request, string $slug)
    {
        return redirect()->route('museum.authority-link', $slug)->with('success', 'Authority linked.');
    }

    public function authorityUnlink(Request $request, string $slug)
    {
        return redirect()->route('museum.authority-link', $slug)->with('success', 'Authority unlinked.');
    }
}
