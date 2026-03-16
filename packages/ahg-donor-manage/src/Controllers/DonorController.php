<?php

namespace AhgDonorManage\Controllers;

use AhgDonorManage\Services\DonorBrowseService;
use AhgDonorManage\Services\DonorService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    protected DonorService $service;

    public function __construct()
    {
        $this->service = new DonorService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new DonorBrowseService($culture);

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ]);

        return view('ahg-donor-manage::browse', [
            'pager' => new SimplePager($result),
            'sortOptions' => ['alphabetic' => 'Name', 'lastUpdated' => 'Date modified'],
        ]);
    }

    public function show(string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);

        return view('ahg-donor-manage::show', [
            'donor' => $donor,
            'contacts' => $this->service->getContacts($donor->id),
            'accessions' => $this->service->getRelatedAccessions($donor->id),
        ]);
    }

    public function create()
    {
        return view('ahg-donor-manage::edit', [
            'donor' => null,
            'contacts' => collect(),
        ]);
    }

    public function edit(string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);

        return view('ahg-donor-manage::edit', [
            'donor' => $donor,
            'contacts' => $this->service->getContacts($donor->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['authorized_form_of_name' => 'required|string|max:1024']);
        $id = $this->service->create($request->only($this->fields()));
        return redirect()->route('donor.show', $this->service->getSlug($id))->with('success', 'Donor created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);
        $request->validate(['authorized_form_of_name' => 'required|string|max:1024']);
        $this->service->update($donor->id, $request->only($this->fields()));
        return redirect()->route('donor.show', $slug)->with('success', 'Donor updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);
        return view('ahg-donor-manage::delete', ['donor' => $donor]);
    }

    public function destroy(string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);
        $this->service->delete($donor->id);
        return redirect()->route('donor.browse')->with('success', 'Donor deleted successfully.');
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
