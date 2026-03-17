<?php

namespace AhgRepositoryManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use AhgRepositoryManage\Services\RepositoryBrowseService;
use AhgRepositoryManage\Services\RepositoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
            'thematicArea' => $request->get('thematicAreas', ''),
            'region' => $request->get('region', ''),
            'locality' => $request->get('locality', ''),
            'hasDigitalObject' => $request->get('hasDigitalObject', ''),
            'archiveType' => $request->get('archiveType', ''),
            'subregion' => $request->get('subregion', ''),
        ];

        $hasAdvanced = $params['thematicArea'] || $params['region']
            || $params['locality'] || $params['hasDigitalObject']
            || $params['archiveType'] || $params['subregion'];

        $result = $hasAdvanced
            ? $browseService->browseAdvanced($params)
            : $browseService->browse($params);

        $pager = new SimplePager($result);

        $thematicAreaFacets = $browseService->getThematicAreaFacets();
        $regions = $browseService->getRegionFacets();
        $archiveTypeFacets = $browseService->getArchiveTypeFacets();
        $subregionFacets = $browseService->getSubregionFacets();

        return view('ahg-repository-manage::browse', [
            'pager' => $pager,
            'thematicAreaFacets' => $thematicAreaFacets,
            'regions' => $regions,
            'archiveTypeFacets' => $archiveTypeFacets,
            'subregionFacets' => $subregionFacets,
            'params' => $params,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
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

        return view('ahg-repository-manage::show', [
            'repository' => $repository,
            'contacts' => $contacts,
            'digitalObjects' => $digitalObjects,
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

        return view('ahg-repository-manage::edit', [
            'repository' => $repository,
            'contacts' => $contacts,
            'formChoices' => $formChoices,
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
            // Contacts
            'contacts',
        ];
    }
}
