<?php

/**
 * RepositoryController - Controller for Heratio
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



namespace AhgRepositoryManage\Controllers;

use AhgCore\Constants\TermId;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use AhgRepositoryManage\Services\RepositoryBrowseService;
use AhgRepositoryManage\Services\RepositoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepositoryController extends Controller
{
    protected RepositoryService $service;

    public function __construct()
    {
        $this->service = new RepositoryService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new RepositoryBrowseService($culture);

        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'lastUpdated'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
            'thematicArea' => $request->get('thematicAreas', ''),
            'region' => $request->get('regions', $request->get('region', '')),
            'locality' => $request->get('locality', ''),
            'hasDigitalObject' => $request->get('hasDigitalObject', ''),
            'archiveType' => $request->get('types', $request->get('archiveType', '')),
            'subregion' => $request->get('geographicSubregions', $request->get('subregion', '')),
            'languages' => $request->get('languages', ''),
        ];

        $hasAdvanced = $params['thematicArea'] || $params['region']
            || $params['locality'] || $params['hasDigitalObject']
            || $params['archiveType'] || $params['subregion']
            || $params['languages'];

        $result = $hasAdvanced
            ? $browseService->browseAdvanced($params)
            : $browseService->browse($params);

        $pager = new SimplePager($result);

        $thematicAreaFacets = $browseService->getThematicAreaFacets();
        $regions = $browseService->getRegionFacets();
        $archiveTypeFacets = $browseService->getArchiveTypeFacets();
        $subregionFacets = $browseService->getSubregionFacets();
        $languageFacets = $browseService->getLanguageFacets();
        $localityFacets = $browseService->getLocalityFacets();

        // Thematic area options for advanced search dropdown (full list from taxonomy)
        $thematicAreaOptions = \Illuminate\Support\Facades\DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) { $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', $culture); })
            ->where('term.taxonomy_id', 72)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Repository types for advanced search dropdown
        $repositoryTypes = \Illuminate\Support\Facades\DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) { $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', $culture); })
            ->where('term.taxonomy_id', 37)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // ── Filter tags (active filter pills) ──
        $filterTags = [];

        if (!empty($params['thematicArea'])) {
            $taName = DB::table('term_i18n')->where('id', $params['thematicArea'])->where('culture', $culture)->value('name');
            if ($taName) {
                $filterTags[] = [
                    'label' => 'Thematic area: ' . $taName,
                    'removeUrl' => url('/repository/browse') . '?' . http_build_query($request->except(['thematicAreas', 'page'])),
                ];
            }
        }

        if (!empty($params['archiveType'])) {
            $atName = DB::table('term_i18n')->where('id', $params['archiveType'])->where('culture', $culture)->value('name');
            if ($atName) {
                $filterTags[] = [
                    'label' => 'Archive type: ' . $atName,
                    'removeUrl' => url('/repository/browse') . '?' . http_build_query($request->except(['types', 'page'])),
                ];
            }
        }

        if (!empty($params['region'])) {
            $filterTags[] = [
                'label' => 'Region: ' . $params['region'],
                'removeUrl' => url('/repository/browse') . '?' . http_build_query($request->except(['regions', 'page'])),
            ];
        }

        if (!empty($params['subregion'])) {
            $srName = DB::table('term_i18n')->where('id', $params['subregion'])->where('culture', $culture)->value('name');
            if ($srName) {
                $filterTags[] = [
                    'label' => 'Subregion: ' . $srName,
                    'removeUrl' => url('/repository/browse') . '?' . http_build_query($request->except(['geographicSubregions', 'page'])),
                ];
            }
        }

        if (!empty($params['locality'])) {
            $filterTags[] = [
                'label' => 'Locality: ' . $params['locality'],
                'removeUrl' => url('/repository/browse') . '?' . http_build_query($request->except(['locality', 'page'])),
            ];
        }

        if (!empty($params['languages'])) {
            $langDisplay = locale_get_display_language($params['languages'], 'en') ?: $params['languages'];
            $filterTags[] = [
                'label' => 'Language: ' . ucfirst($langDisplay),
                'removeUrl' => url('/repository/browse') . '?' . http_build_query($request->except(['languages', 'page'])),
            ];
        }

        return view('ahg-repository-manage::browse', [
            'pager' => $pager,
            'thematicAreaFacets' => $thematicAreaFacets,
            'regions' => $regions,
            'archiveTypeFacets' => $archiveTypeFacets,
            'subregionFacets' => $subregionFacets,
            'languageFacets' => $languageFacets,
            'localityFacets' => $localityFacets,
            'thematicAreaOptions' => $thematicAreaOptions,
            'repositoryTypes' => $repositoryTypes,
            'params' => $params,
            'filterTags' => $filterTags,
            'sortOptions' => [
                'lastUpdated' => 'Date modified',
                'alphabetic' => 'Name',
                'identifier' => 'Identifier',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        $contacts = $this->service->getContacts($repository->id);
        $digitalObjects = $this->service->getDigitalObjects($repository->id);
        $holdingsCount = $this->service->getHoldingsCount($repository->id);
        $descStatusName = $this->service->getTermName($repository->desc_status_id);
        $descDetailName = $this->service->getTermName($repository->desc_detail_id);
        $otherNames = $this->service->getOtherNames($repository->id);
        $repositoryTypes = $this->service->getRepositoryTypes($repository->id);
        $languages = $this->service->getLanguages($repository->id);
        $scripts = $this->service->getScripts($repository->id);
        $maintenanceNotes = $this->service->getMaintenanceNotes($repository->id);
        $thematicAreas = $this->service->getThematicAreas($repository->id);
        $geographicSubregions = $this->service->getGeographicSubregions($repository->id);

        // Sidebar: paginated holdings list
        $holdingsPage = (int) request('holdings_page', 1);
        $holdingsPager = $this->service->getHoldingsPaginated($repository->id, 10, $holdingsPage);
        $holdings = $holdingsPager->getCollection();

        // Sidebar: maintained actors
        $maintainedActorsList = $this->service->getMaintainedActors($repository->id, 10, (int) request('actors_page', 1));

        // Source language name
        $sourceLangName = null;
        if ($repository->source_culture ?? null) {
            $langNames = ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'pt' => 'Portuguese', 'de' => 'German', 'nl' => 'Dutch', 'it' => 'Italian', 'af' => 'Afrikaans', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'st' => 'Southern Sotho', 'tn' => 'Tswana', 'ar' => 'Arabic', 'ja' => 'Japanese', 'zh' => 'Chinese'];
            $sourceLangName = $langNames[$repository->source_culture] ?? $repository->source_culture;
        }

        return view('ahg-repository-manage::show', [
            'repository' => $repository,
            'contacts' => $contacts,
            'digitalObjects' => $digitalObjects,
            'holdingsCount' => $holdingsCount,
            'holdings' => $holdings,
            'holdingsPager' => $holdingsPager,
            'maintainedActorsList' => $maintainedActorsList,
            'descStatusName' => $descStatusName,
            'descDetailName' => $descDetailName,
            'otherNames' => $otherNames,
            'repositoryTypes' => $repositoryTypes,
            'languages' => $languages,
            'scripts' => $scripts,
            'maintenanceNotes' => $maintenanceNotes,
            'thematicAreas' => $thematicAreas,
            'geographicSubregions' => $geographicSubregions,
            'sourceLangName' => $sourceLangName,
        ]);
    }

    /**
     * Print-friendly view for a repository.
     */
    public function print(string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        $contacts = $this->service->getContacts($repository->id);
        $holdingsCount = $this->service->getHoldingsCount($repository->id);
        $descStatusName = $this->service->getTermName($repository->desc_status_id);
        $descDetailName = $this->service->getTermName($repository->desc_detail_id);
        $otherNames = $this->service->getOtherNames($repository->id);
        $repositoryTypes = $this->service->getRepositoryTypes($repository->id);
        $languages = $this->service->getLanguages($repository->id);
        $scripts = $this->service->getScripts($repository->id);
        $maintenanceNotes = $this->service->getMaintenanceNotes($repository->id);
        $thematicAreas = $this->service->getThematicAreas($repository->id);
        $geographicSubregions = $this->service->getGeographicSubregions($repository->id);

        return view('ahg-repository-manage::print', [
            'repository' => $repository,
            'contacts' => $contacts,
            'holdingsCount' => $holdingsCount,
            'descStatusName' => $descStatusName,
            'descDetailName' => $descDetailName,
            'otherNames' => $otherNames,
            'repositoryTypes' => $repositoryTypes,
            'languages' => $languages,
            'scripts' => $scripts,
            'maintenanceNotes' => $maintenanceNotes,
            'thematicAreas' => $thematicAreas,
            'geographicSubregions' => $geographicSubregions,
        ]);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        return view('ahg-repository-manage::edit', [
            'repository' => null,
            'contacts' => collect(),
            'formChoices' => $formChoices,
            'maintenanceNotes' => null,
            'parallelNames' => collect(),
            'otherNames' => collect(),
        ]);
    }

    public function edit(string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        $contacts = $this->service->getContacts($repository->id);
        $formChoices = $this->service->getFormChoices();
        $maintenanceNotes = $this->service->getMaintenanceNotes($repository->id);
        $otherNamesAll = $this->service->getOtherNames($repository->id);
        $parallelNames = $otherNamesAll->where('type_id', TermId::OTHER_NAME_PARALLEL);
        $otherNames = $otherNamesAll->where('type_id', TermId::OTHER_NAME_OTHER_FORM);

        return view('ahg-repository-manage::edit', [
            'repository' => $repository,
            'contacts' => $contacts,
            'formChoices' => $formChoices,
            'maintenanceNotes' => $maintenanceNotes,
            'parallelNames' => $parallelNames,
            'otherNames' => $otherNames,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'authorized_form_of_name' => 'required|string|max:1024',
            'identifier' => 'nullable|string|max:1024',
        ]);

        $data = $request->only($this->getAllFields());

        $id = $this->service->create($data);
        $slug = $this->service->getSlug($id);

        return redirect()
            ->route('repository.show', $slug)
            ->with('success', 'Repository created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        $request->validate([
            'authorized_form_of_name' => 'required|string|max:1024',
            'identifier' => 'nullable|string|max:1024',
        ]);

        $data = $request->only($this->getAllFields());

        $this->service->update($repository->id, $data);

        return redirect()
            ->route('repository.show', $slug)
            ->with('success', 'Repository updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        $holdingsCount = $this->service->getHoldingsCount($repository->id);

        return view('ahg-repository-manage::delete', [
            'repository' => $repository,
            'holdingsCount' => $holdingsCount,
        ]);
    }

    public function destroy(Request $request, string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        $this->service->delete($repository->id);

        return redirect()
            ->route('repository.browse')
            ->with('success', 'Repository deleted successfully.');
    }

    /**
     * Edit theme form (GET).
     */
    public function editTheme(string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        return view('ahg-repository-manage::edit-theme', [
            'repository' => $repository,
        ]);
    }

    /**
     * Update theme (POST).
     */
    public function updateTheme(Request $request, string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        $request->validate([
            'backgroundColor' => 'nullable|string|max:7',
            'htmlSnippet' => 'nullable|string',
            'banner' => 'nullable|image|max:5120',
            'logo' => 'nullable|image|max:5120',
        ]);

        $data = $request->only(['backgroundColor', 'htmlSnippet']);

        $this->service->updateTheme($repository->id, $data, $request);

        return redirect()
            ->route('repository.show', $slug)
            ->with('success', 'Theme updated successfully.');
    }

    /**
     * Edit upload limit (POST via AJAX modal).
     */
    public function editUploadLimit(Request $request, string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        $request->validate([
            'uploadLimit.type' => 'required|in:disabled,limited,unlimited',
            'uploadLimit.value' => 'nullable|numeric|min:0',
        ]);

        $type = $request->input('uploadLimit.type');
        $value = $request->input('uploadLimit.value');

        if ($type === 'disabled') {
            $uploadLimit = 0;
        } elseif ($type === 'unlimited') {
            $uploadLimit = -1;
        } else {
            $uploadLimit = max(0, (float) $value);
        }

        DB::table('repository')
            ->where('id', $repository->id)
            ->update(['upload_limit' => $uploadLimit]);

        return redirect()
            ->route('repository.show', $slug)
            ->with('success', 'Upload limit updated successfully.');
    }

    /**
     * Upload limit exceeded page (GET).
     */
    public function uploadLimitExceeded(string $slug)
    {
        $repository = $this->service->getBySlug($slug);
        if (!$repository) {
            abort(404);
        }

        // Get system admin email
        $adminEmail = DB::table('user')
            ->join('actor_i18n', function ($j) {
                $j->on('user.id', '=', 'actor_i18n.id')
                  ->where('actor_i18n.culture', '=', app()->getLocale());
            })
            ->orderBy('user.id')
            ->value('user.email') ?? '';

        return view('ahg-repository-manage::upload-limit-exceeded', [
            'repository' => $repository,
            'adminEmail' => $adminEmail,
        ]);
    }

    /**
     * Autocomplete for repository names (JSON).
     */
    public function autocomplete(Request $request)
    {
        $query = $request->get('query', '');
        $culture = app()->getLocale();
        $limit = $request->get('limit', 10);

        $results = DB::table('actor')
            ->join('actor_i18n', function ($j) use ($culture) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                  ->where('actor_i18n.culture', '=', $culture);
            })
            ->join('repository', 'repository.id', '=', 'actor.id')
            ->join('slug', 'slug.object_id', '=', 'actor.id')
            ->where('actor_i18n.authorized_form_of_name', 'LIKE', '%' . $query . '%')
            ->select(
                'actor.id',
                'actor_i18n.authorized_form_of_name as name',
                'slug.slug'
            )
            ->limit($limit)
            ->get();

        return response()->json($results);
    }

    /**
     * All form field names accepted by store/update.
     */
    private function getAllFields(): array
    {
        return [
            // Actor i18n (ISAAR)
            'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
            'legal_status', 'functions', 'mandates', 'internal_structures',
            'general_context', 'institution_responsible_identifier', 'rules',
            'sources', 'revision_history',
            // Repository
            'identifier', 'desc_status_id', 'desc_detail_id', 'desc_identifier', 'upload_limit',
            // Repository i18n (ISDIAH)
            'geocultural_context', 'collecting_policies', 'buildings', 'holdings',
            'finding_aids', 'opening_times', 'access_conditions', 'disabled_access',
            'research_services', 'reproduction_services', 'public_facilities',
            'desc_institution_identifier', 'desc_rules', 'desc_sources', 'desc_revision_history',
            // Special fields (stored in other_name / note tables)
            'parallel_name', 'other_name', 'maintenance_notes',
            // Contacts
            'contacts',
            // ICIP cultural-sensitivity URI (issue #36 Phase 2b) — persisted to actor.icip_sensitivity.
            'icip_sensitivity',
        ];
    }
}
