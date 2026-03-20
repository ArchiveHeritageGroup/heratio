<?php

namespace AhgAccessionManage\Controllers;

use AhgAccessionManage\Services\AccessionBrowseService;
use AhgAccessionManage\Services\AccessionService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessionController extends Controller
{
    protected AccessionService $service;

    public function __construct()
    {
        $this->service = new AccessionService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new AccessionBrowseService($culture);

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'lastUpdated'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);
        $termNames = $browseService->resolveTermNames($result['hits'] ?? []);

        return view('ahg-accession-manage::browse', [
            'pager' => $pager,
            'termNames' => $termNames,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'identifier' => 'Accession number',
                'date' => 'Acquisition date',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    public function exportCsv()
    {
        $culture = app()->getLocale();

        $rows = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->join('object', 'accession.id', '=', 'object.id')
            ->leftJoin('term_i18n as status_term', function ($j) use ($culture) {
                $j->on('accession.processing_status_id', '=', 'status_term.id')
                  ->where('status_term.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as priority_term', function ($j) use ($culture) {
                $j->on('accession.processing_priority_id', '=', 'priority_term.id')
                  ->where('priority_term.culture', '=', $culture);
            })
            ->where('accession_i18n.culture', $culture)
            ->select([
                'accession.identifier',
                'accession_i18n.title',
                'accession.date',
                'status_term.name as status',
                'priority_term.name as priority',
                'object.created_at',
                'object.updated_at',
            ])
            ->orderBy('accession.identifier')
            ->get();

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Identifier', 'Title', 'Accession Date', 'Status', 'Priority', 'Created', 'Updated']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->identifier,
                    $row->title,
                    $row->date,
                    $row->status ?? '',
                    $row->priority ?? '',
                    $row->created_at,
                    $row->updated_at,
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="accessions-' . date('Ymd') . '.csv"',
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
            abort(404);
        }

        // Resolve term names for type/priority/status IDs
        $termIds = array_filter([
            $accession->acquisition_type_id,
            $accession->processing_priority_id,
            $accession->processing_status_id,
            $accession->resource_type_id,
        ]);
        $termNames = $this->service->getTermNames($termIds);

        // Get donor via relation table
        $donor = $this->service->getDonor($accession->id);

        // Get deaccessions
        $deaccessions = $this->service->getDeaccessions($accession->id);

        // Resolve deaccession scope term names
        $scopeIds = $deaccessions->pluck('scope_id')->filter()->unique()->values()->toArray();
        $scopeNames = $this->service->getTermNames($scopeIds);

        return view('ahg-accession-manage::show', [
            'accession' => $accession,
            'termNames' => $termNames,
            'donor' => $donor,
            'deaccessions' => $deaccessions,
            'scopeNames' => $scopeNames,
        ]);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        return view('ahg-accession-manage::edit', [
            'accession' => null,
            'donor' => null,
            'donorContact' => null,
            'formChoices' => $formChoices,
        ]);
    }

    public function edit(string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
            abort(404);
        }

        $donor = $this->service->getDonor($accession->id);
        $donorContact = null;
        if ($donor) {
            $donorContact = DB::table('contact_information')
                ->leftJoin('contact_information_i18n', function ($j) {
                    $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                      ->where('contact_information_i18n.culture', '=', app()->getLocale());
                })
                ->where('contact_information.actor_id', $donor->id ?? 0)
                ->select('contact_information.*', 'contact_information_i18n.*')
                ->first();
        }
        $formChoices = $this->service->getFormChoices();

        return view('ahg-accession-manage::edit', [
            'accession' => $accession,
            'donor' => $donor,
            'donorContact' => $donorContact,
            'formChoices' => $formChoices,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255|unique:accession,identifier',
            'title' => 'nullable|string|max:1024',
            'date' => 'nullable|date',
            'acquisition_type_id' => 'nullable|integer|exists:term,id',
            'processing_priority_id' => 'nullable|integer|exists:term,id',
            'processing_status_id' => 'nullable|integer|exists:term,id',
            'resource_type_id' => 'nullable|integer|exists:term,id',
            'scope_and_content' => 'nullable|string',
            'archival_history' => 'nullable|string',
            'source_of_acquisition' => 'nullable|string',
            'location_information' => 'nullable|string',
            'received_extent_units' => 'nullable|string',
            'physical_characteristics' => 'nullable|string',
            'appraisal' => 'nullable|string',
            'processing_notes' => 'nullable|string',
        ]);

        $data = $request->only([
            'identifier', 'title', 'date',
            'acquisition_type_id', 'processing_priority_id',
            'processing_status_id', 'resource_type_id',
            'scope_and_content', 'archival_history', 'source_of_acquisition',
            'location_information', 'received_extent_units', 'physical_characteristics',
            'appraisal', 'processing_notes',
        ]);

        $id = $this->service->create($data);
        $slug = $this->service->getSlug($id);

        return redirect()
            ->route('accession.show', $slug)
            ->with('success', 'Accession record created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
            abort(404);
        }

        $request->validate([
            'identifier' => 'required|string|max:255|unique:accession,identifier,' . $accession->id,
            'title' => 'nullable|string|max:1024',
            'date' => 'nullable|date',
            'acquisition_type_id' => 'nullable|integer|exists:term,id',
            'processing_priority_id' => 'nullable|integer|exists:term,id',
            'processing_status_id' => 'nullable|integer|exists:term,id',
            'resource_type_id' => 'nullable|integer|exists:term,id',
            'scope_and_content' => 'nullable|string',
            'archival_history' => 'nullable|string',
            'source_of_acquisition' => 'nullable|string',
            'location_information' => 'nullable|string',
            'received_extent_units' => 'nullable|string',
            'physical_characteristics' => 'nullable|string',
            'appraisal' => 'nullable|string',
            'processing_notes' => 'nullable|string',
        ]);

        $data = $request->only([
            'identifier', 'title', 'date',
            'acquisition_type_id', 'processing_priority_id',
            'processing_status_id', 'resource_type_id',
            'scope_and_content', 'archival_history', 'source_of_acquisition',
            'location_information', 'received_extent_units', 'physical_characteristics',
            'appraisal', 'processing_notes',
        ]);

        $this->service->update($accession->id, $data);

        return redirect()
            ->route('accession.show', $slug)
            ->with('success', 'Accession record updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
            abort(404);
        }

        return view('ahg-accession-manage::delete', [
            'accession' => $accession,
        ]);
    }

    public function destroy(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
            abort(404);
        }

        $this->service->delete($accession->id);

        return redirect()
            ->route('accession.browse')
            ->with('success', 'Accession record deleted successfully.');
    }
}
