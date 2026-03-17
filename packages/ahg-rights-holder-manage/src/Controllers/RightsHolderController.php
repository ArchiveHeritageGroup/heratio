<?php

namespace AhgRightsHolderManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgRightsHolderManage\Services\RightsHolderBrowseService;
use AhgRightsHolderManage\Services\RightsHolderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RightsHolderController extends Controller
{
    protected RightsHolderService $service;

    public function __construct()
    {
        $this->service = new RightsHolderService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new RightsHolderBrowseService($culture);

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
        ]);

        return view('ahg-rights-holder-manage::browse', [
            'pager' => new SimplePager($result),
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
                'identifier' => 'Identifier',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $rh = $this->service->getBySlug($slug);
        if (!$rh) abort(404);

        $rights = $this->service->getRelatedRights($rh->id);
        $basisIds = $rights->pluck('basis_id')->filter()->unique()->values()->toArray();
        $contacts = $this->service->getContacts($rh->id);

        return view('ahg-rights-holder-manage::show', [
            'rightsHolder' => $rh,
            'rights' => $rights,
            'basisNames' => $this->service->getTermNames($basisIds),
            'contacts' => $contacts,
        ]);
    }

    public function create()
    {
        return view('ahg-rights-holder-manage::edit', [
            'rightsHolder' => null,
            'contacts' => collect(),
        ]);
    }

    public function edit(string $slug)
    {
        $rh = $this->service->getBySlug($slug);
        if (!$rh) abort(404);

        return view('ahg-rights-holder-manage::edit', [
            'rightsHolder' => $rh,
            'contacts' => $this->service->getContacts($rh->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['authorized_form_of_name' => 'required|string|max:1024']);
        $id = $this->service->create($request->only($this->fields()));
        return redirect()->route('rightsholder.show', $this->service->getSlug($id))->with('success', 'Rights holder created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $rh = $this->service->getBySlug($slug);
        if (!$rh) abort(404);
        $request->validate(['authorized_form_of_name' => 'required|string|max:1024']);
        $this->service->update($rh->id, $request->only($this->fields()));
        return redirect()->route('rightsholder.show', $slug)->with('success', 'Rights holder updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $rh = $this->service->getBySlug($slug);
        if (!$rh) abort(404);
        return view('ahg-rights-holder-manage::delete', ['rightsHolder' => $rh]);
    }

    public function destroy(string $slug)
    {
        $rh = $this->service->getBySlug($slug);
        if (!$rh) abort(404);
        $this->service->delete($rh->id);
        return redirect()->route('rightsholder.browse')->with('success', 'Rights holder deleted successfully.');
    }

    private function fields(): array
    {
        return [
            'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
            'legal_status', 'functions', 'mandates', 'internal_structures',
            'general_context', 'institution_responsible_identifier', 'rules',
            'sources', 'revision_history', 'description_identifier',
            'corporate_body_identifiers', 'contacts',
        ];
    }
}
