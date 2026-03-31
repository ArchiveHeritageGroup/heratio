<?php

/**
 * MuseumController - Controller for Heratio
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



namespace AhgMuseum\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\SettingHelper;
use AhgMuseum\Services\MuseumService;
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

        $workType = $request->get('work_type');
        if ($workType) {
            $params['filters']['work_type'] = $workType;
        }

        $classification = $request->get('classification');
        if ($classification) {
            $params['filters']['classification'] = $classification;
        }

        $result = $this->service->browse($params);
        $pager = new SimplePager($result);

        return view('ahg-museum::museum.browse', [
            'pager' => $pager,
            'workTypes' => $result['workTypes'] ?? [],
            'classifications' => $result['classifications'] ?? [],
            'selectedWorkType' => $workType,
            'selectedClassification' => $classification,
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

        return view('ahg-museum::museum.show', [
            'museum' => $museum,
            'digitalObjects' => $digitalObjects,
            'repository' => $repository,
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
        ]);

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
        ]);

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
        $totalItems = DB::table('museum_metadata')->count();
        $itemsWithMedia = DB::table('museum_metadata as mm')->join('digital_object', 'mm.object_id', '=', 'digital_object.object_id')->distinct('mm.object_id')->count('mm.object_id');
        $itemsWithCondition = DB::table('museum_metadata')->whereNotNull('condition_term')->count();
        $recentCount = DB::table('museum_metadata as mm')->join('object', 'mm.object_id', '=', 'object.id')->where('object.created_at', '>=', now()->subDays(30))->count();
        return view('ahg-museum::museum.dashboard', compact('totalItems', 'itemsWithMedia', 'itemsWithCondition', 'recentCount'));
    }

    public function reports()
    {
        $stats = [
            'totalObjects' => DB::table('museum_metadata')->count(),
            'withProvenance' => DB::table('museum_metadata')->whereNotNull('provenance_text')->where('provenance_text', '!=', '')->count(),
            'byCondition' => DB::table('museum_metadata')->whereNotNull('condition_term')->select('condition_term', DB::raw('COUNT(*) as cnt'))->groupBy('condition_term')->get(),
        ];
        return view('ahg-museum::reports.index', compact('stats'));
    }

    public function reportObjects() { return view('ahg-museum::reports.objects', ['objects' => collect()]); }
    public function reportCreators() { return view('ahg-museum::reports.creators', ['creators' => collect()]); }
    public function reportCondition() { return view('ahg-museum::reports.condition', ['records' => collect()]); }
    public function reportProvenance() { return view('ahg-museum::reports.provenance', ['records' => collect()]); }
    public function reportStylePeriod() { return view('ahg-museum::reports.style-period', ['byStyle' => collect(), 'byPeriod' => collect()]); }
    public function reportMaterials() { return view('ahg-museum::reports.materials', ['records' => collect()]); }

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

    public function provenance(string $slug)
    {
        $museum = $this->service->getBySlug($slug);
        if (!$museum) abort(404);
        $provenanceChain = collect();
        return view('ahg-museum::museum.provenance', ['resource' => $museum, 'provenanceChain' => $provenanceChain]);
    }

    public function objectComparison(Request $request, string $slug)
    {
        $objects = collect();
        return view('ahg-museum::museum.object-comparison', compact('objects'));
    }

    public function qualityDashboard()
    {
        $overallScore = 0; $analyzedRecords = 0; $overallGrade = ['grade' => 'N/A', 'label' => '']; $missingFieldCounts = [];
        return view('ahg-museum::dashboard.index', compact('overallScore', 'analyzedRecords', 'overallGrade', 'missingFieldCounts'));
    }

    public function missingField(string $field)
    {
        $fieldName = $field; $records = collect();
        return view('ahg-museum::dashboard.missing-field', compact('fieldName', 'records'));
    }

    public function cidocExport()
    {
        $formats = ['rdf' => ['label' => 'RDF/XML', 'extension' => 'rdf'], 'jsonld' => ['label' => 'JSON-LD', 'extension' => 'jsonld']];
        $includeLinkedData = false;
        return view('ahg-museum::cidoc.export', compact('formats', 'includeLinkedData'));
    }

    public function cidocExportDownload(Request $request)
    {
        return redirect()->route('museum.cidoc-export')->with('success', 'Export started.');
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
